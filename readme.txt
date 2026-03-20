=== TelegrARM ===
Contributors: renatobo
Tags: telegram, armember, notifications, integration
Requires at least: 6.7
Tested up to: 6.8.1
Requires PHP: 8.0
Stable tag: 0.4.2
Version: 0.4.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send Telegram notifications for selected ARMember user events.

== Description ==

TelegrARM connects ARMember events to Telegram so your team receives notifications in real time.

Supported notifications:
- New user registration (`arm_after_new_user_notification`)
- User profile update (`arm_update_profile_external`)

Key capabilities:
- Enable/disable each event type independently
- Configure separate Telegram channel/chat IDs per event type
- Map ARMember fields to human-friendly labels using JSON
- Optional Telegram contact card send on registration
- Compatible with Git Updater release assets for dashboard updates
- Versioned plugin ZIPs generated automatically by GitHub Actions

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/TelegrARM/`.
2. Activate **TelegrARM** in the WordPress admin.
3. Go to **Settings > TelegrARM**.
4. Configure:
   - Telegram Bot API Token
   - Channel/chat ID for new users
   - Channel/chat ID for profile updates
   - ARMember key mapping JSON
   - Optional contact settings
5. Save settings.

== Frequently Asked Questions ==

= Do I need ARMember? =
Yes. TelegrARM is designed to work with ARMember events.

= How do I create a Telegram bot token? =
Create a bot with `@BotFather` and use the generated token.
Reference: https://core.telegram.org/bots/tutorial#introduction

= Can I choose which fields are sent? =
Yes. Use **ARMember Keys Mapping (all fields)** in plugin settings to control which keys are included and their labels.

= Can I send user contact info on registration? =
Yes. Enable **Send contact on new user registration?**, then configure the phone field name and default international prefix.

= Does this plugin support automatic updates from GitHub? =
Yes. Install and activate Git Updater:
https://github.com/afragen/git-updater

== Changelog ==

= 0.4.2 =
- Hardened Telegram notification handlers against HTML injection from user-supplied profile values.
- Removed noisy Telegram failure paths so malformed hook payloads and missing configuration fail quietly under `WP_DEBUG`.
- Expanded release packaging to include `readme.txt` and `LICENSE`.

= 0.4.1 =
- Limited release ZIP contents to the files required by the plugin on a WordPress site.
- Added local WordPress Psalm stubs so static analysis passes without bundling development-only files in releases.

= 0.4.0 =
- Rebuilt the settings page with the same tabbed WordPress-admin layout used by eventon-apify.
- Added Git Updater release-asset metadata so dashboard updates can use GitHub release ZIPs.
- Added automated packaging and release workflows for version tags.

= 0.3.1 =
- Telegram notifications for profile updates and new registrations.
- Configurable ARMember mapping and per-event channel settings.
- Optional contact send during registration.

== Upgrade Notice ==

= 0.4.2 =
Hardens Telegram message handling and includes the plugin readme/license in release packages.

= 0.4.1 =
Keeps release ZIPs WordPress-ready by shipping only runtime plugin files.

= 0.4.0 =
Adds the redesigned settings UI and automated GitHub release packaging.
