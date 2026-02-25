# TelegrARM
[![WordPress](https://img.shields.io/badge/WordPress-6.7%2B-21759b)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777bb4)](https://www.php.net/)
[![Version](https://img.shields.io/badge/version-0.3.1-blue)](https://github.com/renatobo/TelegrARM/releases)
[![Psalm](https://github.com/renatobo/TelegrARM/actions/workflows/psalm.yml/badge.svg?branch=main)](https://github.com/renatobo/TelegrARM/actions/workflows/psalm.yml)
[![License: GPL v2 or later](https://img.shields.io/badge/License-GPL%20v2%20or%20later-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

WordPress plugin for ARMember that sends Telegram notifications for selected user lifecycle events.

## Features

- ARMember event notifications to Telegram:
  - Profile updates (`arm_update_profile_external`)
  - New registrations (`arm_after_new_user_notification`)
- Per-event enable/disable toggles from WordPress admin
- Separate Telegram channel/chat IDs for each event type
- Configurable ARMember key-to-label mapping (JSON)
- Optional contact push on registration (`sendContact`) with phone normalization
- Conditional hook loading (only enabled handlers are attached)
- GitHub-based update compatibility via [GitHub Updater](https://github.com/afragen/github-updater)

## Why TelegrARM

If you already use ARMember and Telegram internally, TelegrARM provides a simple bridge to keep operations informed in real time without adding external middleware.

## Requirements

- WordPress `6.7+`
- PHP `8.0+`
- ARMember installed and active
- A Telegram bot token
- Telegram channel/group/chat IDs where the bot can post

## Installation

1. Copy this plugin into your WordPress plugins directory:
   - `/wp-content/plugins/telegrarm`
2. Activate **TelegrARM** in **Plugins**.
3. Go to **Settings > Telegram Bot**.
4. Configure:
   - Telegram Bot API token
   - Channel/chat ID for new user notifications
   - Channel/chat ID for profile updates
   - ARMember field mapping JSON
   - Optional registration contact settings
5. Save settings and test by triggering an ARMember event.

## Telegram Bot Setup

1. Open Telegram and start a chat with [`@BotFather`](https://t.me/BotFather).
2. Run `/newbot` and complete bot creation.
3. Copy the generated bot token.
4. Add the bot to your target channel/group.
5. Grant permission to post messages.
6. Paste token/channel IDs into **Settings > Telegram Bot**.

Reference: [Telegram Bot documentation](https://core.telegram.org/bots/tutorial#introduction)

## ARMember Field Mapping Example

Use JSON in **ARMember Keys Mapping (all fields)** to whitelist and label profile keys:

```json
{
  "first_name": "First Name",
  "last_name": "Last Name",
  "user_email": "Email"
}
```

Special handling already included:
- `arm_social_field_instagram` is formatted as an Instagram link
- `avatar` is formatted as a clickable URL

## Notifications Sent

### New User Registration
- Sends a formatted profile summary to the configured Telegram channel
- Optionally sends a Telegram contact card (`sendContact`) using configured phone meta key and default international prefix

### Profile Update
- Sends a formatted list of mapped/allowed fields that were submitted in the update

## Automatic Updates

TelegrARM includes GitHub metadata headers and supports dashboard updates via [GitHub Updater](https://github.com/afragen/github-updater).

Repository: [renatobo/TelegrARM](https://github.com/renatobo/TelegrARM)

## Security

Please review [SECURITY.md](./SECURITY.md) for vulnerability reporting and hardening guidance.

## Development

### Project structure

- `telegrarm.php`: plugin bootstrap + conditional hook registration
- `telegrarm_settings.php`: WordPress settings registration and admin UI
- `telegrarm_after_new_user_notification.php`: new-user Telegram handler
- `telegrarm_update_profile_external.php`: profile-update Telegram handler
- `uninstall.php`: cleanup logic

### CI

- Psalm static analysis workflow on push/PR to `main`
- Release zip workflow for `v*` tags

## License

GPL-2.0-or-later. See [LICENSE](./LICENSE).

## Author

Renato Bonomini ([renatobo](https://github.com/renatobo))
