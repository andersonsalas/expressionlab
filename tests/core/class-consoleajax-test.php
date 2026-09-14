<?php

// phpcs:ignoreFile

use ExpressionLab\Admin\Pages\Console;
use ExpressionLab\Core\Helper;

/**
 * @group ajax
 */
class ConsoleAjaxTest extends WP_Ajax_UnitTestCase {

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	private $admin_id;

	public function setUp(): void {
		parent::setUp();

		$admin_user_id = defined( 'EXPRESSION_LAB_ADMIN_USER_ID' ) ? (int) constant( 'EXPRESSION_LAB_ADMIN_USER_ID' ) : 1;
		$existing_admin = get_user_by( 'id', $admin_user_id );
		if ( $existing_admin ) {
			$existing_admin->set_role( 'administrator' );
			$this->admin_id = $admin_user_id;
		} else {
			$this->admin_id = self::factory()->user->create( array(
				'role'      => 'administrator',
				'import_id' => $admin_user_id,
			) );
		}
		wp_set_current_user( $this->admin_id );

		// Ensure hooks are initialized.
		Console::get()->init_hooks();
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		$_POST = array();
		$_GET  = array();
		parent::tearDown();
	}


	public function test_evaluate_without_nonce_is_rejected() {
		$_POST = array(
			'action'     => 'expressionlab_evaluate_expression',
			'expression' => '1 + 1',
		);

		try {
			$this->_handleAjax( 'expressionlab_evaluate_expression' );
			$this->fail( 'Expected WPAjaxDieContinueException was not thrown.' );
		} catch ( \WPAjaxDieContinueException $e ) {
			$response = json_decode( $this->_last_response, true );
			$this->assertFalse( $response['success'] );
			$this->assertSame( 'Unauthorized', $response['data']['message'] );
		}
	}

	public function test_evaluate_with_invalid_nonce_is_rejected() {
		$_POST = array(
			'action'     => 'expressionlab_evaluate_expression',
			'nonce'      => 'invalid_nonce_value',
			'expression' => '1 + 1',
		);

		try {
			$this->_handleAjax( 'expressionlab_evaluate_expression' );
			$this->fail( 'Expected WPAjaxDieContinueException was not thrown.' );
		} catch ( \WPAjaxDieContinueException $e ) {
			$response = json_decode( $this->_last_response, true );
			$this->assertFalse( $response['success'] );
			$this->assertSame( 'Unauthorized', $response['data']['message'] );
		}
	}

	public function test_evaluate_unauthorized_user_is_rejected() {
		$unauthorized_admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $unauthorized_admin );

		$_POST = array(
			'action'     => 'expressionlab_evaluate_expression',
			'nonce'      => wp_create_nonce( 'fpw_nonce' ),
			'expression' => '1 + 1',
		);

		try {
			$this->_handleAjax( 'expressionlab_evaluate_expression' );
			$this->fail( 'Expected WPAjaxDieContinueException was not thrown.' );
		} catch ( \WPAjaxDieContinueException $e ) {
			$response = json_decode( $this->_last_response, true );
			$this->assertFalse( $response['success'] );
			$this->assertSame( 'Unauthorized', $response['data']['message'] );
		}
	}

	public function test_evaluate_empty_expression_returns_bad_request() {
		wp_set_current_user( $this->admin_id );

		$_POST = array(
			'action'          => 'expressionlab_evaluate_expression',
			'nonce'           => wp_create_nonce( 'fpw_nonce' ),
			'expression'      => '',
			'challenge_nonce' => 'test_challenge',
			'timestamp'       => time(),
		);

		try {
			$this->_handleAjax( 'expressionlab_evaluate_expression' );
			$this->fail( 'Expected WPAjaxDieContinueException was not thrown.' );
		} catch ( \WPAjaxDieContinueException $e ) {
			$response = json_decode( $this->_last_response, true );
			$this->assertFalse( $response['success'] );
			$this->assertNotEmpty( $response['data']['message'] );
		}
	}

	public function test_get_outline_without_nonce_is_rejected() {
		$_POST = array(
			'action' => 'expressionlab_get_outline',
		);

		try {
			$this->_handleAjax( 'expressionlab_get_outline' );
			$this->fail( 'Expected WPAjaxDieContinueException was not thrown.' );
		} catch ( \WPAjaxDieContinueException $e ) {
			$response = json_decode( $this->_last_response, true );
			$this->assertFalse( $response['success'] );
		}
	}
}
