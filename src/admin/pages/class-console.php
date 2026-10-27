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
use ExpressionLab\Core\AuditLogger;
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
 * @since 0.0.1
 * @internal
 * @package ExpressionLab
 */
class Console extends AdminPage {
	use Singleton;

	/**
	 * Nonce action handle for AJAX requests.
	 *
	 * @since 0.0.1
	 * @internal
	 * @var string
	 */
	public const NONCE_ACTION = 'fpw_nonce';

	/**
	 * Determines whether the console page should load.
	 *
	 * @since 0.0.1
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
	 * @since 0.0.1
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
		add_action( 'wp_ajax_expressionlab_get_filesystem', array( $this, 'get_filesystem' ) );
	}

	/**
	 * Retrieves the asset handle name for console scripts and styles.
	 *
	 * @since 0.0.1
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
	 * @since 0.0.1
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
	 * @since 0.0.1
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
	 * @since 0.0.1
	 * @internal
	 *
	 * @return array<string, string> Key-value pairs of translation strings.
	 */
	protected function get_i18n_strings(): array {
		return array(
			// Toolbar & Controls.
			'Clear'                                      => __( 'Clear', 'expression-lab' ),
			'Clear session'                              => __( 'Clear session', 'expression-lab' ),
			'Format code (Shift+Alt+F)'                  => __( 'Format code (Shift+Alt+F)', 'expression-lab' ),
			'Settings'                                   => __( 'Settings', 'expression-lab' ),
			'About Expression Lab'                       => __( 'About Expression Lab', 'expression-lab' ),
			'Toggle sidebar outline'                     => __( 'Toggle sidebar outline', 'expression-lab' ),
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

			// Tabs & Workspaces.
			'New Console tab'                            => __( 'New Console tab', 'expression-lab' ),
			'New Scratchpad tab'                         => __( 'New Scratchpad tab', 'expression-lab' ),
			'New tab options'                            => __( 'New tab options', 'expression-lab' ),
			'Close tab'                                  => __( 'Close tab', 'expression-lab' ),
			'Close all tabs'                             => __( 'Close all tabs', 'expression-lab' ),
			'Close all except current'                   => __( 'Close all except current', 'expression-lab' ),
			'Close all to the right'                     => __( 'Close all to the right', 'expression-lab' ),
			'Rename'                                     => __( 'Rename', 'expression-lab' ),
			'Scroll tabs left'                           => __( 'Scroll tabs left', 'expression-lab' ),
			'Scroll tabs right'                          => __( 'Scroll tabs right', 'expression-lab' ),
			'Console'                                    => __( 'Console', 'expression-lab' ),
			'Scratchpad'                                 => __( 'Scratchpad', 'expression-lab' ),

			// Scratchpad & Execution.
			'Run (Ctrl+Enter / Cmd+Enter / Ctrl+R)'      => __( 'Run (Ctrl+Enter / Cmd+Enter / Ctrl+R)', 'expression-lab' ),
			'Run expression (Scratchpad mode only)'      => __( 'Run expression (Scratchpad mode only)', 'expression-lab' ),
			'Save snippet to Library'                    => __( 'Save snippet to Library', 'expression-lab' ),
			'Save snippet to Library (Scratchpad mode only)' => __( 'Save snippet to Library (Scratchpad mode only)', 'expression-lab' ),
			'Toggle output panel'                        => __( 'Toggle output panel', 'expression-lab' ),
			'Toggle output panel (Scratchpad mode only)' => __( 'Toggle output panel (Scratchpad mode only)', 'expression-lab' ),
			'Evaluating expression...'                   => __( 'Evaluating expression...', 'expression-lab' ),
			'Clear output'                               => __( 'Clear output', 'expression-lab' ),
			'Hide output panel'                          => __( 'Hide output panel', 'expression-lab' ),
			'Output'                                     => __( 'Output', 'expression-lab' ),
			/* translators: %s: object type */
			'Object Type: %s'                            => __( 'Object Type: %s', 'expression-lab' ),
			'No deferred console messages available.'    => __( 'No deferred console messages available.', 'expression-lab' ),
			'Run an expression (Ctrl+Enter or Play) to view results here.' => __( 'Run an expression (Ctrl+Enter or Play) to view results here.', 'expression-lab' ),
			'Drag to resize / Double-click to auto-fit'  => __( 'Drag to resize / Double-click to auto-fit', 'expression-lab' ),
			'Scratchpad Snippet'                         => __( 'Scratchpad Snippet', 'expression-lab' ),

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

			// Settings Modal.
			'Default new tab layout'                     => __( 'Default new tab layout', 'expression-lab' ),
			'Choose the default layout for newly created tabs. This also affects the action of the New Tab button in the tab bar.' => __( 'Choose the default layout for newly created tabs. This also affects the action of the New Tab button in the tab bar.', 'expression-lab' ),
			'Interactive single-expression evaluation with immediate output.' => __( 'Interactive single-expression evaluation with immediate output.', 'expression-lab' ),
			'Multi-line script editor with dedicated execution and output inspection panels.' => __( 'Multi-line script editor with dedicated execution and output inspection panels.', 'expression-lab' ),
			'Browser cache'                              => __( 'Browser cache', 'expression-lab' ),
			'Clear cached WordPress functions, classes, and outline symbols stored in your browser.' => __( 'Clear cached WordPress functions, classes, and outline symbols stored in your browser.', 'expression-lab' ),
			'Clear cache'                                => __( 'Clear cache', 'expression-lab' ),
			'Clearing...'                                => __( 'Clearing...', 'expression-lab' ),
			'Refresh filesystem cache'                   => __( 'Refresh filesystem cache', 'expression-lab' ),

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
			'Duplicate Snippet Name'                     => __( 'Duplicate Snippet Name', 'expression-lab' ),
			/* translators: %s: snippet name */
			'A snippet named "%s" already exists. Please choose a different name.' => __( 'A snippet named "%s" already exists. Please choose a different name.', 'expression-lab' ),
			'Syntax error in snippet'                    => __( 'Syntax error in snippet', 'expression-lab' ),
			'Add tags...'                                => __( 'Add tags...', 'expression-lab' ),
			'Remove tag'                                 => __( 'Remove tag', 'expression-lab' ),
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

			// Visualizations & Data Viewer.
			'Raw'                                        => __( 'Raw', 'expression-lab' ),
			'Preview'                                    => __( 'Preview', 'expression-lab' ),
			'Data viewer'                                => __( 'Data viewer', 'expression-lab' ),
			/* translators: %s: column name */
			'Data viewer - %s'                           => __( 'Data viewer - %s', 'expression-lab' ),
			'View data...'                               => __( 'View data...', 'expression-lab' ),
			'Copy cell value'                            => __( 'Copy cell value', 'expression-lab' ),
			'Word Wrap'                                  => __( 'Word Wrap', 'expression-lab' ),
			'Auto'                                       => __( 'Auto', 'expression-lab' ),
			'No highlighting'                            => __( 'No highlighting', 'expression-lab' ),
			'No preview available'                       => __( 'No preview available', 'expression-lab' ),
			'Copied!'                                    => __( 'Copied!', 'expression-lab' ),
			'Expand all'                                 => __( 'Expand all', 'expression-lab' ),
			'Collapse all'                               => __( 'Collapse all', 'expression-lab' ),
			'Search in data...'                          => __( 'Search in data...', 'expression-lab' ),
			'Search path (e.g. response.args[0])...'     => __( 'Search path (e.g. response.args[0])...', 'expression-lab' ),
			'Free text search'                           => __( 'Free text search', 'expression-lab' ),
			'Path expression (Symfony / Dot-syntax)'     => __( 'Path expression (Symfony / Dot-syntax)', 'expression-lab' ),
			'Clear search'                               => __( 'Clear search', 'expression-lab' ),
			'No matches found'                           => __( 'No matches found', 'expression-lab' ),
			'Path found'                                 => __( 'Path found', 'expression-lab' ),
			'Path not found'                             => __( 'Path not found', 'expression-lab' ),
			'1 match'                                    => __( '1 match', 'expression-lab' ),
			/* translators: %d: number of search matches */
			'%d matches'                                 => __( '%d matches', 'expression-lab' ),
			/* translators: %s: PHP class that declares the private property */
			'Declared in %s'                             => __( 'Declared in %s', 'expression-lab' ),
			'Table'                                      => __( 'Table', 'expression-lab' ),
			'Visualization'                              => __( 'Visualization', 'expression-lab' ),
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
	 * @since 0.0.1
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
	 * @since 0.0.1
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
	 * @since 0.0.1
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
	 * @since 0.0.1
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
	 * AJAX endpoint to retrieve bounded filesystem metadata and presets asynchronously.
	 *
	 * @since 0.3.0
	 * @internal
	 *
	 * @return void
	 */
	public function get_filesystem() {
		try {
			$this->validate_ajax_request();
		} catch ( SecurityException $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ), $e->get_status_code() );
		}

		list( $challenge_nonce, $timestamp ) = $this->get_signed_challenge_params();

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

		$data = array(
			'presets' => $this->build_filesystem_presets(),
			'tree'    => $this->build_bounded_filesystem_tree(),
			'limits'  => array(
				'max_read_bytes' => \ExpressionLab\Core\Services\Files::MAX_READ_BYTES,
				'max_tail_bytes' => \ExpressionLab\Core\Services\Files::MAX_TAIL_BYTES,
			),
		);

		wp_send_json_success(
			array(
				'data'      => $data,
				'challenge' => $new_challenge,
			)
		);
	}

