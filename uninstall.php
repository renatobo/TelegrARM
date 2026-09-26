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

/**
 * Remove every option, cron event, and queued payload for the current site.
 *
 * @return void
 */
function telegrarm_uninstall_site() {
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

	// Delivery events carry a ticket argument, which wp_clear_scheduled_hook() would not match.
	wp_unschedule_hook( 'telegrarm_process_delivery' );
	wp_clear_scheduled_hook( 'telegrarm_cleanup_deliveries' );

	telegrarm_uninstall_delete_queue_rows();
}

/**
 * Delete queued delivery payloads, pacing markers, and dedupe markers.
 *
 * Queued payloads use randomized names, so they cannot be removed through
 * named delete_option() or delete_transient() calls.
 *
 * @return void
 */
function telegrarm_uninstall_delete_queue_rows() {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Randomized option and transient names cannot be resolved through the options API.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE %s
			    OR option_name LIKE %s
			    OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_telegrarm_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_telegrarm_' ) . '%',
			$wpdb->esc_like( 'telegrarm_job_' ) . '%'
		)
	);
}

if ( is_multisite() ) {
	$telegrarm_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $telegrarm_site_ids as $telegrarm_site_id ) {
		switch_to_blog( (int) $telegrarm_site_id );
		telegrarm_uninstall_site();
		restore_current_blog();
	}
} else {
	telegrarm_uninstall_site();
}
