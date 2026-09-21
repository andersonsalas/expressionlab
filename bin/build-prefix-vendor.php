<?php

// phpcs:ignoreFile -- WordPress runtime is not loaded.

declare(strict_types=1);

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "Error: Access denied. This script must be executed via the CLI.\n" );
	exit( 1 );
}

if ( '1' !== getenv( 'EXPRESSION_LAB_BUILD' ) ) {
	fwrite( STDERR, "Error: Direct invocation denied. This script must be executed via bin/build-plugin.sh.\n" );
	exit( 1 );
}

if ( $argc < 3 ) {
	fwrite( STDERR, "Usage: php bin/build-prefix-vendor.php <dist-dir> <src-dir> [main-file]\n" );
	exit( 1 );
}

$dist_dir  = rtrim( $argv[1], '/\\' );
$src_dir   = rtrim( $argv[2], '/\\' );
$main_file = isset( $argv[3] ) ? trim( $argv[3] ) : '';
$prefix    = 'ExpressionLab\\Vendor';

if ( ! is_dir( $dist_dir ) ) {
	fwrite( STDERR, "Error: Distribution directory not found: {$dist_dir}\n" );
	exit( 1 );
}

if ( ! is_dir( $src_dir ) ) {
	fwrite( STDERR, "Error: Source directory not found: {$src_dir}\n" );
	exit( 1 );
}

// 1. Discover vendor root namespaces dynamically from scoped Composer autoloaders.
$psr4_file       = $dist_dir . '/vendor/composer/autoload_psr4.php';
$namespaces_file = $dist_dir . '/vendor/composer/autoload_namespaces.php';

$psr4 = file_exists( $psr4_file ) ? require $psr4_file : array();
$psr0 = file_exists( $namespaces_file ) ? require $namespaces_file : array();

$vendor_roots = array();
foreach ( array_merge( array_keys( $psr4 ), array_keys( $psr0 ) ) as $ns ) {
	$clean = $ns;
	if ( str_starts_with( $clean, $prefix . '\\' ) ) {
		$clean = substr( $clean, strlen( $prefix ) + 1 );
	}
	$root = explode( '\\', ltrim( $clean, '\\' ) )[0];
	if ( 'ExpressionLab' !== $root && preg_match( '/^[a-zA-Z0-9_]+$/', $root ) ) {
		$vendor_roots[ $root ] = true;
	}
}

$roots = array_keys( $vendor_roots );
if ( empty( $roots ) ) {
	fwrite( STDERR, "Error: No vendor namespaces detected in {$dist_dir}/vendor/composer/.\n" );
	exit( 1 );
}

// 2. Collect files to process.
$files = array();
if ( ! empty( $main_file ) && is_file( $main_file ) ) {
	$files[] = $main_file;
}

$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $src_dir ) );
foreach ( $iterator as $file ) {
	if ( $file->isFile() && 'php' === $file->getExtension() ) {
		$files[] = $file->getPathname();
	}
}

// 3. Token-aware prefixing using PhpToken (preserves WP tabs, formatting, and strings).
$root_pattern = implode( '|', array_map( 'preg_quote', $roots ) );
$doc_pattern  = '/(?<!Vendor\\\\)(' . $root_pattern . ')\\\\/';
$count        = 0;

foreach ( $files as $file_path ) {
	$content  = file_get_contents( $file_path );
	$tokens   = PhpToken::tokenize( $content );
	$modified = false;

	foreach ( $tokens as $token ) {
		if ( T_NAME_QUALIFIED === $token->id ) {
			foreach ( $roots as $root ) {
				if ( str_starts_with( $token->text, $root . '\\' ) && ! str_starts_with( $token->text, $prefix . '\\' ) ) {
					$token->text = $prefix . '\\' . $token->text;
					$modified    = true;
					break;
				}
			}
		} elseif ( T_NAME_FULLY_QUALIFIED === $token->id ) {
			foreach ( $roots as $root ) {
				if ( str_starts_with( $token->text, '\\' . $root . '\\' ) && ! str_starts_with( $token->text, '\\' . $prefix . '\\' ) ) {
					$token->text = '\\' . $prefix . '\\' . substr( $token->text, 1 );
					$modified    = true;
					break;
				}
			}
		} elseif ( T_DOC_COMMENT === $token->id ) {
			$new_text = preg_replace( $doc_pattern, $prefix . '\\\\$1\\\\', $token->text );
			if ( $new_text !== $token->text ) {
				$token->text = $new_text;
				$modified    = true;
			}
		}
	}

	if ( $modified ) {
		$reconstructed = implode( '', array_map( static fn( $t ) => $t->text, $tokens ) );
		file_put_contents( $file_path, $reconstructed );
		$count++;
	}
}

echo "Token-aware prefixer updated {$count} file(s) for vendor roots: " . implode( ', ', $roots ) . "\n";
exit( 0 );
