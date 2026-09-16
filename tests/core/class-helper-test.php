<?php

// phpcs:ignoreFile

use ExpressionLab\Core\Helper;

class HelperTest extends WP_UnitTestCase {
	public function test_resolve_safe_path_existing_file_default_base() {
		$resolved = Helper::resolve_safe_path( 'wp-login.php', ABSPATH, true );
		$this->assertIsString( $resolved );
		$this->assertTrue( str_starts_with( $resolved, wp_normalize_path( realpath( ABSPATH ) ) ) );
		$this->assertFileExists( $resolved );
	}

	public function test_resolve_safe_path_non_existent_file_when_must_exist_false() {
		$resolved = Helper::resolve_safe_path( 'wp-content/uploads/future-file.txt', ABSPATH, false );
		$this->assertIsString( $resolved );
		$this->assertTrue( str_starts_with( $resolved, wp_normalize_path( realpath( ABSPATH ) ) ) );
	}

	public function test_resolve_safe_path_non_existent_file_when_must_exist_true_throws() {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'File or directory does not exist' );
		Helper::resolve_safe_path( 'wp-content/non_existent_file_12345.xyz', ABSPATH, true );
	}

	public function test_resolve_safe_path_traversal_blocked_default_base() {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Access denied' );
		Helper::resolve_safe_path( 'wp-content/../../etc/passwd', ABSPATH, false );
	}

	public function test_resolve_safe_path_absolute_path_outside_base_blocked() {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Access denied' );
		Helper::resolve_safe_path( '/etc/passwd', ABSPATH, false );
	}

	public function test_resolve_safe_path_custom_base_dir() {
		$custom_base = WP_CONTENT_DIR;
		$resolved    = Helper::resolve_safe_path( 'expressionlab/test-db.mmdb', $custom_base, false );

		$this->assertIsString( $resolved );
		$this->assertTrue( str_starts_with( $resolved, wp_normalize_path( realpath( $custom_base ) ?: $custom_base ) ) );
	}

	public function test_resolve_safe_path_custom_base_dir_traversal_blocked() {
		$custom_base = WP_CONTENT_DIR;
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Access denied' );
		Helper::resolve_safe_path( '../wp-config.php', $custom_base, false );
	}

	public function test_resolve_safe_path_rejects_stream_wrappers() {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Stream wrappers are not permitted' );
		Helper::resolve_safe_path( 'phar://archive.zip/file.txt', ABSPATH, false );
	}

	public function test_resolve_safe_path_rejects_null_bytes() {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'invalid characters' );
		Helper::resolve_safe_path( "wp-content/test\0.php", ABSPATH, false );
	}

	public function test_format_markdown_code_span() {
		$this->assertSame( '`foo`', Helper::format_markdown_code_span( 'foo' ) );
		$this->assertSame( '`` `foo` ``', Helper::format_markdown_code_span( '`foo`' ) );
		$this->assertSame( '`  foo  `', Helper::format_markdown_code_span( ' foo ' ) );
	}
}
