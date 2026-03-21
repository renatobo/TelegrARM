=== TelegrARM ===
Contributors: renatobo
Tags: telegram, armember, notifications, integration
Requires at least: 6.7
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 0.5.0
Version: 0.5.0
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
- Opt-in debug logging for sanitized production troubleshooting
- ARMember field discovery UI that builds mapping JSON from ARMember registry data

External services:
- This plugin connects to the Telegram Bot API to send notifications when enabled ARMember events fire.
- Data sent to Telegram includes the configured destination chat ID, the notification text built from mapped ARMember profile fields, and optional contact data when contact sending is enabled.
- Telegram terms of service: https://telegram.org/tos
- Telegram privacy policy: https://telegram.org/privacy

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

= Does this plugin use an external service? =
Yes. TelegrARM sends requests to the Telegram Bot API when enabled events fire. Review Telegram's terms at https://telegram.org/tos and privacy policy at https://telegram.org/privacy.

== Changelog ==

= 0.5.0 =
- Hardened the ARMember field discovery workflow so existing mappings stay selected by default, non-data and sensitive fields are excluded, and discovery messaging matches the real registry-first behavior.
- Added more robust Telegram delivery diagnostics by validating the Bot API `ok` response body, broadening debug-log redaction, and covering both message and contact send failures.
- Extended local Psalm support and project config so static analysis passes cleanly in the repository workspace.

= 0.4.7 =
- Switched the field discovery UI to read ARMember registry data first, then form-field definitions, before falling back to usermeta scanning.

= 0.4.6 =
- Added an ARMember field discovery UI that scans stored site meta, suggests common preset keys, and builds the mapping JSON from selected fields.

= 0.4.5 =
- Added an opt-in debug logging toggle that writes sanitized Telegram failure traces to the PHP error log for live production troubleshooting.
- Added request-failure logging for the optional registration contact send path so silent failures are easier to diagnose.

= 0.4.4 =
- Finalized dual-distribution release metadata so GitHub plus Git Updater remains the primary channel while WordPress.org stays submission-ready as a secondary channel.
- Added plugin text-domain loading, handler direct-access guards, and WordPress HTTP Psalm stubs for cleaner runtime and CI behavior.
- Updated release and agent documentation to keep versioning and distribution rules consistent.

= 0.4.3 =
- Added direct-access guards to the notification handler files for WordPress.org review readiness.
- Added explicit Telegram external-service disclosure for WordPress.org submission.
- Kept Git Updater metadata and admin links for GitHub-based update flows alongside WordPress.org submission prep.
- Added missing WordPress HTTP stubs so Psalm passes in CI.

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

= 0.5.0 =
Hardens the discovery and Telegram debugging flows, and keeps Psalm clean in the local workspace.

= 0.4.7 =
Prefers ARMember registry data for mapping discovery and uses usermeta only as a fallback.

= 0.4.6 =
Builds on the debug logging update with an ARMember field discovery UI that reduces manual JSON entry.

= 0.4.5 =
Enables opt-in debug logging for production troubleshooting without exposing Telegram tokens or other sensitive values.

= 0.4.4 =
Refines the plugin for the GitHub-first, WordPress.org-secondary release flow and bundles the related hardening/documentation updates.

= 0.4.3 =
Prepares the plugin for WordPress.org directory submission and hardens review-facing packaging details.

= 0.4.2 =
Hardens Telegram message handling and includes the plugin readme/license in release packages.

= 0.4.1 =
Keeps release ZIPs WordPress-ready by shipping only runtime plugin files.

= 0.4.0 =
Adds the redesigned settings UI and automated GitHub release packaging.
