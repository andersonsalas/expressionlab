<?php

// phpcs:ignoreFile

use ExpressionLab\Core\Services\Options;

/**
 * Tests for the Options class.
 *
 * @group core
 * @group library
 */
class OptionsTest extends WP_UnitTestCase {

	/**
	 * Options instance.
	 *
	 * @var Options
	 */
	private $options;

	public function setUp(): void {
		parent::setUp();
		$this->options = new Options();
	}

	public function test_implements_service_interface() {
		$this->assertInstanceOf( \ExpressionLab\Core\Interfaces\ServiceInterface::class, $this->options );
	}

	public function test_get_raw_returns_default_if_not_exists() {
		$this->assertNull( $this->options->get_raw( 'non_existent_option' ) );
		$this->assertEquals( 'default', $this->options->get_raw( 'non_existent_option', 'default' ) );
	}

	public function test_update_raw_and_get_raw() {
		$key   = 'expressionlab_test_raw';
		$value = 'some_raw_value';

		$this->assertTrue( $this->options->update_raw( $key, $value ) );
		$this->assertEquals( $value, $this->options->get_raw( $key ) );

		// Update with same value should return false
		$this->assertFalse( $this->options->update_raw( $key, $value ) );

		// Update with new value
		$this->assertTrue( $this->options->update_raw( $key, 'new_value' ) );
		$this->assertEquals( 'new_value', $this->options->get_raw( $key ) );
	}

	public function test_delete() {
		$key = 'expressionlab_to_delete';
		$this->options->update_raw( $key, 'value' );

		$this->assertTrue( $this->options->delete( $key ) );
		$this->assertNull( $this->options->get_raw( $key ) );
	}

	public function test_json_serialization() {
		$key  = 'expressionlab_test_json';
		$data = array(
			'foo'    => 'bar',
			'nested' => array( 1, 2, 3 ),
		);

		$this->assertTrue( $this->options->update( $key, $data, Options::FORMAT_JSON ) );

		// Raw should be JSON string
		$this->assertJsonStringEqualsJsonString( json_encode( $data ), $this->options->get_raw( $key ) );

		// Get should return array
		$this->assertEquals( $data, $this->options->get( $key ) );
	}

	public function test_php_serialization() {
		$key  = 'expressionlab_test_serialized';
		$data = array( 'foo' => 'bar' );

		$this->assertTrue( $this->options->update( $key, $data, Options::FORMAT_SERIALIZED ) );

		// Raw should be serialized string
		$this->assertEquals( serialize( $data ), $this->options->get_raw( $key ) );

		// Get should return array
		$this->assertEquals( $data, $this->options->get( $key ) );
	}

	public function test_php_serialization_object() {
		$key  = 'expressionlab_test_object';
		$data = array( 'a' => 1 );

		// It converts array to object (stdClass)
		$this->assertTrue( $this->options->update( $key, $data, Options::FORMAT_SERIALIZED_OBJECT ) );

		$retrieved = $this->options->get( $key );
		$this->assertInstanceOf( stdClass::class, $retrieved );
		$this->assertEquals( 1, $retrieved->a );
	}

	/**
	 * Security Test: Prevent overwriting serialized data with JSON directly.
	 */
	public function test_cannot_update_json_over_serialized() {
		$key = 'expressionlab_conflict_test';
		$this->options->update( $key, array( 'a' => 1 ), Options::FORMAT_SERIALIZED );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Cannot update with JSON serialization because the existing value is serialized' );

		$this->options->update( $key, array( 'b' => 2 ), Options::FORMAT_JSON );
	}

	/**
	 * Security Test: Prevent overwriting JSON data with Serialized directly.
	 */
	public function test_cannot_update_serialized_over_json() {
		$key = 'expressionlab_conflict_test_2';
		$this->options->update( $key, array( 'a' => 1 ), Options::FORMAT_JSON );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Cannot update with PHP serialization because the existing value is JSON' );

		$this->options->update( $key, array( 'b' => 2 ), Options::FORMAT_SERIALIZED );
	}

