<?php
/**
 * Register and render the TelegrARM admin settings page.
 *
 * @package TelegrARM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/admin/telegrarm-field-discovery.php';

add_action( 'admin_init', 'telegrarm_settings_init' );
add_action( 'admin_menu', 'telegrarm_settings_menu' );
add_action( 'admin_enqueue_scripts', 'telegrarm_enqueue_admin_assets' );
add_action( 'wp_ajax_telegrarm_discover_arm_metakeys', 'telegrarm_ajax_discover_arm_metakeys' );
add_action( 'wp_ajax_telegrarm_send_test_message', 'telegrarm_ajax_send_test_message' );

/**
 * Load settings assets only on the TelegrARM admin screen.
 *
 * @param string $hook_suffix Current admin screen hook.
 * @return void
 */
function telegrarm_enqueue_admin_assets( $hook_suffix ) {
	if ( 'settings_page_telegrarm_settings_page' !== $hook_suffix ) {
		return;
	}

	wp_enqueue_style( 'telegrarm-admin', plugins_url( 'assets/admin.css', __FILE__ ), array(), BONO_TELEGRARM_VERSION );
	wp_enqueue_script( 'telegrarm-admin', plugins_url( 'assets/admin.js', __FILE__ ), array(), BONO_TELEGRARM_VERSION, true );
	wp_localize_script(
		'telegrarm-admin',
		'telegrarmAdmin',
		array(
			'ajaxUrl'               => admin_url( 'admin-ajax.php' ),
			'ajaxNonce'             => wp_create_nonce( 'telegrarm_discover_arm_metakeys' ),
			'testMessageNonce'      => wp_create_nonce( 'telegrarm_send_test_message' ),
			'hasConfiguredBotToken' => '' !== TelegrARM_Config::get_bot_token(),
			'existingMapping'       => telegrarm_get_arm_mapping(),
			'i18n'                  => array(
				/* translators: %d: Number of mapped fields. */
				'builtJsonSingular'           => __( 'Built mapping JSON from %d field.', 'telegrarm' ),
				/* translators: %d: Number of mapped fields. */
				'builtJsonPlural'             => __( 'Built mapping JSON from %d fields.', 'telegrarm' ),
				/* translators: %d: Number of mapped fields. */
				'currentMappingCountSingular' => __( 'The current mapping already contains %d field. Discover more fields to add or refine them.', 'telegrarm' ),
				/* translators: %d: Number of mapped fields. */
				'currentMappingCountPlural'   => __( 'The current mapping already contains %d fields. Discover more fields to add or refine them.', 'telegrarm' ),
				'detected'                    => __( 'Detected', 'telegrarm' ),
				'discovering'                 => __( 'Discovering ARMember fields...', 'telegrarm' ),
				/* translators: %d: Number of discovered fields. */
				'discoveredCountSingular'     => __( 'Discovered %d candidate field.', 'telegrarm' ),
				/* translators: %d: Number of discovered fields. */
				'discoveredCountPlural'       => __( 'Discovered %d candidate fields.', 'telegrarm' ),
				'formField'                   => __( 'Form field', 'telegrarm' ),
				'metaKey'                     => __( 'Meta key', 'telegrarm' ),
				'noCandidates'                => __( 'No candidate ARMember fields were found on this site yet.', 'telegrarm' ),
				'preset'                      => __( 'Preset', 'telegrarm' ),
				/* translators: %d: HTTP response status code. */
				'requestFailed'               => __( 'Request failed with status %d.', 'telegrarm' ),
				'reorder'                     => __( 'Order', 'telegrarm' ),
				'reorderHint'                 => __( 'Drag the Move control to rearrange the selected field order before building the JSON.', 'telegrarm' ),
				'reorderMove'                 => __( 'Move', 'telegrarm' ),
				'selectAtLeastOne'            => __( 'Select at least one field before building the JSON.', 'telegrarm' ),
				'sendingTestMessage'          => __( 'Sending test message...', 'telegrarm' ),
				'source'                      => __( 'Source', 'telegrarm' ),
				'testMessageMissingBotToken'  => __( 'Enter a bot token before sending a test message.', 'telegrarm' ),
				'testMessageMissingChannel'   => __( 'Enter a channel or chat ID before sending a test message.', 'telegrarm' ),
				'unknownTestMessageError'     => __( 'Unable to send the test message.', 'telegrarm' ),
				'unexpectedResponse'          => __( 'Unexpected response from the discovery endpoint.', 'telegrarm' ),
				'unknownDiscoveryError'       => __( 'Unable to discover ARMember fields.', 'telegrarm' ),
				'use'                         => __( 'Use', 'telegrarm' ),
				'usermeta'                    => __( 'Usermeta', 'telegrarm' ),
				'label'                       => __( 'Label', 'telegrarm' ),
				'builtIn'                     => __( 'Built-in', 'telegrarm' ),
			),
		)
	);
}

/**
 * Register plugin settings.
 */
