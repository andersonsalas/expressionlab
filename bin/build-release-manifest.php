<?php

// phpcs:ignoreFile -- WordPress runtime is not loaded.

declare(strict_types=1);

ini_set( 'zend.exception_ignore_args', '1' );
ini_set( 'display_errors', 'stderr' );

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "Error: Access denied. This script must be executed via the CLI.\n" );
	exit( 1 );
}

if ( '1' !== getenv( 'EXPRESSION_LAB_BUILD' ) ) {
	fwrite( STDERR, "Error: Direct invocation denied. This script must be executed via bin/build-plugin.sh.\n" );
	exit( 1 );
}

if ( $argc < 7 ) {
	fwrite( STDERR, "Usage: php bin/build-release-manifest.php <manifest-file> <version> <sha256> <zip-name> <changelog-file> <zip-file-path> [release-notes-file] [requires-wp] [tested-wp] [requires-php]\n" );
	exit( 1 );
}

$manifest_file      = $argv[1];
$version            = $argv[2];
$sha256             = $argv[3];
$zip_name           = $argv[4];
$changelog_file     = $argv[5];
$zip_file_path      = $argv[6];
$release_notes_file = $argv[7] ?? '';
$requires_wp        = $argv[8] ?? '6.4';
$tested_wp          = $argv[9] ?? '7.1';
$requires_php       = $argv[10] ?? '8.2';

if ( stream_isatty( STDIN ) ) {
	fwrite( STDERR, "Error: Signing key must be provided via STDIN pipe.\n" );
	exit( 1 );
}

$signing_key_hex = trim( (string) file_get_contents( 'php://stdin' ) );

if ( empty( $signing_key_hex ) || ! ctype_xdigit( $signing_key_hex ) || 128 !== strlen( $signing_key_hex ) ) {
	fwrite( STDERR, "Error: Invalid or missing Ed25519 signing key on STDIN. Exactly 128 hex characters required.\n" );
	exit( 1 );
}

$data = array();

if ( file_exists( $manifest_file ) ) {
	$raw = file_get_contents( $manifest_file );
	if ( false !== $raw ) {
		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) ) {
			$data = $decoded;
		}
	}
}

$data['name']         = $data['name'] ?? 'Expression Lab';
$data['slug']         = 'expressionlab';
$data['version']      = $version;
$data['download_url'] = "https://github.com/andersonsalas/expressionlab/releases/download/v{$version}/{$zip_name}";
$data['sha256']       = $sha256;
$data['requires']     = $requires_wp;
$data['tested']       = $tested_wp;
$data['requires_php'] = $requires_php;
$data['last_updated'] = gmdate( 'Y-m-d' );

// Sign ZIP file with Ed25519.
if ( ! function_exists( 'sodium_crypto_sign_detached' ) ) {
	fwrite( STDERR, "Error: Sodium extension is required for Ed25519 signing.\n" );
	sodium_memzero( $signing_key_hex );
	exit( 1 );
}

try {
	$secret_key_bin = sodium_hex2bin( $signing_key_hex );
	sodium_memzero( $signing_key_hex );

	$zip_bytes = file_get_contents( $zip_file_path );
	if ( false === $zip_bytes ) {
		throw new \RuntimeException( "Could not read ZIP file at {$zip_file_path}" );
	}
	$signature_bin     = sodium_crypto_sign_detached( $zip_bytes, $secret_key_bin );
	$data['signature'] = sodium_bin2hex( $signature_bin );
	$signed            = 'yes';

	sodium_memzero( $secret_key_bin );
} catch ( \Throwable $e ) {
	if ( isset( $secret_key_bin ) && is_string( $secret_key_bin ) && strlen( $secret_key_bin ) > 0 ) {
		sodium_memzero( $secret_key_bin );
	}
	if ( isset( $signing_key_hex ) && is_string( $signing_key_hex ) && strlen( $signing_key_hex ) > 0 ) {
		sodium_memzero( $signing_key_hex );
	}
	fwrite( STDERR, 'Error: Failed to generate Ed25519 signature.' . "\n" );
	exit( 1 );
}

// Extract current version changes from CHANGELOG.md.
$changelog_html         = "<h4>{$version}</h4><p>Release {$version}.</p>";
$release_notes_markdown = "Release {$version}.";

if ( file_exists( $changelog_file ) ) {
	$changelog_content = file_get_contents( $changelog_file );
	if ( false !== $changelog_content ) {
		$regex = '/##\s*\[?' . preg_quote( $version, '/' ) . '\]?[^\r\n]*\r?\n(.*?)(?=\r?\n##\s*\[|\z)/s';
		if ( preg_match( $regex, $changelog_content, $matches ) ) {
			$section_text = trim( $matches[1] );
			if ( ! empty( $section_text ) ) {
				$release_notes_markdown = $section_text;
			}
			$lines        = explode( "\n", $section_text );
			$html         = "<h4>{$version}</h4>";
			$current_list = array();

			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( str_starts_with( $line, '###' ) ) {
					if ( ! empty( $current_list ) ) {
						$html        .= '<ul>' . implode( '', $current_list ) . '</ul>';
						$current_list = array();
					}
					$header = htmlspecialchars( trim( substr( $line, 3 ) ), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
					$html  .= "<p><strong>{$header}</strong></p>";
				} elseif ( str_starts_with( $line, '-' ) || str_starts_with( $line, '*' ) ) {
					$item           = htmlspecialchars( trim( substr( $line, 1 ) ), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
					$current_list[] = "<li>{$item}</li>";
				}
			}
			if ( ! empty( $current_list ) ) {
				$html .= '<ul>' . implode( '', $current_list ) . '</ul>';
			}
			$changelog_html = $html;
		}
	}
}

if ( ! empty( $release_notes_file ) ) {
	file_put_contents( $release_notes_file, $release_notes_markdown . PHP_EOL );
}

if ( ! isset( $data['sections'] ) ) {
	$data['sections'] = array(
		'description' => 'Expression Lab is a sandboxed diagnostics and data inspection environment for WordPress.',
	);
}
$data['sections']['changelog'] = $changelog_html;

$encoded = json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
if ( false !== $encoded ) {
	$tmp_file = $manifest_file . '.tmp.' . getmypid();
	file_put_contents( $tmp_file, $encoded . PHP_EOL );
	rename( $tmp_file, $manifest_file );
}

echo $signed;
exit( 0 );
