<?php
/**
 * Versioned plugin upgrades.
 *
 * @package TelegrARM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Run idempotent data migrations between plugin versions. */
final class TelegrARM_Upgrader {
	/**
	 * Run idempotent activation and version upgrades.
	 *
	 * @return void
	 */
	public static function run() {
		$installed_version = get_option( 'telegrarm_version', '0.0.0' );

		if ( BONO_TELEGRARM_VERSION === $installed_version ) {
			return;
		}

		if ( version_compare( (string) $installed_version, '1.0.0', '<' ) ) {
			self::migrate_token_autoload();
		}

		update_option( 'telegrarm_version', BONO_TELEGRARM_VERSION, false );
	}

	/**
	 * Re-save the existing credential with autoload disabled.
	 *
	 * @return void
	 */
	private static function migrate_token_autoload() {
		$token = get_option( 'telegram_bot_api_token', null );

		if ( null === $token ) {
			add_option( 'telegram_bot_api_token', '', '', false );
			return;
		}

		update_option( 'telegram_bot_api_token', $token, false );
		wp_set_option_autoload_values( array( 'telegram_bot_api_token' => false ) );
	}
}
