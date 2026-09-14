<?php
/**
 * Plugin Name: Expression Lab
 * Description: Expression Lab is a sandboxed diagnostics and data inspection environment for WordPress. It features a Domain-Specific Language for evaluating expressions and visualization capabilities.
 * Version: 0.0.1-alpha
 * Author: Anderson Salas
 * Author URI: https://andersonsalas.com
 * Plugin URI: https://expressionlab.io
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: expression-lab
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.2
 * Tested up to: 7.1
 *
 * @package ExpressionLab
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

if ( ! defined( 'EXPRESSION_LAB_VERSION' ) ) {
	/**
	 * Expression Lab version.
	 *
	 * @var string
	 */
	define( 'EXPRESSION_LAB_VERSION', '0.0.1-alpha' );
}

if ( ! defined( 'EXPRESSION_LAB_PUBLIC_KEY' ) ) {
	/**
	 * Public key for verifying update signatures (Ed25519).
	 *
	 * @var string
	 */
	define( 'EXPRESSION_LAB_PUBLIC_KEY', '38129953c6aabc40a5837e3b9ede6d7bb68b34ff78db004df1c9353b74e37afb' );
}

if ( ! defined( 'EXPRESSION_LAB_MANIFEST_URL' ) ) {
	/**
	 * Remote update manifest URL.
	 *
	 * @var string
	 */
	define( 'EXPRESSION_LAB_MANIFEST_URL', 'https://updates.expressionlab.com/update.json' );
}

if ( ! defined( 'EXPRESSION_LAB_HOMEPAGE_URL' ) ) {
	/**
	 * Plugin homepage URL.
	 *
	 * @var string
	 */
	define( 'EXPRESSION_LAB_HOMEPAGE_URL', 'https://expressionlab.com' );
}

if ( ! defined( 'EXPRESSION_LAB_SLUG' ) ) {
	/**
	 * Plugin slug identifier.
	 *
	 * @var string
	 */
	define( 'EXPRESSION_LAB_SLUG', 'expressionlab' );
}

if ( ! defined( 'EXPRESSION_LAB_UPDATE_TRANSIENT_KEY' ) ) {
	/**
	 * Transient key for caching the update manifest.
	 *
	 * @var string
	 */
	define( 'EXPRESSION_LAB_UPDATE_TRANSIENT_KEY', 'expressionlab_update_manifest' );
}

