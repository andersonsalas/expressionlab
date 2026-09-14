<?php

// phpcs:ignoreFile

use ExpressionLab\Core\Helper;
use ExpressionLab\Admin\Pages\Console;
use ExpressionLab\Core\Exceptions\SecurityException;

class ConsoleSecurityTest extends WP_UnitTestCase {

	/**
	 * Admin user whose ID matches EXPRESSION_LAB_ADMIN_USER_ID.
	 *
	 * @var int
	 */
	private $authorized_admin_id;

	/**
	 * Admin user whose ID does NOT match EXPRESSION_LAB_ADMIN_USER_ID.
	 *
	 * @var int
	 */
	private $unauthorized_admin_id;

	/**
	 * Non-admin (subscriber) user.
	 *
	 * @var int
	 */
	private $subscriber_id;

	public function setUp(): void {
		parent::setUp();

		// Create the authorized admin: the one matching EXPRESSION_LAB_ADMIN_USER_ID.
		$admin_user_id = defined( 'EXPRESSION_LAB_ADMIN_USER_ID' ) ? (int) constant( 'EXPRESSION_LAB_ADMIN_USER_ID' ) : 1;
		$existing_admin = get_user_by( 'id', $admin_user_id );
		if ( $existing_admin ) {
			$existing_admin->set_role( 'administrator' );
			$this->authorized_admin_id = $admin_user_id;
		} else {
			$this->authorized_admin_id = self::factory()->user->create( array(
				'role'      => 'administrator',
				'import_id' => $admin_user_id,
			) );
		}

		// Create a second admin whose ID will NOT match.
		$this->unauthorized_admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		// Create a subscriber (no admin capabilities).
		$this->subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * @group security
	 */
	public function test_expressionlab_admin_user_id_constant_effect_on_configuration() {
		if ( ! defined( 'EXPRESSION_LAB_ADMIN_USER_ID' ) ) {
			$this->assertFalse(
				Helper::plugin_is_fully_configured(),
				'plugin_is_fully_configured() must return false when EXPRESSION_LAB_ADMIN_USER_ID is not defined.'
			);
		} else {
			$this->assertNotEmpty(
				constant( 'EXPRESSION_LAB_ADMIN_USER_ID' ),
				'EXPRESSION_LAB_ADMIN_USER_ID must not be empty when defined.'
			);
		}
	}

	/**
	 * @group security
	 */
	public function test_admin_id_matching_logic_authorized_user() {
		wp_set_current_user( $this->authorized_admin_id );

		$allowed_admin_id = $this->authorized_admin_id;

		$current_user = wp_get_current_user();
		$this->assertTrue( current_user_can( 'manage_options' ), 'Authorized admin must have manage_options capability.' );
		$this->assertSame( $allowed_admin_id, $current_user->ID, 'Current user ID must match the allowed admin ID.' );
		$this->assertTrue( Helper::user_is_expressionlab_admin(), 'Helper::user_is_expressionlab_admin() must be true for authorized admin.' );
	}

	/**
	 * @group security
	 */
	public function test_admin_id_matching_logic_unauthorized_admin() {
		wp_set_current_user( $this->unauthorized_admin_id );

		$allowed_admin_id = $this->authorized_admin_id;

		$current_user = wp_get_current_user();
		$this->assertTrue( current_user_can( 'manage_options' ), 'Unauthorized admin still has manage_options capability.' );
		$this->assertNotSame( $allowed_admin_id, $current_user->ID, 'Unauthorized admin ID must NOT match the allowed admin ID.' );
		$this->assertFalse( Helper::user_is_expressionlab_admin(), 'Helper::user_is_expressionlab_admin() must be false for unauthorized admin.' );
	}

	/**
	 * @group security
	 */
	public function test_admin_id_matching_logic_subscriber() {
		wp_set_current_user( $this->subscriber_id );

		$this->assertFalse( current_user_can( 'manage_options' ), 'Subscriber must not have manage_options capability.' );
		$this->assertFalse( Helper::user_is_expressionlab_admin(), 'Helper::user_is_expressionlab_admin() must be false for subscriber.' );
	}

	/**
	 * @group security
	 */
	public function test_admin_id_matching_logic_logged_out() {
		wp_set_current_user( 0 );

		$this->assertFalse( is_user_logged_in(), 'Logged-out user must not be logged in.' );
		$this->assertFalse( current_user_can( 'manage_options' ), 'Logged-out user must not have manage_options capability.' );
		$this->assertFalse( Helper::user_is_expressionlab_admin(), 'Helper::user_is_expressionlab_admin() must be false for logged-out user.' );
	}

	/**
	 * @group security
	 */
	public function test_plugin_is_fully_configured_returns_correct_value() {
		$all_defined = defined( 'EXPRESSION_LAB_ADMIN_USER_ID' )
			&& ! empty( constant( 'EXPRESSION_LAB_ADMIN_USER_ID' ) )
			&& defined( 'EXPRESSION_LAB_ADMIN_PUBLIC_KEY' )
			&& ! empty( constant( 'EXPRESSION_LAB_ADMIN_PUBLIC_KEY' ) )
			&& defined( 'EXPRESSION_LAB_ADMIN_SALT' )
			&& ! empty( constant( 'EXPRESSION_LAB_ADMIN_SALT' ) );

		$this->assertSame(
			$all_defined,
			Helper::plugin_is_fully_configured(),
			'plugin_is_fully_configured() must return true only when all three required constants are defined and non-empty.'
		);
	}

	/**
	 * @group security
	 */
	public function test_should_enqueue_script_authorization() {
		$console    = Console::get();
		$reflection = new \ReflectionMethod( $console, 'should_enqueue_script' );

		wp_set_current_user( $this->unauthorized_admin_id );
		$this->assertFalse( $reflection->invoke( $console ) );

		wp_set_current_user( $this->authorized_admin_id );
		$this->assertTrue( $reflection->invoke( $console ) );
	}

	public function test_get_asset_name_loads_app_css_for_unauthorized_admins() {
		$console    = Console::get();
		$reflection = new \ReflectionMethod( $console, 'get_asset_name' );

		wp_set_current_user( $this->unauthorized_admin_id );
		$this->assertSame( 'expressionlab-app', $reflection->invoke( $console ) );
	}

	/**
	 * @group security
	 */
	public function test_render_calls_wp_die_for_users_without_manage_options() {
		wp_set_current_user( $this->subscriber_id );

		$this->expectException( \WPDieException::class );
		Console::get()->render();
	}

	/**
	 * @group security
	 */
	public function test_render_displays_access_restricted_banner_or_onboarding() {
		wp_set_current_user( $this->unauthorized_admin_id );

		ob_start();
		Console::get()->render();
		$output = ob_get_clean();

		$this->assertIsString( $output );

		// If fully configured, the access restricted banner is rendered.
		if ( Helper::plugin_is_fully_configured() ) {
			$this->assertStringContainsString( 'Access Restricted', $output );
			$this->assertStringContainsString( 'expressionlab-admin-banner', $output );
		}
	}

	/**
	 * @group security
	 */
	public function test_render_displays_app_for_authorized_admin() {
		wp_set_current_user( $this->authorized_admin_id );

		ob_start();
		Console::get()->render();
		$output = ob_get_clean();

		$this->assertIsString( $output );
		$this->assertStringContainsString( 'expressionlab-app', $output );
	}

	/**
	 * @group security
	 */
	public function test_should_render_sandbox_checks_admin_authorization() {
		$console = Console::get();

		wp_set_current_user( $this->unauthorized_admin_id );
		$this->assertFalse( $console->should_render_sandbox() );

		wp_set_current_user( $this->authorized_admin_id );
		$this->assertIsBool( $console->should_render_sandbox() );
	}

	/**
	 * @group security
	 */
	public function test_get_script_data_includes_public_key_and_salt() {
		wp_set_current_user( $this->authorized_admin_id );

		$console    = Console::get();
		$reflection = new \ReflectionMethod( $console, 'get_script_data' );

		$data = $reflection->invoke( $console );

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'public_key', $data );
		$this->assertArrayHasKey( 'salt', $data );
	}

