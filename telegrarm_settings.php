<?php
/**
 * TelegrARM - Telegram Bot Settings Integration for WordPress
 *
 * This file registers and manages all plugin settings, sections, and fields
 * for the TelegrARM plugin in the WordPress admin.
 *
 * @author  Renato Bonomini <https://github.com/renatobo>
 * @link    https://github.com/renatobo/TelegrARM
 * @license GPLv2 or later
 */

/**
 * Register the settings in the WordPress options table.
 */

function telegrarm_settings_init() {

    // 1) Register the new boolean option for "telegrarm_profile_update"
    register_setting('telegrarm_settings_group', 'telegrarm_profile_update', [
        'type'    => 'boolean',
        'default' => false,
    ]);

    // 2) Register the new boolean option for "telegrarm_after_new_user_notification"
    register_setting('telegrarm_settings_group', 'telegrarm_after_new_user_notification', [
        'type'    => 'boolean',
        'default' => false,
    ]);

    // 3/4/5 Existing settings (Token)
    register_setting('telegrarm_settings_group', 'telegram_bot_api_token');
    register_setting('telegrarm_settings_group', 'telegram_channel_id_newuser');
    register_setting('telegrarm_settings_group', 'telegram_channel_id_updates');

    // 6 register a boolean option for sending contact during registration
    register_setting('telegrarm_settings_group', 'telegram_send_contact_during_registration', [
        // 'type' => 'boolean' only works for WP 5.5+ with the REST API, 
        // but it's fine as a reference. You can omit 'type' if you want.
        'type'              => 'boolean',
        'default'           => false,
    ]);

    // 7 Register the phone field name option
    register_setting('telegrarm_settings_group','telegram_phone_field_name', 
        [ 
            'type'    => 'string',
            'default' => 'text_t0cls' 
        ]
    );

    // 8 Register the international code option (default "+1")
    register_setting('telegrarm_settings_group','telegram_international_code_if_missing',
        [
            'type'    => 'string',
            'default' => '+1'
        ]
    );

    // 9 Setting for the ARM mapping
    register_setting('telegrarm_settings_group','telegrarm_arm_mapping',
        array(
            'sanitize_callback' => 'telegrarm_arm_mapping_sanitize',
        )
    );

    /// ADDING SECTIONS 

    // 0 Add a section for these Telegram settings
    add_settings_section(
        'telegrarm_section_id',
        'Telegram Bot Settings',
        function() {
            echo '<p>Enter your Telegram bot credentials and mappings below.</p>';
        },
        'telegrarm_settings_page'
    );

    /**
     * SECTION 1: BOT setup
     */
    add_settings_section(
        'telegrarm_bot_setup_section',               // Section ID
        'BOT setup',                                 // Section Title
        'telegrarm_bot_setup_section_cb',            // Callback to output section intro text
        'telegrarm_settings_page'                    // Page slug (referenced below)
    );

    // 1 : for Bot API Token
    add_settings_field(
        'telegram_bot_api_token',
        'Telegram Bot API Token',
        'telegrarm_bot_api_token_field_cb',
        'telegrarm_settings_page',
        'telegrarm_bot_setup_section'
    );

    // 7 : for the ARM mapping (textarea with JSON)
    add_settings_field(
        'telegrarm_arm_mapping',
        'ARmember Keys Mapping (all fields)',
        'telegrarm_arm_mapping_field_cb',
        'telegrarm_settings_page',
        'telegrarm_bot_setup_section'
    );

    // 8 : "Phone Field Name"
    add_settings_field(
        'telegram_phone_field_name',          // Field ID
        'Phone Field Name',                   // Label
        'telegrarm_phone_field_name_field_cb',// Callback
        'telegrarm_settings_page',            // Page
        'telegrarm_bot_setup_section'                // Section
    );

    // 9 : "International Code if Missing"
    add_settings_field(
        'telegram_international_code_if_missing',           // Field ID
        'International code if missing',                    // Label
        'telegrarm_international_code_if_missing_field_cb', // Callback
        'telegrarm_settings_page',                          // Page
        'telegrarm_bot_setup_section'                              // Section
    );

    /**
     * SECTION 2: New User
     */
    add_settings_section(
        'telegrarm_new_user_section',                // Section ID
        'New User',                                  // Section Title
        'telegrarm_new_user_section_cb',             // Callback for intro text
        'telegrarm_settings_page'
    );

    // 4 : telegrarm_after_new_user_notification
    add_settings_field(
        'telegrarm_after_new_user_notification',
        'Enable New user notifications?',
        'telegrarm_after_new_user_notification_field_cb',
        'telegrarm_settings_page',
        'telegrarm_new_user_section'
    );

    // 5 : for Channel on new users
    add_settings_field(
        'telegram_channel_id_newuser',
        'Channel for new user',
        'telegrarm_channel_id_newusers_field_cb',
        'telegrarm_settings_page',
        'telegrarm_new_user_section'
    );

    // 6 : "Send Contact During Registration" (boolean checkbox)
    add_settings_field(
        'telegram_send_contact_during_registration',             // field ID
        'Send contact on new user registration?',        // label
        'telegrarm_send_contact_during_registration_field_cb',   // callback
        'telegrarm_settings_page',                       // page
        'telegrarm_new_user_section'                           // section
    );

    /**
     * SECTION 3: Update User
     */
    add_settings_section(
        'telegrarm_update_user_section',
        'Update User',
        'telegrarm_update_user_section_cb',
        'telegrarm_settings_page'
    );


    // 2 : telegrarm_profile_update
    add_settings_field(
        'telegrarm_profile_update',
        'Enable Profile Update notifications?',
        'telegrarm_profile_update_field_cb',
        'telegrarm_settings_page',
        'telegrarm_update_user_section'
    );

    // 3 : for Channel on Updates
    add_settings_field(
        'telegram_channel_id_updates',
        'Channel for profile updates',
        'telegrarm_channel_id_updates_field_cb',
        'telegrarm_settings_page',
        'telegrarm_update_user_section'
    );

}
add_action('admin_init', 'telegrarm_settings_init');

