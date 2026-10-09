<?php
/**
 * This file is part of the Expression Lab plugin.
 *
 * (c) Anderson Salas <github@andersonsalas.com>
 *
 * See the LICENSE file for license information.
 *
 * @package ExpressionLab
 */

namespace ExpressionLab\Admin\Pages;

use ExpressionLab\Core\Singleton;
use ExpressionLab\Core\Helper;
use ExpressionLab\Core\AdminPage;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * Onboarding admin page class.
 *
 * Manages the initial plugin configuration wizard, system requirements checks,
 * and administrative key generation workflows.
 *
 * @since 0.0.1
 * @internal
 * @package ExpressionLab
 */
class Onboarding extends AdminPage {
	use Singleton;

	/**
	 * Determines whether the onboarding page should load.
	 *
	 * @since 0.0.1
	 * @internal
	 *
	 * @return bool True if the plugin is not yet fully configured, false otherwise.
	 */
	public function should_load() {
		return ! Helper::plugin_is_fully_configured();
	}

	/**
	 * Retrieves script localization data for the onboarding wizard.
	 *
	 * @since 0.0.1
	 * @internal
	 *
	 * @return array Associative array of localized environment data and translation strings.
	 */
	protected function get_script_data(): array {
		$home           = (string) get_option( 'home' );
		$clean_site_url = (string) preg_replace( '#^https?://#i', '', untrailingslashit( $home ) );

		return array(
			'version'  => EXPRESSION_LAB_VERSION,
			'user_id'  => get_current_user_id(),
			'site_url' => $clean_site_url,
			'server'   => array(
				'sqlite_enabled' => extension_loaded( 'sqlite3' ),
				'sodium_enabled' => extension_loaded( 'sodium' ),
			),
			'i18n'     => $this->get_i18n_strings(),
		);
	}

