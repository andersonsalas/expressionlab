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

	private function make_fixture( string $name, string $content ): array {
		$upload_dir = wp_upload_dir();
		$dest_dir   = $upload_dir['basedir'] . '/expressionlab-tests';
		wp_mkdir_p( $dest_dir );
		$dest_file = $dest_dir . '/' . $name;
		file_put_contents( $dest_file, $content );
		return array( $dest_file, ltrim( str_replace( ABSPATH, '', $dest_file ), '/' ) );
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
		foreach ( $items as $item ) {
			$this->assertIsString( $item['name'] );
			$this->assertContains( $item['type'], array( 'dir', 'file' ) );
			$this->assertArrayHasKey( 'perms', $item );
			if ( null !== $item['perms'] ) {
				$this->assertMatchesRegularExpression( '/^[0-7]{4}$/', $item['perms'] );
			}
			$this->assertIsString( $item['owner'] );
			$this->assertNotSame( '', $item['owner'] );
			$this->assertIsString( $item['group'] );
			$this->assertNotSame( '', $item['group'] );
			$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $item['modified'] );
			if ( 'dir' === $item['type'] ) {
				$this->assertNull( $item['size'] );
			} else {
				$this->assertIsString( $item['size'] );
			}
		}
	}

	public function test_perms() {
		$perms = $this->files->perms( 'wp-load.php' );
		$this->assertIsArray( $perms );
		foreach ( array( 'octal', 'symbolic', 'owner', 'group', 'readable', 'writable', 'executable' ) as $key ) {
			$this->assertArrayHasKey( $key, $perms );
		}
		$this->assertTrue( $perms['readable'] );
		$this->assertIsBool( $perms['writable'] );
		$this->assertIsBool( $perms['executable'] );
		$this->assertIsString( $perms['owner'] );
		$this->assertIsString( $perms['group'] );
		$this->assertMatchesRegularExpression( '/^[0-7]{4}$/', $perms['octal'] );
		$this->assertMatchesRegularExpression( '/^[-ldcbspurgwxST]{10}$/', $perms['symbolic'] );
		$this->assertStringStartsWith( '-', $perms['symbolic'] );
	}

	public function test_perms_directory_symbolic_type() {
		$perms = $this->files->perms( 'wp-content' );
		$this->assertStringStartsWith( 'd', $perms['symbolic'] );
		$this->assertMatchesRegularExpression( '/^[-ldcbspurgwxST]{10}$/', $perms['symbolic'] );
	}

	public function test_stats_directory() {
		$stats = $this->files->stats( 'wp-content' );
		$this->assertIsArray( $stats );
		$this->assertIsInt( $stats['files'] );
		$this->assertIsInt( $stats['directories'] );
		$this->assertIsInt( $stats['total_bytes'] );
		$this->assertIsString( $stats['total_size'] );
		$this->assertIsArray( $stats['by_ext'] );
		$this->assertSame( $stats['total_bytes'], array_sum( array_column( $stats['by_ext'], 'bytes' ) ) );
	}

	public function test_path_traversal_outside_abspath_is_blocked() {
		$this->expectException( \InvalidArgumentException::class );
		$this->files->read( '../../../../etc/passwd' );
	}

	public function test_absolute_path_outside_abspath_is_blocked() {
		$this->assertFalse( $this->files->exists( '/etc/passwd' ) );
		$this->expectException( \InvalidArgumentException::class );
		$this->files->tail( '/etc/passwd' );
	}

	public function test_sibling_directory_prefix_is_blocked() {
		$reflection = new \ReflectionMethod( $this->files, 'resolve_safe_path' );
		$sibling    = rtrim( wp_normalize_path( realpath( ABSPATH ) ), '/' ) . '_backup/wp-config.php';

		$this->expectException( \InvalidArgumentException::class );
		$reflection->invoke( $this->files, $sibling, false );
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

	public function test_read_directory_throws() {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Cannot read a directory as a file.' );
		$this->files->read( 'wp-content' );
	}

	public function test_tail_directory_throws() {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Cannot tail a directory.' );
		$this->files->tail( 'wp-content' );
	}

	public function test_tail_returns_exact_last_lines_without_trailing_empty_line() {
		$content = implode( "\n", array_map( fn( $i ) => "line{$i}", range( 1, 10 ) ) ) . "\n";
		list( $file, $relative ) = $this->make_fixture( 'tail-lines.log', $content );
		try {
			$this->assertSame( array( 'line8', 'line9', 'line10' ), $this->files->tail( $relative, 3 ) );
			$this->assertSame( array( 'line10' ), $this->files->tail( $relative, 1 ) );
			$this->assertCount( 10, $this->files->tail( $relative, 50 ) );
		} finally {
			unlink( $file );
		}
	}

	public function test_tail_without_trailing_newline_and_crlf() {
		list( $file, $relative ) = $this->make_fixture( 'tail-crlf.log', "first\r\nsecond\r\nthird" );
		try {
			$this->assertSame( array( 'second', 'third' ), $this->files->tail( $relative, 2 ) );
		} finally {
			unlink( $file );
		}
	}

	public function test_tail_spanning_multiple_chunks() {
		$lines = array_map( fn( $i ) => str_pad( (string) $i, 99, 'x' ), range( 1, 300 ) );
		list( $file, $relative ) = $this->make_fixture( 'tail-chunks.log', implode( "\n", $lines ) . "\n" );
		try {
			$this->assertSame( array_slice( $lines, -120 ), $this->files->tail( $relative, 120 ) );
		} finally {
			unlink( $file );
		}
	}

	public function test_tail_single_line_file_is_bounded_by_max_tail_bytes() {
		list( $file, $relative ) = $this->make_fixture( 'tail-oneline.log', str_repeat( 'a', Files::MAX_TAIL_BYTES + 50000 ) );
		try {
			$result = $this->files->tail( $relative, 5 );
			$this->assertCount( 1, $result );
			$this->assertSame( Files::MAX_TAIL_BYTES, strlen( $result[0] ) );
		} finally {
			unlink( $file );
		}
	}

	public function test_empty_file_is_read_and_tailed_as_text() {
		list( $file, $relative ) = $this->make_fixture( 'empty-debug.log', '' );
		try {
			LanguageEngine::get()->reset();
			$this->assertSame( '', $this->files->read( $relative ) );
			$this->assertSame( array(), $this->files->tail( $relative ) );
			$this->assertEmpty( LanguageEngine::get()->get_messages() );
		} finally {
			unlink( $file );
		}
	}

	public function test_list_and_stats_skip_dangling_symlinks() {
		$upload_dir = wp_upload_dir();
		$dir        = $upload_dir['basedir'] . '/expressionlab-tests/dangling';
		wp_mkdir_p( $dir );
		file_put_contents( $dir . '/real.txt', 'data' );
		$link = $dir . '/broken-link';

		try {
			if ( ! @symlink( $dir . '/missing-target', $link ) ) {
				$this->markTestSkipped( 'Symlinks are not supported on this filesystem.' );
			}
			$relative = ltrim( str_replace( ABSPATH, '', $dir ), '/' );

			$this->assertSame( array( 'real.txt' ), array_column( $this->files->list( $relative ), 'name' ) );

			$stats = $this->files->stats( $relative );
			$this->assertSame( 1, $stats['files'] );
			$this->assertSame( 4, $stats['total_bytes'] );
		} finally {
			if ( is_link( $link ) ) {
				unlink( $link );
			}
			unlink( $dir . '/real.txt' );
			rmdir( $dir );
		}
	}

	private function make_binary_fixture(): array {
		return $this->make_fixture( 'test-binary-tail.bin', "BINARY\0\x01\x02\xFFDATA" );
	}

	public function test_tail_binary_file_skips_visualization() {
		list( $file, $relative ) = $this->make_binary_fixture();
		try {
			LanguageEngine::get()->reset();
			$this->assertSame( array(), $this->files->tail( $relative ) );
			$this->assertEmpty( LanguageEngine::get()->get_visualizations() );
			$messages = LanguageEngine::get()->get_messages();
			$this->assertNotEmpty( $messages );
			$this->assertStringContainsString( 'Binary file detected', $messages[0]['text'] );
		} finally {
			unlink( $file );
		}
	}

	public function test_stream_wrappers_are_rejected() {
		$this->assertFalse( $this->files->exists( 'phar://wp-config.php' ) );
		$this->assertFalse( $this->files->exists( 'php://filter/resource=wp-config.php' ) );
		$this->assertFalse( $this->files->is_file( 'file://' . ABSPATH . 'wp-load.php' ) );
		$this->expectException( \InvalidArgumentException::class );
		$this->files->read( 'compress.zlib://wp-config.php' );
	}

	public function test_read_null_byte_path_is_rejected() {
		$this->expectException( \InvalidArgumentException::class );
		$this->files->read( "wp-config.php\0.txt" );
	}

	public function test_tail_null_byte_path_is_rejected() {
		$this->expectException( \InvalidArgumentException::class );
		$this->files->tail( "wp-load.php\0.log" );
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