function telegrarm_settings_init() {
	register_setting(
		'telegrarm_settings_group',
		'telegrarm_profile_update',
		array(
			'type'              => 'boolean',
			'sanitize_callback' => 'telegrarm_sanitize_checkbox',
			'default'           => false,
		)
	);

	register_setting(
		'telegrarm_settings_group',
		'telegrarm_after_new_user_notification',
		array(
			'type'              => 'boolean',
			'sanitize_callback' => 'telegrarm_sanitize_checkbox',
			'default'           => false,
		)
	);

	register_setting(
		'telegrarm_settings_group',
		'telegram_bot_api_token',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'telegrarm_sanitize_bot_token',
			'default'           => '',
		)
	);

	register_setting(
		'telegrarm_settings_group',
		'telegrarm_debug_logging',
		array(
			'type'              => 'boolean',
			'sanitize_callback' => 'telegrarm_sanitize_checkbox',
			'default'           => false,
		)
	);

	register_setting(
		'telegrarm_settings_group',
		'telegram_channel_id_newuser',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'telegrarm_sanitize_channel_id',
			'default'           => '',
		)
	);

	register_setting(
		'telegrarm_settings_group',
		'telegram_channel_id_updates',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'telegrarm_sanitize_channel_id',
			'default'           => '',
		)
	);

	register_setting(
		'telegrarm_settings_group',
		'telegram_send_contact_during_registration',
		array(
			'type'              => 'boolean',
			'sanitize_callback' => 'telegrarm_sanitize_checkbox',
			'default'           => false,
		)
	);

	register_setting(
		'telegrarm_settings_group',
		'telegram_phone_field_name',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'telegrarm_sanitize_setting_text',
			'default'           => 'text_t0cls',
		)
	);

	register_setting(
		'telegrarm_settings_group',
		'telegram_international_code_if_missing',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'telegrarm_sanitize_international_code',
			'default'           => '+1',
		)
	);

	register_setting(
		'telegrarm_settings_group',
		'telegrarm_arm_mapping',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'telegrarm_arm_mapping_sanitize',
			'default'           => telegrarm_get_default_arm_mapping(),
		)
	);
}

/**
 * Sanitize checkbox values.
 *
 * @param mixed $value Submitted option value.
 * @return bool
 */
function telegrarm_sanitize_checkbox( $value ) {
	return ! empty( $value );
}

/**
 * Determine whether TelegrARM debug logging is enabled.
 *
 * @return bool
 */
function telegrarm_is_debug_logging_enabled() {
	return (bool) get_option( 'telegrarm_debug_logging', false );
}

/**
 * Normalize a debug context value so it can be safely encoded.
 *
 * @param mixed  $value Context value.
 * @param string $key   Optional context key.
 * @return mixed
 */
function telegrarm_sanitize_debug_context_value( $value, $key = '' ) {
	return TelegrARM_Debug_Logger::redact( $value, $key );
}

/**
 * Write a sanitized debug message to the PHP error log when enabled.
 *
 * @param string $message Log message.
 * @param array  $context Optional structured context.
 * @return void
 */
function telegrarm_log_debug_message( $message, array $context = array() ) {
	TelegrARM_Debug_Logger::log( $message, $context );
}

/**
 * Extract normalized details from a Telegram Bot API response.
 *
 * @param array<string, mixed>|mixed $response HTTP response.
 * @return array{status_code:int,ok:bool|null,description:string,error_code:int|null,retry_after:int}
 */
function telegrarm_get_telegram_response_details( $response ) {
	return TelegrARM_Telegram_Client::response_details( $response );
}

/**
 * Extract a readable Telegram API error without triggering notices.
 *
 * @param array<string, mixed>|mixed $response HTTP response.
 * @return string
 */
if ( ! function_exists( 'telegrarm_get_telegram_error_message' ) ) {
	/**
	 * Extract a readable Telegram API error without triggering notices.
	 *
	 * @param array<string, mixed>|mixed $response HTTP response.
	 * @return string
	 */
	function telegrarm_get_telegram_error_message( $response ) {
		$details = telegrarm_get_telegram_response_details( $response );

		return $details['description'];
	}
}

/**
 * Resolve the site name shown in admin test messages.
 *
 * @return string
 */
function telegrarm_get_test_message_site_name() {
	$site_host = wp_parse_url( (string) get_option( 'home', '' ), PHP_URL_HOST );

	if ( is_string( $site_host ) && '' !== $site_host ) {
		return sanitize_text_field( $site_host );
	}

	$site_name = get_option( 'blogname', '' );

	if ( is_string( $site_name ) && '' !== trim( $site_name ) ) {
		return sanitize_text_field( $site_name );
	}

	return __( 'this website', 'telegrarm' );
}

/**
 * Resolve the settings area label used in admin test messages.
 *
 * @param string $target Settings target key.
 * @return string
 */
function telegrarm_get_test_message_target_label( $target ) {
	if ( 'new-user' === $target ) {
		return __( 'new user', 'telegrarm' );
	}

	if ( 'profile' === $target ) {
		return __( 'profile update', 'telegrarm' );
	}

	return __( 'TelegrARM', 'telegrarm' );
}

/**
 * Resolve the settings area label used in admin status messages.
 *
 * @param string $target Settings target key.
 * @return string
 */
function telegrarm_get_test_message_target_status_label( $target ) {
	if ( 'new-user' === $target ) {
		return __( 'New user', 'telegrarm' );
	}

	if ( 'profile' === $target ) {
		return __( 'Profile updates', 'telegrarm' );
	}

	return __( 'TelegrARM', 'telegrarm' );
}

