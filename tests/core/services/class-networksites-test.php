<?php

// phpcs:ignoreFile

use ExpressionLab\Core\Services\Database;
use ExpressionLab\Core\Services\NetworkSites;
use ExpressionLab\Core\Models\Site;

class NetworkSitesTest extends WP_UnitTestCase {

	/**
	 * NetworkSites instance.
	 *
	 * @var NetworkSites
	 */
	private $network_sites;

	/**
	 * Database mock or instance.
	 *
	 * @var Database
	 */
	private $database;

	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'SQLite3' ) ) {
			$this->markTestSkipped( 'SQLite3 extension is required.' );
		}

		$this->database      = new Database();
		$this->network_sites = new NetworkSites( $this->database );
	}


	/**
	 * @group singlesite
	 */
	public function test_get_throws_exception_in_non_multisite() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single site guard test.' );
		}
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Network sites are only available in multisite installations.' );
		$this->network_sites->get( 1 );
	}

	/**
	 * @group singlesite
	 */
	public function test_build_throws_exception_in_non_multisite() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single site guard test.' );
		}
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Network sites are only available in multisite installations.' );
		$this->network_sites->build();
	}

	/**
	 * @group singlesite
	 */
	public function test_list_throws_exception_in_non_multisite() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single site guard test.' );
		}
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Network sites are only available in multisite installations.' );
		$this->network_sites->list();
	}

	/**
	 * @group singlesite
	 */
	public function test_current_is_null_in_non_multisite() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single site guard test.' );
		}
		$this->assertNull( $this->network_sites->current );
	}


	/**
	 * @group multisite
	 */
	public function test_multisite_list_returns_array_of_sites() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite test.' );
		}

		$sites = $this->network_sites->list();
		$this->assertIsArray( $sites );
		$this->assertNotEmpty( $sites );
		$this->assertArrayHasKey( 'blog_id', $sites[0] );
	}

	/**
	 * @group multisite
	 */
	public function test_multisite_get_returns_site_instance() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite test.' );
		}

		$site = $this->network_sites->get( 1 );
		$this->assertInstanceOf( Site::class, $site );
	}

	/**
	 * @group multisite
	 */
	public function test_multisite_build_returns_site_instance() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite test.' );
		}

		$site = $this->network_sites->build();
		$this->assertInstanceOf( Site::class, $site );
		$this->assertNull( $site->blog_id );
		$this->assertEquals( get_current_network_id(), $site->site_id );
	}

	/**
	 * @group multisite
	 */
	public function test_multisite_get_by_numeric_string() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite test.' );
		}

		$site = $this->network_sites->get( '1' );
		$this->assertInstanceOf( Site::class, $site );
		$this->assertEquals( 1, $site->blog_id );
	}

	/**
	 * @group multisite
	 */
	public function test_multisite_unsaved_site_options_throws_exception() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite test.' );
		}

		$site = $this->network_sites->build();
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Cannot access site options on an unsaved site instance. Call `save()` first.' );

		$site->options->get( 'some_option' );
	}

	/**
	 * @group multisite
	 */
	public function test_multisite_domain_and_path_sanitization() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite test.' );
		}

		$site = $this->network_sites->build()
			->set_domain( 'https://testsite.local///' )
			->set_path( 'subsite///' );

		$this->assertEquals( 'testsite.local', $site->domain );
		$this->assertEquals( '/subsite/', $site->path );
	}

	/**
	 * @group multisite
	 */
	public function test_multisite_list_with_limit_and_offset() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite test.' );
		}

		$sites = $this->network_sites->list( 1, 0 );
		$this->assertIsArray( $sites );
		$this->assertCount( 1, $sites );
	}
}