	/**
	 * Reads the challenge nonce and timestamp from the signed part of the request.
	 *
	 * The signature covers the query string on GET requests and the raw body on
	 * POST requests, so parameters are read only from the matching superglobal.
	 *
	 * @since 0.3.0
	 * @internal
	 *
	 * @return array{0: string, 1: string} Challenge nonce and timestamp.
	 */
	private function get_signed_challenge_params(): array {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		$source = 'POST' === $method ? $_POST : $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended

		return array(
			isset( $source['challenge_nonce'] ) ? sanitize_text_field( wp_unslash( $source['challenge_nonce'] ) ) : '',
			isset( $source['timestamp'] ) ? sanitize_text_field( wp_unslash( $source['timestamp'] ) ) : '',
		);
	}

	/**
	 * Verifies and rotates the challenge nonce for cryptographic requests.
	 *
	 * @since 0.0.1
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
	 * Creates a one-shot audit recorder for an authorized expression evaluation.
	 *
	 * The returned closure accepts a status (`success`, `error` or `aborted`) and
	 * writes exactly one audit record: every call after the first one is a no-op.
	 * This allows registering it as a shutdown function (status `aborted`) to
	 * capture fatal errors and timeouts without duplicating regular records.
	 *
	 * @since 0.3.0
	 * @internal
	 *
	 * @param string   $expression      The evaluated expression (hashed by the logger, never stored).
	 * @param int      $actor_id        Authenticated user performing the evaluation.
	 * @param int|null $context_user_id Execution-context user selected in the console, if any.
	 * @param int|null $site_id         Execution-context site ID, if any.
	 * @param float    $start_time      Request start time (microtime( true )).
	 * @return \Closure(string): bool Recorder returning true when a record was written.
	 */
	public static function create_audit_recorder( string $expression, int $actor_id, ?int $context_user_id, ?int $site_id, float $start_time ): \Closure {
		$recorded = false;

		return static function ( string $status ) use ( &$recorded, $expression, $actor_id, $context_user_id, $site_id, $start_time ): bool {
			if ( $recorded ) {
				return false;
			}

			$recorded = true;

			return AuditLogger::log_evaluation(
				array(
					'expression'      => $expression,
					'user_id'         => $actor_id,
					'context_user_id' => $context_user_id,
					'site_id'         => $site_id,
					'status'          => $status,
					'duration_ms'     => ( microtime( true ) - $start_time ) * 1000,
				)
			);
		};
	}

