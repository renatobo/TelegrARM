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

function telegrarm_profile_update ($user_id, $form_data) {
    $botApiToken = get_option('telegram_bot_api_token', '');
    $channelId   = get_option('telegram_channel_id_updates', '');
    // Retrieve the saved key-to-label mapping (array) or default to []
    $arm_allowed_keys = get_option('telegrarm_arm_mapping', array());

    $text = "Profile update for user {$user_id}";
    $updates = "";
    $url = "https://api.telegram.org/bot{$botApiToken}/sendMessage";

    foreach ($form_data as $key => $value) {
        if ($key == "arm_social_field_instagram") {
            $updates.= "IG: <a href='https://instagram.com/{$value}'>@{$value}</a>\n";
        } elseif ($key == "avatar") {
            $updates.= "avatar: <a href='https://{$value}'>{$value}</a>\n";
        } elseif (isset($arm_allowed_keys[$key])) {
            $label = $arm_allowed_keys[$key];
            $updates.= "{$label}: {$value}\n";
        }
    }

    $post_data = array(
        'chat_id' => $channelId,
        'parse_mode' => 'HTML',
        'text' => "<b>".$text."</b>\n".$updates);

    $result = wp_remote_post( $url, array( 'body' => $post_data ) );

    // Check if there's any WP error (network error, etc.)
    if (is_wp_error($result)) {
        // Handle error if needed (e.g., log it)
        return;
    }

    // Retrieve the HTTP status code
    $code = wp_remote_retrieve_response_code($result);

    // If HTTP code is not 200, send a second message with "ciao"
    if (200 !== $code) {
        $post_data['text'] = $text.', error: '.$result['description'];
        $post_data['parse_mode'] = '';
        wp_remote_post($url, ['body' => $post_data]);
    }
}
