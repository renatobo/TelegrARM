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

OUTPUT_NAME="${PLUGIN_SLUG}-${VERSION}.zip"
OUTPUT_PATH="$PWD/$OUTPUT_NAME"
STAGING_DIR="$(mktemp -d)"
PACKAGE_DIR="$STAGING_DIR/$PLUGIN_SLUG"

cleanup() {
  rm -rf "$STAGING_DIR"
}

trap cleanup EXIT

mkdir -p "$PACKAGE_DIR"

rsync -a \
  --exclude '.git/' \
  --exclude '.github/' \
  --exclude '.DS_Store' \
  --exclude '*.zip' \
  --exclude '.gitignore' \
  --exclude 'build.sh' \
  --exclude 'release.sh' \
  --exclude 'ui.md' \
  ./ "$PACKAGE_DIR/"

(
  cd "$STAGING_DIR"
  zip -rq "$OUTPUT_PATH" "$PLUGIN_SLUG"
)

echo "Created $OUTPUT_PATH"