	/**
	 * Evaluates an expression string sent via AJAX and returns the output.
	 *
	 * @since 0.0.1
	 * @internal
	 *
	 * @return void
	 */
	public function evaluate() {
		$start_time = microtime( true );

		try {
			$this->validate_ajax_request();
		} catch ( SecurityException $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ), $e->get_status_code() );
		}

		$actor_id        = get_current_user_id();
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

		// Audit trail: exactly one record per authorized evaluation. The shutdown guard
		// records an `aborted` event when a fatal error, timeout or exit prevents the
		// success/error record below, so an expression cannot evade the log by crashing.
		$record_audit = self::create_audit_recorder( $expression, $actor_id, $user_id, $site_id, $start_time );
		register_shutdown_function( $record_audit, 'aborted' );

		try {
			$evaluation = LanguageEngine::get()
				->set_user_id( $user_id )
				->set_site_id( $site_id )
				->evaluate( $expression );

			$result      = $evaluation['result'];
			$output      = $evaluation['output'];
			$errors      = $evaluation['errors'];
			$object_type = $evaluation['object_type'];

			$record_audit( 'success' );
		} catch ( \Throwable $e ) {
			// The exception message is deliberately never logged: Symfony embeds the raw expression in it.
			$record_audit( 'error' );

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

		$encoded = wp_json_encode(
			array(
				'success' => true,
				'data'    => $response,
			)
		);
		if ( false === $encoded ) {
			$response['visualizations'] = array();
			LanguageEngine::get()->add_message( 'warning', 'Response visualizations omitted: payload could not be JSON-encoded.' );
			$response['messages'] = LanguageEngine::get()->get_messages();
		} elseif ( strlen( $encoded ) > LanguageEngine::MAX_PAYLOAD_BYTES ) {
			$response['visualizations'] = array();
			LanguageEngine::get()->add_message( 'warning', 'Response visualizations omitted: total JSON payload exceeded the 5 MB limit.' );
			$response['messages'] = LanguageEngine::get()->get_messages();
		}

		wp_send_json_success( $response );
	}



	/**
	 * Generates a new challenge nonce and timestamp for the client via AJAX.
	 *
	 * @since 0.0.1
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
	 * @since 0.0.1
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
	 * @since 0.0.1
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

	/**
	 * Resolves and normalizes a full path to be relative to ABSPATH without leading slash.
	 *
	 * Enforces boundary containment within ABSPATH using Helper::resolve_safe_path().
	 * Returns null if the path is invalid or resolves outside ABSPATH.
	 *
	 * @since 0.3.0
	 * @internal
	 *
	 * @param string $full_path  Absolute or relative path.
	 * @param bool   $must_exist Whether the target path must exist on disk. Default false.
	 * @return string|null Relative normalized path, or null if outside ABSPATH or invalid.
	 */
	private function normalize_rel_path( string $full_path, bool $must_exist = false ): ?string {
		try {
			$safe_path      = Helper::resolve_safe_path( $full_path, ABSPATH, $must_exist );
			$real_base      = realpath( ABSPATH );
			$resolved_base  = false !== $real_base ? $real_base : ABSPATH;
			$canonical_base = trailingslashit( wp_normalize_path( $resolved_base ) );

			if ( str_starts_with( $safe_path, $canonical_base ) ) {
				return substr( $safe_path, strlen( $canonical_base ) );
			}

			if ( rtrim( $canonical_base, '/' ) === $safe_path ) {
				return '.';
			}
		} catch ( \InvalidArgumentException $e ) {
			return null;
		}

		return null;
	}

	/**
	 * Builds quick-access presets for critical WordPress system files.
	 *
	 * @since 0.3.0
	 * @internal
	 * @return array
	 */
	protected function build_filesystem_presets(): array {
		$presets = array();

		// 1. debug.log
		$debug_log_path = defined( 'WP_DEBUG_LOG' ) && is_string( WP_DEBUG_LOG ) ? WP_DEBUG_LOG : WP_CONTENT_DIR . '/debug.log';
		$debug_rel_path = $this->normalize_rel_path( $debug_log_path, false );
		if ( null !== $debug_rel_path ) {
			$debug_exists = file_exists( $debug_log_path );
			$presets[]    = array(
				'id'          => 'debug_log',
				'name'        => 'debug.log',
				'path'        => $debug_rel_path,
				'exists'      => $debug_exists,
				'type'        => 'log',
				'size'        => $debug_exists ? size_format( (int) filesize( $debug_log_path ), 2 ) : null,
				'description' => __( 'WordPress active runtime debug log file', 'expression-lab' ),
			);
		}

		// 2. wp-config.php
		$wp_config_path = ABSPATH . 'wp-config.php';
		$wp_config_rel  = $this->normalize_rel_path( $wp_config_path, false );
		if ( null !== $wp_config_rel ) {
			$wp_config_exists = file_exists( $wp_config_path );
			$presets[]        = array(
				'id'          => 'wp_config',
				'name'        => 'wp-config.php',
				'path'        => $wp_config_rel,
				'exists'      => $wp_config_exists,
				'type'        => 'config',
				'size'        => $wp_config_exists ? size_format( (int) filesize( $wp_config_path ), 2 ) : null,
				'description' => __( 'Core WordPress configuration and database definitions', 'expression-lab' ),
			);
		}

		// 3. Active Theme functions.php
		$theme_functions = get_stylesheet_directory() . '/functions.php';
		$theme_func_rel  = $this->normalize_rel_path( $theme_functions, false );
		if ( null !== $theme_func_rel ) {
			$theme_func_exists = file_exists( $theme_functions );
			$presets[]         = array(
				'id'          => 'theme_functions',
				'name'        => 'functions.php',
				'path'        => $theme_func_rel,
				'exists'      => $theme_func_exists,
				'type'        => 'php',
				'size'        => $theme_func_exists ? size_format( (int) filesize( $theme_functions ), 2 ) : null,
				'description' => __( 'Active theme setup, hooks and helper functions', 'expression-lab' ),
			);
		}

		// 4. Active Theme style.css
		$theme_style     = get_stylesheet_directory() . '/style.css';
		$theme_style_rel = $this->normalize_rel_path( $theme_style, false );
		if ( null !== $theme_style_rel ) {
			$theme_style_exists = file_exists( $theme_style );
			$presets[]          = array(
				'id'          => 'theme_style',
				'name'        => 'style.css',
				'path'        => $theme_style_rel,
				'exists'      => $theme_style_exists,
				'type'        => 'css',
				'size'        => $theme_style_exists ? size_format( (int) filesize( $theme_style ), 2 ) : null,
				'description' => __( 'Active theme stylesheet header and styles', 'expression-lab' ),
			);
		}

		// 5. Active Theme theme.json
		$theme_json     = get_stylesheet_directory() . '/theme.json';
		$theme_json_rel = $this->normalize_rel_path( $theme_json, false );
		if ( null !== $theme_json_rel ) {
			$theme_json_exists = file_exists( $theme_json );
			$presets[]         = array(
				'id'          => 'theme_json',
				'name'        => 'theme.json',
				'path'        => $theme_json_rel,
				'exists'      => $theme_json_exists,
				'type'        => 'json',
				'size'        => $theme_json_exists ? size_format( (int) filesize( $theme_json ), 2 ) : null,
				'description' => __( 'Active theme block styles, typography and theme tokens', 'expression-lab' ),
			);
		}

		// 6. .htaccess
		$htaccess_path = ABSPATH . '.htaccess';
		$htaccess_rel  = $this->normalize_rel_path( $htaccess_path, false );
		if ( null !== $htaccess_rel ) {
			$htaccess_exists = file_exists( $htaccess_path );
			$presets[]       = array(
				'id'          => 'htaccess',
				'name'        => '.htaccess',
				'path'        => $htaccess_rel,
				'exists'      => $htaccess_exists,
				'type'        => 'server',
				'size'        => $htaccess_exists ? size_format( (int) filesize( $htaccess_path ), 2 ) : null,
				'description' => __( 'Web server rewrite rules and security directives', 'expression-lab' ),
			);
		}

		// 7. robots.txt
		$robots_path = ABSPATH . 'robots.txt';
		$robots_rel  = $this->normalize_rel_path( $robots_path, false );
		if ( null !== $robots_rel ) {
			$robots_exists = file_exists( $robots_path );
			$presets[]     = array(
				'id'          => 'robots_txt',
				'name'        => 'robots.txt',
				'path'        => $robots_rel,
				'exists'      => $robots_exists,
				'type'        => 'text',
				'size'        => $robots_exists ? size_format( (int) filesize( $robots_path ), 2 ) : null,
				'description' => __( 'Search engine crawler directives', 'expression-lab' ),
			);
		}

		// 8. Drop-in: db.php
		$db_dropin_path = WP_CONTENT_DIR . '/db.php';
		$db_dropin_rel  = $this->normalize_rel_path( $db_dropin_path, false );
		if ( null !== $db_dropin_rel ) {
			$db_dropin_exists = file_exists( $db_dropin_path );
			$presets[]        = array(
				'id'          => 'dropin_db',
				'name'        => 'db.php',
				'path'        => $db_dropin_rel,
				'exists'      => $db_dropin_exists,
				'type'        => 'php',
				'size'        => $db_dropin_exists ? size_format( (int) filesize( $db_dropin_path ), 2 ) : null,
				'description' => __( 'Custom database class drop-in replacement (wpdb)', 'expression-lab' ),
			);
		}

		// 9. Drop-in: advanced-cache.php
		$adv_cache_path = WP_CONTENT_DIR . '/advanced-cache.php';
		$adv_cache_rel  = $this->normalize_rel_path( $adv_cache_path, false );
		if ( null !== $adv_cache_rel ) {
			$adv_cache_exists = file_exists( $adv_cache_path );
			$presets[]        = array(
				'id'          => 'dropin_advanced_cache',
				'name'        => 'advanced-cache.php',
				'path'        => $adv_cache_rel,
				'exists'      => $adv_cache_exists,
				'type'        => 'php',
				'size'        => $adv_cache_exists ? size_format( (int) filesize( $adv_cache_path ), 2 ) : null,
				'description' => __( 'Advanced page caching drop-in', 'expression-lab' ),
			);
		}

		// 10. Drop-in: object-cache.php
		$obj_cache_path = WP_CONTENT_DIR . '/object-cache.php';
		$obj_cache_rel  = $this->normalize_rel_path( $obj_cache_path, false );
		if ( null !== $obj_cache_rel ) {
			$obj_cache_exists = file_exists( $obj_cache_path );
			$presets[]        = array(
				'id'          => 'dropin_object_cache',
				'name'        => 'object-cache.php',
				'path'        => $obj_cache_rel,
				'exists'      => $obj_cache_exists,
				'type'        => 'php',
				'size'        => $obj_cache_exists ? size_format( (int) filesize( $obj_cache_path ), 2 ) : null,
				'description' => __( 'External persistent object caching drop-in', 'expression-lab' ),
			);
		}

		// 11. Drop-in: maintenance.php
		$maint_path = WP_CONTENT_DIR . '/maintenance.php';
		$maint_rel  = $this->normalize_rel_path( $maint_path, false );
		if ( null !== $maint_rel ) {
			$maint_exists = file_exists( $maint_path );
			$presets[]    = array(
				'id'          => 'dropin_maintenance',
				'name'        => 'maintenance.php',
				'path'        => $maint_rel,
				'exists'      => $maint_exists,
				'type'        => 'php',
				'size'        => $maint_exists ? size_format( (int) filesize( $maint_path ), 2 ) : null,
				'description' => __( 'Custom maintenance page drop-in', 'expression-lab' ),
			);
		}

		// 12. Drop-in: fatal-error-handler.php
		$fatal_err_path = WP_CONTENT_DIR . '/fatal-error-handler.php';
		$fatal_err_rel  = $this->normalize_rel_path( $fatal_err_path, false );
		if ( null !== $fatal_err_rel ) {
			$fatal_err_exists = file_exists( $fatal_err_path );
			$presets[]        = array(
				'id'          => 'dropin_fatal_error',
				'name'        => 'fatal-error-handler.php',
				'path'        => $fatal_err_rel,
				'exists'      => $fatal_err_exists,
				'type'        => 'php',
				'size'        => $fatal_err_exists ? size_format( (int) filesize( $fatal_err_path ), 2 ) : null,
				'description' => __( 'Custom fatal error handler drop-in', 'expression-lab' ),
			);
		}

		// 13. mu-plugins
		$mu_plugins_dir = WPMU_PLUGIN_DIR;
		$mu_plugins_rel = $this->normalize_rel_path( $mu_plugins_dir, false );
		if ( null !== $mu_plugins_rel ) {
			$mu_plugins_exists = is_dir( $mu_plugins_dir );
			$presets[]         = array(
				'id'          => 'mu_plugins',
				'name'        => 'mu-plugins',
				'path'        => $mu_plugins_rel,
				'exists'      => $mu_plugins_exists,
				'type'        => 'dir',
				'size'        => null,
				'description' => __( 'Must-use plugins directory automatically loaded by WordPress', 'expression-lab' ),
			);
		}

		// 14. plugins
		$plugins_dir = WP_PLUGIN_DIR;
		$plugins_rel = $this->normalize_rel_path( $plugins_dir, false );
		if ( null !== $plugins_rel ) {
			$plugins_exists = is_dir( $plugins_dir );
			$presets[]      = array(
				'id'          => 'plugins_dir',
				'name'        => 'plugins',
				'path'        => $plugins_rel,
				'exists'      => $plugins_exists,
				'type'        => 'dir',
				'size'        => null,
				'description' => __( 'WordPress plugins directory', 'expression-lab' ),
			);
		}

		return $presets;
	}

	/**
	 * Builds a bounded in-memory representation of the WordPress filesystem.
	 *
	 * Scans only critical directories (root depth 1, wp-content depth 2, active theme, mu-plugins)
	 * while strictly excluding uploads. Every emitted node, file or directory, counts toward the
	 * global cap of 200 items; once reached, scanning stops and the accumulated tree is returned.
	 *
	 * @since 0.3.0
	 * @internal
	 *
	 * @return array Indexed array of tree nodes.
	 */
	private function build_bounded_filesystem_tree(): array {
		$total_scanned = 0;
		$max_items     = 200;

		try {
			$safe_root = Helper::resolve_safe_path( ABSPATH, ABSPATH, true );
		} catch ( \InvalidArgumentException $e ) {
			return array();
		}

		if ( ! is_dir( $safe_root ) ) {
			return array();
		}

		$wp_content_rel = $this->normalize_rel_path( WP_CONTENT_DIR, true );
		$tree           = array();

		try {
			$iter = new \DirectoryIterator( $safe_root );
			foreach ( $iter as $item ) {
				if ( $total_scanned >= $max_items ) {
					break;
				}
				if ( $item->isDot() ) {
					continue;
				}
				$filename = $item->getFilename();

				// Exclude hidden files except .htaccess.
				if ( str_starts_with( $filename, '.' ) && '.htaccess' !== $filename ) {
					continue;
				}

				$entry = $this->resolve_tree_entry( $item );
				if ( null === $entry ) {
					continue;
				}
				list( $safe_item_path, $rel ) = $entry;

				if ( $item->isDir() ) {
					if ( $rel === $wp_content_rel ) {
						continue;
					}
					$tree[] = $this->build_dir_node( $filename, $rel, $safe_item_path, true );
					++$total_scanned;
				} elseif ( $item->isFile() ) {
					$tree[] = $this->build_file_node( $filename, $rel, $safe_item_path, (int) $item->getSize() );
					++$total_scanned;
				}
			}
			// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		} catch ( \Exception $e ) {
			// Silently fail if root directory cannot be read.
		}

		if ( null !== $wp_content_rel && '.' !== $wp_content_rel && $total_scanned < $max_items ) {
			$safe_content = $safe_root . '/' . $wp_content_rel;
			++$total_scanned;
			$tree[] = $this->build_dir_node(
				basename( $wp_content_rel ),
				$wp_content_rel,
				$safe_content,
				false,
				$this->build_wp_content_tree( $safe_content, $total_scanned, $max_items )
			);
		}

		return $this->sort_tree_nodes( $tree );
	}

	/**
	 * Builds the bounded wp-content directory tree.
	 *
	 * Only the directories matching `WP_PLUGIN_DIR`, `get_theme_root()` and `WPMU_PLUGIN_DIR`
	 * are expanded. Every other subdirectory, including uploads, is returned as an opaque node.
	 *
	 * @since 0.3.0
	 * @internal
	 *
	 * @param string $safe_content  Resolved absolute path of the wp-content directory.
	 * @param int    $total_scanned Reference to counter tracking total scanned items.
	 * @param int    $max_items     Maximum overall items allowed in the tree.
	 * @return array List of tree nodes inside wp-content.
	 */
	private function build_wp_content_tree( string $safe_content, int &$total_scanned, int $max_items ): array {
		$nodes          = array();
		$plugins_rel    = $this->normalize_rel_path( WP_PLUGIN_DIR, true );
		$themes_rel     = $this->normalize_rel_path( get_theme_root(), true );
		$mu_plugins_rel = $this->normalize_rel_path( WPMU_PLUGIN_DIR, true );

		try {
			$iter = new \DirectoryIterator( $safe_content );
			foreach ( $iter as $item ) {
				if ( $total_scanned >= $max_items ) {
					break;
				}
				if ( $item->isDot() || str_starts_with( $item->getFilename(), '.' ) ) {
					continue;
				}

				$entry = $this->resolve_tree_entry( $item );
				if ( null === $entry ) {
					continue;
				}
				list( $safe_item_path, $rel ) = $entry;
				$name                         = $item->getFilename();

				if ( $item->isDir() ) {
					++$total_scanned;
					$nodes[] = match ( true ) {
						'uploads' === $name      => $this->build_dir_node( $name, $rel, $safe_item_path, true ),
						$rel === $plugins_rel    => $this->build_plugins_tree( $name, $rel, $safe_item_path, $total_scanned, $max_items ),
						$rel === $themes_rel     => $this->build_themes_tree( $name, $rel, $safe_item_path, $total_scanned, $max_items ),
						$rel === $mu_plugins_rel => $this->build_mu_plugins_tree( $name, $rel, $safe_item_path, $total_scanned, $max_items ),
						default                  => $this->build_dir_node( $name, $rel, $safe_item_path, true ),
					};
				} elseif ( $item->isFile() ) {
					++$total_scanned;
					$nodes[] = $this->build_file_node( $name, $rel, $safe_item_path, (int) $item->getSize() );
				}
			}
			// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		} catch ( \Exception $e ) {
			// Silently fail if wp-content cannot be read.
		}

		return $this->sort_tree_nodes( $nodes );
	}

	/**
	 * Builds the plugins list (depth 1 inside plugins directory).
	 *
	 * @since 0.3.0
	 * @internal
	 *
	 * @param string $name          Directory name.
	 * @param string $rel           Path relative to ABSPATH.
	 * @param string $safe_dir      Resolved absolute path of the plugins directory.
	 * @param int    $total_scanned Counter reference.
	 * @param int    $max_items     Maximum items limit.
	 * @return array Plugins directory tree node.
	 */
	private function build_plugins_tree( string $name, string $rel, string $safe_dir, int &$total_scanned, int $max_items ): array {
		$children = array();

		try {
			$iter = new \DirectoryIterator( $safe_dir );
			foreach ( $iter as $item ) {
				if ( $total_scanned >= $max_items ) {
					break;
				}
				if ( $item->isDot() || str_starts_with( $item->getFilename(), '.' ) ) {
					continue;
				}

				$entry = $this->resolve_tree_entry( $item );
				if ( null === $entry ) {
					continue;
				}
				list( $safe_item_path, $item_rel ) = $entry;
				$item_name                         = $item->getFilename();

				if ( $item->isDir() ) {
					$children[] = $this->build_dir_node( $item_name, $item_rel, $safe_item_path, true );
					++$total_scanned;
				} elseif ( $item->isFile() ) {
					$children[] = $this->build_file_node( $item_name, $item_rel, $safe_item_path, (int) $item->getSize() );
					++$total_scanned;
				}
			}
			// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		} catch ( \Exception $e ) {
			// Silently fail if plugins directory cannot be read.
		}

		return $this->build_dir_node( $name, $rel, $safe_dir, false, $this->sort_tree_nodes( $children ) );
	}

	/**
	 * Builds themes tree with a bounded scan of the active theme and opaque entries for other themes.
	 *
	 * @since 0.3.0
	 * @internal
	 *
	 * @param string $name          Directory name.
	 * @param string $rel           Path relative to ABSPATH.
	 * @param string $safe_dir      Resolved absolute path of the theme root.
	 * @param int    $total_scanned Counter reference.
	 * @param int    $max_items     Maximum items limit.
	 * @return array Themes directory tree node.
	 */
	private function build_themes_tree( string $name, string $rel, string $safe_dir, int &$total_scanned, int $max_items ): array {
		$active_slug = get_stylesheet();
		$children    = array();

		try {
			$iter = new \DirectoryIterator( $safe_dir );
			foreach ( $iter as $item ) {
				if ( $total_scanned >= $max_items ) {
					break;
				}
				if ( $item->isDot() || ! $item->isDir() || str_starts_with( $item->getFilename(), '.' ) ) {
					continue;
				}

				$entry = $this->resolve_tree_entry( $item );
				if ( null === $entry ) {
					continue;
				}
				list( $safe_item_path, $item_rel ) = $entry;
				$slug                              = $item->getFilename();
				++$total_scanned;

				if ( $slug === $active_slug ) {
					$active_children = $this->scan_directory_bounded( $safe_item_path, 3, min( $max_items, $total_scanned + 60 ), $total_scanned );
					$children[]      = $this->build_dir_node( "{$slug} (active)", $item_rel, $safe_item_path, false, $active_children );
				} else {
					$children[] = $this->build_dir_node( $slug, $item_rel, $safe_item_path, true );
				}
			}
			// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		} catch ( \Exception $e ) {
			// Silently fail if themes directory cannot be read.
		}

		usort(
			$children,
			function ( $a, $b ) {
				if ( str_contains( $a['name'], '(active)' ) ) {
					return -1;
				}
				if ( str_contains( $b['name'], '(active)' ) ) {
					return 1;
				}
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return $this->build_dir_node( $name, $rel, $safe_dir, false, $children );
	}

	/**
	 * Builds the mu-plugins tree.
	 *
	 * @since 0.3.0
	 * @internal
	 *
	 * @param string $name          Directory name.
	 * @param string $rel           Path relative to ABSPATH.
	 * @param string $safe_dir      Resolved absolute path of the mu-plugins directory.
	 * @param int    $total_scanned Counter reference.
	 * @param int    $max_items     Maximum items limit.
	 * @return array mu-plugins directory tree node.
	 */
	private function build_mu_plugins_tree( string $name, string $rel, string $safe_dir, int &$total_scanned, int $max_items ): array {
		$children = $this->scan_directory_bounded( $safe_dir, 3, min( $max_items, $total_scanned + 40 ), $total_scanned );

		return $this->build_dir_node( $name, $rel, $safe_dir, false, $children );
	}

	/**
	 * Recursively scans a directory with strict depth and item caps.
	 *
	 * `$ceiling` is an absolute upper bound for `$total_scanned`, shared by the whole branch, so a
	 * caller limits a subtree to N items by passing `min( $max_items, $total_scanned + N )`.
	 * Entries of a level are collected before descending, so shallow items take precedence.
	 *
	 * @since 0.3.0
	 * @internal
	 *
	 * @param string $dir           Absolute directory path to scan.
	 * @param int    $max_depth     Maximum recursion depth.
	 * @param int    $ceiling       Value of `$total_scanned` at which the branch stops.
	 * @param int    $total_scanned Counter reference across the entire tree.
	 * @param int    $current_depth Current recursion depth.
	 * @return array List of tree nodes.
	 */
	private function scan_directory_bounded( string $dir, int $max_depth, int $ceiling, int &$total_scanned, int $current_depth = 1 ): array {
		if ( $current_depth > $max_depth || $total_scanned >= $ceiling ) {
			return array();
		}

		try {
			$safe_dir = Helper::resolve_safe_path( $dir, ABSPATH, true );
		} catch ( \InvalidArgumentException $e ) {
			return array();
		}

		if ( ! is_dir( $safe_dir ) ) {
			return array();
		}

		$ignored_dirs = array( 'node_modules', 'vendor', 'images', 'assets/images', 'assets/fonts' );
		$parent_name  = strtolower( basename( $safe_dir ) );
		$nodes        = array();
		$subdirs      = array();

		try {
			$iter = new \DirectoryIterator( $safe_dir );
			foreach ( $iter as $item ) {
				if ( $total_scanned + count( $nodes ) + count( $subdirs ) >= $ceiling ) {
					break;
				}
				if ( $item->isDot() || str_starts_with( $item->getFilename(), '.' ) ) {
					continue;
				}

				$name  = $item->getFilename();
				$lower = strtolower( $name );

				if ( $item->isDir() && ( in_array( $lower, $ignored_dirs, true ) || in_array( "{$parent_name}/{$lower}", $ignored_dirs, true ) ) ) {
					continue;
				}

				$entry = $this->resolve_tree_entry( $item );
				if ( null === $entry ) {
					continue;
				}
				list( $safe_item_path, $rel ) = $entry;

				if ( $item->isDir() ) {
					$subdirs[] = array( $name, $rel, $safe_item_path );
				} elseif ( $item->isFile() ) {
					$nodes[] = $this->build_file_node( $name, $rel, $safe_item_path, (int) $item->getSize() );
				}
			}
			// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		} catch ( \Exception $e ) {
			// Silently fail if directory branch cannot be read.
		}

		$total_scanned += count( $nodes ) + count( $subdirs );

		foreach ( $subdirs as list( $name, $rel, $safe_item_path ) ) {
			$opaque   = $current_depth >= $max_depth || $total_scanned >= $ceiling;
			$children = $opaque ? array() : $this->scan_directory_bounded( $safe_item_path, $max_depth, $ceiling, $total_scanned, $current_depth + 1 );
			$nodes[]  = $this->build_dir_node( $name, $rel, $safe_item_path, $opaque, $children );
		}

		return $this->sort_tree_nodes( $nodes );
	}

	/**
	 * Resolves a directory entry for the tree.
	 *
	 * Entries resolving outside ABSPATH, dangling symlinks and symlinked directories
	 * (loop and escape prevention) are skipped.
	 *
	 * @since 0.3.0
	 * @internal
	 *
	 * @param \SplFileInfo $item Directory entry.
	 * @return array{0: string, 1: string}|null Resolved absolute path and ABSPATH-relative path, or null to skip.
	 */
	private function resolve_tree_entry( \SplFileInfo $item ): ?array {
		try {
			$safe_path = Helper::resolve_safe_path( $item->getPathname(), ABSPATH, true );
		} catch ( \InvalidArgumentException $e ) {
			return null;
		}

		if ( $item->isLink() && is_dir( $safe_path ) ) {
			return null;
		}

		$rel = $this->normalize_rel_path( $safe_path, true );

		return null === $rel ? null : array( $safe_path, $rel );
	}

	/**
	 * Builds a directory tree node.
	 *
	 * @since 0.3.0
	 * @internal
	 *
	 * @param string $name     Display name.
	 * @param string $rel      Path relative to ABSPATH.
	 * @param string $path     Resolved absolute path.
	 * @param bool   $opaque   Whether the directory contents were not scanned.
	 * @param array  $children Child nodes.
	 * @return array
	 */
	private function build_dir_node( string $name, string $rel, string $path, bool $opaque, array $children = array() ): array {
		return array(
			'name'     => $name,
			'path'     => $rel,
			'type'     => 'dir',
			'perms'    => $this->resolve_octal_perms( $path ),
			'owner'    => $this->resolve_owner( $path ),
			'group'    => $this->resolve_group( $path ),
			'opaque'   => $opaque,
			'children' => $children,
		);
	}

	/**
	 * Builds a file tree node.
	 *
	 * @since 0.3.0
	 * @internal
	 *
	 * @param string $name  File name.
	 * @param string $rel   Path relative to ABSPATH.
	 * @param string $path  Resolved absolute path.
	 * @param int    $bytes File size in bytes.
	 * @return array
	 */
	private function build_file_node( string $name, string $rel, string $path, int $bytes ): array {
		return array(
			'name'   => $name,
			'path'   => $rel,
			'type'   => 'file',
			'size'   => size_format( $bytes, 2 ),
			'ext'    => strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ),
			'perms'  => $this->resolve_octal_perms( $path ),
			'owner'  => $this->resolve_owner( $path ),
			'group'  => $this->resolve_group( $path ),
			'opaque' => false,
		);
	}

	/**
	 * Sorts tree nodes with directories first, then by case-insensitive name.
	 *
	 * @since 0.3.0
	 * @internal
	 *
	 * @param array $nodes Tree nodes.
	 * @return array Sorted nodes.
	 */
	private function sort_tree_nodes( array $nodes ): array {
		usort(
			$nodes,
			function ( $a, $b ) {
				if ( $a['type'] !== $b['type'] ) {
					return 'dir' === $a['type'] ? -1 : 1;
				}
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return $nodes;
	}

	/**
	 * Formats octal permission string (e.g. '0755', '0644') for a given path.
	 *
	 * @since 0.3.0
	 * @internal
	 *
	 * @param string $path Target filesystem path.
	 * @return string|null Octal string or null on failure.
	 */
	private function resolve_octal_perms( string $path ): ?string {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$perms = @fileperms( $path );
		return false !== $perms ? substr( sprintf( '%o', $perms ), -4 ) : null;
	}

	/**
	 * Resolves Unix owner name or returns numeric UID.
	 *
	 * @since 0.3.0
	 * @internal
	 *
	 * @param string $path Target filesystem path.
	 * @return string
	 */
	private function resolve_owner( string $path ): string {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$uid = @fileowner( $path );
		if ( false === $uid ) {
			return 'unknown';
		}
		if ( function_exists( 'posix_getpwuid' ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$user = @posix_getpwuid( $uid );
			if ( is_array( $user ) && isset( $user['name'] ) ) {
				return (string) $user['name'];
			}
		}
		return (string) $uid;
	}

	/**
	 * Resolves Unix group name or returns numeric GID.
	 *
	 * @since 0.3.0
	 * @internal
	 *
	 * @param string $path Target filesystem path.
	 * @return string
	 */
	private function resolve_group( string $path ): string {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$gid = @filegroup( $path );
		if ( false === $gid ) {
			return 'unknown';
		}
		if ( function_exists( 'posix_getgrgid' ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$group = @posix_getgrgid( $gid );
			if ( is_array( $group ) && isset( $group['name'] ) ) {
				return (string) $group['name'];
			}
		}
		return (string) $gid;
	}
}
