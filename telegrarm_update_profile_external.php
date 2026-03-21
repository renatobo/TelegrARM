<?php
/**
 * TelegrARM - Telegram Bot Profile Update Notification
 *
 * Sends Telegram notifications when a user profile is updated.
 *
 * @author  Renato Bonomini <https://github.com/renatobo>
 * @link    https://github.com/renatobo/TelegrARM
 * @license GPLv2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('telegrarm_escape_telegram_html_text')) {
    /**
     * Escape plain text for Telegram HTML parse mode.
     *
     * @param mixed $value Raw value.
     * @return string
     */
    function telegrarm_escape_telegram_html_text($value) {
        if (!is_scalar($value)) {
            return '';
        }

        return esc_html((string) $value);
    }
}

if (!function_exists('telegrarm_get_telegram_error_message')) {
    /**
     * Extract a readable Telegram API error without triggering notices.
     *
     * @param array<string, mixed>|mixed $response HTTP response.
     * @return string
     */
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
 * Build a Telegram-safe profile update line.
 *
 * @param string $key   Form field key.
 * @param mixed  $value Form field value.
 * @param array  $map   Allowed field mapping.
 * @return string
 */
function telegrarm_build_profile_update_line($key, $value, $map) {
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

        return 'IG: ' . telegrarm_escape_telegram_html_text($value_string) . "\n";
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
        $label      = telegrarm_escape_telegram_html_text($map[$key]);
        $safe_value = telegrarm_escape_telegram_html_text($value_string);

        return "{$label}: {$safe_value}\n";
    }

    return '';
}

/**
 * Send the profile update notification.
 *
 * @param int   $user_id   Updated user ID.
 * @param array $form_data Submitted profile data.
 * @return void
 */
function telegrarm_profile_update($user_id, $form_data) {
    telegrarm_log_debug_message(
        'Profile-update handler received event.',
        array(
            'user_id'          => (int) $user_id,
            'form_data_is_arr' => is_array($form_data),
        )
    );

    $bot_api_token    = (string) get_option('telegram_bot_api_token', '');
    $channel_id       = (string) get_option('telegram_channel_id_updates', '');
    $arm_allowed_keys = get_option('telegrarm_arm_mapping', array());
    $text             = sprintf('Profile update for user %d', (int) $user_id);
    $updates          = '';
    $url              = "https://api.telegram.org/bot{$bot_api_token}/sendMessage";

    if ('' === $bot_api_token || '' === $channel_id || !is_array($arm_allowed_keys) || !is_array($form_data)) {
        telegrarm_log_debug_message(
            'Profile-update handler skipped: missing configuration or invalid payload.',
            array(
                'bot_token_set'   => '' !== $bot_api_token,
                'channel_id_set'   => '' !== $channel_id,
                'mapping_is_array' => is_array($arm_allowed_keys),
                'form_data_is_arr' => is_array($form_data),
            )
        );

        return;
    }

    foreach ($form_data as $key => $value) {
        $updates .= telegrarm_build_profile_update_line($key, $value, $arm_allowed_keys);
    }

    $post_data = array(
        'chat_id'    => $channel_id,
        'parse_mode' => 'HTML',
        'text'       => '<b>' . telegrarm_escape_telegram_html_text($text) . "</b>\n" . $updates,
    );

    $result = wp_remote_post($url, array('body' => $post_data));

    if (is_wp_error($result)) {
        telegrarm_log_debug_message(
            'Profile-update Telegram message request failed.',
            array(
                'action' => 'sendMessage',
                'error'  => $result->get_error_message(),
                'code'   => $result->get_error_code(),
            )
        );

        return;
    }

    $telegram_response = telegrarm_get_telegram_response_details($result);

    if (200 !== $telegram_response['status_code'] || true !== $telegram_response['ok']) {
        telegrarm_log_debug_message(
            'Profile-update Telegram message request was unsuccessful.',
            array(
                'action'      => 'sendMessage',
                'status_code' => $telegram_response['status_code'],
                'telegram_ok' => $telegram_response['ok'],
                'error_code'  => $telegram_response['error_code'],
                'description' => $telegram_response['description'],
            )
        );

        return;
    }
}
