<?php

// phpcs:ignoreFile

use ExpressionLab\Core\LanguageEngine;
use ExpressionLab\Core\Services\Http;

class HttpTest extends WP_UnitTestCase {

	/**
	 * Public IP used for offline test URLs to pass SSRF checks without DNS lookups.
	 */
	const MOCK_PUBLIC_URL = 'https://1.1.1.1';

	/**
	 * Http instance.
	 *
	 * @var Http
	 */
	private $http;

	public function setUp(): void {
		parent::setUp();
		LanguageEngine::get()->reset();
		$this->http = new Http();
	}

	public function tearDown(): void {
		remove_all_filters( 'pre_http_request' );
		LanguageEngine::get()->reset();
		parent::tearDown();
	}

	public function test_ssrf_protection_blocks_loopback() {
		$this->expectException( \InvalidArgumentException::class );
		$this->http->get( 'http://127.0.0.1:8080' );
	}

	public function test_ssrf_protection_blocks_cloud_metadata() {
		$this->expectException( \InvalidArgumentException::class );
		$this->http->get( 'http://169.254.169.254/latest/meta-data/' );
	}

	public function test_invalid_scheme_is_blocked() {
		$this->expectException( \InvalidArgumentException::class );
		$this->http->get( 'file:///etc/passwd' );
	}

	public function test_invalid_url_format_is_blocked() {
		$this->expectException( \InvalidArgumentException::class );
		$this->http->get( 'not-a-valid-url' );
	}

	public function test_constants_exposed_via_get() {
		$this->assertEquals( 10, $this->http->DEFAULT_TIMEOUT );
		$this->assertEquals( 1048576, $this->http->MAX_BODY_BYTES );
		$this->assertEquals( 64, $this->http->MAX_JSON_DEPTH );
		$this->assertIsArray( $this->http->ALLOWED_OPTIONS );
		$this->assertContains( 'timeout', $this->http->ALLOWED_OPTIONS );
		$this->assertNotContains( 'reject_unsafe_urls', $this->http->ALLOWED_OPTIONS );
		$this->assertNotContains( 'stream', $this->http->ALLOWED_OPTIONS );
		$this->assertNotContains( 'filename', $this->http->ALLOWED_OPTIONS );
		$this->assertNotContains( 'redirection', $this->http->ALLOWED_OPTIONS );
		$this->assertNull( $this->http->NON_EXISTENT_CONSTANT );
	}

	public function test_options_sanitization_strips_disallowed_keys() {
		$reflection = new \ReflectionMethod( $this->http, 'sanitize_options' );

		$raw_options = array(
			'timeout'            => 15,
			'user-agent'         => 'CustomAgent/1.0',
			'reject_unsafe_urls' => false,
			'sslverify'          => false,
			'stream'             => true,
			'filename'           => '/var/www/shell.php',
			'method'             => 'DELETE',
			'redirection'        => 0,
		);

		$sanitized = $reflection->invoke( $this->http, $raw_options );

		$this->assertArrayHasKey( 'timeout', $sanitized );
		$this->assertEquals( 15, $sanitized['timeout'] );
		$this->assertArrayHasKey( 'user-agent', $sanitized );
		$this->assertEquals( 'CustomAgent/1.0', $sanitized['user-agent'] );
		$this->assertArrayNotHasKey( 'reject_unsafe_urls', $sanitized );
		$this->assertArrayNotHasKey( 'sslverify', $sanitized );
		$this->assertArrayNotHasKey( 'stream', $sanitized );
		$this->assertArrayNotHasKey( 'filename', $sanitized );
		$this->assertArrayNotHasKey( 'method', $sanitized );
		$this->assertArrayNotHasKey( 'redirection', $sanitized );
	}

	public function test_get_successful_response_and_json_decoding() {
		add_filter(
			'pre_http_request',
			function() {
				return array(
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'headers'  => array(
						'content-type' => 'application/json',
					),
					'body'     => '{"status":"active","count":42}',
				);
			}
		);

		$res = $this->http->get( self::MOCK_PUBLIC_URL );

		$this->assertSame( 200, $res['status'] );
		$this->assertSame( 'OK', $res['status_text'] );
		$this->assertIsArray( $res['json'] );
		$this->assertSame( 'active', $res['json']['status'] );
		$this->assertSame( 42, $res['json']['count'] );
	}

