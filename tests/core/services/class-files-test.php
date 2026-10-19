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

	public function test_read_attaches_file_visualization() {
		LanguageEngine::get()->reset();
		$this->files->read( 'wp-load.php', 200 );

		$visualizations = LanguageEngine::get()->get_visualizations();
		$this->assertNotEmpty( $visualizations );
		$last_viz = end( $visualizations );

		$this->assertEquals( 'file', $last_viz['type'] );
		$this->assertEquals( 'wp-load.php', $last_viz['filename'] );
		$this->assertNotEmpty( $last_viz['content'] );
		$this->assertEquals( 'php', $last_viz['ext'] );
	}

	public function test_tail_attaches_file_visualization() {
		LanguageEngine::get()->reset();
		$this->files->tail( 'wp-load.php', 10 );

		$visualizations = LanguageEngine::get()->get_visualizations();
		$this->assertNotEmpty( $visualizations );
		$last_viz = end( $visualizations );

		$this->assertEquals( 'file', $last_viz['type'] );
		$this->assertStringContainsString( 'tail 10', $last_viz['title'] );
		$this->assertNotEmpty( $last_viz['content'] );
	}

	public function test_show_code_file_generates_file_visualization() {
		LanguageEngine::get()->reset();
		$result = $this->files->show( 'wp-load.php' );

		$this->assertIsArray( $result );
		$this->assertEquals( 'file', $result['type'] );
		$this->assertEquals( 'wp-load.php', $result['name'] );

		$visualizations = LanguageEngine::get()->get_visualizations();
		$this->assertNotEmpty( $visualizations );
		$last_viz = end( $visualizations );

		$this->assertEquals( 'file', $last_viz['type'] );
		$this->assertEquals( 'wp-load.php', $last_viz['filename'] );
	}

	public function test_show_image_file_generates_image_visualization() {
		// Copy test image into WordPress uploads inside ABSPATH
		$image_source = DIR_TESTDATA . '/images/test-image.png';
		if ( ! file_exists( $image_source ) ) {
			$this->markTestSkipped( 'Test image not available.' );
		}

		$upload_dir = wp_upload_dir();
		$dest_dir   = $upload_dir['basedir'] . '/expressionlab-tests';
		if ( ! file_exists( $dest_dir ) ) {
			wp_mkdir_p( $dest_dir );
		}
		$dest_file = $dest_dir . '/test-image.png';
		copy( $image_source, $dest_file );

		try {
			$relative_path = ltrim( str_replace( ABSPATH, '', $dest_file ), '/' );

			LanguageEngine::get()->reset();
			$result = $this->files->show( $relative_path );

			$this->assertIsArray( $result );
			$this->assertEquals( 'image', $result['type'] );
			$this->assertEquals( 'image/png', $result['mime'] );
			$this->assertGreaterThan( 0, $result['bytes'] );

			$visualizations = LanguageEngine::get()->get_visualizations();
			$this->assertNotEmpty( $visualizations );
			$last_viz = end( $visualizations );

			$this->assertEquals( 'image', $last_viz['type'] );
			$this->assertEquals( 'image/png', $last_viz['mime'] );
			$this->assertNotEmpty( $last_viz['base64'] );
		} finally {
			if ( file_exists( $dest_file ) ) {
				unlink( $dest_file );
			}
		}
	}

	public function test_show_directory_throws() {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Cannot show a directory' );
		$this->files->show( 'wp-content' );
	}

	private function make_binary_fixture(): array {
		$upload_dir = wp_upload_dir();
		$dest_dir   = $upload_dir['basedir'] . '/expressionlab-tests';
		wp_mkdir_p( $dest_dir );
		$dest_file = $dest_dir . '/test-binary-show.bin';
		file_put_contents( $dest_file, "BINARY\0\x01\x02\xFFDATA" );
		return array( $dest_file, ltrim( str_replace( ABSPATH, '', $dest_file ), '/' ) );
	}

	public function test_show_binary_file_throws() {
		list( $file, $relative ) = $this->make_binary_fixture();
		try {
			LanguageEngine::get()->reset();
			$this->expectException( \RuntimeException::class );
			$this->expectExceptionMessage( 'Binary file detected' );
			$this->files->show( $relative );
		} finally {
			unlink( $file );
		}
	}

	public function test_tail_binary_file_skips_visualization() {
		list( $file, $relative ) = $this->make_binary_fixture();
		try {
			LanguageEngine::get()->reset();
			$this->files->tail( $relative );
			$this->assertEmpty( LanguageEngine::get()->get_visualizations() );
			$this->assertNotEmpty( LanguageEngine::get()->get_messages() );
		} finally {
			unlink( $file );
		}
	}

	public function test_stream_wrappers_are_rejected() {
		$this->assertFalse( $this->files->exists( 'phar://wp-config.php' ) );
		$this->assertFalse( $this->files->exists( 'php://filter/resource=wp-config.php' ) );
		$this->expectException( \InvalidArgumentException::class );
		$this->files->read( 'compress.zlib://wp-config.php' );
	}

	public function test_read_null_byte_path_is_rejected() {
		$this->expectException( \InvalidArgumentException::class );
		$this->files->read( "wp-config.php\0.txt" );
	}

	public function test_read_binary_file_returns_false_and_warns() {
		$upload_dir = wp_upload_dir();
		$dest_dir   = $upload_dir['basedir'] . '/expressionlab-tests';
		if ( ! file_exists( $dest_dir ) ) {
			wp_mkdir_p( $dest_dir );
		}
		$dest_file = $dest_dir . '/test-binary.bin';
		// Create a binary file with null bytes.
		file_put_contents( $dest_file, "BINARY\0\x01\x02\xFFDATA" );

		try {
			$relative_path = ltrim( str_replace( ABSPATH, '', $dest_file ), '/' );

			LanguageEngine::get()->reset();
			$result = $this->files->read( $relative_path );

			$this->assertFalse( $result );
			$this->assertEmpty( LanguageEngine::get()->get_visualizations() );

			$messages = LanguageEngine::get()->get_messages();
			$this->assertNotEmpty( $messages );
			$this->assertEquals( 'warning', $messages[0]['type'] );
			$this->assertStringContainsString( 'Binary file detected', $messages[0]['text'] );
		} finally {
			if ( file_exists( $dest_file ) ) {
				unlink( $dest_file );
			}
		}
	}

	public function test_read_image_file_returns_data_uri_and_attaches_image_visualization() {
		$image_source = DIR_TESTDATA . '/images/test-image.png';
		if ( ! file_exists( $image_source ) ) {
			$this->markTestSkipped( 'Test image not available.' );
		}

		$upload_dir = wp_upload_dir();
		$dest_dir   = $upload_dir['basedir'] . '/expressionlab-tests';
		if ( ! file_exists( $dest_dir ) ) {
			wp_mkdir_p( $dest_dir );
		}
		$dest_file = $dest_dir . '/test-read-image.png';
		copy( $image_source, $dest_file );

		try {
			$relative_path = ltrim( str_replace( ABSPATH, '', $dest_file ), '/' );

			LanguageEngine::get()->reset();
			$result = $this->files->read( $relative_path );

			$this->assertIsString( $result );
			$this->assertTrue( str_starts_with( $result, 'data:image/png;base64,' ) );

			$visualizations = LanguageEngine::get()->get_visualizations();
			$this->assertNotEmpty( $visualizations );
			$last_viz = end( $visualizations );

			$this->assertEquals( 'image', $last_viz['type'] );
			$this->assertEquals( 'image/png', $last_viz['mime'] );
			$this->assertNotEmpty( $last_viz['base64'] );
		} finally {
			if ( file_exists( $dest_file ) ) {
				unlink( $dest_file );
			}
		}
	}

	public function test_read_image_exceeding_budget_returns_false_and_warns() {
		$image_source = DIR_TESTDATA . '/images/test-image.png';
		if ( ! file_exists( $image_source ) ) {
			$this->markTestSkipped( 'Test image not available.' );
		}

		$upload_dir = wp_upload_dir();
		$dest_dir   = $upload_dir['basedir'] . '/expressionlab-tests';
		if ( ! file_exists( $dest_dir ) ) {
			wp_mkdir_p( $dest_dir );
		}
		$dest_file = $dest_dir . '/test-read-budget.png';
		copy( $image_source, $dest_file );

		try {
			$relative_path = ltrim( str_replace( ABSPATH, '', $dest_file ), '/' );

			LanguageEngine::get()->reset();
			// Pre-allocate full binary budget so image read exceeds budget
			LanguageEngine::get()->allocate_binary( LanguageEngine::MAX_EMBEDDED_BINARY_BYTES );

			$result = $this->files->read( $relative_path );

			$this->assertFalse( $result );
			$this->assertEmpty( LanguageEngine::get()->get_visualizations() );

			$messages = LanguageEngine::get()->get_messages();
			$this->assertNotEmpty( $messages );
			$this->assertEquals( 'warning', $messages[0]['type'] );
			$this->assertStringContainsString( 'budget', $messages[0]['text'] );
		} finally {
			if ( file_exists( $dest_file ) ) {
				unlink( $dest_file );
			}
		}
	}
}


