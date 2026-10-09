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
}
