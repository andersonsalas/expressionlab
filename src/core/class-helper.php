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
 * Plugin helper class.
 *
 * Contains static utility and helper methods for configuration, authorization,
 * serialization, and formatting across the plugin.
 *
 * @since 0.0.1
 *
 * @package ExpressionLab
 */
class Helper {
	/**
	 * Checks if the plugin is fully configured.
	 *
	 * Verifies that all required constants for plugin operation are defined
	 * and non-empty.
	 *
	 * @since 0.0.1
	 *
	 * @return bool True if the plugin is fully configured, false otherwise.
	 */
	public static function plugin_is_fully_configured(): bool {
		return defined( 'EXPRESSION_LAB_ADMIN_USER_ID' )
			&& ! empty( constant( 'EXPRESSION_LAB_ADMIN_USER_ID' ) )
			&& defined( 'EXPRESSION_LAB_ADMIN_PUBLIC_KEY' )
			&& ! empty( constant( 'EXPRESSION_LAB_ADMIN_PUBLIC_KEY' ) )
			&& defined( 'EXPRESSION_LAB_ADMIN_SALT' )
			&& ! empty( constant( 'EXPRESSION_LAB_ADMIN_SALT' ) );
	}

	/**
	 * Checks if Expression Lab internal debug mode is enabled.
	 *
	 * Debug mode is controlled deterministically via the EXPRESSION_LAB_DEBUG_MODE
	 * constant in wp-config.php.
	 *
	 * @since 0.0.1
	 *
	 * @return bool True if debug mode is enabled, false otherwise.
	 */
	public static function is_debug_mode(): bool {
		return defined( 'EXPRESSION_LAB_DEBUG_MODE' ) && true === constant( 'EXPRESSION_LAB_DEBUG_MODE' );
	}

	/**
	 * Checks if the current admin screen belongs to Expression Lab.
	 *
	 * @since 0.0.1
	 *
	 * @return bool True if on Expression Lab admin screen, false otherwise.
	 */
	public static function is_current_screen(): bool {
		if ( ! is_admin() ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['page'] ) && 'expressionlab' === sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return true;
		}

		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && ! empty( $screen->id ) && false !== strpos( $screen->id, 'page_expressionlab' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if the current user is the Expression Lab admin user.
	 *
	 * Verifies user capabilities and ensures the current logged-in user matches
	 * the administrator ID defined by the `EXPRESSION_LAB_ADMIN_USER_ID` constant.
	 *
	 * @since 0.0.1
	 *
	 * @return bool True if the current user is the Expression Lab admin, false otherwise.
	 */
	public static function user_is_expressionlab_admin(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			if ( ! current_user_can( 'manage_network_options' ) ) {
				return false;
			}
		} elseif ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$allowed_admin_id = defined( 'EXPRESSION_LAB_ADMIN_USER_ID' )
			? (int) constant( 'EXPRESSION_LAB_ADMIN_USER_ID' )
			: null;

		return ! empty( wp_get_current_user()->ID ) && wp_get_current_user()->ID === $allowed_admin_id;
	}

	/**
	 * Serializes a value, validating permitted types.
	 *
	 * Ensures that only scalar values, arrays, null, or stdClass instances are
	 * serialized, verifying round-trip serialization against unauthorized classes.
	 *
	 * @since 0.0.1
	 *
	 * @param mixed $value The value to serialize. Must be a scalar, array, stdClass object, or null.
	 * @return mixed The input value after serialization validation.
	 *
	 * @throws \UnexpectedValueException If the provided value contains disallowed classes or fails round-trip serialization.
	 */
	public static function strict_serialize( $value ) {
		// Quick test to ensure only allowed types are serialized.
		if ( is_object( $value ) && ! ( $value instanceof \stdClass ) ) {
			throw new \UnexpectedValueException( 'Unsafe serialization detected.' );
		}

		// Now we perform a test serialization and unserialization to ensure that the value can be safely round-tripped without issues.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		self::strict_unserialize( @serialize( $value ) );

		return $value;
	}

