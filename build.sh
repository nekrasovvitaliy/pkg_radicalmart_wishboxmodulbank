#!/usr/bin/env bash

set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_SOURCE_DIR="$PROJECT_DIR/plg_radicalmart_payment_wishboxmodulbank"
PLUGIN_MANIFEST_PATH="$PLUGIN_SOURCE_DIR/wishboxmodulbank.xml"
PLUGIN_SCRIPT_PATH="$PLUGIN_SOURCE_DIR/script.php"
PACKAGE_MANIFEST_PATH="$PROJECT_DIR/pkg_radicalmart_wishboxmodulbank.xml"
PACKAGE_SCRIPT_PATH="$PROJECT_DIR/script.php"
PACKAGE_LANGUAGE_DIR="$PROJECT_DIR/language"

PLUGIN_BASENAME="plg_radicalmart_payment_wishboxmodulbank"
PACKAGE_BASENAME="pkg_radicalmart_wishboxmodulbank"
BUILD_DIR="$(mktemp -d "${TMPDIR:-/tmp}/${PACKAGE_BASENAME}-build.XXXXXX")"
PLUGIN_STAGE_DIR="$BUILD_DIR/plugin"
PACKAGE_STAGE_DIR="$BUILD_DIR/package"

GREEN='\033[0;32m'
RED='\033[0;31m'
RESET='\033[0m'

cleanup() {
    rm -rf -- "$BUILD_DIR"
}

fail() {
    printf '%bERROR:%b %s\n' "$RED" "$RESET" "$1" >&2
    exit 1
}

manifest_version() {
    sed -n 's/.*<version>[[:space:]]*\([^<][^<]*\)[[:space:]]*<\/version>.*/\1/p' "$1" \
        | head -n 1
}

clean_metadata() {
    find "$1" -name '.DS_Store' -delete
    find "$1" -name '._*' -delete

    if command -v dot_clean >/dev/null 2>&1; then
        dot_clean "$1" 2>/dev/null || true
    fi

    if command -v xattr >/dev/null 2>&1; then
        xattr -rc "$1" 2>/dev/null || true
    fi
}

trap cleanup EXIT

[[ -d "$PLUGIN_SOURCE_DIR" ]] || fail "Plugin source directory not found: $PLUGIN_SOURCE_DIR"
[[ -f "$PLUGIN_MANIFEST_PATH" ]] || fail "Joomla plugin manifest not found: $PLUGIN_MANIFEST_PATH"
[[ -f "$PLUGIN_SCRIPT_PATH" ]] || fail "Joomla plugin installer script not found: $PLUGIN_SCRIPT_PATH"
[[ -f "$PACKAGE_MANIFEST_PATH" ]] || fail "Joomla package manifest not found: $PACKAGE_MANIFEST_PATH"
[[ -f "$PACKAGE_SCRIPT_PATH" ]] || fail "Joomla package installer script not found: $PACKAGE_SCRIPT_PATH"
[[ -d "$PACKAGE_LANGUAGE_DIR" ]] || fail "Package language directory not found: $PACKAGE_LANGUAGE_DIR"

for pluginDirectory in forms language services src; do
    [[ -d "$PLUGIN_SOURCE_DIR/$pluginDirectory" ]] \
        || fail "Plugin directory not found: $PLUGIN_SOURCE_DIR/$pluginDirectory"
done

command -v zip >/dev/null 2>&1 || fail "The zip command is required"

PACKAGE_VERSION="$(manifest_version "$PACKAGE_MANIFEST_PATH")"
[[ -n "$PACKAGE_VERSION" ]] || fail "Package version not found in $PACKAGE_MANIFEST_PATH"

VERSIONED_ARCHIVE_PATH="$PROJECT_DIR/${PACKAGE_BASENAME}_${PACKAGE_VERSION}.zip"

# Build the Joomla plugin archive that will be embedded in the package.
mkdir -p "$PLUGIN_STAGE_DIR" "$PACKAGE_STAGE_DIR"
cp -a -- "$PLUGIN_SOURCE_DIR/forms" "$PLUGIN_STAGE_DIR/"
cp -a -- "$PLUGIN_SOURCE_DIR/language" "$PLUGIN_STAGE_DIR/"
cp -a -- "$PLUGIN_SOURCE_DIR/services" "$PLUGIN_STAGE_DIR/"
cp -a -- "$PLUGIN_SOURCE_DIR/src" "$PLUGIN_STAGE_DIR/"
cp -a -- "$PLUGIN_MANIFEST_PATH" "$PLUGIN_STAGE_DIR/"
cp -a -- "$PLUGIN_SCRIPT_PATH" "$PLUGIN_STAGE_DIR/"

clean_metadata "$PLUGIN_STAGE_DIR"

(
    cd "$PLUGIN_STAGE_DIR"
    COPYFILE_DISABLE=1 zip -X -q -r "$PACKAGE_STAGE_DIR/${PLUGIN_BASENAME}.zip" .
)

# Build the installable Joomla package around the plugin archive.
cp -a -- "$PACKAGE_MANIFEST_PATH" "$PACKAGE_STAGE_DIR/"
cp -a -- "$PACKAGE_SCRIPT_PATH" "$PACKAGE_STAGE_DIR/"
cp -a -- "$PACKAGE_LANGUAGE_DIR" "$PACKAGE_STAGE_DIR/"

clean_metadata "$PACKAGE_STAGE_DIR"
rm -f -- "$VERSIONED_ARCHIVE_PATH"

(
    cd "$PACKAGE_STAGE_DIR"
    COPYFILE_DISABLE=1 zip -X -q -r "$VERSIONED_ARCHIVE_PATH" .
)

if command -v shasum >/dev/null 2>&1; then
    SHA256="$(shasum -a 256 "$VERSIONED_ARCHIVE_PATH" | awk '{print $1}')"
elif command -v openssl >/dev/null 2>&1; then
    SHA256="$(openssl dgst -sha256 "$VERSIONED_ARCHIVE_PATH" | sed 's/^.*= //')"
else
    SHA256="unavailable"
fi

printf '%bOK:%b package -> %s\n' "$GREEN" "$RESET" "$VERSIONED_ARCHIVE_PATH"
printf '%bOK:%b sha256 -> %s\n' "$GREEN" "$RESET" "$SHA256"
