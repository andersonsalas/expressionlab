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

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * Base class for admin pages.
 *
 * Provides common functionality for admin pages in Expression Lab,
 * including menu registration, asset enqueueing, and sandboxed rendering.
 *
 * @since 1.0.0
 *
 * @package ExpressionLab
 */
abstract class AdminPage {
	/**
	 * Initializes the admin page hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		if ( ! $this->should_load() ) {
			return;
		}

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			add_action( 'network_admin_menu', array( $this, 'network_admin_menu' ) );
		} else {
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		}

		add_action( 'admin_init', array( $this, 'maybe_render_sandbox' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'admin_body_class', array( $this, 'add_body_classes' ) );

		if ( Helper::is_debug_mode() ) {
			add_action( 'admin_bar_menu', array( $this, 'add_debug_admin_bar_badge' ), 100 );
		}

		$this->init_hooks();
	}

	/**
	 * Adds a debug mode warning badge to the WordPress admin bar.
	 *
	 * Only displays when debug mode is enabled and the user is on an Expression Lab admin screen.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar The WordPress admin bar instance.
	 * @return void
	 */
	public function add_debug_admin_bar_badge( $wp_admin_bar ) {
		if ( ! Helper::is_debug_mode() ) {
			return;
		}

		if ( ! ( $wp_admin_bar instanceof \WP_Admin_Bar ) ) {
			return;
		}

		if ( ! Helper::is_current_screen() ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'expressionlab-debug-badge',
				'title' => '<span class="ab-icon dashicons dashicons-warning" aria-hidden="true"></span><span class="ab-label">' . esc_html__( 'Expression Lab Debug Enabled', 'expression-lab' ) . '</span>',
				'href'  => '#',
				'meta'  => array(
					'class' => 'expressionlab-debug-badge',
					'title' => esc_attr__( 'Debug mode is active. This poses a security risk in production environments.', 'expression-lab' ),
				),
			)
		);
	}

	/**
	 * Adds custom body classes for the admin page.
	 *
	 * @since 1.0.0
	 *
	 * @param string $classes Existing body classes.
	 * @return string Modified body classes.
	 */
	public function add_body_classes( $classes ) {
		if ( is_string( $classes ) ) {
			$classes .= ' expressionlab-admin-page';
		}
		return $classes;
	}

	/**
	 * Determines if the admin page should be loaded.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the page should be loaded, false otherwise.
	 */
	protected function should_load() {
		return true;
	}

	/**
	 * Renders the sandbox template if requested.
	 *
	 * Hooked to `admin_init` to ensure user authentication functions are available.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function maybe_render_sandbox() {
		if ( $this->should_render_sandbox() ) {
			$this->render_sandbox();
			exit;
		}
	}

	/**
	 * Determines if the iframe sandbox should be rendered.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the sandbox should be rendered, false otherwise.
	 */
	protected function should_render_sandbox() {
		return EXPRESSION_LAB_SANDBOX_ENABLED && '1' === filter_input( INPUT_GET, 'sandbox' );
	}

	/**
	 * Determines if admin scripts should be enqueued for the current screen.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return bool True if scripts should be enqueued, false otherwise.
	 */
	protected function should_enqueue_admin_scripts( $hook_suffix ) {
		if ( null === $this->get_asset_name() ) {
			return false;
		}
		return false !== strpos( $hook_suffix, 'page_expressionlab' );
	}

	/**
	 * Determines if the JavaScript bundle should be enqueued for the current page.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the script should be enqueued, false otherwise.
	 */
	protected function should_enqueue_script(): bool {
		return true;
	}

	/**
	 * Initializes additional hooks for the admin page.
	 *
	 * Can be overridden by subclasses to register custom hooks.
	 *
	 * @since 1.0.0
	 */
	protected function init_hooks() {
	}

	/**
	 * Registers the admin menu page.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function admin_menu() {
		add_submenu_page(
			'tools.php',
			__( 'Console', 'expression-lab' ),
			__( 'Console', 'expression-lab' ),
			'manage_options',
			'expressionlab',
			array( $this, 'render' )
		);
	}

	/**
	 * Registers the network admin menu page for multisite installations.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function network_admin_menu() {
		add_submenu_page(
			'settings.php',
			__( 'Console', 'expression-lab' ),
			__( 'Console', 'expression-lab' ),
			'manage_network_options',
			'expressionlab',
			array( $this, 'render' )
		);
	}

	/**
	 * Retrieves the current admin page URL.
	 *
	 * Supports both single-site and network admin environments.
	 *
	 * @since 1.0.0
	 *
	 * @param array $query_args Optional. Query arguments to append to the URL. Default empty array.
	 * @return string The full page URL.
	 */
	public function get_page_url( array $query_args = array() ) {
		if ( is_network_admin() ) {
			$url = network_admin_url( 'settings.php?page=expressionlab' );
		} else {
			$url = menu_page_url( 'expressionlab', false ) ? menu_page_url( 'expressionlab', false ) : admin_url( 'tools.php?page=expressionlab' );
		}

		if ( ! empty( $query_args ) ) {
			$url = add_query_arg( $query_args, $url );
		}

		return $url;
	}

	/**
	 * Enqueues admin scripts and styles for the page.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function admin_enqueue_scripts( $hook_suffix ) {
		if ( null === $this->get_asset_name() ) {
			return;
		}

		if ( ! $this->should_enqueue_admin_scripts( $hook_suffix ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_network_options' ) ) {
			return;
		}

		// Remove all admin notices to prevent them from appearing in the console page.
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
		remove_all_actions( 'network_admin_notices' );
		remove_all_actions( 'user_admin_notices' );

		$entrypoints_file = EXPRESSION_LAB_ASSETS_DIR . '/build/entrypoints.json';
		$entrypoints      = array();

		if ( file_exists( $entrypoints_file ) ) {
			$entrypoints = file_get_contents( $entrypoints_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$entrypoints = json_decode( $entrypoints, true );
		} else {
			return;
		}

		$asset_name = $this->get_asset_name();

		// Script file.
		$js_url = isset( $entrypoints['entrypoints'][ $asset_name ]['js'][0] ) ? $entrypoints['entrypoints'][ $asset_name ]['js'][0] : null;
		$js_url = 0 === strpos( $js_url, '/' ) ? EXPRESSION_LAB_URL . ltrim( $js_url, '/' ) : $js_url;

		if ( null !== $js_url && $this->should_enqueue_script() ) {
			wp_enqueue_script(
				$asset_name,
				$js_url,
				array(),
				EXPRESSION_LAB_VERSION,
				true
			);

			wp_localize_script(
				$asset_name,
				'el_settings',
				$this->get_script_data()
			);
		}

		// Style file.
		$css_url = isset( $entrypoints['entrypoints'][ $asset_name ]['css'][0] ) ? $entrypoints['entrypoints'][ $asset_name ]['css'][0] : null;
		$css_url = 0 === strpos( $css_url, '/' ) ? EXPRESSION_LAB_URL . ltrim( $css_url, '/' ) : $css_url;

		if ( null !== $css_url ) {
			wp_enqueue_style(
				$asset_name,
				$css_url,
				array(),
				EXPRESSION_LAB_VERSION
			);
		}
	}

	/**
	 * Renders the admin page content.
	 *
	 * @since 1.0.0
	 */
	public function render() {
	}

	/**
	 * Renders the sandboxed iframe template.
	 *
	 * Outputs a standalone HTML document with Content Security Policy headers
	 * for sandboxed execution of the application bundle.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function render_sandbox() {
		global $wp_version;

		// Role check. Do NOT remove, this is CRITICAL.
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Unauthorized', 'expression-lab' ) );
		}

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'expression-lab' ) );
		}

		// Buffer cleanup.
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript
		// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		// Generate a nonce for the CSP header.
		$csp_nonce = base64_encode( random_bytes( 16 ) );

		// Security headers. Do NOT remove, these are CRITICAL.
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( "Content-Security-Policy: default-src 'none'; script-src 'nonce-$csp_nonce' 'wasm-unsafe-eval'; style-src 'unsafe-inline'; font-src data:; connect-src data:; img-src 'self' data: https://secure.gravatar.com;" );

		// Load integrity file generated by Webpack.
		$integrity_file = EXPRESSION_LAB_ASSETS_DIR . '/integrity.json';
		$integrity_data = array();

		if ( file_exists( $integrity_file ) ) {
			$integrity_data = file_get_contents( $integrity_file );
			$integrity_data = json_decode( $integrity_data, true );
		} else {
			wp_die( esc_html__( 'Integrity file not found', 'expression-lab' ) );
		}

		if ( ! is_array( $integrity_data ) || empty( $integrity_data ) ) {
			wp_die( esc_html__( 'Invalid integrity data', 'expression-lab' ) );
		}

		// Note: we need to print the assets directly here because the sandboxed iframe.
		$asset_name     = $this->get_asset_name();
		$js_path        = EXPRESSION_LAB_ASSETS_DIR . '/build/' . $asset_name . '.js';
		$script_content = file_exists( $js_path ) ? file_get_contents( $js_path ) : false;

		if ( false === $script_content ) {
			wp_die( esc_html__( 'Script not found', 'expression-lab' ) );
		}

		if ( ! isset( $integrity_data[ 'assets/build/' . $asset_name . '.js' ] ) ) {
			wp_die( esc_html__( 'Integrity hash for script not found', 'expression-lab' ) );
		}

		if ( hash( 'sha256', $script_content ) !== $integrity_data[ 'assets/build/' . $asset_name . '.js' ] ) {
			/* translators: %s: asset file name */
			wp_die( esc_html( sprintf( __( 'Integrity check failed for %s', 'expression-lab' ), $asset_name . '.js' ) ) );
		}

		$script_data = $this->get_script_data();
		$css_path    = EXPRESSION_LAB_ASSETS_DIR . '/build/' . $asset_name . '.css';

		// We still need some of the default WP admin styles for the page to look correct.
		$admin_css_path = ABSPATH . 'wp-admin/css/';
		$files_to_load  = array(
			$admin_css_path . 'common.css',
			$admin_css_path . 'forms.css',
			$admin_css_path . 'buttons.css',
		);

		// WordPress 7.0+.
		if ( version_compare( $wp_version, '7.0', '>=' ) ) {
			$wp_includes_path = ABSPATH . 'wp-includes/css/dist/';
			if ( file_exists( $wp_includes_path . 'base-styles/admin-schemes.css' ) ) {
				$files_to_load[] = $wp_includes_path . 'base-styles/admin-schemes.css';
			}
			if ( file_exists( $admin_css_path . 'colors/modern/colors.min.css' ) ) {
				$files_to_load[] = $admin_css_path . 'colors/modern/colors.min.css';
			}
		}

		$combined_styles = '';

		foreach ( $files_to_load as $file ) {
			if ( file_exists( $file ) ) {
				$combined_styles .= file_get_contents( $file );
			}
		}

		$stylesheet_content = file_exists( $css_path ) ? file_get_contents( $css_path ) : false;

		if ( false === $stylesheet_content ) {
			wp_die( esc_html__( 'Stylesheet not found', 'expression-lab' ) );
		}

		if ( ! isset( $integrity_data[ 'assets/build/' . $asset_name . '.css' ] ) ) {
			wp_die( esc_html__( 'Integrity hash for stylesheet not found', 'expression-lab' ) );
		}

		if ( hash( 'sha256', $stylesheet_content ) !== $integrity_data[ 'assets/build/' . $asset_name . '.css' ] ) {
			/* translators: %s: asset file name */
			wp_die( esc_html( sprintf( __( 'Integrity check failed for %s', 'expression-lab' ), $asset_name . '.css' ) ) );
		}

		$stylesheet_content = $combined_styles . "\n" . $stylesheet_content;

		$script_data['sandbox_url'] = $this->get_page_url( array( 'sandbox' => '1' ) );

		$fonts_dir = EXPRESSION_LAB_ASSETS_DIR . '/fonts/';

		$fonts = array(
			'Roboto-Bold.woff2'          => 'Roboto',
			'Roboto-Regular.woff2'       => 'Roboto',
			'Roboto-Italic.woff2'        => 'Roboto',
			'RobotoSlab-Bold.woff2'      => 'Roboto Slab',
			'RobotoSlab-Regular.woff2'   => 'Roboto Slab',
			'CascadiaMono-Regular.woff2' => 'Cascadia Mono',
			'codicon.woff2'              => 'Codicon',
		);

		$font_styles_base64 = '';

		// We need to embed the fonts as base64.
		foreach ( $fonts as $font => $font_family ) {
			$font_path = $fonts_dir . $font;
			if ( file_exists( $font_path ) ) {
				$font_content = file_get_contents( $font_path );

				if ( ! isset( $integrity_data[ 'assets/fonts/' . $font ] ) ) {
					/* translators: %s: font file name */
					wp_die( esc_html( sprintf( __( 'Integrity hash for font %s not found', 'expression-lab' ), $font ) ) );
				}

				if ( hash( 'sha256', $font_content ) !== $integrity_data[ 'assets/fonts/' . $font ] ) {
					/* translators: %s: font file name */
					wp_die( esc_html( sprintf( __( 'Integrity check failed for font %s', 'expression-lab' ), $font ) ) );
				}

				$font_mime_type      = 'font/woff2';
				$font_base64         = base64_encode( $font_content );
				$font_weight         = strpos( $font, 'Bold' ) !== false ? 'bold' : 'normal';
				$font_style          = strpos( $font, 'Italic' ) !== false ? 'italic' : 'normal';
				$font_styles_base64 .= "@font-face { font-family: '" . $font_family . "'; src: url('data:" . $font_mime_type . ';base64,' . $font_base64 . "') format('woff2'); font-weight: " . $font_weight . '; font-style: ' . $font_style . "; }\n";
			}
		}

		$stylesheet_content = $font_styles_base64 . "\n" . $stylesheet_content;

		// A small tweak, remove Codicon (local) @font-face as it violates our CSP.
		$stylesheet_content = preg_replace( '/@font-face\s*{[^}]*font-family:\s*codicon;[^}]*}/', '', $stylesheet_content );

		$extra_body_class = '';

		// WordPress 7.0+.
		if ( version_compare( $wp_version, '7.0', '>=' ) ) {
			$extra_body_class .= ' wp-admin wp-core-ui version-7-0 admin-color-modern';
		}

		echo '<!DOCTYPE html>
			<html>
			<head>
				<title>ExpressionLab</title>
				<meta charset="utf-8" />
				<meta name="viewport" content="width=device-width, initial-scale=1" />
				<style>' . $stylesheet_content . '</style>
			</head>
			<body class="expression-lab-sandbox' . esc_attr( $extra_body_class ) . '">
				<div class="expressionlab-app"></div>
				<script nonce="' . esc_attr( $csp_nonce ) . '">
					window.el_settings = ' . wp_json_encode( $script_data ) . ';
				</script>
				<script nonce="' . esc_attr( $csp_nonce ) . '">' . $script_content . '</script>
			</body>
		</html>';

		// phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedScript
		// phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		// phpcs:enable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		// phpcs:enable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		exit;
	}

	/**
	 * Retrieves the asset bundle name for the admin page.
	 *
	 * Must match an entry in the compiled `entrypoints.json` manifest.
	 *
	 * @since 1.0.0
	 *
	 * @return string The asset bundle name.
	 */
	abstract protected function get_asset_name(): string;

	/**
	 * Retrieves settings data localized for the admin page script.
	 *
	 * @since 1.0.0
	 *
	 * @return array The configuration data passed to the client.
	 */
	abstract protected function get_script_data(): array;
}
