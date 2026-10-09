<?php

// phpcs:ignoreFile

use ExpressionLab\Core\Loader;
use ExpressionLab\Admin\Pages\Onboarding;
use ExpressionLab\Admin\Pages\Console;

class LoaderTest extends WP_UnitTestCase {

	public function test_loader_is_singleton() {
		$loader1 = Loader::get();
		$loader2 = Loader::get();

		$this->assertSame( $loader1, $loader2 );
	}

	public function test_loader_boot_attaches_hooks_and_instantiates_pages() {
		$loader = Loader::get();
		$loader->boot();

		$this->assertNotFalse(
			has_action( 'init', array( $loader, 'load_textdomain' ) ),
			'Loader::boot() must register load_textdomain on the init hook.'
		);

		$this->assertInstanceOf( Onboarding::class, Onboarding::get() );
		$this->assertInstanceOf( Console::class, Console::get() );
	}

	public function test_load_textdomain_executes_without_errors() {
		$loader = Loader::get();

		// Should complete without throwing warnings or exceptions.
		$loader->load_textdomain();
		$this->assertTrue( true );
	}

	public function test_boot_in_allowed_environment_does_not_attach_dormant_notice() {
		$hook = 'after_plugin_row_' . EXPRESSION_LAB_BASENAME;
		remove_all_actions( $hook );

		$loader = Loader::get();
		$loader->boot();

		$this->assertTrue( \ExpressionLab\Core\Helper::is_environment_allowed() );
		$this->assertFalse( has_action( $hook, array( $loader, 'render_plugin_row_notice' ) ) );
	}

	public function test_boot_dormant_registers_only_the_plugin_row_notice() {
		$hook          = 'after_plugin_row_' . EXPRESSION_LAB_BASENAME;
		$guarded_hooks = array(
			$hook,
			'admin_menu',
			'network_admin_menu',
			'admin_init',
			'admin_enqueue_scripts',
			'wp_ajax_expressionlab_evaluate_expression',
			'wp_ajax_expressionlab_get_outline',
			'wp_ajax_expressionlab_challenge',
			'wp_ajax_expressionlab_search_users',
			'wp_ajax_expressionlab_search_sites',
		);

		// Start from a clean slate (hooks are restored by WP_UnitTestCase after the test).
		foreach ( $guarded_hooks as $guarded_hook ) {
			remove_all_actions( $guarded_hook );
		}

		$loader = Loader::get();
		$loader->boot_dormant();

		$this->assertSame( 10, has_action( $hook, array( $loader, 'render_plugin_row_notice' ) ) );

		foreach ( array_slice( $guarded_hooks, 1 ) as $guarded_hook ) {
			$this->assertFalse( has_action( $guarded_hook ), "Dormant shell must not register '$guarded_hook'." );
		}
	}

	public function test_render_plugin_row_notice_outputs_escaped_actionable_markup() {
		update_option( 'home', 'https://prod.example.com/' );

		ob_start();
		Loader::get()->render_plugin_row_notice( EXPRESSION_LAB_BASENAME );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( '<tr class="plugin-update-tr expressionlab-staging-notice', $html );
		$this->assertStringContainsString( 'data-plugin="' . esc_attr( EXPRESSION_LAB_BASENAME ) . '"', $html );
		$this->assertMatchesRegularExpression( '/<td colspan="\d+" class="plugin-update colspanchange"/', $html );
		$this->assertStringContainsString( 'notice inline notice-warning notice-alt', $html );
		$this->assertStringContainsString( '<code>prod.example.com</code>', $html );
		$this->assertStringContainsString( '<code>EXPRESSION_LAB_STAGING_URL</code>', $html );
		$this->assertStringContainsString( 'Staging mismatch:', $html );
		$this->assertStringNotContainsString( 'https://', $html );
	}

	public function test_render_plugin_row_notice_escapes_home_url() {
		// Bypass sanitize_option() so the raw payload reaches the renderer.
		add_filter(
			'pre_option_home',
			static function () {
				return 'https://prod.example.com/<script>alert(1)</script>';
			}
		);

		ob_start();
		Loader::get()->render_plugin_row_notice();
		$html = (string) ob_get_clean();

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}
}
