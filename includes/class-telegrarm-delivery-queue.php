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
	const HOOK              = 'telegrarm_process_delivery';
	const GC_HOOK           = 'telegrarm_cleanup_deliveries';
	const MAX_ATTEMPTS      = 3;
	const MIN_SEND_INTERVAL = 4;
	const TICKET_PREFIX     = 'telegrarm_job_';
	// Generous, because WP-Cron only fires on visitor requests: a quiet site can
	// leave a scheduled delivery waiting for a long time, and a late notification
	// is better than a reaped one.
	const TICKET_TTL = 3 * DAY_IN_SECONDS;

	/**
	 * Register queue processing and expired-payload cleanup.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( self::HOOK, array( __CLASS__, 'process' ), 10, 1 );
		add_action( self::GC_HOOK, array( __CLASS__, 'collect_garbage' ) );

		if ( false === wp_next_scheduled( self::GC_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::GC_HOOK );
		}
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

		TelegrARM_Debug_Logger::log(
			'Telegram delivery queued.',
			array(
				'method' => $payload['method'],
				'target' => $payload['target'],
			)
		);

		return self::schedule( $payload, time() + 1 );
	}

	/**
	 * Store a payload behind an opaque ticket and schedule its delivery.
	 *
	 * Member data is kept out of the autoloaded `cron` option: only the ticket
	 * is passed as a cron argument. The payload itself lives in a
	 * non-autoloaded option rather than a transient, so it survives an object
	 * cache flush between scheduling and delivery.
	 *
	 * @param array  $payload   Queue payload.
	 * @param int    $timestamp Scheduled timestamp.
	 * @param string $ticket    Existing ticket to reuse, or empty to mint one.
	 * @return bool
	 */
	private static function schedule( array $payload, $timestamp, $ticket = '' ) {
		$is_new_ticket = ! is_string( $ticket ) || '' === $ticket;

		if ( $is_new_ticket ) {
			$ticket = self::TICKET_PREFIX . wp_generate_password( 20, false, false );
		}

		self::remember( $ticket, $payload );

		if ( false !== wp_schedule_single_event( (int) $timestamp, self::HOOK, array( $ticket ) ) ) {
			return true;
		}

		if ( $is_new_ticket ) {
			TelegrARM_Debug_Logger::log( 'Telegram delivery could not be scheduled; stored payload discarded.' );
			self::forget( $ticket );
			return false;
		}

		// A reused ticket was refused as a duplicate, so an equivalent event is
		// already scheduled. Keep the payload: deleting it would strand that event.
		TelegrARM_Debug_Logger::log(
			'Telegram delivery reschedule refused as duplicate; stored payload retained.',
			array( 'target' => $payload['target'] )
		);

		return true;
	}

	/**
	 * Persist a payload against its ticket with a bounded lifetime.
	 *
	 * @param string $ticket  Ticket identifier.
	 * @param array  $payload Queue payload.
	 * @return void
	 */
	private static function remember( $ticket, array $payload ) {
		$record = array(
			'payload' => $payload,
			'expires' => time() + self::TICKET_TTL,
		);

		if ( false === add_option( $ticket, $record, '', false ) ) {
			update_option( $ticket, $record, false );
		}
	}

	/**
	 * Read a stored payload, discarding it once it has expired.
	 *
	 * @param string $ticket Ticket identifier.
	 * @return array<string, mixed>
	 */
	private static function recall( $ticket ) {
		$record = get_option( $ticket, false );

		if ( ! is_array( $record ) || ! isset( $record['payload'] ) || ! is_array( $record['payload'] ) ) {
			// Tickets queued before 1.1.2 stored the payload in a transient.
			$legacy = get_transient( $ticket );

			return is_array( $legacy ) ? $legacy : array();
		}

		// A record without a usable expiry is a partial write; reap it rather than keep it forever.
		if ( ! isset( $record['expires'] ) || ! is_scalar( $record['expires'] ) || (int) $record['expires'] < time() ) {
			self::forget( $ticket );
			return array();
		}

		return $record['payload'];
	}

	/**
	 * Discard a stored payload once it is delivered or abandoned.
	 *
	 * @param string $ticket Ticket identifier.
	 * @return void
	 */
	private static function forget( $ticket ) {
		if ( is_string( $ticket ) && '' !== $ticket ) {
			delete_option( $ticket );
			delete_transient( $ticket );
		}
	}

	/**
	 * Remove payloads whose cron event never ran before they expired.
	 *
	 * Stored payloads are options now, so nothing removes them automatically.
	 *
	 * @return void
	 */
	public static function collect_garbage() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Randomized ticket names cannot be resolved through the options API.
		$tickets = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( self::TICKET_PREFIX ) . '%'
			)
		);

		foreach ( (array) $tickets as $ticket ) {
			// recall() deletes the record when it has expired.
			self::recall( (string) $ticket );
		}
	}

	/**
	 * Process one queued request.
	 *
	 * @param array|string $payload Ticket identifier, or a legacy inline payload.
	 * @return void
	 */
	public static function process( $payload ) {
		$ticket = is_string( $payload ) ? $payload : '';

		if ( '' !== $ticket ) {
			$stored = self::recall( $ticket );

			if ( empty( $stored ) ) {
				TelegrARM_Debug_Logger::log( 'Queued delivery skipped: stored payload expired.' );
				return;
			}

			$payload = $stored;
		}

		$payload = self::sanitize_payload( $payload );

		if ( empty( $payload ) ) {
			TelegrARM_Debug_Logger::log( 'Queued delivery skipped: payload failed validation.' );
			self::forget( $ticket );
			return;
		}

		$channel_id = TelegrARM_Config::get_channel_id( $payload['target'] );

		if ( '' === $channel_id ) {
			TelegrARM_Debug_Logger::log( 'Queued delivery skipped: channel is not configured.', array( 'target' => $payload['target'] ) );
			self::forget( $ticket );
			return;
		}

		$rate_key  = 'telegrarm_rate_' . md5( $channel_id );
		$last_send = (int) get_transient( $rate_key );
		$next_slot = $last_send + self::MIN_SEND_INTERVAL;

		if ( $next_slot > time() ) {
			self::schedule( $payload, $next_slot, $ticket );
			return;
		}

		set_transient( $rate_key, time(), 2 * MINUTE_IN_SECONDS );
		$body     = array_merge( $payload['body'], array( 'chat_id' => $channel_id ) );
		$client   = new TelegrARM_Telegram_Client();
		$response = $client->send( $payload['method'], $body );

		if ( ! is_wp_error( $response ) && TelegrARM_Telegram_Client::is_success( $response ) ) {
			TelegrARM_Debug_Logger::log(
				'Telegram delivery sent.',
				array(
					'method' => $payload['method'],
					'target' => $payload['target'],
				)
			);
			self::forget( $ticket );
			return;
		}

		self::retry( $payload, $response, $ticket );
	}

	/**
	 * Retry transient failures with a bounded delay.
	 *
	 * @param array                         $payload  Queue payload.
	 * @param array<string, mixed>|WP_Error $response Failed response.
	 * @param string                        $ticket   Ticket holding the payload.
	 * @return void
	 */
	private static function retry( array $payload, $response, $ticket = '' ) {
		++$payload['attempt'];

		if ( $payload['attempt'] >= self::MAX_ATTEMPTS ) {
			TelegrARM_Debug_Logger::log(
				'Telegram delivery abandoned after bounded retries.',
				array(
					'attempt' => $payload['attempt'],
					'method'  => $payload['method'],
					'target'  => $payload['target'],
				)
			);
			self::forget( $ticket );
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
			TelegrARM_Debug_Logger::log(
				'Telegram delivery rejected without retry.',
				array_merge(
					$details,
					array(
						'method' => $payload['method'],
						'target' => $payload['target'],
					)
				)
			);
			self::forget( $ticket );
			return;
		}

		$delay = isset( $details['retry_after'] ) && null !== $details['retry_after'] && 0 < $details['retry_after']
			? (int) $details['retry_after']
			: 5 * $payload['attempt'];
		self::schedule( $payload, time() + min( 300, max( 1, $delay ) ), $ticket );
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
