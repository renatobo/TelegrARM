<?php
/**
 * Plugin configuration access.
 *
 * @package TelegrARM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Resolve TelegrARM configuration without exposing credential sources. */
final class TelegrARM_Config {
	/**
	 * Return the Telegram bot token without exposing its source to callers.
	 *
	 * @return string
	 */
	public static function get_bot_token() {
		$token = defined( 'TELEGRARM_BOT_TOKEN' ) ? TELEGRARM_BOT_TOKEN : get_option( 'telegram_bot_api_token', '' );
		$token = is_scalar( $token ) ? trim( (string) $token ) : '';

		/**
		 * Filter the Telegram bot token at runtime.
		 *
		 * @param string $token Configured token.
		 */
		$token = apply_filters( 'telegrarm_bot_token', $token );

		return is_scalar( $token ) ? trim( (string) $token ) : '';
	}

	/**
	 * Resolve a configured delivery target.
	 *
	 * @param string $target Target identifier.
	 * @return string
	 */
	public static function get_channel_id( $target ) {
		$option_name = 'new-user' === $target
			? 'telegram_channel_id_newuser'
			: 'telegram_channel_id_updates';

		$channel_id = get_option( $option_name, '' );

		return is_scalar( $channel_id ) ? trim( (string) $channel_id ) : '';
	}
}
