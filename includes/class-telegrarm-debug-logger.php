<?php
/**
 * Privacy-conscious debug logging.
 *
 * @package TelegrARM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Write bounded and redacted opt-in debug entries. */
final class TelegrARM_Debug_Logger {
	/**
	 * Write a bounded, redacted log entry when debugging is enabled.
	 *
	 * @param string $message Log message.
	 * @param array  $context Structured context.
	 * @return void
	 */
	public static function log( $message, array $context = array() ) {
		if ( ! (bool) get_option( 'telegrarm_debug_logging', false ) ) {
			return;
		}

		$entry = array( 'message' => sanitize_text_field( (string) $message ) );

		if ( ! empty( $context ) ) {
			$entry['context'] = self::redact( $context );
		}

		$encoded = wp_json_encode( $entry );

		if ( is_string( $encoded ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- This is an explicit, opt-in diagnostic logger.
			error_log( 'TelegrARM debug: ' . $encoded );
		}
	}

	/**
	 * Normalize a log value and redact sensitive keys.
	 *
	 * @param mixed  $value Context value.
	 * @param string $key   Context key.
	 * @return mixed
	 */
	public static function redact( $value, $key = '' ) {
		if ( is_array( $value ) ) {
			$sanitized = array();

			foreach ( $value as $nested_key => $nested_value ) {
				$sanitized[ $nested_key ] = self::redact( $nested_value, (string) $nested_key );
			}

			return $sanitized;
		}

		if ( is_object( $value ) ) {
			return self::redact( (array) $value, $key );
		}

		if ( preg_match( '/token|secret|authorization|password|phone|email|name|message|text/i', $key ) ) {
			return '[redacted]';
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}

		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$text = sanitize_textarea_field( (string) $value );

		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 500 ) : substr( $text, 0, 500 );
	}
}
