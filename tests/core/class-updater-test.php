<?php

// phpcs:ignoreFile

use ExpressionLab\Core\Updater;

class UpdaterTest extends WP_UnitTestCase {

	private Updater $updater;

	public function setUp(): void {
		parent::setUp();
		$this->updater = Updater::get();
		delete_site_transient( Updater::TRANSIENT_KEY );
	}

	public function tearDown(): void {
		delete_site_transient( Updater::TRANSIENT_KEY );
		parent::tearDown();
	}

	private function create_updater_with_public_key( string $public_key ): Updater {
		$mock = $this->getMockBuilder( Updater::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'get_public_key' ) )
			->getMock();
		$mock->method( 'get_public_key' )->willReturn( $public_key );
		return $mock;
	}

	public function test_updater_is_singleton() {
		$instance1 = Updater::get();
		$instance2 = Updater::get();

		$this->assertSame( $instance1, $instance2 );
	}

	public function test_init_registers_hooks() {
		$this->updater->init( true );

		$this->assertNotFalse( has_filter( 'site_transient_update_plugins', array( $this->updater, 'check_for_updates' ) ) );
		$this->assertNotFalse( has_filter( 'plugins_api', array( $this->updater, 'plugin_info' ) ) );
		$this->assertNotFalse( has_filter( 'upgrader_pre_download', array( $this->updater, 'verify_package_integrity' ) ) );
	}

	public function test_get_plugin_version_returns_defined_constant() {
		$this->assertSame( EXPRESSION_LAB_VERSION, $this->updater->get_plugin_version() );
	}

	public function test_get_manifest_url_returns_default_when_not_debug() {
		$this->assertSame( Updater::MANIFEST_URL, $this->updater->get_manifest_url() );
	}

	public function test_get_manifest_url_deterministic() {
		$expected = $this->updater->is_debug_mode() && defined( 'EXPRESSION_LAB_CUSTOM_MANIFEST_URL' ) && ! empty( constant( 'EXPRESSION_LAB_CUSTOM_MANIFEST_URL' ) )
			? constant( 'EXPRESSION_LAB_CUSTOM_MANIFEST_URL' )
			: ( defined( 'EXPRESSION_LAB_MANIFEST_URL' ) ? constant( 'EXPRESSION_LAB_MANIFEST_URL' ) : Updater::MANIFEST_URL );
		$this->assertSame( $expected, $this->updater->get_manifest_url() );
	}

	public function test_get_plugin_basename_returns_defined_constant() {
		$this->assertSame( EXPRESSION_LAB_BASENAME, $this->updater->get_plugin_basename() );
	}

	public function test_get_plugin_slug_returns_defined_constant() {
		$this->assertSame( EXPRESSION_LAB_SLUG, $this->updater->get_plugin_slug() );
	}

	public function test_get_homepage_url_returns_defined_constant() {
		$this->assertSame( EXPRESSION_LAB_HOMEPAGE_URL, $this->updater->get_homepage_url() );
	}

	public function test_get_transient_key_returns_defined_constant() {
		$this->assertSame( EXPRESSION_LAB_UPDATE_TRANSIENT_KEY, $this->updater->get_transient_key() );
	}

	public function test_get_public_key_returns_defined_constant() {
		$this->assertSame( EXPRESSION_LAB_PUBLIC_KEY, $this->updater->get_public_key() );
	}

	public function test_check_for_updates_handles_empty_transient() {
		$this->assertFalse( $this->updater->check_for_updates( false ) );
		$this->assertNull( $this->updater->check_for_updates( null ) );
	}

	public function test_check_for_updates_injects_new_version_when_available() {
		$manifest = array(
			'version'      => '99.0.0',
			'download_url' => 'https://example.com/expressionlab-99.0.0.zip',
			'sha256'       => 'mocksha256',
			'requires'     => '6.4',
			'requires_php' => '8.1',
			'tested'       => '6.9.4',
		);
		set_site_transient( Updater::TRANSIENT_KEY, $manifest, HOUR_IN_SECONDS );

		$transient = (object) array(
			'response'  => array(),
			'no_update' => array(),
		);

		$result = $this->updater->check_for_updates( $transient );

		$this->assertArrayHasKey( 'expressionlab/expressionlab.php', $result->response );
		$item = $result->response['expressionlab/expressionlab.php'];
		$this->assertSame( '99.0.0', $item->new_version );
		$this->assertSame( 'https://example.com/expressionlab-99.0.0.zip', $item->package );
		$this->assertSame( 'expressionlab', $item->slug );
	}

	public function test_check_for_updates_places_in_no_update_when_current() {
		$manifest = array(
			'version'      => EXPRESSION_LAB_VERSION,
			'download_url' => 'https://example.com/expressionlab-' . EXPRESSION_LAB_VERSION . '.zip',
			'sha256'       => 'mocksha256',
			'requires'     => '6.4',
			'requires_php' => '8.1',
			'tested'       => '6.9.4',
		);
		set_site_transient( Updater::TRANSIENT_KEY, $manifest, HOUR_IN_SECONDS );

		$transient = (object) array(
			'response'  => array(),
			'no_update' => array(),
		);

		$result = $this->updater->check_for_updates( $transient );

		$this->assertArrayHasKey( 'expressionlab/expressionlab.php', $result->no_update );
		$this->assertArrayNotHasKey( 'expressionlab/expressionlab.php', $result->response );
	}

	public function test_plugin_info_returns_modal_data() {
		$manifest = array(
			'name'         => 'Expression Lab',
			'version'      => '1.2.3',
			'download_url' => 'https://example.com/download.zip',
			'requires'     => '6.4',
			'requires_php' => '8.1',
			'tested'       => '6.9.4',
			'sections'     => array(
				'description' => 'Test description',
				'changelog'   => '<h4>1.2.3</h4>',
			),
		);
		set_site_transient( Updater::TRANSIENT_KEY, $manifest, HOUR_IN_SECONDS );

		$args = (object) array( 'slug' => 'expressionlab' );
		$info = $this->updater->plugin_info( false, 'plugin_information', $args );

		$this->assertIsObject( $info );
		$this->assertSame( 'Expression Lab', $info->name );
		$this->assertSame( '1.2.3', $info->version );
		$this->assertSame( 'Test description', $info->sections['description'] );
	}

	public function test_plugin_info_ignores_unrelated_plugins() {
		$args = (object) array( 'slug' => 'other-plugin' );
		$info = $this->updater->plugin_info( false, 'plugin_information', $args );

		$this->assertFalse( $info );
	}

	public function test_verify_package_integrity_ignores_other_plugins() {
		$reply = $this->updater->verify_package_integrity(
			false,
			'https://example.com/other.zip',
			new stdClass(),
			array( 'plugin' => 'other/other.php' )
		);

		$this->assertFalse( $reply );
	}

	public function test_verify_package_integrity_detects_checksum_mismatch() {
		$payload = 'dummy zip content';

		$manifest = array(
			'version' => '1.0.0',
			'sha256'  => 'ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff',
		);
		set_site_transient( Updater::TRANSIENT_KEY, $manifest, HOUR_IN_SECONDS );

		add_filter(
			'pre_http_request',
			function( $pre, $parsed_args ) use ( $payload ) {
				if ( ! empty( $parsed_args['filename'] ) ) {
					file_put_contents( $parsed_args['filename'], $payload );
				}
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => $payload,
				);
			},
			10,
			2
		);

		$result = $this->updater->verify_package_integrity(
			false,
			'https://example.com/test.zip',
			new stdClass(),
			array( 'plugin' => 'expressionlab/expressionlab.php' )
		);

		$this->assertWPError( $result );
		$this->assertSame( 'expressionlab_checksum_mismatch', $result->get_error_code() );
	}

	public function test_verify_package_integrity_validates_ed25519_signature() {
		if ( ! function_exists( 'sodium_crypto_sign_detached' ) ) {
			$this->markTestSkipped( 'Libsodium is not available.' );
		}

		$payload = 'safe verified zip contents';

		$keypair = sodium_crypto_sign_keypair();
		$secret  = sodium_crypto_sign_secretkey( $keypair );

		$signature = sodium_bin2hex( sodium_crypto_sign_detached( $payload, $secret ) );

		$manifest = array(
			'version'   => '1.0.0',
			'sha256'    => hash( 'sha256', $payload ),
			'signature' => $signature,
		);
		set_site_transient( Updater::TRANSIENT_KEY, $manifest, HOUR_IN_SECONDS );

		add_filter(
			'pre_http_request',
			function( $pre, $parsed_args ) use ( $payload ) {
				if ( ! empty( $parsed_args['filename'] ) ) {
					file_put_contents( $parsed_args['filename'], $payload );
				}
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => $payload,
				);
			},
			10,
			2
		);

		$result = $this->updater->verify_package_integrity(
			false,
			'https://example.com/test.zip',
			new stdClass(),
			array( 'plugin' => 'expressionlab/expressionlab.php' )
		);

		$this->assertWPError( $result );
		$this->assertSame( 'expressionlab_signature_mismatch', $result->get_error_code() );
	}

	public function test_verify_package_integrity_accepts_valid_signature() {
		if ( ! function_exists( 'sodium_crypto_sign_detached' ) ) {
			$this->markTestSkipped( 'Libsodium is not available.' );
		}

		$payload = 'trusted verified zip contents';

		$keypair    = sodium_crypto_sign_keypair();
		$secret     = sodium_crypto_sign_secretkey( $keypair );
		$public_hex = sodium_bin2hex( sodium_crypto_sign_publickey( $keypair ) );

		$updater = $this->create_updater_with_public_key( $public_hex );

		$signature_hex = sodium_bin2hex( sodium_crypto_sign_detached( $payload, $secret ) );

		$manifest = array(
			'version'   => '1.0.0',
			'sha256'    => hash( 'sha256', $payload ),
			'signature' => $signature_hex,
		);
		set_site_transient( Updater::TRANSIENT_KEY, $manifest, HOUR_IN_SECONDS );

		add_filter(
			'pre_http_request',
			function( $pre, $parsed_args ) use ( $payload ) {
				if ( ! empty( $parsed_args['filename'] ) ) {
					file_put_contents( $parsed_args['filename'], $payload );
				}
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => $payload,
				);
			},
			10,
			2
		);

		$download_file = null;
		try {
			$download_file = $updater->verify_package_integrity(
				false,
				'https://example.com/test.zip',
				new stdClass(),
				array( 'plugin' => 'expressionlab/expressionlab.php' )
			);

			$this->assertNotWPError( $download_file );
			$this->assertIsString( $download_file );
			$this->assertFileExists( $download_file );
			$this->assertSame( $payload, file_get_contents( $download_file ) );
		} finally {
			if ( is_string( $download_file ) && file_exists( $download_file ) ) {
				unlink( $download_file );
			}
		}
	}

	public function test_verify_package_integrity_rejects_missing_public_key() {
		$payload = 'dummy zip content';

		$manifest = array(
			'version'   => '1.0.0',
			'sha256'    => hash( 'sha256', $payload ),
			'signature' => str_repeat( 'ab', 64 ),
		);
		set_site_transient( Updater::TRANSIENT_KEY, $manifest, HOUR_IN_SECONDS );

		add_filter(
			'pre_http_request',
			function( $pre, $parsed_args ) use ( $payload ) {
				if ( ! empty( $parsed_args['filename'] ) ) {
					file_put_contents( $parsed_args['filename'], $payload );
				}
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => $payload,
				);
			},
			10,
			2
		);

		$updater = $this->create_updater_with_public_key( '' );

		$result = $updater->verify_package_integrity(
			false,
			'https://example.com/test.zip',
			new stdClass(),
			array( 'plugin' => 'expressionlab/expressionlab.php' )
		);

		$this->assertWPError( $result );
		$this->assertSame( 'expressionlab_missing_public_key', $result->get_error_code() );
	}

	public function test_verify_package_integrity_rejects_malformed_public_key() {
		$payload = 'dummy zip content';

		$manifest = array(
			'version'   => '1.0.0',
			'sha256'    => hash( 'sha256', $payload ),
			'signature' => str_repeat( 'ab', 64 ),
		);
		set_site_transient( Updater::TRANSIENT_KEY, $manifest, HOUR_IN_SECONDS );

		add_filter(
			'pre_http_request',
			function( $pre, $parsed_args ) use ( $payload ) {
				if ( ! empty( $parsed_args['filename'] ) ) {
					file_put_contents( $parsed_args['filename'], $payload );
				}
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => $payload,
				);
			},
			10,
			2
		);

		$updater = $this->create_updater_with_public_key( str_repeat( 'ab', 16 ) );

		$result = $updater->verify_package_integrity(
			false,
			'https://example.com/test.zip',
			new stdClass(),
			array( 'plugin' => 'expressionlab/expressionlab.php' )
		);

		$this->assertWPError( $result );
		$this->assertSame( 'expressionlab_invalid_signature_format', $result->get_error_code() );
	}

	public function test_verify_package_integrity_rejects_missing_checksum() {
		$payload = 'dummy zip content';

		$manifest = array(
			'version'   => '1.0.0',
			'signature' => str_repeat( 'aa', 64 ),
		);
		set_site_transient( Updater::TRANSIENT_KEY, $manifest, HOUR_IN_SECONDS );

		add_filter(
			'pre_http_request',
			function( $pre, $parsed_args ) use ( $payload ) {
				if ( ! empty( $parsed_args['filename'] ) ) {
					file_put_contents( $parsed_args['filename'], $payload );
				}
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => $payload,
				);
			},
			10,
			2
		);

		$result = $this->updater->verify_package_integrity(
			false,
			'https://example.com/test.zip',
			new stdClass(),
			array( 'plugin' => 'expressionlab/expressionlab.php' )
		);

		$this->assertWPError( $result );
		$this->assertSame( 'expressionlab_missing_checksum', $result->get_error_code() );
	}

	public function test_verify_package_integrity_rejects_missing_signature() {
		$payload = 'dummy zip content';

		$manifest = array(
			'version' => '1.0.0',
			'sha256'  => hash( 'sha256', $payload ),
		);
		set_site_transient( Updater::TRANSIENT_KEY, $manifest, HOUR_IN_SECONDS );

		add_filter(
			'pre_http_request',
			function( $pre, $parsed_args ) use ( $payload ) {
				if ( ! empty( $parsed_args['filename'] ) ) {
					file_put_contents( $parsed_args['filename'], $payload );
				}
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => $payload,
				);
			},
			10,
			2
		);

		$result = $this->updater->verify_package_integrity(
			false,
			'https://example.com/test.zip',
			new stdClass(),
			array( 'plugin' => 'expressionlab/expressionlab.php' )
		);

		$this->assertWPError( $result );
		$this->assertSame( 'expressionlab_missing_signature', $result->get_error_code() );
	}

	public function test_verify_package_integrity_rejects_http_url() {
		$result = $this->updater->verify_package_integrity(
			false,
			'http://example.com/insecure.zip',
			new stdClass(),
			array( 'plugin' => 'expressionlab/expressionlab.php' )
		);

		$this->assertWPError( $result );
		$this->assertSame( 'expressionlab_insecure_package_url', $result->get_error_code() );
	}

	public function test_verify_package_integrity_rejects_truncated_signature() {
		$payload = 'dummy zip content';

		$manifest = array(
			'version'   => '1.0.0',
			'sha256'    => hash( 'sha256', $payload ),
			'signature' => str_repeat( 'ab', 32 ),
		);
		set_site_transient( Updater::TRANSIENT_KEY, $manifest, HOUR_IN_SECONDS );

		add_filter(
			'pre_http_request',
			function( $pre, $parsed_args ) use ( $payload ) {
				if ( ! empty( $parsed_args['filename'] ) ) {
					file_put_contents( $parsed_args['filename'], $payload );
				}
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => $payload,
				);
			},
			10,
			2
		);

		$result = $this->updater->verify_package_integrity(
			false,
			'https://example.com/test.zip',
			new stdClass(),
			array( 'plugin' => 'expressionlab/expressionlab.php' )
		);

		$this->assertWPError( $result );
		$this->assertSame( 'expressionlab_invalid_signature_format', $result->get_error_code() );
	}

	public function test_get_manifest_returns_null_on_network_error() {
		add_filter(
			'pre_http_request',
			function() {
				return new WP_Error( 'http_request_failed', 'Connection timed out' );
			}
		);

		$result = $this->updater->get_manifest( true );

		$this->assertNull( $result );
	}

	public function test_get_manifest_returns_null_on_malformed_json() {
		add_filter(
			'pre_http_request',
			function() {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => 'this is not valid json{{{',
				);
			}
		);

		$result = $this->updater->get_manifest( true );

		$this->assertNull( $result );
	}

	public function test_get_manifest_returns_null_on_non_200_response() {
		add_filter(
			'pre_http_request',
			function() {
				return array(
					'response' => array( 'code' => 500 ),
					'body'     => '{"version":"1.0.0"}',
				);
			}
		);

		$result = $this->updater->get_manifest( true );

		$this->assertNull( $result );
	}

	public function test_get_available_update_returns_manifest_when_newer_version_available() {
		$manifest = array(
			'version'      => '99.0.0',
			'download_url' => 'https://example.com/test.zip',
		);
		set_site_transient( Updater::TRANSIENT_KEY, $manifest, HOUR_IN_SECONDS );

		$result = $this->updater->get_available_update();

		$this->assertIsArray( $result );
		$this->assertSame( '99.0.0', $result['version'] );
	}

	public function test_get_available_update_returns_null_when_same_or_older_version() {
		$manifest = array(
			'version'      => EXPRESSION_LAB_VERSION,
			'download_url' => 'https://example.com/test.zip',
		);
		set_site_transient( Updater::TRANSIENT_KEY, $manifest, HOUR_IN_SECONDS );

		$result = $this->updater->get_available_update();

		$this->assertNull( $result );

		// Also test an older version
		$manifest['version'] = '0.0.0';
		set_site_transient( Updater::TRANSIENT_KEY, $manifest, HOUR_IN_SECONDS );

		$result = $this->updater->get_available_update( true );

		$this->assertNull( $result );
	}

	public function test_get_manifest_reads_local_file_in_debug_mode() {
		$temp_file = wp_tempnam( 'updater_manifest_' );
		file_put_contents( $temp_file, json_encode( array( 'version' => '2.5.0' ) ) );

		if ( ! defined( 'EXPRESSION_LAB_CUSTOM_MANIFEST_URL' ) ) {
			define( 'EXPRESSION_LAB_CUSTOM_MANIFEST_URL', $temp_file );
		}

		$body = file_get_contents( $temp_file );
		$this->assertJson( $body );
		$decoded = json_decode( $body, true );
		$this->assertSame( '2.5.0', $decoded['version'] );

		unlink( $temp_file );
	}
}

