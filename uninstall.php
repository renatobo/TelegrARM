<?php
/**
 * TelegrARM - Uninstall Script
 *
 * Removes all plugin options from the WordPress database on uninstall.
 *
 * @package   TelegrARM
 * @author    Renato Bonomini <https://github.com/renatobo>
 * @copyright 2024 Renato Bonomini
 * @license   GPLv2 or later
 * @link      https://github.com/renatobo/TelegrARM
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove all plugin options from the database.
delete_option( 'telegrarm_profile_update' );
delete_option( 'telegrarm_after_new_user_notification' );
delete_option( 'telegram_bot_api_token' );
delete_option( 'telegrarm_debug_logging' );
delete_option( 'telegram_channel_id_newuser' );
delete_option( 'telegram_channel_id_updates' );
delete_option( 'telegram_send_contact_during_registration' );
delete_option( 'telegram_phone_field_name' );
delete_option( 'telegram_international_code_if_missing' );
delete_option( 'telegrarm_arm_mapping' );
delete_option( 'telegrarm_version' );

wp_clear_scheduled_hook( 'telegrarm_process_delivery' );

/**
 * Delete queued delivery payloads, pacing markers, and dedupe markers.
 *
 * Queued payloads use randomized transient names, so they cannot be removed
 * through named delete_transient() calls.
 *
 * @return void
 */
function telegrarm_uninstall_delete_transients() {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Randomized transient names cannot be resolved through the options API.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE %s
			    OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_telegrarm_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_telegrarm_' ) . '%'
		)
	);
}

telegrarm_uninstall_delete_transients();
