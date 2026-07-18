<?php
/**
 * Bounded background Telegram delivery queue.
 *
 * @package TelegrARM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Queue, rate-limit, and retry bounded Telegram deliveries. */
final class TelegrARM_Delivery_Queue {
	const HOOK         = 'telegrarm_process_delivery';
	const MAX_ATTEMPTS = 3;

	/**
	 * Register queue processing.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( self::HOOK, array( __CLASS__, 'process' ), 10, 1 );
	}

	/**
	 * Add a bounded delivery payload to WP-Cron.
	 *
	 * @param string $method Bot API method.
	 * @param string $target Configuration target.
	 * @param array  $body   Request body without credentials or chat ID.
	 * @return bool
	 */
	public static function enqueue( $method, $target, array $body ) {
		$payload = self::sanitize_payload(
			array(
				'method'  => $method,
				'target'  => $target,
				'body'    => $body,
				'attempt' => 0,
			)
		);

		if ( empty( $payload ) ) {
			return false;
		}

		$dedupe_key = 'telegrarm_delivery_' . md5( http_build_query( $payload, '', '&', PHP_QUERY_RFC3986 ) );

		if ( get_transient( $dedupe_key ) ) {
			return true;
		}

		set_transient( $dedupe_key, 1, MINUTE_IN_SECONDS );

		return false !== wp_schedule_single_event( time() + 1, self::HOOK, array( $payload ) );
	}

	/**
	 * Process one queued request.
	 *
	 * @param array $payload Queue payload.
	 * @return void
	 */
	public static function process( $payload ) {
		$payload = self::sanitize_payload( $payload );

		if ( empty( $payload ) ) {
			return;
		}

		$channel_id = TelegrARM_Config::get_channel_id( $payload['target'] );

		if ( '' === $channel_id ) {
			TelegrARM_Debug_Logger::log( 'Queued delivery skipped: channel is not configured.', array( 'target' => $payload['target'] ) );
			return;
		}

		$rate_key  = 'telegrarm_rate_' . md5( $channel_id );
		$last_send = (int) get_transient( $rate_key );

		if ( $last_send >= time() ) {
			wp_schedule_single_event( $last_send + 1, self::HOOK, array( $payload ) );
			return;
		}

		set_transient( $rate_key, time(), 2 * MINUTE_IN_SECONDS );
		$body     = array_merge( $payload['body'], array( 'chat_id' => $channel_id ) );
		$client   = new TelegrARM_Telegram_Client();
		$response = $client->send( $payload['method'], $body );

		if ( ! is_wp_error( $response ) && TelegrARM_Telegram_Client::is_success( $response ) ) {
			return;
		}

		self::retry( $payload, $response );
	}

	/**
	 * Retry transient failures with a bounded delay.
	 *
	 * @param array                         $payload  Queue payload.
	 * @param array<string, mixed>|WP_Error $response Failed response.
	 * @return void
	 */
	private static function retry( array $payload, $response ) {
		++$payload['attempt'];

		if ( $payload['attempt'] >= self::MAX_ATTEMPTS ) {
			TelegrARM_Debug_Logger::log( 'Telegram delivery abandoned after bounded retries.', array( 'attempt' => $payload['attempt'] ) );
			return;
		}

		$details     = is_wp_error( $response )
			? array(
				'status_code' => 0,
				'retry_after' => 0,
				'error_code'  => $response->get_error_code(),
			)
			: TelegrARM_Telegram_Client::response_details( $response );
		$status_code = (int) $details['status_code'];

		if ( ! is_wp_error( $response ) && 429 !== $status_code && $status_code < 500 ) {
			TelegrARM_Debug_Logger::log( 'Telegram delivery rejected without retry.', $details );
			return;
		}

		$delay = isset( $details['retry_after'] ) && null !== $details['retry_after'] && 0 < $details['retry_after']
			? (int) $details['retry_after']
			: 5 * $payload['attempt'];
		wp_schedule_single_event( time() + min( 300, max( 1, $delay ) ), self::HOOK, array( $payload ) );
	}

	/**
	 * Validate and bound a queue payload.
	 *
	 * @param mixed $payload Raw payload.
	 * @return array<string, mixed>
	 */
	private static function sanitize_payload( $payload ) {
		if ( ! is_array( $payload ) ) {
			return array();
		}

		$method = isset( $payload['method'] ) && is_scalar( $payload['method'] ) ? (string) $payload['method'] : '';
		$target = isset( $payload['target'] ) && is_scalar( $payload['target'] ) ? (string) $payload['target'] : '';
		$body   = isset( $payload['body'] ) && is_array( $payload['body'] ) ? $payload['body'] : array();

		if ( ! in_array( $method, array( 'sendMessage', 'sendContact' ), true ) || ! in_array( $target, array( 'new-user', 'profile' ), true ) ) {
			return array();
		}

		$allowed_body_keys = 'sendContact' === $method
			? array( 'phone_number', 'first_name', 'last_name' )
			: array( 'text', 'parse_mode' );
		$sanitized_body    = array();

		foreach ( $allowed_body_keys as $key ) {
			if ( ! isset( $body[ $key ] ) || ! is_scalar( $body[ $key ] ) ) {
				continue;
			}

			$limit                  = 'text' === $key ? TelegrARM_Message_Formatter::SAFE_TEXT_LIMIT : 200;
			$value                  = 'text' === $key
				? (string) $body[ $key ]
				: sanitize_text_field( (string) $body[ $key ] );
			$sanitized_body[ $key ] = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );
		}

		if ( empty( $sanitized_body ) ) {
			return array();
		}

		return array(
			'method'  => $method,
			'target'  => $target,
			'body'    => $sanitized_body,
			'attempt' => isset( $payload['attempt'] ) ? min( self::MAX_ATTEMPTS, max( 0, (int) $payload['attempt'] ) ) : 0,
		);
	}
}
