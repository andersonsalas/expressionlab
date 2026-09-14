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
}
