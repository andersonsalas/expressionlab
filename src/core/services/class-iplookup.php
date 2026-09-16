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

namespace ExpressionLab\Core\Services;

use ExpressionLab\Core\Helper;
use ExpressionLab\Core\Interfaces\ServiceInterface;
use ExpressionLab\Core\LanguageEngine;
use MaxMind\Db\Reader;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * IPLookup Service.
 *
 * Provides offline IP address resolution to ISO 3166-1 alpha-2 country codes
 * backed by a local MaxMind DB (.mmdb) database.
 *
 * MaxMind and GeoLite2 are registered trademarks of MaxMind, Inc.
 *
 * @package ExpressionLab
 */
final class IPLookup implements ServiceInterface {
	/**
	 * Database Reader instance for the current execution context.
	 *
	 * @var Reader|null
	 */
	private $reader = null;

	/**
	 * IPLookup constructor.
	 *
	 * Initializes the underlying database Reader if installed and readable.
	 */
	public function __construct() {
		if ( self::is_available() ) {
			try {
				$this->reader = new Reader( self::get_database_path() );
			} catch ( \Exception $e ) {
				$this->reader = null;
			}
		}
	}

	/**
	 * Resolves the canonical file path to the local database file.
	 *
	 * @internal
	 *
	 * @return string Absolute canonical path to the database file.
	 * @throws \InvalidArgumentException If custom path violates confinement or lacks .mmdb extension.
	 */
	public static function get_database_path(): string {
		$custom_path = defined( 'EXPRESSION_LAB_MAXMIND_PATH' ) ? constant( 'EXPRESSION_LAB_MAXMIND_PATH' ) : null;

		if ( ! empty( $custom_path ) && is_string( $custom_path ) ) {
			if ( ! str_ends_with( strtolower( $custom_path ), '.mmdb' ) ) {
				throw new \InvalidArgumentException( 'Access denied: `EXPRESSION_LAB_MAXMIND_PATH` must end with a `.mmdb` extension.' );
			}

			return Helper::resolve_safe_path( $custom_path, WP_CONTENT_DIR, false );
		}

		$salt = defined( 'AUTH_KEY' ) ? constant( 'AUTH_KEY' ) : ( function_exists( 'wp_salt' ) ? wp_salt( 'nonce' ) : 'expressionlab_salt' );
		$hash = substr( hash_hmac( 'sha256', 'expressionlab_geolite2_country', $salt ), 0, 16 );

		return wp_normalize_path( WP_CONTENT_DIR . '/expressionlab/' . $hash . '-GeoLite2-Country.mmdb' );
	}

	/**
	 * Checks whether the local database file exists and is readable.
	 *
	 * @internal
	 *
	 * @return bool True if the database file is installed and readable, false otherwise.
	 */
	public static function is_available(): bool {
		try {
			$path = self::get_database_path();
			return file_exists( $path ) && is_readable( $path );
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Resolves an IPv4 or IPv6 address to its ISO 3166-1 alpha-2 country code.
	 *
	 * TODO: Documentation.
	 *
	 * This method uses the GeoLite2 database from MaxMind, Inc.
	 *
	 * MaxMind and GeoLite2 are registered trademarks of MaxMind, Inc.
	 *
	 * @param string $ip Valid IPv4 or IPv6 address.
	 * @return string|null Two-letter country code (e.g. `'US'`, `'ES'`), or `null` if unallocated or private.
	 * @throws \InvalidArgumentException If the IP address is syntactically invalid.
	 * @throws \RuntimeException If the database file is missing or unreadable.
	 */
	public function to_country( string $ip ): ?string {
		LanguageEngine::get()->tick();

		$clean_ip = trim( $ip );

		if ( ! filter_var( $clean_ip, FILTER_VALIDATE_IP ) ) {
			throw new \InvalidArgumentException( esc_html( "Invalid IP address provided: $ip" ) );
		}

		// Private or reserved IP ranges (RFC 1918, loopback, link-local) have no public country mapping.
		if ( ! filter_var( $clean_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return null;
		}

		if ( null === $this->reader ) {
			throw new \RuntimeException( 'GeoLite2 database not found. Please run `wp expressionlab iplookup update` via WP-CLI.' );
		}

		try {
			$record = $this->reader->get( $clean_ip );
		} catch ( \Exception $e ) {
			throw new \RuntimeException( esc_html( 'IP lookup failed: ' . $e->getMessage() ) );
		}

		if ( is_array( $record ) && isset( $record['country']['iso_code'] ) && is_string( $record['country']['iso_code'] ) ) {
			return strtoupper( $record['country']['iso_code'] );
		}

		if ( is_array( $record ) && isset( $record['registered_country']['iso_code'] ) && is_string( $record['registered_country']['iso_code'] ) ) {
			return strtoupper( $record['registered_country']['iso_code'] );
		}

		return null;
	}
}
