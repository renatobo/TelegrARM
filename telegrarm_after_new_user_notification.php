<?php
/**
 * Notify on new user registration.
 */

/**
 * Escape plain text for Telegram HTML parse mode.
 *
 * @param mixed $value Raw value.
 * @return string
 */
if (!function_exists('telegrarm_escape_telegram_html_text')) {
    function telegrarm_escape_telegram_html_text($value) {
        if (!is_scalar($value)) {
            return '';
        }

        return esc_html((string) $value);
    }
}

/**
 * Build a Telegram-safe profile line for special fields.
 *
 * @param string $key   Meta key.
 * @param mixed  $value Meta value.
 * @param array  $map   Allowed field mapping.
 * @return string
 */
function telegrarm_build_registration_profile_line($key, $value, $map) {
    if (!is_scalar($value)) {
        return '';
    }

    $value_string = trim((string) $value);

    if ('' === $value_string) {
        return '';
    }

    if ('arm_social_field_instagram' === $key) {
        $username = preg_replace('/[^A-Za-z0-9._]/', '', $value_string);

        if (is_string($username) && '' !== $username) {
            $username_html = telegrarm_escape_telegram_html_text($username);

            return "IG: <a href=\"https://instagram.com/{$username}\">@{$username_html}</a>\n";
        }

        return "IG: " . telegrarm_escape_telegram_html_text($value_string) . "\n";
    }

    if ('avatar' === $key) {
        $avatar_url = $value_string;

        if (!preg_match('#^https?://#i', $avatar_url)) {
            $avatar_url = 'https://' . ltrim($avatar_url, '/');
        }

        $validated_url = wp_http_validate_url($avatar_url);

        if ($validated_url) {
            $display_url = telegrarm_escape_telegram_html_text($validated_url);

            return 'avatar: <a href="' . esc_url($validated_url) . '">' . $display_url . "</a>\n";
        }

        return 'avatar: ' . telegrarm_escape_telegram_html_text($value_string) . "\n";
    }

    if (isset($map[$key])) {
        $label = telegrarm_escape_telegram_html_text($map[$key]);
        $safe_value = telegrarm_escape_telegram_html_text($value_string);

        return "{$label}: {$safe_value}\n";
    }

    return '';
}

/**
 * Extract a readable Telegram API error without triggering notices.
 *
 * @param array<string, mixed>|mixed $response HTTP response.
 * @return string
 */
if (!function_exists('telegrarm_get_telegram_error_message')) {
    function telegrarm_get_telegram_error_message($response) {
        if (!is_array($response)) {
            return __('Unknown Telegram API error.', 'telegrarm');
        }

        $body = wp_remote_retrieve_body($response);

        if (!is_string($body) || '' === $body) {
            return __('Unknown Telegram API error.', 'telegrarm');
        }

        $decoded = json_decode($body, true);

        if (is_array($decoded) && !empty($decoded['description']) && is_scalar($decoded['description'])) {
            return sanitize_text_field((string) $decoded['description']);
        }

        return __('Unknown Telegram API error.', 'telegrarm');
    }
}

/**
 * Send the registration notification.
 *
 * @param object $user User object from ARMember.
 * @return void
 */
function telegrarm_after_new_user_notification($user) {
    if (!is_object($user) || !isset($user->ID, $user->user_login)) {
        return;
    }

    $bot_api_token    = (string) get_option('telegram_bot_api_token', '');
    $channel_id       = (string) get_option('telegram_channel_id_newuser', '');
    $arm_allowed_keys = get_option('telegrarm_arm_mapping', array());

    if ('' === $bot_api_token || '' === $channel_id || !is_array($arm_allowed_keys)) {
        return;
    }

    $meta = get_user_meta($user->ID);
    $meta = array_filter(
        array_map(
            static function ($item) {
                return is_array($item) && isset($item[0]) ? $item[0] : '';
            },
            $meta
        ),
        static function ($item) {
            return '' !== $item && null !== $item;
        }
    );

    $user_login = maybe_unserialize($user->user_login);
    $text       = sprintf(
        'New user registered: %s [%d]',
        is_scalar($user_login) ? (string) $user_login : '',
        (int) $user->ID
    );
    $profile    = '';
    $url        = "https://api.telegram.org/bot{$bot_api_token}/sendMessage";

    foreach ($meta as $key => $value) {
        $profile .= telegrarm_build_registration_profile_line($key, $value, $arm_allowed_keys);
    }

    $post_data = array(
        'chat_id'    => $channel_id,
        'parse_mode' => 'HTML',
        'text'       => '<b>' . telegrarm_escape_telegram_html_text($text) . "</b>\n" . $profile,
    );

    $result = wp_remote_post($url, array('body' => $post_data));

    if (is_wp_error($result)) {
        return;
    }

    if (200 !== wp_remote_retrieve_response_code($result)) {
        return;
    }

    $send_contact = get_option('telegram_send_contact_during_registration', false);

    if (!$send_contact) {
        return;
    }

    $phone_field_name = (string) get_option('telegram_phone_field_name', 'text_t0cls');
    $default_code     = (string) get_option('telegram_international_code_if_missing', '+1');
    $phone            = isset($meta[$phone_field_name]) && is_scalar($meta[$phone_field_name]) ? (string) $meta[$phone_field_name] : '';

    if ('' !== $phone && 0 !== strpos($phone, '+')) {
        $phone = $default_code . $phone;
    }

    if ('' === $phone) {
        return;
    }

    $url = "https://api.telegram.org/bot{$bot_api_token}/sendContact";

    $post_data = array(
        'chat_id'      => $channel_id,
        'phone_number' => $phone,
        'first_name'   => isset($meta['first_name']) && is_scalar($meta['first_name']) ? (string) $meta['first_name'] : '',
        'last_name'    => isset($meta['last_name']) && is_scalar($meta['last_name']) ? (string) $meta['last_name'] : '',
    );

    wp_remote_post($url, array('body' => $post_data));
}