function telegrarm_bot_setup_section_cb() {
    echo '<hr><p>Configure the Bot Token and the meta fields mapping</p>';
}

function telegrarm_new_user_section_cb() {
    echo '<hr><p>Notify on new user registrations</p>';
}

function telegrarm_update_user_section_cb() {
    echo '<hr><p>Notify on user profile updates</p>';
}

/**
 * Sanitize the JSON input from our "telegrarm_arm_mapping" field.
 */
function telegrarm_arm_mapping_sanitize( $input ) {
    // If, for some reason, $input is already an array, convert it to JSON.
    if ( is_array( $input ) ) {
        $input = wp_json_encode( $input );
    }
    // Now $input is a string, so we can safely decode
    $decoded = json_decode( $input, true );

    // If decoding worked and returned an array, use that. Otherwise fallback.
    return is_array( $decoded ) ? $decoded : array();
}

/**
 * Callback for the "Bot API Token" field (masked).
 */
function telegrarm_bot_api_token_field_cb() {
    $token = get_option('telegram_bot_api_token', '');
    echo "<input type='password' name='telegram_bot_api_token' value='" . esc_attr($token) . "' style='width: 80%;' />";
}

/**
 * Callback for the "Channel for profile updates" field.
 */
function telegrarm_channel_id_updates_field_cb() {
    $channel_id = get_option('telegram_channel_id_updates', '');
    echo "<input type='text' name='telegram_channel_id_updates' value='" . esc_attr($channel_id) . "' style='width: 80%;' />";
}

/**
 * Callback for the "Channel for new user" field.
 */
function telegrarm_channel_id_newusers_field_cb() {
    $channel_id = get_option('telegram_channel_id_newuser', '');
    echo "<input type='text' name='telegram_channel_id_newuser' value='" . esc_attr($channel_id) . "' style='width: 80%;' />";
}

/**
 * Callback for the "Send contact on new user registration?" field.
 */
function telegrarm_send_contact_during_registration_field_cb() {
    // Retrieve the current value (default is false)
    $send_during_registration = get_option('telegram_send_contact_during_registration', false);

    // Output a checkbox
    echo '<label for="telegram_send_contact_during_registration">';
    echo '<input type="checkbox" name="telegram_send_contact_during_registration" id="telegram_send_contact_during_registration" value="1" ' 
         . checked(1, $send_during_registration, false) . ' />';
    echo ' Yes, send contact details to Telegram on new user registration</label>';
}

/**
 * Callback for the "Phone Field Name" setting.
 */
function telegrarm_phone_field_name_field_cb() {
    // Retrieve current value or default to ''
    $phone_field_name = get_option('telegram_phone_field_name', '');
    echo "<input type='text' name='telegram_phone_field_name' value='" 
         . esc_attr($phone_field_name) . "' style='width: 80%;' />";
    echo "<p class='description'>Enter the meta key or form field name that contains the phone number.</p>";
}

/**
 * Callback for the "International Code if Missing" setting.
 */
