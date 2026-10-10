<?php

// phpcs:ignoreFile

use ExpressionLab\Admin\Pages\Console;
use ExpressionLab\Core\AuditLogger;
use ExpressionLab\Core\Helper;
use ExpressionLab\Core\LanguageEngine;

class AuditLoggerTest extends WP_UnitTestCase {

	private string $log_file;

	public function setUp(): void {
		parent::setUp();
		$this->log_file = Helper::get_storage_dir() . '/' . AuditLogger::get_log_filename();
		$this->remove_log_file();
	}

	public function tearDown(): void {
		$this->remove_log_file();
		unset( $_SERVER['REMOTE_ADDR'] );
		parent::tearDown();
	}

	private function remove_log_file(): void {
		if ( is_dir( $this->log_file ) ) {
			rmdir( $this->log_file );
		} elseif ( file_exists( $this->log_file ) ) {
			unlink( $this->log_file );
		}
	}

	private function log( array $overrides = array() ): bool {
		return AuditLogger::log_evaluation(
			array_merge(
				array(
					'expression'  => '1 + 1',
					'user_id'     => 1,
					'status'      => 'success',
					'duration_ms' => 1.0,
					'ip'          => '203.0.113.10',
				),
				$overrides
			)
		);
	}

	public function test_is_enabled_default_is_true() {
		$this->assertTrue( AuditLogger::is_enabled() );
	}

	public function test_get_salt_returns_non_empty_string() {
		$salt = AuditLogger::get_salt();
		$this->assertIsString( $salt );
		$this->assertNotEmpty( $salt );
	}

	public function test_get_log_filename_deterministic_pattern() {
		$filename = AuditLogger::get_log_filename();
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{16}-audit-\d{4}-\d{2}\.log\.php$/', $filename );
		$this->assertStringEndsWith( '-audit-' . gmdate( 'Y-m' ) . '.log.php', $filename );

		$custom_date_filename = AuditLogger::get_log_filename( '2026-05' );
		$this->assertStringEndsWith( '-audit-2026-05.log.php', $custom_date_filename );

		// Deterministic: the same month always maps to the same file.
		$this->assertSame( $custom_date_filename, AuditLogger::get_log_filename( '2026-05' ) );
	}

	public function test_get_log_filename_partitions_by_month_with_a_shared_secret_prefix() {
		$may  = AuditLogger::get_log_filename( '2026-05' );
		$june = AuditLogger::get_log_filename( '2026-06' );

		$this->assertNotSame( $may, $june );
		$this->assertSame( substr( $may, 0, 16 ), substr( $june, 0, 16 ) );
	}

	public function test_get_client_ip_returns_valid_ip() {
		$_SERVER['REMOTE_ADDR'] = '198.51.100.42';
		$this->assertSame( '198.51.100.42', AuditLogger::get_client_ip() );

		$_SERVER['REMOTE_ADDR'] = 'invalid_ip_string';
		$this->assertSame( '127.0.0.1', AuditLogger::get_client_ip() );
	}

	public function test_log_evaluation_success_creates_protected_file_and_valid_jsonl() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$expression = "Posts.where('post_type', 'post').limit(5).get()";

		$logged = AuditLogger::log_evaluation(
			array(
				'expression'  => $expression,
				'user_id'     => $user_id,
				'status'      => 'success',
				'duration_ms' => 12.34,
				'ip'          => '203.0.113.195',
			)
		);

		$this->assertTrue( $logged );
		$this->assertFileExists( $this->log_file );

		// Verify PHP exit header is present at the very beginning of the file.
		$raw_content = file_get_contents( $this->log_file );
		$this->assertStringStartsWith( "<?php exit; ?>\n", $raw_content );

		// Verify plaintext expression is never stored in the raw file.
		$this->assertStringNotContainsString( 'Posts.where', $raw_content );

		// Decode using read_logs.
		$entries = AuditLogger::read_logs();
		$this->assertCount( 1, $entries );

