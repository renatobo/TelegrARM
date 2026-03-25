# WordPress.org Submission Checklist

Release prepared in this repository: `0.5.4`

Submission ZIP:
- `/Users/renatobo/development/TelegrARM/TelegrARM-v0.5.4.zip`

What is already prepared:
- Plugin version bumped to `0.5.4` in `telegrarm.php` and `readme.txt`.
- `readme.txt` updated with a `0.5.4` changelog and upgrade notice.
- Expanded Telegram test-message feedback to show the target type, chat ID, HTTP status, Telegram response details, and raw API body in the settings UI.
- Updated Telegram test-message wording so each test call names the New user or Profile updates configuration it is validating.
- Added inline Telegram test-message actions in the New user and Profile updates settings tabs.
- Added Telegram setup guidance for using the test-message action to confirm the bot can post in the target channel.
- Added opt-in debug logging for sanitized production troubleshooting.
- Added an ARMember field discovery UI to reduce manual JSON entry.
- Updated discovery to read ARMember registry data first, then form-field definitions, before falling back to usermeta scanning.
- Hardened the discovery UI so existing mappings remain selected and sensitive/non-data fields are excluded from generated JSON.
- Tightened Telegram response validation and debug-log redaction so failures are surfaced without leaking secrets.
- Reissued the release from the correct commit so the GitHub release asset matches the plugin version WordPress should install.
- Telegram external-service disclosure added for plugin review.
- Direct-access guards added to included PHP handler files.
- Package build updated to include the plugin icon asset.

Manual steps still required on WordPress.org:
1. Submit the ZIP through the plugin submission form: `https://wordpress.org/plugins/developers/add/`
2. Provide a short reviewer summary:
   - ARMember integration plugin that sends Telegram notifications for new registrations and profile updates.
   - External service used: Telegram Bot API.
   - Data sent: destination chat ID, mapped profile fields, and optional contact payload.
3. After the plugin is approved, add directory assets in the WordPress.org SVN `assets` directory:
   - plugin icon
   - banner image
4. If the review team requests changes, apply them in `trunk` and resubmit.
5. Keep the GitHub release flow and Git Updater metadata in place because this plugin is intended to support dual distribution through both WordPress.org and GitHub.

Recommended reviewer notes:
- ARMember must be installed and active.
- TelegrARM does not send data unless the relevant event toggle is enabled and a Telegram bot token/chat ID is configured.
- Uninstall removes the plugin options it stores.
