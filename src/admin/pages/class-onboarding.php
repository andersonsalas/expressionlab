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
 * @since 1.0.0
 * @internal
 * @package ExpressionLab
 */
class Onboarding extends AdminPage {
	use Singleton;

	/**
	 * Determines whether the onboarding page should load.
	 *
	 * @since 1.0.0
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
	 * @since 1.0.0
	 * @internal
	 *
	 * @return array Associative array of localized environment data and translation strings.
	 */
	protected function get_script_data(): array {
		return array(
			'version' => EXPRESSION_LAB_VERSION,
			'user_id' => get_current_user_id(),
			'server'  => array(
				'sqlite_enabled' => extension_loaded( 'sqlite3' ),
				'sodium_enabled' => extension_loaded( 'sodium' ),
			),
			'i18n'    => $this->get_i18n_strings(),
		);
	}

	/**
	 * Retrieves localized internationalization strings for onboarding.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return array<string, string> Key-value pairs of translation strings.
	 */
	protected function get_i18n_strings(): array {
		return array(
			'Welcome'                          => __( 'Welcome', 'expression-lab' ),
			'System Requirements'              => __( 'System Requirements', 'expression-lab' ),
			'Key Generation'                   => __( 'Key Generation', 'expression-lab' ),
			'Verification'                     => __( 'Verification', 'expression-lab' ),
			'Welcome to Expression Lab'        => __( 'Welcome to Expression Lab', 'expression-lab' ),
			'The installation wizard guides the initial configuration of the plugin.' => __( 'The installation wizard guides the initial configuration of the plugin.', 'expression-lab' ),
			'Experimental Alpha Software'      => __( 'Experimental Alpha Software', 'expression-lab' ),
			'Expression Lab is in alpha. While designed with security-first patterns (AST sandboxing, client-side cryptographic keys), it has not undergone third-party security audits.' => __( 'Expression Lab is in alpha. While designed with security-first patterns (AST sandboxing, client-side cryptographic keys), it has not undergone third-party security audits.', 'expression-lab' ),
			'Intended for local development, testing, or staging environments.' => __( 'Intended for local development, testing, or staging environments.', 'expression-lab' ),
			'Do not use in production, high-security, governmental, or critical infrastructure sites.' => __( 'Do not use in production, high-security, governmental, or critical infrastructure sites.', 'expression-lab' ),
			'Provided "as is" under GPL-2.0 without warranty or liability for data loss.' => __( 'Provided "as is" under GPL-2.0 without warranty or liability for data loss.', 'expression-lab' ),
			'I understand Expression Lab is an unaudited alpha prototype and agree to use it only in non-critical environments at my own risk.' => __( 'I understand Expression Lab is an unaudited alpha prototype and agree to use it only in non-critical environments at my own risk.', 'expression-lab' ),
			'Begin install'                    => __( 'Begin install', 'expression-lab' ),
			'SQLite3 PHP extension'            => __( 'SQLite3 PHP extension', 'expression-lab' ),
			'Sodium PHP extension'             => __( 'Sodium PHP extension', 'expression-lab' ),
			'Web Crypto API (Browser)'         => __( 'Web Crypto API (Browser)', 'expression-lab' ),
			'OK'                               => __( 'OK', 'expression-lab' ),
			'Missing'                          => __( 'Missing', 'expression-lab' ),
			'<strong>SQLite3</strong> enables safe, in-memory database mirroring to prevent direct queries to the live database. <strong>Sodium</strong> and the <strong>Web Crypto API</strong> are required to generate, sign, and validate secure communications within the sandboxed environment.' => __( '<strong>SQLite3</strong> enables safe, in-memory database mirroring to prevent direct queries to the live database. <strong>Sodium</strong> and the <strong>Web Crypto API</strong> are required to generate, sign, and validate secure communications within the sandboxed environment.', 'expression-lab' ),
			'Next'                             => __( 'Next', 'expression-lab' ),
			'Passphrase:'                      => __( 'Passphrase:', 'expression-lab' ),
			'Min 8 characters'                 => __( 'Min 8 characters', 'expression-lab' ),
			'This passphrase is used only to derive the private key. Keep the following in mind:' => __( 'This passphrase is used only to derive the private key. Keep the following in mind:', 'expression-lab' ),
			'<strong>Zero storage:</strong> Neither this passphrase nor the derived private key are ever stored on the server or the database.' => __( '<strong>Zero storage:</strong> Neither this passphrase nor the derived private key are ever stored on the server or the database.', 'expression-lab' ),
			'<strong>Security:</strong> Avoid reusing WordPress passwords or trivial terms (e.g., <code>admin</code>, <code>12345678</code>).' => __( '<strong>Security:</strong> Avoid reusing WordPress passwords or trivial terms (e.g., <code>admin</code>, <code>12345678</code>).', 'expression-lab' ),
			'<strong>No recovery:</strong> If this passphrase is lost, cryptographic constants must be removed from <code>wp-config.php</code> to restart the setup.' => __( '<strong>No recovery:</strong> If this passphrase is lost, cryptographic constants must be removed from <code>wp-config.php</code> to restart the setup.', 'expression-lab' ),
			'Generating cryptographic keys...' => __( 'Generating cryptographic keys...', 'expression-lab' ),
			'Generate Keys'                    => __( 'Generate Keys', 'expression-lab' ),
			'Key generation failed: '          => __( 'Key generation failed: ', 'expression-lab' ),
			/* translators: %s: error message */
			'Key generation failed: %s'        => __( 'Key generation failed: %s', 'expression-lab' ),
			'Server Ownership Verification'    => __( 'Server Ownership Verification', 'expression-lab' ),
			'For security reasons, server access must be verified. Copy the following code and paste it into the <code>wp-config.php</code> file:' => __( 'For security reasons, server access must be verified. Copy the following code and paste it into the <code>wp-config.php</code> file:', 'expression-lab' ),
			'Once changes are saved to the <code>wp-config.php</code> file, reload this page to verify the setup and complete the installation.' => __( 'Once changes are saved to the <code>wp-config.php</code> file, reload this page to verify the setup and complete the installation.', 'expression-lab' ),
			'Expression Lab is a free, open-source plugin by Anderson Salas and contributors.' => __( 'Expression Lab is a free, open-source plugin by Anderson Salas and contributors.', 'expression-lab' ),
		);
	}

	/**
	 * Retrieves the asset handle name for onboarding scripts and styles.
	 *
	 * @since 1.0.0
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
	 * @since 1.0.0
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
