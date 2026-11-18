#!/usr/bin/env bash
#
# Builds the distributable Expression Lab plugin ZIP.
#
# Usage: bin/build-plugin.sh [--with-addons] [--no-sign] [VERSION]
#
#   --with-addons  Absorb the signed Professional Edition bundle from assets/addons/ (EXPRESSION_LAB_WITH_ADDONS=1).
#                  The addon version must equal the plugin version (1:1 contract); override the expected value
#                  with EXPRESSION_LAB_EXPECTED_ADDON_VERSION (CI pins it to the release tag).
#   --no-sign      CI build-job mode: produce build/expressionlab-<version>.zip + build/release-meta.env only.
#                  Refuses to run when EXPRESSION_LAB_SIGNING_KEY is present, because every build tool
#                  (pnpm, webpack, Composer, PHP-Scoper) could read it from /proc/<pid>/environ.
#                  Sign afterwards in an isolated job with bin/sign-release.sh.
#   (default)      Local mode: requires EXPRESSION_LAB_SIGNING_KEY and signs at the end via bin/sign-release.sh.
#
set -euo pipefail
set +x
umask 022

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

WITH_ADDONS="${EXPRESSION_LAB_WITH_ADDONS:-0}"
SIGN=1
POSITIONAL_ARGS=()
for arg in "$@"; do
	case "$arg" in
		--with-addons|--with-addon)
			WITH_ADDONS=1
			;;
		--no-sign)
			SIGN=0
			;;
		-*)
			echo "ERROR: Unknown option '$arg'. Usage: build-plugin.sh [--with-addons] [--no-sign] [VERSION]" >&2
			exit 1
			;;
		*)
			POSITIONAL_ARGS+=("$arg")
			;;
	esac
done

if [ "${#POSITIONAL_ARGS[@]}" -gt 1 ]; then
	echo "ERROR: Too many positional arguments. Usage: build-plugin.sh [--with-addons] [--no-sign] [VERSION]" >&2
	exit 1
fi

# Extract version from expressionlab.php or use argument if provided.
VERSION="${POSITIONAL_ARGS[0]:-$(grep -m1 "Version:" "$ROOT_DIR/expressionlab.php" | awk -F: '{print $2}' | tr -d ' \r\n')}"
if [ -z "$VERSION" ]; then
	echo "ERROR: Could not determine plugin version from expressionlab.php." >&2
	exit 1
fi

if ! echo "$VERSION" | grep -qE '^[0-9A-Za-z.+\-]+$'; then
	echo "ERROR: Invalid version string: '$VERSION'. Only alphanumeric, dots, hyphens, and plus signs are allowed." >&2
	exit 1
fi

if [ "$SIGN" = "1" ]; then
	SIGNING_KEY="${EXPRESSION_LAB_SIGNING_KEY:-}"
	if [ -z "$SIGNING_KEY" ]; then
		echo "ERROR: The EXPRESSION_LAB_SIGNING_KEY environment variable is not set or is empty (use --no-sign for an unsigned CI build)." >&2
		exit 1
	fi
	if ! [[ "$SIGNING_KEY" =~ ^[0-9a-fA-F]{128}$ ]]; then
		echo "ERROR: EXPRESSION_LAB_SIGNING_KEY must be exactly 128 hexadecimal characters (64-byte Ed25519 secret key)." >&2
		exit 1
	fi
else
	if [ -n "${EXPRESSION_LAB_SIGNING_KEY+x}" ]; then
		echo "ERROR: --no-sign builds must run WITHOUT EXPRESSION_LAB_SIGNING_KEY in the environment (it would be readable by every build tool). Sign in a separate job with bin/sign-release.sh." >&2
		exit 1
	fi
	SIGNING_KEY=""
fi
unset EXPRESSION_LAB_SIGNING_KEY

# Runs a build tool in a subshell without any signing material.
run_isolated() {
	(
		unset SIGNING_KEY EXPRESSION_LAB_SIGNING_KEY EXPRESSION_LAB_ADDON_SIGNING_KEY
		"$@"
	)
}

ZIP_NAME="expressionlab-${VERSION}.zip"
ZIP_FILE="$BUILD_DIR/$ZIP_NAME"
META_FILE="$BUILD_DIR/release-meta.env"