function telegrarm_international_code_if_missing_field_cb() {
    // Retrieve current value, default to '+1'
    $international_code = get_option('telegram_international_code_if_missing', '+1');
    echo "<input type='text' name='telegram_international_code_if_missing' value='" 
         . esc_attr($international_code) . "' style='width: 80%;' />";
    echo "<p class='description'>If the phone number has no country code, prepend this (e.g., +1).</p>";
}

/**
 * Callback for the ARM mapping field (textarea with JSON).
 */
function telegrarm_arm_mapping_field_cb() {
    // Fetch the stored option or default to an empty array
    $mapping_array = get_option('telegrarm_arm_mapping', array());

    // If it's empty, use our default mapping
    if (empty($mapping_array)) {
        $mapping_array = [
            'first_name' => 'First Name',
            'last_name'  => 'Last Name',
            "user_email" => "Email"
        ];
    }

    // Convert the array to a JSON string for display in the textarea
    $json_value = json_encode($mapping_array, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    echo '<p>Enter your ARmember key-to-label mapping as valid JSON.</p>';
    echo "<textarea name='telegrarm_arm_mapping' style='width: 80%; height: 250px;'>"
         . esc_textarea($json_value)
         . "</textarea>";
}

/**
 * Callback for "Enable telegrarm_profile_update?"
 */
function telegrarm_profile_update_field_cb() {
    $option_value = get_option('telegrarm_profile_update', false);
    echo '<label for="telegrarm_profile_update">';
    echo '<input type="checkbox" name="telegrarm_profile_update" id="telegrarm_profile_update" value="1" ' 
         . checked(1, $option_value, false) . ' />';
    echo ' Yes, enable telegrarm_profile_update functionality.';
    echo '</label>';
}

/**
 * Callback for "Enable telegrarm_after_new_user_notification?"
 */
function telegrarm_after_new_user_notification_field_cb() {
    $option_value = get_option('telegrarm_after_new_user_notification', false);
    echo '<label for="telegrarm_after_new_user_notification">';
    echo '<input type="checkbox" name="telegrarm_after_new_user_notification" id="telegrarm_after_new_user_notification" value="1" ' 
         . checked(1, $option_value, false) . ' />';
    echo ' Yes, enable telegrarm_after_new_user_notification.';
    echo '</label>';
}

/**
 * Create a menu item in the WordPress Admin under "Settings".
 */
function telegrarm_settings_menu() {
    add_options_page(
        'Telegram Settings',
        'Telegram Bot',
        'manage_options',
        'telegrarm_settings_page',
        'telegrarm_settings_page_cb'
    );
}
add_action('admin_menu', 'telegrarm_settings_menu');

/**
 * The main settings page for Telegram Bot settings & mapping.
 */
function telegrarm_settings_page_cb() {
    ?>
    <div class="wrap">
        <h1>Telegram Bot Settings</h1>
        <p>
            <strong>TelegrARM</strong> enables Telegram notifications for select ARMember user events, such as profile updates and new user registrations. Use this page to configure your Telegram bot integration and ARMember field mappings.
        </p>
        <div style="margin-bottom:1em; padding: 10px; background: #f8f8f8; border-left: 4px solid #0088cc;">
            <strong>Plugin Updates:</strong><br>
            Current Version: <?php echo BONO_TELEGRARM_VERSION; ?><br>
            This plugin supports automatic updates via GitHub using the <a href="https://github.com/afragen/github-updater" target="_blank">GitHub Updater</a> plugin.<br>
            To enable updates, install and activate the GitHub Updater plugin. The repository is:<br>
            <code>https://github.com/renatobo/TelegrARM</code>
        </div>
        <form method="post" action="options.php">
            <?php
            settings_fields('telegrarm_settings_group');
            do_settings_sections('telegrarm_settings_page');
            submit_button('Save Telegram Settings');
            ?>
        </form>
        <div style="margin-top:2em; padding: 10px; background: #f8f8f8; border-left: 4px solid #34ab5e;">
            <strong>How to set up a Telegram Bot and retrieve the API token:</strong>
            <ol>
                <li>Open Telegram and search for <strong>@BotFather</strong>.</li>
                <li>Start a chat and send the command <code>/newbot</code>.</li>
                <li>Follow the instructions to choose a name and username for your bot.</li>
                <li>After creation, BotFather will provide you with an <strong>API token</strong>. Copy this token and paste it into the "Telegram Bot API Token" field above.</li>
                <li>Add your bot to your Telegram group or channel and grant it permission to post messages.</li>
            </ol>
            For more details, see the <a href="https://core.telegram.org/bots/tutorial#introduction" target="_blank">Telegram Bot documentation</a>.
        </div>
    </div>
    <?php
}