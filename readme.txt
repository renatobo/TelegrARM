=== TelegrARM ===
Contributors: renatobo
Tags: telegram, armember, notifications, integration
Requires at least: 7.0
Tested up to: 7.0.3
Requires PHP: 8.0
Requires Plugins: armember-membership
Stable tag: 1.1.3
Version: 1.1.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send Telegram notifications for selected ARMember user events.

== Description ==

TelegrARM connects ARMember events to Telegram so your team receives notifications in real time.

Supported notifications:
- New user registration (`arm_after_new_user_notification`)
- User profile update (a member saving an ARMember edit-profile form, Lite or Pro)

Key capabilities:
- Enable/disable each event type independently
- Configure separate Telegram channel/chat IDs per event type
- Send an inline test message from each event tab to verify the Telegram destination
- Map ARMember fields to human-friendly labels using JSON
- Optional Telegram contact card send on registration
- Opt-in debug logging for sanitized production troubleshooting
- ARMember field discovery UI that builds mapping JSON from ARMember registry data

External services:
- This plugin connects to the Telegram Bot API to send notifications when enabled ARMember events fire.
- Data sent to Telegram includes the configured destination chat ID, the notification text built from mapped ARMember profile fields, and optional contact data when contact sending is enabled.
- Event payloads are stored temporarily in WordPress scheduled events while bounded background delivery is pending. Bot tokens are not stored in queued payloads.
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
   - Use the per-event "Send a test message" button to verify each destination
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

= 1.1.3 =
- Fixed profile-update notifications never firing on ARMember Lite, which does not fire `arm_update_profile_external`.
- Fixed `user_login` and `user_email` never appearing in new-user messages.
- Fixed message lines ignoring the field order set in the mapping builder.
- Fixed duplicated settings notices and a misleading description on successful test messages.
- Fixed notifications being dropped when a mapped field contained a named HTML entity such as `&nbsp;`, which Telegram rejects.
- Fixed checkbox and multi-select fields appearing as serialized data or being omitted; they are now comma-separated lists.
- Fixed contact cards failing for members without a first name.
- Rejected credential and permission keys such as `user_pass` from the field mapping and from every message.
- Cleared cron events and queued payloads on deactivation and uninstall, and cleaned every site on multisite uninstall.
- Added the `telegrarm_should_enqueue` and `telegrarm_message_text` filters and the `telegrarm_delivery_sent` and `telegrarm_delivery_abandoned` actions.
- Added suggested privacy policy text, a Rescan button for field discovery, and a disabled token field when `TELEGRARM_BOT_TOKEN` is defined.

= 1.1.2 =
- Fixed queued notifications being silently dropped when a persistent object cache was flushed after the delivery was scheduled, by storing payloads in randomized, non-autoloaded options instead of transients.
- Fixed the pacing and retry paths deleting the payload of a delivery that was still scheduled, which could strand a live event when two cron spawns overlapped.
- Added a daily cleanup event for payloads whose delivery never ran, and raised their lifetime from six hours to three days so a quiet site does not reap them before WP-Cron fires.
- Logged queued and successful deliveries, and reported the method and target on every drop path, so a missing notification can be diagnosed from the debug log.

= 1.1.1 =
- Declared ARMember as a required plugin through the `Requires Plugins` header, so WordPress blocks TelegrARM activation until ARMember is installed and active.

= 1.1.0 =
- Raised the minimum WordPress version to 7.0 and tested the plugin against WordPress 7.0.3.
- Removed the runtime PHP version guard and its admin notice; the `Requires PHP: 8.0` header is enforced by WordPress core at install, update, and activation.

= 1.0.1 =
- Kept member data out of the autoloaded cron option by storing queued delivery payloads in randomized, non-autoloaded transients and passing only an opaque ticket to WP-Cron.
- Fixed per-chat pacing, which previously only deferred deliveries queued within the same second, to space messages at one every four seconds per chat.
- Limited the clear-token checkbox to nonce-verified settings saves so an unrelated option update can no longer blank the stored bot token.
- Reported a format error when an admin test message uses a malformed bot token instead of silently testing the previously saved token.
- Removed queued delivery payloads and transient markers on uninstall.
- Published releases with explicit release notes, a SHA-256 checksum, and a build provenance attestation.

= 1.0.0 =
- Moved event delivery to a bounded WP-Cron queue with short HTTP timeouts, capped retries, Telegram 429 handling, and per-chat pacing.
- Enforced field mapping as a true allowlist, including Instagram and avatar fields.
- Centralized configuration, formatting, transport, logging, queueing, and upgrade behavior into focused runtime modules.
- Split cached ARMember database discovery and admin CSS/JavaScript out of the settings renderer.
- Kept bot tokens server-side, added constant/filter overrides, migrated saved tokens to non-autoloaded storage, and removed raw response bodies from admin diagnostics.
- Added strict identifier validation, sensitive-field discovery filtering, message-length limits, ARMember dependency notices, and more aggressive log redaction.
- Added PHPUnit coverage, PHP 8.0/8.2/8.5 CI, WordPress Coding Standards, ShellCheck, and release-package validation.
- Added a documented 1.0.0 upgrade and rollback procedure.

= 0.5.4 =
- Expanded Telegram test-message feedback in the settings UI to include the target type, chat ID, HTTP status, Telegram ok flag, error code or description, and raw API response body.

= 0.5.3 =
- Updated Telegram test messages so they identify whether the validation is for the New user or Profile updates configuration before naming the current website.

= 0.5.2 =
- Added inline "Send a test message" actions to the New user and Profile updates tabs so each configured Telegram destination can be verified directly from plugin settings.
- Added Telegram setup guidance that points users to the test-message action when validating bot posting permissions and channel delivery.

= 0.5.1 =
- Reissued the release from the correct commit so GitHub release assets and WordPress updates resolve to the current plugin version.

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

= 1.1.3 =
Profile-update notifications now work on ARMember Lite. Also fixes messages silently dropped on fields containing entities like `&nbsp;` and shows checkbox fields correctly. Credential keys are now refused in the field mapping. No settings or data migration.

= 1.1.2 =
Reliability fix release for sites running a persistent object cache, where queued notifications could be lost before WP-Cron delivered them. No settings or data changes, and deliveries queued by earlier versions are processed without migration.

= 1.1.1 =
ARMember is now a hard requirement: TelegrARM cannot be activated unless a plugin folder named armember-membership is installed and active. Existing ARMember Lite sites are unaffected. Sites running ARMember Premium from CodeCanyon, or installing ARMember for the first time, cannot satisfy this dependency and should stay on 1.1.0.

= 1.1.0 =
Requires WordPress 7.0 or later. Sites on an older WordPress will not be offered this update and should stay on 1.0.1. No settings or data changes.

= 1.0.1 =
Privacy and reliability fix release. Queued notifications no longer keep member data in the autoloaded cron option, and per-chat pacing now works as documented. Deliveries queued by 1.0.0 are processed without migration.

= 1.0.0 =
Major reliability and privacy hardening release. Existing options are retained, but WP-Cron must be operational for background delivery. Review UPGRADE.md before deployment.

= 0.5.4 =
Improves Telegram test-message feedback with explicit delivery details and the raw Telegram API response.

= 0.5.3 =
Clarifies Telegram test deliveries by naming the settings area being validated in each test message.

= 0.5.2 =
Adds inline Telegram test-message validation for both event tabs and documents the verification step in Telegram setup.

= 0.5.1 =
Republishes the release from the correct commit so dashboard updates download the right package.

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
