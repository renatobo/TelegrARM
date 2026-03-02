# TelegrARM UI Notes

## Settings Header

- Use the banner image at `assets/telegrarm-settings-banner.svg` at the top of the settings page.
- Render the banner near its authored width instead of stretching it across the full admin container.
- Keep the compact metadata row below the banner with:
  - `Plugin Repository`
  - current plugin version
  - author GitHub link
  - single-button link: `Updates via Git Updater`

## Settings Intro Copy

- Keep the page title `TelegrARM Settings`.
- Keep the intro copy focused on:
  - enabling or disabling ARMember event notifications
  - choosing Telegram channels or chats per event
  - managing the ARMember field mapping used in Telegram messages
- Keep the secondary intro sentence explaining that hooks are loaded only for enabled events.

## Tabs

- Use native WordPress tab markup:
  - `nav-tab-wrapper`
  - `nav-tab`
  - `nav-tab-active`
- Tabs are in-page panels, not separate admin pages.
- Keep this order:
  - `Bot setup`
  - `New user`
  - `Profile updates`
  - `ARMember mapping`
  - `Telegram setup`
- Switching tabs should:
  - show only the active panel
  - hide inactive panels with the `hidden` attribute
  - update the URL hash
  - restore the active tab from the URL hash on load

## Panel Layout

- Keep the layout WordPress-admin friendly, not app-like.
- Prefer flat cards, subtle borders, and native admin spacing.
- Use a bordered accent card for the primary toggle or credential control in each tab.
- Keep the save button in a footer row below the panels.

## Maintenance

- Keep these version references synchronized when releasing:
  - `telegrarm.php` plugin header `Version`
  - `telegrarm.php` constant `BONO_TELEGRARM_VERSION`
  - `readme.txt` `Stable tag`
- Keep the Git Updater metadata in the plugin header aligned with the release automation:
  - `GitHub Plugin URI`
  - `Primary Branch`
  - `Release Asset`
- When the header or tab layout changes, update this file in the same change.