	/**
	 * @group security
	 */
	public function test_get_script_data_public_key_matches_constant() {
		wp_set_current_user( $this->authorized_admin_id );

		$console    = Console::get();
		$reflection = new \ReflectionMethod( $console, 'get_script_data' );

		$data = $reflection->invoke( $console );

		if ( ! defined( 'EXPRESSION_LAB_ADMIN_PUBLIC_KEY' ) ) {
			$this->assertSame( '', $data['public_key'] );
		} else {
			$this->assertSame(
				EXPRESSION_LAB_ADMIN_PUBLIC_KEY,
				$data['public_key']
			);
		}
	}

	/**
	 * @group security
	 */
	public function test_get_script_data_salt_matches_constant() {
		wp_set_current_user( $this->authorized_admin_id );

		$console    = Console::get();
		$reflection = new \ReflectionMethod( $console, 'get_script_data' );

		$data = $reflection->invoke( $console );

		if ( ! defined( 'EXPRESSION_LAB_ADMIN_SALT' ) ) {
			$this->assertSame( '', $data['salt'] );
		} else {
			$this->assertSame(
				EXPRESSION_LAB_ADMIN_SALT,
				$data['salt']
			);
		}
	}

	/**
	 * @group security
	 */
	public function test_get_script_data_includes_nonce() {
		wp_set_current_user( $this->authorized_admin_id );

		$console    = Console::get();
		$reflection = new \ReflectionMethod( $console, 'get_script_data' );

		$data = $reflection->invoke( $console );

		$this->assertArrayHasKey( 'nonce', $data );
		$this->assertNotEmpty( $data['nonce'] );
	}

