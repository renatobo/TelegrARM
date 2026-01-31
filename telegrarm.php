<?php
/**
 * Plugin Name:       TelegrARM
 * Plugin URI:        https://github.com/renatobo/TelegrARM
 * Description:       Enable Telegram notifications for user profile updates and other ARMember events.
 * Version:           0.3.1
 * Requires at least: 6.7
 * Requires PHP:      8.0
 * Author:            Renato Bonomini
 * Author URI:        https://github.com/renatobo
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       telegrarm
 * Domain Path:       /languages
 *
 * GitHub Plugin URI: https://github.com/renatobo/TelegrARM
 * GitHub Branch:     main
 *
 * @package           TelegrARM
 * @author            Renato Bonomini <https://github.com/renatobo>
 * @copyright         2024 Renato Bonomini
 * @license           GPLv2 or later
 * @link              https://github.com/renatobo/TelegrARM
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

define('BONO_TELEGRARM_VERSION', '0.3.1');

// Check PHP version requirement
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    // Show admin notice about PHP version
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>TelegrARM:</strong> This plugin requires PHP 8.0 or higher. ';
        echo 'You are running PHP ' . esc_html(PHP_VERSION) . '. ';
        echo 'Please upgrade your PHP version.';
        echo '</p></div>';
    });
    // Don't load plugin functionality
    return;
}

// Load settings page
require_once __DIR__ . '/telegrarm_settings.php';

/**
 * Initialize plugin hooks conditionally based on settings
 */
function telegrarm_init_hooks_conditionally() {
    // Load profile update notifications if enabled
    if (get_option('telegrarm_profile_update', false)) {
        require_once __DIR__ . '/telegrarm_update_profile_external.php';
        add_action('arm_update_profile_external', 'telegrarm_profile_update', 10, 2);
    }

    // Load new user notifications if enabled
    if (get_option('telegrarm_after_new_user_notification', false)) {
        require_once __DIR__ . '/telegrarm_after_new_user_notification.php';
        add_action('arm_after_new_user_notification', 'telegrarm_after_new_user_notification', 10, 1);
    }
}

add_action('plugins_loaded', 'telegrarm_init_hooks_conditionally');