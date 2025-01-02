<?php
/** 
 * Notify on new user registration
 */

function telegrarm_after_new_user_notification ($user) {
    $botApiToken = get_option('telegram_bot_api_token', '');
    $channelId   = get_option('telegram_channel_id_newuser', '');
    // Retrieve the saved key-to-label mapping (array) or default to []
    $arm_allowed_keys = get_option('telegrarm_arm_mapping', array());

	// Get all user meta data for $user_id
	$meta = get_user_meta( $user->ID );

	// Filter out empty meta data
	$meta = array_filter( array_map( function( $a ) {
		return $a[0];
	}, $meta ) );

    $user_login = maybe_unserialize($user->user_login);
    $text = "New user registered: $user_login [{$user->ID}]";

    $profile = "";
    $url = "https://api.telegram.org/bot{$botApiToken}/sendMessage";

    foreach ($meta as $key => $value) {
        if ($key == "arm_social_field_instagram") {
            $profile.= "IG: <a href='https://instagram.com/{$value}'>@{$value}</a>\n";
        } elseif ($key == "avatar") {
            $profile.= "avatar: <a href='https://{$value}'>{$value}</a>\n";
        } elseif (isset($arm_allowed_keys[$key])) {
            $label = $arm_allowed_keys[$key];
            $profile.= "{$label}: {$value}\n";
        }
    }

    $post_data = array(
        'chat_id' => $channelId,
        'parse_mode' => 'HTML',
        'text' => "<b>".$text."</b>\n".$profile);

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
    } else {
        // now optionally send the user contact
        $sendContact = get_option('telegram_send_contact_during_registration', false);
        if ($sendContact) {
            $phone_field_name = get_option('telegram_phone_field_name', 'text_t0cls');
            $default_code     = get_option('telegram_international_code_if_missing', '+1');
            $phone = $meta[$phone_field_name];
            
            // If there’s a phone number and it doesn't start with '+', prepend the default code
            if ( ! empty($phone) && 0 !== strpos($phone, '+') ) {
                $phone = $default_code . $phone;
            }

            $url = "https://api.telegram.org/bot{$botApiToken}/sendContact";
            $post_data = array(
                'chat_id' => $channelId,
                'phone_number' => $phone,
                'first_name' => $meta["first_name"],
                'last_name' => $meta["last_name"]);
                $result = wp_remote_post( $url, array( 'body' => $post_data ) );
        }

    }
}