/**
 * Build the admin test message body sent to Telegram.
 *
 * @param string $target Settings target key.
 * @return string
 */
function telegrarm_get_test_message_text( $target ) {
	return sprintf(
		/* translators: 1: Settings target label. 2: Site name. */
		__( 'This is a test message for %1$s from TelegrARM on %2$s', 'telegrarm' ),
		telegrarm_get_test_message_target_label( $target ),
		telegrarm_get_test_message_site_name()
	);
}

/**
 * Build a detailed admin feedback message for Telegram test requests.
 *
 * @param string                     $target            Settings target key.
 * @param string                     $channel_id        Telegram channel or chat ID.
 * @param array<string, mixed>|mixed $response         HTTP response.
 * @param bool                       $request_succeeded Whether the request succeeded.
 * @return string
 */
function telegrarm_build_test_message_feedback( $target, $channel_id, $response, $request_succeeded ) {
	$target_label = telegrarm_get_test_message_target_status_label( $target );
	$details      = telegrarm_get_telegram_response_details( $response );

	$lines = array(
		$request_succeeded
			? sprintf(
				/* translators: 1: Settings target label. 2: Telegram chat ID. */
				__( 'Test message for %1$s sent successfully to chat ID %2$s.', 'telegrarm' ),
				$target_label,
				$channel_id
			)
			: sprintf(
				/* translators: 1: Settings target label. 2: Telegram chat ID. */
				__( 'Test message for %1$s failed for chat ID %2$s.', 'telegrarm' ),
				$target_label,
				$channel_id
			),
		__( 'Telegram response details:', 'telegrarm' ),
		/* translators: %d: HTTP response status code. */
		sprintf( __( 'HTTP status: %d', 'telegrarm' ), (int) $details['status_code'] ),
		sprintf(
			/* translators: %s: Telegram response success state. */
			__( 'Telegram ok: %s', 'telegrarm' ),
			true === $details['ok'] ? 'true' : ( false === $details['ok'] ? 'false' : 'null' )
		),
	);

	if ( null !== $details['error_code'] ) {
		/* translators: %d: Telegram API error code. */
		$lines[] = sprintf( __( 'Telegram error code: %d', 'telegrarm' ), (int) $details['error_code'] );
	}

	if ( '' !== trim( (string) $details['description'] ) ) {
		/* translators: %s: Telegram API error description. */
		$lines[] = sprintf( __( 'Telegram description: %s', 'telegrarm' ), $details['description'] );
	}

	return implode( "\n", $lines );
}

/**
 * Send a plain Telegram message to a configured channel or chat.
 *
 * @param string $bot_api_token Telegram bot token.
 * @param string $channel_id    Telegram channel or chat ID.
 * @param string $message       Message text.
 * @return array<string, mixed>|WP_Error
 */
function telegrarm_send_telegram_text_message( $bot_api_token, $channel_id, $message ) {
	$bot_api_token = telegrarm_sanitize_bot_token( $bot_api_token );
	$channel_id    = telegrarm_sanitize_channel_id( $channel_id );
	$message       = is_scalar( $message ) ? trim( sanitize_textarea_field( (string) $message ) ) : '';

	if ( '' === $bot_api_token ) {
		return new WP_Error(
			'telegrarm_missing_bot_token',
			__( 'Enter a Telegram bot token before sending a test message.', 'telegrarm' )
		);
	}

	if ( '' === $channel_id ) {
		return new WP_Error(
			'telegrarm_missing_channel_id',
			__( 'Enter a Telegram channel or chat ID before sending a test message.', 'telegrarm' )
		);
	}

	if ( '' === $message ) {
		return new WP_Error(
			'telegrarm_missing_test_message',
			__( 'The test message could not be generated.', 'telegrarm' )
		);
	}

	$client = new TelegrARM_Telegram_Client( $bot_api_token );

	return $client->send(
		'sendMessage',
		array(
			'chat_id' => $channel_id,
			'text'    => $message,
		)
	);
}

/**
 * AJAX endpoint that sends an admin test message to Telegram.
 *
 * @return void
 */
