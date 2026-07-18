# WordPress Plugin Security Assessment

## Executive Summary

- Scope reviewed: plugin bootstrap, settings registration and admin UI, both authenticated AJAX actions, ARMember discovery queries, Telegram request and response handling, both event handlers, uninstall behavior, static-analysis configuration, packaging, release workflows, and security documentation.
- Overall risk after 1.0.0 remediation: **Low**. No critical, high, or clearly exploitable medium-severity vulnerability was identified. All five original low-severity findings are remediated in the 1.0.0 implementation.
- Open finding counts: Critical 0, High 0, Medium 0, Low 0. Remediated: 5.
- Validation: PHP 8.5.8 lint passed for all runtime PHP files, Psalm 6.16.1 reported no configured errors, and the PHPUnit security/formatter suite passes. Runtime verification inside a real WordPress plus ARMember installation remains required before production deployment.

## 1.0.0 Remediation Status

- WPSEC-001 resolved: the existing token is migrated to non-autoloaded storage, is no longer redisplayed, and can be supplied through `TELEGRARM_BOT_TOKEN` or `telegrarm_bot_token`.
- WPSEC-002 resolved: admin diagnostics return only allowlisted response details and no raw Telegram response body.
- WPSEC-003 resolved: redundant deserialization of `user_login` and the already-decoded option value was removed from runtime paths.
- WPSEC-004 resolved: sensitive-name filtering now excludes token, secret, credential, authentication, recovery, password, private-key, and API-key patterns; regression coverage protects these defaults.
- WPSEC-005 resolved: mapping membership is checked before Instagram, avatar, or ordinary field formatting; regression tests cover both special fields.

## Critical

No critical findings identified.

## High

No high findings identified.

## Medium

No clearly exploitable medium findings identified.

## Remediated Low Findings

### WPSEC-001 Telegram bot token is a plaintext, normally autoloaded WordPress option (Resolved in 1.0.0)

- File: `telegrarm_settings.php:41-49`, `telegrarm_settings.php:1310`
- Impact: A database dump, object-cache disclosure, privileged plugin access, or same-origin admin compromise can expose a credential that can post as the configured Telegram bot. Normal WordPress option storage does not encrypt secrets, and registering the option does not opt it out of autoloading.
- Evidence: `telegram_bot_api_token` is registered as an ordinary string option and its current value is rendered into the administrator settings form. This is common in WordPress plugins and is not an independent authentication bypass, but it increases the credential's exposure surface.
- Remediation: Document the storage model accurately; provide a constant/filter-based secret override for production; prevent the token option from autoloading during activation/migration where supported; avoid redisplaying an existing token unless replacement is requested; and never include it in logs, notices, diagnostics, or release artifacts.

### WPSEC-002 Admin test feedback returns the raw Telegram response body (Resolved in 1.0.0)

- File: `telegrarm_settings.php:262-290`, `telegrarm_settings.php:387-425`, `telegrarm_settings.php:480-554`
- Impact: Telegram response content and metadata are copied into the WordPress admin page. Access is restricted to administrators and the body is normalized and displayed through `textContent`, so no XSS path was found, but the extra data can leak into screenshots, support tickets, browser extensions, or shared admin sessions.
- Evidence: The test-message response is decoded, sanitized, capped at 2,000 characters, labeled `Raw response body`, returned over AJAX, and rendered in the settings page. Telegram bot tokens are redacted when their JSON key identifies them, but arbitrary response fields are retained.
- Remediation: Default to status code, Telegram error code, and description only. Put raw response diagnostics behind an explicit debug mode, apply a strict field allowlist, and label the privacy implications.

### WPSEC-003 Double deserialization expands object-injection exposure unnecessarily (Resolved in 1.0.0)

- File: `telegrarm_after_new_user_notification.php:171`, `telegrarm_settings.php:766-767`, `telegrarm_settings.php:847`
- Impact: If another compromised or vulnerable component can place a crafted serialized object string into one of these values and a useful gadget chain exists, `maybe_unserialize()` may instantiate objects. Exploitation was not established: the values are database- or ARMember-derived, WordPress normally already unserializes option values, and the user-login character rules strongly constrain input.
- Evidence: The handler calls `maybe_unserialize()` on `user_login`; the discovery code calls it again on `get_option('arm_preset_form_fields')`; and ARMember form-field records are deserialized without an allowed-class restriction.
- Remediation: Remove deserialization from scalar `user_login`; do not re-deserialize values returned by `get_option()`; prefer ARMember's documented decoded API; and, when direct legacy serialized data must be decoded, reject object payloads or use a decoder with `allowed_classes => false` after confirming compatibility.

### WPSEC-004 Sensitive-field exclusion is an incomplete denylist (Resolved in 1.0.0)

- File: `telegrarm_settings.php:585-634`, `telegrarm_settings.php:881-944`
- Impact: An administrator can be offered a custom meta key containing credentials, recovery data, or other private values when ARMember registry discovery falls back to scanning usermeta. Sending still requires the administrator to select and save the key, so this is a privacy and secure-default gap rather than unauthorized disclosure.
- Evidence: Discovery excludes a small exact list plus leading-underscore fields, but custom keys such as `api_token`, `access_token`, `secret`, or site-specific sensitive identifiers are not classified. The resulting list is exposed only through a capability- and nonce-protected AJAX action.
- Remediation: Prefer an allowlist derived from active ARMember form definitions; broaden sensitive-name and sensitive-field-type filtering; clearly warn that selected values leave the site for Telegram; and add tests proving credentials and WordPress authentication metadata never become selectable.

### WPSEC-005 Two special profile fields bypass the configured mapping allowlist (Resolved in 1.0.0)

- File: `telegrarm_after_new_user_notification.php:45-73`, `telegrarm_update_profile_external.php:79-107`
- Impact: Instagram and avatar values can be sent to Telegram even when the administrator omitted those keys from the configured field mapping. This violates the documented mapping-as-whitelist expectation and can cause unintended personal-data disclosure to the configured Telegram destination.
- Evidence: Both line builders handle `arm_social_field_instagram` and `avatar` before checking `isset($map[$key])`; the mapping gate therefore never applies to those special branches.
- Remediation: Require mapping membership before every field-specific formatter, then apply Instagram/avatar formatting only to allowed keys. Add regression tests proving that every unmapped key, including both special keys, is excluded.

## Notes

- Both AJAX endpoints check `manage_options` before doing work and verify action-specific nonces.
- The Settings API supplies CSRF protection for the main options form, and every registered setting has a sanitization callback.
- The ARMember table name is interpolated into SQL, but it comes from the trusted ARMember runtime object or the WordPress table prefix rather than request input. No attacker-controlled SQL path was established.
- Telegram HTML messages escape user-controlled text; the Instagram username is reduced to a narrow character allowlist and avatar URLs are validated.
- There are no unauthenticated AJAX actions, REST routes, upload paths, custom sessions, filesystem write endpoints, shell calls, or direct database writes in plugin runtime code.
- Runtime behavior was not exercised inside a real WordPress plus ARMember installation, and no dynamic penetration test was performed. Plugin interoperability, option autoload state on existing installations, hook payload shapes, and Telegram edge responses remain residual verification gaps.