	/**
	 * Security Test: PHP Object Injection Protection.
	 *
	 * We simulate a scenario where a malicious serialized string containing a non-stdClass object
	 * is somehow inserted into the database (e.g. via direct DB access or vulnerable other plugins).
	 *
	 * options->get() must refuse to unserialize it.
	 */
	public function test_security_prevents_getting_unsafe_objects() {
		$key = 'expressionlab_unsafe_object';

		$exploit_payload = 'O:14:"MaliciousClass":1:{s:3:"cmd";s:10:"rm -rf /";}';

		// Insert raw directly to bypass update() checks
		$this->options->update_raw( $key, $exploit_payload );

		// Expect exception when trying to process it
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Unsafe or invalid serialized data' );

		$this->options->get( $key );
	}

	/**
	 * Security Test: Prevent array with nested unsafe objects.
	 */
	public function test_security_prevents_nested_unsafe_objects() {
		$key = 'expressionlab_nested_unsafe';

		// Serialized array containing an object
		$exploit_payload = 'a:1:{i:0;O:14:"MaliciousClass":0:{}}';

		$this->options->update_raw( $key, $exploit_payload );

		$this->expectException( Exception::class );

		// Since unserialize with allowed_classes = [stdClass] converts 'MaliciousClass'
		// to __PHP_Incomplete_Class, strict_unserialize will detect it and throw exception.
		$this->options->get( $key );
	}

	public function test_smart_prefix_logic_numeric() {
		$key = 'wp_123_something';
		// Make it large enough to appear in top 50, assuming standard install isn't huge.
		$value = str_repeat( 'x', 1024 * 10 ); // 10KB
		$this->options->update_raw( $key, $value, 'no' );

		// Use a large sample limit to cover everything and request more results
		$result = $this->options->stats( 100000, 100 );

		$found = false;
		if ( isset( $result['prefixes'] ) && is_array( $result['prefixes'] ) ) {
			foreach ( $result['prefixes'] as $row ) {
				if ( $row['prefix'] === 'wp_123' ) {
					$found = true;
					break;
				}
			}
		}

		$this->assertTrue( $found, 'Smart prefix logic for short prefixes failed. Prefix "wp_123" not found in distribution stats.' );
	}

