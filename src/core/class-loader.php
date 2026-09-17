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

namespace ExpressionLab\Core;

use ExpressionLab\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * Plugin loader class.
 *
 * Initializes the plugin by loading required modules and registering admin pages.
 *
 * @since 1.0.0
 * @internal
 *
 * @package ExpressionLab
 */
class Loader {
	use Singleton;

	/**
	 * Boots the plugin by registering core hooks and instantiating admin pages.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function boot() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		Admin\Pages\Onboarding::get();
		Admin\Pages\Console::get();
		Updater::get()->init();

		if ( defined( 'WP_CLI' ) && \WP_CLI ) {
			\WP_CLI::add_command( 'expressionlab iplookup', Cli\Commands\IPLookupCommand::class );
		}
	}

	/**
	 * Loads the plugin textdomain for internationalization.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'expression-lab',
			false,
			dirname( plugin_basename( EXPRESSION_LAB_DIR . 'expressionlab.php' ) ) . '/languages'
		);
	}
}
