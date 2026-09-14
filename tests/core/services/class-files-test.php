<?php

// phpcs:ignoreFile

use ExpressionLab\Core\LanguageEngine;
use ExpressionLab\Core\Services\Files;

class FilesTest extends WP_UnitTestCase {

	/**
	 * Files instance.
	 *
	 * @var Files
	 */
	private $files;

	public function setUp(): void {
		parent::setUp();
		LanguageEngine::get()->reset();
		$this->files = new Files();
	}

	public function test_exists_and_is_file() {
		$this->assertTrue( $this->files->exists( 'wp-load.php' ) );
		$this->assertTrue( $this->files->is_file( 'wp-load.php' ) );
		$this->assertFalse( $this->files->exists( 'non_existent_file_xyz.txt' ) );
	}

	public function test_is_dir() {
		$this->assertTrue( $this->files->is_dir( 'wp-content' ) );
		$this->assertFalse( $this->files->is_dir( 'wp-load.php' ) );
	}

	public function test_size_and_modified() {
		$size = $this->files->size( 'wp-load.php' );
		$this->assertIsArray( $size );
		$this->assertArrayHasKey( 'bytes', $size );
		$this->assertArrayHasKey( 'human', $size );
		$this->assertGreaterThan( 0, $size['bytes'] );

		$modified = $this->files->modified( 'wp-load.php' );
		$this->assertIsArray( $modified );
		$this->assertArrayHasKey( 'timestamp', $modified );
		$this->assertArrayHasKey( 'formatted', $modified );
	}

	public function test_read_file() {
		$content = $this->files->read( 'wp-load.php', 100 );
		$this->assertIsString( $content );
		$this->assertNotEmpty( $content );
		$this->assertLessThanOrEqual( 100, strlen( $content ) );
	}

	public function test_read_file_default_max_bytes() {
		$reflection = new \ReflectionMethod( $this->files, 'read' );
		$params     = $reflection->getParameters();
		$this->assertEquals( Files::MAX_READ_BYTES, $params[1]->getDefaultValue() );

		$content = $this->files->read( 'wp-load.php' );
		$this->assertIsString( $content );
		$this->assertNotEmpty( $content );
	}

	public function test_tail_file() {
		$lines = $this->files->tail( 'wp-load.php', 5 );
		$this->assertIsArray( $lines );
		$this->assertLessThanOrEqual( 5, count( $lines ) );
	}

	public function test_list_directory() {
		$items = $this->files->list( 'wp-content' );
		$this->assertIsArray( $items );
		$this->assertNotEmpty( $items );
		$this->assertArrayHasKey( 'name', $items[0] );
		$this->assertArrayHasKey( 'type', $items[0] );
	}

	public function test_stats_directory() {
		$stats = $this->files->stats( 'wp-content' );
		$this->assertIsArray( $stats );
		$this->assertArrayHasKey( 'files', $stats );
		$this->assertArrayHasKey( 'directories', $stats );
		$this->assertArrayHasKey( 'by_ext', $stats );
	}

	public function test_path_traversal_outside_abspath_is_blocked() {
		$this->expectException( \InvalidArgumentException::class );
		$this->files->read( '../../../../etc/passwd' );
	}

	public function test_constants_exposed_via_get() {
		$this->assertEquals( 262144, $this->files->MAX_READ_BYTES );
		$this->assertEquals( 1048576, $this->files->MAX_TAIL_BYTES );
		$this->assertNull( $this->files->NON_EXISTENT_CONSTANT );
	}

	public function test_resolve_safe_path_non_existent_traversal_blocked() {
		$reflection = new \ReflectionMethod( $this->files, 'resolve_safe_path' );

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Access denied' );
		$reflection->invoke( $this->files, 'wp-content/../../etc/passwd', false );
	}

	public function test_resolve_safe_path_non_existent_inside_root_allowed() {
		$reflection = new \ReflectionMethod( $this->files, 'resolve_safe_path' );

		$result = $reflection->invoke( $this->files, 'wp-content/uploads/future-file.txt', false );
		$this->assertIsString( $result );
		$this->assertTrue( str_starts_with( $result, wp_normalize_path( realpath( ABSPATH ) ) ) );
	}
}