	/**
	 * Test that update_raw normalizes boolean autoload to 'yes'/'no'.
	 */
	public function test_update_raw_autoload_boolean_normalization() {
		global $wpdb;

		$key1 = 'expr_test_autoload_bool_true';
		$this->options->update_raw( $key1, 'val1', true );

		$autoload1 = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $key1 ) );
		$this->assertEquals( 'yes', $autoload1 );

		$key2 = 'expr_test_autoload_bool_false';
		$this->options->update_raw( $key2, 'val2', false );

		$autoload2 = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $key2 ) );
		$this->assertEquals( 'no', $autoload2 );
	}

	/**
	 * Test that update_raw updates autoload column when option already exists.
	 */
	public function test_update_raw_updates_autoload_on_existing_option() {
		global $wpdb;

		$key = 'expr_test_autoload_switch';
		$this->options->update_raw( $key, 'initial_val', 'no' );

		$autoload_before = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $key ) );
		$this->assertEquals( 'no', $autoload_before );

		// Update existing option with new value and new autoload
		$this->options->update_raw( $key, 'updated_val', 'yes' );

		$autoload_after = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $key ) );
		$this->assertEquals( 'yes', $autoload_after );
		$this->assertEquals( 'updated_val', $this->options->get_raw( $key ) );
	}

	/**
	 * Test set_transient and get_transient basic lifecycle with scalar and complex data.
	 */
	public function test_set_and_get_transient() {
		$key   = 'expr_test_transient_basic';
		$value = array( 'foo' => 'bar', 'count' => 42 );

		$this->assertTrue( $this->options->set_transient( $key, $value, 3600 ) );

		$raw_data = $this->options->get_raw( Options::TRANSIENT_PREFIX . $key );
		$this->assertNotNull( $raw_data );
		$this->assertEquals( serialize( $value ), $raw_data );

		$raw_timeout = $this->options->get_raw( Options::TRANSIENT_TIMEOUT_PREFIX . $key );
		$this->assertNotNull( $raw_timeout );
		$this->assertGreaterThan( time(), (int) $raw_timeout );

		$this->assertEquals( $value, $this->options->get_transient( $key ) );

		$json_key  = 'expr_test_transient_json';
		$json_data = array( 'nested' => array( 1, 2, 3 ) );
		$this->assertTrue( $this->options->set_transient( $json_key, $json_data, 1800, Options::FORMAT_JSON ) );
		$this->assertEquals( $json_data, $this->options->get_transient( $json_key ) );
		$this->assertJsonStringEqualsJsonString( json_encode( $json_data ), $this->options->get_transient_raw( $json_key ) );
	}

	/**
	 * Test transient expiration returns default.
	 */
	public function test_transient_expiration() {
		$key   = 'expr_test_expired_transient';
		$value = 'will_expire';

		$this->options->set_transient( $key, $value, 3600 );

		$past_time = time() - 60;
		$this->options->update_raw( Options::TRANSIENT_TIMEOUT_PREFIX . $key, (string) $past_time );

		$this->assertNull( $this->options->get_transient( $key ) );
		$this->assertEquals( 'fallback', $this->options->get_transient( $key, 'fallback' ) );

		$this->assertNotNull( $this->options->get_raw( Options::TRANSIENT_PREFIX . $key ) );
		$this->assertNotNull( $this->options->get_raw( Options::TRANSIENT_TIMEOUT_PREFIX . $key ) );

		$this->assertTrue( $this->options->delete_transient( $key ) );
		$this->assertNull( $this->options->get_raw( Options::TRANSIENT_PREFIX . $key ) );
		$this->assertNull( $this->options->get_raw( Options::TRANSIENT_TIMEOUT_PREFIX . $key ) );
	}

	/**
	 * Test get_transient_raw behavior with and without expiration checks.
	 */
	public function test_get_transient_raw() {
		$key   = 'expr_test_raw_transient';
		$value = 'raw_string_data';

		$this->options->set_transient_raw( $key, $value, 3600 );

		$this->assertEquals( $value, $this->options->get_transient_raw( $key ) );

		$this->options->update_raw( Options::TRANSIENT_TIMEOUT_PREFIX . $key, (string) ( time() - 30 ) );

		$this->assertNull( $this->options->get_transient_raw( $key ) );
		$this->assertNotNull( $this->options->get_raw( Options::TRANSIENT_PREFIX . $key ) );
		$this->assertEquals( $value, $this->options->get_transient_raw( $key, null, false ) );
	}

	/**
	 * Test delete_transient purges both data and timeout.
	 */
	public function test_delete_transient() {
		$key = 'expr_test_to_delete_transient';
		$this->options->set_transient( $key, 'sample', 600 );

		$this->assertNotNull( $this->options->get_raw( Options::TRANSIENT_PREFIX . $key ) );
		$this->assertNotNull( $this->options->get_raw( Options::TRANSIENT_TIMEOUT_PREFIX . $key ) );

		$this->assertTrue( $this->options->delete_transient( $key ) );

		$this->assertNull( $this->options->get_raw( Options::TRANSIENT_PREFIX . $key ) );
		$this->assertNull( $this->options->get_raw( Options::TRANSIENT_TIMEOUT_PREFIX . $key ) );

		$this->assertFalse( $this->options->delete_transient( $key ) );
	}

	/**
	 * Test get_transient_timeout and get_transient_ttl.
	 */
	public function test_transient_timeout_and_ttl() {
		$key = 'expr_test_ttl_transient';

		// Non-existent returns null
		$this->assertNull( $this->options->get_transient_timeout( $key ) );
		$this->assertNull( $this->options->get_transient_ttl( $key ) );

		// Persistent transient (expiration = 0)
		$this->options->set_transient( $key, 'persistent', 0 );
		$this->assertNull( $this->options->get_transient_timeout( $key ) );
		$this->assertSame( 0, $this->options->get_transient_ttl( $key ) );

		// Transient with expiration
		$ttl = 1200;
		$this->options->set_transient( $key, 'expiring', $ttl );
		$timeout = $this->options->get_transient_timeout( $key );
		$this->assertNotNull( $timeout );
		$this->assertEqualsWithDelta( time() + $ttl, $timeout, 2 );

		$current_ttl = $this->options->get_transient_ttl( $key );
		$this->assertNotNull( $current_ttl );
		$this->assertGreaterThan( 1190, $current_ttl );
		$this->assertLessThanOrEqual( $ttl, $current_ttl );
	}

	/**
	 * Test autoload behavior for transients with and without expiration.
	 */
	public function test_transient_autoload_behavior() {
		global $wpdb;

		$expiring_key   = 'expr_test_autoload_expiring';
		$persistent_key = 'expr_test_autoload_persist';

		$this->options->set_transient( $expiring_key, 'val', 3600 );
		$this->options->set_transient( $persistent_key, 'val', 0 );

		// For expiring transients, both data and timeout must have autoload disabled ('no' or 'off')
		$exp_autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", Options::TRANSIENT_PREFIX . $expiring_key ) );
		$timeout_autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", Options::TRANSIENT_TIMEOUT_PREFIX . $expiring_key ) );

		$this->assertContains( $exp_autoload, array( 'no', 'off' ) );
		$this->assertContains( $timeout_autoload, array( 'no', 'off' ) );

		// Setting an expiring transient to persistent deletes its timeout option
		$this->options->set_transient( $expiring_key, 'val_persistent', 0 );
		$this->assertNull( $this->options->get_raw( Options::TRANSIENT_TIMEOUT_PREFIX . $expiring_key ) );
	}

	/**
	 * Test transient key validation (empty or exceeding MAX_TRANSIENT_KEY_LENGTH).
	 */
	public function test_transient_key_validation() {
		$this->expectException( InvalidArgumentException::class );
		$this->options->get_transient( '' );
	}

	public function test_transient_key_length_validation() {
		$too_long_key = str_repeat( 'a', Options::MAX_TRANSIENT_KEY_LENGTH + 1 );
		$this->expectException( InvalidArgumentException::class );
		$this->options->set_transient( $too_long_key, 'value' );
	}

	/**
	 * @group singlesite
	 */
	public function test_transient_single_site_fallback_to_site_transient() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single site fallback test.' );
		}

		$key   = 'expr_updater_manifest_fallback';
		$value = array(
			'package' => 'https://example.com/update.zip',
			'version' => '2.0.0',
		);

		set_site_transient( $key, $value, 3600 );

		$this->assertEquals( $value, $this->options->get_transient( $key ) );
		$this->assertEquals( serialize( $value ), $this->options->get_transient_raw( $key ) );

		$timeout = $this->options->get_transient_timeout( $key );
		$this->assertNotNull( $timeout );
		$this->assertGreaterThan( time(), $timeout );
		$this->assertGreaterThan( 3500, $this->options->get_transient_ttl( $key ) );

		$standard_val = array( 'precedence' => 'standard' );
		$this->options->set_transient( $key, $standard_val, 7200 );
		$this->assertEquals( $standard_val, $this->options->get_transient( $key ) );
		$this->assertTrue( $this->options->delete_transient( $key ) );
		$this->assertNull( $this->options->get_transient( $key ) );
		$this->assertNull( $this->options->get_raw( Options::TRANSIENT_PREFIX . $key ) );
		$this->assertNull( $this->options->get_raw( Options::SITE_TRANSIENT_PREFIX . $key ) );
		$this->assertNull( $this->options->get_raw( Options::TRANSIENT_TIMEOUT_PREFIX . $key ) );
		$this->assertNull( $this->options->get_raw( Options::SITE_TRANSIENT_TIMEOUT_PREFIX . $key ) );
	}

	/**
	 * Counts the physical rows stored under an exact option name in the current blog's options table.
	 *
	 * @param string $name Exact option name.
	 * @return int
	 */
	private function count_option_rows( string $name ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s", $name ) );
	}

	/**
	 * Reads the autoload flag of an option directly from the database.
	 *
	 * @param string $name Exact option name.
	 * @return string|null
	 */
	private function get_autoload_flag( string $name ): ?string {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $name ) );
	}

	public function test_transient_key_validation_rejects_without_touching_database() {
		global $wpdb;

		$too_long       = str_repeat( 'k', Options::MAX_TRANSIENT_KEY_LENGTH + 1 );
		$queries_before = $wpdb->num_queries;

		try {
			$this->options->set_transient( $too_long, 'val', 60 );
			$this->fail( 'Expected InvalidArgumentException for oversized key.' );
		} catch ( InvalidArgumentException $e ) {
			$this->assertStringContainsString( '172', $e->getMessage() );
		}

		$this->assertSame( $queries_before, $wpdb->num_queries, 'Database was queried before key validation.' );
		$this->assertSame( 0, $this->count_option_rows( Options::TRANSIENT_PREFIX . $too_long ) );
	}

	public function test_transient_key_at_exact_boundary_is_stored_without_truncation() {
		$key = str_repeat( 'k', Options::MAX_TRANSIENT_KEY_LENGTH ); // 172.

		$this->assertTrue( $this->options->set_transient( $key, array( 'boundary' => true ), 600 ) );

		// The timeout row name is exactly 191 characters and must be stored verbatim.
		$this->assertSame( 191, strlen( Options::TRANSIENT_TIMEOUT_PREFIX . $key ) );
		$this->assertSame( 1, $this->count_option_rows( Options::TRANSIENT_PREFIX . $key ) );
		$this->assertSame( 1, $this->count_option_rows( Options::TRANSIENT_TIMEOUT_PREFIX . $key ) );

		$this->assertSame( array( 'boundary' => true ), $this->options->get_transient( $key ) );
		$this->assertGreaterThan( 590, $this->options->get_transient_ttl( $key ) );
		$this->assertTrue( $this->options->delete_transient( $key ) );
		$this->assertSame( 0, $this->count_option_rows( Options::TRANSIENT_TIMEOUT_PREFIX . $key ) );
	}

	public function test_transient_key_length_is_measured_in_bytes_for_multibyte_keys() {
		// 86 x 'é' (2 bytes each) = 172 bytes: accepted.
		$accepted = str_repeat( 'é', 86 );
		$this->assertTrue( $this->options->set_transient_raw( $accepted, 'mb', 60 ) );
		$this->assertSame( 'mb', $this->options->get_transient_raw( $accepted ) );

		// 87 x 'é' = 174 bytes (only 87 characters): rejected conservatively.
		$this->expectException( InvalidArgumentException::class );
		$this->options->set_transient_raw( str_repeat( 'é', 87 ), 'mb', 60 );
	}

	public function test_transient_keys_with_special_and_unicode_characters_round_trip() {
		$keys = array(
			"quote'\"back\\slash",
			'path/with:colons?and=query&x',
			'percent%and_underscore',
			'ñandú_日本語_ключ',
		);

		foreach ( $keys as $index => $key ) {
			$this->assertTrue( $this->options->set_transient( $key, "value_{$index}", 300 ), "Failed to set key: {$key}" );
			$this->assertSame( "value_{$index}", $this->options->get_transient( $key ), "Failed to read key: {$key}" );
			$this->assertSame( 1, $this->count_option_rows( Options::TRANSIENT_PREFIX . $key ) );
		}

		foreach ( $keys as $key ) {
			$this->assertTrue( $this->options->delete_transient( $key ) );
		}
	}

	public function test_transient_expiration_threshold_boundaries() {
		$key = 'expr_test_boundary_threshold';
		$this->options->set_transient( $key, 'live', 600 );
		$timeout_key = Options::TRANSIENT_TIMEOUT_PREFIX . $key;

		// time() < timeout: alive.
		$this->options->update_raw( $timeout_key, (string) ( time() + 30 ) );
		$this->assertSame( 'live', $this->options->get_transient( $key ) );
		$this->assertSame( 'live', $this->options->get_transient_raw( $key ) );

		// time() > timeout: expired.
		$this->options->update_raw( $timeout_key, (string) ( time() - 30 ) );
		$this->assertSame( 'gone', $this->options->get_transient( $key, 'gone' ) );
		$this->assertSame( 'gone', $this->options->get_transient_raw( $key, 'gone' ) );
		$this->assertSame( 0, $this->options->get_transient_ttl( $key ) );

		$this->assertTrue( $this->options->delete_transient( $key ) );
	}

	public function test_persistent_transient_creates_no_timeout_and_purges_existing_one() {
		$key = 'expr_test_persistent_purge';

		$this->options->set_transient( $key, 'forever', 0 );
		$this->assertSame( 0, $this->count_option_rows( Options::TRANSIENT_TIMEOUT_PREFIX . $key ) );

		$this->options->set_transient( $key, 'expiring', 120 );
		$this->assertSame( 1, $this->count_option_rows( Options::TRANSIENT_TIMEOUT_PREFIX . $key ) );

		$this->options->set_transient( $key, 'forever_again', 0 );
		$this->assertSame( 0, $this->count_option_rows( Options::TRANSIENT_TIMEOUT_PREFIX . $key ) );
		$this->assertSame( 'forever_again', $this->options->get_transient( $key ) );

		// Same for the raw writer.
		$this->options->set_transient_raw( $key, 'raw_expiring', 120 );
		$this->assertSame( 1, $this->count_option_rows( Options::TRANSIENT_TIMEOUT_PREFIX . $key ) );
		$this->options->set_transient_raw( $key, 'raw_forever', 0 );
		$this->assertSame( 0, $this->count_option_rows( Options::TRANSIENT_TIMEOUT_PREFIX . $key ) );
	}

	public function test_transient_timeout_and_ttl_contract() {
		$key = 'expr_test_ttl_contract';

		$this->assertNull( $this->options->get_transient_timeout( $key ) );
		$this->assertNull( $this->options->get_transient_ttl( $key ) );

		$this->options->set_transient( $key, 'value', 600 );
		$past = time() - 100;
		$this->options->update_raw( Options::TRANSIENT_TIMEOUT_PREFIX . $key, (string) $past );
		$this->assertSame( $past, $this->options->get_transient_timeout( $key ) );
		$this->assertSame( 0, $this->options->get_transient_ttl( $key ) );

		$this->options->set_transient( $key, 'value', 0 );
		$this->assertNull( $this->options->get_transient_timeout( $key ) );
		$this->assertSame( 0, $this->options->get_transient_ttl( $key ) );
	}

	public function test_orphaned_timeout_without_data_is_reported_as_non_existent() {
		$key = 'expr_test_orphan_timeout';
		$this->options->update_raw( Options::TRANSIENT_TIMEOUT_PREFIX . $key, (string) ( time() + 900 ), 'no' );

		$this->assertNull( $this->options->get_transient( $key ) );
		$this->assertNull( $this->options->get_transient_raw( $key, null, false ) );
		$this->assertNull( $this->options->get_transient_timeout( $key ) );
		$this->assertNull( $this->options->get_transient_ttl( $key ) );

		// The orphan remains inspectable through the raw primitive.
		$this->assertNotNull( $this->options->get_raw( Options::TRANSIENT_TIMEOUT_PREFIX . $key ) );
	}

	public function test_reading_expired_transient_is_non_destructive() {
		$key = 'expr_test_non_destructive';
		$this->options->set_transient( $key, array( 'evidence' => 1 ), 600 );
		$this->options->update_raw( Options::TRANSIENT_TIMEOUT_PREFIX . $key, (string) ( time() - 3600 ) );

		$this->options->get_transient( $key );
		$this->options->get_transient_raw( $key );
		$this->options->get_transient_raw( $key, null, false );
		$this->options->get_transient_timeout( $key );
		$this->options->get_transient_ttl( $key );

		$this->assertSame( 1, $this->count_option_rows( Options::TRANSIENT_PREFIX . $key ), 'Expired data row was deleted on read.' );
		$this->assertSame( 1, $this->count_option_rows( Options::TRANSIENT_TIMEOUT_PREFIX . $key ), 'Expired timeout row was deleted on read.' );
	}

	public function test_expiring_transient_is_never_autoloaded_even_when_value_is_unchanged() {
		$key      = 'expr_test_autoload_unchanged';
		$data_key = Options::TRANSIENT_PREFIX . $key;

		// Persistent transients follow WordPress core and may be autoloaded.
		$this->options->set_transient( $key, 'same_value', 0 );
		$this->assertContains( $this->get_autoload_flag( $data_key ), wp_autoload_values_to_autoload() );

		// Converting to an expiring transient with the SAME value must still disable autoload.
		$this->options->set_transient( $key, 'same_value', 3600 );
		$this->assertNotContains( $this->get_autoload_flag( $data_key ), wp_autoload_values_to_autoload() );
		$this->assertNotContains( $this->get_autoload_flag( Options::TRANSIENT_TIMEOUT_PREFIX . $key ), wp_autoload_values_to_autoload() );
		$this->assertArrayNotHasKey( $data_key, wp_load_alloptions() );
		$this->assertSame( 'same_value', $this->options->get_transient( $key ) );
	}

	public function test_set_transient_raw_forces_no_autoload_on_both_rows() {
		$key = 'expr_test_raw_autoload';
		$this->options->update_raw( Options::TRANSIENT_PREFIX . $key, 'v', 'yes' );

		$this->options->set_transient_raw( $key, 'v', 300 );

		$this->assertSame( 'no', $this->get_autoload_flag( Options::TRANSIENT_PREFIX . $key ) );
		$this->assertSame( 'no', $this->get_autoload_flag( Options::TRANSIENT_TIMEOUT_PREFIX . $key ) );
	}

	public function test_transient_writes_keep_wordpress_object_cache_coherent() {
		$key = 'expr_test_cache_coherency';

		// Prime the `notoptions` cache: WordPress core now believes neither row exists.
		$this->assertFalse( get_transient( $key ) );
		$this->assertFalse( get_option( Options::TRANSIENT_TIMEOUT_PREFIX . $key ) );

		$this->options->set_transient_raw( $key, 'fresh', 300 );
		$this->assertSame( 'fresh', get_option( Options::TRANSIENT_PREFIX . $key ) );
		$this->assertNotFalse( get_option( Options::TRANSIENT_TIMEOUT_PREFIX . $key ), 'Stale `notoptions` cache hides the new timeout row.' );
		$this->assertSame( 'fresh', get_transient( $key ) );

		$this->options->set_transient( $key, 'updated', 300 );
		$this->assertSame( 'updated', get_transient( $key ) );

		$this->options->delete_transient( $key );
		$this->assertFalse( get_transient( $key ) );
	}

	public function test_set_transient_rejects_negative_expiration() {
		$this->expectException( InvalidArgumentException::class );
		$this->options->set_transient( 'expr_test_negative', 'value', -1 );
	}

	public function test_set_transient_raw_rejects_negative_expiration_without_writing() {
		try {
			$this->options->set_transient_raw( 'expr_test_negative_raw', 'value', -60 );
			$this->fail( 'Negative expiration was accepted.' );
		} catch ( InvalidArgumentException $e ) {
			$this->assertSame( 0, $this->count_option_rows( Options::TRANSIENT_PREFIX . 'expr_test_negative_raw' ) );
		}
	}

	public function test_get_transient_refuses_object_injection_payload_while_raw_read_is_safe() {
		$key     = 'expr_test_poi_transient';
		$payload = 'O:8:"Requests":1:{s:4:"hook";s:6:"system";}';
		$this->options->set_transient_raw( $key, $payload, 600 );

		// Raw read never deserializes.
		$this->assertSame( $payload, $this->options->get_transient_raw( $key ) );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Options.get_transient_raw()' );
		$this->options->get_transient( $key );
	}

	public function test_set_transient_rejects_non_stdclass_objects_without_side_effects() {
		$key = 'expr_test_unsafe_write';

		try {
			$this->options->set_transient( $key, new ArrayObject( array( 1 ) ), 600 );
			$this->fail( 'Unsafe object was accepted.' );
		} catch ( Exception $e ) {
			$this->assertStringContainsString( 'Unsafe or invalid data for serialization', $e->getMessage() );
		}

		$this->assertSame( 0, $this->count_option_rows( Options::TRANSIENT_PREFIX . $key ) );
		$this->assertSame( 0, $this->count_option_rows( Options::TRANSIENT_TIMEOUT_PREFIX . $key ) );
	}

	/**
	 * @group singlesite
	 */
	public function test_single_site_timeout_and_ttl_resolve_from_the_same_scope_as_the_value() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single site fallback test.' );
		}

		$key = 'expr_test_scope_coherence';

		// Expiring site transient + persistent standard transient with the same key.
		set_site_transient( $key, 'site_value', 3600 );
		$this->options->set_transient( $key, 'standard_value', 0 );

		// The standard transient wins, so its (absent) timeout must be reported, not the site transient's.
		$this->assertSame( 'standard_value', $this->options->get_transient( $key ) );
		$this->assertNull( $this->options->get_transient_timeout( $key ) );
		$this->assertSame( 0, $this->options->get_transient_ttl( $key ) );

		// An expired standard transient masks (does not fall back to) the site transient.
		$this->options->set_transient( $key, 'standard_value', 600 );
		$this->options->update_raw( Options::TRANSIENT_TIMEOUT_PREFIX . $key, (string) ( time() - 5 ) );
		$this->assertSame( 'masked', $this->options->get_transient( $key, 'masked' ) );

		$this->assertTrue( $this->options->delete_transient( $key ) );
		$this->assertSame( 0, $this->count_option_rows( Options::SITE_TRANSIENT_TIMEOUT_PREFIX . $key ) );
	}

	/**
	 * @group singlesite
	 */
	public function test_single_site_fallback_honours_site_transient_expiration_non_destructively() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single site fallback test.' );
		}

		$key = 'expr_test_site_fallback_expired';
		set_site_transient( $key, 'site_value', 3600 );
		$this->options->update_raw( Options::SITE_TRANSIENT_TIMEOUT_PREFIX . $key, (string) ( time() - 5 ) );

		$this->assertNull( $this->options->get_transient( $key ) );
		$this->assertNull( $this->options->get_transient_raw( $key ) );
		// WordPress stores scalar strings verbatim (maybe_serialize() leaves them untouched).
		$this->assertSame( 'site_value', $this->options->get_transient_raw( $key, null, false ) );
		$this->assertSame( 0, $this->options->get_transient_ttl( $key ) );
		$this->assertSame( 1, $this->count_option_rows( Options::SITE_TRANSIENT_PREFIX . $key ) );
		$this->assertSame( 1, $this->count_option_rows( Options::SITE_TRANSIENT_TIMEOUT_PREFIX . $key ) );
	}

	/**
	 * @group multisite
	 */
	public function test_multisite_options_never_falls_back_to_site_transient_rows() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite test.' );
		}

		$key = 'expr_test_ms_no_fallback';
		$this->options->update_raw( Options::SITE_TRANSIENT_PREFIX . $key, 'stray_row' );

		$this->assertNull( $this->options->get_transient( $key ) );
		$this->assertNull( $this->options->get_transient_raw( $key, null, false ) );
		$this->assertNull( $this->options->get_transient_ttl( $key ) );

		// delete_transient() must not purge `_site_transient_*` rows on multisite.
		$this->assertFalse( $this->options->delete_transient( $key ) );
		$this->assertSame( 1, $this->count_option_rows( Options::SITE_TRANSIENT_PREFIX . $key ) );
	}

	/**
	 * @group multisite
	 */
	public function test_multisite_site_options_transients_are_scoped_to_the_target_blog() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite test.' );
		}

		$blog_id      = self::factory()->blog->create();
		$site_options = new \ExpressionLab\Core\Models\SiteOptions( get_site( $blog_id ) );
		$key          = 'expr_test_subsite_transient';

		$this->assertTrue( $site_options->set_transient( $key, 'subsite_value', 600 ) );
		$this->assertSame( 'subsite_value', $site_options->get_transient( $key ) );
		$this->assertGreaterThan( 590, $site_options->get_transient_ttl( $key ) );

		// Not visible from the main site's options table.
		$this->assertNull( $this->options->get_transient( $key ) );
		$this->assertSame( 0, $this->count_option_rows( Options::TRANSIENT_PREFIX . $key ) );

		switch_to_blog( $blog_id );
		$this->assertSame( 1, $this->count_option_rows( Options::TRANSIENT_PREFIX . $key ) );
		$this->assertNotContains( $this->get_autoload_flag( Options::TRANSIENT_PREFIX . $key ), wp_autoload_values_to_autoload() );
		restore_current_blog();

		$this->assertTrue( $site_options->delete_transient( $key ) );
		switch_to_blog( $blog_id );
		$this->assertSame( 0, $this->count_option_rows( Options::TRANSIENT_PREFIX . $key ) );
		restore_current_blog();
		$this->assertSame( get_current_blog_id(), get_main_site_id() );
	}
}
