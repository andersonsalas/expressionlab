<?php
/**
 * This file is part of the Expression Lab plugin.
 *
 * (c) Anderson Salas <github@andersonsalas.com>
 *
 * See the LICENSE file for license information.
 *
 * @package ExpressionLab
 */

namespace ExpressionLab\Core;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * Audit Logger class.
 *
 * Provides background audit logging for evaluated expressions, recording
 * administrator IDs, client IP addresses, HMAC hashes of expressions,
 * and execution telemetry into protected log files.
 *
 * @since 0.3.0
 *
 * @package ExpressionLab
 */
class AuditLogger {

	/**
	 * Protective header written as the very first line of every log file.
	 *
	 * If the file is ever requested over HTTP and routed through the PHP
	 * handler, execution terminates before any record is emitted.
	 *
	 * @since 0.3.0
	 *
	 * @var string
	 */
	const EXIT_HEADER = "<?php exit; ?>\n";

	/**
	 * Allowed values for the `status` field.
	 *
	 * - `success`: The expression evaluated without throwing.
	 * - `error`:   The expression threw (the exception message is never persisted).
	 * - `aborted`: The request terminated (fatal error, timeout, exit) before a
	 *              success or error outcome could be recorded.
	 *
	 * @since 0.3.0
	 *
	 * @var string[]
	 */
	const STATUSES = array( 'success', 'error', 'aborted' );

	/**
	 * Placeholder value shipped in the stock wp-config-sample.php salts.
	 *
	 * @since 0.3.0
	 *
	 * @var string
	 */
	const DEFAULT_SALT_PHRASE = 'put your unique phrase here';

