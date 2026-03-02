<?php
/**
 * Register and render the TelegrARM admin settings page.
 *
 * @package TelegrARM
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_init', 'telegrarm_settings_init');
add_action('admin_menu', 'telegrarm_settings_menu');

/**
 * Register plugin settings.
 */
function telegrarm_settings_init() {
    register_setting(
        'telegrarm_settings_group',
        'telegrarm_profile_update',
        array(
            'type' => 'boolean',
            'sanitize_callback' => 'telegrarm_sanitize_checkbox',
            'default' => false,
        )
    );

    register_setting(
        'telegrarm_settings_group',
        'telegrarm_after_new_user_notification',
        array(
            'type' => 'boolean',
            'sanitize_callback' => 'telegrarm_sanitize_checkbox',
            'default' => false,
        )
    );

    register_setting(
        'telegrarm_settings_group',
        'telegram_bot_api_token',
        array(
            'type' => 'string',
            'sanitize_callback' => 'telegrarm_sanitize_bot_token',
            'default' => '',
        )
    );

    register_setting(
        'telegrarm_settings_group',
        'telegram_channel_id_newuser',
        array(
            'type' => 'string',
            'sanitize_callback' => 'telegrarm_sanitize_channel_id',
            'default' => '',
        )
    );

    register_setting(
        'telegrarm_settings_group',
        'telegram_channel_id_updates',
        array(
            'type' => 'string',
            'sanitize_callback' => 'telegrarm_sanitize_channel_id',
            'default' => '',
        )
    );

    register_setting(
        'telegrarm_settings_group',
        'telegram_send_contact_during_registration',
        array(
            'type' => 'boolean',
            'sanitize_callback' => 'telegrarm_sanitize_checkbox',
            'default' => false,
        )
    );

    register_setting(
        'telegrarm_settings_group',
        'telegram_phone_field_name',
        array(
            'type' => 'string',
            'sanitize_callback' => 'telegrarm_sanitize_setting_text',
            'default' => 'text_t0cls',
        )
    );

    register_setting(
        'telegrarm_settings_group',
        'telegram_international_code_if_missing',
        array(
            'type' => 'string',
            'sanitize_callback' => 'telegrarm_sanitize_international_code',
            'default' => '+1',
        )
    );

    register_setting(
        'telegrarm_settings_group',
        'telegrarm_arm_mapping',
        array(
            'type' => 'array',
            'sanitize_callback' => 'telegrarm_arm_mapping_sanitize',
            'default' => telegrarm_get_default_arm_mapping(),
        )
    );
}

/**
 * Sanitize checkbox values.
 *
 * @param mixed $value Submitted option value.
 * @return bool
 */
function telegrarm_sanitize_checkbox($value) {
    return !empty($value);
}

/**
 * Sanitize generic text settings.
 *
 * @param mixed $value Submitted option value.
 * @return string
 */
function telegrarm_sanitize_setting_text($value) {
    return is_scalar($value) ? trim(sanitize_text_field((string) $value)) : '';
}

/**
 * Sanitize the Telegram bot token.
 *
 * @param mixed $value Submitted option value.
 * @return string
 */
function telegrarm_sanitize_bot_token($value) {
    $token = telegrarm_sanitize_setting_text($value);

    $token = preg_replace('/\s+/', '', $token);

    return is_string($token) ? $token : '';
}

/**
 * Sanitize Telegram channel or chat identifiers.
 *
 * @param mixed $value Submitted option value.
 * @return string
 */
function telegrarm_sanitize_channel_id($value) {
    $channel_id = telegrarm_sanitize_setting_text($value);

    $channel_id = preg_replace('/\s+/', '', $channel_id);

    return is_string($channel_id) ? $channel_id : '';
}

/**
 * Sanitize the default country code.
 *
 * @param mixed $value Submitted option value.
 * @return string
 */