	/**
	 * Unserializes a string, allowing only stdClass objects.
	 *
	 * Performs inspection of the unserialized data to ensure that only stdClass
	 * instances are permitted, rejecting any incomplete class definitions.
	 *
	 * @since 0.0.1
	 *
	 * @param string $value The serialized string to unserialize.
	 * @return mixed The unserialized value, or the original value if it is not a serialized string.
	 *
	 * @throws \UnexpectedValueException If the provided value is not a valid serialized string or contains disallowed classes.
	 */
	public static function strict_unserialize( string $value ) {
		$remaining_time = null;
		LanguageEngine::get()->tick( $remaining_time, max( 1, (int) ceil( strlen( $value ) / 1024 ) ) );

		if ( ! is_serialized( $value ) ) {
			return $value; // Not a serialized string, return as is.
		}

		// Only stdClass is allowed to be unserialized.
		$unserialized = @unserialize( $value, array( 'allowed_classes' => array( \stdClass::class ) ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize

		// If unserialization completely fails (false) and it wasn't the serialized boolean false, just abort.
		if ( false === $unserialized && 'b:0;' !== $value ) {
			throw new \UnexpectedValueException( 'Invalid serialization detected.' );
		}

		// Deep Inspection (Recursive Scan).
		if ( self::strict_unserialize_has_incomplete_classes( $unserialized ) ) {
			throw new \UnexpectedValueException( 'Unsafe serialization detected.' );
		}

		return $unserialized;
	}

	/**
	 * Recursively checks if data contains an incomplete class instance.
	 *
	 * Traverses arrays and stdClass properties to verify that no __PHP_Incomplete_Class
	 * instances are present.
	 *
	 * @since 0.0.1
	 * @internal
	 *
	 * @param mixed $data The data structure to inspect.
	 * @return bool True if an incomplete class is found, false otherwise.
	 */
	private static function strict_unserialize_has_incomplete_classes( $data ): bool {
		if ( is_array( $data ) ) {
			foreach ( $data as $value ) {
				if ( self::strict_unserialize_has_incomplete_classes( $value ) ) {
					return true;
				}
			}
		} elseif ( is_object( $data ) ) {
			if ( $data instanceof \__PHP_Incomplete_Class ) {
				return true;
			}
			// If it's a stdClass, we need to check its properties as well.
			foreach ( get_object_vars( $data ) as $value ) {
				if ( self::strict_unserialize_has_incomplete_classes( $value ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Formats a string as an inline Markdown code span.
	 *
	 * Handles internal backticks according to the CommonMark specification.
	 *
	 * @since 0.0.1
	 *
	 * @param string $code The code snippet to wrap.
	 * @return string The formatted Markdown code span.
	 */
	public static function format_markdown_code_span( string $code ): string {
		preg_match_all( '/`+/', $code, $matches );
		$max_ticks = 0;
		if ( ! empty( $matches[0] ) ) {
			foreach ( $matches[0] as $match ) {
				$max_ticks = max( $max_ticks, strlen( $match ) );
			}
		}

		$delimiter         = str_repeat( '`', $max_ticks + 1 );
		$starts_with_tick  = '' !== $code && '`' === $code[0];
		$ends_with_tick    = '' !== $code && '`' === substr( $code, -1 );
		$starts_with_space = '' !== $code && ' ' === $code[0];
		$ends_with_space   = '' !== $code && ' ' === substr( $code, -1 );

		if ( $max_ticks > 0 || $starts_with_tick || $ends_with_tick || $starts_with_space || $ends_with_space ) {
			return $delimiter . ' ' . $code . ' ' . $delimiter;
		}

		return $delimiter . $code . $delimiter;
	}

	/**
	 * Resolves a path, verifying that it resides within a designated base directory.
	 *
	 * Normalizes relative and absolute paths, collapses directory traversal segments ('..'),
	 * and validates that the canonical path is confined within the specified boundary.
	 *
	 * @since 0.0.1
	 *
	 * @param string $path       The relative or absolute path.
	 * @param string $base_dir   The base directory boundary. Default ABSPATH.
	 * @param bool   $must_exist Whether the path must already exist on disk. Default true.
	 * @return string The canonicalized absolute path.
	 * @throws \InvalidArgumentException If stream wrappers or invalid characters are used,
	 *                                   the path does not exist (when $must_exist is true),
	 *                                   or the path resolves outside the base directory.
	 */
	public static function resolve_safe_path( string $path, string $base_dir = ABSPATH, bool $must_exist = true ): string {
		// Reject stream wrappers (e.g. phar://, php://, file://, compress.zlib://).
		if ( preg_match( '/^[a-z0-9][a-z0-9.+\-]*:\/\//i', $path ) ) {
			throw new \InvalidArgumentException( 'Access denied: Stream wrappers are not permitted.' );
		}

		// Reject null byte injection or invalid control characters.
		if ( str_contains( $path, "\0" ) ) {
			throw new \InvalidArgumentException( 'Access denied: Path contains invalid characters.' );
		}

		// Append trailing separator to prevent sibling-directory prefix collisions
		// (e.g., '/var/www/html' matching '/var/www/html_backup').
		$real_base      = realpath( $base_dir );
		$resolved_base  = false !== $real_base ? $real_base : $base_dir;
		$canonical_base = trailingslashit( wp_normalize_path( $resolved_base ) );

		// If relative, prepend canonical base directory.
		if ( ! str_starts_with( $path, '/' ) && ! preg_match( '/^[A-Za-z]:[\\\\\/]/', $path ) ) {
			$full_path = $canonical_base . ltrim( $path, '/\\' );
		} else {
			$full_path = $path;
		}

		$real = realpath( $full_path );

		if ( false === $real ) {
			if ( $must_exist ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sanitize_text_field() chosen over esc_html() for CLI context compatibility.
				throw new \InvalidArgumentException( sanitize_text_field( "File or directory does not exist: $path" ) );
			}

			$normalized = wp_normalize_path( $full_path );
			$parts      = explode( '/', $normalized );
			$resolved   = array();

			foreach ( $parts as $segment ) {
				if ( '.' === $segment ) {
					continue;
				}
				if ( '..' === $segment ) {
					array_pop( $resolved );
				} else {
					$resolved[] = $segment;
				}
			}

			$collapsed = implode( '/', $resolved );

			if ( ! str_starts_with( $collapsed, $canonical_base ) && rtrim( $canonical_base, '/' ) !== $collapsed ) {
				if ( ABSPATH === $base_dir ) {
					throw new \InvalidArgumentException( 'Access denied: Path is outside the WordPress root directory.' );
				}
				throw new \InvalidArgumentException( 'Access denied: Path is outside the designated directory.' );
			}

			return $collapsed;
		}

		$normalized_real = wp_normalize_path( $real );

		// Allow both exact root match and paths within the root.
		if ( ! str_starts_with( $normalized_real, $canonical_base ) && rtrim( $canonical_base, '/' ) !== $normalized_real ) {
			if ( ABSPATH === $base_dir ) {
				throw new \InvalidArgumentException( 'Access denied: Path is outside the WordPress root directory.' );
			}
			throw new \InvalidArgumentException( 'Access denied: Path is outside the designated directory.' );
		}

		return $normalized_real;
	}

	/**
	 * Checks if the current site environment matches the authorized staging environment.
	 *
	 * When the `EXPRESSION_LAB_STAGING_URL` constant is defined and non-empty,
	 * this method validates that the current WordPress home URL matches the specified
	 * domain or wildcard pattern.
	 *
	 * To protect against HTTP host spoofing attacks, the verification is strictly
	 * performed against the site URL stored in the database (`get_option('home')`),
	 * never relying on incoming HTTP request headers.
	 *
	 * @since 0.3.0
	 *
	 * @return bool True if the environment is authorized or unrestricted, false otherwise.
	 */
	public static function is_environment_allowed(): bool {
		$configured_staging = defined( 'EXPRESSION_LAB_STAGING_URL' ) ? constant( 'EXPRESSION_LAB_STAGING_URL' ) : null;

		return self::check_environment( $configured_staging, (string) get_option( 'home' ) );
	}

	/**
	 * Evaluates the environment guardrail for a given staging pattern and home URL.
	 *
	 * Pure counterpart of is_environment_allowed(), kept separate so the decision
	 * table can be unit tested without redefining PHP constants.
	 *
	 * - A null, non-scalar or blank pattern disables the guardrail (returns true).
	 * - Once a pattern is configured the check fails closed: an empty or unparsable
	 *   home URL is treated as a mismatch.
	 *
	 * @since 0.3.0
	 * @internal
	 *
	 * @param mixed  $configured_staging Value of EXPRESSION_LAB_STAGING_URL.
	 * @param string $home_url           Site URL persisted in the database.
	 * @return bool True if the environment is authorized or unrestricted, false otherwise.
	 */
	public static function check_environment( $configured_staging, string $home_url ): bool {
		if ( null === $configured_staging || ! is_scalar( $configured_staging ) ) {
			return true;
		}

		$configured_staging = trim( (string) $configured_staging );
		if ( '' === $configured_staging ) {
			return true;
		}

		$home_url = trim( $home_url );
		if ( '' === $home_url ) {
			return false;
		}

		return self::matches_url_pattern( $home_url, $configured_staging );
	}

	/**
	 * Matches a site URL against a domain, hostname, wildcard, or URL path pattern.
	 *
	 * Supports:
	 * - Exact domain/host: `staging.example.com`
	 * - Domain with wildcard: `*.staging.example.com` or `staging-*.example.com`
	 * - Domain with port: `localhost:8080`
	 * - URL with path: `127.0.0.1/expressionlab` or `staging.example.com/subsite`
	 * - Wildcard path: `127.0.0.1/site-*`
	 *
	 * Protocols (`http://`, `https://`) and trailing slashes are normalized and stripped.
	 * If no path is specified in the pattern, any path on that host is allowed.
	 * If no port is specified in the pattern, any port on that host is allowed.
	 * Wildcards never match across `/` boundaries. Comparison is case-insensitive.
	 *
	 * @since 0.3.0
	 *
	 * @param string $home_url Site URL to test (usually from get_option('home')).
	 * @param string $pattern  Domain, hostname, or URL pattern configured in EXPRESSION_LAB_STAGING_URL.
	 * @return bool True if the URL matches the pattern, false otherwise.
	 */
	public static function matches_url_pattern( string $home_url, string $pattern ): bool {
		$home_url = trim( $home_url );
		$pattern  = trim( $pattern );

		if ( '' === $home_url || '' === $pattern ) {
			return false;
		}

		// Normalize pattern: strip protocol, then split into authority (host[:port]) and path.
		$normalized_pattern = (string) preg_replace( '#^[a-z][a-z0-9+.\-]*://#i', '', $pattern );
		$pattern_parts      = explode( '/', $normalized_pattern, 2 );
		$pattern_authority  = strtolower( trim( $pattern_parts[0] ) );
		$pattern_path       = self::normalize_url_path( isset( $pattern_parts[1] ) ? $pattern_parts[1] : '' );

		// Host is either a bracketed IPv6 literal or a colon-free name; port is optional.
		if ( ! preg_match( '/^(\[[^\]]+\]|[^:\[\]]+)(?::(\d{1,5}))?$/', $pattern_authority, $authority ) ) {
			return false;
		}
		$pattern_host = rtrim( $authority[1], '.' );
		$pattern_port = isset( $authority[2] ) && '' !== $authority[2] ? (int) $authority[2] : null;
		if ( '' === $pattern_host ) {
			return false;
		}

		// Ensure the home URL has a scheme, otherwise parse_url() cannot extract the host.
		if ( ! preg_match( '#^[a-z][a-z0-9+.\-]*://#i', $home_url ) ) {
			$home_url = 'http://' . ltrim( $home_url, '/' );
		}

		$parsed = wp_parse_url( $home_url );
		if ( ! is_array( $parsed ) || empty( $parsed['host'] ) ) {
			return false;
		}

		$home_host = rtrim( strtolower( (string) $parsed['host'] ), '.' );

		// Port check (only when the pattern pins a port). Implicit ports default to the scheme's.
		if ( null !== $pattern_port ) {
			$home_port = isset( $parsed['port'] ) ? (int) $parsed['port'] : self::default_port_for_scheme( isset( $parsed['scheme'] ) ? (string) $parsed['scheme'] : '' );
			if ( $home_port !== $pattern_port ) {
				return false;
			}
		}

		// Host check (exact or wildcard).
		if ( ! self::matches_wildcard( $pattern_host, $home_host, '' ) ) {
			return false;
		}

		// If no path was specified in the pattern, allow any path on this host.
		if ( '' === $pattern_path ) {
			return true;
		}

		$home_path = self::normalize_url_path( isset( $parsed['path'] ) ? (string) $parsed['path'] : '' );

		// Path check: exact match or nested subpath, with segment-bound wildcards.
		return self::matches_wildcard( $pattern_path, $home_path, '(?:/.*)?' );
	}

	/**
	 * Normalizes a URL path for comparison: drops query/fragment, trims slashes and lowercases.
	 *
	 * @since 0.3.0
	 * @internal
	 *
	 * @param string $path Raw path (with or without leading/trailing slashes).
	 * @return string Normalized path starting with `/`, or an empty string for the root.
	 */
	private static function normalize_url_path( string $path ): string {
		$path = (string) preg_replace( '/[?#].*$/s', '', $path );
		$path = trim( $path );
		$path = trim( $path, '/' );

		return '' === $path ? '' : '/' . strtolower( $path );
	}

	/**
	 * Matches a subject against a pattern where `*` stands for any run of non-slash characters.
	 *
	 * @since 0.3.0
	 * @internal
	 *
	 * @param string $pattern Pattern (already normalized).
	 * @param string $subject Subject (already normalized).
	 * @param string $suffix  Optional regex suffix appended after the pattern (e.g. nested subpaths).
	 * @return bool True on match.
	 */
	private static function matches_wildcard( string $pattern, string $subject, string $suffix ): bool {
		$regex = '#^' . str_replace( '\*', '[^/]*', preg_quote( $pattern, '#' ) ) . $suffix . '$#i';

		return 1 === preg_match( $regex, $subject );
	}

	/**
	 * Returns the implicit TCP port for a URL scheme.
	 *
	 * @since 0.3.0
	 * @internal
	 *
	 * @param string $scheme URL scheme.
	 * @return int|null Default port, or null when unknown.
	 */
	private static function default_port_for_scheme( string $scheme ): ?int {
		switch ( strtolower( $scheme ) ) {
			case 'https':
				return 443;
			case 'http':
				return 80;
			default:
				return null;
		}
	}

	/**
	 * Matches a site URL against a domain or wildcard pattern.
	 *
	 * Alias for matches_url_pattern for backward compatibility.
	 *
	 * @since 0.3.0
	 *
	 * @param string $home_url Site URL to test (usually from get_option('home')).
	 * @param string $pattern  Domain or pattern configured in EXPRESSION_LAB_STAGING_URL.
	 * @return bool True if the URL matches the pattern, false otherwise.
	 */
	public static function matches_domain_pattern( string $home_url, string $pattern ): bool {
		return self::matches_url_pattern( $home_url, $pattern );
	}
}
