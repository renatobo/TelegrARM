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

		// Telegram HTML mode accepts only &lt; &gt; &amp; &quot; and numeric entities,
		// so always double-encode rather than preserving named entities like esc_html().
		return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8', true );
	}

	/**
	 * Whether a key names credential or privilege data that must never leave the site.
	 *
	 * @param mixed $key Field or meta key.
	 * @return bool
	 */
	public static function is_sensitive_key( $key ) {
		$key = is_scalar( $key ) ? strtolower( trim( (string) $key ) ) : '';

		return in_array( $key, array( 'user_pass', 'user_activation_key', 'session_tokens' ), true )
			|| 1 === preg_match( '/(?:pass(?:word)?|secret|token|credential|recovery|private[_-]?key|api[_-]?key)|_capabilities$|_user_level$/', $key );
	}

	/**
	 * Reduce a field value to display text: scalars as-is, lists of scalars comma-joined.
	 *
	 * @param mixed $value Raw field value, possibly a serialized string.
	 * @return string
	 */
	public static function flatten( $value ) {
		if ( is_string( $value ) && is_serialized( $value ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize,WordPress.PHP.NoSilencedErrors.Discouraged -- allowed_classes is false, so no object can be instantiated; malformed data yields false.
			$value = @unserialize( trim( $value ), array( 'allowed_classes' => false ) );
		}

		if ( is_scalar( $value ) ) {
			return trim( (string) $value );
		}

		if ( ! is_array( $value ) ) {
			return '';
		}

		$parts = array();

		foreach ( $value as $item ) {
			if ( is_scalar( $item ) && '' !== trim( (string) $item ) ) {
				$parts[] = trim( (string) $item );
			}
		}

		return implode( ', ', $parts );
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
		if ( ! isset( $map[ $key ] ) || self::is_sensitive_key( $key ) ) {
			return '';
		}

		$value_string = self::flatten( $value );

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
			$avatar_url = preg_match( '#^https?://#i', $value_string )
				? $value_string
				: 'https://' . ltrim( $value_string, '/' );
			$parts      = wp_parse_url( $avatar_url );

			// Validate the shape only: a DNS lookup here would block the member's request.
			if ( is_array( $parts ) && ! empty( $parts['host'] ) && in_array( strtolower( (string) ( $parts['scheme'] ?? '' ) ), array( 'http', 'https' ), true ) ) {
				return $label . ': <a href="' . esc_url( $avatar_url, array( 'http', 'https' ) ) . '">' . self::escape( $avatar_url ) . "</a>\n";
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

		// Follow the mapping order, which the admin sets in the mapping builder.
		foreach ( array_keys( $map ) as $key ) {
			if ( ! array_key_exists( $key, $values ) ) {
				continue;
			}

			$line = self::profile_line( (string) $key, $values[ $key ], $map );

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
