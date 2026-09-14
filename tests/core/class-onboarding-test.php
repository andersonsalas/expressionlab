<?php

// phpcs:ignoreFile

use ExpressionLab\Admin\Pages\Onboarding;
use ExpressionLab\Core\Helper;

class OnboardingTest extends WP_UnitTestCase {

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	private $admin_id;

	/**
	 * Subscriber user ID.
	 *
	 * @var int
	 */
	private $subscriber_id;

	public function setUp(): void {
		parent::setUp();

		$this->admin_id      = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	public function test_should_load_depends_on_plugin_configuration() {
		$onboarding = Onboarding::get();
		$this->assertSame( ! Helper::plugin_is_fully_configured(), $onboarding->should_load() );
	}

	public function test_get_asset_name_returns_onboard_handle() {
		$onboarding = Onboarding::get();
		$reflection = new \ReflectionMethod( $onboarding, 'get_asset_name' );

		$this->assertSame( 'expressionlab-onboard', $reflection->invoke( $onboarding ) );
	}

	public function test_get_script_data_structure() {
		wp_set_current_user( $this->admin_id );

		$onboarding = Onboarding::get();
		$reflection = new \ReflectionMethod( $onboarding, 'get_script_data' );

		$data = $reflection->invoke( $onboarding );

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'version', $data );
		$this->assertArrayHasKey( 'user_id', $data );
		$this->assertArrayHasKey( 'server', $data );
		$this->assertArrayHasKey( 'i18n', $data );

		$this->assertSame( $this->admin_id, $data['user_id'] );
		$this->assertIsBool( $data['server']['sqlite_enabled'] );
		$this->assertIsBool( $data['server']['sodium_enabled'] );
		$this->assertNotEmpty( $data['i18n'] );
	}

	public function test_render_rejects_subscribers_with_wp_die() {
		wp_set_current_user( $this->subscriber_id );

		$this->expectException( \WPDieException::class );
		Onboarding::get()->render();
	}

	public function test_render_outputs_html_for_authorized_admin() {
		wp_set_current_user( $this->admin_id );

		ob_start();
		Onboarding::get()->render();
		$output = ob_get_clean();

		$this->assertIsString( $output );
		$this->assertStringContainsString( 'expressionlab-app', $output );
	}
}
