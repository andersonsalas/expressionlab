#!/usr/bin/env bash

set -euo pipefail
set +x

cleanup() {
	local exit_code=$?
	unset SIGNING_KEY 2>/dev/null || true
	if [ -d "${STAGING_DIR:-}" ]; then
		rm -rf "$STAGING_DIR"
	fi
	exit "$exit_code"
}
trap cleanup EXIT

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

export EXPRESSION_LAB_BUILD=1

BUILD_DIR="$ROOT_DIR/build"
STAGING_DIR="$BUILD_DIR/staging"
DIST_DIR="$BUILD_DIR/dist/expressionlab"

# Extract version from expressionlab.php or use argument if provided.
VERSION="${1:-$(grep -m1 "Version:" "$ROOT_DIR/expressionlab.php" | awk -F: '{print $2}' | tr -d ' \r\n')}"
if [ -z "$VERSION" ]; then
    echo "ERROR: Could not determine plugin version from expressionlab.php." >&2
    exit 1
fi

if ! echo "$VERSION" | grep -qE '^[0-9A-Za-z.+\-]+$'; then
    echo "ERROR: Invalid version string: '$VERSION'. Only alphanumeric, dots, hyphens, and plus signs are allowed." >&2
    exit 1
fi

SIGNING_KEY="${EXPRESSION_LAB_SIGNING_KEY:-}"
if [ -z "$SIGNING_KEY" ]; then
    echo "ERROR: The EXPRESSION_LAB_SIGNING_KEY environment variable is not set or is empty." >&2
    exit 1
fi

if ! echo "$SIGNING_KEY" | grep -qE '^[0-9a-fA-F]{128}$'; then
    echo "ERROR: EXPRESSION_LAB_SIGNING_KEY must be exactly 128 hexadecimal characters (64-byte Ed25519 secret key)." >&2
    exit 1
fi

unset EXPRESSION_LAB_SIGNING_KEY

ZIP_NAME="expressionlab-${VERSION}.zip"
ZIP_FILE="$BUILD_DIR/$ZIP_NAME"
MANIFEST_FILE="$BUILD_DIR/update.json"

echo "Compiling frontend assets with Webpack Encore..."
(
	unset EXPRESSION_LAB_SIGNING_KEY
	unset SIGNING_KEY
	pnpm --dir "$ROOT_DIR" run build
)

echo "Preparing staging environment with production dependencies..."
rm -rf "$BUILD_DIR"
mkdir -p "$STAGING_DIR" "$BUILD_DIR/dist"

# Copy necessary files to install production dependencies
cp "$ROOT_DIR/composer.json" "$STAGING_DIR/"
cp "$ROOT_DIR/composer.lock" "$STAGING_DIR/"
cp -r "$ROOT_DIR/src" "$STAGING_DIR/src"
cp "$ROOT_DIR/expressionlab.php" "$STAGING_DIR/"
cp "$ROOT_DIR/scoper.inc.php" "$STAGING_DIR/"

# Install production dependencies only (no dev-tools)
(
	unset EXPRESSION_LAB_SIGNING_KEY
	unset SIGNING_KEY
	composer install \
		--working-dir="$STAGING_DIR" \
		--no-dev \
		--prefer-dist \
		--no-interaction \
		--optimize-autoloader
)

find "$STAGING_DIR/vendor" -type d \( -name "tests" -o -name "Tests" -o -name "test" -o -name "Test" -o -name "doc" -o -name "docs" -o -name "example" -o -name "examples" \) -exec rm -rf {} + 2>/dev/null || true

echo "Running PHP-Scoper on vendor dependencies..."
PHP_SCOPER_BIN="$ROOT_DIR/vendor-bin/php-scoper/vendor/bin/php-scoper"
if [ ! -f "$PHP_SCOPER_BIN" ]; then
    PHP_SCOPER_BIN="$ROOT_DIR/vendor/bin/php-scoper"
fi

if [ ! -f "$PHP_SCOPER_BIN" ]; then
    echo "ERROR: php-scoper binary not found in vendor-bin or vendor/bin." >&2
    exit 1
fi

(
	unset EXPRESSION_LAB_SIGNING_KEY
	unset SIGNING_KEY
	"$PHP_SCOPER_BIN" add-prefix \
		--working-dir="$STAGING_DIR" \
		--output-dir="$DIST_DIR" \
		--force
)

echo "Copying plugin source files and prefixing vendor references..."
cp "$ROOT_DIR/expressionlab.php" "$DIST_DIR/expressionlab.php"
cp -r "$ROOT_DIR/src" "$DIST_DIR/src"

(
	unset EXPRESSION_LAB_SIGNING_KEY
	unset SIGNING_KEY
	php "$SCRIPT_DIR/build-prefix-vendor.php" "$DIST_DIR" "$DIST_DIR/src" "$DIST_DIR/expressionlab.php"
)