if ( ! defined( 'EXPRESSION_LAB_DIR' ) ) {
	/**
	 * Expression Lab directory path.
	 *
	 * @var string
	 */
	define( 'EXPRESSION_LAB_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'EXPRESSION_LAB_BASENAME' ) ) {
	/**
	 * Expression Lab plugin basename.
	 *
	 * @var string
	 */
	define( 'EXPRESSION_LAB_BASENAME', 'expressionlab/expressionlab.php' );
}

if ( ! defined( 'EXPRESSION_LAB_URL' ) ) {
	/**
	 * Expression Lab directory URL.
	 *
	 * @var string
	 */
	define( 'EXPRESSION_LAB_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'EXPRESSION_LAB_ASSETS_DIR' ) ) {
	/**
	 * Expression Lab assets directory path.
	 *
	 * @var string
	 */
	define( 'EXPRESSION_LAB_ASSETS_DIR', EXPRESSION_LAB_DIR . 'assets' );
}

if ( ! defined( 'EXPRESSION_LAB_ASSETS_URL' ) ) {
	/**
	 * Expression Lab assets directory URL.
	 *
	 * @var string
	 */
	define( 'EXPRESSION_LAB_ASSETS_URL', EXPRESSION_LAB_URL . 'assets' );
}

if ( ! defined( 'EXPRESSION_LAB_DB_PREFIX' ) ) {
	/**
	 * Expression Lab database prefix.
	 *
	 * @var string
	 */
	define( 'EXPRESSION_LAB_DB_PREFIX', 'wpfp_' );
}

if ( ! defined( 'EXPRESSION_LAB_TEST_ENV' ) ) {
	/**
	 * Expression Lab test environment flag.
	 *
	 * @internal
	 * @var bool
	 */
	define( 'EXPRESSION_LAB_TEST_ENV', false );
}

if ( ! defined( 'EXPRESSION_LAB_SANDBOX_ENABLED' ) ) {
	/**
	 * Security sandbox.
	 *
	 * When enabled, the plugin will be rendered inside a sandboxed iframe and a signature
	 * verification will be enforced on all incoming requests.
	 *
	 * WARNING: THIS IS A CRITICAL FEATURE. DO NOT DISABLE IT IN PRODUCTION.
	 *
	 * @var bool
	 */
	define( 'EXPRESSION_LAB_SANDBOX_ENABLED', true );
}

if ( ! defined( 'EXPRESSION_LAB_HOOKS_ENABLED' ) ) {
	/**
	 * Enable extensibility
	 *
	 * When enabled, the plugin will allow other plugins to extend Expression Lab
	 * through hooks and filters.
	 *
	 * WARNING: THIRD-PARTY HOOKS CAN BYPASS SANDBOX SECURITY. USE AT YOUR OWN RISK.
	 *
	 * @var bool
	 */
	define( 'EXPRESSION_LAB_HOOKS_ENABLED', false );
}

if ( ! defined( 'EXPRESSION_LAB_MAX_EXECUTION_LIMIT' ) ) {
	/**
	 * Max execution time limit.
	 *
	 * This constant defines the maximum amount of time, in seconds, that an
	 * expression is allowed to run before being forcefully terminated by the
	 * engine to prevent long-running or infinite processes.
	 *
	 * @var int
	 */
	define( 'EXPRESSION_LAB_MAX_EXECUTION_LIMIT', 2 );
}

if ( ! defined( 'EXPRESSION_LAB_DATABASE_READONLY' ) ) {
	/**
	 * Database write protection.
	 *
	 * When enabled, the plugin will prevent any write operations to the database
	 * during expression evaluation, ensuring that expressions can only read data
	 * but cannot modify it.
	 *
	 * @var bool
	 */
	define( 'EXPRESSION_LAB_DATABASE_READONLY', true );
}

if ( ! defined( 'EXPRESSION_LAB_FILESYSTEM_READONLY' ) ) {
	/**
	 * Filesystem write protection.
	 *
	 * When enabled, the plugin will prevent any write operations to the filesystem
	 * during expression evaluation, ensuring that expressions cannot create,
	 * modify, or delete files.
	 *
	 * @var bool
	 */
	define( 'EXPRESSION_LAB_FILESYSTEM_READONLY', true );
}

if ( ! defined( 'EXPRESSION_LAB_NETWORK_READONLY' ) ) {
	/**
	 * Network write protection.
	 *
	 * When enabled, the plugin will prevent any outgoing network requests during
	 * expression evaluation, ensuring that expressions cannot communicate with
	 * external services or APIs.
	 *
	 * @var bool
	 */
	define( 'EXPRESSION_LAB_NETWORK_READONLY', true );
}

if ( ! defined( 'EXPRESSION_LAB_SIGNATURE_DURATION' ) ) {
	/**
	 * Signature duration.
	 *
	 * This constant defines the validity period, in seconds, of the security
	 * signatures used for request verification when the sandbox mode is enabled.
	 * After this period, signatures will expire and requests will be rejected to
	 * enhance security against replay attacks.
	 *
	 * @var int
	 */
	define( 'EXPRESSION_LAB_SIGNATURE_DURATION', 120 );
}

if ( ! defined( 'EXPRESSION_LAB_CACHE' ) ) {
	/**
	 * Internal cache mechanism.
	 *
	 * @internal
	 * @var bool
	 */
	define( 'EXPRESSION_LAB_CACHE', true );
}

if ( ! defined( 'EXPRESSION_LAB_DEBUG_MODE' ) ) {
	/**
	 * Internal debug mode.
	 *
	 * When enabled, the plugin bypasses the security sandbox and exposes internal
	 * diagnostic and debugging information.
	 *
	 * WARNING: DO NOT ENABLE THIS IN PRODUCTION ENVIRONMENTS.
	 *
	 * @internal
	 * @var bool
	 */
	define( 'EXPRESSION_LAB_DEBUG_MODE', false );
}

if ( ! defined( 'EXPRESSION_LAB_BROWSER_LOG' ) ) {
	/**
	 * Internal browser log.
	 *
	 * @internal
	 * @var bool
	 */
	define( 'EXPRESSION_LAB_BROWSER_LOG', false );
}

if ( ! defined( 'EXPRESSION_LAB_DISABLE_SQLITE' ) ) {
	/**
	 * Disable SQLite.
	 *
	 * @internal
	 * @var bool
	 */
	define( 'EXPRESSION_LAB_DISABLE_SQLITE', false );
}

if ( file_exists( __DIR__ . '/vendor/scoper-autoload.php' ) ) {
	require_once __DIR__ . '/vendor/scoper-autoload.php';
} else {
	require_once __DIR__ . '/vendor/autoload.php';
}

ExpressionLab\Core\Loader::get()->boot();