	public function test_get_handles_http_500_error_response() {
		add_filter(
			'pre_http_request',
			function() {
				return array(
					'response' => array(
						'code'    => 500,
						'message' => 'Internal Server Error',
					),
					'headers'  => array(
						'content-type' => 'text/plain',
					),
					'body'     => 'Internal Server Error Occurred',
				);
			}
		);

		$res = $this->http->get( self::MOCK_PUBLIC_URL );

		$this->assertSame( 500, $res['status'] );
		$this->assertSame( 'Internal Server Error Occurred', $res['body'] );
		$this->assertNull( $res['json'] );
	}

	public function test_get_throws_runtime_exception_on_network_timeout() {
		add_filter(
			'pre_http_request',
			function() {
				return new \WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' );
			}
		);

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'cURL error 28: Operation timed out' );

		$this->http->get( self::MOCK_PUBLIC_URL );
	}

	public function test_get_caps_response_body_at_max_body_bytes() {
		add_filter(
			'pre_http_request',
			function() {
				return array(
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'headers'  => array(
						'content-type' => 'text/plain',
					),
					'body'     => str_repeat( 'X', Http::MAX_BODY_BYTES + 500 ),
				);
			}
		);

		$res = $this->http->get( self::MOCK_PUBLIC_URL );

		$this->assertSame( Http::MAX_BODY_BYTES, strlen( $res['body'] ) );
	}

	public function test_get_leaves_json_null_on_malformed_payload() {
		add_filter(
			'pre_http_request',
			function() {
				return array(
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'headers'  => array(
						'content-type' => 'application/json',
					),
					'body'     => '{"unclosed_json":',
				);
			}
		);

		$res = $this->http->get( self::MOCK_PUBLIC_URL );

		$this->assertSame( '{"unclosed_json":', $res['body'] );
		$this->assertNull( $res['json'] );
	}

	public function test_post_serializes_array_to_json_with_case_insensitive_content_type() {
		$captured_args = null;
		add_filter(
			'pre_http_request',
			function( $preempt, $parsed_args, $url ) use ( &$captured_args ) {
				$captured_args = $parsed_args;
				return array(
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'headers'  => array(
						'content-type' => 'application/json',
					),
					'body'     => '{"received":true}',
				);
			},
			10,
			3
		);

		$this->http->post(
			self::MOCK_PUBLIC_URL,
			array( 'event' => 'ping' ),
			array( 'Content-Type' => 'application/json' )
		);

		$this->assertNotNull( $captured_args );
		$this->assertSame( '{"event":"ping"}', $captured_args['body'] );
	}

	public function test_post_preserves_array_without_json_content_type() {
		$captured_args = null;
		add_filter(
			'pre_http_request',
			function( $preempt, $parsed_args, $url ) use ( &$captured_args ) {
				$captured_args = $parsed_args;
				return array(
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'headers'  => array(
						'content-type' => 'text/plain',
					),
					'body'     => 'OK',
				);
			},
			10,
			3
		);

		$this->http->post(
			self::MOCK_PUBLIC_URL,
			array( 'username' => 'admin' )
		);

		$this->assertNotNull( $captured_args );
		$this->assertIsArray( $captured_args['body'] );
		$this->assertSame( array( 'username' => 'admin' ), $captured_args['body'] );
	}

	public function test_send_request_ticks_post_request() {
		add_filter(
			'pre_http_request',
			function() {
				return array(
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'headers'  => array(),
					'body'     => 'OK',
				);
			}
		);

		// Advance operations counter to 49999 so that 1st tick reaches 50000 and 2nd tick (post-request) exceeds 50000.
		$engine = LanguageEngine::get()->reset();
		$engine->tick( $remaining, 49999 );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Maximum number of operations exceeded' );

		$this->http->get( self::MOCK_PUBLIC_URL );
	}
}

