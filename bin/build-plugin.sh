#!/usr/bin/env bash

set -euo pipefail
set +x

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

BUILD_DIR="$ROOT_DIR/build"
STAGING_DIR="$BUILD_DIR/staging"
DIST_DIR="$BUILD_DIR/dist/expressionlab"
# Extract version from expressionlab.php or use argument if provided.
VERSION="${1:-$(grep -m1 "Version:" "$ROOT_DIR/expressionlab.php" | awk -F: '{print $2}' | tr -d ' \r\n')}"
if [ -z "$VERSION" ]; then
    echo "ERROR: Could not determine plugin version from expressionlab.php." >&2
    exit 1
fi

SIGNING_KEY="${EXPRESSION_LAB_SIGNING_KEY:-}"
if [ -z "$SIGNING_KEY" ]; then
    echo "ERROR: The EXPRESSION_LAB_SIGNING_KEY environment variable is not set or is empty." >&2
    exit 1
fi

# Validate signing key format.
if ! echo "$SIGNING_KEY" | grep -qE '^[0-9a-fA-F]{128}$'; then
    echo "ERROR: EXPRESSION_LAB_SIGNING_KEY must be exactly 128 hexadecimal characters (64-byte Ed25519 secret key)." >&2
    exit 1
fi

ZIP_NAME="expressionlab-${VERSION}.zip"
ZIP_FILE="$BUILD_DIR/$ZIP_NAME"
MANIFEST_FILE="$BUILD_DIR/update.json"

echo "Compiling frontend assets with Webpack Encore..."
pnpm --dir "$ROOT_DIR" run build

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
composer install \
    --working-dir="$STAGING_DIR" \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader

echo "Running PHP-Scoper on vendor dependencies..."
PHP_SCOPER_BIN="$ROOT_DIR/vendor-bin/php-scoper/vendor/bin/php-scoper"
if [ ! -f "$PHP_SCOPER_BIN" ]; then
    PHP_SCOPER_BIN="$ROOT_DIR/vendor/bin/php-scoper"
fi

if [ ! -f "$PHP_SCOPER_BIN" ]; then
    echo "ERROR: php-scoper binary not found in vendor-bin or vendor/bin." >&2
    exit 1
fi

"$PHP_SCOPER_BIN" add-prefix \
    --working-dir="$STAGING_DIR" \
    --output-dir="$DIST_DIR" \
    --force

echo "Copying plugin source files and prefixing vendor references..."
cp "$ROOT_DIR/expressionlab.php" "$DIST_DIR/expressionlab.php"
cp -r "$ROOT_DIR/src" "$DIST_DIR/src"

php -r '
$srcDir = $argv[1];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcDir));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === "php") {
        $content = file_get_contents($file->getPathname());
        $updated = preg_replace(
            "/(?<!Vendor\\\\)(Symfony|phpDocumentor)\\\\/",
            "ExpressionLab\\\\Vendor\\\\$1\\\\",
            $content
        );
        if ($updated !== $content) {
            file_put_contents($file->getPathname(), $updated);
        }
    }
}
' "$DIST_DIR/src"

echo "Generating optimized production autoloader (classmap)..."
# Include vendor in classmap so Composer maps prefixed classes
sed -i 's/"src\/"/"src\/", "vendor\/"/' "$DIST_DIR/composer.json"
composer dump-autoload \
    --working-dir="$DIST_DIR" \
    --classmap-authoritative \
    --no-dev \
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

echo "Packaging final ZIP ($ZIP_NAME)..."
rm -f "$ZIP_FILE"
(cd "$BUILD_DIR/dist" && zip -r -q "$ZIP_FILE" expressionlab)

# Clean up intermediate staging directory
rm -rf "$STAGING_DIR"

ZIP_SHA256=$(sha256sum "$ZIP_FILE" | awk '{print $1}')
CHANGELOG_FILE="$ROOT_DIR/CHANGELOG.md"
RELEASE_NOTES_FILE="$BUILD_DIR/RELEASE_NOTES.md"

# Parse WordPress plugin header metadata dynamically.
REQUIRES_WP=$(grep -m1 "Requires at least:" "$ROOT_DIR/expressionlab.php" | awk -F: '{print $2}' | tr -d ' \r\n')
REQUIRES_PHP=$(grep -m1 "Requires PHP:" "$ROOT_DIR/expressionlab.php" | awk -F: '{print $2}' | tr -d ' \r\n')
TESTED_WP=$(grep -m1 "Tested up to:" "$ROOT_DIR/expressionlab.php" | awk -F: '{print $2}' | tr -d ' \r\n')

