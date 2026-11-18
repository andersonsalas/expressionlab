<?php

// phpcs:ignoreFile -- WordPress runtime is not loaded.

declare(strict_types=1);

/**
 * Verifies the compiled production tree before it is zipped (and the extracted ZIP in CI):
 *
 *   1. No signing material in the environment of build tools.
 *   2. Leak scan of the FINAL tree: forbidden paths (source maps, dev manifests, tests, keys, env files,
 *      assets/addons/) and forbidden content (source-map directives, PEM private keys, 128-hex key-shaped
 *      strings, absolute build paths, build paths inside inline WASM).
 *   3. Smoke test of the scoped autoloader and the expression engine (skipped with --scan-only).
 *
 * Usage: php bin/build-verify-artifact.php [--scan-only] <dist-dir>
 */

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'EXPRESSION_LAB_BUILD' ) ) {
	fwrite( STDERR, "Error: Access denied. Must be executed via bin/build-plugin.sh.\n" );
	exit( 1 );
}

foreach ( array( 'EXPRESSION_LAB_SIGNING_KEY', 'EXPRESSION_LAB_ADDON_SIGNING_KEY' ) as $secret_name ) {
	if ( false !== getenv( $secret_name ) || ! empty( $_ENV[ $secret_name ] ) || ! empty( $_SERVER[ $secret_name ] ) ) {
		fwrite( STDERR, "Security Error: {$secret_name} must NOT be defined at this stage.\n" );
		exit( 1 );
	}
}

$args      = array_slice( $argv, 1 );
$scan_only = false;
$dist_dir  = null;
foreach ( $args as $arg ) {
	if ( '--scan-only' === $arg ) {
		$scan_only = true;
	} elseif ( null === $dist_dir && ! str_starts_with( $arg, '-' ) ) {
		$dist_dir = rtrim( $arg, '/\\' );
	} else {
		fwrite( STDERR, "Usage: php bin/build-verify-artifact.php [--scan-only] <dist-dir>\n" );
		exit( 1 );
	}
}

if ( null === $dist_dir || ! is_dir( $dist_dir ) ) {
	fwrite( STDERR, "Usage: php bin/build-verify-artifact.php [--scan-only] <dist-dir>\n" );
	exit( 1 );
}

/**
 * Relative-path patterns that must never ship.
 */
const EL_FORBIDDEN_PATHS = array(
	'#(^|/)(node_modules|\.git|\.github|\.idea|\.vscode|tests?)/#'                                                     => 'development directory',
	'#\.(map|pem|key|log)$#i'                                                                                          => 'source map / key / log file',
	'#(^|/)\.env($|\.)#'                                                                                               => 'environment file',
	'#(^|/)(composer\.(json|lock)|package(-lock)?\.json|pnpm-(lock|workspace)\.yaml|webpack\.config\.js|phpunit\.xml(\.dist)?|\.phpcs\.xml(\.dist)?|scoper\.inc\.php)$#' => 'development manifest',
	'#^assets/addons(/|$)#'                                                                                            => 'raw addon mount (assets/addons/)',
);

/**
 * Content patterns that must never ship (applied to text files with base64 data URIs blanked).
 */
const EL_FORBIDDEN_CONTENT = array(
	'#^\s*/[/*][\#@]\s*sourceMappingURL=#m'                                                    => 'source map directive',
	'#-----BEGIN [A-Z ]*PRIVATE KEY-----#'                                                     => 'PEM private key block',
	'#(?:^|[^0-9a-fA-F])(?=[0-9a-fA-F]{0,127}[0-9])(?=[0-9a-fA-F]{0,127}[a-fA-F])[0-9a-fA-F]{128}(?![0-9a-fA-F])#' => '128-hex secret-key-shaped string',
	'#/home/runner/#'                                                                          => 'CI runner absolute path',
	'#/var/www/html/expressionlab#'                                                                       => 'local development absolute path',
	'#\.cargo/registry/src/#'                                                                  => 'Cargo registry source path',
);

const EL_TEXT_EXTENSIONS = array( 'php', 'js', 'css', 'json', 'txt', 'md', 'html', 'htm', 'svg', 'po', 'pot', 'xml' );

