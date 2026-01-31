# TelegrARM - Claude AI Developer Guide

## Project Overview

TelegrARM is a WordPress plugin that bridges ARMember membership plugin with Telegram, enabling automated notifications for user events. This plugin serves as an extensible framework for Telegram-based notifications.

## Architecture

### Core Components

- **[telegrarm.php](telegrarm.php)** - Main plugin file with conditional hook initialization
- **[telegrarm_settings.php](telegrarm_settings.php)** - WordPress admin settings interface
- **[telegrarm_update_profile_external.php](telegrarm_update_profile_external.php)** - Profile update notification handler
- **[telegrarm_after_new_user_notification.php](telegrarm_after_new_user_notification.php)** - New user registration notification handler
- **[uninstall.php](uninstall.php)** - Plugin cleanup on uninstall

### Plugin Structure

```
TelegrARM/
├── telegrarm.php                               # Main plugin file
├── telegrarm_settings.php                      # Admin settings page
├── telegrarm_update_profile_external.php       # Profile update hook
├── telegrarm_after_new_user_notification.php   # New user hook
└── uninstall.php                               # Cleanup script
```

## Technical Requirements

- **WordPress:** 6.7+
- **PHP:** 8.0+ (tested up to PHP 8.5)
- **Dependencies:** ARMember plugin
- **External Services:** Telegram Bot API

## Key Design Patterns

### Conditional Hook Loading
The plugin uses `telegrarm_init_hooks_conditionally()` to dynamically load notification handlers based on settings, ensuring minimal overhead when features are disabled.

### Modular Notification System
Each notification type is implemented in a separate file and loaded only when enabled, making the codebase extensible for new notification types.

## WordPress Integration Points

### ARMember Hooks
- `arm_update_profile_external` - Triggered when users update their profile
- `arm_after_new_user_notification` - Triggered when new users register

### WordPress Options
Plugin settings are stored in WordPress options table with the `telegrarm_` prefix:
- `telegrarm_profile_update` - Enable/disable profile update notifications
- `telegrarm_after_new_user_notification` - Enable/disable new user notifications
- `telegrarm_bot_token` - Telegram Bot API token
- Additional settings for channel IDs and field mappings

## Telegram API Integration

The plugin communicates with Telegram's Bot API to send formatted notifications. Ensure proper error handling and rate limiting when making API calls.

## Development Guidelines

### Coding Standards
- Follow WordPress PHP Coding Standards
- Use WordPress escaping functions for all output (`esc_html()`, `esc_attr()`, etc.)
- Sanitize all input data (`sanitize_text_field()`, `sanitize_email()`, etc.)
- Validate and verify nonces for all form submissions
- Use WordPress HTTP API (`wp_remote_post()`) for external API calls

### Security Considerations
- **Never expose bot tokens** in client-side code or error messages
- **Validate user permissions** before sending notifications
- **Sanitize all user data** before including in Telegram messages
- **Use nonces** for all admin form submissions
- **Check capabilities** for admin pages (`manage_options`)

### Adding New Notification Types

1. Create a new PHP file: `telegrarm_[event_name].php`
2. Implement the notification function
3. Add option check in `telegrarm_init_hooks_conditionally()`
4. Add settings UI in `telegrarm_settings.php`
5. Update documentation

### Testing Checklist
- Test with WordPress 6.7+ and latest version
- Verify ARMember compatibility
- Test Telegram API connection
- Validate all user inputs
- Check error handling for failed API calls
- Test uninstall cleanup

## Common Tasks

### Updating Version Number
Update in three places:
1. Plugin header in [telegrarm.php](telegrarm.php#L7)
2. `BONO_TELEGRARM_VERSION` constant in [telegrarm.php](telegrarm.php#L24)

### Adding Settings
1. Register option in `telegrarm_settings.php`
2. Add settings field to admin page
3. Implement sanitization callback
4. Update uninstall script if needed

### Debugging
- Enable WordPress debug mode: `WP_DEBUG` and `WP_DEBUG_LOG`
- Check Telegram API responses for errors
- Verify bot permissions in Telegram channels
- Test with different ARMember form configurations

## Release Process

The plugin uses GitHub Actions for automated releases:
- Builds release ZIP on version tags
- Updates `stable` tag for GitHub Updater compatibility
- See [.github/workflows/](.github/workflows/) for automation details

## External Resources

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [Telegram Bot API Documentation](https://core.telegram.org/bots/api)
- [ARMember Documentation](https://www.armemberplugin.com/documentation/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)

## Support and Contribution

- **Repository:** https://github.com/renatobo/TelegrARM
- **Issues:** Report bugs and feature requests via GitHub Issues
- **Author:** Renato Bonomini ([@renatobo](https://github.com/renatobo))

## License

GPLv2 or later - See plugin header for full license information
