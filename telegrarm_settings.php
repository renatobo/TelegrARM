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
add_action('wp_ajax_telegrarm_discover_arm_metakeys', 'telegrarm_ajax_discover_arm_metakeys');
add_action('wp_ajax_telegrarm_send_test_message', 'telegrarm_ajax_send_test_message');

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
        'telegrarm_debug_logging',
        array(
            'type' => 'boolean',
            'sanitize_callback' => 'telegrarm_sanitize_checkbox',
            'default' => false,
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
 * Determine whether TelegrARM debug logging is enabled.
 *
 * @return bool
 */
function telegrarm_is_debug_logging_enabled() {
    return (bool) get_option('telegrarm_debug_logging', false);
}

/**
 * Normalize a debug context value so it can be safely encoded.
 *
 * @param mixed  $value Context value.
 * @param string $key   Optional context key.
 * @return mixed
 */
function telegrarm_sanitize_debug_context_value($value, $key = '') {
    if (is_array($value)) {
        $sanitized = array();

        foreach ($value as $nested_key => $nested_value) {
            $sanitized[$nested_key] = telegrarm_sanitize_debug_context_value($nested_value, is_scalar($nested_key) ? (string) $nested_key : '');
        }

        return $sanitized;
    }

    if (is_object($value)) {
        return telegrarm_sanitize_debug_context_value((array) $value, $key);
    }

    if (!is_scalar($value) && null !== $value) {
        return '';
    }

    if ('' !== $key && preg_match('/token|secret|authorization|password|phone/i', $key)) {
        return '[redacted]';
    }

    if (is_bool($value) || is_int($value) || is_float($value) || null === $value) {
        return $value;
    }

    $text = sanitize_textarea_field((string) $value);

    return function_exists('mb_substr') ? mb_substr($text, 0, 500) : substr($text, 0, 500);
}

/**
 * Write a sanitized debug message to the PHP error log when enabled.
 *
 * @param string $message Log message.
 * @param array  $context Optional structured context.
 * @return void
 */
function telegrarm_log_debug_message($message, array $context = array()) {
    if (!telegrarm_is_debug_logging_enabled()) {
        return;
    }

    $entry = array(
        'message' => sanitize_text_field((string) $message),
    );

    if (!empty($context)) {
        $entry['context'] = telegrarm_sanitize_debug_context_value($context);
    }

    error_log('TelegrARM debug: ' . wp_json_encode($entry));
}

/**
 * Extract normalized details from a Telegram Bot API response.
 *
 * @param array<string, mixed>|mixed $response HTTP response.
 * @return array{status_code:int,ok:bool|null,description:string,error_code:int|null}
 */
function telegrarm_get_telegram_response_details($response) {
    $details = array(
        'status_code' => 0,
        'ok'          => null,
        'description' => __('Unknown Telegram API error.', 'telegrarm'),
        'error_code'  => null,
    );

    if (!is_array($response)) {
        return $details;
    }

    $details['status_code'] = (int) wp_remote_retrieve_response_code($response);

    $body = wp_remote_retrieve_body($response);

    if (!is_string($body) || '' === trim($body)) {
        if (200 === $details['status_code']) {
            $details['description'] = __('Empty Telegram API response body.', 'telegrarm');
        }

        return $details;
    }

    $decoded = json_decode($body, true);

    if (!is_array($decoded)) {
        $details['description'] = __('Invalid Telegram API response body.', 'telegrarm');

        return $details;
    }

    if (array_key_exists('ok', $decoded)) {
        $details['ok'] = (bool) $decoded['ok'];
    }

    if (!empty($decoded['description']) && is_scalar($decoded['description'])) {
        $details['description'] = sanitize_text_field((string) $decoded['description']);
    }

    if (isset($decoded['error_code']) && is_scalar($decoded['error_code'])) {
        $details['error_code'] = (int) $decoded['error_code'];
    }

    return $details;
}

/**
 * Extract a readable Telegram API error without triggering notices.
 *
 * @param array<string, mixed>|mixed $response HTTP response.
 * @return string
 */
if (!function_exists('telegrarm_get_telegram_error_message')) {
    function telegrarm_get_telegram_error_message($response) {
        $details = telegrarm_get_telegram_response_details($response);

        return $details['description'];
    }
}

/**
 * Resolve the site name shown in admin test messages.
 *
 * @return string
 */
function telegrarm_get_test_message_site_name() {
    $site_host = parse_url((string) get_option('home', ''), PHP_URL_HOST);

    if (is_string($site_host) && '' !== $site_host) {
        return sanitize_text_field($site_host);
    }

    $site_name = get_option('blogname', '');

    if (is_string($site_name) && '' !== trim($site_name)) {
        return sanitize_text_field($site_name);
    }

    return __('this website', 'telegrarm');
}

/**
 * Resolve the settings area label used in admin test messages.
 *
 * @param string $target Settings target key.
 * @return string
 */
function telegrarm_get_test_message_target_label($target) {
    if ('new-user' === $target) {
        return __('new user', 'telegrarm');
    }

    if ('profile' === $target) {
        return __('profile update', 'telegrarm');
    }

    return __('TelegrARM', 'telegrarm');
}

/**
 * Build the admin test message body sent to Telegram.
 *
 * @param string $target Settings target key.
 * @return string
 */
function telegrarm_get_test_message_text($target) {
    return sprintf(
        __('This is a test message for %1$s from TelegrARM on %2$s', 'telegrarm'),
        telegrarm_get_test_message_target_label($target),
        telegrarm_get_test_message_site_name()
    );
}

/**
 * Send a plain Telegram message to a configured channel or chat.
 *
 * @param string $bot_api_token Telegram bot token.
 * @param string $channel_id    Telegram channel or chat ID.
 * @param string $message       Message text.
 * @return array<string, mixed>|WP_Error
 */
function telegrarm_send_telegram_text_message($bot_api_token, $channel_id, $message) {
    $bot_api_token = telegrarm_sanitize_bot_token($bot_api_token);
    $channel_id = telegrarm_sanitize_channel_id($channel_id);
    $message = is_scalar($message) ? trim(sanitize_textarea_field((string) $message)) : '';

    if ('' === $bot_api_token) {
        return new WP_Error(
            'telegrarm_missing_bot_token',
            __('Enter a Telegram bot token before sending a test message.', 'telegrarm')
        );
    }

    if ('' === $channel_id) {
        return new WP_Error(
            'telegrarm_missing_channel_id',
            __('Enter a Telegram channel or chat ID before sending a test message.', 'telegrarm')
        );
    }

    if ('' === $message) {
        return new WP_Error(
            'telegrarm_missing_test_message',
            __('The test message could not be generated.', 'telegrarm')
        );
    }

    $url = "https://api.telegram.org/bot{$bot_api_token}/sendMessage";

    return wp_remote_post(
        $url,
        array(
            'body' => array(
                'chat_id' => $channel_id,
                'text'    => $message,
            ),
        )
    );
}

/**
 * AJAX endpoint that sends an admin test message to Telegram.
 *
 * @return void
 */
function telegrarm_ajax_send_test_message() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(
            array(
                'message' => __('You do not have permission to send TelegrARM test messages.', 'telegrarm'),
            ),
            403
        );
    }

    check_ajax_referer('telegrarm_send_test_message');

    $bot_api_token = isset($_POST['bot_token']) ? telegrarm_sanitize_bot_token(wp_unslash($_POST['bot_token'])) : '';
    $channel_id = isset($_POST['channel_id']) ? telegrarm_sanitize_channel_id(wp_unslash($_POST['channel_id'])) : '';
    $target = isset($_POST['target']) ? telegrarm_sanitize_setting_text(wp_unslash($_POST['target'])) : '';

    $result = telegrarm_send_telegram_text_message(
        $bot_api_token,
        $channel_id,
        telegrarm_get_test_message_text($target)
    );

    if (is_wp_error($result)) {
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
                'message' => $result->get_error_message(),
            ),
            400
        );
    }

    $telegram_response = telegrarm_get_telegram_response_details($result);

    if (200 !== $telegram_response['status_code'] || true !== $telegram_response['ok']) {
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
                'message' => $telegram_response['description'],
            ),
            400
        );
    }

    wp_send_json_success(
        array(
            'message' => __('Test message sent successfully.', 'telegrarm'),
        )
    );
}

