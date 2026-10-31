# AGENTS.md

Guidance for coding agents working in this repository.

## Project

- Name: `TelegrARM`
- Type: WordPress plugin (PHP)
- Purpose: Send Telegram notifications for selected ARMember events.

## Distribution Strategy

- Primary distribution channel: GitHub releases + Git Updater.
- Secondary distribution channel: WordPress.org plugin directory.
- Publication is explicit: label a version PR `release` before merging into `main`.
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

- WordPress: `7.0+`
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
- The release workflow validates version metadata and refuses to overwrite existing releases.

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
- Release publishing runs through `.github/workflows/release.yml` after a labelled PR merges; tag pushes alone do not publish.

## References

- `CLAUDE.md` contains additional project context.
- WordPress Plugin Handbook: https://developer.wordpress.org/plugins/
- Telegram Bot API: https://core.telegram.org/bots/api

## GitHub releases after PR merge

The supported release path is `.github/workflows/release.yml`. Ordinary merges do not publish. The old tag/push/manual publishing workflows have been replaced; pushing a tag alone does not publish.

For the next release:

1. Bump `telegrarm.php` header `Version` and `BONO_TELEGRARM_VERSION` to matching `X.Y.Z`. Also update `readme.txt` Stable tag and any Version field. Update the user-facing changelog/upgrade notes under this repository’s conventions.
2. Add `release-notes/X.Y.Z.md` with exact headings `## New Features`, `## Improvements`, `## Bug Fixes`; use `- None.` if a section has no entries. Older releases without this notes format remain unchanged.
3. Open a PR into `main`, run all required checks, then apply **`release` before merging**. That label explicitly opts the merge into publication. Do not create/push the version tag yourself or use the retired direct-tagging `release.sh` path.
4. Merge. The workflow verifies the canonical merged PR and exact merge commit, runs all existing reusable quality gates plus release/package guards, checks versions/notes and main ancestry, creates annotated `vX.Y.Z`, and publishes the **exact CI-tested ZIP** with SHA-256 checksum. Build provenance attestation is retained. The release is created as a draft and published only after asset upload succeeds.
5. Read back the published release and uploaded checksum; a green PR is not evidence that publication completed. Expected ZIP: `TelegrARM-v{version}.zip` under root `TelegrARM/`.

If publication fails, manually dispatch **Release merged PR** from `main` with `pr_number`. The PR must be merged into `main` and still carry `release`. Adding the label after merge does not trigger publication, but manual dispatch can release that eligible PR. Matching existing tags may be reused only when they point to the same merge commit. Existing releases/drafts are never overwritten: inspect and explicitly resolve partial drafts before retrying; never move/delete published tags as a retry strategy. Concurrent runs for the same PR are serialized.

Tags and publication happen in one workflow, avoiding the `GITHUB_TOKEN` tag-event suppression. No PAT or additional secret is required. Only publishing has `contents: write` plus OIDC/attestation permissions; quality jobs retain their own minimum analysis permissions.

Release configuration is `.github/release.json`; guard code is `.github/scripts/release.py`; tests run with `python3 -B -m unittest discover -s .github/tests -v`. Package locally with `bash build.sh`, then run `python3 -B .github/scripts/release.py package`. Release ZIPs exclude this tooling and agent guides. Do not hand-edit the upload/download handoff to rebuild a different artifact in the publishing job.

For workflow-only maintenance, keep the plugin version unchanged and omit the `release` label. Merge setup/automation PRs unlabelled; the next labelled version PR uses the new flow.
