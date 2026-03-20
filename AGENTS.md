# AGENTS.md

Guidance for coding agents working in this repository.

## Project

- Name: `TelegrARM`
- Type: WordPress plugin (PHP)
- Purpose: Send Telegram notifications for selected ARMember events.

## Distribution Strategy

- Primary distribution channel: GitHub releases + Git Updater.
- Secondary distribution channel: WordPress.org plugin directory.
- Release tagging is explicit only. Do not reintroduce automatic tag creation on `main`.
- Supported release paths are:
  - local/manual via `./release.sh x.y.z`
  - manual GitHub Actions via the `Manual Release` workflow (`workflow_dispatch`)
- Preserve Git Updater metadata in `telegrarm.php` (`GitHub Plugin URI`, `Primary Branch`, `Release Asset`) unless the user explicitly asks to remove or replace it.
- Preserve Git Updater-facing admin/UI copy in `telegrarm_settings.php` and GitHub distribution guidance in `README.md`.
- Keep `readme.txt` valid for WordPress.org submission, but do not treat WordPress.org as the primary source of truth unless the user explicitly changes that direction.

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
4. Update docs (`README.md` for GitHub-primary distribution and `readme.txt` for WordPress.org compatibility).
5. Ensure uninstall behavior remains correct.

## Versioning and Release Notes

- Keep plugin version aligned in:
  - `telegrarm.php` plugin header
  - `BONO_TELEGRARM_VERSION` constant
  - `readme.txt` (`Stable tag`, `Version`, and changelog)
- When preparing releases, keep both distribution channels coherent:
  - GitHub release/update behavior should continue to work through Git Updater metadata and release assets.
  - WordPress.org submission metadata should remain valid in `readme.txt`.
- The latest published GitHub Release, not the latest tag alone, drives the README release badge.
- The `Manual Release` workflow must validate version metadata before publishing or updating a GitHub Release.

## Validation Checklist

- Settings page loads and saves correctly.
- Notifications send for enabled events only.
- Telegram bot token and channel IDs are used from options.
- No PHP warnings/notices in typical flows.
- Static analysis passes (`psalm`).
- GitHub Release publishing still works for both explicit release paths.
- Git Updater metadata and admin copy remain intact unless intentionally changed.

## CI/Automation

- Psalm workflow runs on push/PR to `main`.
- CodeQL is repo-managed through `.github/workflows/codeql.yml`, not GitHub default setup.
- Manual GitHub release publishing runs through `.github/workflows/update-stable-tag.yml` (`Manual Release`).
- Release zip is built on `v*` tags.

## References

- `CLAUDE.md` contains additional project context.
- WordPress Plugin Handbook: https://developer.wordpress.org/plugins/
- Telegram Bot API: https://core.telegram.org/bots/api