if [ "$WITH_ADDONS" = "1" ]; then
	if [ ! -f "$ROOT_DIR/assets/addons/addons.json" ] || [ ! -f "$ROOT_DIR/assets/addons/integrity.json" ] || [ ! -f "$ROOT_DIR/assets/addons/signature.json" ]; then
		echo "ERROR: --with-addons is enabled, but assets/addons/addons.json, integrity.json or signature.json is missing." >&2
		exit 1
	fi
	EXPECTED_ADDON_VERSION="${EXPRESSION_LAB_EXPECTED_ADDON_VERSION:-$VERSION}"
	if [ "$EXPECTED_ADDON_VERSION" != "$VERSION" ]; then
		echo "ERROR: 1:1 release contract violated: expected addon version '$EXPECTED_ADDON_VERSION' differs from plugin version '$VERSION'." >&2
		exit 1
	fi
	echo "Compiling frontend assets with Webpack Encore (with proprietary addons v$EXPECTED_ADDON_VERSION)..."
	run_isolated env EXPRESSION_LAB_WITH_ADDONS=1 EXPRESSION_LAB_EXPECTED_ADDON_VERSION="$EXPECTED_ADDON_VERSION" \
		pnpm --dir "$ROOT_DIR" run build
else
	echo "Compiling frontend assets with Webpack Encore..."
	run_isolated env -u EXPRESSION_LAB_WITH_ADDONS -u EXPRESSION_LAB_EXPECTED_ADDON_VERSION \
		pnpm --dir "$ROOT_DIR" run build
fi

echo "Preparing staging environment with production dependencies..."
rm -rf "$BUILD_DIR"
mkdir -p "$STAGING_DIR" "$BUILD_DIR/dist"

# Copy necessary files to install production dependencies
cp "$ROOT_DIR/composer.json" "$STAGING_DIR/"
cp "$ROOT_DIR/composer.lock" "$STAGING_DIR/"
cp -r "$ROOT_DIR/src" "$STAGING_DIR/src"
cp "$ROOT_DIR/expressionlab.php" "$STAGING_DIR/"
cp "$ROOT_DIR/scoper.inc.php" "$STAGING_DIR/"

# Install production dependencies only (no dev-tools, no Composer scripts or plugins)
run_isolated composer install \
	--working-dir="$STAGING_DIR" \
	--no-dev \
	--prefer-dist \
	--no-interaction \
	--no-scripts \
	--no-plugins \
	--optimize-autoloader

# Strip vendor test/doc/example trees (-prune avoids descending into directories that are being removed)
# and vendor development manifests that must not ship.
find "$STAGING_DIR/vendor" -type d \( -name "tests" -o -name "Tests" -o -name "test" -o -name "Test" -o -name "doc" -o -name "docs" -o -name "example" -o -name "examples" \) -prune -exec rm -rf {} +
find "$STAGING_DIR/vendor" -type f \( -name "phpunit.xml" -o -name "phpunit.xml.dist" -o -name ".phpcs.xml" -o -name ".phpcs.xml.dist" -o -name "phpcs.xml" -o -name "phpcs.xml.dist" -o -name "*.map" \) -delete

echo "Running PHP-Scoper on vendor dependencies..."
PHP_SCOPER_BIN="$ROOT_DIR/vendor-bin/php-scoper/vendor/bin/php-scoper"
if [ ! -f "$PHP_SCOPER_BIN" ]; then
	PHP_SCOPER_BIN="$ROOT_DIR/vendor/bin/php-scoper"
fi

if [ ! -f "$PHP_SCOPER_BIN" ]; then
	echo "ERROR: php-scoper binary not found in vendor-bin or vendor/bin." >&2
	exit 1
fi

run_isolated "$PHP_SCOPER_BIN" add-prefix \
	--working-dir="$STAGING_DIR" \
	--output-dir="$DIST_DIR" \
	--force

echo "Copying plugin source files and prefixing vendor references..."
cp "$ROOT_DIR/expressionlab.php" "$DIST_DIR/expressionlab.php"
cp -r "$ROOT_DIR/src" "$DIST_DIR/src"

