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


	/**
	 * @group singlesite
	 */
	public function test_get_raw_throws_exception_in_non_multisite() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single site guard test.' );
		}
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Network options are only available in multisite installations.' );
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
		$this->expectExceptionMessage( 'Network options are only available in multisite installations.' );
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
		$this->expectExceptionMessage( 'Network options are only available in multisite installations.' );
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
		$this->expectExceptionMessage( 'Network options are only available in multisite installations.' );
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
		$this->expectExceptionMessage( 'Network options are only available in multisite installations.' );
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
		$this->expectExceptionMessage( 'Network options are only available in multisite installations.' );
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
}