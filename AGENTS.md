# AGENTS.md

Guidance for coding agents working in this repository.

## Project

- Name: `TelegrARM`
- Type: WordPress plugin (PHP)
- Purpose: Send Telegram notifications for selected ARMember events.

## Key Files

- `telegrarm.php`: Plugin bootstrap, metadata, conditional hook loading.
- `telegrarm_settings.php`: Admin settings registration and UI.
- `telegrarm_after_new_user_notification.php`: New-user event handler.
- `telegrarm_update_profile_external.php`: Profile-update event handler.
- `uninstall.php`: Option cleanup on uninstall.
- `readme.txt`: WordPress-style plugin readme.
- `README.md`: GitHub readme.

## Environment and Compatibility

- WordPress: `6.7+`
- PHP: `8.0+`
- Dependency: ARMember plugin must be installed/active.

## Implementation Rules

- Follow WordPress coding standards and APIs.
- Escape all admin output (`esc_html`, `esc_attr`, etc.).
- Sanitize and validate all settings/user input.
- Use WordPress HTTP API (`wp_remote_post`) for Telegram calls.
- Keep feature toggles respected: handlers should only load when enabled.
- Do not introduce breaking option name changes without migration.

## When Adding Features

1. Add/adjust options in `telegrarm_settings.php`.
2. Register hooks conditionally in `telegrarm.php`.
3. Add/update dedicated handler file(s).
4. Update docs (`README.md` and `readme.txt`).
5. Ensure uninstall behavior remains correct.

## Versioning and Release Notes

- Keep plugin version aligned in:
  - `telegrarm.php` plugin header
  - `BONO_TELEGRARM_VERSION` constant
  - `readme.txt` (`Stable tag` and changelog)

## Validation Checklist

- Settings page loads and saves correctly.
- Notifications send for enabled events only.
- Telegram bot token and channel IDs are used from options.
- No PHP warnings/notices in typical flows.
- Static analysis passes (`psalm`).

## CI/Automation

- Psalm workflow runs on push/PR to `main`.
- Release zip is built on `v*` tags.

## References

- `CLAUDE.md` contains additional project context.
- WordPress Plugin Handbook: https://developer.wordpress.org/plugins/
- Telegram Bot API: https://core.telegram.org/bots/api
