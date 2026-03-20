# WordPress.org Submission Checklist

Release prepared in this repository: `0.4.4`

Submission ZIP:
- `/Users/renatobo/development/TelegrARM/TelegrARM-0.4.4.zip`

What is already prepared:
- Plugin version bumped to `0.4.4` in `telegrarm.php` and `readme.txt`.
- `readme.txt` updated with a `0.4.4` changelog and upgrade notice.
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
