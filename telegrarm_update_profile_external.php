<?php
/**
 * Queue Telegram notifications when ARMember profiles are updated.
 *
 * @package TelegrARM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Backward-compatible profile-line formatter.
 *
 * @param string $key   Form field key.
 * @param mixed  $value Form field value.
 * @param array  $map   Allowed field mapping.
 * @return string
 */
function telegrarm_build_profile_update_line( $key, $value, $map ) {
	return is_array( $map ) ? TelegrARM_Message_Formatter::profile_line( $key, $value, $map ) : '';
}

/**
 * Queue the profile update notification.
 *
 * @param int   $user_id   Updated user ID.
 * @param array $form_data Submitted profile data.
 * @return void
 */
function telegrarm_profile_update( $user_id, $form_data ) {
	if ( ! is_array( $form_data ) ) {
		TelegrARM_Debug_Logger::log( 'Profile-update handler skipped: invalid payload.' );
		return;
	}

	$mapping = get_option( 'telegrarm_arm_mapping', array() );

	if ( '' === TelegrARM_Config::get_bot_token() || '' === TelegrARM_Config::get_channel_id( 'profile' ) || ! is_array( $mapping ) ) {
		TelegrARM_Debug_Logger::log( 'Profile-update handler skipped: incomplete configuration.' );
		return;
	}

	$message = TelegrARM_Message_Formatter::profile_message(
		/* translators: %d: Numeric user ID. */
		sprintf( __( 'Profile update for user %d', 'telegrarm' ), (int) $user_id ),
		$form_data,
		$mapping
	);

	TelegrARM_Delivery_Queue::enqueue(
		'sendMessage',
		'profile',
		array(
			'parse_mode' => 'HTML',
			'text'       => $message,
		)
	);
}
