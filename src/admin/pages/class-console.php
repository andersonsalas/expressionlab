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
use ExpressionLab\Core\LanguageEngine;
use ExpressionLab\Core\Helper;
use ExpressionLab\Core\AdminPage;
use ExpressionLab\Core\Exceptions\SecurityException;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * Console admin page class.
 *
 * Manages the expression evaluator REPL console interface, asset registration,
 * authentication challenges, and AJAX expression evaluation endpoints.
 *
 * @since 1.0.0
 * @internal
 * @package ExpressionLab
 */
class Console extends AdminPage {
	use Singleton;

	/**
	 * Nonce action handle for AJAX requests.
	 *
	 * @since 1.0.0
	 * @internal
	 * @var string
	 */
	public const NONCE_ACTION = 'fpw_nonce';

	/**
	 * Determines whether the console page should load.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return bool True if the plugin is fully configured, false otherwise.
	 */
	public function should_load() {
		return Helper::plugin_is_fully_configured();
	}

	/**
	 * Initializes WordPress action hooks and AJAX endpoints.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'wp_ajax_expressionlab_evaluate_expression', array( $this, 'evaluate' ) );
		add_action( 'wp_ajax_expressionlab_get_outline', array( $this, 'get_outline' ) );
		add_action( 'wp_ajax_expressionlab_challenge', array( $this, 'challenge' ) );
		add_action( 'wp_ajax_expressionlab_search_users', array( $this, 'search_users' ) );
		add_action( 'wp_ajax_expressionlab_search_sites', array( $this, 'search_sites' ) );
	}

	/**
	 * Retrieves the asset handle name for console scripts and styles.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return string Asset handle name.
	 */
	protected function get_asset_name(): string {
		$asset_name = 'expressionlab-app';

		if ( ! Helper::user_is_expressionlab_admin() || filter_input( INPUT_GET, 'sandbox', FILTER_VALIDATE_BOOLEAN ) || true === EXPRESSION_LAB_DEBUG_MODE ) {
			return $asset_name;
		}

		return 'expressionlab-app' . ( EXPRESSION_LAB_SANDBOX_ENABLED ? '-sandbox' : '' );
	}

	/**
	 * Determines whether the console script should be enqueued.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return bool True if the user is an Expression Lab administrator, false otherwise.
	 */
	protected function should_enqueue_script(): bool {
		return Helper::user_is_expressionlab_admin();
	}

	/**
	 * Retrieves script localization data and initial environment state for the console.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return array Associative array of configuration, user lists, and translation strings.
	 */
	protected function get_script_data(): array {
		$server_ip = isset( $_SERVER['SERVER_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_ADDR'] ) ) : 'N/A';
		$user_ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'N/A';
		$sites     = array();
		$users     = array();

		if ( Helper::plugin_is_fully_configured() && Helper::user_is_expressionlab_admin() ) {
			$users_args = array(
				'fields' => array( 'ID', 'display_name' ),
				'number' => 20, // Load initial 20.
			);

			if ( is_multisite() ) {
				$users_args['blog_id'] = 0; // Return all network users instead of the current blog users.
			}

			$users = get_users( $users_args );
			$users = array_map(
				function ( $user ) {
					return array(
						'id'           => $user->ID,
						'display_name' => $user->display_name,
						'avatar'       => get_avatar_url( $user->ID, array( 'size' => 32 ) ),
					);
				},
				$users
			);
			if ( is_multisite() ) {
				$sites           = get_sites( array( 'number' => 20 ) ); // Get up to 20 sites for the dropdown.
				$main_site_id    = get_main_site_id();
				$formatted_sites = array();
				$main_site_data  = null;

				foreach ( $sites as $site ) {
					$is_main   = (int) $site->blog_id === (int) $main_site_id;
					$site_data = array(
						'id'     => $site->blog_id,
						'name'   => get_blog_details( $site->blog_id )->blogname . ( $is_main ? ' ' . __( '(Main)', 'expression-lab' ) : '' ),
						'avatar' => get_site_icon_url( 32, '', $site->blog_id ),
					);

					if ( $is_main ) {
						$main_site_data = $site_data;
					} else {
						$formatted_sites[] = $site_data;
					}
				}

				if ( ! $main_site_data ) {
					$main_site_data = array(
						'id'     => $main_site_id,
						'name'   => get_blog_details( $main_site_id )->blogname . ' ' . __( '(Main)', 'expression-lab' ),
						'avatar' => get_site_icon_url( 32, '', $main_site_id ),
					);
				}

				array_unshift( $formatted_sites, $main_site_data );
				$sites = $formatted_sites;
			} else {
				$sites = array(
					array(
						'id'     => get_current_blog_id(),
						'name'   => get_bloginfo( 'name' ),
						'avatar' => get_site_icon_url( 32 ),
					),
				);
			}
		}

