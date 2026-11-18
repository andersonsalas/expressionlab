<?php

// phpcs:ignoreFile -- WordPress runtime is not loaded.

declare(strict_types=1);

/**
 * Verifies a signed release manifest exactly like installed sites will:
 *   - version / ZIP name / download_url bind to the expected release version,
 *   - sha256 matches the ZIP bytes,
 *   - the Ed25519 signature over the ZIP verifies against EXPRESSION_LAB_PUBLIC_KEY as defined in
 *     expressionlab.php (single source of truth for the update key).
 *
 * Usage: php bin/build-verify-manifest.php <update.json> <zip-file> <expected-version>
 */

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'EXPRESSION_LAB_BUILD' ) ) {
	fwrite( STDERR, "Error: Access denied. Must be executed with EXPRESSION_LAB_BUILD=1.\n" );
	exit( 1 );
}

if ( 4 !== $argc ) {
	fwrite( STDERR, "Usage: php bin/build-verify-manifest.php <update.json> <zip-file> <expected-version>\n" );
	exit( 1 );
}

[ , $manifest_file, $zip_file, $expected_version ] = $argv;

function el_fail( string $message ): void {
	fwrite( STDERR, "ERROR: {$message}\n" );
	exit( 1 );
}

if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
	el_fail( 'The sodium extension is required.' );
}

$plugin_main = dirname( __DIR__ ) . '/expressionlab.php';
$source      = (string) file_get_contents( $plugin_main );
if ( ! preg_match( "#define\(\s*'EXPRESSION_LAB_PUBLIC_KEY',\s*'([0-9a-f]{64})'\s*\)#", $source, $key_match ) ) {
	el_fail( 'EXPRESSION_LAB_PUBLIC_KEY not found in expressionlab.php.' );
}

$manifest = json_decode( (string) file_get_contents( $manifest_file ), true );
if ( ! is_array( $manifest ) ) {
	el_fail( "Unable to parse {$manifest_file}." );
}

$zip_name     = "expressionlab-{$expected_version}.zip";
$expected_url = "https://github.com/andersonsalas/expressionlab/releases/download/v{$expected_version}/{$zip_name}";

if ( ( $manifest['version'] ?? null ) !== $expected_version ) {
	el_fail( 'Manifest version does not match the release version.' );
}
if ( basename( $zip_file ) !== $zip_name ) {
	el_fail( "ZIP file name must be {$zip_name}." );
}
if ( ( $manifest['download_url'] ?? null ) !== $expected_url ) {
	el_fail( "Manifest download_url must be {$expected_url}." );
}
if ( ! is_file( $zip_file ) || ( $manifest['sha256'] ?? null ) !== hash_file( 'sha256', $zip_file ) ) {
	el_fail( 'Manifest sha256 does not match the ZIP.' );
}
$signature_hex = (string) ( $manifest['signature'] ?? '' );
if ( ! preg_match( '/^[0-9a-f]{128}$/', $signature_hex ) ) {
	el_fail( 'Manifest signature is missing or malformed.' );
}

$valid = sodium_crypto_sign_verify_detached(
	sodium_hex2bin( $signature_hex ),
	(string) file_get_contents( $zip_file ),
	sodium_hex2bin( $key_match[1] )
);
if ( ! $valid ) {
	el_fail( 'Ed25519 signature does not verify against EXPRESSION_LAB_PUBLIC_KEY (wrong signing key?).' );
}

echo "Release manifest verified: v{$expected_version}, sha256 and Ed25519 signature match EXPRESSION_LAB_PUBLIC_KEY.\n";
exit( 0 );
