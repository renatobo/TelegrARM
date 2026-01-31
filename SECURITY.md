# Security Policy

## Supported Versions

We release patches for security vulnerabilities in the following versions:

| Version | Supported          |
| ------- | ------------------ |
| 0.2.x   | :white_check_mark: |
| < 0.2.0 | :x:                |

## Reporting a Vulnerability

The TelegrARM team takes security issues seriously. We appreciate your efforts to responsibly disclose your findings.

### How to Report

**Please do not report security vulnerabilities through public GitHub issues.**

Instead, please report security vulnerabilities by:

1. **Email:** Send details to the repository owner via GitHub private messaging
2. **GitHub Security Advisories:** Use the [Security Advisories](https://github.com/renatobo/TelegrARM/security/advisories) feature (preferred method)

### What to Include

Please include the following information in your report:

- Description of the vulnerability
- Steps to reproduce the issue
- Potential impact of the vulnerability
- Suggested fix (if available)
- Your contact information for follow-up

### Response Timeline

- **Initial Response:** Within 48 hours of report submission
- **Status Update:** Within 7 days with assessment and planned actions
- **Fix Timeline:** Critical issues will be addressed within 30 days; other issues based on severity

### Disclosure Policy

- Security issues will be disclosed publicly only after a fix has been released
- We will credit reporters in the security advisory unless anonymity is requested
- We request that you do not publicly disclose the issue until we have addressed it

## Security Best Practices for Users

### Installation & Configuration

1. **Keep Updated:** Always use the latest version of the plugin
2. **Secure Bot Token:** Never share your Telegram bot token publicly
3. **Restrict Access:** Only grant admin access to trusted users
4. **HTTPS Only:** Use HTTPS for your WordPress installation
5. **Strong Passwords:** Use strong passwords for WordPress admin accounts

### Bot Token Security

- Store bot tokens securely in WordPress options (never in code)
- Regenerate bot tokens if exposed
- Use environment variables for tokens in development environments
- Never commit tokens to version control

### WordPress Security Hardening

1. Keep WordPress core, plugins, and themes updated
2. Use security plugins (e.g., Wordfence, Sucuri)
3. Implement proper file permissions
4. Enable WordPress security headers
5. Use SSL/TLS certificates
6. Regular backups of your WordPress installation

### Telegram Bot Security

1. **Verify Bot Ownership:** Ensure you control the bot before configuring
2. **Channel Permissions:** Only add bot to authorized channels/groups
3. **Monitor Activity:** Regularly review bot message logs
4. **Revoke Access:** Remove bot from channels when no longer needed

## Known Security Considerations

### Data Privacy

- User profile data is transmitted to Telegram servers
- Ensure compliance with privacy regulations (GDPR, CCPA, etc.)
- Inform users that their data may be sent to Telegram
- Only send necessary information in notifications

### Input Validation

The plugin sanitizes and validates:
- Admin settings input
- User profile data before sending to Telegram
- Bot token format
- Channel ID format

### Permission Checks

- Settings page requires `manage_options` capability
- All admin actions verify user permissions
- Nonces protect against CSRF attacks

### External API Security

- Telegram API calls use WordPress HTTP API with SSL verification
- API tokens are never exposed in client-side code
- Failed API calls are logged securely without exposing sensitive data

## Security Features

### Current Protections

- ✅ Input sanitization for all user-provided data
- ✅ Output escaping in admin interfaces
- ✅ Nonce verification for form submissions
- ✅ Capability checks for admin pages
- ✅ Secure option storage for sensitive data
- ✅ HTTPS-only API communications with Telegram
- ✅ Safe uninstall process with option cleanup

### Planned Enhancements

- Rate limiting for API calls
- Enhanced error logging with security context
- Webhook signature verification (if webhooks are added)

## Vulnerability Disclosure History

No security vulnerabilities have been reported or disclosed to date.

---

**Last Updated:** 2026-01-31

For general questions about this security policy, please open a public GitHub issue.