/**
 * Humanize a meta key into a label suggestion.
 *
 * @param string $key Meta key.
 * @return string
 */
function telegrarm_humanize_metakey_label($key) {
    $label = trim((string) $key);

    if ('' === $label) {
        return '';
    }

    $label = preg_replace('/([a-z])([A-Z])/', '$1 $2', $label);
    $label = str_replace(array('_', '-'), ' ', $label);
    $label = preg_replace('/\s+/', ' ', $label);

    return ucwords(trim((string) $label));
}

/**
 * Determine whether a discovered ARMember field is safe and useful to expose in
 * the mapping builder.
 *
 * @param string               $meta_key      Candidate meta key.
 * @param array<string, mixed> $field_options Optional ARMember field options.
 * @return bool
 */
function telegrarm_should_include_discovered_metakey($meta_key, array $field_options = array()) {
    $meta_key = trim((string) $meta_key);

    if ('' === $meta_key) {
        return false;
    }

    $excluded_meta_keys = array(
        'user_pass',
        'password',
        'confirm_password',
        'confirm_user_pass',
        'repeat_password',
        'repeat_user_pass',
        'session_tokens',
        'wp_capabilities',
        'wp_user_level',
    );

    if (in_array($meta_key, $excluded_meta_keys, true)) {
        return false;
    }

    if (0 === strpos($meta_key, '_')) {
        return false;
    }

    if (0 === strpos($meta_key, 'arm_') && !in_array($meta_key, array('arm_social_field_instagram'), true)) {
        return false;
    }

    if (!empty($field_options)) {
        $field_type = isset($field_options['type']) && is_scalar($field_options['type']) ? trim((string) $field_options['type']) : '';

        if (in_array($field_type, array('hidden', 'html', 'password', 'section', 'social_fields', 'submit'), true)) {
            return false;
        }

        if (!empty($field_options['is_hidden']) || !empty($field_options['hidden'])) {
            return false;
        }
    }

    return true;
}

/**
 * Return common ARMember and WordPress profile meta keys that are useful in TelegrARM.
 *
 * @return array<int, string>
 */
function telegrarm_get_common_armember_metakeys() {
    return array(
        'first_name',
        'last_name',
        'user_email',
        'user_login',
        'display_name',
        'nickname',
        'user_url',
        'description',
        'arm_social_field_instagram',
        'avatar',
    );
}

/**
 * Get the active ARMember runtime object if the plugin is loaded.
 *
 * @return object|null
 */
function telegrarm_get_armember_runtime_object() {
    global $ARMemberLite, $ARMember;

    if (is_object($ARMemberLite)) {
        return $ARMemberLite;
    }

    if (is_object($ARMember)) {
        return $ARMember;
    }

    return null;
}

