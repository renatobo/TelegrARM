# TelegrARM - Claude AI Developer Guide

## Project Overview

TelegrARM is a WordPress plugin that bridges ARMember membership plugin with Telegram, enabling automated notifications for user events. This plugin serves as an extensible framework for Telegram-based notifications.

[AGENTS.md](AGENTS.md) holds the distribution strategy and the supported release paths. Keep the two files consistent when changing either.

## Architecture

### Core Components

- **[telegrarm.php](telegrarm.php)** - Main plugin file with conditional hook initialization
- **[telegrarm_settings.php](telegrarm_settings.php)** - WordPress admin settings interface
- **[includes/](includes/)** - Config, debug logger, message formatter, Telegram client, delivery queue, upgrader
- **[admin/telegrarm-field-discovery.php](admin/telegrarm-field-discovery.php)** - Cached ARMember field discovery for the mapping builder
- **[telegrarm_update_profile_external.php](telegrarm_update_profile_external.php)** - Profile update notification handler
- **[telegrarm_after_new_user_notification.php](telegrarm_after_new_user_notification.php)** - New user registration notification handler
- **[uninstall.php](uninstall.php)** - Plugin cleanup on uninstall

### Plugin Structure

```
TelegrARM/
├── telegrarm.php                               # Main plugin file
├── telegrarm_settings.php                      # Admin settings page
├── includes/class-telegrarm-*.php              # Config, logger, formatter, client, queue, upgrader
├── admin/telegrarm-field-discovery.php         # ARMember field discovery (cached, admin-only)
├── assets/                                     # admin.css, admin.js, SVGs
├── tests/ + stubs/                              # Pure unit tests, no WordPress
├── scripts/                                    # phpunit, psalm, validate-package wrappers
├── release-notes/<version>.md                  # Required per release
├── telegrarm_update_profile_external.php       # Profile update hook
├── telegrarm_after_new_user_notification.php   # New user hook
└── uninstall.php                               # Cleanup script
```

## Technical Requirements

- **WordPress:** 7.0+
- **PHP:** 8.0+ (tested up to PHP 8.5)
- **Dependencies:** ARMember plugin
- **External Services:** Telegram Bot API

## Local Development

No `composer.json` or `vendor/`. Tooling is phars in gitignored `.tools/`, run via wrappers that need `chmod +x` on first use:

- `./scripts/phpunit` - unit tests
- `./scripts/psalm` - static analysis (must report "No errors found")
- `phpcs --standard=phpcs.xml.dist` - not vendored; `composer global require wp-coding-standards/wpcs dealerdirect/phpcodesniffer-composer-installer` first
- `shellcheck build.sh release.sh scripts/*` - matches the shellcheck CI job
- `./build.sh && ./scripts/validate-package <zip>` - matches the package CI job

All five must pass before a release; CI runs exactly these.

## Key Design Patterns

### Conditional Hook Loading
The plugin uses `telegrarm_init_hooks_conditionally()` to dynamically load notification handlers based on settings, ensuring minimal overhead when features are disabled.

### Modular Notification System
Each notification type is implemented in a separate file and loaded only when enabled, making the codebase extensible for new notification types.

### Queue Payload Indirection
`TelegrARM_Delivery_Queue` stores each payload in a randomized, non-autoloaded option (`telegrarm_job_*`) and passes only an opaque ticket as the cron argument. Never pass the payload itself: WP-Cron args live in the autoloaded `cron` option, and payloads carry member PII (phone, names, message body).

Do not move payloads back to transients. With a persistent object cache a transient is volatile while the cron entry is DB-durable, so a `wp_cache_flush()` between enqueue and delivery loses the message silently. Options do not self-expire, hence the explicit `expires` field and the daily `telegrarm_cleanup_deliveries` GC event.

`schedule()` reuses the same ticket on the pacing and retry paths. `wp_schedule_single_event()` refuses a duplicate hook+args pair within ten minutes, so only discard a stored payload when the ticket was freshly minted; discarding on a reused ticket strands a live event.

Delivery still depends on WP-Cron, which only runs on visitor requests. `TICKET_TTL` is the loss horizon on a quiet site.

`process()` also accepts a legacy inline array (pre-1.0.1) and reads a legacy transient (pre-1.1.2).

## WordPress Integration Points

### ARMember Hooks
- `arm_update_profile_external` - Triggered when users update their profile
- `arm_after_new_user_notification` - Triggered when new users register