if [ "$WITH_ADDONS" = "1" ]; then
	sed -i "s/define( 'EXPRESSION_LAB_ADDON_ENABLED', false );/define( 'EXPRESSION_LAB_ADDON_ENABLED', true );/" "$DIST_DIR/expressionlab.php"
	if ! grep -q "define( 'EXPRESSION_LAB_ADDON_ENABLED', true );" "$DIST_DIR/expressionlab.php"; then
		echo "ERROR: Failed to inject EXPRESSION_LAB_ADDON_ENABLED=true into expressionlab.php." >&2
		exit 1
	fi
fi

run_isolated php "$SCRIPT_DIR/build-prefix-vendor.php" "$DIST_DIR" "$DIST_DIR/src" "$DIST_DIR/expressionlab.php"

echo "Generating optimized production autoloader (classmap)..."

# Include vendor in classmap so Composer maps prefixed classes
sed -i -E 's#"src(\\)?/"\s*#"src/", "vendor/"#' "$DIST_DIR/composer.json"
if ! grep -qE '"vendor(\\)?/"' "$DIST_DIR/composer.json"; then
	echo "ERROR: Failed to inject vendor/ into composer.json classmap." >&2
	exit 1
fi
run_isolated composer dump-autoload \
	--working-dir="$DIST_DIR" \
	--classmap-authoritative \
	--no-dev \
	--no-scripts \
	--no-plugins \
	--no-interaction

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

if [ -e "$DIST_DIR/assets/addons" ]; then
	echo "ERROR: assets/addons/ must never be shipped inside the release package." >&2
	exit 1
fi

echo "Verifying compiled production artifact (smoke test + leak scan of the final tree)..."
run_isolated php "$SCRIPT_DIR/build-verify-artifact.php" "$DIST_DIR"

echo "Packaging final ZIP ($ZIP_NAME)..."
rm -f "$ZIP_FILE"
(
	cd "$BUILD_DIR/dist" && run_isolated zip -r -q -X "$ZIP_FILE" expressionlab
)

# Clean up intermediate staging directory
rm -rf "$STAGING_DIR"

ZIP_SHA256=$(sha256sum "$ZIP_FILE" | awk '{print $1}')
if ! echo "$ZIP_SHA256" | grep -qE '^[0-9a-f]{64}$'; then
	echo "ERROR: SHA-256 checksum computation failed or produced invalid output." >&2
	exit 1
fi

# Parse WordPress plugin header metadata dynamically.
REQUIRES_WP=$(grep -m1 "Requires at least:" "$ROOT_DIR/expressionlab.php" | awk -F: '{print $2}' | tr -d ' \r\n')
REQUIRES_PHP=$(grep -m1 "Requires PHP:" "$ROOT_DIR/expressionlab.php" | awk -F: '{print $2}' | tr -d ' \r\n')
TESTED_WP=$(grep -m1 "Tested up to:" "$ROOT_DIR/expressionlab.php" | awk -F: '{print $2}' | tr -d ' \r\n')
for header_value in "$REQUIRES_WP" "$REQUIRES_PHP" "$TESTED_WP"; do
	if ! [[ "$header_value" =~ ^[0-9]+(\.[0-9]+)*$ ]]; then
		echo "ERROR: Invalid plugin header version value '$header_value' in expressionlab.php." >&2
		exit 1
	fi
done

# Non-secret metadata consumed by bin/sign-release.sh (possibly in another CI job).
cat > "$META_FILE" <<EOF
VERSION=$VERSION
ZIP_NAME=$ZIP_NAME
ZIP_SHA256=$ZIP_SHA256
REQUIRES_WP=$REQUIRES_WP
TESTED_WP=$TESTED_WP
REQUIRES_PHP=$REQUIRES_PHP
WITH_ADDONS=$WITH_ADDONS
EOF

echo "Build completed successfully!"
echo "    Version:       $VERSION"
echo "    Generated ZIP: $ZIP_FILE"
echo "    SHA-256:       $ZIP_SHA256"
echo "    Size:          $(du -h "$ZIP_FILE" | cut -f1)"

if [ "$SIGN" = "1" ]; then
	# The key reaches only the signer process (environment, never argv).
	EXPRESSION_LAB_SIGNING_KEY="$SIGNING_KEY" bash "$SCRIPT_DIR/sign-release.sh" "$BUILD_DIR"
	SIGNING_KEY=""
	unset SIGNING_KEY
else
	echo "    Signature:     (unsigned; run bin/sign-release.sh in the isolated signing job)"
fi
