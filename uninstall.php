<?php
/**
 * TelegrARM - Uninstall Script
 *
 * Removes all plugin options from the WordPress database on uninstall.
 *
 * @package   TelegrARM
 * @author    Renato Bonomini <https://github.com/renatobo>
 * @copyright 2024 Renato Bonomini
 * @license   GPLv2 or later
 * @link      https://github.com/renatobo/TelegrARM
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove all plugin options from the database
delete_option('telegrarm_profile_update');
delete_option('telegrarm_after_new_user_notification');
delete_option('telegram_bot_api_token');
delete_option('telegram_channel_id_newuser');
delete_option('telegram_channel_id_updates');
delete_option('telegram_send_contact_during_registration');
delete_option('telegram_phone_field_name');
delete_option('telegram_international_code_if_missing');
delete_option('telegrarm_arm_mapping');