	/**
	 * @group security
	 */
	public function test_get_script_data_does_not_expose_private_key() {
		wp_set_current_user( $this->authorized_admin_id );

		$console    = Console::get();
		$reflection = new \ReflectionMethod( $console, 'get_script_data' );

		$data     = $reflection->invoke( $console );
		$json_str = wp_json_encode( $data );

		$this->assertArrayNotHasKey( 'private_key', $data );
		$this->assertStringNotContainsString( 'private_key', $json_str );
	}

	/**
	 * @group security
	 */
	public function test_verify_and_rotate_challenge_functional_behavior() {
		wp_set_current_user( $this->authorized_admin_id );

		$console    = Console::get();
		$reflection = new \ReflectionMethod( $console, 'verify_and_rotate_challenge' );

		if ( false === EXPRESSION_LAB_SANDBOX_ENABLED ) {
			$simulated = $reflection->invoke( $console, 'dummy_nonce', time() );
			$this->assertIsArray( $simulated );
			$this->assertArrayHasKey( 'nonce', $simulated );
			return;
		}

		$initial_nonce = bin2hex( random_bytes( 16 ) );
		$current_time  = time();

		update_user_meta(
			$this->authorized_admin_id,
			'expressionlab_challenge',
			array(
				'nonce'     => $initial_nonce,
				'timestamp' => $current_time,
			)
		);

		// 1. Expired timestamp must fail.
		$expired_result = $reflection->invoke(
			$console,
			$initial_nonce,
			$current_time - ( EXPRESSION_LAB_SIGNATURE_DURATION + 100 )
		);
		$this->assertFalse( $expired_result );

		// 2. Mismatched nonce must fail.
		$mismatch_result = $reflection->invoke(
			$console,
			'invalid_nonce_string',
			$current_time
		);
		$this->assertFalse( $mismatch_result );

		// 3. Valid nonce and fresh timestamp must succeed and rotate.
		$rotated = $reflection->invoke(
			$console,
			$initial_nonce,
			$current_time
		);
		$this->assertIsArray( $rotated );
		$this->assertArrayHasKey( 'nonce', $rotated );
		$this->assertNotSame( $initial_nonce, $rotated['nonce'] );

		// 4. Stored user meta must match the newly rotated nonce.
		$stored_after = get_user_meta( $this->authorized_admin_id, 'expressionlab_challenge', true );
		$this->assertSame( $rotated['nonce'], $stored_after['nonce'] );
	}

	/**
	 * @group security
	 */
	public function test_validate_ajax_request_rejects_malformed_signature_format() {
		wp_set_current_user( $this->authorized_admin_id );

		$_REQUEST['nonce']                         = wp_create_nonce( Console::NONCE_ACTION );
		$_SERVER['HTTP_X_EXPRESSIONLAB_SIGNATURE'] = base64_encode( 'malformed_short_bytes' );

		$this->expectException( SecurityException::class );
		$this->expectExceptionMessage( 'Invalid signature.' );

		try {
			Console::get()->validate_ajax_request();
		} finally {
			unset( $_SERVER['HTTP_X_EXPRESSIONLAB_SIGNATURE'] );
			unset( $_REQUEST['nonce'] );
		}
	}

	/**
	 * @group security
	 */
	public function test_validate_ajax_request_rejects_missing_signature() {
		wp_set_current_user( $this->authorized_admin_id );

		$_REQUEST['nonce'] = wp_create_nonce( Console::NONCE_ACTION );
		unset( $_SERVER['HTTP_X_EXPRESSIONLAB_SIGNATURE'] );

		$this->expectException( SecurityException::class );
		$this->expectExceptionMessage( 'Invalid signature.' );

		try {
			Console::get()->validate_ajax_request();
		} finally {
			unset( $_REQUEST['nonce'] );
		}
	}

	/**
	 * @group security
	 */
	public function test_validate_ajax_request_rejects_unauthorized_user() {
		wp_set_current_user( $this->subscriber_id );

		$_REQUEST['nonce'] = wp_create_nonce( Console::NONCE_ACTION );

		$this->expectException( SecurityException::class );
		$this->expectExceptionMessage( 'Unauthorized' );

		try {
			Console::get()->validate_ajax_request();
		} finally {
			unset( $_REQUEST['nonce'] );
		}
	}

	/**
	 * @group security
	 */
	public function test_validate_ajax_request_rejects_invalid_nonce() {
		wp_set_current_user( $this->authorized_admin_id );

		$_REQUEST['nonce'] = 'completely_invalid_nonce';

		$this->expectException( SecurityException::class );
		$this->expectExceptionMessage( 'Unauthorized' );

		try {
			Console::get()->validate_ajax_request();
		} finally {
			unset( $_REQUEST['nonce'] );
		}
	}
}
