# Upgrading TelegrARM to 1.0.0

TelegrARM 1.0.0 preserves all existing WordPress option names and ARMember hook callbacks. No manual database migration is normally required. The plugin runs an idempotent upgrade routine on activation and on the first request after files are updated.

## Before upgrading

1. Confirm the site runs WordPress 6.7 or later and PHP 8.0 or later.
2. Confirm ARMember is installed and active.
3. Take a database backup and a copy of the current TelegrARM plugin directory.
4. Record the enabled event toggles, destination chat IDs, contact settings, and field mapping JSON from **Settings > TelegrARM**.
5. Confirm WP-Cron is operational. If `DISABLE_WP_CRON` is enabled, confirm a system cron calls `wp-cron.php` regularly.
6. If the site uses a bot-token constant, define it before upgrading:

   ```php
   define( 'TELEGRARM_BOT_TOKEN', '123456789:replace-with-the-real-token' );
   ```

## Upgrade procedure

### Git Updater or WordPress dashboard

1. Install the published `TelegrARM-v1.0.0.zip` release through Git Updater or the WordPress plugin uploader.
2. Keep the plugin active. The 1.0.0 upgrade routine preserves the existing token but changes its option to non-autoloaded storage.
3. Open **Settings > TelegrARM**. A configured token is shown only as **Configured**; its value is no longer placed in the page markup.
4. Save settings once. Leave the token input empty to retain the existing token.
5. Use both **Send a test message** actions.
6. Trigger one test registration and one test profile update.
7. Confirm the queued messages arrive and inspect **Tools > Site Health** for loopback or scheduled-event problems if they do not.

### Manual file replacement

1. Deactivate TelegrARM only if the site's deployment procedure requires it. Do not uninstall it, because uninstall removes all TelegrARM options.
2. Replace the plugin directory with the contents of `TelegrARM-v1.0.0.zip`.
3. Reactivate the plugin if needed, then complete steps 3-7 above.

## Behavior changes in 1.0.0

- Event notifications are queued through WP-Cron instead of blocking registration or profile-update requests.
- Transient delivery failures are retried at most three times. Telegram 429 `retry_after` values are honored up to five minutes.
- Per-chat dispatch is paced to avoid bursts.
- Telegram messages are capped below the 4,096-character API limit.
- Only fields present in the saved mapping are sent. This now also applies to Instagram and avatar fields.
- The saved bot token is not redisplayed. Leaving the field blank preserves it; the removal checkbox explicitly deletes it.
- `TELEGRARM_BOT_TOKEN` and the `telegrarm_bot_token` filter can supply the runtime token without database storage.
- Bot token, channel ID, and international dialing-code validation are stricter.
- Test-message diagnostics no longer return the raw Telegram response body.

## Compatibility notes

- Existing option names are unchanged.
- Existing callbacks `telegrarm_profile_update()` and `telegrarm_after_new_user_notification()` remain available.
- Existing mapped labels and enabled-event settings are retained.
- A previously saved malformed channel ID may be cleared the next time settings are saved. Re-enter a numeric chat ID, a negative group/channel ID, or an `@channel_username`.
- Sites that disable WP-Cron must provide a real cron runner; otherwise event notifications remain queued until WordPress cron executes.

## Verification checklist

- Settings page loads without PHP warnings under `WP_DEBUG`.
- Existing token badge reads **Configured** without exposing the credential in page source.
- New-user and profile test messages succeed.
- Unmapped Instagram and avatar fields are not sent.
- Mapped fields remain correctly escaped and labeled.
- Registration and profile-update requests return without waiting for Telegram.
- A failed Telegram request produces only redacted debug context when debug logging is enabled.

## Rollback

1. Deactivate TelegrARM without uninstalling it.
2. Restore the previous plugin directory or install the prior release ZIP.
3. Reactivate the prior version.
4. Verify settings and send a test message.

The 1.0.0 migration does not rename options, so rollback does not require a database downgrade. Any already scheduled `telegrarm_process_delivery` events are ignored by older versions and can be removed with WP-CLI if required:

```bash
wp cron event delete telegrarm_process_delivery --all
```
