=== TelegrARM ===
Contributors: renatobo
Tags: telegram, armember, notifications, integration
Requires at least: 6.7
Tested up to: 6.8.1
Requires PHP: 8.0
Stable tag: 0.3.1
Version: 0.3.1
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
- Compatible with GitHub Updater for dashboard updates

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/telegrarm/`.
2. Activate **TelegrARM** in the WordPress admin.
3. Go to **Settings > Telegram Bot**.
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
Yes. Install and activate GitHub Updater:
https://github.com/afragen/github-updater

== Changelog ==

= 0.3.1 =
- Current stable release.
- Telegram notifications for profile updates and new registrations.
- Configurable ARMember mapping and per-event channel settings.
- Optional contact send during registration.

== Upgrade Notice ==

= 0.3.1 =
Recommended stable release.