function telegrarm_ajax_send_test_message() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'You do not have permission to send TelegrARM test messages.', 'telegrarm' ),
			),
			403
		);
	}

	check_ajax_referer( 'telegrarm_send_test_message' );

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The plugin-specific callback validates the Telegram token format.
	$bot_api_token = isset( $_POST['bot_token'] ) ? telegrarm_sanitize_bot_token( wp_unslash( $_POST['bot_token'] ) ) : '';

	if ( '' === $bot_api_token ) {
		$bot_api_token = TelegrARM_Config::get_bot_token();
	}
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The plugin-specific callback validates Telegram destinations.
	$channel_id = isset( $_POST['channel_id'] ) ? telegrarm_sanitize_channel_id( wp_unslash( $_POST['channel_id'] ) ) : '';
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The target is sanitized immediately.
	$target       = isset( $_POST['target'] ) ? telegrarm_sanitize_setting_text( wp_unslash( $_POST['target'] ) ) : '';
	$target_label = telegrarm_get_test_message_target_status_label( $target );

	$result = telegrarm_send_telegram_text_message(
		$bot_api_token,
		$channel_id,
		telegrarm_get_test_message_text( $target )
	);

	if ( is_wp_error( $result ) ) {
		telegrarm_log_debug_message(
			'Admin test Telegram message request failed before response.',
			array(
				'action' => 'sendMessage',
				'target' => $target,
				'error'  => $result->get_error_message(),
				'code'   => $result->get_error_code(),
			)
		);

		wp_send_json_error(
			array(
				'message' => sprintf(
					/* translators: 1: Settings target label. 2: Telegram chat ID. 3: Error message. */
					__( 'Test message for %1$s could not be sent to chat ID %2$s: %3$s', 'telegrarm' ),
					$target_label,
					$channel_id,
					$result->get_error_message()
				),
			),
			400
		);
	}

	$telegram_response = telegrarm_get_telegram_response_details( $result );

	if ( 200 !== $telegram_response['status_code'] || true !== $telegram_response['ok'] ) {
		telegrarm_log_debug_message(
			'Admin test Telegram message request was unsuccessful.',
			array(
				'action'      => 'sendMessage',
				'target'      => $target,
				'status_code' => $telegram_response['status_code'],
				'telegram_ok' => $telegram_response['ok'],
				'error_code'  => $telegram_response['error_code'],
				'description' => $telegram_response['description'],
			)
		);

		wp_send_json_error(
			array(
				'message' => telegrarm_build_test_message_feedback( $target, $channel_id, $result, false ),
			),
			400
		);
	}

	wp_send_json_success(
		array(
			'message' => telegrarm_build_test_message_feedback( $target, $channel_id, $result, true ),
		)
	);
}


/**
 * Sanitize generic text settings.
 *
 * @param mixed $value Submitted option value.
 * @return string
 */
function telegrarm_sanitize_setting_text( $value ) {
	return is_scalar( $value ) ? trim( sanitize_text_field( (string) $value ) ) : '';
}

/**
 * Sanitize the Telegram bot token.
 *
 * @param mixed $value Submitted option value.
 * @return string
 */
function telegrarm_sanitize_bot_token( $value ) {
	$token = telegrarm_sanitize_setting_text( $value );
	$token = preg_replace( '/\s+/', '', $token );

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- This sanitizer runs only inside the nonce-protected Settings API request.
	if ( isset( $_POST['telegrarm_clear_bot_token'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['telegrarm_clear_bot_token'] ) ) ) {
		return '';
	}

	if ( '' === $token ) {
		$existing = get_option( 'telegram_bot_api_token', '' );
		return is_scalar( $existing ) ? (string) $existing : '';
	}

	if ( ! preg_match( '/^[0-9]{6,12}:[A-Za-z0-9_-]{30,}$/', $token ) ) {
		add_settings_error(
			'telegrarm_settings_group',
			'telegrarm_invalid_bot_token',
			__( 'The Telegram bot token format is invalid; the previous token was retained.', 'telegrarm' )
		);
		$existing = get_option( 'telegram_bot_api_token', '' );
		return is_scalar( $existing ) ? (string) $existing : '';
	}

	return is_string( $token ) ? $token : '';
}

/**
 * Sanitize Telegram channel or chat identifiers.
 *
 * @param mixed $value Submitted option value.
 * @return string
 */
function telegrarm_sanitize_channel_id( $value ) {
	$channel_id = telegrarm_sanitize_setting_text( $value );

	$channel_id = preg_replace( '/\s+/', '', $channel_id );

	if ( ! is_string( $channel_id ) || ! preg_match( '/^(?:-?[0-9]{1,20}|@[A-Za-z0-9_]{5,32})$/', $channel_id ) ) {
		return '';
	}

	return $channel_id;
}

/**
 * Sanitize the default country code.
 *
 * @param mixed $value Submitted option value.
 * @return string
 */
function telegrarm_sanitize_international_code( $value ) {
	$code = telegrarm_sanitize_setting_text( $value );
	$code = preg_replace( '/\s+/', '', $code );
	$code = is_string( $code ) ? $code : '';

	if ( '' === $code ) {
		return '+1';
	}

	return preg_match( '/^\+[0-9]{1,4}$/', $code ) ? $code : '+1';
}

/**
 * Return the default ARMember field mapping.
 *
 * @return array<string, string>
 */
function telegrarm_get_default_arm_mapping() {
	return array(
		'first_name' => 'First Name',
		'last_name'  => 'Last Name',
		'user_email' => 'Email',
	);
}

/**
 * Return the saved ARMember mapping with defaults.
 *
 * @return array<string, string>
 */
function telegrarm_get_arm_mapping() {
	$mapping = get_option( 'telegrarm_arm_mapping', telegrarm_get_default_arm_mapping() );

	if ( ! is_array( $mapping ) || empty( $mapping ) ) {
		return telegrarm_get_default_arm_mapping();
	}

	return $mapping;
}

/**
 * Sanitize the saved ARMember key-to-label mapping.
 *
 * @param mixed $input Submitted option value.
 * @return array<string, string>
 */
