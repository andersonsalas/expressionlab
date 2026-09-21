<?php

// phpcs:ignoreFile -- WordPress runtime is not loaded.

declare(strict_types=1);

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'EXPRESSION_LAB_BUILD' ) ) {
	fwrite( STDERR, "Error: Access denied. Must be executed via bin/build-plugin.sh.\n" );
	exit( 1 );
}

if ( false !== getenv( 'EXPRESSION_LAB_SIGNING_KEY' ) || ! empty( $_ENV['EXPRESSION_LAB_SIGNING_KEY'] ) || ! empty( $_SERVER['EXPRESSION_LAB_SIGNING_KEY'] ) ) {
	fwrite( STDERR, "Security Error: EXPRESSION_LAB_SIGNING_KEY must NOT be defined at this stage.\n" );
	exit( 1 );
}

if ( $argc < 2 ) {
	fwrite( STDERR, "Usage: php bin/build-verify-artifact.php <dist-dir>\n" );
	exit( 1 );
}

$dist_dir      = rtrim( $argv[1], '/\\' );
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