		$banner = array(
			array(
				'type' => 'info',
				'text' => sprintf(
					/* translators: %s: Expression Lab version number */
					__( 'Expression Lab [Version %s]', 'expression-lab' ),
					EXPRESSION_LAB_VERSION
				) . "\n" . __( '(c) Anderson Salas and contributors. All rights reserved.', 'expression-lab' ),
			),
		);

		// Append all system and diagnostic messages.
		foreach ( LanguageEngine::get()->get_system_messages() as $system_msg ) {
			$banner[] = $system_msg;
		}

		if ( defined( 'EXPRESSION_LAB_HOOKS_ENABLED' ) && EXPRESSION_LAB_HOOKS_ENABLED ) {
			/**
			 * Filters the console banner messages displayed at startup.
			 *
			 * @param array<int, array{type: string, text: string}> $banner Array of banner message items.
			 */
			$filtered_banner = apply_filters( 'expressionlab_console_banners', $banner );
			if ( is_array( $filtered_banner ) ) {
				$banner = $filtered_banner;
			}
		}

		return array(
			'version'    => EXPRESSION_LAB_VERSION,
			'user_id'    => get_current_user_id(),
			'salt'       => defined( 'EXPRESSION_LAB_ADMIN_SALT' ) ? EXPRESSION_LAB_ADMIN_SALT : '',
			'public_key' => defined( 'EXPRESSION_LAB_ADMIN_PUBLIC_KEY' ) ? EXPRESSION_LAB_ADMIN_PUBLIC_KEY : '',
			'ajax_url'   => admin_url( 'admin-ajax.php' ),
			'rest_url'   => esc_url_raw( rest_url() ),
			'plugin_url' => EXPRESSION_LAB_URL,
			'assets_url' => EXPRESSION_LAB_ASSETS_URL,
			'nonce'      => wp_create_nonce( self::NONCE_ACTION ),
			'multisite'  => is_multisite(),
			'banner'     => $banner,
			'user'       => array(
				'display_name' => wp_get_current_user()->display_name,
				'gravatar'     => get_avatar_url( get_current_user_id(), array( 'size' => 96 ) ),
			),
			'settings'   => array(
				'enable_sandbox'      => EXPRESSION_LAB_SANDBOX_ENABLED,
				'hooks_enabled'       => defined( 'EXPRESSION_LAB_HOOKS_ENABLED' ) && EXPRESSION_LAB_HOOKS_ENABLED,
				'enable_cache'        => EXPRESSION_LAB_CACHE,
				'debug_mode'          => EXPRESSION_LAB_DEBUG_MODE,
				'browser_log'         => EXPRESSION_LAB_BROWSER_LOG,
				'database_readonly'   => EXPRESSION_LAB_DATABASE_READONLY,
				'filesystem_readonly' => EXPRESSION_LAB_FILESYSTEM_READONLY,
				'network_readonly'    => EXPRESSION_LAB_NETWORK_READONLY,
				'signature_duration'  => EXPRESSION_LAB_SIGNATURE_DURATION,
			),
			'site'       => array(
				'users' => $users,
				'sites' => $sites,
			),
			'server'     => array(
				'php_version'    => phpversion(),
				'wp_version'     => get_bloginfo( 'version' ),
				'server_ip'      => $server_ip,
				'user_ip'        => $user_ip,
				'debug_enabled'  => defined( 'WP_DEBUG' ) && WP_DEBUG,
				'sqlite_enabled' => extension_loaded( 'sqlite3' ),
				'sodium_enabled' => extension_loaded( 'sodium' ),
			),
			'i18n'       => $this->get_i18n_strings(),
		);
	}

	/**
	 * Retrieves localized internationalization strings for the console application.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return array<string, string> Key-value pairs of translation strings.
	 */
	protected function get_i18n_strings(): array {
		return array(
			// Toolbar & Controls.
			'Clear'                                      => __( 'Clear', 'expression-lab' ),
			'User'                                       => __( 'User', 'expression-lab' ),
			'Search users...'                            => __( 'Search users...', 'expression-lab' ),
			'Site'                                       => __( 'Site', 'expression-lab' ),
			'Search by domain/path...'                   => __( 'Search by domain/path...', 'expression-lab' ),
			'Current site'                               => __( 'Current site', 'expression-lab' ),
			'Library'                                    => __( 'Library', 'expression-lab' ),
			'Loading...'                                 => __( 'Loading...', 'expression-lab' ),
			'Error evaluating expression.'               => __( 'Error evaluating expression.', 'expression-lab' ),
			'Copy to clipboard'                          => __( 'Copy to clipboard', 'expression-lab' ),
			'Edit input'                                 => __( 'Edit input', 'expression-lab' ),
			'Search...'                                  => __( 'Search...', 'expression-lab' ),
			'Objects'                                    => __( 'Objects', 'expression-lab' ),
			'Functions'                                  => __( 'Functions', 'expression-lab' ),
			'Constants'                                  => __( 'Constants', 'expression-lab' ),
			'No results found'                           => __( 'No results found', 'expression-lab' ),
			'Loading outline'                            => __( 'Loading outline', 'expression-lab' ),
			'No outline available'                       => __( 'No outline available', 'expression-lab' ),

			// Documentation Tooltips.
			'Parameters:'                                => __( 'Parameters:', 'expression-lab' ),
			'Returns:'                                   => __( 'Returns:', 'expression-lab' ),
			'See also:'                                  => __( 'See also:', 'expression-lab' ),
			'Type:'                                      => __( 'Type:', 'expression-lab' ),
			'Value:'                                     => __( 'Value:', 'expression-lab' ),
			'Default:'                                   => __( 'Default:', 'expression-lab' ),

			// Footer Badges.
			'Hide Sensitive Info'                        => __( 'Hide Sensitive Info', 'expression-lab' ),
			'Show Sensitive Info'                        => __( 'Show Sensitive Info', 'expression-lab' ),
			'Server:'                                    => __( 'Server:', 'expression-lab' ),
			'User:'                                      => __( 'User:', 'expression-lab' ),
			'PHP:'                                       => __( 'PHP:', 'expression-lab' ),
			'WP:'                                        => __( 'WP:', 'expression-lab' ),
			'Debug:'                                     => __( 'Debug:', 'expression-lab' ),
			'Enabled'                                    => __( 'Enabled', 'expression-lab' ),
			'Disabled'                                   => __( 'Disabled', 'expression-lab' ),
			'Database:'                                  => __( 'Database:', 'expression-lab' ),
			'Files:'                                     => __( 'Files:', 'expression-lab' ),
			'Network:'                                   => __( 'Network:', 'expression-lab' ),
			'Locked'                                     => __( 'Locked', 'expression-lab' ),
			'Unlocked'                                   => __( 'Unlocked', 'expression-lab' ),
			'Requesting signature...'                    => __( 'Requesting signature...', 'expression-lab' ),
			'Signature expired'                          => __( 'Signature expired', 'expression-lab' ),
			'Signed'                                     => __( 'Signed', 'expression-lab' ),
			'Sandbox disabled'                           => __( 'Sandbox disabled', 'expression-lab' ),

			// Password Screen.
			'Enter your passphrase:'                     => __( 'Enter your passphrase:', 'expression-lab' ),
			'Please enter your passphrase.'              => __( 'Please enter your passphrase.', 'expression-lab' ),
			'Password too short.'                        => __( 'Password too short.', 'expression-lab' ),
			'Salt not found in configuration.'           => __( 'Salt not found in configuration.', 'expression-lab' ),
			'Invalid passphrase.'                        => __( 'Invalid passphrase.', 'expression-lab' ),
			'Failed: '                                   => __( 'Failed: ', 'expression-lab' ),
			/* translators: %s: error message */
			'Failed: %s'                                 => __( 'Failed: %s', 'expression-lab' ),
			'Unlocking...'                               => __( 'Unlocking...', 'expression-lab' ),
			'Unlock'                                     => __( 'Unlock', 'expression-lab' ),
			'If you forgot this passphrase, you must delete the Expression Lab constants from your <code>wp-config.php</code> to trigger a new installation.' => __( 'If you forgot this passphrase, you must delete the Expression Lab constants from your <code>wp-config.php</code> to trigger a new installation.', 'expression-lab' ),
			'An error occurred during verification.'     => __( 'An error occurred during verification.', 'expression-lab' ),

			// About Modal.
			'About'                                      => __( 'About', 'expression-lab' ),
			'Close'                                      => __( 'Close', 'expression-lab' ),
			'© Anderson Salas and contributors.'         => __( '© Anderson Salas and contributors.', 'expression-lab' ),
			'Website'                                    => __( 'Website', 'expression-lab' ),
			'Docs'                                       => __( 'Docs', 'expression-lab' ),
			'GitHub'                                     => __( 'GitHub', 'expression-lab' ),
			'This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.' => __( 'This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.', 'expression-lab' ),
			'This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.' => __( 'This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.', 'expression-lab' ),
			'You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.' => __( 'You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.', 'expression-lab' ),

			// Dialog Modal.
			'Accept'                                     => __( 'Accept', 'expression-lab' ),
			'Cancel'                                     => __( 'Cancel', 'expression-lab' ),

			// Library Modal.
			'Save changes?'                              => __( 'Save changes?', 'expression-lab' ),
			'You have unsaved changes in the current snippet. Do you want to save them?' => __( 'You have unsaved changes in the current snippet. Do you want to save them?', 'expression-lab' ),
			'Save'                                       => __( 'Save', 'expression-lab' ),
			"Don't save"                                 => __( "Don't save", 'expression-lab' ),
			'Snippet library'                            => __( 'Snippet library', 'expression-lab' ),
			'Save your frequent snippets here to reuse them easily.' => __( 'Save your frequent snippets here to reuse them easily.', 'expression-lab' ),
			'Pro tip:'                                   => __( 'Pro tip:', 'expression-lab' ),
			'select Use local file system to save snippets to your hard drive.' => __( 'select Use local file system to save snippets to your hard drive.', 'expression-lab' ),
			/* translators: %s: directory name */
			'Permission required to access folder "%s".' => __( 'Permission required to access folder "%s".', 'expression-lab' ),
			'Grant Access'                               => __( 'Grant Access', 'expression-lab' ),
			'File System Error'                          => __( 'File System Error', 'expression-lab' ),
			'Could not connect to local folder.'         => __( 'Could not connect to local folder.', 'expression-lab' ),
			'Import Error'                               => __( 'Import Error', 'expression-lab' ),
			'Failed to select file.'                     => __( 'Failed to select file.', 'expression-lab' ),
			'Invalid JSON'                               => __( 'Invalid JSON', 'expression-lab' ),
			'The selected file is not a valid JSON document.' => __( 'The selected file is not a valid JSON document.', 'expression-lab' ),
			'Empty File'                                 => __( 'Empty File', 'expression-lab' ),
			'No snippets found in the selected file.'    => __( 'No snippets found in the selected file.', 'expression-lab' ),
			'Import Complete'                            => __( 'Import Complete', 'expression-lab' ),
			/* translators: %d: count of imported snippets */
			'Successfully imported %d snippet(s).'       => __( 'Successfully imported %d snippet(s).', 'expression-lab' ),
			'New Snippet'                                => __( 'New Snippet', 'expression-lab' ),
			'Delete Snippet'                             => __( 'Delete Snippet', 'expression-lab' ),
			'Are you sure you want to delete this snippet?' => __( 'Are you sure you want to delete this snippet?', 'expression-lab' ),
			'Delete'                                     => __( 'Delete', 'expression-lab' ),
			'Search'                                     => __( 'Search', 'expression-lab' ),
			'Add Snippet'                                => __( 'Add Snippet', 'expression-lab' ),
			'(Untitled)'                                 => __( '(Untitled)', 'expression-lab' ),
			'Untitled'                                   => __( 'Untitled', 'expression-lab' ),
			'No snippets found.'                         => __( 'No snippets found.', 'expression-lab' ),
			'Store snippets in a local folder using native File System API' => __( 'Store snippets in a local folder using native File System API', 'expression-lab' ),
			'Use local file system'                      => __( 'Use local file system', 'expression-lab' ),
			'Import or export snippets'                  => __( 'Import or export snippets', 'expression-lab' ),
			'Import/Export'                              => __( 'Import/Export', 'expression-lab' ),
			'Import JSON'                                => __( 'Import JSON', 'expression-lab' ),
			'Export JSON'                                => __( 'Export JSON', 'expression-lab' ),
			'Snippet title'                              => __( 'Snippet title', 'expression-lab' ),
			'No snippet selected.'                       => __( 'No snippet selected.', 'expression-lab' ),
			'Insert'                                     => __( 'Insert', 'expression-lab' ),
			'OK'                                         => __( 'OK', 'expression-lab' ),

			// Dropdown UI.
			'Select'                                     => __( 'Select', 'expression-lab' ),
			'No results found.'                          => __( 'No results found.', 'expression-lab' ),

			// Accessibility & UI Elements.
			'Site Icon'                                  => __( 'Site Icon', 'expression-lab' ),
			'User Avatar'                                => __( 'User Avatar', 'expression-lab' ),
			'Avatar'                                     => __( 'Avatar', 'expression-lab' ),

			// Visualizations.
			'Raw'                                        => __( 'Raw', 'expression-lab' ),
			'Table'                                      => __( 'Table', 'expression-lab' ),
			/* translators: %s: visualization type */
			'Unknown visualization type: %s'             => __( 'Unknown visualization type: %s', 'expression-lab' ),
			'Paginate'                                   => __( 'Paginate', 'expression-lab' ),
			/* translators: %d: page size */
			'%d per page'                                => __( '%d per page', 'expression-lab' ),
			/* translators: %d: number of rows */
			'%d rows'                                    => __( '%d rows', 'expression-lab' ),
			/* translators: %d: total number of rows before filtering */
			'(filtered from %d)'                         => __( '(filtered from %d)', 'expression-lab' ),
			'&laquo; Prev'                               => __( '&laquo; Prev', 'expression-lab' ),
			'Next &raquo;'                               => __( 'Next &raquo;', 'expression-lab' ),
			/* translators: 1: current page number, 2: total pages */
			'Page %1$d / %2$d'                           => __( 'Page %1$d / %2$d', 'expression-lab' ),
			'Export CSV'                                 => __( 'Export CSV', 'expression-lab' ),
			/* translators: %s: column name */
			'Search %s'                                  => __( 'Search %s', 'expression-lab' ),
			'Click to expand/collapse'                   => __( 'Click to expand/collapse', 'expression-lab' ),
			'No data to display.'                        => __( 'No data to display.', 'expression-lab' ),
		);
	}

	/**
	 * Renders the console application screen or access restriction banner.
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

		if ( Helper::plugin_is_fully_configured() && ! Helper::user_is_expressionlab_admin() ) {
			echo '<div class="wrap">
				<div class="expressionlab-app">
					<div class="expressionlab-admin-banner">
						<div class="codicon codicon-lock"></div>
						<h2>' . esc_html__( 'Access Restricted', 'expression-lab' ) . '</h2>
						<p>' . esc_html__( 'For security reasons, access to this console is limited to a single administrator designated at the server level.', 'expression-lab' ) . '</p>
						<p class="details">' . sprintf( wp_kses( /* translators: 1: admin user ID constant name, 2: config file name */ __( 'If you are the site owner, verify that your current user ID matches the %1$s constant in your %2$s file.', 'expression-lab' ), array( 'code' => array() ) ), '<code>EXPRESSION_LAB_ADMIN_USER_ID</code>', '<code>wp-config.php</code>' ) . '</p>
					</div>
				</div>
			</div>';
			return;
		}

		if ( false === EXPRESSION_LAB_SANDBOX_ENABLED || true === EXPRESSION_LAB_DEBUG_MODE ) {
			echo '<div class="wrap"><div class="expressionlab-app"></div></div>';
		} else {
			$sandbox_url = $this->get_page_url( array( 'sandbox' => '1' ) );
			echo '<div class="wrap"><iframe src="' . esc_url( $sandbox_url ) . '" id="expressionlab-sandbox" class="expressionlab-app" sandbox="allow-scripts"></iframe></div>';
		}
	}

	/**
	 * Determines whether the sandbox iframe should be rendered.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return bool True if the sandbox should be rendered, false otherwise.
	 */
	public function should_render_sandbox() {
		return parent::should_render_sandbox() && Helper::user_is_expressionlab_admin();
	}

	/**
	 * Validates an incoming AJAX request.
	 *
	 * Verifies nonce tokens, user capabilities, and detached libsodium signatures
	 * for requests originated from the sandboxed iframe.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @throws SecurityException If authorization, nonce, or signature verification fails.
	 * @return true Always returns true on successful validation.
	 */
	public function validate_ajax_request(): bool {
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';

		if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
			throw new SecurityException( esc_html__( 'Sodium extension is required for AJAX requests.', 'expression-lab' ), 500 );
		}

		if ( ! Helper::plugin_is_fully_configured() ) {
			throw new SecurityException( esc_html__( 'Plugin is not fully configured.', 'expression-lab' ), 400 );
		}

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			throw new SecurityException( esc_html__( 'Unauthorized', 'expression-lab' ), 403 );
		}

		if ( ! Helper::user_is_expressionlab_admin() ) {
			throw new SecurityException( esc_html__( 'Unauthorized', 'expression-lab' ), 403 );
		}

		// Bypass cryptographic signature validation during development.
		if ( false === EXPRESSION_LAB_SANDBOX_ENABLED ) {
			return true;
		}

		// Signature validation for requests coming from the sandboxed iframe.
		$signature_b64 = isset( $_SERVER['HTTP_X_EXPRESSIONLAB_SIGNATURE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_EXPRESSIONLAB_SIGNATURE'] ) ) : '';

		if ( empty( $signature_b64 ) ) {
			throw new SecurityException( esc_html__( 'Invalid signature.', 'expression-lab' ), 403 );
		}

		// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- We need to decode the signature from base64.
		$signature  = base64_decode( $signature_b64, true );
		$public_key = base64_decode( EXPRESSION_LAB_ADMIN_PUBLIC_KEY, true );
		// phpcs:enable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if (
			false === $signature ||
			false === $public_key ||
			strlen( $signature ) !== SODIUM_CRYPTO_SIGN_BYTES ||
			strlen( $public_key ) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
		) {
			throw new SecurityException( esc_html__( 'Invalid signature.', 'expression-lab' ), 403 );
		}

		// Get the payload depending on the HTTP method.
		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';

		if ( 'GET' === $request_method ) {
			// For GET requests, we reconstruct the exact query string used for signing in JS (which includes the leading '?').
			$raw_payload = isset( $_SERVER['QUERY_STRING'] ) ? '?' . wp_unslash( $_SERVER['QUERY_STRING'] ) : '?'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		} else {
			// For POST requests, get exactly the raw body that was sent.
			$raw_payload = file_get_contents( 'php://input' );
		}

		// Verify with libsodium.
		try {
			$is_valid = sodium_crypto_sign_verify_detached( $signature, $raw_payload, $public_key );
		} catch ( \Throwable $e ) {
			$is_valid = false;
		}

		if ( ! $is_valid ) {
			throw new SecurityException( esc_html__( 'Signature verification failed.', 'expression-lab' ), 403 );
		}

		return true;
	}

	/**
	 * Retrieves the outline of available classes and methods via AJAX.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return void
	 */
	public function get_outline() {
		try {
			$this->validate_ajax_request();
		} catch ( SecurityException $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ), $e->get_status_code() );
		}

		$challenge_nonce = isset( $_POST['challenge_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['challenge_nonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$timestamp       = isset( $_POST['timestamp'] ) ? sanitize_text_field( wp_unslash( $_POST['timestamp'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( empty( $challenge_nonce ) && isset( $_GET['challenge_nonce'] ) ) {
			$challenge_nonce = sanitize_text_field( wp_unslash( $_GET['challenge_nonce'] ) );
		}
		if ( empty( $timestamp ) && isset( $_GET['timestamp'] ) ) {
			$timestamp = sanitize_text_field( wp_unslash( $_GET['timestamp'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$new_challenge = $this->verify_and_rotate_challenge( $challenge_nonce, $timestamp );

		if ( ! $new_challenge ) {
			wp_send_json_error(
				array(
					'code'    => 'challenge_required',
					'message' => __( 'Invalid or expired challenge.', 'expression-lab' ),
				),
				403
			);
		}

		$data = LanguageEngine::get()->get_outline();

		wp_send_json_success(
			array(
				'data'      => $data,
				'challenge' => $new_challenge,
			)
		);
	}

	/**
	 * Verifies and rotates the challenge nonce for cryptographic requests.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string $request_nonce     The nonce sent by the client.
	 * @param int    $request_timestamp The timestamp sent by the client.
	 * @return array|false New challenge array with nonce and timestamp, or false on failure.
	 */
	private function verify_and_rotate_challenge( $request_nonce, $request_timestamp ) {
		// Bypass challenge verification during development.
		if ( false === EXPRESSION_LAB_SANDBOX_ENABLED ) {
			return array(
				'nonce'     => 'simulated',
				'timestamp' => time(),
			);
		}

		$user_id = get_current_user_id();
		$stored  = get_user_meta( $user_id, 'expressionlab_challenge', true );

		if ( ! is_array( $stored ) || empty( $stored['nonce'] ) ) {
			return false;
		}

		// Verify nonce matches.
		if ( ! hash_equals( $stored['nonce'], $request_nonce ) ) {
			return false;
		}

		if ( abs( time() - intval( $stored['timestamp'] ) ) > EXPRESSION_LAB_SIGNATURE_DURATION ) {
			return false;
		}

		if ( abs( time() - intval( $request_timestamp ) ) > EXPRESSION_LAB_SIGNATURE_DURATION ) {
			return false;
		}

		// Rotate.
		$new_nonce     = bin2hex( random_bytes( 16 ) );
		$new_timestamp = time();

		update_user_meta(
			$user_id,
			'expressionlab_challenge',
			array(
				'nonce'     => $new_nonce,
				'timestamp' => $new_timestamp,
			)
		);

		return array(
			'nonce' => $new_nonce,
			'ts'    => $new_timestamp,
		);
	}

	/**
	 * Evaluates an expression string sent via AJAX and returns the output.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return void
	 */
	public function evaluate() {
		try {
			$this->validate_ajax_request();
		} catch ( SecurityException $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ), $e->get_status_code() );
		}

		$expression      = isset( $_POST['expression'] ) ? wp_unslash( $_POST['expression'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$challenge_nonce = isset( $_POST['challenge_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['challenge_nonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$timestamp       = isset( $_POST['timestamp'] ) ? sanitize_text_field( wp_unslash( $_POST['timestamp'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$user_id         = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$site_id         = isset( $_POST['site_id'] ) ? absint( $_POST['site_id'] ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( is_multisite() && null === $site_id ) {
			$site_id = get_current_blog_id();
		}

		if ( 0 === strlen( $expression ?? '' ) ) {
			wp_send_json_error( array( 'message' => __( 'No expression provided', 'expression-lab' ) ), 400 );
		}

		$new_challenge = $this->verify_and_rotate_challenge( $challenge_nonce, $timestamp );
		if ( ! $new_challenge ) {
			wp_send_json_error(
				array(
					'code'    => 'challenge_required',
					'message' => __( 'Invalid or expired challenge.', 'expression-lab' ),
				),
				403
			);
		}

		try {
			$evaluation = LanguageEngine::get()
				->set_user_id( $user_id )
				->set_site_id( $site_id )
				->evaluate( $expression );

			$result      = $evaluation['result'];
			$output      = $evaluation['output'];
			$errors      = $evaluation['errors'];
			$object_type = $evaluation['object_type'];
		} catch ( \Throwable $e ) {
			$error_message = '';

			if ( $e instanceof \Symfony\Component\ExpressionLanguage\SyntaxError ) {
				$msg = $e->getMessage();
				// Symfony converts newlines to spaces during lexing. We restore the original multiline expression and remove trailing dot.
				if ( preg_match( '/^(.*?\baround position \d+)(?: for expression `.*`)?(\.?\s*Did you mean "[^"]*"\??)?[.?\s]*$/s', $msg, $matches ) ) {
					$suggestion = ! empty( $matches[2] ) ? '. ' . ltrim( str_replace( '`', '\`', $matches[2] ), '. ' ) : '';
					$details    = str_replace( '`', '\`', $matches[1] );
					/* translators: 1: syntax error details, 2: expression code span, 3: suggestion */
					$error_message = sprintf( __( 'Syntax error: %1$s for expression %2$s%3$s', 'expression-lab' ), $details, Helper::format_markdown_code_span( $expression ), $suggestion );
				} else {
					/* translators: %s: syntax error details */
					$error_message = sprintf( __( 'Syntax error: %s', 'expression-lab' ), rtrim( str_replace( '`', '\`', $msg ), '.' ) );
				}
			} elseif ( ! empty( $e->getMessage() ) ) {
				$error_message = $e->getMessage();
			} else {
				/* translators: %s: exception class name */
				$error_message = sprintf( __( 'Expression error: %s', 'expression-lab' ), get_class( $e ) );
			}

			wp_send_json_error( array( 'message' => $error_message ), 400 );
			return;
		}

		// Formatting result...
		$wp_debug_enabled     = defined( 'WP_DEBUG' ) && WP_DEBUG;
		$full_captured_output = null;

		if ( $wp_debug_enabled ) {
			$full_captured_output = $output;
			if ( ! empty( $errors ) ) {
				$full_captured_output .= ( $full_captured_output ? "\n" : '' ) . implode( "\n", $errors );
			}
		}

		if ( is_string( $result ) && ! is_numeric( $result ) ) {
			if ( false !== strpos( $result, '\'' ) && false === strpos( $result, '"' ) ) {
				$result = '"' . $result . '"';
			} elseif ( false !== strpos( $result, '\'' ) && false !== strpos( $result, '"' ) ) {
				$result = '"' . addcslashes( $result, '"\\' ) . '"';
			} else {
				$result = '\'' . $result . '\'';
			}
		}

		if ( is_float( $result ) && ( is_nan( $result ) || is_infinite( $result ) ) ) {
			$result = (string) $result; // Prevent JSON encoding issues.
		}

		$response = array(
			'format'          => 'json',
			'challenge'       => $new_challenge,
			'result'          => $result,
			'object_type'     => $object_type,
			'captured_output' => $full_captured_output,
			'messages'        => LanguageEngine::get()->get_messages(),
			'visualizations'  => LanguageEngine::get()->get_visualizations(),
		);

		wp_send_json_success( $response );
	}



	/**
	 * Generates a new challenge nonce and timestamp for the client via AJAX.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return void
	 */
	public function challenge() {
		try {
			$this->validate_ajax_request();
		} catch ( SecurityException $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ), $e->get_status_code() );
		}
		$user_id      = get_current_user_id();
		$stored       = get_user_meta( $user_id, 'expressionlab_challenge', true );
		$current_time = time();

		// If there's already a valid and recent challenge (e.g., from the last 5 seconds),
		// we return that same one instead of burning it and overwriting the database.
		if ( is_array( $stored ) && ! empty( $stored['timestamp'] ) && abs( $current_time - intval( $stored['timestamp'] ) ) < 5 ) {
			wp_send_json_success(
				array(
					'nonce'     => $stored['nonce'],
					'timestamp' => $stored['timestamp'],
				)
			);
			return;
		}

		$nonce = bin2hex( random_bytes( 16 ) );

		update_user_meta(
			$user_id,
			'expressionlab_challenge',
			array(
				'nonce'     => $nonce,
				'timestamp' => $current_time,
			)
		);

		wp_send_json_success(
			array(
				'nonce'     => $nonce,
				'timestamp' => $current_time,
			)
		);
	}

	/**
	 * Searches and paginates users via AJAX.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return void
	 */
	public function search_users() {
		try {
			$this->validate_ajax_request();
		} catch ( SecurityException $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ), $e->get_status_code() );
		}

		$challenge_nonce = isset( $_POST['challenge_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['challenge_nonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$timestamp       = isset( $_POST['timestamp'] ) ? absint( $_POST['timestamp'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( empty( $challenge_nonce ) && isset( $_GET['challenge_nonce'] ) ) {
			$challenge_nonce = sanitize_text_field( wp_unslash( $_GET['challenge_nonce'] ) );
		}
		if ( empty( $timestamp ) && isset( $_GET['timestamp'] ) ) {
			$timestamp = absint( $_GET['timestamp'] );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$new_challenge = $this->verify_and_rotate_challenge( $challenge_nonce, $timestamp );

		if ( ! $new_challenge ) {
			wp_send_json_error(
				array(
					'code'    => 'challenge_required',
					'message' => __( 'Challenge verification failed.', 'expression-lab' ),
				),
				403
			);
		}

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$page   = isset( $_POST['page'] ) ? absint( wp_unslash( $_POST['page'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$number = 100;

		$args = array(
			'fields' => array( 'ID', 'display_name' ),
			'number' => $number,
			'paged'  => $page,
		);

		if ( is_multisite() ) {
			$args['blog_id'] = 0; // Return all network users instead of the current blog users.
		}

		if ( ! empty( $search ) ) {
			$args['search']         = '*' . $search . '*';
			$args['search_columns'] = array( 'user_login', 'user_nicename', 'user_email', 'display_name' );
		}

		$user_query = new \WP_User_Query( $args );
		$users      = array_map(
			function ( $user ) {
				return array(
					'id'           => $user->ID,
					'display_name' => $user->display_name,
					'avatar'       => get_avatar_url( $user->ID, array( 'size' => 32 ) ),
				);
			},
			$user_query->get_results()
		);

		$total_users = $user_query->get_total();

		wp_send_json_success(
			array(
				'users'     => $users,
				'total'     => $total_users,
				'page'      => $page,
				'max_pages' => ceil( $total_users / $number ),
				'challenge' => $new_challenge,
			)
		);
	}

	/**
	 * Searches and paginates subsites in a multisite network via AJAX.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return void
	 */
	public function search_sites() {
		try {
			$this->validate_ajax_request();
		} catch ( SecurityException $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ), $e->get_status_code() );
		}

		if ( ! is_multisite() ) {
			wp_send_json_error( array( 'message' => __( 'Not a multisite installation.', 'expression-lab' ) ) );
		}

		$challenge_nonce = isset( $_POST['challenge_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['challenge_nonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$timestamp       = isset( $_POST['timestamp'] ) ? absint( $_POST['timestamp'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( empty( $challenge_nonce ) && isset( $_GET['challenge_nonce'] ) ) {
			$challenge_nonce = sanitize_text_field( wp_unslash( $_GET['challenge_nonce'] ) );
		}
		if ( empty( $timestamp ) && isset( $_GET['timestamp'] ) ) {
			$timestamp = absint( $_GET['timestamp'] );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$new_challenge = $this->verify_and_rotate_challenge( $challenge_nonce, $timestamp );

		if ( ! $new_challenge ) {
			wp_send_json_error(
				array(
					'code'    => 'challenge_required',
					'message' => __( 'Challenge verification failed.', 'expression-lab' ),
				),
				403
			);
		}

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$page   = isset( $_POST['page'] ) ? absint( wp_unslash( $_POST['page'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$number = 100;

		$args = array(
			'number' => $number,
			'offset' => ( $page - 1 ) * $number,
		);

		if ( ! empty( $search ) ) {
			$args['search'] = $search;
		}

		$site_query = new \WP_Site_Query( $args );
		$sites      = array_map(
			function ( $site ) {
				$is_main_site = ( (int) get_main_site_id() === (int) $site->blog_id );
				return array(
					'id'     => $site->blog_id,
					'name'   => get_blog_details( $site->blog_id )->blogname . ( $is_main_site ? ' ' . __( '(Main)', 'expression-lab' ) : '' ),
					'avatar' => null,
				);
			},
			$site_query->get_sites()
		);

		$total_sites = $site_query->found_sites;

		wp_send_json_success(
			array(
				'sites'     => $sites,
				'total'     => $total_sites,
				'page'      => $page,
				'max_pages' => ceil( $total_sites / $number ),
				'challenge' => $new_challenge,
			)
		);
	}
}