### WordPress Options
Plugin settings are stored in WordPress options table with the `telegrarm_` prefix:
- `telegrarm_profile_update` - Enable/disable profile update notifications
- `telegrarm_after_new_user_notification` - Enable/disable new user notifications
- `telegram_bot_api_token` - Telegram Bot API token
- Additional settings for channel IDs and field mappings

## Development Guidelines

### Adding New Notification Types

1. Create a new PHP file: `telegrarm_[event_name].php`
2. Implement the notification function
3. Add option check in `telegrarm_init_hooks_conditionally()`
4. Add settings UI in `telegrarm_settings.php`
5. Update documentation

### Test Harness
Tests are pure unit tests with no WordPress loaded. `tests/bootstrap.php` defines WP constants and loads `stubs/wordpress-stubs.php`. Any WordPress function newly used in runtime code needs a stub there, and any new WP constant (e.g. `HOUR_IN_SECONDS`) needs defining in the bootstrap. Stubs hold state in `$GLOBALS['telegrarm_test_*']`.

## Common Tasks

### Updating Version Number
Prefer `./release.sh x.y.z`, which rewrites all four references and verifies they match. When editing by hand:
1. Plugin header `Version:` in [telegrarm.php](telegrarm.php)
2. `BONO_TELEGRARM_VERSION` constant in [telegrarm.php](telegrarm.php)
3. `Stable tag` in [readme.txt](readme.txt)
4. `Version` in [readme.txt](readme.txt)

### Adding Settings
1. Register option in `telegrarm_settings.php`
2. Add settings field to admin page
3. Implement sanitization callback
4. Update uninstall script if needed

### Debugging
- Enable WordPress debug mode (`WP_DEBUG`, `WP_DEBUG_LOG`) and the plugin's opt-in `telegrarm_debug_logging` option; the logger is silent without it
- `TelegrARM_Debug_Logger::redact()` blanks any context key matching `/token|secret|authorization|password|phone|email|name|message|text/i`. A key called `name` or `text` logs as `[redacted]` with no error. Use `method`, `target`, `attempt`, `status_code`
- Log lines are the only queue diagnostic: `queued` / `sent` bracket a healthy delivery, and each drop path has its own distinct message

### Static Analysis Gotchas
- Psalm rejects top-level `global $wpdb;` (`InvalidGlobal`); PHPCS rejects `$wpdb = $GLOBALS['wpdb'];` (`WordPress.WP.GlobalVariablesOverride`). Wrap DB access in a function and use `global $wpdb;` inside it, as [uninstall.php](uninstall.php) does.
- `phpcs:ignore` justifications are read as claims. Verify the claim is true before writing it.

## Release Process

The plugin uses GitHub Actions for automated releases:
- write `release-notes/x.y.z.md` first, with the `## New Features`, `## Improvements`, and `## Bug Fixes` headings. `release.sh` and the release workflow both refuse to run without it
- `./build.sh` creates a versioned WordPress plugin ZIP from an explicit file allowlist
- `./release.sh x.y.z` verifies the notes file, refuses to run when non-release paths are dirty, syncs version metadata, commits the bump, and pushes the version tag
- pushing `v*` tags builds the ZIP, validates it, attaches it to the GitHub Release with its SHA-256 checksum and build provenance attestation, and uses the notes file as the release body
- the plugin header advertises `Primary Branch` and `Release Asset` for Git Updater compatibility
- `release.sh` aborts when any path outside its allowlist is dirty or untracked. Gitignore local scratch files rather than leaving them at the repo root (this is why there is no `.claude.local.md`)
- it `git add`s the paths *inside* that allowlist (`readme.txt`, `telegrarm.php`, `README.md`, `build.sh`, `release.sh`, `.gitignore`, `.github/workflows/package-plugin.yml`, `release-notes/`). Uncommitted work in those files lands in the "Bump version" commit
- run phpcs before tagging. It is the one gate not runnable out of the box, and CI runs after the tag is pushed, so a failure leaves a published tag with a red run
- `release.sh` does **not** update the `readme.txt` changelog or Upgrade Notice. Add both by hand before releasing; only version numbers are automated
- See [.github/workflows/](.github/workflows/) for automation details

## External Resources

- [Telegram Bot API Documentation](https://core.telegram.org/bots/api)
- [ARMember Documentation](https://www.armemberplugin.com/documentation/)
