<?php
/**
 * Queue Telegram notifications after ARMember registration.
 *
 * @package TelegrARM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Backward-compatible registration profile-line formatter.
 *
 * @param string $key   Meta key.
 * @param mixed  $value Meta value.
 * @param array  $map   Allowed field mapping.
 * @return string
 */
function telegrarm_build_registration_profile_line( $key, $value, $map ) {
	return is_array( $map ) ? TelegrARM_Message_Formatter::profile_line( $key, $value, $map ) : '';
}

/**
 * Collect the first stored value of each WordPress user meta key, plus the
 * public account fields that live in the users table rather than in user meta.
 *
 * Values are left raw (possibly serialized); the formatter flattens them.
 *
 * @param int $user_id User ID.
 * @return array<string, scalar>
 */
function telegrarm_get_registration_meta( $user_id ) {
	$raw_meta = get_user_meta( (int) $user_id );
	$meta     = array();

	if ( is_array( $raw_meta ) ) {
		foreach ( $raw_meta as $key => $values ) {
			if ( ! is_string( $key ) || ! is_array( $values ) || ! isset( $values[0] ) || ! is_scalar( $values[0] ) ) {
				continue;
			}

			$meta[ $key ] = $values[0];
		}
	}

	$user = get_userdata( (int) $user_id );

	if ( ! is_object( $user ) || ! isset( $user->data ) || ! is_object( $user->data ) ) {
		return $meta;
	}

	// Allowlisted: the users table also holds user_pass and user_activation_key.
	foreach ( array( 'user_login', 'user_email', 'user_url', 'user_registered', 'display_name', 'user_nicename' ) as $field ) {
		if ( isset( $user->data->{$field} ) && is_scalar( $user->data->{$field} ) ) {
			$meta[ $field ] = $user->data->{$field};
		}
	}

	return $meta;
}

/**
 * Queue the new-user notification and optional contact card.
 *
 * @param object $user User object from ARMember.
 * @return void
 */
function telegrarm_after_new_user_notification( $user ) {
	if ( ! is_object( $user ) || ! isset( $user->ID, $user->user_login ) || ! is_scalar( $user->user_login ) ) {
		TelegrARM_Debug_Logger::log( 'New-user handler skipped: invalid payload.' );
		return;
	}

	$mapping = get_option( 'telegrarm_arm_mapping', array() );

	if ( '' === TelegrARM_Config::get_bot_token() || '' === TelegrARM_Config::get_channel_id( 'new-user' ) || ! is_array( $mapping ) ) {
		TelegrARM_Debug_Logger::log( 'New-user handler skipped: incomplete configuration.' );
		return;
	}

	$meta    = telegrarm_get_registration_meta( (int) $user->ID );
	$message = TelegrARM_Message_Formatter::profile_message(
		/* translators: 1: User login. 2: Numeric user ID. */
		sprintf( __( 'New user registered: %1$s [%2$d]', 'telegrarm' ), (string) $user->user_login, (int) $user->ID ),
		$meta,
		$mapping
	);

	/**
	 * Filter the Telegram HTML message before it is queued.
	 *
	 * The returned text is sent with parse_mode HTML, so escape any added content.
	 *
	 * @param string $message Telegram HTML message.
	 * @param string $target  Configuration target: 'new-user' or 'profile'.
	 * @param int    $user_id User ID.
	 * @param array  $values  Raw field values the message was built from.
	 */
	$message = (string) apply_filters( 'telegrarm_message_text', $message, 'new-user', (int) $user->ID, $meta );

	TelegrARM_Delivery_Queue::enqueue(
		'sendMessage',
		'new-user',
		array(
			'parse_mode' => 'HTML',
			'text'       => $message,
		)
	);

	if ( ! (bool) get_option( 'telegram_send_contact_during_registration', false ) ) {
		return;
	}

	$phone_field = (string) get_option( 'telegram_phone_field_name', 'text_t0cls' );
	$phone       = isset( $meta[ $phone_field ] ) ? preg_replace( '/[^0-9+]/', '', TelegrARM_Message_Formatter::flatten( $meta[ $phone_field ] ) ) : '';

	if ( ! is_string( $phone ) || '' === $phone ) {
		TelegrARM_Debug_Logger::log( 'New-user contact skipped: phone number missing.' );
		return;
	}

	if ( 0 !== strpos( $phone, '+' ) ) {
		$default_code = preg_replace( '/[^0-9+]/', '', (string) get_option( 'telegram_international_code_if_missing', '+1' ) );
		$phone        = ( is_string( $default_code ) ? $default_code : '+1' ) . $phone;
	}

	TelegrARM_Delivery_Queue::enqueue(
		'sendContact',
		'new-user',
		array(
			'phone_number' => $phone,
			// Telegram requires first_name, so fall back to the login name.
			'first_name'   => '' !== trim( (string) ( $meta['first_name'] ?? '' ) ) ? (string) $meta['first_name'] : (string) $user->user_login,
			'last_name'    => isset( $meta['last_name'] ) ? (string) $meta['last_name'] : '',
		)
	);
}