	/**
	 * Retrieves localized internationalization strings for onboarding.
	 *
	 * @since 0.0.1
	 * @internal
	 *
	 * @return array<string, string> Key-value pairs of translation strings.
	 */
	protected function get_i18n_strings(): array {
		return array(
			'Welcome'                                    => __( 'Welcome', 'expression-lab' ),
			'System Requirements'                        => __( 'System Requirements', 'expression-lab' ),
			'Settings'                                   => __( 'Settings', 'expression-lab' ),
			'Key Generation'                             => __( 'Key Generation', 'expression-lab' ),
			'Verification'                               => __( 'Verification', 'expression-lab' ),
			'Preparing key generation...'                => __( 'Preparing key generation...', 'expression-lab' ),
			'Generating cryptographic keys...'           => __( 'Generating cryptographic keys...', 'expression-lab' ),
			'Cryptographic keys generated successfully!' => __( 'Cryptographic keys generated successfully!', 'expression-lab' ),
			/* translators: %s: error message */
			'Key generation failed: %s'                  => __( 'Key generation failed: %s', 'expression-lab' ),
			'Welcome to Expression Lab'                  => __( 'Welcome to Expression Lab', 'expression-lab' ),
			'Configure the environment limits and generate your cryptographic access keys to get started.' => __( 'Configure the environment limits and generate your cryptographic access keys to get started.', 'expression-lab' ),
			'Warning: Alpha Software'                    => __( 'Warning: Alpha Software', 'expression-lab' ),
			'Expression Lab contains diagnostic tools and evaluation engines designed for non-production environments. While built with defensive architecture (AST sandboxing, client-side cryptography), this codebase has not undergone independent third-party security audits.' => __( 'Expression Lab contains diagnostic tools and evaluation engines designed for non-production environments. While built with defensive architecture (AST sandboxing, client-side cryptography), this codebase has not undergone independent third-party security audits.', 'expression-lab' ),
			'I understand Expression Lab is unaudited alpha software and agree to run it only in staging, testing, or local environments.' => __( 'I understand Expression Lab is unaudited alpha software and agree to run it only in staging, testing, or local environments.', 'expression-lab' ),
			'Begin install'                              => __( 'Begin install', 'expression-lab' ),
			'SQLite3 PHP extension'                      => __( 'SQLite3 PHP extension', 'expression-lab' ),
			'OK'                                         => __( 'OK', 'expression-lab' ),
			'Missing'                                    => __( 'Missing', 'expression-lab' ),
			'Sodium PHP extension'                       => __( 'Sodium PHP extension', 'expression-lab' ),
			'Web Crypto API (Browser)'                   => __( 'Web Crypto API (Browser)', 'expression-lab' ),
			'SQLite3 Extension Missing (Optional)'       => __( 'SQLite3 Extension Missing (Optional)', 'expression-lab' ),
			'Expression Lab can operate without SQLite3, but safe in-memory database mirroring will be disabled. You may continue the installation, or install the PHP sqlite3 extension to enable this feature.' => __( 'Expression Lab can operate without SQLite3, but safe in-memory database mirroring will be disabled. You may continue the installation, or install the PHP sqlite3 extension to enable this feature.', 'expression-lab' ),
			'<strong>SQLite3</strong> enables safe, in-memory database mirroring to prevent direct queries to the live database. <strong>Sodium</strong> and the <strong>Web Crypto API</strong> are required to generate, sign, and validate secure communications within the sandboxed environment.' => __( '<strong>SQLite3</strong> enables safe, in-memory database mirroring to prevent direct queries to the live database. <strong>Sodium</strong> and the <strong>Web Crypto API</strong> are required to generate, sign, and validate secure communications within the sandboxed environment.', 'expression-lab' ),
			'Back'                                       => __( 'Back', 'expression-lab' ),
			'Next'                                       => __( 'Next', 'expression-lab' ),
			'General'                                    => __( 'General', 'expression-lab' ),
			'Staging URL or Domain'                      => __( 'Staging URL or Domain', 'expression-lab' ),
			'If set, execution is restricted to matching URLs or domains. Verification checks the database-persisted site URL and ignores client-supplied HTTP request headers. When empty, environment checks are disabled.' => __( 'If set, execution is restricted to matching URLs or domains. Verification checks the database-persisted site URL and ignores client-supplied HTTP request headers. When empty, environment checks are disabled.', 'expression-lab' ),
			'Invalid staging URL. Use only a host name, an optional port and an optional path (letters, digits, dots, hyphens, underscores, slashes and * wildcards).' => __( 'Invalid staging URL. Use only a host name, an optional port and an optional path (letters, digits, dots, hyphens, underscores, slashes and * wildcards).', 'expression-lab' ),
			'Domain, hostname, or base URL path without protocol prefix (e.g. staging.example.com or 127.0.0.1/site). Wildcard (*) matching is supported.' => __( 'Domain, hostname, or base URL path without protocol prefix (e.g. staging.example.com or 127.0.0.1/site). Wildcard (*) matching is supported.', 'expression-lab' ),
			'Permissions'                                => __( 'Permissions', 'expression-lab' ),
			'All DSL execution operates in an isolated, read-only, and offline state by default. Elevated write privileges and outbound networking may be enabled selectively below:' => __( 'All DSL execution operates in an isolated, read-only, and offline state by default. Elevated write privileges and outbound networking may be enabled selectively below:', 'expression-lab' ),
			'Database'                                   => __( 'Database', 'expression-lab' ),
			'Permits write, update, and delete operations on database tables.' => __( 'Permits write, update, and delete operations on database tables.', 'expression-lab' ),
			'File system'                                => __( 'File system', 'expression-lab' ),
			'Permits file creation, modification, and deletion within the server filesystem.' => __( 'Permits file creation, modification, and deletion within the server filesystem.', 'expression-lab' ),
			'Network'                                    => __( 'Network', 'expression-lab' ),
			'Permits outbound HTTP and network socket requests to external hosts.' => __( 'Permits outbound HTTP and network socket requests to external hosts.', 'expression-lab' ),
			'Hooks'                                      => __( 'Hooks', 'expression-lab' ),
			'Permits registration and invocation of native WordPress action and filter hooks.' => __( 'Permits registration and invocation of native WordPress action and filter hooks.', 'expression-lab' ),
			'Engine constraints'                         => __( 'Engine constraints', 'expression-lab' ),
			'Max execution time'                         => __( 'Max execution time', 'expression-lab' ),
			'Maximum execution time in seconds allocated per expression prior to process termination. Default: 2 seconds.' => __( 'Maximum execution time in seconds allocated per expression prior to process termination. Default: 2 seconds.', 'expression-lab' ),
			'seconds'                                    => __( 'seconds', 'expression-lab' ),
			'Enter a whole number of seconds greater than zero.' => __( 'Enter a whole number of seconds greater than zero.', 'expression-lab' ),
			'Signature duration'                         => __( 'Signature duration', 'expression-lab' ),
			'How long signed requests remain valid before expiring. The default 120 seconds works well for most setups.' => __( 'How long signed requests remain valid before expiring. The default 120 seconds works well for most setups.', 'expression-lab' ),
			'Integrations'                               => __( 'Integrations', 'expression-lab' ),
			'MaxMind license key'                        => __( 'MaxMind license key', 'expression-lab' ),
			'Optional key used to download and update the local GeoLite2 database.' => __( 'Optional key used to download and update the local GeoLite2 database.', 'expression-lab' ),
			'Invalid license key. MaxMind license keys contain only letters, digits, hyphens and underscores.' => __( 'Invalid license key. MaxMind license keys contain only letters, digits, hyphens and underscores.', 'expression-lab' ),
			'Once the setup is completed, run <code>wp expressionlab iplookup update</code> to fetch the file.' => __( 'Once the setup is completed, run <code>wp expressionlab iplookup update</code> to fetch the file.', 'expression-lab' ),
			'Keys already generated'                     => __( 'Keys already generated', 'expression-lab' ),
			'Leave the passphrase empty and continue to keep the keys generated in this session, or enter a new passphrase to generate a new key pair.' => __( 'Leave the passphrase empty and continue to keep the keys generated in this session, or enter a new passphrase to generate a new key pair.', 'expression-lab' ),
			'Passphrase:'                                => __( 'Passphrase:', 'expression-lab' ),
			'Min 8 characters'                           => __( 'Min 8 characters', 'expression-lab' ),
			'This passphrase derives the Ed25519 signing key in your browser.' => __( 'This passphrase derives the Ed25519 signing key in your browser.', 'expression-lab' ),
			'<strong>Zero storage:</strong> Neither the passphrase nor the private key touches the database or server filesystem.' => __( '<strong>Zero storage:</strong> Neither the passphrase nor the private key touches the database or server filesystem.', 'expression-lab' ),
			'<strong>Separation of access:</strong> Use a distinct phrase rather than your WordPress user password.' => __( '<strong>Separation of access:</strong> Use a distinct phrase rather than your WordPress user password.', 'expression-lab' ),
			'<strong>No recovery:</strong> If forgotten, reset requires removing the authentication constants from <code>wp-config.php</code>' => __( '<strong>No recovery:</strong> If forgotten, reset requires removing the authentication constants from <code>wp-config.php</code>', 'expression-lab' ),
			'Continue'                                   => __( 'Continue', 'expression-lab' ),
			'Generate Keys'                              => __( 'Generate Keys', 'expression-lab' ),
			'Server Ownership Verification'              => __( 'Server Ownership Verification', 'expression-lab' ),
			'Activation requires filesystem-level persistence. Paste the following constants into your <code>wp-config.php</code> file:' => __( 'Activation requires filesystem-level persistence. Paste the following constants into your <code>wp-config.php</code> file:', 'expression-lab' ),
			'Once the file is saved, reload this page to validate the configuration and unlock the console.' => __( 'Once the file is saved, reload this page to validate the configuration and unlock the console.', 'expression-lab' ),
			'Expression Lab is a free, open-source plugin by Anderson Salas and contributors.' => __( 'Expression Lab is a free, open-source plugin by Anderson Salas and contributors.', 'expression-lab' ),
		);
	}

	/**
	 * Retrieves the asset handle name for onboarding scripts and styles.
	 *
	 * @since 0.0.1
	 * @internal
	 *
	 * @return string Asset handle name.
	 */
	protected function get_asset_name(): string {
		return 'expressionlab-onboard';
	}

	/**
	 * Renders the onboarding administration screen.
	 *
	 * @since 0.0.1
	 * @internal
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'expression-lab' ) );
		}

		if ( false === EXPRESSION_LAB_SANDBOX_ENABLED ) {
			echo '<div class="wrap"><div class="expressionlab-app"></div></div>';
		} else {
			$sandbox_url = $this->get_page_url( array( 'sandbox' => '1' ) );

			echo '<div class="wrap"><iframe src="' . esc_url( $sandbox_url ) . '" id="expressionlab-sandbox" class="expressionlab-app" sandbox="allow-scripts"></iframe></div>';
		}
	}
}
