<?php

// phpcs:ignoreFile

use ExpressionLab\Core\Services\NetworkOptions;
use ExpressionLab\Core\Services\Options;

class NetworkOptionsTest extends WP_UnitTestCase {

	/**
	 * NetworkOptions instance.
	 *
	 * @var NetworkOptions
	 */
	private $network_options;

	public function setUp(): void {
		parent::setUp();
		$this->network_options = new NetworkOptions();
	}

	public function test_constructor_injection_with_options() {
		$options         = new Options();
		$network_options = new NetworkOptions( $options );
		$this->assertInstanceOf( NetworkOptions::class, $network_options );

		if ( ! is_multisite() ) {
			$key = 'injected_options_test';
			$network_options->set_transient( $key, 'delegated_value' );
			$this->assertSame( 'delegated_value', $options->get_transient( $key ) );
			$network_options->delete_transient( $key );
			$this->assertNull( $options->get_transient( $key ) );
		}
	}


	/**
	 * @group singlesite
	 */
	public function test_get_raw_throws_exception_in_non_multisite() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single site guard test.' );
		}
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'This method is only available in multisite installations.' );
		$this->network_options->get_raw( 'test_option' );
	}

	/**
	 * @group singlesite
	 */
	public function test_get_raw_with_default_throws_exception_in_non_multisite() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single site guard test.' );
		}
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'This method is only available in multisite installations.' );
		$this->network_options->get_raw( 'test_option', 'default' );
	}

	/**
	 * @group singlesite
	 */
	public function test_update_raw_throws_exception_in_non_multisite() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single site guard test.' );
		}
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'This method is only available in multisite installations.' );
		$this->network_options->update_raw( 'test_key', 'test_value' );
	}

	/**
	 * @group singlesite
	 */
	public function test_delete_throws_exception_in_non_multisite() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single site guard test.' );
		}
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'This method is only available in multisite installations.' );
		$this->network_options->delete( 'test_key' );
	}

	/**
	 * @group singlesite
	 */
	public function test_get_throws_exception_in_non_multisite() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single site guard test.' );
		}
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'This method is only available in multisite installations.' );
		$this->network_options->get( 'test_option' );
	}

	/**
	 * @group singlesite
	 */
	public function test_update_throws_exception_in_non_multisite() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single site guard test.' );
		}
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'This method is only available in multisite installations.' );
		$this->network_options->update( 'test_key', 'test_value' );
	}

	public function test_magic_get_returns_null_for_undefined_property() {
		$this->assertNull( $this->network_options->non_existent_property );
	}


	/**
	 * @group multisite
	 */
	public function test_multisite_get_raw_returns_default_if_not_exists() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite test.' );
		}

		$this->assertNull( $this->network_options->get_raw( 'non_existent_net_opt' ) );
		$this->assertEquals( 'fallback', $this->network_options->get_raw( 'non_existent_net_opt', 'fallback' ) );
	}

	/**
	 * @group multisite
	 */
	public function test_multisite_update_raw_and_get_raw() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite test.' );
		}

		$key   = 'expr_test_net_raw';
		$value = 'raw_network_data';

		$this->assertTrue( $this->network_options->update_raw( $key, $value ) );
		$this->assertEquals( $value, $this->network_options->get_raw( $key ) );

		// Same value update should return false
		$this->assertFalse( $this->network_options->update_raw( $key, $value ) );

		// New value update
		$this->assertTrue( $this->network_options->update_raw( $key, 'new_raw_data' ) );
		$this->assertEquals( 'new_raw_data', $this->network_options->get_raw( $key ) );
	}

	/**
	 * @group multisite
	 */
	public function test_multisite_delete() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite test.' );
		}

		$key = 'expr_test_net_delete';
		$this->network_options->update_raw( $key, 'value_to_delete' );

		$this->assertTrue( $this->network_options->delete( $key ) );
		$this->assertNull( $this->network_options->get_raw( $key ) );
	}

	/**
	 * @group multisite
	 */
	public function test_multisite_json_serialization() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite test.' );
		}

		$key  = 'expr_test_net_json';
		$data = array(
			'site_quota' => 1024,
			'features'   => array( 'sso', 'custom_css' ),
		);

		$this->assertTrue( $this->network_options->update( $key, $data, Options::FORMAT_JSON ) );
		$this->assertJsonStringEqualsJsonString( json_encode( $data ), $this->network_options->get_raw( $key ) );
		$this->assertEquals( $data, $this->network_options->get( $key ) );
	}

	/**
	 * @group multisite
	 */
	public function test_multisite_php_serialization() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite test.' );
		}

		$key  = 'expr_test_net_serialized';
		$data = array( 'admin_email' => 'network@example.com' );

		$this->assertTrue( $this->network_options->update( $key, $data, Options::FORMAT_SERIALIZED ) );
		$this->assertEquals( serialize( $data ), $this->network_options->get_raw( $key ) );
		$this->assertEquals( $data, $this->network_options->get( $key ) );
	}

	/**
	 * @group multisite
	 */
	public function test_multisite_php_serialization_object() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite test.' );
		}

		$key  = 'expr_test_net_object';
		$data = array( 'setting' => 'enabled' );

		$this->assertTrue( $this->network_options->update( $key, $data, Options::FORMAT_SERIALIZED_OBJECT ) );
		$retrieved = $this->network_options->get( $key );
		$this->assertInstanceOf( stdClass::class, $retrieved );
		$this->assertEquals( 'enabled', $retrieved->setting );
	}

	/**
	 * @group multisite
	 */
	public function test_multisite_format_conflict_protections() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite test.' );
		}

		$key = 'expr_test_net_conflict';
		$this->network_options->update( $key, array( 'a' => 1 ), Options::FORMAT_SERIALIZED );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Cannot update with JSON serialization because the existing value is serialized' );

		$this->network_options->update( $key, array( 'b' => 2 ), Options::FORMAT_JSON );
	}

	/**
	 * @group singlesite
	 */
	public function test_transient_fallback_to_options_in_non_multisite() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single site fallback test.' );
		}

		$options = new Options();
		$key     = 'expr_net_single_fallback';
		$value   = array(
			'manifest_version' => '1.0.0',
			'update_available' => true,
		);

		$this->assertTrue( $this->network_options->set_transient( $key, $value, 3600 ) );

		$raw_data = $options->get_raw( NetworkOptions::TRANSIENT_PREFIX . $key );
		$this->assertNotNull( $raw_data );
		$this->assertEquals( serialize( $value ), $raw_data );

		$raw_timeout = $options->get_raw( NetworkOptions::TRANSIENT_TIMEOUT_PREFIX . $key );
		$this->assertNotNull( $raw_timeout );
		$this->assertGreaterThan( time(), (int) $raw_timeout );

		$this->assertEquals( $value, $this->network_options->get_transient( $key ) );

		$timeout = $this->network_options->get_transient_timeout( $key );
		$this->assertEquals( (int) $raw_timeout, $timeout );
		$ttl = $this->network_options->get_transient_ttl( $key );
		$this->assertGreaterThan( 3500, $ttl );

		$raw_key   = 'expr_net_single_raw';
		$raw_value = 'raw_manifest_json_payload';
		$this->assertTrue( $this->network_options->set_transient_raw( $raw_key, $raw_value, 1800 ) );
		$this->assertEquals( $raw_value, $this->network_options->get_transient_raw( $raw_key ) );
		$this->assertTrue( $this->network_options->delete_transient( $key ) );
		$this->assertNull( $options->get_raw( NetworkOptions::TRANSIENT_PREFIX . $key ) );
		$this->assertNull( $options->get_raw( NetworkOptions::TRANSIENT_TIMEOUT_PREFIX . $key ) );
		$this->assertNull( $this->network_options->get_transient( $key ) );
	}

	/**
	 * @group multisite
	 */
	public function test_multisite_set_and_get_transient() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite test.' );
		}

		$key   = 'expr_net_transient_basic';
		$value = array( 'global_setting' => 'active', 'quota' => 5000 );

		$this->assertTrue( $this->network_options->set_transient( $key, $value, 3600 ) );

		$raw_data = $this->network_options->get_raw( NetworkOptions::TRANSIENT_PREFIX . $key );
		$this->assertNotNull( $raw_data );
		$this->assertEquals( serialize( $value ), $raw_data );
		$raw_timeout = $this->network_options->get_raw( NetworkOptions::TRANSIENT_TIMEOUT_PREFIX . $key );
		$this->assertNotNull( $raw_timeout );
		$this->assertGreaterThan( time(), (int) $raw_timeout );

		$this->assertEquals( $value, $this->network_options->get_transient( $key ) );

		$json_key  = 'expr_net_transient_json';
		$json_data = array( 'admins' => array( 'alice', 'bob' ) );
		$this->assertTrue( $this->network_options->set_transient( $json_key, $json_data, 1800, Options::FORMAT_JSON ) );
		$this->assertEquals( $json_data, $this->network_options->get_transient( $json_key ) );
		$this->assertJsonStringEqualsJsonString( json_encode( $json_data ), $this->network_options->get_transient_raw( $json_key ) );
	}

	/**
	 * @group multisite
	 */
	public function test_multisite_transient_expiration() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite test.' );
		}

		$key   = 'expr_net_expired_transient';
		$value = 'will_expire_soon';

		$this->network_options->set_transient( $key, $value, 3600 );

		$past_time = time() - 60;
		$this->network_options->update_raw( NetworkOptions::TRANSIENT_TIMEOUT_PREFIX . $key, (string) $past_time );

		$this->assertNull( $this->network_options->get_transient( $key ) );
		$this->assertEquals( 'default_val', $this->network_options->get_transient( $key, 'default_val' ) );

		$this->assertNotNull( $this->network_options->get_raw( NetworkOptions::TRANSIENT_PREFIX . $key ) );
		$this->assertNotNull( $this->network_options->get_raw( NetworkOptions::TRANSIENT_TIMEOUT_PREFIX . $key ) );

		$this->assertTrue( $this->network_options->delete_transient( $key ) );
		$this->assertNull( $this->network_options->get_raw( NetworkOptions::TRANSIENT_PREFIX . $key ) );
		$this->assertNull( $this->network_options->get_raw( NetworkOptions::TRANSIENT_TIMEOUT_PREFIX . $key ) );
	}

	/**
	 * @group multisite
	 */
	public function test_multisite_get_transient_raw() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite test.' );
		}

		$key   = 'expr_net_raw_transient';
		$value = 'raw_sitewide_payload';

		$this->network_options->set_transient_raw( $key, $value, 3600 );
		$this->assertEquals( $value, $this->network_options->get_transient_raw( $key ) );

		$this->network_options->update_raw( NetworkOptions::TRANSIENT_TIMEOUT_PREFIX . $key, (string) ( time() - 30 ) );

		$this->assertNull( $this->network_options->get_transient_raw( $key ) );
		$this->assertNotNull( $this->network_options->get_raw( NetworkOptions::TRANSIENT_PREFIX . $key ) );
		$this->assertEquals( $value, $this->network_options->get_transient_raw( $key, null, false ) );
	}

	/**
	 * @group multisite
	 */
	public function test_multisite_delete_transient() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite test.' );
		}

		$key = 'expr_net_delete_transient';
		$this->network_options->set_transient( $key, 'sample', 600 );

		$this->assertNotNull( $this->network_options->get_raw( NetworkOptions::TRANSIENT_PREFIX . $key ) );
		$this->assertNotNull( $this->network_options->get_raw( NetworkOptions::TRANSIENT_TIMEOUT_PREFIX . $key ) );

		$this->assertTrue( $this->network_options->delete_transient( $key ) );

		$this->assertNull( $this->network_options->get_raw( NetworkOptions::TRANSIENT_PREFIX . $key ) );
		$this->assertNull( $this->network_options->get_raw( NetworkOptions::TRANSIENT_TIMEOUT_PREFIX . $key ) );

		$this->assertFalse( $this->network_options->delete_transient( $key ) );
	}

	/**
	 * @group multisite
	 */
	public function test_multisite_transient_timeout_and_ttl() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite test.' );
		}

		$key = 'expr_net_ttl_transient';

		$this->assertNull( $this->network_options->get_transient_timeout( $key ) );
		$this->assertNull( $this->network_options->get_transient_ttl( $key ) );

		$this->network_options->set_transient( $key, 'persistent', 0 );
		$this->assertNull( $this->network_options->get_transient_timeout( $key ) );
		$this->assertSame( 0, $this->network_options->get_transient_ttl( $key ) );

		$ttl = 2400;
		$this->network_options->set_transient( $key, 'expiring', $ttl );
		$timeout = $this->network_options->get_transient_timeout( $key );
		$this->assertNotNull( $timeout );
		$this->assertEqualsWithDelta( time() + $ttl, $timeout, 2 );

		$current_ttl = $this->network_options->get_transient_ttl( $key );
		$this->assertNotNull( $current_ttl );
		$this->assertGreaterThan( 2390, $current_ttl );
		$this->assertLessThanOrEqual( $ttl, $current_ttl );
	}

	/**
	 * @group multisite
	 */
	public function test_multisite_transient_key_validation() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite test.' );
		}

		$this->expectException( InvalidArgumentException::class );
		$this->network_options->get_transient( '' );
	}

	/**
	 * @group multisite
	 */
	public function test_multisite_transient_key_length_validation() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite test.' );
		}

		$too_long_key = str_repeat( 'b', NetworkOptions::MAX_TRANSIENT_KEY_LENGTH + 1 );
		$this->expectException( InvalidArgumentException::class );
		$this->network_options->set_transient( $too_long_key, 'value' );
	}


	/**
	 * Counts physical rows for an exact name in the table backing network transients
	 * (`wp_sitemeta` on multisite, `wp_options` on single-site).
	 *
	 * @param string $name Exact row name.
	 * @return int
	 */
	private function count_transient_rows( string $name ): int {
		global $wpdb;

		if ( is_multisite() ) {
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->sitemeta} WHERE meta_key = %s AND site_id = %d", $name, get_current_network_id() ) );
		}

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s", $name ) );
	}

	/**
	 * Overwrites a raw transient row in the environment-appropriate table.
	 *
	 * @param string $name  Exact row name.
	 * @param string $value Raw value.
	 */
	private function write_transient_row( string $name, string $value ): void {
		if ( is_multisite() ) {
			$this->network_options->update_raw( $name, $value );
		} else {
			( new Options() )->update_raw( $name, $value, 'no' );
		}
	}

	public function test_transient_key_validation_rejects_without_touching_database() {
		global $wpdb;

		$too_long       = str_repeat( 'n', NetworkOptions::MAX_TRANSIENT_KEY_LENGTH + 1 );
		$queries_before = $wpdb->num_queries;

		try {
			$this->network_options->set_transient( $too_long, 'val', 60 );
			$this->fail( 'Expected InvalidArgumentException for oversized key.' );
		} catch ( InvalidArgumentException $e ) {
			$this->assertStringContainsString( '167', $e->getMessage() );
		}

		$this->assertSame( $queries_before, $wpdb->num_queries, 'Database was queried before key validation.' );
		$this->assertSame( 0, $this->count_transient_rows( NetworkOptions::TRANSIENT_PREFIX . $too_long ) );
	}

	public function test_transient_key_at_exact_boundary_is_stored_without_truncation() {
		$key = str_repeat( 'n', NetworkOptions::MAX_TRANSIENT_KEY_LENGTH ); // 167.

		$this->assertTrue( $this->network_options->set_transient( $key, array( 'boundary' => true ), 600 ) );
		$this->assertSame( 191, strlen( NetworkOptions::TRANSIENT_TIMEOUT_PREFIX . $key ) );
		$this->assertSame( 1, $this->count_transient_rows( NetworkOptions::TRANSIENT_PREFIX . $key ) );
		$this->assertSame( 1, $this->count_transient_rows( NetworkOptions::TRANSIENT_TIMEOUT_PREFIX . $key ) );
		$this->assertSame( array( 'boundary' => true ), $this->network_options->get_transient( $key ) );
		$this->assertTrue( $this->network_options->delete_transient( $key ) );
		$this->assertSame( 0, $this->count_transient_rows( NetworkOptions::TRANSIENT_TIMEOUT_PREFIX . $key ) );
	}

	public function test_transient_keys_with_special_and_unicode_characters_round_trip() {
		$keys = array( "quote'\"back\\slash", 'percent%and_underscore', 'ñandú_日本語_ключ' );

		foreach ( $keys as $index => $key ) {
			$this->assertTrue( $this->network_options->set_transient( $key, "value_{$index}", 300 ) );
			$this->assertSame( "value_{$index}", $this->network_options->get_transient( $key ) );
		}

		foreach ( $keys as $key ) {
			$this->assertTrue( $this->network_options->delete_transient( $key ) );
		}
	}

	public function test_transient_rejects_negative_expiration() {
		foreach ( array( 'set_transient', 'set_transient_raw' ) as $method ) {
			try {
				$this->network_options->$method( 'expr_net_negative', 'value', -10 );
				$this->fail( "NetworkOptions::{$method}() accepted a negative expiration." );
			} catch ( InvalidArgumentException $e ) {
				$this->assertSame( 0, $this->count_transient_rows( NetworkOptions::TRANSIENT_PREFIX . 'expr_net_negative' ) );
			}
		}
	}

	public function test_transient_ttl_contract_and_orphaned_timeouts() {
		$key = 'expr_net_ttl_contract';

		// Orphaned timeout (no data row): the transient does not exist.
		$this->write_transient_row( NetworkOptions::TRANSIENT_TIMEOUT_PREFIX . $key, (string) ( time() + 900 ) );
		$this->assertNull( $this->network_options->get_transient_timeout( $key ) );
		$this->assertNull( $this->network_options->get_transient_ttl( $key ) );
		$this->assertNull( $this->network_options->get_transient_raw( $key, null, false ) );

		// Expired: timeout preserved, TTL 0.
		$this->network_options->set_transient( $key, 'value', 600 );
		$past = time() - 50;
		$this->write_transient_row( NetworkOptions::TRANSIENT_TIMEOUT_PREFIX . $key, (string) $past );
		$this->assertSame( $past, $this->network_options->get_transient_timeout( $key ) );
		$this->assertSame( 0, $this->network_options->get_transient_ttl( $key ) );

		// Persistent: timeout purged, TTL 0.
		$this->network_options->set_transient( $key, 'value', 0 );
		$this->assertSame( 0, $this->count_transient_rows( NetworkOptions::TRANSIENT_TIMEOUT_PREFIX . $key ) );
		$this->assertNull( $this->network_options->get_transient_timeout( $key ) );
		$this->assertSame( 0, $this->network_options->get_transient_ttl( $key ) );

		// Corrupt (non-numeric) timeout behaves as persistent, mirroring WordPress core.
		$this->write_transient_row( NetworkOptions::TRANSIENT_TIMEOUT_PREFIX . $key, 'garbage' );
		$this->assertSame( 'value', $this->network_options->get_transient( $key ) );
		$this->assertSame( 0, $this->network_options->get_transient_ttl( $key ) );
	}

	public function test_reading_expired_transient_is_non_destructive() {
		$key = 'expr_net_non_destructive';
		$this->network_options->set_transient( $key, array( 'evidence' => 1 ), 600 );
		$this->write_transient_row( NetworkOptions::TRANSIENT_TIMEOUT_PREFIX . $key, (string) ( time() - 3600 ) );

		$this->assertSame( 'dflt', $this->network_options->get_transient( $key, 'dflt' ) );
		$this->assertSame( 'dflt', $this->network_options->get_transient_raw( $key, 'dflt', true ) );
		$this->assertSame( serialize( array( 'evidence' => 1 ) ), $this->network_options->get_transient_raw( $key, 'dflt', false ) );
		$this->network_options->get_transient_timeout( $key );
		$this->network_options->get_transient_ttl( $key );

		$this->assertSame( 1, $this->count_transient_rows( NetworkOptions::TRANSIENT_PREFIX . $key ), 'Expired data row was deleted on read.' );
		$this->assertSame( 1, $this->count_transient_rows( NetworkOptions::TRANSIENT_TIMEOUT_PREFIX . $key ), 'Expired timeout row was deleted on read.' );
	}

	public function test_get_transient_refuses_object_injection_payload_while_raw_read_is_safe() {
		$key     = 'expr_net_poi_transient';
		$payload = 'a:1:{i:0;O:8:"Requests":1:{s:4:"hook";s:6:"system";}}';
		$this->network_options->set_transient_raw( $key, $payload, 600 );

		$this->assertSame( $payload, $this->network_options->get_transient_raw( $key ) );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'NetworkOptions.get_transient_raw()' );
		$this->network_options->get_transient( $key );
	}

	/**
	 * @group singlesite
	 */
	public function test_single_site_delegates_to_injected_options_instance() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single site delegation test.' );
		}

		$options         = new Options();
		$network_options = new NetworkOptions( $options );
		$key             = 'expr_net_di_delegation';

		$this->assertTrue( $network_options->set_transient( $key, 'delegated_value', 600 ) );
		$this->assertSame( 'delegated_value', $options->get_raw( Options::SITE_TRANSIENT_PREFIX . $key ) );
		$this->assertSame( 'delegated_value', $network_options->get_transient( $key ) );

		$this->assertTrue( $network_options->delete_transient( $key ) );
		$this->assertNull( $options->get_raw( Options::SITE_TRANSIENT_PREFIX . $key ) );
	}

	/**
	 * @group singlesite
	 */
	public function test_single_site_expiring_transient_is_never_autoloaded() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single site test.' );
		}

		global $wpdb;
		$key      = 'expr_net_autoload';
		$data_key = NetworkOptions::TRANSIENT_PREFIX . $key;
		$autoload = function ( $name ) use ( $wpdb ) {
			return $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $name ) );
		};

		$this->network_options->set_transient( $key, 'same', 0 );
		$this->assertContains( $autoload( $data_key ), wp_autoload_values_to_autoload() );

		// Same value, now expiring: autoload must be disabled despite update_option() short-circuiting.
		$this->network_options->set_transient( $key, 'same', 600 );
		$this->assertNotContains( $autoload( $data_key ), wp_autoload_values_to_autoload() );
		$this->assertNotContains( $autoload( NetworkOptions::TRANSIENT_TIMEOUT_PREFIX . $key ), wp_autoload_values_to_autoload() );

		$this->network_options->set_transient_raw( $key . '_raw', 'v', 600 );
		$this->assertSame( 'no', $autoload( NetworkOptions::TRANSIENT_PREFIX . $key . '_raw' ) );
		$this->assertSame( 'no', $autoload( NetworkOptions::TRANSIENT_TIMEOUT_PREFIX . $key . '_raw' ) );
	}

	/**
	 * @group singlesite
	 */
	public function test_single_site_transients_interoperate_with_wordpress_core() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single site test.' );
		}

		$key = 'expr_net_core_interop';

		// Prime `notoptions` through core, then write through Expression Lab.
		$this->assertFalse( get_site_transient( $key ) );
		$this->network_options->set_transient_raw( $key, 'from_el', 300 );
		$this->assertSame( 'from_el', get_site_transient( $key ) );

		// Core-written site transients are readable through NetworkOptions.
		set_site_transient( $key, array( 'from' => 'core' ), 300 );
		$this->assertSame( array( 'from' => 'core' ), $this->network_options->get_transient( $key ) );

		$this->network_options->delete_transient( $key );
		$this->assertFalse( get_site_transient( $key ) );
	}

	/**
	 * @group multisite
	 */
	public function test_multisite_transients_write_exclusively_to_sitemeta() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite test.' );
		}

		global $wpdb;
		$key = 'expr_net_isolation';

		$this->network_options->set_transient( $key, 'net_value', 600 );
		$this->network_options->set_transient_raw( $key . '_raw', 'net_raw', 600 );

		$this->assertSame( 1, $this->count_transient_rows( NetworkOptions::TRANSIENT_PREFIX . $key ) );
		$this->assertSame( 1, $this->count_transient_rows( NetworkOptions::TRANSIENT_TIMEOUT_PREFIX . $key ) );

		$polluted = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", '%' . $wpdb->esc_like( $key ) . '%' ) );
		$this->assertSame( 0, $polluted, 'Network transients leaked into wp_options.' );

		// Core sees the same data (cache coherency in the `site-options` group).
		$this->assertSame( 'net_value', get_site_transient( $key ) );
		$this->network_options->set_transient_raw( $key . '_raw', 'net_raw_updated', 600 );
		$this->assertSame( 'net_raw_updated', get_site_transient( $key . '_raw' ) );

		$this->network_options->delete_transient( $key );
		$this->assertFalse( get_site_transient( $key ) );
	}

	/**
	 * @group multisite
	 */
	public function test_multisite_transients_ignore_blog_switching() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite test.' );
		}

		$key     = 'expr_net_switch';
		$blog_id = self::factory()->blog->create();

		$this->network_options->set_transient( $key, 'network_wide', 600 );

		switch_to_blog( $blog_id );
		$this->assertSame( 'network_wide', $this->network_options->get_transient( $key ) );
		restore_current_blog();

		$this->assertTrue( $this->network_options->delete_transient( $key ) );
	}
}