function telegrarm_arm_mapping_sanitize( $input ) {
	if ( is_array( $input ) ) {
		$decoded = $input;
	} elseif ( is_string( $input ) && '' !== trim( $input ) ) {
		$decoded = json_decode( wp_unslash( $input ), true );
	} else {
		return telegrarm_get_default_arm_mapping();
	}

	if ( ! is_array( $decoded ) ) {
		add_settings_error(
			'telegrarm_arm_mapping',
			'telegrarm_arm_mapping_invalid_json',
			__( 'The ARMember field mapping must be valid JSON.', 'telegrarm' ),
			'error'
		);

		return telegrarm_get_arm_mapping();
	}

	$sanitized = array();

	foreach ( $decoded as $key => $label ) {
		if ( ! is_scalar( $key ) || ! is_scalar( $label ) ) {
			continue;
		}

		$mapping_key   = trim( sanitize_text_field( (string) $key ) );
		$mapping_label = trim( sanitize_text_field( (string) $label ) );

		if ( '' === $mapping_key || '' === $mapping_label ) {
			continue;
		}

		$sanitized[ $mapping_key ] = $mapping_label;
	}

	if ( empty( $sanitized ) ) {
		add_settings_error(
			'telegrarm_arm_mapping',
			'telegrarm_arm_mapping_empty',
			__( 'The ARMember field mapping could not be saved because it did not contain any valid key and label pairs.', 'telegrarm' ),
			'error'
		);

		return telegrarm_get_arm_mapping();
	}

	return $sanitized;
}

/**
 * Add the settings page under Settings.
 */
function telegrarm_settings_menu() {
	add_options_page(
		__( 'TelegrARM Settings', 'telegrarm' ),
		__( 'TelegrARM', 'telegrarm' ),
		'manage_options',
		'telegrarm_settings_page',
		'telegrarm_settings_page_cb'
	);
}

/**
 * Render the settings page.
 */