/**
 * Resolve an ARMember table name, falling back to the current WordPress prefix.
 *
 * @param string $property      Runtime property name.
 * @param string $fallback_name Table suffix without the WordPress prefix.
 * @return string
 */
function telegrarm_get_armember_table_name($property, $fallback_name) {
    $runtime = telegrarm_get_armember_runtime_object();

    if (is_object($runtime) && isset($runtime->{$property}) && is_string($runtime->{$property}) && '' !== $runtime->{$property}) {
        return $runtime->{$property};
    }

    global $wpdb;

    return $wpdb->prefix . $fallback_name;
}

/**
 * Normalize a discovered field entry into a consistent shape.
 *
 * @param string $key    Meta key.
 * @param string $label  Suggested label.
 * @param string $source Discovery source.
 * @return array{key:string,label:string,source:string}|null
 */
function telegrarm_build_discovered_metakey_item($key, $label, $source) {
    $key = is_scalar($key) ? trim((string) $key) : '';
    $label = is_scalar($label) ? trim((string) $label) : '';
    $source = is_scalar($source) ? trim((string) $source) : 'discovered';

    if ('' === $key) {
        return null;
    }

    if ('' === $label) {
        $label = telegrarm_humanize_metakey_label($key);
    }

    return array(
        'key'    => $key,
        'label'  => $label,
        'source' => $source,
    );
}

/**
 * Merge a discovered field item into the result set, preserving the more
 * authoritative source when the same key appears multiple times.
 *
 * @param array<string, array{key:string,label:string,source:string}> $items Collected items keyed by meta key.
 * @param array{key:string,label:string,source:string}                $item  Item to merge.
 * @return void
 */
function telegrarm_merge_discovered_metakey_item(array &$items, array $item) {
    if (empty($item['key'])) {
        return;
    }

    $source_priority = array(
        'preset'     => 1,
        'form_field' => 2,
        'common'     => 3,
        'usermeta'   => 4,
        'fallback'   => 5,
        'discovered' => 6,
    );

    $key = $item['key'];

    if (!isset($items[$key])) {
        $items[$key] = $item;

        return;
    }

    $existing_source = isset($source_priority[$items[$key]['source']]) ? $source_priority[$items[$key]['source']] : 99;
    $new_source = isset($source_priority[$item['source']]) ? $source_priority[$item['source']] : 99;

    if ($new_source < $existing_source) {
        $items[$key] = $item;

        return;
    }

    if ('' === $items[$key]['label'] && '' !== $item['label']) {
        $items[$key]['label'] = $item['label'];
    }
}

/**
 * Extract ARMember preset field definitions from the arm_preset_form_fields option.
 *
 * @return array<int, array{key:string,label:string,source:string}>
 */
function telegrarm_get_armember_preset_field_items() {
    $preset_form_fields = maybe_unserialize(get_option('arm_preset_form_fields', ''));

    if (!is_array($preset_form_fields) || empty($preset_form_fields)) {
        return array();
    }

    $items = array();

    foreach ($preset_form_fields as $group_name => $group_fields) {
        if (!is_array($group_fields)) {
            continue;
        }

        foreach ($group_fields as $field_key => $field_value) {
            if (!is_array($field_value)) {
                continue;
            }

            $meta_key = isset($field_value['meta_key']) && is_scalar($field_value['meta_key']) ? trim((string) $field_value['meta_key']) : '';
            $label    = isset($field_value['label']) && is_scalar($field_value['label']) ? trim((string) $field_value['label']) : '';

            if ('' === $meta_key && is_scalar($field_key)) {
                $meta_key = trim((string) $field_key);
            }

            if ('' === $meta_key) {
                continue;
            }

            if (!telegrarm_should_include_discovered_metakey($meta_key, $field_value)) {
                continue;
            }

            $source = 'default' === $group_name ? 'preset' : 'preset';
            $item   = telegrarm_build_discovered_metakey_item($meta_key, $label, $source);

            if (null !== $item) {
                $items[] = $item;
            }
        }
    }

    return $items;
}

/**
 * Extract ARMember field definitions from the form field table.
 *
 * @return array<int, array{key:string,label:string,source:string}>
 */
function telegrarm_get_armember_form_field_items() {
    global $wpdb;

    $table_name = telegrarm_get_armember_table_name('tbl_arm_form_field', 'arm_form_field');
    $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table_name)));

    if ($table_name !== $table_exists) {
        return array();
    }

    $rows = $wpdb->get_results(
        "SELECT arm_form_field_slug, arm_form_field_option, arm_form_field_status
         FROM `{$table_name}`
         WHERE arm_form_field_slug <> ''
           AND arm_form_field_status != 2
         ORDER BY arm_form_field_id ASC",
        ARRAY_A
    );

    if (!is_array($rows) || empty($rows)) {
        return array();
    }

    $items = array();

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $field_options = isset($row['arm_form_field_option']) ? maybe_unserialize($row['arm_form_field_option']) : array();
        $field_options = is_array($field_options) ? $field_options : array();

        $meta_key = isset($field_options['meta_key']) && is_scalar($field_options['meta_key']) ? trim((string) $field_options['meta_key']) : '';
        $label    = isset($field_options['label']) && is_scalar($field_options['label']) ? trim((string) $field_options['label']) : '';

        if ('' === $meta_key && isset($row['arm_form_field_slug']) && is_scalar($row['arm_form_field_slug'])) {
            $meta_key = trim((string) $row['arm_form_field_slug']);
        }

        if ('' === $meta_key) {
            continue;
        }

        if (!telegrarm_should_include_discovered_metakey($meta_key, $field_options)) {
            continue;
        }

        $source = (!empty($field_options['_builtin']) || !empty($field_options['default_field'])) ? 'common' : 'form_field';
        $item   = telegrarm_build_discovered_metakey_item($meta_key, $label, $source);

        if (null !== $item) {
            $items[] = $item;
        }
    }

    return $items;
}

