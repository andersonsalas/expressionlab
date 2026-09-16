<?php

// phpcs:ignoreFile

use ExpressionLab\Core\LanguageEngine;
use ExpressionLab\Core\Services\IPLookup;
use MaxMind\Db\Reader;

class IPLookupTest extends WP_UnitTestCase {

	/**
	 * IPLookup instance.
	 *
	 * @var IPLookup
	 */
	private $iplookup;

	public function setUp(): void {
		parent::setUp();
		LanguageEngine::get()->reset();
		$this->iplookup = new IPLookup();
	}

	public function tearDown(): void {
		LanguageEngine::get()->reset();
		parent::tearDown();
	}

	public function test_invalid_ip_format_throws_exception() {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid IP address provided' );
		$this->iplookup->to_country( 'not-a-valid-ip' );
	}

	public function test_invalid_ip_range_throws_exception() {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid IP address provided' );
		$this->iplookup->to_country( '999.999.999.999' );
	}

	public function test_private_ipv4_returns_null_without_reader() {
		$this->assertNull( $this->iplookup->to_country( '192.168.1.1' ) );
		$this->assertNull( $this->iplookup->to_country( '10.0.0.5' ) );
		$this->assertNull( $this->iplookup->to_country( '172.16.0.10' ) );
	}

	public function test_loopback_and_reserved_ips_return_null() {
		$this->assertNull( $this->iplookup->to_country( '127.0.0.1' ) );
		$this->assertNull( $this->iplookup->to_country( '::1' ) );
	}

	public function test_missing_database_throws_runtime_exception() {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'GeoLite2 database not found' );
		$this->iplookup->to_country( '8.8.8.8' );
	}

	public function test_is_available_returns_false_when_database_absent() {
		$this->assertFalse( $this->iplookup->is_available() );
		$this->assertFalse( IPLookup::is_available() );
	}

	public function test_get_database_path_deterministic_default() {
		$path = IPLookup::get_database_path();
		$this->assertIsString( $path );
		$this->assertTrue( str_starts_with( $path, wp_normalize_path( WP_CONTENT_DIR . '/expressionlab/' ) ) );
		$this->assertTrue( str_ends_with( $path, '-GeoLite2-Country.mmdb' ) );
	}

	/**
	 * Injects a mock Reader into an IPLookup instance via reflection.
	 *
	 * @param IPLookup $instance IPLookup instance.
	 * @param Reader   $reader   Mock Reader instance.
	 */
	private function set_mock_reader( IPLookup $instance, Reader $reader ): void {
		$ref = new \ReflectionProperty( IPLookup::class, 'reader' );
		$ref->setValue( $instance, $reader );
	}

	public function test_to_country_with_injected_reader_resolves_country() {
		$mock_reader = $this->getMockBuilder( Reader::class )
			->disableOriginalConstructor()
			->getMock();

		$mock_reader->method( 'get' )
			->with( '8.8.8.8' )
			->willReturn( array(
				'country' => array(
					'iso_code' => 'US',
				),
			) );

		$iplookup = new IPLookup();
		$this->set_mock_reader( $iplookup, $mock_reader );
		$this->assertSame( 'US', $iplookup->to_country( '8.8.8.8' ) );
	}

	public function test_to_country_with_registered_country_fallback() {
		$mock_reader = $this->getMockBuilder( Reader::class )
			->disableOriginalConstructor()
			->getMock();

		$mock_reader->method( 'get' )
			->with( '1.1.1.1' )
			->willReturn( array(
				'registered_country' => array(
					'iso_code' => 'au',
				),
			) );

		$iplookup = new IPLookup();
		$this->set_mock_reader( $iplookup, $mock_reader );
		$this->assertSame( 'AU', $iplookup->to_country( '1.1.1.1' ) );
	}

	public function test_to_country_when_record_missing_returns_null() {
		$mock_reader = $this->getMockBuilder( Reader::class )
			->disableOriginalConstructor()
			->getMock();

		$mock_reader->method( 'get' )
			->willReturn( null );

		$iplookup = new IPLookup();
		$this->set_mock_reader( $iplookup, $mock_reader );
		$this->assertNull( $iplookup->to_country( '8.8.4.4' ) );
	}

	public function test_evaluation_in_language_engine_private_ip() {
		$result = LanguageEngine::get()->evaluate( "IPLookup.to_country('127.0.0.1')" );
		$this->assertNull( $result['result'] );
	}

	public function test_language_engine_identifies_iplookup_as_reserved() {
		$this->assertTrue( LanguageEngine::get()->is_reserved_identifier( 'iplookup' ) );
		$this->assertTrue( LanguageEngine::get()->is_reserved_identifier( 'IPLookup' ) );
	}
}
