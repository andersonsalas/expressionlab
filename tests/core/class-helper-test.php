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

	/**
	 * @dataProvider provide_compound_stream_wrappers
	 */
	public function test_resolve_safe_path_rejects_compound_stream_wrappers( string $path ) {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Stream wrappers are not permitted' );
		Helper::resolve_safe_path( $path, ABSPATH, false );
	}

	public function provide_compound_stream_wrappers(): array {
		return array(
			'compress.zlib'  => array( 'compress.zlib:///etc/passwd' ),
			'compress.bzip2' => array( 'compress.bzip2:///etc/passwd' ),
			'php://filter'   => array( 'php://filter/read=string.rot13/resource=/etc/passwd' ),
			'data://'        => array( 'data://text/plain;base64,SSBsb3ZlIFBIUAo=' ),
			'file://'        => array( 'file:///etc/passwd' ),
			'PHAR:// (case)' => array( 'PHAR:///tmp/test.phar' ),
		);
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

	public function test_matches_domain_pattern_exact_match() {
		$this->assertTrue( Helper::matches_domain_pattern( 'https://staging.example.com', 'staging.example.com' ) );
		$this->assertTrue( Helper::matches_domain_pattern( 'http://staging.example.com/', 'https://staging.example.com' ) );
		$this->assertTrue( Helper::matches_domain_pattern( 'https://staging.example.com/wp-admin', 'staging.example.com' ) );
		$this->assertFalse( Helper::matches_domain_pattern( 'https://example.com', 'staging.example.com' ) );
	}

	public function test_matches_domain_pattern_wildcard() {
		$this->assertTrue( Helper::matches_domain_pattern( 'https://app.staging.example.com', '*.staging.example.com' ) );
		$this->assertTrue( Helper::matches_domain_pattern( 'https://alpha.beta.staging.example.com', '*.staging.example.com' ) );
		$this->assertFalse( Helper::matches_domain_pattern( 'https://production.example.com', '*.staging.example.com' ) );
		$this->assertTrue( Helper::matches_domain_pattern( 'https://staging-01.example.com', 'staging-*.example.com' ) );
	}

	public function test_matches_domain_pattern_port_handling() {
		$this->assertTrue( Helper::matches_domain_pattern( 'http://localhost:8080/test', 'localhost:8080' ) );
		$this->assertTrue( Helper::matches_domain_pattern( 'http://localhost:8080', 'localhost' ) );
		$this->assertFalse( Helper::matches_domain_pattern( 'http://localhost:9000', 'localhost:8080' ) );
	}

	public function test_matches_url_pattern_path_handling() {
		// Exact subfolder match.
		$this->assertTrue( Helper::matches_url_pattern( 'http://127.0.0.1/expressionlab', '127.0.0.1/expressionlab' ) );
		$this->assertTrue( Helper::matches_url_pattern( 'http://127.0.0.1/expressionlab/', '127.0.0.1/expressionlab' ) );
		$this->assertTrue( Helper::matches_url_pattern( 'http://127.0.0.1/expressionlab', 'http://127.0.0.1/expressionlab/' ) );

		// Mismatch on same domain with different subfolder.
		$this->assertFalse( Helper::matches_url_pattern( 'http://127.0.0.1/otracosa', '127.0.0.1/expressionlab' ) );
		$this->assertFalse( Helper::matches_url_pattern( 'http://127.0.0.1', '127.0.0.1/expressionlab' ) );
		$this->assertFalse( Helper::matches_url_pattern( 'http://127.0.0.1/', '127.0.0.1/expressionlab' ) );

		// Host-only pattern allows any subfolder on that host.
		$this->assertTrue( Helper::matches_url_pattern( 'http://127.0.0.1/expressionlab', '127.0.0.1' ) );
		$this->assertTrue( Helper::matches_url_pattern( 'http://127.0.0.1/otracosa', '127.0.0.1' ) );

		// Subpath nesting.
		$this->assertTrue( Helper::matches_url_pattern( 'http://localhost/site-dev/sub', 'localhost/site-dev' ) );
		$this->assertFalse( Helper::matches_url_pattern( 'http://localhost/site-prod', 'localhost/site-dev' ) );

		// Wildcard in path.
		$this->assertTrue( Helper::matches_url_pattern( 'http://127.0.0.1/staging-01', '127.0.0.1/staging-*' ) );
		$this->assertFalse( Helper::matches_url_pattern( 'http://127.0.0.1/production', '127.0.0.1/staging-*' ) );
	}

	public function test_is_environment_allowed_when_no_staging_url_defined() {
		$this->assertTrue( Helper::is_environment_allowed() );
	}

	public function test_matches_url_pattern_scheme_less_home_url() {
		$this->assertTrue( Helper::matches_url_pattern( '127.0.0.1/site', '127.0.0.1/site' ) );
		$this->assertTrue( Helper::matches_url_pattern( 'staging.example.com', 'staging.example.com' ) );
		$this->assertTrue( Helper::matches_url_pattern( 'localhost:8080/app', 'localhost:8080/app' ) );
		$this->assertTrue( Helper::matches_url_pattern( '//staging.example.com/wp', 'staging.example.com' ) );
		$this->assertFalse( Helper::matches_url_pattern( '127.0.0.1/other', '127.0.0.1/site' ) );
	}

	public function test_matches_url_pattern_scheme_less_home_cannot_smuggle_wildcard_host() {
		// Previously the whole unparsed string was used as host, letting `*` swallow the path.
		$this->assertFalse( Helper::matches_url_pattern( 'evil.com/x.example.com', '*.example.com' ) );
		$this->assertFalse( Helper::matches_url_pattern( 'https://evil.com/x.example.com', '*.example.com' ) );
	}

	public function test_matches_url_pattern_sibling_path_collisions_are_rejected() {
		$this->assertFalse( Helper::matches_url_pattern( 'http://localhost/site-production', 'localhost/site' ) );
		$this->assertFalse( Helper::matches_url_pattern( 'http://localhost/site2', 'localhost/site' ) );
		$this->assertFalse( Helper::matches_url_pattern( 'http://localhost/app-backup', 'localhost/app' ) );
		$this->assertTrue( Helper::matches_url_pattern( 'http://localhost/app/backup', 'localhost/app' ) );
		$this->assertTrue( Helper::matches_url_pattern( 'http://127.0.0.1/expressionlab/subpage', '127.0.0.1/expressionlab' ) );
	}

	public function test_matches_url_pattern_trailing_slash_permutations() {
		$homes    = array( 'http://localhost/app', 'http://localhost/app/', 'http://localhost/app//' );
		$patterns = array( 'localhost/app', 'localhost/app/', 'localhost//app/', 'http://localhost/app/' );
		foreach ( $homes as $home ) {
			foreach ( $patterns as $pattern ) {
				$this->assertTrue( Helper::matches_url_pattern( $home, $pattern ), "$home vs $pattern" );
			}
		}
		$this->assertTrue( Helper::matches_url_pattern( 'https://staging.example.com/', 'staging.example.com/' ) );
		$this->assertTrue( Helper::matches_url_pattern( 'https://staging.example.com', 'staging.example.com/' ) );
	}

	public function test_matches_url_pattern_wildcards() {
		// Subdomain wildcard requires at least the dot separator: the apex does not match.
		$this->assertFalse( Helper::matches_url_pattern( 'https://staging.example.com', '*.staging.example.com' ) );

		// Multiple wildcard segments.
		$this->assertTrue( Helper::matches_url_pattern( 'https://app-01.eu.staging.example.com', '*-*.*.staging.example.com' ) );
		$this->assertTrue( Helper::matches_url_pattern( 'http://127.0.0.1/clients/acme/staging-2', '127.0.0.1/clients/*/staging-*' ) );
		$this->assertFalse( Helper::matches_url_pattern( 'http://127.0.0.1/clients/acme/prod', '127.0.0.1/clients/*/staging-*' ) );

		// Path wildcards are segment-bound: `*` never crosses a `/`.
		$this->assertFalse( Helper::matches_url_pattern( 'http://localhost/prod/x-dev', 'localhost/*-dev' ) );
		$this->assertTrue( Helper::matches_url_pattern( 'http://localhost/x-dev/sub', 'localhost/*-dev' ) );

		// Wildcard path segment requires a non-root path.
		$this->assertTrue( Helper::matches_url_pattern( 'http://localhost/anything', 'localhost/*' ) );
		$this->assertFalse( Helper::matches_url_pattern( 'http://localhost', 'localhost/*' ) );
	}

	public function test_matches_url_pattern_is_case_insensitive() {
		$this->assertTrue( Helper::matches_url_pattern( 'https://staging.example.com/site', 'STAGING.EXAMPLE.COM/Site' ) );
		$this->assertTrue( Helper::matches_url_pattern( 'HTTPS://Staging.Example.COM/SITE/', 'staging.example.com/site' ) );
		$this->assertTrue( Helper::matches_url_pattern( 'https://APP.staging.example.com', '*.Staging.Example.com' ) );
	}

	public function test_matches_url_pattern_ports() {
		$this->assertTrue( Helper::matches_url_pattern( 'http://localhost:8080', 'localhost:8080' ) );
		$this->assertFalse( Helper::matches_url_pattern( 'http://localhost:9000', 'localhost:8080' ) );
		$this->assertFalse( Helper::matches_url_pattern( 'http://localhost', 'localhost:8080' ) );

		// Port-less patterns are port-agnostic.
		$this->assertTrue( Helper::matches_url_pattern( 'http://localhost:8080', 'localhost' ) );

		// Implicit default ports.
		$this->assertTrue( Helper::matches_url_pattern( 'https://staging.example.com', 'staging.example.com:443' ) );
		$this->assertTrue( Helper::matches_url_pattern( 'http://staging.example.com', 'staging.example.com:80' ) );
		$this->assertFalse( Helper::matches_url_pattern( 'http://staging.example.com', 'staging.example.com:443' ) );

		// IPv6 literals.
		$this->assertTrue( Helper::matches_url_pattern( 'http://[::1]:8080/', '[::1]' ) );
		$this->assertTrue( Helper::matches_url_pattern( 'http://[::1]:8080/', '[::1]:8080' ) );
		$this->assertFalse( Helper::matches_url_pattern( 'http://[::1]:8081/', '[::1]:8080' ) );

		// Port with path.
		$this->assertTrue( Helper::matches_url_pattern( 'http://localhost:8080/site/sub', 'localhost:8080/site' ) );
		$this->assertFalse( Helper::matches_url_pattern( 'http://localhost:8080/other', 'localhost:8080/site' ) );
	}

	public function test_matches_url_pattern_ignores_query_and_fragment() {
		$this->assertTrue( Helper::matches_url_pattern( 'https://staging.example.com/site?x=1#top', 'staging.example.com/site' ) );
		$this->assertFalse( Helper::matches_url_pattern( 'https://staging.example.com/?p=/site', 'staging.example.com/site' ) );
	}

	public function test_matches_url_pattern_userinfo_cannot_spoof_host() {
		$this->assertTrue( Helper::matches_url_pattern( 'https://evil.com@staging.example.com', 'staging.example.com' ) );
		$this->assertFalse( Helper::matches_url_pattern( 'https://staging.example.com@evil.com', 'staging.example.com' ) );
	}

	public function test_matches_url_pattern_malformed_or_empty_inputs() {
		$this->assertFalse( Helper::matches_url_pattern( '', 'staging.example.com' ) );
		$this->assertFalse( Helper::matches_url_pattern( 'https://staging.example.com', '' ) );
		$this->assertFalse( Helper::matches_url_pattern( '   ', '   ' ) );
		$this->assertFalse( Helper::matches_url_pattern( 'https://staging.example.com', '/site' ) );
		$this->assertFalse( Helper::matches_url_pattern( 'https://staging.example.com', 'a:b:c' ) );
		$this->assertFalse( Helper::matches_url_pattern( 'https://staging.example.com', 'staging.example.com:port' ) );
		$this->assertFalse( Helper::matches_url_pattern( 'http://', 'staging.example.com' ) );
		$this->assertFalse( Helper::matches_url_pattern( 'https://staging.example.com', "staging.example.com'); phpinfo(); //" ) );
	}

	public function test_check_environment_decision_table() {
		// Guardrail disabled.
		$this->assertTrue( Helper::check_environment( null, 'https://prod.example.com' ) );
		$this->assertTrue( Helper::check_environment( '', 'https://prod.example.com' ) );
		$this->assertTrue( Helper::check_environment( '   ', 'https://prod.example.com' ) );
		$this->assertTrue( Helper::check_environment( array( 'x' ), 'https://prod.example.com' ) );

		// Guardrail enabled: match / mismatch.
		$this->assertTrue( Helper::check_environment( 'staging.example.com', 'https://staging.example.com' ) );
		$this->assertFalse( Helper::check_environment( 'staging.example.com', 'https://prod.example.com' ) );

		// Fail closed when the home URL is missing.
		$this->assertFalse( Helper::check_environment( 'staging.example.com', '' ) );
		$this->assertFalse( Helper::check_environment( 'staging.example.com', '   ' ) );
	}

	public function test_is_environment_allowed_reads_home_option_not_request_headers() {
		$original_host        = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : null;
		$_SERVER['HTTP_HOST'] = 'attacker.example.net';

		// The constant is null in the test bootstrap, so the guardrail is disabled regardless of headers.
		$this->assertTrue( Helper::is_environment_allowed() );

		// The evaluator only depends on its explicit inputs (database home URL), not on headers.
		$this->assertFalse( Helper::check_environment( 'attacker.example.net', (string) get_option( 'home' ) ) );

		if ( null === $original_host ) {
			unset( $_SERVER['HTTP_HOST'] );
		} else {
			$_SERVER['HTTP_HOST'] = $original_host;
		}
	}

	public function test_get_storage_dir_returns_expected_path() {
		$dir = Helper::get_storage_dir();
		$this->assertIsString( $dir );
		$this->assertSame( wp_normalize_path( WP_CONTENT_DIR . '/expressionlab' ), $dir );
	}

	public function test_ensure_storage_dir_creates_directory_and_security_files() {
		$dir = Helper::get_storage_dir();
		$this->assertTrue( Helper::ensure_storage_dir() );
		$this->assertDirectoryExists( $dir );

		$index_file = $dir . '/index.html';
		$this->assertFileExists( $index_file );

		$htaccess_file = $dir . '/.htaccess';
		$this->assertFileExists( $htaccess_file );
		$htaccess_content = file_get_contents( $htaccess_file );
		$this->assertStringContainsString( 'Require all denied', $htaccess_content );
	}

	public function test_append_storage_file_writes_content_and_appends() {
		$test_file = 'test-audit-' . uniqid() . '.log';
		$dir       = Helper::get_storage_dir();
		$full_path = $dir . '/' . $test_file;

		Helper::append_storage_file( $test_file, "line1\n" );
		$this->assertFileExists( $full_path );
		$this->assertSame( "line1\n", file_get_contents( $full_path ) );

		Helper::append_storage_file( $test_file, "line2\n" );
		$this->assertSame( "line1\nline2\n", file_get_contents( $full_path ) );

		if ( file_exists( $full_path ) ) {
			unlink( $full_path );
		}
	}

	public function test_write_storage_file_overwrites_content() {
		$test_file = 'test-write-' . uniqid() . '.txt';
		$dir       = Helper::get_storage_dir();
		$full_path = $dir . '/' . $test_file;

		Helper::write_storage_file( $test_file, 'initial' );
		$this->assertSame( 'initial', file_get_contents( $full_path ) );

		Helper::write_storage_file( $test_file, 'replaced' );
		$this->assertSame( 'replaced', file_get_contents( $full_path ) );

		if ( file_exists( $full_path ) ) {
			unlink( $full_path );
		}
	}

	public function test_append_storage_file_blocks_directory_traversal() {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Access denied' );
		Helper::append_storage_file( '../evil.php', 'malicious content' );
	}

	public function storage_traversal_provider(): array {
		return array(
			'parent'             => array( '../evil.log' ),
			'wp-config'          => array( '../../wp-config.php' ),
			'nested traversal'   => array( 'subdir/../../../evil.php' ),
			'absolute outside'   => array( '/etc/passwd' ),
			'phar wrapper'       => array( 'phar://archive.phar/file' ),
			'php filter wrapper' => array( 'php://filter/resource=index.php' ),
			'null byte'          => array( "audit.log\0.php" ),
			'storage dir itself' => array( '' ),
			'storage dir dot'    => array( '.' ),
		);
	}

	/**
	 * @dataProvider storage_traversal_provider
	 */
	public function test_append_storage_file_rejects_unsafe_targets( string $target ) {
		$this->expectException( \InvalidArgumentException::class );
		Helper::append_storage_file( $target, 'payload' );
	}

	/**
	 * @dataProvider storage_traversal_provider
	 */
	public function test_write_storage_file_rejects_unsafe_targets( string $target ) {
		$this->expectException( \InvalidArgumentException::class );
		Helper::write_storage_file( $target, 'hack' );
	}

	public function guard_file_provider(): array {
		return array(
			'htaccess'          => array( '.htaccess' ),
			'index'             => array( 'index.html' ),
			'index uppercase'   => array( 'INDEX.HTML' ),
			'htaccess via dots' => array( 'sub/../.htaccess' ),
		);
	}

	/**
	 * @dataProvider guard_file_provider
	 */
	public function test_storage_guard_files_cannot_be_modified( string $target ) {
		Helper::ensure_storage_dir();
		$htaccess_before = file_get_contents( Helper::get_storage_dir() . '/.htaccess' );

		try {
			Helper::append_storage_file( $target, "\nAllow from all\n" );
			$this->fail( 'Appending to a guard file must be rejected.' );
		} catch ( \InvalidArgumentException $e ) {
			$this->assertStringContainsString( 'Access denied', $e->getMessage() );
		}

		try {
			Helper::write_storage_file( $target, "Allow from all\n" );
			$this->fail( 'Overwriting a guard file must be rejected.' );
		} catch ( \InvalidArgumentException $e ) {
			$this->assertStringContainsString( 'Access denied', $e->getMessage() );
		}

		$this->assertSame( $htaccess_before, file_get_contents( Helper::get_storage_dir() . '/.htaccess' ) );
	}

	public function test_write_storage_file_refuses_to_overwrite_append_only_files() {
		$test_file = 'test-worm-' . uniqid() . '.log.php';
		$full_path = Helper::get_storage_dir() . '/' . $test_file;

		try {
			Helper::append_storage_file( $test_file, "record-1\n", "<?php exit; ?>\n" );

			try {
				Helper::write_storage_file( $test_file, '' );
				$this->fail( 'Append-only files must not be truncated.' );
			} catch ( \InvalidArgumentException $e ) {
				$this->assertStringContainsString( 'Append-only', $e->getMessage() );
			}

			$this->assertSame( "<?php exit; ?>\nrecord-1\n", file_get_contents( $full_path ) );
		} finally {
			if ( file_exists( $full_path ) ) {
				unlink( $full_path );
			}
		}
	}

	public function test_append_storage_file_writes_header_only_for_new_or_empty_files() {
		$test_file = 'test-header-' . uniqid() . '.log';
		$full_path = Helper::get_storage_dir() . '/' . $test_file;

		try {
			Helper::append_storage_file( $test_file, "a\n", "HEADER\n" );
			Helper::append_storage_file( $test_file, "b\n", "HEADER\n" );
			$this->assertSame( "HEADER\na\nb\n", file_get_contents( $full_path ) );

			// An existing but empty file still receives the header.
			file_put_contents( $full_path, '' );
			Helper::append_storage_file( $test_file, "c\n", "HEADER\n" );
			$this->assertSame( "HEADER\nc\n", file_get_contents( $full_path ) );
		} finally {
			if ( file_exists( $full_path ) ) {
				unlink( $full_path );
			}
		}
	}

	public function test_write_storage_file_is_atomic_and_leaves_no_temporary_files() {
		$test_file = 'test-atomic-' . uniqid() . '.json';
		$dir       = Helper::get_storage_dir();
		$full_path = $dir . '/' . $test_file;

		try {
			Helper::write_storage_file( $test_file, str_repeat( 'x', 4096 ) );
			Helper::write_storage_file( $test_file, 'final' );

			$this->assertSame( 'final', file_get_contents( $full_path ) );
			$this->assertSame( array(), glob( $full_path . '.tmp.*' ) );
		} finally {
			if ( file_exists( $full_path ) ) {
				unlink( $full_path );
			}
		}
	}

	public function test_htaccess_supports_apache_22_and_24() {
		Helper::ensure_storage_dir();
		$htaccess = file_get_contents( Helper::get_storage_dir() . '/.htaccess' );

		$this->assertStringContainsString( '<IfModule mod_authz_core.c>', $htaccess );
		$this->assertStringContainsString( 'Require all denied', $htaccess );
		$this->assertStringContainsString( '<IfModule !mod_authz_core.c>', $htaccess );
		$this->assertStringContainsString( 'Deny from all', $htaccess );
	}

	public function test_ensure_storage_dir_restores_deleted_guard_files() {
		$dir = Helper::get_storage_dir();
		Helper::ensure_storage_dir();

		unlink( $dir . '/.htaccess' );
		unlink( $dir . '/index.html' );

		$this->assertTrue( Helper::ensure_storage_dir() );
		$this->assertFileExists( $dir . '/.htaccess' );
		$this->assertFileExists( $dir . '/index.html' );
		$this->assertSame( '', file_get_contents( $dir . '/index.html' ) );
	}

	public function test_ensure_storage_dir_fails_closed_when_guards_cannot_be_written() {
		if ( function_exists( 'posix_geteuid' ) && 0 === posix_geteuid() ) {
			$this->markTestSkipped( 'File permissions are not enforced for root.' );
		}

		$dir = Helper::get_storage_dir();
		Helper::ensure_storage_dir();
		$perms = fileperms( $dir ) & 0777;
		unlink( $dir . '/index.html' );
		chmod( $dir, 0555 );

		try {
			Helper::ensure_storage_dir();
			$this->fail( 'Expected a RuntimeException when the directory cannot be secured.' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'Failed to secure storage directory', $e->getMessage() );
		} finally {
			chmod( $dir, $perms );
			Helper::ensure_storage_dir();
		}
	}

	public function test_append_storage_file_never_writes_into_an_unsecured_directory() {
		if ( function_exists( 'posix_geteuid' ) && 0 === posix_geteuid() ) {
			$this->markTestSkipped( 'File permissions are not enforced for root.' );
		}

		$dir       = Helper::get_storage_dir();
		$test_file = 'test-unsecured-' . uniqid() . '.log';
		Helper::ensure_storage_dir();
		$perms = fileperms( $dir ) & 0777;
		unlink( $dir . '/.htaccess' );
		chmod( $dir, 0555 );

		try {
			Helper::append_storage_file( $test_file, 'payload' );
			$this->fail( 'Expected a RuntimeException.' );
		} catch ( \RuntimeException $e ) {
			$this->assertFileDoesNotExist( $dir . '/' . $test_file );
		} finally {
			chmod( $dir, $perms );
			Helper::ensure_storage_dir();
		}
	}
}
