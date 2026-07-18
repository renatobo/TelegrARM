#!/bin/bash

set -euo pipefail

PLUGIN_SLUG="$(basename "$PWD")"
PLUGIN_FILE="telegrarm.php"

if [[ ! -f "$PLUGIN_FILE" ]]; then
  echo "Expected plugin bootstrap file '$PLUGIN_FILE' in $PWD"
  exit 1
fi

VERSION="$(
  sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//p' "$PLUGIN_FILE" | head -n 1
)"

if [[ -z "$VERSION" ]]; then
  echo "Could not determine plugin version from $PLUGIN_FILE"
  exit 1
fi

OUTPUT_NAME="${PLUGIN_SLUG}-v${VERSION}.zip"
OUTPUT_PATH="$PWD/$OUTPUT_NAME"
STAGING_DIR="$(mktemp -d)"
PACKAGE_DIR="$STAGING_DIR/$PLUGIN_SLUG"
PACKAGE_PATHS=(
  "README.md"
  "SECURITY.md"
  "UPGRADE.md"
  "readme.txt"
  "LICENSE"
  "telegrarm.php"
  "telegrarm_settings.php"
  "telegrarm_after_new_user_notification.php"
  "telegrarm_update_profile_external.php"
  "uninstall.php"
  "admin/telegrarm-field-discovery.php"
  "includes/class-telegrarm-config.php"
  "includes/class-telegrarm-debug-logger.php"
  "includes/class-telegrarm-delivery-queue.php"
  "includes/class-telegrarm-message-formatter.php"
  "includes/class-telegrarm-telegram-client.php"
  "includes/class-telegrarm-upgrader.php"
  "assets/icon.svg"
  "assets/admin.css"
  "assets/admin.js"
  "assets/telegrarm-settings-banner.svg"
)

cleanup() {
  rm -rf "$STAGING_DIR"
}

trap cleanup EXIT

if [[ -f "$OUTPUT_PATH" ]]; then
  rm -f "$OUTPUT_PATH"
fi

mkdir -p "$PACKAGE_DIR"

for relative_path in "${PACKAGE_PATHS[@]}"; do
  if [[ ! -e "$relative_path" ]]; then
    echo "Missing package path: $relative_path"
    exit 1
  fi

  destination_dir="$PACKAGE_DIR/$(dirname "$relative_path")"
  mkdir -p "$destination_dir"
  cp -pR "$relative_path" "$destination_dir/"
done

(
  cd "$STAGING_DIR"
  zip -rq "$OUTPUT_PATH" "$PLUGIN_SLUG"
)

echo "Created $OUTPUT_PATH"