/**
 * Collect likely ARMember user meta keys by scanning the WordPress usermeta table.
 *
 * @return array<int, array{key:string,label:string,source:string}>
 */
function telegrarm_get_armember_usermeta_items() {
    global $wpdb;

    $technical_user_meta_keys = array(
        'admin_color',
        'comment_shortcuts',
        'dismissed_wp_pointers',
        'locale',
        'manages',
        'rich_editing',
        'screen_layout_dashboard',
        'screen_layout_profile',
        'session_tokens',
        'show_admin_bar_front',
        'syntax_highlighting',
        'user_level',
        'user-settings',
        'user-settings-time',
        'wp_capabilities',
        'wp_user_level',
    );

    $user_meta_keys = $wpdb->get_col(
        "SELECT DISTINCT meta_key
         FROM {$wpdb->usermeta}
         WHERE meta_key <> ''
           AND meta_key NOT LIKE '\\_%'
         ORDER BY meta_key ASC"
    );

    if (!is_array($user_meta_keys) || empty($user_meta_keys)) {
        return array();
    }

    $items = array();

    foreach ($user_meta_keys as $meta_key) {
        if (!is_scalar($meta_key)) {
            continue;
        }

        $meta_key = trim((string) $meta_key);

        if ('' === $meta_key || in_array($meta_key, $technical_user_meta_keys, true)) {
            continue;
        }

        if (!telegrarm_should_include_discovered_metakey($meta_key)) {
            continue;
        }

        $item = telegrarm_build_discovered_metakey_item($meta_key, telegrarm_humanize_metakey_label($meta_key), 'usermeta');

        if (null !== $item) {
            $items[] = $item;
        }
    }

    return $items;
}

/**
 * Discover likely ARMember meta keys from the current site.
 *
 * Discovery order:
 * 1. ARMember preset fields from `arm_preset_form_fields`
 * 2. ARMember form field registry from `tbl_arm_form_field`
 * 3. Fallback scan of public user meta keys
 *
 * @param bool $force_refresh Whether to bypass the transient cache.
 * @return array<int, array{key:string,label:string,source:string}>
 */
function telegrarm_get_discovered_armember_metakeys($force_refresh = false) {
    $cache_key = 'telegrarm_armember_metakeys';

    if (!$force_refresh) {
        $cached = get_transient($cache_key);

        if (is_array($cached)) {
            return $cached;
        }
    }

    $items = array();

    $registry_items = array();

    foreach (telegrarm_get_armember_preset_field_items() as $item) {
        telegrarm_merge_discovered_metakey_item($registry_items, $item);
    }

    foreach (telegrarm_get_armember_form_field_items() as $item) {
        telegrarm_merge_discovered_metakey_item($registry_items, $item);
    }

    $items = $registry_items;

    foreach (telegrarm_get_common_armember_metakeys() as $key) {
        $item = telegrarm_build_discovered_metakey_item($key, telegrarm_humanize_metakey_label($key), 'common');

        if (null !== $item) {
            telegrarm_merge_discovered_metakey_item($items, $item);
        }
    }

    if (empty($registry_items)) {
        foreach (telegrarm_get_armember_usermeta_items() as $item) {
            telegrarm_merge_discovered_metakey_item($items, $item);
        }
    }

    $items = array_values($items);

    usort(
        $items,
        static function ($left, $right) {
            return strcasecmp($left['key'], $right['key']);
        }
    );

    set_transient($cache_key, $items, 10 * MINUTE_IN_SECONDS);

    return $items;
}

/**
 * AJAX endpoint that returns discovered ARMember meta keys.
 *
 * @return void
 */
