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

	/**
	 * Challenge nonce seeded by the last batch helper invocation.
	 *
	 * @var string
	 */
	private $last_seeded_nonce = '';

	public function setUp(): void {
		parent::setUp();

		remove_action( 'admin_init', '_maybe_update_core' );
		remove_action( 'admin_init', '_maybe_update_plugins' );
		remove_action( 'admin_init', '_maybe_update_themes' );

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

	public static function tear_down_after_class() {
		if ( ! has_action( 'admin_init', '_maybe_update_core' ) ) {
			add_action( 'admin_init', '_maybe_update_core' );
		}
		if ( ! has_action( 'admin_init', '_maybe_update_plugins' ) ) {
			add_action( 'admin_init', '_maybe_update_plugins' );
		}
		if ( ! has_action( 'admin_init', '_maybe_update_themes' ) ) {
			add_action( 'admin_init', '_maybe_update_themes' );
		}

		parent::tear_down_after_class();
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

	/**
	 * Helper to execute Console::evaluate_batch with a valid challenge and capture the JSON response.
	 *
	 * @param mixed       $expressions      Raw expressions payload (JSON string or array).
	 * @param string|null $request_nonce    Optional challenge nonce sent by the client (defaults to the stored one).
	 * @return array Decoded JSON response array.
	 */
	private function execute_batch_payload( $expressions, ?string $request_nonce = null ): array {
		wp_set_current_user( $this->admin_id );
		\ExpressionLab\Core\LanguageEngine::get()->reset();

		$challenge_nonce         = bin2hex( random_bytes( 16 ) );
		$timestamp               = (string) time();
		$this->last_seeded_nonce = $challenge_nonce;

		update_user_meta(
			$this->admin_id,
			'expressionlab_challenge',
			array(
				'nonce'     => $challenge_nonce,
				'timestamp' => (int) $timestamp,
			)
		);

		$console    = Console::get();
		$reflection = new \ReflectionMethod( $console, 'evaluate_batch' );

		$this->_last_response = '';
		ini_set( 'implicit_flush', false );
		ob_start();

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', array( $this, 'getDieHandler' ), 1, 1 );

		try {
			$reflection->invoke(
				$console,
				$expressions,
				$this->admin_id,
				$this->admin_id,
				null,
				$request_nonce ?? $challenge_nonce,
				$timestamp,
				microtime( true )
			);
		} catch ( \WPAjaxDieContinueException $e ) {
			// Expected termination from wp_send_json_success / wp_send_json_error.
		} finally {
			remove_filter( 'wp_doing_ajax', '__return_true' );
			remove_filter( 'wp_die_ajax_handler', array( $this, 'getDieHandler' ), 1 );
		}

		$output = '' !== $this->_last_response ? $this->_last_response : ob_get_clean();

		return json_decode( $output, true ) ?? array();
	}

	public function test_evaluate_batch_sequential_state_and_isolated_messages() {
		$batch = wp_json_encode(
			array(
				array(
					'id'         => 'cell_1',
					'expression' => "prog[ Console.log('first cell message'), set['x', 40] ]",
				),
				array(
					'id'         => 'cell_2',
					'expression' => 'var.x + 2 === 42',
				),
			)
		);

		$response = $this->execute_batch_payload( $batch );

		$this->assertTrue( $response['success'] );
		$this->assertSame( 'batch_json', $response['data']['format'] );
		$this->assertArrayHasKey( 'challenge', $response['data'] );
		$this->assertCount( 2, $response['data']['results'] );

		$cell1 = $response['data']['results'][0];
		$this->assertSame( 'cell_1', $cell1['id'] );
		$this->assertSame( 'success', $cell1['status'] );
		$this->assertSame( 40, $cell1['result'] );
		$this->assertCount( 1, $cell1['messages'] );
		$this->assertSame( 'first cell message', $cell1['messages'][0]['text'] );

		$cell2 = $response['data']['results'][1];
		$this->assertSame( 'cell_2', $cell2['id'] );
		$this->assertSame( 'success', $cell2['status'] );
		$this->assertTrue( $cell2['result'] );
		$this->assertSame( array(), $cell2['messages'] );
		$this->assertSame( array(), $cell2['visualizations'] );
	}

	public function test_evaluate_batch_fail_fast_preserves_previous_results_and_marks_skipped() {
		$batch = wp_json_encode(
			array(
				array(
					'id'         => 'cell_1',
					'expression' => "set['x', 10]",
				),
				array(
					'id'         => 'cell_2',
					'expression' => '1 + (',
				),
				array(
					'id'         => 'cell_3',
					'expression' => 'var.x + 5',
				),
			)
		);

		$response = $this->execute_batch_payload( $batch );

		$this->assertTrue( $response['success'] );
		$this->assertCount( 3, $response['data']['results'] );

		$this->assertSame( 'cell_1', $response['data']['results'][0]['id'] );
		$this->assertSame( 'success', $response['data']['results'][0]['status'] );
		$this->assertSame( 10, $response['data']['results'][0]['result'] );

		$this->assertSame( 'cell_2', $response['data']['results'][1]['id'] );
		$this->assertSame( 'error', $response['data']['results'][1]['status'] );
		$this->assertNotEmpty( $response['data']['results'][1]['message'] );

		$this->assertSame( 'cell_3', $response['data']['results'][2]['id'] );
		$this->assertSame( 'skipped', $response['data']['results'][2]['status'] );
	}

	public function test_evaluate_batch_rejects_oversized_batch() {
		$items = array();
		for ( $i = 0; $i <= \ExpressionLab\Core\LanguageEngine::MAX_BATCH_EXPRESSIONS; $i++ ) {
			$items[] = '1 + 1';
		}

		$response = $this->execute_batch_payload( wp_json_encode( $items ) );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'maximum limit of 25 expressions', $response['data']['message'] );
	}

	public function test_get_script_data_exposes_addon_enabled_setting() {
		wp_set_current_user( $this->admin_id );
		$console    = Console::get();
		$reflection = new \ReflectionMethod( $console, 'get_script_data' );
		$data       = $reflection->invoke( $console );

		$this->assertArrayHasKey( 'settings', $data );
		$this->assertArrayHasKey( 'addon_enabled', $data['settings'] );
		$this->assertIsBool( $data['settings']['addon_enabled'] );
	}

	/**
	 * @dataProvider provide_malformed_batch_payloads
	 */
	public function test_evaluate_batch_rejects_malformed_payloads_before_rotating_challenge( $payload, string $expected_message ) {
		$response = $this->execute_batch_payload( $payload );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( $expected_message, $response['data']['message'] );

		$stored = get_user_meta( $this->admin_id, 'expressionlab_challenge', true );
		$this->assertSame( $this->last_seeded_nonce, $stored['nonce'] );
		$this->assertFalse( \ExpressionLab\Core\LanguageEngine::get()->get_variable_store()->has( 'leak' ) );
	}

	public function provide_malformed_batch_payloads(): array {
		return array(
			'invalid json'        => array( '[{"expression": "1 + 1"', 'No expressions provided' ),
			'json string scalar'  => array( '"1 + 1"', 'No expressions provided' ),
			'json number scalar'  => array( '42', 'No expressions provided' ),
			'empty json array'    => array( '[]', 'No expressions provided' ),
			'json object map'     => array( '{"a": "set[\'leak\', 1]", "b": "2"}', 'sequential list' ),
			'empty cell'          => array( wp_json_encode( array( "set['leak', 1]", '' ) ), 'No expression provided' ),
			'non string cell'     => array( wp_json_encode( array( "set['leak', 1]", 42 ) ), 'No expression provided' ),
			'non string property' => array( wp_json_encode( array( array( 'expression' => array( '1' ) ) ) ), 'No expression provided' ),
		);
	}

	public function test_evaluate_batch_with_invalid_challenge_is_rejected_without_evaluating() {
		$response = $this->execute_batch_payload( wp_json_encode( array( "set['leak', 1]" ) ), 'forged_nonce' );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'challenge_required', $response['data']['code'] );
		$this->assertFalse( \ExpressionLab\Core\LanguageEngine::get()->get_variable_store()->has( 'leak' ) );
	}

	public function test_evaluate_batch_error_cell_keeps_messages_emitted_before_the_failure() {
		$batch = wp_json_encode(
			array(
				array(
					'id'         => 'cell_1',
					'expression' => "prog[ Console.log('before failure'), Files.read('wp-content/__expressionlab_missing__.txt') ]",
				),
				array(
					'id'         => 'cell_2',
					'expression' => '1 + 1',
				),
			)
		);

		$response = $this->execute_batch_payload( $batch );

		$this->assertTrue( $response['success'] );
		$cell1 = $response['data']['results'][0];
		$this->assertSame( 'error', $cell1['status'] );
		$this->assertNotEmpty( $cell1['message'] );
		$this->assertSame( 'before failure', $cell1['messages'][0]['text'] );
		$this->assertArrayNotHasKey( 'visualizations', $cell1 );
		$this->assertSame( 'skipped', $response['data']['results'][1]['status'] );
	}

	public function test_evaluate_batch_sanitizes_and_bounds_cell_ids() {
		$batch = wp_json_encode(
			array(
				array(
					'id'         => '<b>chart</b>' . str_repeat( 'x', 200 ),
					'expression' => '1',
				),
				array(
					'id'         => '<script></script>',
					'expression' => '2',
				),
			)
		);

		$response = $this->execute_batch_payload( $batch );

		$this->assertTrue( $response['success'] );
		$first_id = $response['data']['results'][0]['id'];
		$this->assertSame( Console::MAX_BATCH_CELL_ID_LENGTH, strlen( $first_id ) );
		$this->assertStringStartsWith( 'chart', $first_id );
		$this->assertStringNotContainsString( '<', $first_id );
		$this->assertSame( 'cell_2', $response['data']['results'][1]['id'] );
	}

	public function test_evaluate_batch_shares_embedded_binary_budget_across_cells() {
		$upload_dir = wp_upload_dir();
		$dest_dir   = $upload_dir['basedir'] . '/expressionlab-tests';
		wp_mkdir_p( $dest_dir );
		$dest_file = $dest_dir . '/batch-budget.svg';
		$padding   = (int) ( \ExpressionLab\Core\LanguageEngine::MAX_EMBEDDED_BINARY_BYTES * 0.6 );
		file_put_contents( $dest_file, '<?xml version="1.0"?>' . "\n" . '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"><!-- ' . str_repeat( 'a', $padding ) . ' --></svg>' . "\n" );

		try {
			$relative_path = ltrim( str_replace( ABSPATH, '', $dest_file ), '/' );
			$expression    = "Files.read('" . $relative_path . "')";

			$response = $this->execute_batch_payload( wp_json_encode( array( $expression, $expression ) ) );

			$this->assertTrue( $response['success'] );
			$cell1 = $response['data']['results'][0];
			$cell2 = $response['data']['results'][1];

			$this->assertSame( 'success', $cell1['status'] );
			$this->assertNotEmpty( $cell1['visualizations'] );

			$this->assertSame( 'success', $cell2['status'] );
			$this->assertFalse( $cell2['result'] );
			$this->assertSame( array(), $cell2['visualizations'] );
			$this->assertStringContainsString( 'budget', $cell2['messages'][0]['text'] );

			$this->assertTrue( \ExpressionLab\Core\LanguageEngine::get()->can_allocate_binary( \ExpressionLab\Core\LanguageEngine::MAX_EMBEDDED_BINARY_BYTES ) );
		} finally {
			if ( file_exists( $dest_file ) ) {
				unlink( $dest_file );
			}
		}
	}

	public function test_batch_payload_limit_trims_heaviest_visualizations_first() {
		$mib     = 1024 * 1024;
		$medium  = array( array( 'type' => 'text', 'content' => str_repeat( 'm', 2 * $mib ) ) );
		$heavy   = array( array( 'type' => 'text', 'content' => str_repeat( 'h', 3 * $mib ) ) );
		$light   = array( array( 'type' => 'text', 'content' => 'tiny' ) );
		$heavier = array( array( 'type' => 'text', 'content' => str_repeat( 'g', (int) ( 2.5 * $mib ) ) ) );
		$small   = array( array( 'type' => 'text', 'content' => str_repeat( 's', $mib ) ) );

		$response = array(
			'format'    => 'batch_json',
			'challenge' => array( 'nonce' => 'n', 'ts' => 1 ),
			'results'   => array(
				array( 'id' => 'medium', 'status' => 'success', 'messages' => array(), 'visualizations' => $medium ),
				array( 'id' => 'heavy', 'status' => 'success', 'messages' => array(), 'visualizations' => $heavy ),
				array( 'id' => 'light', 'status' => 'success', 'messages' => array(), 'visualizations' => $light ),
				array( 'id' => 'heavier', 'status' => 'success', 'messages' => array(), 'visualizations' => $heavier ),
				array( 'id' => 'small', 'status' => 'success', 'messages' => array(), 'visualizations' => $small ),
			),
		);

		$reflection = new \ReflectionMethod( Console::get(), 'enforce_batch_payload_limit' );
		$trimmed    = $reflection->invoke( Console::get(), $response );

		$this->assertSame( array(), $trimmed['results'][1]['visualizations'] );
		$this->assertSame( 'warning', $trimmed['results'][1]['messages'][0]['type'] );
		$this->assertSame( array(), $trimmed['results'][3]['visualizations'] );
		$this->assertSame( 'warning', $trimmed['results'][3]['messages'][0]['type'] );

		$this->assertSame( $medium, $trimmed['results'][0]['visualizations'] );
		$this->assertSame( array(), $trimmed['results'][0]['messages'] );
		$this->assertSame( $light, $trimmed['results'][2]['visualizations'] );
		$this->assertSame( $small, $trimmed['results'][4]['visualizations'] );

		$this->assertLessThanOrEqual(
			\ExpressionLab\Core\LanguageEngine::MAX_PAYLOAD_BYTES,
			strlen( wp_json_encode( array( 'success' => true, 'data' => $trimmed ) ) )
		);
	}

	public function test_batch_payload_limit_returns_response_untouched_when_within_budget() {
		$response = array(
			'format'    => 'batch_json',
			'challenge' => array( 'nonce' => 'n', 'ts' => 1 ),
			'results'   => array(
				array( 'id' => 'cell_1', 'status' => 'success', 'messages' => array(), 'visualizations' => array( array( 'type' => 'text' ) ) ),
			),
		);

		$reflection = new \ReflectionMethod( Console::get(), 'enforce_batch_payload_limit' );

		$this->assertSame( $response, $reflection->invoke( Console::get(), $response ) );
	}
}

