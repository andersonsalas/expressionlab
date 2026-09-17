<?php

// phpcs:ignoreFile

use ExpressionLab\Core\Cli\Runners\IPLookupRunner;
use ExpressionLab\Core\Services\IPLookup;
use splitbrain\PHPArchive\Tar;

class IPLookupRunnerTest extends WP_UnitTestCase {

	/**
	 * Runner instance.
	 *
	 * @var IPLookupRunner
	 */
	private $runner;

	/**
	 * Target database path.
	 *
	 * @var string
	 */
	private $target_path;

	public function setUp(): void {
		parent::setUp();
		$this->runner      = new IPLookupRunner();
		$this->target_path = IPLookup::get_database_path();

		if ( file_exists( $this->target_path ) ) {
			unlink( $this->target_path );
		}
	}

	public function tearDown(): void {
		if ( file_exists( $this->target_path ) ) {
			unlink( $this->target_path );
		}

		$tmp_target = $this->target_path . '.tmp';
		if ( file_exists( $tmp_target ) ) {
			unlink( $tmp_target );
		}

		parent::tearDown();
	}

	public function test_update_throws_exception_when_license_key_is_missing() {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'MaxMind license key is missing' );

		$this->runner->update( null );
	}

	public function test_delete_returns_false_when_file_not_found() {
		$this->assertFileDoesNotExist( $this->target_path );
		$this->assertFalse( $this->runner->delete() );
	}

	public function test_delete_removes_existing_file() {
		$dir = dirname( $this->target_path );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		file_put_contents( $this->target_path, 'dummy database content' );
		$this->assertFileExists( $this->target_path );

		$this->assertTrue( $this->runner->delete() );
		$this->assertFileDoesNotExist( $this->target_path );
	}

	public function test_get_status_reports_uninstalled_when_missing() {
		$status = $this->runner->get_status();

		$this->assertIsArray( $status );
		$this->assertFalse( $status['installed'] );
		$this->assertNull( $status['size'] );
		$this->assertSame( $this->target_path, $status['path'] );
	}

	public function test_get_status_reports_installed_when_file_present() {
		$dir = dirname( $this->target_path );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		file_put_contents( $this->target_path, 'dummy database content' );

		$status = $this->runner->get_status();

		$this->assertTrue( $status['installed'] );
		$this->assertSame( strlen( 'dummy database content' ), $status['size'] );
		$this->assertNotNull( $status['modified'] );
	}

	public function test_update_skips_when_recently_updated_without_force() {
		$dir = dirname( $this->target_path );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		file_put_contents( $this->target_path, 'dummy content' );

		$result = $this->runner->update( 'test_license_key', false );

		$this->assertSame( 'skipped', $result['status'] );
		$this->assertSame( 'recently_updated', $result['reason'] );
	}

	public function test_tar_archive_extraction() {
		$temp_dir = wp_normalize_path( get_temp_dir() . 'test_tar_' . uniqid() );
		wp_mkdir_p( $temp_dir );

		$sub_dir = $temp_dir . '/GeoLite2-Country_20260916';
		wp_mkdir_p( $sub_dir );

		file_put_contents( $sub_dir . '/README.txt', 'Documentation' );
		file_put_contents( $sub_dir . '/GeoLite2-Country.mmdb', 'Binary MMDB Content' );

		$archive_file = $temp_dir . '/archive.tar';
		$tar          = new Tar();
		$tar->create( $archive_file );
		$tar->addFile( $sub_dir . '/README.txt', 'GeoLite2-Country_20260916/README.txt' );
		$tar->addFile( $sub_dir . '/GeoLite2-Country.mmdb', 'GeoLite2-Country_20260916/GeoLite2-Country.mmdb' );
		$tar->close();

		$extract_dir = $temp_dir . '/extracted';
		wp_mkdir_p( $extract_dir );

		$extract_tar = new Tar();
		$extract_tar->open( $archive_file );
		$extract_tar->extract( $extract_dir );

		$this->assertFileExists( $extract_dir . '/GeoLite2-Country_20260916/GeoLite2-Country.mmdb' );
		$this->assertSame( 'Binary MMDB Content', file_get_contents( $extract_dir . '/GeoLite2-Country_20260916/GeoLite2-Country.mmdb' ) );

		unlink( $sub_dir . '/README.txt' );
		unlink( $sub_dir . '/GeoLite2-Country.mmdb' );
		rmdir( $sub_dir );
		unlink( $archive_file );
		unlink( $extract_dir . '/GeoLite2-Country_20260916/README.txt' );
		unlink( $extract_dir . '/GeoLite2-Country_20260916/GeoLite2-Country.mmdb' );
		rmdir( $extract_dir . '/GeoLite2-Country_20260916' );
		rmdir( $extract_dir );
		rmdir( $temp_dir );
	}
}