function telegrarm_ajax_discover_arm_metakeys() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(
            array(
                'message' => __('You do not have permission to discover ARMember fields.', 'telegrarm'),
            ),
            403
        );
    }

    check_ajax_referer('telegrarm_discover_arm_metakeys');

    $force_refresh = !empty($_POST['refresh']);
    $saved_mapping = telegrarm_get_arm_mapping();
    $items = array();

    foreach (telegrarm_get_discovered_armember_metakeys($force_refresh) as $item) {
        if (empty($item['key'])) {
            continue;
        }

        $is_selected = isset($saved_mapping[$item['key']]);

        $items[] = array(
            'key'        => $item['key'],
            'label'      => isset($saved_mapping[$item['key']]) && is_scalar($saved_mapping[$item['key']])
                ? (string) $saved_mapping[$item['key']]
                : $item['label'],
            'source'     => $item['source'],
            'isSelected' => $is_selected,
        );
    }

    wp_send_json_success(
        array(
            'count' => count($items),
            'items' => $items,
        )
    );
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
    $debug_logging_enabled = (bool) get_option('telegrarm_debug_logging', false);
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

                        <div class="telegrarm-switch-row telegrarm-switch-row-spaced">
                            <div>
                                <h3><?php esc_html_e('Debug logging', 'telegrarm'); ?></h3>
                                <p><?php esc_html_e('Write sanitized failure traces to the PHP error log when Telegram requests fail or handlers skip an event. Token values are never logged.', 'telegrarm'); ?></p>
                            </div>
                            <label class="telegrarm-toggle">
                                <input
                                    type="checkbox"
                                    name="telegrarm_debug_logging"
                                    value="1"
                                    <?php checked(true, $debug_logging_enabled, true); ?>
                                />
                                <span><?php esc_html_e('Enable debug logging', 'telegrarm'); ?></span>
                            </label>
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

                        <div class="telegrarm-test-message">
                            <button
                                type="button"
                                class="button button-secondary telegrarm-test-message-button"
                                data-channel-input="telegram_channel_id_newuser"
                                data-target="new-user"
                            >
                                <?php esc_html_e('Send a test message', 'telegrarm'); ?>
                            </button>
                            <p class="description"><?php esc_html_e('A test message will be sent to this channel to make sure it works.', 'telegrarm'); ?></p>
                            <div class="telegrarm-test-message-status" aria-live="polite"></div>
                        </div>
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

                        <div class="telegrarm-test-message">
                            <button
                                type="button"
                                class="button button-secondary telegrarm-test-message-button"
                                data-channel-input="telegram_channel_id_updates"
                                data-target="profile"
                            >
                                <?php esc_html_e('Send a test message', 'telegrarm'); ?>
                            </button>
                            <p class="description"><?php esc_html_e('A test message will be sent to this channel to make sure it works.', 'telegrarm'); ?></p>
                            <div class="telegrarm-test-message-status" aria-live="polite"></div>
                        </div>
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

                        <div class="telegrarm-card telegrarm-card-accent telegrarm-mapping-builder">
                            <div class="telegrarm-switch-row">
                                <div>
                                    <h3><?php esc_html_e('Discover ARMember fields', 'telegrarm'); ?></h3>
                                    <p><?php esc_html_e('Pull ARMember field keys from the plugin registry first, then fall back to stored user meta only if those registry sources are unavailable.', 'telegrarm'); ?></p>
                                </div>
                                <button type="button" class="button button-primary" id="telegrarm-discover-metakeys">
                                    <?php esc_html_e('Discover fields', 'telegrarm'); ?>
                                </button>
                            </div>

                            <p class="description"><?php esc_html_e('Use the results below to select the keys you want, edit their labels, and generate the JSON back into the textarea above.', 'telegrarm'); ?></p>
                            <p class="description"><?php esc_html_e('Need a specific message order? Drag the Move control in the results table to rearrange fields before building the JSON.', 'telegrarm'); ?></p>

                            <div class="telegrarm-mapping-tools">
                                <button type="button" class="button" id="telegrarm-select-all-metakeys"><?php esc_html_e('Select all', 'telegrarm'); ?></button>
                                <button type="button" class="button" id="telegrarm-select-none-metakeys"><?php esc_html_e('Select none', 'telegrarm'); ?></button>
                                <button type="button" class="button button-secondary" id="telegrarm-build-mapping"><?php esc_html_e('Build JSON', 'telegrarm'); ?></button>
                            </div>

                            <div id="telegrarm-metakeys-status" class="telegrarm-discovery-status" aria-live="polite"></div>
                            <div id="telegrarm-metakeys-results" class="telegrarm-metakeys-results" hidden></div>
                        </div>

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
                            <?php esc_html_e('After entering the bot token and channel ID, use the Send a test message button in the New user or Profile updates tab to confirm the bot can receive requests and post into the specified channel.', 'telegrarm'); ?>
                        </p>
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

            .telegrarm-switch-row-spaced {
                margin-top: 10px;
            }

            .telegrarm-mapping-builder {
                margin-top: 18px;
            }

            .telegrarm-test-message {
                display: grid;
                gap: 8px;
                margin-top: -6px;
            }

            .telegrarm-test-message-status {
                min-height: 20px;
                color: #475569;
            }

            .telegrarm-mapping-tools {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                margin: 14px 0 12px;
            }

            .telegrarm-discovery-status {
                min-height: 22px;
                margin: 0 0 12px;
                color: #475569;
            }

            .telegrarm-metakeys-results {
                margin-top: 8px;
            }

            .telegrarm-metakeys-table {
                width: 100%;
                border-collapse: collapse;
            }

            .telegrarm-metakeys-table th,
            .telegrarm-metakeys-table td {
                padding: 10px 8px;
                border-top: 1px solid #dcdcde;
                vertical-align: top;
                text-align: left;
            }

            .telegrarm-metakeys-table thead th {
                border-top: 0;
                color: #1d2327;
                font-weight: 600;
            }

            .telegrarm-metakeys-table input[type="text"] {
                max-width: none;
            }

            .telegrarm-metakey-source {
                display: inline-flex;
                align-items: center;
                padding: 2px 8px;
                border-radius: 999px;
                background: #f6f7f7;
                color: #475569;
                font-size: 12px;
                font-weight: 600;
                line-height: 1.6;
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

            .telegrarm-metakeys-table tr.is-dragging {
                opacity: 0.55;
            }

            .telegrarm-metakeys-table tr.is-drop-target td {
                border-top: 2px solid #2271b1;
            }

            .telegrarm-metakeys-table td.telegrarm-order-cell,
            .telegrarm-metakeys-table th.telegrarm-order-cell {
                width: 76px;
                text-align: center;
            }

            .telegrarm-drag-handle {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 54px;
                min-height: 32px;
                padding: 0 10px;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                background: #f6f7f7;
                color: #1d2327;
                cursor: grab;
                font-size: 12px;
                font-weight: 600;
                line-height: 1;
                user-select: none;
            }

            .telegrarm-drag-handle:focus {
                outline: 2px solid #2271b1;
                outline-offset: 1px;
            }

            .telegrarm-drag-handle:active {
                cursor: grabbing;
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
            const mappingTextarea = document.getElementById('telegrarm_arm_mapping');
            const discoverButton = document.getElementById('telegrarm-discover-metakeys');
            const selectAllButton = document.getElementById('telegrarm-select-all-metakeys');
            const selectNoneButton = document.getElementById('telegrarm-select-none-metakeys');
            const buildButton = document.getElementById('telegrarm-build-mapping');
            const botTokenInput = document.getElementById('telegram_bot_api_token');
            const statusNode = document.getElementById('telegrarm-metakeys-status');
            const resultsNode = document.getElementById('telegrarm-metakeys-results');
            const testMessageButtons = document.querySelectorAll('.telegrarm-test-message-button');
            const ajaxUrl = window.ajaxurl || <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
            const ajaxNonce = <?php echo wp_json_encode(wp_create_nonce('telegrarm_discover_arm_metakeys')); ?>;
            const testMessageNonce = <?php echo wp_json_encode(wp_create_nonce('telegrarm_send_test_message')); ?>;
            const existingMapping = <?php echo wp_json_encode(telegrarm_get_arm_mapping(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            const i18n = <?php echo wp_json_encode(
                array(
                    'builtJsonSingular' => __('Built mapping JSON from %d field.', 'telegrarm'),
                    'builtJsonPlural' => __('Built mapping JSON from %d fields.', 'telegrarm'),
                    'currentMappingCountSingular' => __('The current mapping already contains %d field. Discover more fields to add or refine them.', 'telegrarm'),
                    'currentMappingCountPlural' => __('The current mapping already contains %d fields. Discover more fields to add or refine them.', 'telegrarm'),
                    'detected' => __('Detected', 'telegrarm'),
                    'discovering' => __('Discovering ARMember fields...', 'telegrarm'),
                    'discoveredCountSingular' => __('Discovered %d candidate field.', 'telegrarm'),
                    'discoveredCountPlural' => __('Discovered %d candidate fields.', 'telegrarm'),
                    'formField' => __('Form field', 'telegrarm'),
                    'metaKey' => __('Meta key', 'telegrarm'),
                    'noCandidates' => __('No candidate ARMember fields were found on this site yet.', 'telegrarm'),
                    'preset' => __('Preset', 'telegrarm'),
                    'requestFailed' => __('Request failed with status %d.', 'telegrarm'),
                    'reorder' => __('Order', 'telegrarm'),
                    'reorderHint' => __('Drag the Move control to rearrange the selected field order before building the JSON.', 'telegrarm'),
                    'reorderMove' => __('Move', 'telegrarm'),
                    'selectAtLeastOne' => __('Select at least one field before building the JSON.', 'telegrarm'),
                    'sendingTestMessage' => __('Sending test message...', 'telegrarm'),
                    'source' => __('Source', 'telegrarm'),
                    'testMessageMissingBotToken' => __('Enter a bot token before sending a test message.', 'telegrarm'),
                    'testMessageMissingChannel' => __('Enter a channel or chat ID before sending a test message.', 'telegrarm'),
                    'unknownTestMessageError' => __('Unable to send the test message.', 'telegrarm'),
                    'unexpectedResponse' => __('Unexpected response from the discovery endpoint.', 'telegrarm'),
                    'unknownDiscoveryError' => __('Unable to discover ARMember fields.', 'telegrarm'),
                    'use' => __('Use', 'telegrarm'),
                    'usermeta' => __('Usermeta', 'telegrarm'),
                    'label' => __('Label', 'telegrarm'),
                    'builtIn' => __('Built-in', 'telegrarm'),
                ),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ); ?>;

            function formatCountMessage(singularTemplate, pluralTemplate, count) {
                const template = count === 1 ? singularTemplate : pluralTemplate;
                return String(template || '').replace('%d', String(count));
            }

            function formatMessage(template, value) {
                return String(template || '').replace('%d', String(value));
            }

            function humanizeKey(key) {
                return String(key || '')
                    .replace(/([a-z])([A-Z])/g, '$1 $2')
                    .replace(/[_-]+/g, ' ')
                    .replace(/\s+/g, ' ')
                    .trim()
                    .replace(/\b\w/g, function (match) {
                        return match.toUpperCase();
                    });
            }

            function setStatus(message, isError) {
                if (!statusNode) {
                    return;
                }

                statusNode.textContent = message || '';
                statusNode.style.color = isError ? '#b32d2e' : '';
            }

            function setScopedStatus(node, message, isError) {
                if (!node) {
                    return;
                }

                node.textContent = message || '';
                node.style.color = isError ? '#b32d2e' : '';
            }

            function enableRowReordering(tbody) {
                if (!tbody) {
                    return;
                }

                let draggedRow = null;
                let dropTargetRow = null;

                function clearDropTarget() {
                    if (dropTargetRow) {
                        dropTargetRow.classList.remove('is-drop-target');
                        dropTargetRow = null;
                    }
                }

                tbody.querySelectorAll('tr').forEach(function (row) {
                    const handle = row.querySelector('.telegrarm-drag-handle');

                    if (!handle) {
                        return;
                    }

                    handle.draggable = true;

                    handle.addEventListener('dragstart', function (event) {
                        draggedRow = row;
                        row.classList.add('is-dragging');

                        if (event.dataTransfer) {
                            event.dataTransfer.effectAllowed = 'move';
                            event.dataTransfer.setData('text/plain', row.dataset.key || '');
                        }
                    });

                    handle.addEventListener('dragend', function () {
                        row.classList.remove('is-dragging');
                        draggedRow = null;
                        clearDropTarget();
                    });

                    row.addEventListener('dragover', function (event) {
                        if (!draggedRow || draggedRow === row) {
                            return;
                        }

                        event.preventDefault();

                        if (dropTargetRow && dropTargetRow !== row) {
                            dropTargetRow.classList.remove('is-drop-target');
                        }

                        dropTargetRow = row;
                        dropTargetRow.classList.add('is-drop-target');
                    });

                    row.addEventListener('drop', function (event) {
                        const bounds = row.getBoundingClientRect();
                        const shouldInsertBefore = event.clientY < bounds.top + (bounds.height / 2);

                        event.preventDefault();

                        if (!draggedRow || draggedRow === row) {
                            clearDropTarget();
                            return;
                        }

                        if (shouldInsertBefore) {
                            tbody.insertBefore(draggedRow, row);
                        } else {
                            tbody.insertBefore(draggedRow, row.nextSibling);
                        }

                        clearDropTarget();
                    });
                });
            }

            function renderMetakeys(items) {
                if (!resultsNode) {
                    return;
                }

                resultsNode.innerHTML = '';
                resultsNode.hidden = false;

                if (!items || !items.length) {
                    resultsNode.innerHTML = '<p>' + i18n.noCandidates + '</p>';
                    return;
                }

                const table = document.createElement('table');
                table.className = 'telegrarm-metakeys-table';

                const reorderHint = document.createElement('p');
                reorderHint.className = 'description';
                reorderHint.textContent = i18n.reorderHint;
                resultsNode.appendChild(reorderHint);

                const thead = document.createElement('thead');
                const headRow = document.createElement('tr');
                [i18n.reorder, i18n.use, i18n.metaKey, i18n.label, i18n.source].forEach(function (title, index) {
                    const th = document.createElement('th');
                    if (index === 0) {
                        th.className = 'telegrarm-order-cell';
                    }
                    th.textContent = title;
                    headRow.appendChild(th);
                });
                thead.appendChild(headRow);
                table.appendChild(thead);

                const tbody = document.createElement('tbody');

                items.forEach(function (item) {
                    const row = document.createElement('tr');
                    row.dataset.key = item.key || '';

                    const orderCell = document.createElement('td');
                    orderCell.className = 'telegrarm-order-cell';
                    const dragHandle = document.createElement('span');
                    dragHandle.className = 'telegrarm-drag-handle';
                    dragHandle.textContent = i18n.reorderMove;
                    dragHandle.setAttribute('role', 'button');
                    dragHandle.setAttribute('tabindex', '0');
                    dragHandle.setAttribute('aria-label', (i18n.reorderMove || 'Move') + ': ' + (item.key || ''));
                    orderCell.appendChild(dragHandle);
                    row.appendChild(orderCell);

                    const checkCell = document.createElement('td');
                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.checked = !!item.isSelected;
                    checkbox.setAttribute('aria-label', item.key || '');
                    checkCell.appendChild(checkbox);
                    row.appendChild(checkCell);

                    const keyCell = document.createElement('td');
                    const keyCode = document.createElement('code');
                    keyCode.textContent = item.key || '';
                    keyCell.appendChild(keyCode);
                    row.appendChild(keyCell);

                    const labelCell = document.createElement('td');
                    const labelInput = document.createElement('input');
                    labelInput.type = 'text';
                    labelInput.className = 'regular-text telegrarm-input';
                    labelInput.value = item.label || humanizeKey(item.key);
                    labelCell.appendChild(labelInput);
                    row.appendChild(labelCell);

                    const sourceCell = document.createElement('td');
                    const sourceBadge = document.createElement('span');
                    sourceBadge.className = 'telegrarm-metakey-source';
                    if (item.source === 'form_field') {
                        sourceBadge.textContent = i18n.formField;
                    } else if (item.source === 'preset') {
                        sourceBadge.textContent = i18n.preset;
                    } else if (item.source === 'common') {
                        sourceBadge.textContent = i18n.builtIn;
                    } else if (item.source === 'usermeta') {
                        sourceBadge.textContent = i18n.usermeta;
                    } else {
                        sourceBadge.textContent = i18n.detected;
                    }
                    sourceCell.appendChild(sourceBadge);
                    row.appendChild(sourceCell);

                    tbody.appendChild(row);
                });

                table.appendChild(tbody);
                resultsNode.appendChild(table);
                enableRowReordering(tbody);
            }

            function collectSelectedMapping() {
                const mapping = {};

                if (!resultsNode) {
                    return mapping;
                }

                resultsNode.querySelectorAll('tbody tr').forEach(function (row) {
                    const checkbox = row.querySelector('input[type="checkbox"]');

                    if (!checkbox || !checkbox.checked) {
                        return;
                    }

                    const key = row.dataset.key || '';
                    const labelInput = row.querySelector('input[type="text"]');
                    const label = labelInput && labelInput.value ? labelInput.value.trim() : humanizeKey(key);

                    if (key) {
                        mapping[key] = label;
                    }
                });

                return mapping;
            }

            function setAllCheckboxes(checked) {
                if (!resultsNode) {
                    return;
                }

                resultsNode.querySelectorAll('tbody input[type="checkbox"]').forEach(function (checkbox) {
                    checkbox.checked = checked;
                });
            }

            function buildMappingJson() {
                if (!mappingTextarea) {
                    return;
                }

                const mapping = collectSelectedMapping();
                const keys = Object.keys(mapping);

                if (!keys.length) {
                    setStatus(i18n.selectAtLeastOne, true);
                    return;
                }

                mappingTextarea.value = JSON.stringify(mapping, null, 2);
                setStatus(formatCountMessage(i18n.builtJsonSingular, i18n.builtJsonPlural, keys.length));
                mappingTextarea.focus();
            }

            if (discoverButton && resultsNode) {
                discoverButton.addEventListener('click', function () {
                    setStatus(i18n.discovering);
                    discoverButton.disabled = true;

                    const body = new URLSearchParams();
                    body.set('action', 'telegrarm_discover_arm_metakeys');
                    body.set('_ajax_nonce', ajaxNonce);
                    body.set('refresh', '1');

                    fetch(ajaxUrl, {
                        credentials: 'same-origin',
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        },
                        body: body.toString(),
                    })
                        .then(function (response) {
                            return response.json()
                                .catch(function () {
                                    throw new Error(formatMessage(i18n.requestFailed, response.status));
                                })
                                .then(function (payload) {
                                    if (!response.ok) {
                                        const message = payload && payload.data && payload.data.message ? payload.data.message : formatMessage(i18n.requestFailed, response.status);
                                        throw new Error(message);
                                    }

                                    return payload;
                                });
                        })
                        .then(function (payload) {
                            if (!payload || !payload.success || !payload.data || !Array.isArray(payload.data.items)) {
                                throw new Error(i18n.unexpectedResponse);
                            }

                            renderMetakeys(payload.data.items);
                            setStatus(formatCountMessage(i18n.discoveredCountSingular, i18n.discoveredCountPlural, payload.data.count));
                        })
                        .catch(function (error) {
                            resultsNode.hidden = true;
                            resultsNode.innerHTML = '';
                            setStatus(error.message || i18n.unknownDiscoveryError, true);
                        })
                        .finally(function () {
                            discoverButton.disabled = false;
                        });
                });
            }

            if (selectAllButton) {
                selectAllButton.addEventListener('click', function () {
                    setAllCheckboxes(true);
                });
            }

            if (selectNoneButton) {
                selectNoneButton.addEventListener('click', function () {
                    setAllCheckboxes(false);
                });
            }

            if (buildButton) {
                buildButton.addEventListener('click', function () {
                    buildMappingJson();
                });
            }

            if (resultsNode) {
                resultsNode.addEventListener('input', function () {
                    if (statusNode && statusNode.textContent) {
                        setStatus('');
                    }
                });
            }

            if (resultsNode && Object.keys(existingMapping).length > 0) {
                setStatus(formatCountMessage(i18n.currentMappingCountSingular, i18n.currentMappingCountPlural, Object.keys(existingMapping).length));
            }

            if (testMessageButtons.length) {
                testMessageButtons.forEach(function (button) {
                    const testMessageContainer = button.closest('.telegrarm-test-message');
                    const statusTarget = testMessageContainer ? testMessageContainer.querySelector('.telegrarm-test-message-status') : null;
                    const channelInput = document.getElementById(button.getAttribute('data-channel-input') || '');

                    if (channelInput) {
                        channelInput.addEventListener('input', function () {
                            setScopedStatus(statusTarget, '', false);
                        });
                    }

                    button.addEventListener('click', function () {
                        const botToken = botTokenInput && botTokenInput.value ? botTokenInput.value.trim() : '';
                        const channelId = channelInput && channelInput.value ? channelInput.value.trim() : '';
                        const previousLabel = button.textContent;

                        if (!botToken) {
                            setScopedStatus(statusTarget, i18n.testMessageMissingBotToken, true);

                            if (botTokenInput) {
                                botTokenInput.focus();
                            }

                            return;
                        }

                        if (!channelId) {
                            setScopedStatus(statusTarget, i18n.testMessageMissingChannel, true);

                            if (channelInput) {
                                channelInput.focus();
                            }

                            return;
                        }

                        setScopedStatus(statusTarget, i18n.sendingTestMessage, false);
                        button.disabled = true;
                        button.textContent = i18n.sendingTestMessage;

                        const body = new URLSearchParams();
                        body.set('action', 'telegrarm_send_test_message');
                        body.set('_ajax_nonce', testMessageNonce);
                        body.set('bot_token', botToken);
                        body.set('channel_id', channelId);
                        body.set('target', button.getAttribute('data-target') || '');

                        fetch(ajaxUrl, {
                            credentials: 'same-origin',
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                            },
                            body: body.toString(),
                        })
                            .then(function (response) {
                                return response.json()
                                    .catch(function () {
                                        throw new Error(formatMessage(i18n.requestFailed, response.status));
                                    })
                                    .then(function (payload) {
                                        if (!response.ok || !payload || !payload.success) {
                                            const message = payload && payload.data && payload.data.message ? payload.data.message : formatMessage(i18n.requestFailed, response.status);
                                            throw new Error(message);
                                        }

                                        return payload;
                                    });
                            })
                            .then(function (payload) {
                                const message = payload && payload.data && payload.data.message ? payload.data.message : i18n.unknownTestMessageError;
                                setScopedStatus(statusTarget, message, false);
                            })
                            .catch(function (error) {
                                setScopedStatus(statusTarget, error.message || i18n.unknownTestMessageError, true);
                            })
                            .finally(function () {
                                button.disabled = false;
                                button.textContent = previousLabel;
                            });
                    });
                });
            }

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
