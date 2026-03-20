# TelegrARM
[![WordPress](https://img.shields.io/badge/WordPress-6.7%2B-21759b)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777bb4)](https://www.php.net/)
[![Release](https://img.shields.io/github/v/release/renatobo/TelegrARM?label=release)](https://github.com/renatobo/TelegrARM/releases)
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
- Git Updater-compatible release assets for dashboard updates
- Versioned release ZIPs built automatically by GitHub Actions

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
   - `/wp-content/plugins/TelegrARM`
2. Activate **TelegrARM** in **Plugins**.
3. Go to **Settings > TelegrARM**.
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
6. Paste token/channel IDs into **Settings > TelegrARM**.

Reference: [Telegram Bot documentation](https://core.telegram.org/bots/tutorial#introduction)

## External Services

TelegrARM connects to the Telegram Bot API when enabled ARMember events fire.

- Data sent to Telegram includes the configured destination chat ID, notification text built from mapped ARMember profile fields, and optional contact data when contact sending is enabled.
- Telegram terms of service: [telegram.org/tos](https://telegram.org/tos)
- Telegram privacy policy: [telegram.org/privacy](https://telegram.org/privacy)

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

TelegrARM includes the metadata Git Updater expects, including `Primary Branch` and `Release Asset`, so the plugin can update from GitHub release ZIPs through the WordPress dashboard when [Git Updater](https://github.com/afragen/git-updater) is installed.

Repository: [renatobo/TelegrARM](https://github.com/renatobo/TelegrARM)

## Packaging

Build an installable plugin ZIP from the repo root:

```bash
./build.sh
```

That creates a file like `TelegrARM-x.y.z.zip` in the project root, ready to upload in **Plugins > Add New > Upload Plugin**.
The archive includes only the plugin files needed on a WordPress site, excludes shell scripts, and keeps only `README.md` from markdown documentation files.

## Releases

The release badge above reflects the latest published GitHub Release, not just the latest git tag.

Use one of these explicit release paths to publish a WordPress-ready ZIP:

### Local release path

```bash
./release.sh x.y.z
```

This is the primary operator workflow. It:

- updates the plugin version in `telegrarm.php`
- updates the stable tag in `readme.txt`
- updates the `Version` field in `readme.txt`
- commits the version bump
- creates and pushes the git tag `vx.y.z`
- verifies that the plugin header, `BONO_TELEGRARM_VERSION`, `Stable tag`, and `readme.txt` `Version` all match

Pushing the tag triggers GitHub Actions, which runs `./build.sh`, creates or updates the GitHub Release for that tag, and uploads the generated ZIP asset automatically.

### Manual GitHub Actions path

Use the **Manual Release** workflow with a `version` input when you want GitHub Actions to perform an explicit release without relying on a separate tag-push workflow.

The manual workflow:

- validates that `telegrarm.php` and `readme.txt` already match the requested version
- reuses the existing `vx.y.z` tag or creates it if it does not exist
- runs `./build.sh`
- creates or updates the GitHub Release and uploads the generated ZIP asset

## Related Repositories

- [WebHookARM](https://github.com/renatobo/WebHookARM)
- [bono_arm_api](https://github.com/renatobo/bono_arm_api)

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
- Repo-managed CodeQL workflow on push/PR to `main` plus a weekly schedule
- Manual release workflow for explicit GitHub Actions-driven releases
- Release ZIP workflow for `v*` tags

## License

GPL-2.0-or-later. See [LICENSE](./LICENSE).

## Author

Renato Bonomini ([renatobo](https://github.com/renatobo))