	/**
	 * Checks if audit logging is enabled.
	 *
	 * Logging is enabled by default and can be suppressed only via the
	 * `EXPRESSION_LAB_AUDIT_LOG_ENABLED` constant in wp-config.php.
	 *
	 * @since 0.3.0
	 *
	 * @return bool True if audit logging is enabled, false otherwise.
	 */
	public static function is_enabled(): bool {
		if ( defined( 'EXPRESSION_LAB_AUDIT_LOG_ENABLED' ) && false === constant( 'EXPRESSION_LAB_AUDIT_LOG_ENABLED' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Retrieves the secret key used for hashing expressions and log filenames.
	 *
	 * The key is derived exclusively from server-side secrets (`AUTH_KEY` or
	 * `wp_salt( 'nonce' )`). The Argon2id salt `EXPRESSION_LAB_ADMIN_SALT` is
	 * deliberately NOT used: it is a public KDF parameter that is shipped to the
	 * browser, so using it would make log filenames predictable and expression
	 * hashes brute-forceable offline.
	 *
	 * @since 0.3.0
	 *
	 * @return string Secret HMAC key.
	 */
	public static function get_salt(): string {
		if ( defined( 'AUTH_KEY' ) ) {
			$auth_key = (string) constant( 'AUTH_KEY' );

			if ( '' !== $auth_key && self::DEFAULT_SALT_PHRASE !== $auth_key ) {
				return $auth_key;
			}
		}

		if ( function_exists( 'wp_salt' ) ) {
			return wp_salt( 'nonce' );
		}

		return 'expressionlab_audit_salt';
	}

	/**
	 * Generates the deterministic log filename for a given month.
	 *
	 * Prepends an HMAC-derived prefix to prevent filename guessing or enumeration attacks,
	 * partitioning logs by month (YYYY-MM) with a .log.php extension for direct-download protection.
	 *
	 * @since 0.3.0
	 *
	 * @param string|null $date_month Optional month string in 'Y-m' format. Defaults to current month (UTC).
	 * @return string Protected log filename.
	 * @throws \InvalidArgumentException If `$date_month` is not a valid 'Y-m' month.
	 */
	public static function get_log_filename( ?string $date_month = null ): string {
		$month = ! empty( $date_month ) ? $date_month : gmdate( 'Y-m' );

		if ( ! preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $month ) ) {
			throw new \InvalidArgumentException( 'Invalid audit log month. Expected format: YYYY-MM.' );
		}

		$salt      = self::get_salt();
		$salt_hash = substr( hash_hmac( 'sha256', 'expressionlab_audit_log', $salt ), 0, 16 );

		return sprintf( '%s-audit-%s.log.php', $salt_hash, $month );
	}

	/**
	 * Resolves the client IP address from the request environment safely.
	 *
	 * Relies on REMOTE_ADDR and validates against IPv4/IPv6 filters to avoid header spoofing.
	 *
	 * @since 0.3.0
	 *
	 * @return string Validated IP address or '127.0.0.1' fallback.
	 */
	public static function get_client_ip(): string {
		$raw_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		if ( '' !== $raw_ip && false !== filter_var( $raw_ip, FILTER_VALIDATE_IP ) ) {
			return $raw_ip;
		}

		return '127.0.0.1';
	}

	/**
	 * Builds the audit record for an expression evaluation event.
	 *
	 * The record never contains the expression in clear text, a preview of it,
	 * or any exception message: the only expression indicator is its HMAC.
	 *
	 * @since 0.3.0
	 * @internal
	 *
	 * @param array     $params    Event parameters. See {@see AuditLogger::log_evaluation()}.
	 * @param bool|null $multisite Optional. Overrides multisite detection (testing seam). Default null (auto).
	 * @return array<string, mixed> Audit record.
	 */
	public static function build_entry( array $params, ?bool $multisite = null ): array {
		$expression  = isset( $params['expression'] ) ? (string) $params['expression'] : '';
		$user_id     = isset( $params['user_id'] ) ? (int) $params['user_id'] : get_current_user_id();
		$status      = isset( $params['status'] ) ? sanitize_key( $params['status'] ) : '';
		$status      = in_array( $status, self::STATUSES, true ) ? $status : 'unknown';
		$duration_ms = isset( $params['duration_ms'] ) ? max( 0.0, (float) $params['duration_ms'] ) : 0.0;
		$ip          = isset( $params['ip'] ) ? trim( (string) $params['ip'] ) : '';
		$ip          = ( '' !== $ip && false !== filter_var( $ip, FILTER_VALIDATE_IP ) ) ? $ip : self::get_client_ip();

		$entry = array(
			'timestamp'       => gmdate( 'c' ),
			'user_id'         => $user_id,
			'ip'              => $ip,
			'action'          => 'evaluate',
			'expression_hash' => hash_hmac( 'sha256', $expression, self::get_salt() ),
			'status'          => $status,
			'duration_ms'     => round( $duration_ms, 2 ),
		);

		// The execution-context user selected in the console (may differ from the actor).
		if ( isset( $params['context_user_id'] ) && null !== $params['context_user_id'] ) {
			$entry['context_user_id'] = (int) $params['context_user_id'];
		}

		if ( null === $multisite ? is_multisite() : $multisite ) {
			$entry['site_id'] = isset( $params['site_id'] ) && null !== $params['site_id'] ? (int) $params['site_id'] : get_current_blog_id();
		}

		return $entry;
	}

	/**
	 * Records an expression evaluation event in the audit log.
	 *
	 * Calculates an HMAC SHA-256 hash of the expression, formats the record as a
	 * JSON Lines entry and appends it atomically to the current month's log. The
	 * protective PHP exit header is written by the storage layer, under the file
	 * lock, whenever the file is new or empty.
	 *
	 * This method never throws: any failure is reported to `error_log` (when
	 * `WP_DEBUG` is enabled) and `false` is returned, so a logging failure can
	 * never break the console response.
	 *
	 * @since 0.3.0
	 *
	 * @param array $params {
	 *     Event parameters.
	 *
	 *     @type string      $expression      The evaluated expression string (hashed, never stored).
	 *     @type int|null    $user_id         Authenticated actor (administrator) user ID. Defaults to the current user.
	 *     @type int|null    $context_user_id Optional execution-context user ID selected in the console.
	 *     @type string|null $status          Execution status ('success', 'error' or 'aborted').
	 *     @type float|null  $duration_ms     Execution duration in milliseconds.
	 *     @type string|null $ip              Optional client IP address override (validated).
	 *     @type int|null    $site_id         Optional blog/site ID for multisite installations.
	 * }
	 * @return bool True if logged successfully, false if disabled or write failed.
	 */
	public static function log_evaluation( array $params ): bool {
		if ( ! self::is_enabled() ) {
			return false;
		}

		try {
			$line = wp_json_encode( self::build_entry( $params ) );

			if ( false === $line ) {
				self::report_failure( 'Failed to encode audit record.' );
				return false;
			}

			Helper::append_storage_file( self::get_log_filename(), $line . "\n", self::EXIT_HEADER );

			return true;
		} catch ( \Throwable $e ) {
			self::report_failure( $e->getMessage() );
			return false;
		}
	}

	/**
	 * Reports an audit logging failure to the PHP error log when debugging is enabled.
	 *
	 * The message never contains the expression: storage-layer messages only
	 * include file paths, and record encoding failures are reported generically.
	 *
	 * @since 0.3.0
	 *
	 * @param string $message Failure description.
	 * @return void
	 */
	private static function report_failure( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'Expression Lab Audit Log write error: %s', $message ) );
		}
	}

	/**
	 * Reads and decodes audit log entries from a log file.
	 *
	 * Skips the protective PHP exit header and any malformed line, and parses
	 * each remaining JSON line. The filename is confined to the storage directory.
	 *
	 * @since 0.3.0
	 *
	 * @param string|null $filename Relative filename or null for current month's log.
	 * @return array Decoded log entries.
	 * @throws \InvalidArgumentException If the filename escapes the storage directory.
	 */
	public static function read_logs( ?string $filename = null ): array {
		$filename  = ! empty( $filename ) ? $filename : self::get_log_filename();
		$file_path = Helper::resolve_storage_file_path( $filename );

		if ( ! is_file( $file_path ) || ! is_readable( $file_path ) ) {
			return array();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = file_get_contents( $file_path );
		if ( false === $content ) {
			return array();
		}

		$lines   = explode( "\n", trim( $content ) );
		$entries = array();

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line || str_starts_with( $line, '<?php' ) ) {
				continue;
			}

			$decoded = json_decode( $line, true );
			if ( is_array( $decoded ) ) {
				$entries[] = $decoded;
			}
		}

		return $entries;
	}
}