echo "Generating optimized production autoloader (classmap)..."

# Include vendor in classmap so Composer maps prefixed classes
sed -i -E 's#"src(\\)?/"\s*#"src/", "vendor/"#' "$DIST_DIR/composer.json"
if ! grep -qE '"vendor(\\)?/"' "$DIST_DIR/composer.json"; then
    echo "ERROR: Failed to inject vendor/ into composer.json classmap." >&2
    exit 1
fi
(
	unset EXPRESSION_LAB_SIGNING_KEY
	unset SIGNING_KEY
	composer dump-autoload \
		--working-dir="$DIST_DIR" \
		--classmap-authoritative \
		--no-dev \
		--no-interaction
)

echo "Verifying compiled production artifact integrity..."
(
	unset EXPRESSION_LAB_SIGNING_KEY
	unset SIGNING_KEY
	php "$SCRIPT_DIR/build-verify-artifact.php" "$DIST_DIR"
)

echo "Copying compiled assets and static files..."
mkdir -p "$DIST_DIR/assets"
cp -r "$ROOT_DIR/assets/build" "$DIST_DIR/assets/build"

if [ -d "$ROOT_DIR/assets/fonts" ]; then
    cp -r "$ROOT_DIR/assets/fonts" "$DIST_DIR/assets/fonts"
fi

if [ -d "$ROOT_DIR/assets/img" ]; then
    cp -r "$ROOT_DIR/assets/img" "$DIST_DIR/assets/img"
    # Exclude promotional/heavy demo files from production zip
    rm -f "$DIST_DIR/assets/img/console.gif"
fi

if [ -f "$ROOT_DIR/assets/integrity.json" ]; then
    cp "$ROOT_DIR/assets/integrity.json" "$DIST_DIR/assets/integrity.json"
fi

if [ -d "$ROOT_DIR/languages" ]; then
    cp -r "$ROOT_DIR/languages" "$DIST_DIR/languages"
fi

for file in LICENSE README.md CHANGELOG.md index.html; do
    if [ -f "$ROOT_DIR/$file" ]; then
        cp "$ROOT_DIR/$file" "$DIST_DIR/"
    fi
done

# Remove development definition files from the final package
rm -f "$DIST_DIR/composer.json" "$DIST_DIR/composer.lock"

echo "Packaging final ZIP ($ZIP_NAME)..."
rm -f "$ZIP_FILE"
(
	unset EXPRESSION_LAB_SIGNING_KEY
	unset SIGNING_KEY
	cd "$BUILD_DIR/dist" && zip -r -q "$ZIP_FILE" expressionlab
)

# Clean up intermediate staging directory
rm -rf "$STAGING_DIR"

ZIP_SHA256=$(sha256sum "$ZIP_FILE" | awk '{print $1}')
if ! echo "$ZIP_SHA256" | grep -qE '^[0-9a-f]{64}$'; then
    echo "ERROR: SHA-256 checksum computation failed or produced invalid output." >&2
    exit 1
fi
CHANGELOG_FILE="$ROOT_DIR/CHANGELOG.md"
RELEASE_NOTES_FILE="$BUILD_DIR/RELEASE_NOTES.md"

# Parse WordPress plugin header metadata dynamically.
REQUIRES_WP=$(grep -m1 "Requires at least:" "$ROOT_DIR/expressionlab.php" | awk -F: '{print $2}' | tr -d ' \r\n')
REQUIRES_PHP=$(grep -m1 "Requires PHP:" "$ROOT_DIR/expressionlab.php" | awk -F: '{print $2}' | tr -d ' \r\n')
TESTED_WP=$(grep -m1 "Tested up to:" "$ROOT_DIR/expressionlab.php" | awk -F: '{print $2}' | tr -d ' \r\n')

echo "Generating release manifest ($MANIFEST_FILE)..."

SIGN_RESULT=$(printf '%s' "$SIGNING_KEY" | php "$SCRIPT_DIR/build-release-manifest.php" \
    "$MANIFEST_FILE" "$VERSION" "$ZIP_SHA256" "$ZIP_NAME" "$CHANGELOG_FILE" \
    "$ZIP_FILE" "$RELEASE_NOTES_FILE" "$REQUIRES_WP" "$TESTED_WP" "$REQUIRES_PHP")

unset SIGNING_KEY

echo "Build completed successfully!"
echo "    Version:       $VERSION"
echo "    Generated ZIP: $ZIP_FILE"
echo "    SHA-256:       $ZIP_SHA256"
if [ "$SIGN_RESULT" = "yes" ]; then
    echo "    Ed25519 Sig:   Generated & injected successfully"
else
    echo "ERROR: Failed to generate Ed25519 signature ($SIGN_RESULT)." >&2
    exit 1
fi
echo "    Size:          $(du -h "$ZIP_FILE" | cut -f1)"
