<?php
/**
 * Test bootstrap for pure plugin behavior.
 */

define('ABSPATH', __DIR__ . '/wordpress/');
define('MINUTE_IN_SECONDS', 60);
define('BONO_TELEGRARM_VERSION', '1.0.0');

require_once dirname(__DIR__) . '/stubs/wordpress-stubs.php';
require_once dirname(__DIR__) . '/includes/class-telegrarm-message-formatter.php';
require_once dirname(__DIR__) . '/includes/class-telegrarm-config.php';
require_once dirname(__DIR__) . '/includes/class-telegrarm-debug-logger.php';
require_once dirname(__DIR__) . '/includes/class-telegrarm-telegram-client.php';
require_once dirname(__DIR__) . '/includes/class-telegrarm-delivery-queue.php';
require_once dirname(__DIR__) . '/telegrarm_settings.php';
require_once dirname(__DIR__) . '/telegrarm_after_new_user_notification.php';
require_once dirname(__DIR__) . '/telegrarm_update_profile_external.php';