function telegrarm_sanitize_international_code($value) {
    $code = telegrarm_sanitize_setting_text($value);
    $code = preg_replace('/\s+/', '', $code);
    $code = is_string($code) ? $code : '';

    if ('' === $code) {
        return '+1';
    }

    return $code;
}

/**
 * Return the default ARMember field mapping.
 *
 * @return array<string, string>
 */
function telegrarm_get_default_arm_mapping() {
    return array(
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'user_email' => 'Email',
    );
}

/**
 * Return the saved ARMember mapping with defaults.
 *
 * @return array<string, string>
 */
function telegrarm_get_arm_mapping() {
    $mapping = get_option('telegrarm_arm_mapping', telegrarm_get_default_arm_mapping());

    if (!is_array($mapping) || empty($mapping)) {
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
function telegrarm_arm_mapping_sanitize($input) {
    if (is_array($input)) {
        $decoded = $input;
    } elseif (is_string($input) && '' !== trim($input)) {
        $decoded = json_decode(wp_unslash($input), true);
    } else {
        return telegrarm_get_default_arm_mapping();
    }

    if (!is_array($decoded)) {
        add_settings_error(
            'telegrarm_arm_mapping',
            'telegrarm_arm_mapping_invalid_json',
            __('The ARMember field mapping must be valid JSON.', 'telegrarm'),
            'error'
        );

        return telegrarm_get_arm_mapping();
    }

    $sanitized = array();

    foreach ($decoded as $key => $label) {
        if (!is_scalar($key) || !is_scalar($label)) {
            continue;
        }

        $mapping_key = trim(sanitize_text_field((string) $key));
        $mapping_label = trim(sanitize_text_field((string) $label));

        if ('' === $mapping_key || '' === $mapping_label) {
            continue;
        }

        $sanitized[$mapping_key] = $mapping_label;
    }

    if (empty($sanitized)) {
        add_settings_error(
            'telegrarm_arm_mapping',
            'telegrarm_arm_mapping_empty',
            __('The ARMember field mapping could not be saved because it did not contain any valid key and label pairs.', 'telegrarm'),
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
        __('TelegrARM Settings', 'telegrarm'),
        __('TelegrARM', 'telegrarm'),
        'manage_options',
        'telegrarm_settings_page',
        'telegrarm_settings_page_cb'
    );
}

/**
 * Render the settings page.
 */
function telegrarm_settings_page_cb() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $project_url = 'https://github.com/renatobo/TelegrARM';
    $author_url = 'https://github.com/renatobo';
    $git_updater_url = 'https://github.com/afragen/git-updater';
    $banner_url = plugins_url('assets/telegrarm-settings-banner.svg', __FILE__);

    $new_user_enabled = (bool) get_option('telegrarm_after_new_user_notification', false);
    $profile_update_enabled = (bool) get_option('telegrarm_profile_update', false);
    $send_contact_enabled = (bool) get_option('telegram_send_contact_during_registration', false);
    $mapping_json = wp_json_encode(
        telegrarm_get_arm_mapping(),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    ?>
    <div class="wrap">
        <div class="telegrarm-admin">
            <div class="telegrarm-hero">
                <img
                    src="<?php echo esc_url($banner_url); ?>"
                    alt="<?php echo esc_attr__('TelegrARM settings banner', 'telegrarm'); ?>"
                    class="telegrarm-hero-image"
                />
            </div>

            <div class="telegrarm-meta">
                <a href="<?php echo esc_url($project_url); ?>" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e('Plugin Repository', 'telegrarm'); ?>
                </a>
                <span><?php echo esc_html(sprintf(__('Version %s', 'telegrarm'), BONO_TELEGRARM_VERSION)); ?></span>
                <a href="<?php echo esc_url($author_url); ?>" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e('Renato Bonomini on GitHub', 'telegrarm'); ?>
                </a>
                <a href="<?php echo esc_url($git_updater_url); ?>" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e('Updates via Git Updater', 'telegrarm'); ?>
                </a>
            </div>

            <div class="telegrarm-headline">
                <h1><?php esc_html_e('TelegrARM Settings', 'telegrarm'); ?></h1>
                <p class="telegrarm-intro">
                    <?php esc_html_e('Control which ARMember events send Telegram notifications, where those notifications go, and how profile fields are mapped into readable Telegram messages.', 'telegrarm'); ?>
                </p>
                <p class="telegrarm-intro telegrarm-intro-secondary">
                    <?php esc_html_e('TelegrARM keeps hooks disabled until each event toggle is enabled, so ARMember integrations only load the handlers you actually use.', 'telegrarm'); ?>
                </p>
            </div>

            <?php settings_errors(); ?>

            <nav class="nav-tab-wrapper telegrarm-tabs" role="tablist" aria-label="<?php echo esc_attr__('TelegrARM settings sections', 'telegrarm'); ?>">
                <a href="#bot" class="nav-tab telegrarm-tab nav-tab-active" role="tab" aria-selected="true" data-panel="bot">
                    <?php esc_html_e('Bot setup', 'telegrarm'); ?>
                </a>
                <a href="#new-user" class="nav-tab telegrarm-tab" role="tab" aria-selected="false" data-panel="new-user">
                    <?php esc_html_e('New user', 'telegrarm'); ?>
                </a>
                <a href="#profile" class="nav-tab telegrarm-tab" role="tab" aria-selected="false" data-panel="profile">
                    <?php esc_html_e('Profile updates', 'telegrarm'); ?>
                </a>
                <a href="#mapping" class="nav-tab telegrarm-tab" role="tab" aria-selected="false" data-panel="mapping">
                    <?php esc_html_e('ARMember mapping', 'telegrarm'); ?>
                </a>
                <a href="#telegram" class="nav-tab telegrarm-tab" role="tab" aria-selected="false" data-panel="telegram">
                    <?php esc_html_e('Telegram setup', 'telegrarm'); ?>
                </a>
            </nav>

            <form method="post" action="options.php" class="telegrarm-shell">
                <?php settings_fields('telegrarm_settings_group'); ?>

                <section class="telegrarm-panel is-active" id="bot" data-panel="bot" role="tabpanel">
                    <div class="telegrarm-panel-header">
                        <div>
                            <h2><?php esc_html_e('Bot credentials and shared configuration', 'telegrarm'); ?></h2>
                            <p><?php esc_html_e('Set the Telegram bot token once, then configure event-specific channels and mapping rules in the other tabs.', 'telegrarm'); ?></p>
                        </div>
                    </div>

                    <div class="telegrarm-card telegrarm-card-accent">
                        <div class="telegrarm-switch-row">
                            <div>
                                <h3><?php esc_html_e('Telegram Bot API token', 'telegrarm'); ?></h3>
                                <p><?php esc_html_e('This token is used for both message and contact delivery through the Telegram Bot API.', 'telegrarm'); ?></p>
                            </div>
                            <span class="telegrarm-badge <?php echo '' !== get_option('telegram_bot_api_token', '') ? 'is-enabled' : 'is-disabled'; ?>">
                                <?php echo '' !== get_option('telegram_bot_api_token', '') ? esc_html__('Configured', 'telegrarm') : esc_html__('Missing', 'telegrarm'); ?>
                            </span>
                        </div>

                        <label class="telegrarm-field" for="telegram_bot_api_token">
                            <span class="telegrarm-label"><?php esc_html_e('Bot token', 'telegrarm'); ?></span>
                            <input
                                type="password"
                                class="regular-text telegrarm-input"
                                id="telegram_bot_api_token"
                                name="telegram_bot_api_token"
                                value="<?php echo esc_attr(get_option('telegram_bot_api_token', '')); ?>"
                                autocomplete="new-password"
                            />
                            <span class="description"><?php esc_html_e('Create the bot with @BotFather and paste the token exactly as provided.', 'telegrarm'); ?></span>
                        </label>

                        <div class="telegrarm-grid telegrarm-grid-two">
                            <div class="telegrarm-code-card">
                                <strong><?php esc_html_e('Dependency', 'telegrarm'); ?></strong>
                                <span><?php esc_html_e('ARMember must be active so its user lifecycle hooks are available.', 'telegrarm'); ?></span>
                            </div>
                            <div class="telegrarm-code-card">
                                <strong><?php esc_html_e('Transport', 'telegrarm'); ?></strong>
                                <span><?php esc_html_e('TelegrARM uses the WordPress HTTP API to post directly to Telegram.', 'telegrarm'); ?></span>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="telegrarm-panel" id="new-user" data-panel="new-user" role="tabpanel" hidden>
                    <div class="telegrarm-panel-header">
                        <div>
                            <h2><?php esc_html_e('New user notifications', 'telegrarm'); ?></h2>
                            <p><?php esc_html_e('Control notifications sent from the ARMember new-registration hook.', 'telegrarm'); ?></p>
                        </div>
                    </div>

                    <div class="telegrarm-card telegrarm-card-accent">
                        <div class="telegrarm-switch-row">
                            <div>
                                <h3><?php esc_html_e('Enable registration notifications', 'telegrarm'); ?></h3>
                                <p><?php esc_html_e('Loads the handler for arm_after_new_user_notification only when enabled.', 'telegrarm'); ?></p>
                            </div>
                            <label class="telegrarm-toggle">
                                <input
                                    type="checkbox"
                                    name="telegrarm_after_new_user_notification"
                                    value="1"
                                    <?php checked(true, $new_user_enabled, true); ?>
                                />
                                <span><?php esc_html_e('Enable new user messages', 'telegrarm'); ?></span>
                            </label>
                        </div>

                        <label class="telegrarm-field" for="telegram_channel_id_newuser">
                            <span class="telegrarm-label"><?php esc_html_e('Channel or chat ID', 'telegrarm'); ?></span>
                            <input
                                type="text"
                                class="regular-text telegrarm-input"
                                id="telegram_channel_id_newuser"
                                name="telegram_channel_id_newuser"
                                value="<?php echo esc_attr(get_option('telegram_channel_id_newuser', '')); ?>"
                            />
                            <span class="description"><?php esc_html_e('Use a numeric chat ID like -1001234567890 or a channel username such as @yourchannel.', 'telegrarm'); ?></span>
                        </label>
                    </div>

                    <div class="telegrarm-card">
                        <div class="telegrarm-switch-row">
                            <div>
                                <h3><?php esc_html_e('Optional contact card', 'telegrarm'); ?></h3>
                                <p><?php esc_html_e('Send a Telegram sendContact payload after the registration message when a phone number is available.', 'telegrarm'); ?></p>
                            </div>
                            <label class="telegrarm-toggle">
                                <input
                                    type="checkbox"
                                    name="telegram_send_contact_during_registration"
                                    value="1"
                                    <?php checked(true, $send_contact_enabled, true); ?>
                                />
                                <span><?php esc_html_e('Send contact details', 'telegrarm'); ?></span>
                            </label>
                        </div>

                        <div class="telegrarm-grid telegrarm-grid-two">
                            <label class="telegrarm-field" for="telegram_phone_field_name">
                                <span class="telegrarm-label"><?php esc_html_e('Phone field name', 'telegrarm'); ?></span>
                                <input
                                    type="text"
                                    class="regular-text telegrarm-input"
                                    id="telegram_phone_field_name"
                                    name="telegram_phone_field_name"
                                    value="<?php echo esc_attr(get_option('telegram_phone_field_name', 'text_t0cls')); ?>"
                                />
                                <span class="description"><?php esc_html_e('Enter the ARMember field key or stored user meta key that contains the phone number.', 'telegrarm'); ?></span>
                            </label>

                            <label class="telegrarm-field" for="telegram_international_code_if_missing">
                                <span class="telegrarm-label"><?php esc_html_e('Default international code', 'telegrarm'); ?></span>
                                <input
                                    type="text"
                                    class="regular-text telegrarm-input"
                                    id="telegram_international_code_if_missing"
                                    name="telegram_international_code_if_missing"
                                    value="<?php echo esc_attr(get_option('telegram_international_code_if_missing', '+1')); ?>"
                                />
                                <span class="description"><?php esc_html_e('Prepended only when the saved phone number does not already start with a plus sign.', 'telegrarm'); ?></span>
                            </label>
                        </div>
                    </div>
                </section>

                <section class="telegrarm-panel" id="profile" data-panel="profile" role="tabpanel" hidden>
                    <div class="telegrarm-panel-header">
                        <div>
                            <h2><?php esc_html_e('Profile update notifications', 'telegrarm'); ?></h2>
                            <p><?php esc_html_e('Control notifications sent from the ARMember profile update hook.', 'telegrarm'); ?></p>
                        </div>
                    </div>

                    <div class="telegrarm-card telegrarm-card-accent">
                        <div class="telegrarm-switch-row">
                            <div>
                                <h3><?php esc_html_e('Enable profile update notifications', 'telegrarm'); ?></h3>
                                <p><?php esc_html_e('Loads the handler for arm_update_profile_external only when enabled.', 'telegrarm'); ?></p>
                            </div>
                            <label class="telegrarm-toggle">
                                <input
                                    type="checkbox"
                                    name="telegrarm_profile_update"
                                    value="1"
                                    <?php checked(true, $profile_update_enabled, true); ?>
                                />
                                <span><?php esc_html_e('Enable profile update messages', 'telegrarm'); ?></span>
                            </label>
                        </div>

                        <label class="telegrarm-field" for="telegram_channel_id_updates">
                            <span class="telegrarm-label"><?php esc_html_e('Channel or chat ID', 'telegrarm'); ?></span>
                            <input
                                type="text"
                                class="regular-text telegrarm-input"
                                id="telegram_channel_id_updates"
                                name="telegram_channel_id_updates"
                                value="<?php echo esc_attr(get_option('telegram_channel_id_updates', '')); ?>"
                            />
                            <span class="description"><?php esc_html_e('Messages include only keys that are present in the ARMember mapping tab.', 'telegrarm'); ?></span>
                        </label>
                    </div>
                </section>

                <section class="telegrarm-panel" id="mapping" data-panel="mapping" role="tabpanel" hidden>
                    <div class="telegrarm-panel-header">
                        <div>
                            <h2><?php esc_html_e('ARMember field mapping', 'telegrarm'); ?></h2>
                            <p><?php esc_html_e('Define which submitted keys are allowed into Telegram messages and how each key should be labeled.', 'telegrarm'); ?></p>
                        </div>
                    </div>

                    <div class="telegrarm-card">
                        <label class="telegrarm-field" for="telegrarm_arm_mapping">
                            <span class="telegrarm-label"><?php esc_html_e('Field mapping JSON', 'telegrarm'); ?></span>
                            <textarea
                                class="large-text code telegrarm-textarea"
                                id="telegrarm_arm_mapping"
                                name="telegrarm_arm_mapping"
                                rows="14"
                            ><?php echo esc_textarea($mapping_json); ?></textarea>
                            <span class="description"><?php esc_html_e('Use a JSON object where each key is the stored ARMember field key and each value is the label shown in Telegram.', 'telegrarm'); ?></span>
                        </label>

                        <div class="telegrarm-grid telegrarm-grid-two">
                            <div class="telegrarm-code-card">
                                <strong><?php esc_html_e('Example mapping', 'telegrarm'); ?></strong>
                                <pre>{
  "first_name": "First Name",
  "last_name": "Last Name",
  "user_email": "Email"
}</pre>
                            </div>
                            <div class="telegrarm-code-card">
                                <strong><?php esc_html_e('Built-in formatting', 'telegrarm'); ?></strong>
                                <span><?php esc_html_e('arm_social_field_instagram is converted into an Instagram link, and avatar is output as a clickable URL.', 'telegrarm'); ?></span>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="telegrarm-panel" id="telegram" data-panel="telegram" role="tabpanel" hidden>
                    <div class="telegrarm-panel-header">
                        <div>
                            <h2><?php esc_html_e('How to set up the Telegram bot', 'telegrarm'); ?></h2>
                            <p><?php esc_html_e('Create the bot once, add it to the destination chat, then paste the token and chat IDs back into the other tabs.', 'telegrarm'); ?></p>
                        </div>
                    </div>

                    <div class="telegrarm-card">
                        <ol class="telegrarm-steps">
                            <li><?php esc_html_e('Open Telegram and start a chat with @BotFather.', 'telegrarm'); ?></li>
                            <li><?php esc_html_e('Run /newbot and follow the prompts to name the bot and choose its username.', 'telegrarm'); ?></li>
                            <li><?php esc_html_e('Copy the bot token BotFather returns and paste it into the Bot setup tab.', 'telegrarm'); ?></li>
                            <li><?php esc_html_e('Add the bot to your target channel or group and grant it permission to post messages.', 'telegrarm'); ?></li>
                            <li><?php esc_html_e('Use the target chat ID or @channel username in the event tabs above.', 'telegrarm'); ?></li>
                        </ol>
                        <p class="telegrarm-note">
                            <?php esc_html_e('Reference:', 'telegrarm'); ?>
                            <a href="https://core.telegram.org/bots/tutorial#introduction" target="_blank" rel="noopener noreferrer">core.telegram.org/bots/tutorial#introduction</a>.
                        </p>
                    </div>
                </section>

                <div class="telegrarm-footer">
                    <?php submit_button(__('Save settings', 'telegrarm'), 'primary', 'submit', false); ?>
                </div>
            </form>
        </div>

        <style>
            .telegrarm-admin {
                max-width: 1120px;
                margin-top: 18px;
            }

            .telegrarm-hero {
                margin: 0 0 16px;
                border: 1px solid #c8ccd0;
                background: #f6f7f7;
                display: block;
                max-width: 750px;
                width: fit-content;
            }

            .telegrarm-hero-image {
                display: block;
                width: min(100%, 750px);
                height: auto;
            }

            .telegrarm-headline {
                margin: 8px 0 20px;
            }

            .telegrarm-headline h1 {
                margin: 0 0 8px;
                font-size: 42px;
                line-height: 1.1;
                color: #0f172a;
                font-weight: 400;
            }

            .telegrarm-intro,
            .telegrarm-panel-header p,
            .telegrarm-note,
            .telegrarm-switch-row p,
            .telegrarm-code-card span,
            .telegrarm-field .description {
                margin: 0;
                color: #475569;
                font-size: 14px;
                line-height: 1.65;
            }

            .telegrarm-meta {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                margin: 16px 0 10px;
            }

            .telegrarm-meta a,
            .telegrarm-meta span {
                display: inline-flex;
                align-items: center;
                min-height: 36px;
                padding: 0 14px;
                background: #f6f7f7;
                border: 1px solid #c3c4c7;
                color: #0f172a;
                text-decoration: none;
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
            }

            .telegrarm-meta a:hover {
                border-color: #2271b1;
                color: #2271b1;
            }

            .telegrarm-intro {
                margin-bottom: 20px;
                max-width: 76ch;
            }

            .telegrarm-intro-secondary {
                margin-top: -8px;
            }

            .telegrarm-tabs {
                margin: 24px 0 0;
            }

            .telegrarm-tabs .telegrarm-tab {
                display: inline-block;
                float: none;
            }

            .telegrarm-tabs .telegrarm-tab:focus {
                box-shadow: 0 0 0 1px #2271b1;
            }

            .telegrarm-shell {
                display: grid;
                gap: 18px;
                padding-top: 20px;
            }

            .telegrarm-panel {
                display: grid;
                gap: 18px;
            }

            .telegrarm-panel[hidden] {
                display: none;
            }

            .telegrarm-panel-header h2,
            .telegrarm-switch-row h3 {
                margin: 0 0 8px;
                color: #0f172a;
            }

            .telegrarm-card {
                padding: 22px;
                border: 1px solid #c3c4c7;
                background: #ffffff;
            }

            .telegrarm-card-accent {
                border-left: 4px solid #72aee6;
                background: #f6f7f7;
            }

            .telegrarm-switch-row {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 18px;
                margin-bottom: 18px;
            }

            .telegrarm-toggle {
                display: inline-flex;
                gap: 10px;
                align-items: center;
                background: #ffffff;
                border: 1px solid #c3c4c7;
                padding: 12px 14px;
                font-weight: 600;
                color: #0f172a;
            }

            .telegrarm-grid {
                display: grid;
                gap: 14px;
            }

            .telegrarm-grid-two {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .telegrarm-code-card {
                display: grid;
                gap: 8px;
                padding: 14px;
                border: 1px solid #dcdcde;
                background: #ffffff;
            }

            .telegrarm-field {
                display: grid;
                gap: 8px;
                margin-bottom: 18px;
            }

            .telegrarm-field:last-child {
                margin-bottom: 0;
            }

            .telegrarm-label {
                font-weight: 600;
                color: #0f172a;
            }

            .telegrarm-input,
            .telegrarm-textarea {
                width: 100%;
                max-width: 680px;
            }

            .telegrarm-badge {
                display: inline-flex;
                align-items: center;
                padding: 2px 8px;
                border-radius: 999px;
                font-size: 12px;
                font-weight: 600;
                line-height: 1.6;
            }

            .telegrarm-badge.is-enabled {
                background: #edf7ed;
                color: #116329;
            }

            .telegrarm-badge.is-disabled {
                background: #fcf0f1;
                color: #8a2424;
            }

            .telegrarm-footer {
                display: flex;
                justify-content: flex-start;
            }

            .telegrarm-steps {
                margin: 0;
                padding-left: 18px;
                color: #1e293b;
            }

            .telegrarm-steps li + li {
                margin-top: 8px;
            }

            code,
            pre {
                background: #f1f1f1;
                border-radius: 4px;
            }

            code {
                padding: 2px 6px;
            }

            pre {
                padding: 12px;
                overflow-x: auto;
            }

            @media (max-width: 960px) {
                .telegrarm-grid-two,
                .telegrarm-switch-row {
                    grid-template-columns: 1fr;
                    display: grid;
                }

                .telegrarm-switch-row {
                    justify-content: stretch;
                }
            }
        </style>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tabs = document.querySelectorAll('.telegrarm-tab');
            const panels = document.querySelectorAll('.telegrarm-panel');

            function activateTab(targetPanel, updateHash) {
                let hasMatch = false;

                tabs.forEach(function (item) {
                    const isTarget = item.getAttribute('data-panel') === targetPanel;
                    item.classList.toggle('nav-tab-active', isTarget);
                    item.setAttribute('aria-selected', isTarget ? 'true' : 'false');
                    hasMatch = hasMatch || isTarget;
                });

                panels.forEach(function (panel) {
                    const isTarget = panel.getAttribute('data-panel') === targetPanel;
                    panel.classList.toggle('is-active', isTarget);
                    panel.hidden = !isTarget;
                });

                if (hasMatch && updateHash) {
                    window.location.hash = targetPanel;
                }
            }

            tabs.forEach(function (tab) {
                tab.addEventListener('click', function (event) {
                    event.preventDefault();
                    activateTab(tab.getAttribute('data-panel'), true);
                });
            });

            const initialPanel = window.location.hash ? window.location.hash.replace('#', '') : 'bot';
            activateTab(initialPanel, false);

            window.addEventListener('hashchange', function () {
                const hashPanel = window.location.hash ? window.location.hash.replace('#', '') : 'bot';
                activateTab(hashPanel, false);
            });
        });
        </script>
    </div>
    <?php
}
