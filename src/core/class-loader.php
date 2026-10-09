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
 * @since 0.0.1
 * @internal
 *
 * @package ExpressionLab
 */
class Loader {
	use Singleton;

	/**
	 * Boots the plugin by registering core hooks and instantiating admin pages.
	 *
	 * @since 0.0.1
	 * @internal
	 */
	public function boot() {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Environment guardrail: evaluated before any admin page, AJAX endpoint or CLI
		// command is registered, and independently of plugin_is_fully_configured() so a
		// mismatched (e.g. production) site never falls back to the onboarding wizard.
		if ( ! Helper::is_environment_allowed() ) {
			$this->boot_dormant();
			return;
		}

		Admin\Pages\Onboarding::get();
		Admin\Pages\Console::get();
		Updater::get()->init();

		if ( defined( 'WP_CLI' ) && \WP_CLI ) {
			\WP_CLI::add_command( 'expressionlab iplookup', Cli\Commands\IPLookupCommand::class );
		}
	}

	/**
	 * Boots the plugin in dormant shell mode (environment mismatch).
	 *
	 * Only the updater (so security updates keep flowing) and the plugins screen
	 * notice are registered. No admin pages, AJAX endpoints or CLI commands are loaded,
	 * and no database option is modified.
	 *
	 * @since 0.3.0
	 * @internal
	 */
	public function boot_dormant() {
		add_action( 'after_plugin_row_' . EXPRESSION_LAB_BASENAME, array( $this, 'render_plugin_row_notice' ), 10, 1 );
		Updater::get()->init();
	}

	/**
	 * Loads the plugin textdomain for internationalization.
	 *
	 * @since 0.0.1
	 * @internal
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'expression-lab',
			false,
			dirname( plugin_basename( EXPRESSION_LAB_DIR . 'expressionlab.php' ) ) . '/languages'
		);
	}

	/**
	 * Renders an inline warning below the plugin row in the plugins table.
	 *
	 * @since 0.3.0
	 * @internal
	 *
	 * @param string $plugin_file Plugin basename relative to the plugins directory.
	 */
	public function render_plugin_row_notice( $plugin_file = '' ) {
		$plugin_file = is_string( $plugin_file ) && '' !== $plugin_file ? $plugin_file : EXPRESSION_LAB_BASENAME;

		$wp_list_table = function_exists( '_get_list_table' ) ? _get_list_table( 'WP_Plugins_List_Table' ) : null;
		$colspan       = $wp_list_table ? $wp_list_table->get_column_count() : 3;

		// Mirror core's wp_plugin_update_row(): the row is "active" in the context being viewed.
		$is_active = false;
		if ( function_exists( 'is_plugin_active' ) ) {
			$is_active = is_network_admin() ? is_plugin_active_for_network( $plugin_file ) : is_plugin_active( $plugin_file );
		}

		$home            = (string) get_option( 'home' );
		$current_url     = (string) preg_replace( '#^https?://#i', '', untrailingslashit( $home ) );
		$configured_host = defined( 'EXPRESSION_LAB_STAGING_URL' ) ? (string) constant( 'EXPRESSION_LAB_STAGING_URL' ) : '';

		?>
		<tr class="plugin-update-tr expressionlab-staging-notice<?php echo $is_active ? ' active' : ''; ?> notice-warning notice-alt" data-plugin="<?php echo esc_attr( $plugin_file ); ?>">
			<td colspan="<?php echo esc_attr( (string) $colspan ); ?>" class="plugin-update colspanchange" style="border-left:none">
				<div class="notice inline notice-warning notice-alt" style="margin: 0 0 1px;padding-left: 44px;">
					<p style="margin:0">
						<strong><?php esc_html_e( 'Staging mismatch:', 'expression-lab' ); ?></strong>
						<?php
						printf(
							/* translators: 1: current site URL or domain, 2: configured staging URL pattern */
							esc_html__( 'Current environment (%1$s) does not match the configured staging URL (%2$s). Expression Lab console is disabled.', 'expression-lab' ),
							'<code>' . esc_html( (string) $current_url ) . '</code>',
							'<code>' . esc_html( $configured_host ) . '</code>'
						);
						echo ' ';
						printf(
							/* translators: %s: name of the wp-config.php constant */
							esc_html__( 'If this site is a legitimate staging environment, update or remove the %s constant in wp-config.php to re-enable it.', 'expression-lab' ),
							'<code>EXPRESSION_LAB_STAGING_URL</code>'
						);
						?>
					</p>
				</div>
			</td>
		</tr>
		<?php
	}
}
