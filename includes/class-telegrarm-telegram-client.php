<?php
/**
 * Telegram Bot API transport.
 *
 * @package TelegrARM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Send allowlisted requests through the WordPress HTTP API. */
final class TelegrARM_Telegram_Client {
	const REQUEST_TIMEOUT = 4;

	/**
	 * Optional token used only for an administrator's unsaved test request.
	 *
	 * @var string
	 */
	private $token_override;

	/**
	 * Optionally use an unsaved administrator-provided token for a test request.
	 *
	 * @param string $token_override Temporary token.
	 */
	public function __construct( $token_override = '' ) {
		$this->token_override = is_scalar( $token_override ) ? trim( (string) $token_override ) : '';
	}

	/**
	 * Send a Telegram Bot API request.
	 *
	 * @param string $method Bot API method.
	 * @param array  $body   Request fields.
	 * @return array<string, mixed>|WP_Error
	 */
	public function send( $method, array $body ) {
		$token = '' !== $this->token_override ? $this->token_override : TelegrARM_Config::get_bot_token();

		if ( '' === $token ) {
			return new WP_Error( 'telegrarm_missing_bot_token', __( 'Telegram bot token is not configured.', 'telegrarm' ) );
		}

		if ( ! preg_match( '/^[0-9]{6,12}:[A-Za-z0-9_-]{30,}$/', $token ) ) {
			return new WP_Error( 'telegrarm_invalid_bot_token', __( 'Telegram bot token format is invalid.', 'telegrarm' ) );
		}

		$allowed_methods = array( 'sendMessage', 'sendContact' );

		if ( ! in_array( $method, $allowed_methods, true ) ) {
			return new WP_Error( 'telegrarm_invalid_method', __( 'Unsupported Telegram API method.', 'telegrarm' ) );
		}

		return wp_remote_post(
			'https://api.telegram.org/bot' . rawurlencode( $token ) . '/' . $method,
			array(
				'body'        => $body,
				'timeout'     => self::REQUEST_TIMEOUT,
				'redirection' => 0,
				'sslverify'   => true,
			)
		);
	}

	/**
	 * Normalize Telegram response details.
	 *
	 * @param array<string, mixed>|mixed $response HTTP response.
	 * @return array{status_code:int,ok:bool|null,description:string,error_code:int|null,retry_after:int}
	 */
	public static function response_details( $response ) {
		$details = array(
			'status_code' => 0,
			'ok'          => null,
			'description' => __( 'Unknown Telegram API error.', 'telegrarm' ),
			'error_code'  => null,
			'retry_after' => 0,
		);

		if ( ! is_array( $response ) ) {
			return $details;
		}

		$details['status_code'] = (int) wp_remote_retrieve_response_code( $response );
		$decoded                = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $decoded ) ) {
			return $details;
		}

		if ( array_key_exists( 'ok', $decoded ) ) {
			$details['ok'] = (bool) $decoded['ok'];
		}

		if ( isset( $decoded['description'] ) && is_scalar( $decoded['description'] ) ) {
			$details['description'] = sanitize_text_field( (string) $decoded['description'] );
		}

		if ( isset( $decoded['error_code'] ) && is_scalar( $decoded['error_code'] ) ) {
			$details['error_code'] = (int) $decoded['error_code'];
		}

		if ( isset( $decoded['parameters']['retry_after'] ) && is_scalar( $decoded['parameters']['retry_after'] ) ) {
			$details['retry_after'] = min( 300, max( 1, (int) $decoded['parameters']['retry_after'] ) );
		}

		return $details;
	}

	/**
	 * Determine whether a response represents a successful delivery.
	 *
	 * @param array<string, mixed>|mixed $response HTTP response.
	 * @return bool
	 */
	public static function is_success( $response ) {
		$details = self::response_details( $response );

		return 200 === $details['status_code'] && true === $details['ok'];
	}
}
