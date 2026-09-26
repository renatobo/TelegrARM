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
 * Hold or release the profile data a member submitted in this request.
 *
 * @param int        $user_id User ID.
 * @param array|null $data    Data to store, or null to take (and clear) the stored data.
 * @return array|null Stored data when taking, otherwise null.
 */
function telegrarm_profile_submission( $user_id, $data = null ) {
	static $submissions = array();

	if ( is_array( $data ) ) {
		$submissions[ $user_id ] = $data;
		return null;
	}

	if ( ! isset( $submissions[ $user_id ] ) ) {
		return null;
	}

	$stored = $submissions[ $user_id ];
	unset( $submissions[ $user_id ] );

	return $stored;
}

/**
 * Remember a member's own profile submission until ARMember confirms the save.
 *
 * `arm_member_update_meta` also fires for registrations and admin edits, so it
 * cannot trigger a notification on its own.
 *
 * @param int   $user_id         User ID.
 * @param mixed $posted_data     Submitted form data.
 * @param int   $admin_save_flag 1 when an administrator saved the member.
 * @return void
 */
function telegrarm_capture_profile_submission( $user_id, $posted_data = array(), $admin_save_flag = 0 ) {
	if ( ! empty( $admin_save_flag ) || ! is_array( $posted_data ) ) {
		return;
	}

	telegrarm_profile_submission( (int) $user_id, $posted_data );
}

/**
 * Send the profile notification once ARMember records a completed profile update.
 *
 * Only the edit-profile save records an `update_profile` activity, in both
 * ARMember Lite and Pro, so registrations and admin edits are never reported.
 *
 * @param mixed $activity ARMember activity record.
 * @return void
 */
function telegrarm_notify_on_profile_activity( $activity ) {
	if ( ! is_array( $activity ) || ! isset( $activity['type'], $activity['user_id'] ) || 'update_profile' !== $activity['type'] ) {
		return;
	}

	$user_id   = (int) $activity['user_id'];
	$form_data = telegrarm_profile_submission( $user_id );

	if ( null === $form_data ) {
		TelegrARM_Debug_Logger::log( 'Profile-update handler skipped: no submission captured for this save.' );
		return;
	}

	telegrarm_profile_update( $user_id, $form_data );
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

	/** This filter is documented in telegrarm_after_new_user_notification.php */
	$message = (string) apply_filters( 'telegrarm_message_text', $message, 'profile', (int) $user_id, $form_data );

	TelegrARM_Delivery_Queue::enqueue(
		'sendMessage',
		'profile',
		array(
			'parse_mode' => 'HTML',
			'text'       => $message,
		)
	);
}
