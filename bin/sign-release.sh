#!/usr/bin/env bash
#
# Signs a built release ZIP with the Core auto-update key and writes build/update.json + build/RELEASE_NOTES.md.
#
# Usage: EXPRESSION_LAB_SIGNING_KEY=<128 hex> bash bin/sign-release.sh [BUILD_DIR]
#
# Designed for the isolated CI signing job (no pnpm/Composer install, no build tools): the only processes
# that ever see the key are this shell (environment) and build-release-manifest.php (STDIN pipe).
# Inputs come from BUILD_DIR/release-meta.env (written by bin/build-plugin.sh), which is parsed - never sourced.
#
set -euo pipefail
set +x

# Drop inherited/exported shell functions that could shadow the builtins used below.
# In POSIX mode the special builtins `set` and `unset` take precedence over functions.
set -o posix
unset -f printf builtin command echo test read cut grep awk tr sha256sum php 2>/dev/null || true
set +o posix

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
BUILD_DIR="${1:-$ROOT_DIR/build}"
META_FILE="$BUILD_DIR/release-meta.env"

export EXPRESSION_LAB_BUILD=1

fail() {
	builtin printf 'ERROR: %s\n' "$1" >&2
	exit 1
}

[ -f "$META_FILE" ] || fail "Release metadata not found: $META_FILE (run bin/build-plugin.sh first)."

read_meta() {
	local key="$1" pattern="$2" value
	value="$(grep -m1 -E "^${key}=" "$META_FILE" | cut -d= -f2- | tr -d '\r')"
	[[ "$value" =~ $pattern ]] || fail "Invalid or missing $key in $META_FILE."
	builtin printf '%s' "$value"
}

VERSION="$(read_meta VERSION '^[0-9A-Za-z.+-]+$')"
ZIP_NAME="$(read_meta ZIP_NAME '^expressionlab-[0-9A-Za-z.+-]+\.zip$')"
ZIP_SHA256="$(read_meta ZIP_SHA256 '^[0-9a-f]{64}$')"
REQUIRES_WP="$(read_meta REQUIRES_WP '^[0-9]+(\.[0-9]+)*$')"
TESTED_WP="$(read_meta TESTED_WP '^[0-9]+(\.[0-9]+)*$')"
REQUIRES_PHP="$(read_meta REQUIRES_PHP '^[0-9]+(\.[0-9]+)*$')"

[ "$ZIP_NAME" = "expressionlab-${VERSION}.zip" ] || fail "ZIP name '$ZIP_NAME' does not match version '$VERSION'."
ZIP_FILE="$BUILD_DIR/$ZIP_NAME"
[ -f "$ZIP_FILE" ] || fail "Release ZIP not found: $ZIP_FILE"

ACTUAL_SHA256="$(sha256sum "$ZIP_FILE" | awk '{print $1}')"
[ "$ACTUAL_SHA256" = "$ZIP_SHA256" ] || fail "ZIP checksum does not match release-meta.env (artifact altered between build and sign?)."

SIGNING_KEY="${EXPRESSION_LAB_SIGNING_KEY:-}"
unset EXPRESSION_LAB_SIGNING_KEY
[ -n "$SIGNING_KEY" ] || fail "The EXPRESSION_LAB_SIGNING_KEY environment variable is not set or is empty."
[[ "$SIGNING_KEY" =~ ^[0-9a-fA-F]{128}$ ]] || fail "EXPRESSION_LAB_SIGNING_KEY must be exactly 128 hexadecimal characters (64-byte Ed25519 secret key)."

MANIFEST_FILE="$BUILD_DIR/update.json"
RELEASE_NOTES_FILE="$BUILD_DIR/RELEASE_NOTES.md"
rm -f "$MANIFEST_FILE" "$RELEASE_NOTES_FILE"

echo "Generating signed release manifest ($MANIFEST_FILE)..."
# `builtin printf` runs inside the forked pipeline subshell: no execve, so the key never reaches argv
# (/proc/<pid>/cmdline); PHP reads it from STDIN and zeroes it after use.
SIGN_RESULT="$(builtin printf '%s' "$SIGNING_KEY" | php "$SCRIPT_DIR/build-release-manifest.php" \
	"$MANIFEST_FILE" "$VERSION" "$ZIP_SHA256" "$ZIP_NAME" "$ROOT_DIR/CHANGELOG.md" \
	"$ZIP_FILE" "$RELEASE_NOTES_FILE" "$REQUIRES_WP" "$TESTED_WP" "$REQUIRES_PHP")"
SIGNING_KEY=""
unset SIGNING_KEY

[ "$SIGN_RESULT" = "yes" ] || fail "Failed to generate Ed25519 signature ($SIGN_RESULT)."

echo "Release signed successfully!"
echo "    Version:       $VERSION"
echo "    ZIP:           $ZIP_FILE"
echo "    SHA-256:       $ZIP_SHA256"
echo "    Ed25519 Sig:   Generated & injected into $(basename "$MANIFEST_FILE")"