		$entry = $entries[0];
		$this->assertSame( $user_id, $entry['user_id'] );
		$this->assertSame( '203.0.113.195', $entry['ip'] );
		$this->assertSame( 'evaluate', $entry['action'] );
		$this->assertSame( 'success', $entry['status'] );
		$this->assertSame( 12.34, $entry['duration_ms'] );
		$this->assertSame( hash_hmac( 'sha256', $expression, AuditLogger::get_salt() ), $entry['expression_hash'] );
		$this->assertArrayNotHasKey( 'expression_preview', $entry );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/', $entry['timestamp'] );
	}

	public function test_log_evaluation_defaults_actor_to_current_user() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$this->assertTrue( AuditLogger::log_evaluation( array( 'expression' => '1', 'status' => 'success' ) ) );

		$entries = AuditLogger::read_logs();
		$this->assertSame( $user_id, $entries[0]['user_id'] );
	}

	public function test_log_evaluation_appends_multiple_entries() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		AuditLogger::log_evaluation(
			array(
				'expression'  => '1 + 1',
				'user_id'     => $user_id,
				'status'      => 'success',
				'duration_ms' => 1.5,
			)
		);

		AuditLogger::log_evaluation(
			array(
				'expression'  => 'invalid syntax (',
				'user_id'     => $user_id,
				'status'      => 'error',
				'duration_ms' => 0.8,
			)
		);

		$entries = AuditLogger::read_logs();
		$this->assertCount( 2, $entries );
		$this->assertSame( 'success', $entries[0]['status'] );
		$this->assertSame( 'error', $entries[1]['status'] );
		$this->assertArrayNotHasKey( 'error', $entries[1] );
	}

	public function test_audit_logs_are_strictly_append_only() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		AuditLogger::log_evaluation(
			array(
				'expression'  => 'Posts.get(1)',
				'user_id'     => $user_id,
				'status'      => 'success',
				'duration_ms' => 5.0,
			)
		);

		$initial_content = file_get_contents( $this->log_file );
		$this->assertNotEmpty( $initial_content );

		AuditLogger::log_evaluation(
			array(
				'expression'  => 'Posts.get(2)',
				'user_id'     => $user_id,
				'status'      => 'success',
				'duration_ms' => 4.0,
			)
		);

		$updated_content = file_get_contents( $this->log_file );
		$this->assertStringStartsWith( $initial_content, $updated_content );

		$entries = AuditLogger::read_logs();
		$this->assertCount( 2, $entries );
	}

	public function test_read_logs_returns_empty_array_for_missing_file() {
		$this->assertSame( array(), AuditLogger::read_logs( AuditLogger::get_log_filename( '1999-01' ) ) );
	}

	// ---------------------------------------------------------------------
	// Cryptographic key material.
	// ---------------------------------------------------------------------

	public function test_get_salt_never_uses_the_public_kdf_salt() {
		// EXPRESSION_LAB_ADMIN_SALT is shipped to the browser (Console::get_script_data()).
		$this->assertTrue( defined( 'EXPRESSION_LAB_ADMIN_SALT' ) );
		$this->assertNotSame( (string) EXPRESSION_LAB_ADMIN_SALT, AuditLogger::get_salt() );
	}

	public function test_get_salt_prefers_a_real_auth_key_and_ignores_the_placeholder() {
		$auth_key = defined( 'AUTH_KEY' ) ? (string) AUTH_KEY : '';

		if ( '' !== $auth_key && AuditLogger::DEFAULT_SALT_PHRASE !== $auth_key ) {
			$this->assertSame( $auth_key, AuditLogger::get_salt() );
		} else {
			$this->assertSame( wp_salt( 'nonce' ), AuditLogger::get_salt() );
			$this->assertNotSame( AuditLogger::DEFAULT_SALT_PHRASE, AuditLogger::get_salt() );
		}
	}

	public function test_filename_prefix_is_not_derivable_from_public_values() {
		$prefix = substr( AuditLogger::get_log_filename(), 0, 16 );

		foreach ( array( (string) EXPRESSION_LAB_ADMIN_SALT, (string) EXPRESSION_LAB_ADMIN_PUBLIC_KEY, AuditLogger::DEFAULT_SALT_PHRASE, '' ) as $public_value ) {
			$this->assertNotSame( substr( hash_hmac( 'sha256', 'expressionlab_audit_log', $public_value ), 0, 16 ), $prefix );
		}
	}

	public function invalid_month_provider(): array {
		return array(
			'month 13'           => array( '2026-13' ),
			'month 00'           => array( '2026-00' ),
			'single digit month' => array( '2026-1' ),
			'traversal'          => array( '../../wp-config' ),
			'embedded traversal' => array( '2026-05/../../x' ),
			'garbage'            => array( 'abcd-ef' ),
			'null byte'          => array( "2026-05\0" ),
		);
	}

	/**
	 * @dataProvider invalid_month_provider
	 */
	public function test_get_log_filename_rejects_invalid_months( string $month ) {
		$this->expectException( \InvalidArgumentException::class );
		AuditLogger::get_log_filename( $month );
	}

	public function test_read_logs_rejects_directory_traversal() {
		$this->expectException( \InvalidArgumentException::class );
		AuditLogger::read_logs( '../../wp-config.php' );
	}

	public function test_read_logs_rejects_stream_wrappers() {
		$this->expectException( \InvalidArgumentException::class );
		AuditLogger::read_logs( 'php://filter/resource=' . ABSPATH . 'wp-config.php' );
	}

	public function test_read_logs_skips_malformed_lines() {
		$this->log( array( 'expression' => 'first' ) );
		file_put_contents( $this->log_file, "{not json\n[1,\n", FILE_APPEND );
		$this->log( array( 'expression' => 'second' ) );

		$entries = AuditLogger::read_logs();
		$this->assertCount( 2, $entries );
		$this->assertSame( hash_hmac( 'sha256', 'second', AuditLogger::get_salt() ), $entries[1]['expression_hash'] );
	}

	public function test_ipv6_remote_addr_is_recorded() {
		$_SERVER['REMOTE_ADDR'] = '2001:db8::1';
		$this->assertTrue( $this->log( array( 'ip' => null ) ) );

		$this->assertSame( '2001:db8::1', AuditLogger::read_logs()[0]['ip'] );
	}

	public function test_invalid_remote_addr_falls_back_to_loopback() {
		$_SERVER['REMOTE_ADDR'] = '<script>alert(1)</script>';
		$this->assertTrue( $this->log( array( 'ip' => null ) ) );

		$this->assertSame( '127.0.0.1', AuditLogger::read_logs()[0]['ip'] );
	}

	public function test_spoofable_forwarding_headers_are_ignored() {
		$_SERVER['REMOTE_ADDR']          = '198.51.100.7';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.1';
		$_SERVER['HTTP_CLIENT_IP']       = '10.0.0.2';

		try {
			$this->assertSame( '198.51.100.7', AuditLogger::get_client_ip() );
		} finally {
			unset( $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_CLIENT_IP'] );
		}
	}

	public function test_invalid_ip_override_is_rejected_in_favor_of_remote_addr() {
		$_SERVER['REMOTE_ADDR'] = '198.51.100.8';
		$this->assertTrue( $this->log( array( 'ip' => "1.2.3.4\n{\"forged\":true}" ) ) );

		$raw = file_get_contents( $this->log_file );
		$this->assertStringNotContainsString( 'forged', $raw );
		$this->assertSame( '198.51.100.8', AuditLogger::read_logs()[0]['ip'] );
	}

	public function test_unknown_status_is_normalized() {
		$this->assertTrue( $this->log( array( 'status' => 'pwned"}' ) ) );
		$this->assertSame( 'unknown', AuditLogger::read_logs()[0]['status'] );
	}

	public function test_aborted_status_is_accepted() {
		$this->assertTrue( $this->log( array( 'status' => 'aborted' ) ) );
		$this->assertSame( 'aborted', AuditLogger::read_logs()[0]['status'] );
	}

	public function test_error_message_is_never_persisted() {
		$secret = 'sk_live_9f8e7d6c5b4a';

		$this->assertTrue(
			$this->log(
				array(
					'expression' => "Options.get('{$secret}')",
					'status'     => 'error',
					'error'      => "Variable \"x\" is not valid around position 5 for expression `Options.get('{$secret}')`",
					'message'    => $secret,
				)
			)
		);

		$raw   = file_get_contents( $this->log_file );
		$entry = AuditLogger::read_logs()[0];

		$this->assertStringNotContainsString( $secret, $raw );
		$this->assertArrayNotHasKey( 'error', $entry );
		$this->assertArrayNotHasKey( 'message', $entry );
		$this->assertSame( 'error', $entry['status'] );
	}

	public function test_symfony_exception_messages_embed_the_expression_but_never_reach_the_log() {
		$secret     = 'sk_live_SYMFONY_LEAK_PROBE';
		$expression = "[ Options.get('{$secret}'), error! ]";

		// Threat verification: Symfony interpolates the raw expression into the exception message.
		try {
			LanguageEngine::get()->evaluate( $expression );
			$this->fail( 'Expected a syntax error.' );
		} catch ( \Throwable $e ) {
			$this->assertStringContainsString( $secret, $e->getMessage() );
		}

		// Mitigation: the console recorder only receives the status.
		$recorder = Console::create_audit_recorder( $expression, 1, null, null, microtime( true ) );
		$this->assertTrue( $recorder( 'error' ) );

		$raw = file_get_contents( $this->log_file );
		$this->assertStringNotContainsString( $secret, $raw );
		$this->assertStringNotContainsString( 'Options.get', $raw );
		$this->assertStringNotContainsString( 'position', $raw );
	}

	public function test_zero_cleartext_expression_leakage() {
		$expression = "Options.update('api_key', 'Hello world! s3cr3t_t0k3n')";
		$this->assertTrue( $this->log( array( 'expression' => $expression ) ) );

		$raw   = file_get_contents( $this->log_file );
		$entry = AuditLogger::read_logs()[0];

		foreach ( array( $expression, 'Options', 'api_key', 'Hello world!', 's3cr3t_t0k3n' ) as $fragment ) {
			$this->assertStringNotContainsString( $fragment, $raw );
		}

		$this->assertArrayNotHasKey( 'expression', $entry );
		$this->assertArrayNotHasKey( 'expression_preview', $entry );
		$expected_keys = array( 'timestamp', 'user_id', 'ip', 'action', 'expression_hash', 'status', 'duration_ms' );
		if ( is_multisite() ) {
			$expected_keys[] = 'site_id';
		}

		$this->assertSame(
			$expected_keys,
			array_keys( $entry )
		);
	}

	public function test_expression_hash_supports_forensic_verification() {
		$this->log( array( 'expression' => "Users.get(1).delete()" ) );
		$hash = AuditLogger::read_logs()[0]['expression_hash'];

		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $hash );
		$this->assertSame( hash_hmac( 'sha256', 'Users.get(1).delete()', AuditLogger::get_salt() ), $hash );
		$this->assertNotSame( hash_hmac( 'sha256', 'Users.get(2).delete()', AuditLogger::get_salt() ), $hash );
		// Keyed: an unkeyed SHA-256 dictionary does not match.
		$this->assertNotSame( hash( 'sha256', 'Users.get(1).delete()' ), $hash );
	}

	public function test_console_recorder_attributes_actor_not_context_user() {
		$actor   = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$context = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		$recorder = Console::create_audit_recorder( '1 + 1', $actor, $context, null, microtime( true ) );
		$this->assertTrue( $recorder( 'success' ) );

		$entry = AuditLogger::read_logs()[0];
		$this->assertSame( $actor, $entry['user_id'] );
		$this->assertSame( $context, $entry['context_user_id'] );
	}

	public function test_console_recorder_writes_exactly_one_record() {
		$recorder = Console::create_audit_recorder( '1 + 1', 1, null, null, microtime( true ) );

		$this->assertTrue( $recorder( 'success' ) );
		// Subsequent calls (e.g. the shutdown guard with 'aborted') are no-ops.
		$this->assertFalse( $recorder( 'aborted' ) );
		$this->assertFalse( $recorder( 'error' ) );

		$entries = AuditLogger::read_logs();
		$this->assertCount( 1, $entries );
		$this->assertSame( 'success', $entries[0]['status'] );
	}

	public function test_console_recorder_records_aborted_when_no_outcome_was_logged() {
		$recorder = Console::create_audit_recorder( 'Files.read("x")', 1, null, null, microtime( true ) );

		// Simulates the shutdown function firing after a fatal error.
		$this->assertTrue( $recorder( 'aborted' ) );

		$this->assertSame( 'aborted', AuditLogger::read_logs()[0]['status'] );
	}

	public function test_site_id_is_recorded_on_multisite() {
		$entry = AuditLogger::build_entry( array( 'expression' => '1', 'user_id' => 1, 'site_id' => 7 ), true );
		$this->assertSame( 7, $entry['site_id'] );

		$entry = AuditLogger::build_entry( array( 'expression' => '1', 'user_id' => 1 ), true );
		$this->assertSame( get_current_blog_id(), $entry['site_id'] );
	}

	public function test_site_id_is_omitted_on_single_site() {
		$entry = AuditLogger::build_entry( array( 'expression' => '1', 'user_id' => 1, 'site_id' => 7 ), false );
		$this->assertArrayNotHasKey( 'site_id', $entry );
	}

	public function test_site_id_follows_runtime_multisite_detection() {
		$this->assertTrue( $this->log( array( 'site_id' => 3 ) ) );
		$entry = AuditLogger::read_logs()[0];

		if ( is_multisite() ) {
			$this->assertSame( 3, $entry['site_id'] );
		} else {
			$this->assertArrayNotHasKey( 'site_id', $entry );
		}
	}

	public function test_exit_header_is_written_exactly_once() {
		$this->log();
		$this->log();
		$this->log();

		$raw = file_get_contents( $this->log_file );
		$this->assertStringStartsWith( AuditLogger::EXIT_HEADER, $raw );
		$this->assertSame( 1, substr_count( $raw, '<?php' ) );
	}

	public function test_exit_header_is_written_when_file_exists_but_is_empty() {
		// Reproduces the TOCTOU window: another writer created the file but has not written yet.
		Helper::ensure_storage_dir();
		touch( $this->log_file );
		$this->assertSame( 0, filesize( $this->log_file ) );

		$this->assertTrue( $this->log() );

		$this->assertStringStartsWith( AuditLogger::EXIT_HEADER, file_get_contents( $this->log_file ) );
	}

	public function test_concurrent_writers_never_corrupt_or_reorder_the_header() {
		if ( ! function_exists( 'pcntl_fork' ) || ! function_exists( 'posix_kill' ) ) {
			$this->markTestSkipped( 'pcntl/posix extensions are required for the concurrency test.' );
		}

		// Warm caches (salt, storage dir) before forking.
		AuditLogger::get_log_filename();
		Helper::ensure_storage_dir();

		$workers    = 6;
		$per_worker = 25;
		$pids       = array();

		for ( $w = 0; $w < $workers; $w++ ) {
			$pid = pcntl_fork();
			$this->assertNotSame( -1, $pid, 'Fork failed.' );

			if ( 0 === $pid ) {
				for ( $i = 0; $i < $per_worker; $i++ ) {
					AuditLogger::log_evaluation(
						array(
							'expression'  => "worker {$w} record {$i}",
							'user_id'     => 1,
							'status'      => 'success',
							'duration_ms' => 1.0,
							'ip'          => '192.0.2.1',
						)
					);
				}
				// Terminate immediately, without running PHPUnit/WordPress shutdown routines.
				posix_kill( posix_getpid(), SIGKILL );
			}

			$pids[] = $pid;
		}

		foreach ( $pids as $pid ) {
			pcntl_waitpid( $pid, $status );
		}

		$raw   = file_get_contents( $this->log_file );
		$lines = explode( "\n", rtrim( $raw, "\n" ) );

		$this->assertSame( '<?php exit; ?>', $lines[0], 'The first line must be the exit header.' );
		$this->assertSame( 1, substr_count( $raw, '<?php' ), 'The header must appear exactly once.' );
		$this->assertCount( $workers * $per_worker + 1, $lines );

		foreach ( array_slice( $lines, 1 ) as $line ) {
			$this->assertIsArray( json_decode( $line, true ), 'Interleaved/corrupted line: ' . $line );
		}

		$this->assertCount( $workers * $per_worker, AuditLogger::read_logs() );
	}

	public function test_write_failure_returns_false_without_throwing() {
		// Occupy the log path with a directory so the append cannot open it.
		Helper::ensure_storage_dir();
		mkdir( $this->log_file );

		$previous = ini_set( 'error_log', '/dev/null' );

		try {
			$this->assertFalse( $this->log() );
		} finally {
			ini_set( 'error_log', (string) $previous );
		}
	}
}