/**
 * Scans the final tree and returns a list of violations.
 *
 * @param string $root Distribution directory.
 * @return string[]
 */
function el_scan_tree( string $root ): array {
	$violations = array();
	$iterator   = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ( $iterator as $file ) {
		$rel = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $root ) + 1 ) );

		if ( $file->isLink() ) {
			$violations[] = "{$rel}: symbolic link";
			continue;
		}
		foreach ( EL_FORBIDDEN_PATHS as $pattern => $label ) {
			if ( preg_match( $pattern, $rel . ( $file->isDir() ? '/' : '' ) ) ) {
				$violations[] = "{$rel}: {$label}";
				continue 2;
			}
		}
		if ( ! $file->isFile() ) {
			continue;
		}

		$extension = strtolower( $file->getExtension() );
		if ( ! in_array( $extension, EL_TEXT_EXTENSIONS, true ) ) {
			continue;
		}

		$content = (string) file_get_contents( $file->getPathname() );

		if ( 'js' === $extension && preg_match_all( '#data:application/wasm;base64,([A-Za-z0-9+/=]+)#', $content, $wasm_matches ) ) {
			foreach ( $wasm_matches[1] as $index => $encoded ) {
				$wasm = (string) base64_decode( $encoded, true );
				foreach ( array( '/home/runner/', '/var/www/html/expressionlab', '.cargo/registry/src/' ) as $needle ) {
					if ( str_contains( $wasm, $needle ) ) {
						$violations[] = "{$rel}: inline WASM #" . ( $index + 1 ) . " leaks build path '{$needle}'";
					}
				}
			}
		}

		$scannable = (string) preg_replace( '#data:[a-z]+/[a-z0-9.+-]+;base64,[A-Za-z0-9+/=]+#i', 'data:;base64,', $content );
		foreach ( EL_FORBIDDEN_CONTENT as $pattern => $label ) {
			if ( preg_match( $pattern, $scannable ) ) {
				$violations[] = "{$rel}: {$label}";
			}
		}
	}

	return $violations;
}

$violations = el_scan_tree( $dist_dir );
if ( ! empty( $violations ) ) {
	fwrite( STDERR, "ERROR: Release artifact leak scan failed:\n  - " . implode( "\n  - ", $violations ) . "\n" );
	exit( 1 );
}
echo "Artifact leak scan passed: no forbidden files or content in {$dist_dir}.\n";

if ( $scan_only ) {
	exit( 0 );
}

$autoload_file = $dist_dir . '/vendor/scoper-autoload.php';

if ( ! file_exists( $autoload_file ) ) {
	fwrite( STDERR, "Error: Scoper autoloader not found at: {$autoload_file}\n" );
	exit( 1 );
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', true );
}

try {
	require_once $autoload_file;

	$tar      = new \ExpressionLab\Vendor\splitbrain\PHPArchive\Tar();
	$factory  = \ExpressionLab\Vendor\phpDocumentor\Reflection\DocBlockFactory::createInstance();
	$docblock = $factory->create( '/** @param string $foo */' );

	if ( ! $docblock->hasTag( 'param' ) ) {
		throw new \RuntimeException( 'DocBlockFactory failed to parse docblock tags.' );
	}

	if ( ! class_exists( \ExpressionLab\Vendor\MaxMind\Db\Reader::class ) ) {
		throw new \RuntimeException( 'MaxMind Reader class missing from autoloader.' );
	}

	$engine = new \ExpressionLab\Core\ExpressionLanguage();
	$result = $engine->evaluate( '40 + 2' );

	if ( 42 !== $result ) {
		throw new \RuntimeException( 'ExpressionLanguage evaluation failed. Expected 42, got: ' . json_encode( $result ) );
	}

	echo "Artifact smoke test passed: Autoloader, scoped dependencies, and ExpressionLanguage verified.\n";
	exit( 0 );
} catch ( \Throwable $e ) {
	fwrite( STDERR, "ERROR: Production artifact smoke test failed!\n" );
	fwrite( STDERR, 'Message: ' . $e->getMessage() . "\n" );
	fwrite( STDERR, 'File:    ' . $e->getFile() . ':' . $e->getLine() . "\n" );
	exit( 1 );
}