function telegrarm_settings_page_cb() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$project_url     = 'https://github.com/renatobo/TelegrARM';
	$author_url      = 'https://github.com/renatobo';
	$git_updater_url = 'https://github.com/afragen/git-updater';
	$banner_url      = plugins_url( 'assets/telegrarm-settings-banner.svg', __FILE__ );

	$new_user_enabled       = (bool) get_option( 'telegrarm_after_new_user_notification', false );
	$profile_update_enabled = (bool) get_option( 'telegrarm_profile_update', false );
	$send_contact_enabled   = (bool) get_option( 'telegram_send_contact_during_registration', false );
	$debug_logging_enabled  = (bool) get_option( 'telegrarm_debug_logging', false );
	$mapping_json           = wp_json_encode(
		telegrarm_get_arm_mapping(),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
	);
	?>
	<div class="wrap">
		<div class="telegrarm-admin">
			<div class="telegrarm-hero">
				<img
					src="<?php echo esc_url( $banner_url ); ?>"
					alt="<?php echo esc_attr__( 'TelegrARM settings banner', 'telegrarm' ); ?>"
					class="telegrarm-hero-image"
				/>
			</div>

			<div class="telegrarm-meta">
				<a href="<?php echo esc_url( $project_url ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Plugin Repository', 'telegrarm' ); ?>
				</a>
				<span><?php /* translators: %s: Plugin version. */ echo esc_html( sprintf( __( 'Version %s', 'telegrarm' ), BONO_TELEGRARM_VERSION ) ); ?></span>
				<a href="<?php echo esc_url( $author_url ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Renato Bonomini on GitHub', 'telegrarm' ); ?>
				</a>
				<a href="<?php echo esc_url( $git_updater_url ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Updates via Git Updater', 'telegrarm' ); ?>
				</a>
			</div>

			<div class="telegrarm-headline">
				<h1><?php esc_html_e( 'TelegrARM Settings', 'telegrarm' ); ?></h1>
				<p class="telegrarm-intro">
					<?php esc_html_e( 'Control which ARMember events send Telegram notifications, where those notifications go, and how profile fields are mapped into readable Telegram messages.', 'telegrarm' ); ?>
				</p>
				<p class="telegrarm-intro telegrarm-intro-secondary">
					<?php esc_html_e( 'TelegrARM keeps hooks disabled until each event toggle is enabled, so ARMember integrations only load the handlers you actually use.', 'telegrarm' ); ?>
				</p>
			</div>

			<?php settings_errors(); ?>

			<nav class="nav-tab-wrapper telegrarm-tabs" role="tablist" aria-label="<?php echo esc_attr__( 'TelegrARM settings sections', 'telegrarm' ); ?>">
				<a href="#bot" class="nav-tab telegrarm-tab nav-tab-active" role="tab" aria-selected="true" data-panel="bot">
					<?php esc_html_e( 'Bot setup', 'telegrarm' ); ?>
				</a>
				<a href="#new-user" class="nav-tab telegrarm-tab" role="tab" aria-selected="false" data-panel="new-user">
					<?php esc_html_e( 'New user', 'telegrarm' ); ?>
				</a>
				<a href="#profile" class="nav-tab telegrarm-tab" role="tab" aria-selected="false" data-panel="profile">
					<?php esc_html_e( 'Profile updates', 'telegrarm' ); ?>
				</a>
				<a href="#mapping" class="nav-tab telegrarm-tab" role="tab" aria-selected="false" data-panel="mapping">
					<?php esc_html_e( 'ARMember mapping', 'telegrarm' ); ?>
				</a>
				<a href="#telegram" class="nav-tab telegrarm-tab" role="tab" aria-selected="false" data-panel="telegram">
					<?php esc_html_e( 'Telegram setup', 'telegrarm' ); ?>
				</a>
			</nav>

			<form method="post" action="options.php" class="telegrarm-shell">
				<?php settings_fields( 'telegrarm_settings_group' ); ?>

				<section class="telegrarm-panel is-active" id="bot" data-panel="bot" role="tabpanel">
					<div class="telegrarm-panel-header">
						<div>
							<h2><?php esc_html_e( 'Bot credentials and shared configuration', 'telegrarm' ); ?></h2>
							<p><?php esc_html_e( 'Set the Telegram bot token once, then configure event-specific channels and mapping rules in the other tabs.', 'telegrarm' ); ?></p>
						</div>
					</div>

					<div class="telegrarm-card telegrarm-card-accent">
						<div class="telegrarm-switch-row">
							<div>
								<h3><?php esc_html_e( 'Telegram Bot API token', 'telegrarm' ); ?></h3>
								<p><?php esc_html_e( 'This token is used for both message and contact delivery through the Telegram Bot API.', 'telegrarm' ); ?></p>
							</div>
							<span class="telegrarm-badge <?php echo '' !== TelegrARM_Config::get_bot_token() ? 'is-enabled' : 'is-disabled'; ?>">
								<?php echo '' !== TelegrARM_Config::get_bot_token() ? esc_html__( 'Configured', 'telegrarm' ) : esc_html__( 'Missing', 'telegrarm' ); ?>
							</span>
						</div>

						<label class="telegrarm-field" for="telegram_bot_api_token">
							<span class="telegrarm-label"><?php esc_html_e( 'Bot token', 'telegrarm' ); ?></span>
							<input
								type="password"
								class="regular-text telegrarm-input"
								id="telegram_bot_api_token"
								name="telegram_bot_api_token"
								value=""
								placeholder="<?php echo esc_attr__( 'Leave blank to keep the configured token', 'telegrarm' ); ?>"
								autocomplete="new-password"
							/>
							<span class="description"><?php esc_html_e( 'Create the bot with @BotFather. Existing tokens are never redisplayed; enter a value only to replace it.', 'telegrarm' ); ?></span>
							<label>
								<input type="checkbox" name="telegrarm_clear_bot_token" value="1" />
								<?php esc_html_e( 'Remove the saved token when settings are saved', 'telegrarm' ); ?>
							</label>
						</label>

						<div class="telegrarm-grid telegrarm-grid-two">
							<div class="telegrarm-code-card">
								<strong><?php esc_html_e( 'Dependency', 'telegrarm' ); ?></strong>
								<span><?php esc_html_e( 'ARMember must be active so its user lifecycle hooks are available.', 'telegrarm' ); ?></span>
							</div>
							<div class="telegrarm-code-card">
								<strong><?php esc_html_e( 'Transport', 'telegrarm' ); ?></strong>
								<span><?php esc_html_e( 'TelegrARM queues event delivery through WP-Cron and uses the WordPress HTTP API to post to Telegram.', 'telegrarm' ); ?></span>
							</div>
						</div>

						<div class="telegrarm-switch-row telegrarm-switch-row-spaced">
							<div>
								<h3><?php esc_html_e( 'Debug logging', 'telegrarm' ); ?></h3>
								<p><?php esc_html_e( 'Write sanitized failure traces to the PHP error log when Telegram requests fail or handlers skip an event. Token values are never logged.', 'telegrarm' ); ?></p>
							</div>
							<label class="telegrarm-toggle">
								<input
									type="checkbox"
									name="telegrarm_debug_logging"
									value="1"
									<?php checked( true, $debug_logging_enabled, true ); ?>
								/>
								<span><?php esc_html_e( 'Enable debug logging', 'telegrarm' ); ?></span>
							</label>
						</div>
					</div>
				</section>

				<section class="telegrarm-panel" id="new-user" data-panel="new-user" role="tabpanel" hidden>
					<div class="telegrarm-panel-header">
						<div>
							<h2><?php esc_html_e( 'New user notifications', 'telegrarm' ); ?></h2>
							<p><?php esc_html_e( 'Control notifications sent from the ARMember new-registration hook.', 'telegrarm' ); ?></p>
						</div>
					</div>

					<div class="telegrarm-card telegrarm-card-accent">
						<div class="telegrarm-switch-row">
							<div>
								<h3><?php esc_html_e( 'Enable registration notifications', 'telegrarm' ); ?></h3>
								<p><?php esc_html_e( 'Loads the handler for arm_after_new_user_notification only when enabled.', 'telegrarm' ); ?></p>
							</div>
							<label class="telegrarm-toggle">
								<input
									type="checkbox"
									name="telegrarm_after_new_user_notification"
									value="1"
									<?php checked( true, $new_user_enabled, true ); ?>
								/>
								<span><?php esc_html_e( 'Enable new user messages', 'telegrarm' ); ?></span>
							</label>
						</div>

						<label class="telegrarm-field" for="telegram_channel_id_newuser">
							<span class="telegrarm-label"><?php esc_html_e( 'Channel or chat ID', 'telegrarm' ); ?></span>
							<input
								type="text"
								class="regular-text telegrarm-input"
								id="telegram_channel_id_newuser"
								name="telegram_channel_id_newuser"
								value="<?php echo esc_attr( get_option( 'telegram_channel_id_newuser', '' ) ); ?>"
							/>
							<span class="description"><?php esc_html_e( 'Use a numeric chat ID like -1001234567890 or a channel username such as @yourchannel.', 'telegrarm' ); ?></span>
						</label>

						<div class="telegrarm-test-message">
							<button
								type="button"
								class="button button-secondary telegrarm-test-message-button"
								data-channel-input="telegram_channel_id_newuser"
								data-target="new-user"
							>
								<?php esc_html_e( 'Send a test message', 'telegrarm' ); ?>
							</button>
							<p class="description"><?php esc_html_e( 'A test message will be sent to this channel to make sure it works.', 'telegrarm' ); ?></p>
							<div class="telegrarm-test-message-status" aria-live="polite"></div>
						</div>
					</div>

					<div class="telegrarm-card">
						<div class="telegrarm-switch-row">
							<div>
								<h3><?php esc_html_e( 'Optional contact card', 'telegrarm' ); ?></h3>
								<p><?php esc_html_e( 'Send a Telegram sendContact payload after the registration message when a phone number is available.', 'telegrarm' ); ?></p>
							</div>
							<label class="telegrarm-toggle">
								<input
									type="checkbox"
									name="telegram_send_contact_during_registration"
									value="1"
									<?php checked( true, $send_contact_enabled, true ); ?>
								/>
								<span><?php esc_html_e( 'Send contact details', 'telegrarm' ); ?></span>
							</label>
						</div>

						<div class="telegrarm-grid telegrarm-grid-two">
							<label class="telegrarm-field" for="telegram_phone_field_name">
								<span class="telegrarm-label"><?php esc_html_e( 'Phone field name', 'telegrarm' ); ?></span>
								<input
									type="text"
									class="regular-text telegrarm-input"
									id="telegram_phone_field_name"
									name="telegram_phone_field_name"
									value="<?php echo esc_attr( get_option( 'telegram_phone_field_name', 'text_t0cls' ) ); ?>"
								/>
								<span class="description"><?php esc_html_e( 'Enter the ARMember field key or stored user meta key that contains the phone number.', 'telegrarm' ); ?></span>
							</label>

							<label class="telegrarm-field" for="telegram_international_code_if_missing">
								<span class="telegrarm-label"><?php esc_html_e( 'Default international code', 'telegrarm' ); ?></span>
								<input
									type="text"
									class="regular-text telegrarm-input"
									id="telegram_international_code_if_missing"
									name="telegram_international_code_if_missing"
									value="<?php echo esc_attr( get_option( 'telegram_international_code_if_missing', '+1' ) ); ?>"
								/>
								<span class="description"><?php esc_html_e( 'Prepended only when the saved phone number does not already start with a plus sign.', 'telegrarm' ); ?></span>
							</label>
						</div>
					</div>
				</section>

				<section class="telegrarm-panel" id="profile" data-panel="profile" role="tabpanel" hidden>
					<div class="telegrarm-panel-header">
						<div>
							<h2><?php esc_html_e( 'Profile update notifications', 'telegrarm' ); ?></h2>
							<p><?php esc_html_e( 'Control notifications sent from the ARMember profile update hook.', 'telegrarm' ); ?></p>
						</div>
					</div>

					<div class="telegrarm-card telegrarm-card-accent">
						<div class="telegrarm-switch-row">
							<div>
								<h3><?php esc_html_e( 'Enable profile update notifications', 'telegrarm' ); ?></h3>
								<p><?php esc_html_e( 'Loads the handler for arm_update_profile_external only when enabled.', 'telegrarm' ); ?></p>
							</div>
							<label class="telegrarm-toggle">
								<input
									type="checkbox"
									name="telegrarm_profile_update"
									value="1"
									<?php checked( true, $profile_update_enabled, true ); ?>
								/>
								<span><?php esc_html_e( 'Enable profile update messages', 'telegrarm' ); ?></span>
							</label>
						</div>

						<label class="telegrarm-field" for="telegram_channel_id_updates">
							<span class="telegrarm-label"><?php esc_html_e( 'Channel or chat ID', 'telegrarm' ); ?></span>
							<input
								type="text"
								class="regular-text telegrarm-input"
								id="telegram_channel_id_updates"
								name="telegram_channel_id_updates"
								value="<?php echo esc_attr( get_option( 'telegram_channel_id_updates', '' ) ); ?>"
							/>
							<span class="description"><?php esc_html_e( 'Messages include only keys that are present in the ARMember mapping tab.', 'telegrarm' ); ?></span>
						</label>

						<div class="telegrarm-test-message">
							<button
								type="button"
								class="button button-secondary telegrarm-test-message-button"
								data-channel-input="telegram_channel_id_updates"
								data-target="profile"
							>
								<?php esc_html_e( 'Send a test message', 'telegrarm' ); ?>
							</button>
							<p class="description"><?php esc_html_e( 'A test message will be sent to this channel to make sure it works.', 'telegrarm' ); ?></p>
							<div class="telegrarm-test-message-status" aria-live="polite"></div>
						</div>
					</div>
				</section>

				<section class="telegrarm-panel" id="mapping" data-panel="mapping" role="tabpanel" hidden>
					<div class="telegrarm-panel-header">
						<div>
							<h2><?php esc_html_e( 'ARMember field mapping', 'telegrarm' ); ?></h2>
							<p><?php esc_html_e( 'Define which submitted keys are allowed into Telegram messages and how each key should be labeled.', 'telegrarm' ); ?></p>
						</div>
					</div>

					<div class="telegrarm-card">
						<label class="telegrarm-field" for="telegrarm_arm_mapping">
							<span class="telegrarm-label"><?php esc_html_e( 'Field mapping JSON', 'telegrarm' ); ?></span>
							<textarea
								class="large-text code telegrarm-textarea"
								id="telegrarm_arm_mapping"
								name="telegrarm_arm_mapping"
								rows="14"
							><?php echo esc_textarea( $mapping_json ); ?></textarea>
							<span class="description"><?php esc_html_e( 'Use a JSON object where each key is the stored ARMember field key and each value is the label shown in Telegram.', 'telegrarm' ); ?></span>
						</label>

						<div class="telegrarm-card telegrarm-card-accent telegrarm-mapping-builder">
							<div class="telegrarm-switch-row">
								<div>
									<h3><?php esc_html_e( 'Discover ARMember fields', 'telegrarm' ); ?></h3>
									<p><?php esc_html_e( 'Pull ARMember field keys from the plugin registry first, then fall back to stored user meta only if those registry sources are unavailable.', 'telegrarm' ); ?></p>
								</div>
								<button type="button" class="button button-primary" id="telegrarm-discover-metakeys">
									<?php esc_html_e( 'Discover fields', 'telegrarm' ); ?>
								</button>
							</div>

							<p class="description"><?php esc_html_e( 'Use the results below to select the keys you want, edit their labels, and generate the JSON back into the textarea above.', 'telegrarm' ); ?></p>
							<p class="description"><?php esc_html_e( 'Need a specific message order? Drag the Move control in the results table to rearrange fields before building the JSON.', 'telegrarm' ); ?></p>

							<div class="telegrarm-mapping-tools">
								<button type="button" class="button" id="telegrarm-select-all-metakeys"><?php esc_html_e( 'Select all', 'telegrarm' ); ?></button>
								<button type="button" class="button" id="telegrarm-select-none-metakeys"><?php esc_html_e( 'Select none', 'telegrarm' ); ?></button>
								<button type="button" class="button button-secondary" id="telegrarm-build-mapping"><?php esc_html_e( 'Build JSON', 'telegrarm' ); ?></button>
							</div>

							<div id="telegrarm-metakeys-status" class="telegrarm-discovery-status" aria-live="polite"></div>
							<div id="telegrarm-metakeys-results" class="telegrarm-metakeys-results" hidden></div>
						</div>

						<div class="telegrarm-grid telegrarm-grid-two">
							<div class="telegrarm-code-card">
								<strong><?php esc_html_e( 'Example mapping', 'telegrarm' ); ?></strong>
								<pre>{
	"first_name": "First Name",
	"last_name": "Last Name",
	"user_email": "Email"
}</pre>
							</div>
							<div class="telegrarm-code-card">
								<strong><?php esc_html_e( 'Built-in formatting', 'telegrarm' ); ?></strong>
								<span><?php esc_html_e( 'arm_social_field_instagram is converted into an Instagram link, and avatar is output as a clickable URL.', 'telegrarm' ); ?></span>
							</div>
						</div>
					</div>
				</section>

				<section class="telegrarm-panel" id="telegram" data-panel="telegram" role="tabpanel" hidden>
					<div class="telegrarm-panel-header">
						<div>
							<h2><?php esc_html_e( 'How to set up the Telegram bot', 'telegrarm' ); ?></h2>
							<p><?php esc_html_e( 'Create the bot once, add it to the destination chat, then paste the token and chat IDs back into the other tabs.', 'telegrarm' ); ?></p>
						</div>
					</div>

					<div class="telegrarm-card">
						<ol class="telegrarm-steps">
							<li><?php esc_html_e( 'Open Telegram and start a chat with @BotFather.', 'telegrarm' ); ?></li>
							<li><?php esc_html_e( 'Run /newbot and follow the prompts to name the bot and choose its username.', 'telegrarm' ); ?></li>
							<li><?php esc_html_e( 'Copy the bot token BotFather returns and paste it into the Bot setup tab.', 'telegrarm' ); ?></li>
							<li><?php esc_html_e( 'Add the bot to your target channel or group and grant it permission to post messages.', 'telegrarm' ); ?></li>
							<li><?php esc_html_e( 'Use the target chat ID or @channel username in the event tabs above.', 'telegrarm' ); ?></li>
						</ol>
						<p class="telegrarm-note">
							<?php esc_html_e( 'After entering the bot token and channel ID, use the Send a test message button in the New user or Profile updates tab to confirm the bot can receive requests and post into the specified channel.', 'telegrarm' ); ?>
						</p>
						<p class="telegrarm-note">
							<?php esc_html_e( 'Reference:', 'telegrarm' ); ?>
							<a href="https://core.telegram.org/bots/tutorial#introduction" target="_blank" rel="noopener noreferrer">core.telegram.org/bots/tutorial#introduction</a>.
						</p>
					</div>
				</section>

				<div class="telegrarm-footer">
					<?php submit_button( __( 'Save settings', 'telegrarm' ), 'primary', 'submit', false ); ?>
				</div>
			</form>
		</div>

	</div>
	<?php
}
