<?php
/**
 * Telegram message formatting.
 *
 * @package TelegrARM
 */

/**
 * Allow each file to retain its direct-access guard during whole-project analysis.
 *
 * @psalm-suppress ParadoxicalCondition
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Format allowlisted profile data for Telegram HTML mode. */
final class TelegrARM_Message_Formatter {
	const TELEGRAM_TEXT_LIMIT = 4096;
	const SAFE_TEXT_LIMIT     = 4000;

	/**
	 * Escape plain text for Telegram HTML parse mode.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function escape( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return esc_html( (string) $value );
	}

	/**
	 * Format one mapped profile field.
	 *
	 * @param string $key   Field key.
	 * @param mixed  $value Field value.
	 * @param array  $map   Allowed mapping.
	 * @return string
	 */
	public static function profile_line( $key, $value, array $map ) {
		if ( ! isset( $map[ $key ] ) || ! is_scalar( $value ) ) {
			return '';
		}

		$value_string = trim( (string) $value );

		if ( function_exists( 'mb_substr' ) ) {
			$value_string = mb_substr( $value_string, 0, 1000 );
		} else {
			$value_string = substr( $value_string, 0, 1000 );
		}

		if ( '' === $value_string ) {
			return '';
		}

		$label = self::escape( $map[ $key ] );

		if ( 'arm_social_field_instagram' === $key ) {
			$username = preg_replace( '/[^A-Za-z0-9._]/', '', $value_string );

			if ( is_string( $username ) && '' !== $username ) {
				return $label . ': <a href="https://instagram.com/' . rawurlencode( $username ) . '">@' . self::escape( $username ) . "</a>\n";
			}
		}

		if ( 'avatar' === $key ) {
			$avatar_url    = preg_match( '#^https?://#i', $value_string )
				? $value_string
				: 'https://' . ltrim( $value_string, '/' );
			$validated_url = wp_http_validate_url( $avatar_url );

			if ( false !== $validated_url ) {
				return $label . ': <a href="' . esc_url( $validated_url ) . '">' . self::escape( $validated_url ) . "</a>\n";
			}
		}

		return $label . ': ' . self::escape( $value_string ) . "\n";
	}

	/**
	 * Build a bounded Telegram HTML message.
	 *
	 * @param string $heading Heading text.
	 * @param array  $values  Field values.
	 * @param array  $map     Allowed mapping.
	 * @return string
	 */
	public static function profile_message( $heading, array $values, array $map ) {
		$message = '<b>' . self::escape( $heading ) . "</b>\n";

		$omitted_notice = self::escape( __( 'Additional mapped fields were omitted because the Telegram message reached its length limit.', 'telegrarm' ) );

		foreach ( $values as $key => $value ) {
			if ( ! is_scalar( $key ) ) {
				continue;
			}

			$line = self::profile_line( (string) $key, $value, $map );

			if ( self::length( $message . $line . $omitted_notice ) > self::SAFE_TEXT_LIMIT ) {
				$message .= $omitted_notice;
				break;
			}

			$message .= $line;
		}

		return $message;
	}

	/**
	 * Keep a message below Telegram's text limit.
	 *
	 * @param string $message Message body.
	 * @return string
	 */
	public static function truncate( $message ) {
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $message ) <= self::SAFE_TEXT_LIMIT ) {
			return $message;
		}

		if ( ! function_exists( 'mb_strlen' ) && strlen( $message ) <= self::SAFE_TEXT_LIMIT ) {
			return $message;
		}

		$suffix = "\n…";
		$body   = function_exists( 'mb_substr' )
			? mb_substr( $message, 0, self::SAFE_TEXT_LIMIT - mb_strlen( $suffix ) )
			: substr( $message, 0, self::SAFE_TEXT_LIMIT - strlen( $suffix ) );

		return $body . $suffix;
	}

	/**
	 * Return a Unicode-aware string length.
	 *
	 * @param string $value Value to measure.
	 * @return int
	 */
	private static function length( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
	}
}