echo "Generating release manifest ($MANIFEST_FILE)..."

SIGN_RESULT=$(EXPRESSION_LAB_SIGNING_KEY="$SIGNING_KEY" php -r '
$manifestFile     = $argv[1];
$version          = $argv[2];
$sha256           = $argv[3];
$zipName          = $argv[4];
$changelogFile    = $argv[5];
$zipFilePath      = $argv[6];
$releaseNotesFile = $argv[7] ?? "";
$requiresWp       = $argv[8] ?? "6.4";
$testedWp         = $argv[9] ?? "6.9.4";
$requiresPhp      = $argv[10] ?? "8.2";

// Read signing key from environment variable (CWE-214 mitigation).
$signingKeyHex = getenv( "EXPRESSION_LAB_SIGNING_KEY" ) ?: "";

$data = [];
if ( file_exists( $manifestFile ) ) {
    $data = json_decode( file_get_contents( $manifestFile ), true ) ?: [];
}

$data["name"]         = $data["name"] ?? "Expression Lab";
$data["slug"]         = "expressionlab";
$data["version"]      = $version;
$data["download_url"] = "https://github.com/andersonsalas/expressionlab/releases/download/v{$version}/{$zipName}";
$data["sha256"]       = $sha256;
$data["requires"]     = $requiresWp;
$data["tested"]       = $testedWp;
$data["requires_php"] = $requiresPhp;
$data["last_updated"] = date( "Y-m-d" );

// Sign ZIP file with Ed25519 if secret key is present
$signed = "no";
if ( ! empty( $signingKeyHex ) && ctype_xdigit( $signingKeyHex ) && strlen( $signingKeyHex ) === 128 && function_exists( "sodium_crypto_sign_detached" ) ) {
    try {
        $secretKeyBin = sodium_hex2bin( $signingKeyHex );
        $zipBytes     = file_get_contents( $zipFilePath );
        $signatureBin = sodium_crypto_sign_detached( $zipBytes, $secretKeyBin );
        $data["signature"] = sodium_bin2hex( $signatureBin );
        $signed = "yes";
    } catch ( \Throwable $e ) {
        $signed = "error: " . $e->getMessage();
    }
}

// Extract current version changes from CHANGELOG.md
$changelogHtml        = "<h4>{$version}</h4><p>Release {$version}.</p>";
$releaseNotesMarkdown = "Release {$version}.";

if ( file_exists( $changelogFile ) ) {
    $changelogContent = file_get_contents( $changelogFile );
    $regex            = "/##\s*\[?" . preg_quote( $version, "/" ) . "\]?[^\r\n]*\r?\n(.*?)(?=\r?\n##\s*\[|\z)/s";
    if ( preg_match( $regex, $changelogContent, $matches ) ) {
        $sectionText = trim( $matches[1] );
        if ( ! empty( $sectionText ) ) {
            $releaseNotesMarkdown = $sectionText;
        }
        $lines       = explode( "\n", $sectionText );
        $html        = "<h4>{$version}</h4>";
        $currentList = [];

        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( str_starts_with( $line, "###" ) ) {
                if ( ! empty( $currentList ) ) {
                    $html .= "<ul>" . implode( "", $currentList ) . "</ul>";
                    $currentList = [];
                }
                $header = htmlspecialchars( trim( substr( $line, 3 ) ) );
                $html  .= "<p><strong>{$header}</strong></p>";
            } elseif ( str_starts_with( $line, "-" ) || str_starts_with( $line, "*" ) ) {
                $item          = htmlspecialchars( trim( substr( $line, 1 ) ) );
                $currentList[] = "<li>{$item}</li>";
            }
        }
        if ( ! empty( $currentList ) ) {
            $html .= "<ul>" . implode( "", $currentList ) . "</ul>";
        }
        $changelogHtml = $html;
    }
}

if ( ! empty( $releaseNotesFile ) ) {
    file_put_contents( $releaseNotesFile, $releaseNotesMarkdown . PHP_EOL );
}

if ( ! isset( $data["sections"] ) ) {
    $data["sections"] = [
        "description" => "Expression Lab is a sandboxed REPL console for WordPress.",
    ];
}
$data["sections"]["changelog"] = $changelogHtml;

file_put_contents( $manifestFile, json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL );
echo $signed;
' "$MANIFEST_FILE" "$VERSION" "$ZIP_SHA256" "$ZIP_NAME" "$CHANGELOG_FILE" "$ZIP_FILE" "$RELEASE_NOTES_FILE" "$REQUIRES_WP" "$TESTED_WP" "$REQUIRES_PHP")

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
