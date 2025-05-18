# TelegrARM

WordPress plugin to send notifications to Telegram for select ARMember user events.

## Description

TelegrARM integrates WordPress + ARMember with Telegram to send automated notifications when users:
- Update their profile
- Register as new users

This plugin is designed as a framework that you can extend for additional notification types.

## Requirements

- WordPress 6.7+
- PHP 8.0+
- ARMember plugin installed and configured
- Telegram bot token and channel/group

## Installation

1. Install and activate the plugin through the WordPress plugins screen
2. Create a Telegram bot following [Telegram's Bot documentation](https://core.telegram.org/bots/tutorial#introduction)
3. Configure the plugin settings under "Settings > Telegram Bot":
   - Enter your Telegram Bot API token
   - Configure channel IDs for notifications
   - Enable desired notification types
   - Map ARMember fields to notification content

## Updating

This plugin supports automatic updates via GitHub using the [GitHub Updater](https://github.com/afragen/github-updater) plugin.

Repository: `https://github.com/renatobo/TelegrARM`

## License

Licensed under [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html)

## Author

Renato Bonomini ([@renatobo](https://github.com/renatobo))