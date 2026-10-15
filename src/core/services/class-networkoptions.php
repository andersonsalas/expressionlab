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

use ExpressionLab\Core\LanguageEngine;
use ExpressionLab\Core\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * Network Options Service.
 *
 * Provides methods to manage WordPress multisite network options stored in the `wp_sitemeta` table.
 *
 * Regular network option methods (`get`, `get_raw`, `update`, `update_raw`, `delete`) are exclusively
 * functional in multisite environments, always targeting the main network context (`site_id`).
 *
 * Transient helpers (`*_transient*`) are universal: on multisite they target `wp_sitemeta`, while on
 * single-site installations they transparently delegate to the `Options` service and target the
 * `_site_transient_*` records of the `wp_options` table (mirroring WordPress core storage).
 *
 * @since 0.0.1
 *
 * @package ExpressionLab
 */
final class NetworkOptions {
	/**
	 * Database prefix for site transient values (`wp_sitemeta` on multisite, `wp_options` on single-site).
	 */
	public const TRANSIENT_PREFIX = '_site_transient_';

	/**
	 * Database prefix for site transient expiration timestamps (`wp_sitemeta` on multisite, `wp_options` on single-site).
	 */
	public const TRANSIENT_TIMEOUT_PREFIX = '_site_transient_timeout_';

	/**
	 * Maximum length allowed for a site transient key.
	 *
	 * When running in single-site mode, site transients are stored in the `wp_options` table
	 * where `option_name` is limited to 191 characters. Subtracting the 24 characters of
	 * `_site_transient_timeout_` leaves 167 characters for the transient key name, ensuring
	 * full compatibility across both single-site and multisite environments.
	 */
	public const MAX_TRANSIENT_KEY_LENGTH = 167;

	/**
	 * Live database instance.
	 *
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * Options service instance for single-site transient delegation.
	 *
	 * @var Options
	 */
	private $options;

	/**
	 * Constructor.
	 *
	 * @internal
	 *
	 * @param Options|null $options Optional. Options service instance for single-site transient delegation. Default null.
	 */
	public function __construct( ?Options $options = null ) {
		global $wpdb;

		$this->wpdb    = $wpdb;
		$this->options = $options ?? new Options();
	}

	/**
	 * Magic method to expose constants as properties.
	 *
	 * @internal
	 *
	 * @param string $name Property name.
	 * @return mixed Constant value if defined, null otherwise.
	 */
	public function __get( string $name ) {
		if ( defined( "self::$name" ) ) {
			return constant( "self::$name" );
		}
		return null;
	}

	/**
	 * Retrieves the exact, unmodified raw option value directly from the network sitemeta table.
	 *
	 * Queries the `meta_value` column of the `wp_sitemeta` table for the current network (`site_id`)
	 * without performing any deserialization or JSON decoding.
	 *
	 * Example:
	 *
	 * ```elscript
	 * NetworkOptions.get_raw('site_admins')
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/options#networkoptionsget_raw
	 *
	 * @param string $key           The network option key.
	 * @param mixed  $default_value Optional. Default value returned if the option does not exist. Default `null`.
	 * @return string|mixed|null The raw string option value from the database, or `default_value` if not found.
	 * @throws \Exception If accessed in a non-multisite installation.
	 */
	public function get_raw( string $key, $default_value = null ) {
		if ( ! is_multisite() ) {
			throw new \Exception( esc_html( 'This method is only available in multisite installations.' ) );
		}

		// Retrieve raw value for inspection before unserializing.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- They are actually prepared.
		LanguageEngine::get()->tick();
		$network_id = get_current_network_id();
		$query      = $this->wpdb->prepare( "SELECT meta_value FROM {$this->wpdb->sitemeta} WHERE meta_key = %s AND site_id = %d", $key, $network_id );
		$value      = $this->wpdb->get_var( $query );
		LanguageEngine::get()->tick( $remaining_time, is_string( $value ) ? strlen( $value ) : 1 ); // The length of the value is a good proxy for the operation cost.
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		return $value ?? $default_value;
	}

	/**
	 * Retrieves a network option value, automatically parsing JSON strings or safely unserializing PHP data.
	 *
	 * Queries the `wp_sitemeta` table for the current network (`site_id`). If the stored value is a
	 * PHP serialized string, it undergoes strict deserialization: only `stdClass` objects, scalar
	 * primitives, or arrays are allowed.
	 *
	 * If the stored value is valid JSON, it is decoded into an associative array. Scalar values and
	 * unparsed strings are returned directly.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * NetworkOptions.get('active_sitewide_plugins')
	 * ```
	 *
	 * ```elscript
	 * NetworkOptions.get('site_admins', [])
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/options#networkoptionsget
	 *
	 * @param string $key           The network option key.
	 * @param mixed  $default_value Optional. Default value returned if the option does not exist. Default `null`.
	 * @return mixed The processed option value, or `default_value` if not found.
	 * @throws \Exception If accessed in a non-multisite installation, or if serialized data contains unauthorized PHP classes.
	 */
	public function get( string $key, $default_value = null ) {
		$value = $this->get_raw( $key, $default_value );

		// If it's not a string, it can't be serialized or JSON (probably an array or direct object).
		if ( ! is_string( $value ) ) {
			return $value;
		}

		return $this->decode_value( $value, 'NetworkOptions.get_raw()' );
	}

	/**
	 * Updates or inserts a raw network option value directly in the sitemeta table without serialization or encoding.
	 *
	 * Enforces write protection via `EXPRESSION_LAB_DATABASE_READONLY`. On update or insert, purges
	 * the corresponding cache keys from the WordPress `site-options` object cache group to ensure atomic cache coherence.
	 *
	 * Example:
	 *
	 * ```elscript
	 * NetworkOptions.update_raw(
	 *      'custom_network_meta',
	 *      'raw_value'
	 * )
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/options#networkoptionsupdate_raw
	 *
	 * @param string $key   The network option key.
	 * @param string $value The raw string payload to store.
	 * @return bool `true` if `value` was updated or inserted, `false` if unchanged or on database error.
	 * @throws \Exception If write protection is active, or if accessed in a non-multisite installation.
	 */
	public function update_raw( string $key, string $value ): bool {
		if ( ! is_multisite() ) {
			throw new \Exception( esc_html( 'This method is only available in multisite installations.' ) );
		}

		if ( defined( 'EXPRESSION_LAB_DATABASE_READONLY' ) && EXPRESSION_LAB_DATABASE_READONLY ) {
			throw new \Exception( esc_html( 'Operation denied. Write protection is enabled. Set `EXPRESSION_LAB_DATABASE_READONLY` to `false` in `wp-config.php`.' ) );
		}

		$old_value = $this->get_raw( $key );

		// Do not update if the new value is the same as the old value.
		if ( $value === $old_value ) {
			return false;
		}

		LanguageEngine::get()->tick( $remaining_time, strlen( $value ) );

		$network_id = get_current_network_id();

		// Determine if it's an INSERT or UPDATE.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- They are actually prepared.
		$query         = $this->wpdb->prepare( "SELECT count(*) FROM {$this->wpdb->sitemeta} WHERE meta_key = %s AND site_id = %d", $key, $network_id );
		$option_exists = ( (int) $this->wpdb->get_var( $query ) ) > 0;
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		$result = false;

		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		if ( $option_exists ) {
			// Update.
			$result = $this->wpdb->update(
				$this->wpdb->sitemeta,
				array( 'meta_value' => $value ),
				array(
					'meta_key' => $key,
					'site_id'  => $network_id,
				),
				array( '%s' ), // Value format.
				array( '%s', '%d' )  // Where format.
			);
		} else {
			// Insert.
			$result = $this->wpdb->insert(
				$this->wpdb->sitemeta,
				array(
					'meta_key'   => $key,
					'meta_value' => $value,
					'site_id'    => $network_id,
				),
				array( '%s', '%s', '%d' )
			);
		}
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value

		if ( false === $result ) {
			return false; // Database error.
		}

		// Clear the cache.
		wp_cache_delete( "{$network_id}:{$key}", 'site-options' );
		wp_cache_delete( "{$network_id}:notoptions", 'site-options' );

		return true;
	}

	/**
	 * Updates an existing network option or creates a new one with strict serialization safety checks.
	 *
	 * Enforces write protection (`EXPRESSION_LAB_DATABASE_READONLY`) and format conflict protections
	 * to prevent silent data corruption:
	 * * If the existing option is PHP-serialized, updating with `Options.FORMAT_JSON` is rejected.
	 * * If the existing option is JSON, updating with PHP serialization is rejected.
	 * * Pre-flight unserialization detects non-`stdClass` objects and throws an exception to prevent class loss.
	 *
	 * For intentional format conversions or low-level writes, use `NetworkOptions.update_raw()` instead.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * NetworkOptions.update('network_security_policy', {
	 *     'max_login_attempts': 5,
	 *     'lockout_duration': 900
	 * })
	 * ```
	 *
	 * ```elscript
	 * NetworkOptions.update('custom_feature_flag', true)
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/options#networkoptionsupdate
	 *
	 * @param string $key                The network option key.
	 * @param mixed  $value              The option value to store.
	 * @param string $serialization_type Optional. Serialization format (`Options.FORMAT_SERIALIZED`, `Options.FORMAT_JSON`, or `Options.FORMAT_SERIALIZED_OBJECT`). Default `Options.FORMAT_SERIALIZED`.
	 * @return bool `true` on success, `false` on failure or if `value` is unchanged.
	 * @throws \Exception If write protection is enabled, if serialization checks fail, or if accessed in a non-multisite installation.
	 */
	public function update( string $key, $value, string $serialization_type = Options::FORMAT_SERIALIZED ): bool {
		if ( ! is_multisite() ) {
			throw new \Exception( esc_html( 'This method is only available in multisite installations.' ) );
		}

		if ( defined( 'EXPRESSION_LAB_DATABASE_READONLY' ) && EXPRESSION_LAB_DATABASE_READONLY ) {
			throw new \Exception( esc_html( 'Operation denied. Write protection is enabled. Set `EXPRESSION_LAB_DATABASE_READONLY` to `false` in `wp-config.php`.' ) );
		}

		$network_id = get_current_network_id();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- They are actually prepared.
		LanguageEngine::get()->tick();
		$query         = $this->wpdb->prepare( "SELECT count(*) FROM {$this->wpdb->sitemeta} WHERE meta_key = %s AND site_id = %d", $key, $network_id );
		$option_exists = ( (int) $this->wpdb->get_var( $query ) ) > 0;
		LanguageEngine::get()->tick();
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		if ( $option_exists ) {
			$raw_value = $this->get_raw( $key );

			if ( is_string( $raw_value ) && is_serialized( $raw_value ) ) {
				// Safety check: prevent accidental overwriting of serialized data with incompatible formats.
				if ( Options::FORMAT_JSON === $serialization_type ) {
					throw new \Exception( esc_html( 'Cannot update with JSON serialization because the existing value is serialized. Use NetworkOptions.update_raw() instead.' ) );
				}

				// Safety check: prevent class information loss if the existing value is serialized and contains objects different from stdClass.
				$this->strict_serialize_check( $raw_value, 'NetworkOptions.update_raw()' );
			}

			if ( is_string( $raw_value ) ) {
				// Safety check: prevent accidental overwriting of JSON data with incompatible formats.
				$decoded = json_decode( $raw_value, true );
				if ( json_last_error() === JSON_ERROR_NONE && Options::FORMAT_JSON !== $serialization_type ) {
					throw new \Exception( esc_html( 'Cannot update with PHP serialization because the existing value is JSON. Use NetworkOptions.update_raw() instead.' ) );
				}
			}
		}

		if ( Options::FORMAT_SERIALIZED_OBJECT === $serialization_type ) {
			$value = (object) $value;
		} elseif ( Options::FORMAT_JSON === $serialization_type ) {
			$value = wp_json_encode( $value );
		}

		if ( Options::FORMAT_JSON !== $serialization_type ) {
			// Safety check: reject any serialized value that contains non-stdClass objects.
			$this->strict_serialize_check( $value, 'NetworkOptions.update_raw()' );
		}

		return update_network_option( $network_id, $key, $value );
	}

	/**
	 * Deletes a network option value from the sitemeta table and purges related cache entries.
	 *
	 * Enforces write protection via `EXPRESSION_LAB_DATABASE_READONLY`. On deletion, invalidates
	 * cached site options for the current network.
	 *
	 * Example:
	 *
	 * ```elscript
	 * NetworkOptions.delete('obsolete_network_key')
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/options#networkoptionsdelete
	 *
	 * @param string $key The network option key to delete.
	 * @return bool `true` on successful deletion, `false` on failure or if option did not exist.
	 * @throws \Exception If write protection is active, or if accessed in a non-multisite installation.
	 */
	public function delete( string $key ): bool {
		if ( ! is_multisite() ) {
			throw new \Exception( esc_html( 'This method is only available in multisite installations.' ) );
		}

		if ( defined( 'EXPRESSION_LAB_DATABASE_READONLY' ) && EXPRESSION_LAB_DATABASE_READONLY ) {
			throw new \Exception( esc_html( 'Operation denied. Write protection is enabled. Set `EXPRESSION_LAB_DATABASE_READONLY` to `false` in `wp-config.php`.' ) );
		}

		$network_id = get_current_network_id();
		LanguageEngine::get()->tick();
		return delete_network_option( $network_id, $key );
	}

	/**
	 * Retrieves the expiration timestamp (Unix epoch) for a specific network transient.
	 *
	 * Queries the `_site_transient_timeout_{$key}` option directly from the sitemeta table
	 * (or options table in single-site) without triggering deserialization.
	 *
	 * Orphaned timeout records (without a matching value record) are reported as `null`,
	 * since the transient does not exist.
	 *
	 * Example:
	 *
	 * ```elscript
	 * NetworkOptions.get_transient_timeout('update_core')
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/options#networkoptionsget_transient_timeout
	 *
	 * @param string $key The transient key name.
	 * @return int|null The Unix timestamp when the transient expires, or `null` if it does not expire or does not exist.
	 * @throws \InvalidArgumentException If the key is empty or exceeds the maximum key length.
	 */
	public function get_transient_timeout( string $key ): ?int {
		$this->validate_transient_key( $key );

		$transient = $this->locate_transient( $key );

		return null === $transient ? null : $transient['timeout'];
	}

	/**
	 * Calculates the remaining time to live (TTL in seconds) of a network transient.
	 *
	 * Example:
	 *
	 * ```elscript
	 * NetworkOptions.get_transient_ttl('update_plugins')
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/options#networkoptionsget_transient_ttl
	 *
	 * @param string $key The transient key name.
	 * @return int|null Remaining seconds until expiration, `0` if expired or persistent without timeout, or `null` if the transient does not exist.
	 * @throws \InvalidArgumentException If the key is empty or exceeds the maximum key length.
	 */
	public function get_transient_ttl( string $key ): ?int {
		$this->validate_transient_key( $key );

		$transient = $this->locate_transient( $key );

		if ( null === $transient ) {
			return null;
		}

		if ( null === $transient['timeout'] ) {
			return 0;
		}

		return (int) max( 0, $transient['timeout'] - time() );
	}

	/**
	 * Retrieves the raw value of a network transient from the database.
	 *
	 * Queries `_site_transient_{$key}` without performing any deserialization or JSON decoding.
	 *
	 * In single-site installations, operates on the `wp_options` table via internal delegation.
	 *
	 * By default, evaluates if the transient has expired; if expired, returns `$default_value`.
	 *
	 * Example:
	 *
	 * ```elscript
	 * NetworkOptions.get_transient_raw('sitewide_api_cache')
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/options#networkoptionsget_transient_raw
	 *
	 * @param string $key              The transient key name.
	 * @param mixed  $default_value    Optional. Default value returned if the transient does not exist or has expired. Default `null`.
	 * @param bool   $check_expiration Optional. Whether to verify expiration against `_site_transient_timeout_{$key}`. Default `true`.
	 * @return string|mixed|null The raw string value from the sitemeta or options table, or `$default_value`.
	 * @throws \InvalidArgumentException If the key is empty or exceeds the maximum key length.
	 */
	public function get_transient_raw( string $key, $default_value = null, bool $check_expiration = true ) {
		$this->validate_transient_key( $key );

		$transient = $this->locate_transient( $key, $check_expiration );

		if ( null === $transient || ( $check_expiration && $this->is_transient_expired( $transient['timeout'] ) ) ) {
			return $default_value;
		}

		return $transient['value'];
	}

	/**
	 * Retrieves a network transient value, decoding JSON strings or unserializing allowed types.
	 *
	 * Checks if the transient has expired according to its timeout record. If expired, returns
	 * `$default_value`.
	 *
	 * In single-site installations, operates on the `wp_options` table via internal delegation.
	 * Otherwise, parses JSON or unserializes PHP data (disallowing unauthorized classes).
	 *
	 * Examples:
	 *
	 * ```elscript
	 * NetworkOptions.get_transient('update_plugins')
	 * ```
	 *
	 * ```elscript
	 * NetworkOptions.get_transient('network_notice', 'default_value')
	 * ```
	 *
	 * @see https://developer.wordpress.org/reference/functions/get_site_transient/
	 * @see https://expressionlab.io/docs/api-reference/options#networkoptionsget_transient
	 *
	 * @param string $key           The transient key name.
	 * @param mixed  $default_value Optional. Default value returned if transient is not found or expired. Default `null`.
	 * @return mixed The processed transient value, or `$default_value`.
	 * @throws \InvalidArgumentException If the key is empty or exceeds the maximum key length.
	 * @throws \Exception If the serialized data contains unauthorized PHP classes.
	 */
	public function get_transient( string $key, $default_value = null ) {
		$this->validate_transient_key( $key );

		$transient = $this->locate_transient( $key );

		if ( null === $transient || $this->is_transient_expired( $transient['timeout'] ) ) {
			return $default_value;
		}

		// Decode the value already fetched (single read: no double query, no TOCTOU between checks and decoding).
		return $this->decode_value( $transient['value'], 'NetworkOptions.get_transient_raw()' );
	}

	/**
	 * Sets or updates a network transient value with expiration and serialization safety checks.
	 *
	 * If `$expiration > 0`, stores an expiration timestamp in `_site_transient_timeout_{$key}`.
	 *
	 * If `$expiration === 0`, removes any pre-existing timeout record.
	 *
	 * In single-site installations, operates on the `wp_options` table via internal delegation and ensures autoload is `'no'`.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * NetworkOptions.set_transient('global_alert', 'Maintenance at 22:00', 3600)
	 * ```
	 *
	 * ```elscript
	 * NetworkOptions.set_transient('network_stats', { 'sites': 50 }, 7200, Options.FORMAT_JSON)
	 * ```
	 *
	 * @see https://developer.wordpress.org/reference/functions/set_site_transient/
	 * @see https://expressionlab.io/docs/api-reference/options#networkoptionsset_transient
	 *
	 * @param string $key                The transient key name.
	 * @param mixed  $value              The transient value to store.
	 * @param int    $expiration         Optional. Time until expiration in seconds. `0` means no expiration. Default `0`.
	 * @param string $serialization_type Optional. Serialization format (`Options.FORMAT_SERIALIZED`, `Options.FORMAT_JSON`, or `Options.FORMAT_SERIALIZED_OBJECT`). Default `Options.FORMAT_SERIALIZED`.
	 * @return bool `true` on successful update/insert, `false` if unchanged or on error.
	 * @throws \InvalidArgumentException If the key is empty or exceeds the maximum key length, or if the expiration is negative.
	 * @throws \Exception If write protection is active, serialization fails, or a format mismatch is detected.
	 */
	public function set_transient( string $key, $value, int $expiration = 0, string $serialization_type = Options::FORMAT_SERIALIZED ): bool {
		$this->validate_write_permission();
		$this->validate_transient_key( $key );
		$this->validate_transient_expiration( $expiration );

		$data_key    = self::TRANSIENT_PREFIX . $key;
		$timeout_key = self::TRANSIENT_TIMEOUT_PREFIX . $key;

		if ( ! is_multisite() ) {
			$options = $this->options;
			if ( $expiration > 0 ) {
				$data_result = $options->update( $data_key, $value, $serialization_type, 'no' );

				if ( ! $data_result ) {
					// `update_option()` short-circuits on unchanged values without applying the autoload change,
					// so enforce it explicitly: expiring transients must never be loaded into `alloptions`.
					$data_result = $this->disable_single_site_autoload( $data_key );
				}

				$timeout_result = $options->update_raw( $timeout_key, (string) ( time() + $expiration ), 'no' );

				return $data_result || $timeout_result;
			}

			$data_result     = $options->update( $data_key, $value, $serialization_type );
			$timeout_deleted = $options->delete( $timeout_key );

			return $data_result || $timeout_deleted;
		}

		if ( $expiration > 0 ) {
			$data_result    = $this->update( $data_key, $value, $serialization_type );
			$timeout_result = $this->update_raw( $timeout_key, (string) ( time() + $expiration ) );

			return $data_result || $timeout_result;
		}

		$data_result     = $this->update( $data_key, $value, $serialization_type );
		$timeout_deleted = $this->delete( $timeout_key );

		return $data_result || $timeout_deleted;
	}

	/**
	 * Sets or updates a raw network transient string value directly in the database without serialization or format checks.
	 *
	 * If `$expiration > 0`, manages `_site_transient_timeout_{$key}`.
	 *
	 * In single-site installations, operates on the `wp_options` table via internal delegation and ensures autoload is `'no'`.
	 *
	 * Example:
	 *
	 * ```elscript
	 * NetworkOptions.set_transient_raw('raw_sitewide_data', 'raw_string', 600)
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/options#networkoptionsset_transient_raw
	 *
	 * @param string $key        The transient key name.
	 * @param string $value      The raw string payload to store.
	 * @param int    $expiration Optional. Time until expiration in seconds. `0` means no expiration. Default `0`.
	 * @return bool `true` on successful update/insert, `false` if unchanged or on database error.
	 * @throws \InvalidArgumentException If the key is empty or exceeds the maximum key length, or if the expiration is negative.
	 * @throws \Exception If write protection is active (`EXPRESSION_LAB_DATABASE_READONLY` is true).
	 */
	public function set_transient_raw( string $key, string $value, int $expiration = 0 ): bool {
		$this->validate_write_permission();
		$this->validate_transient_key( $key );
		$this->validate_transient_expiration( $expiration );

		$data_key    = self::TRANSIENT_PREFIX . $key;
		$timeout_key = self::TRANSIENT_TIMEOUT_PREFIX . $key;

		if ( ! is_multisite() ) {
			$options = $this->options;
			if ( $expiration > 0 ) {
				$data_result    = $options->update_raw( $data_key, $value, 'no' );
				$timeout_result = $options->update_raw( $timeout_key, (string) ( time() + $expiration ), 'no' );

				return $data_result || $timeout_result;
			}

			$data_result     = $options->update_raw( $data_key, $value );
			$timeout_deleted = $options->delete( $timeout_key );

			return $data_result || $timeout_deleted;
		}

		if ( $expiration > 0 ) {
			$data_result    = $this->update_raw( $data_key, $value );
			$timeout_result = $this->update_raw( $timeout_key, (string) ( time() + $expiration ) );

			return $data_result || $timeout_result;
		}

		$data_result     = $this->update_raw( $data_key, $value );
		$timeout_deleted = $this->delete( $timeout_key );

		return $data_result || $timeout_deleted;
	}

	/**
	 * Deletes a network transient and its associated expiration timeout from the database.
	 *
	 * Purges both `_site_transient_{$key}` and `_site_transient_timeout_{$key}`.
	 * In single-site installations, operates on the `wp_options` table via internal delegation.
	 *
	 * Example:
	 *
	 * ```elscript
	 * NetworkOptions.delete_transient('update_plugins')
	 * ```
	 *
	 * @see https://developer.wordpress.org/reference/functions/delete_site_transient/
	 * @see https://expressionlab.io/docs/api-reference/options#networkoptionsdelete_transient
	 *
	 * @param string $key The transient key name.
	 * @return bool `true` if either the value or timeout was deleted, `false` if neither existed or on failure.
	 * @throws \InvalidArgumentException If the key is empty or exceeds the maximum key length.
	 * @throws \Exception If write protection is active (`EXPRESSION_LAB_DATABASE_READONLY` is true).
	 */
	public function delete_transient( string $key ): bool {
		$this->validate_write_permission();
		$this->validate_transient_key( $key );

		$data_key    = self::TRANSIENT_PREFIX . $key;
		$timeout_key = self::TRANSIENT_TIMEOUT_PREFIX . $key;

		if ( ! is_multisite() ) {
			$options         = $this->options;
			$data_deleted    = $options->delete( $data_key );
			$timeout_deleted = $options->delete( $timeout_key );

			return $data_deleted || $timeout_deleted;
		}

		$data_deleted    = $this->delete( $data_key );
		$timeout_deleted = $this->delete( $timeout_key );

		return $data_deleted || $timeout_deleted;
	}

	/**
	 * Validates a transient key before any database access.
	 *
	 * Rejects empty keys and keys longer than `MAX_TRANSIENT_KEY_LENGTH` bytes so that the derived
	 * `_site_transient_timeout_{$key}` name always fits `varchar(191)` columns without truncation.
	 *
	 * @internal
	 *
	 * @param string $key The transient key name.
	 * @return void
	 * @throws \InvalidArgumentException If the key is empty or exceeds the maximum key length.
	 */
	private function validate_transient_key( string $key ): void {
		if ( '' === $key || strlen( $key ) > self::MAX_TRANSIENT_KEY_LENGTH ) {
			throw new \InvalidArgumentException( esc_html( 'Transient key must be non-empty and cannot exceed ' . self::MAX_TRANSIENT_KEY_LENGTH . ' characters.' ) );
		}
	}

	/**
	 * Validates a transient expiration (in seconds).
	 *
	 * Negative values are rejected instead of being silently converted into a persistent transient,
	 * and values that would overflow `time() + $expiration` are rejected as well.
	 *
	 * @internal
	 *
	 * @param int $expiration Time until expiration in seconds.
	 * @return void
	 * @throws \InvalidArgumentException If the expiration is negative or too large.
	 */
	private function validate_transient_expiration( int $expiration ): void {
		if ( $expiration < 0 || $expiration > PHP_INT_MAX - time() ) {
			throw new \InvalidArgumentException( esc_html( 'Transient expiration must be a non-negative number of seconds.' ) );
		}
	}

	/**
	 * Ensures write protection is disabled before a compound (multi-row) write starts.
	 *
	 * @internal
	 *
	 * @return void
	 * @throws \Exception If write protection is active (`EXPRESSION_LAB_DATABASE_READONLY` is true).
	 */
	private function validate_write_permission(): void {
		if ( defined( 'EXPRESSION_LAB_DATABASE_READONLY' ) && EXPRESSION_LAB_DATABASE_READONLY ) {
			throw new \Exception( esc_html( 'Operation denied. Write protection is enabled. Set `EXPRESSION_LAB_DATABASE_READONLY` to `false` in `wp-config.php`.' ) );
		}
	}

	/**
	 * Reads a raw transient row from the environment-appropriate table without mutating it.
	 *
	 * @internal
	 *
	 * @param string $name The full row name (prefix included).
	 * @return string|null The raw value, or `null` if not found.
	 */
	private function read_transient_row( string $name ): ?string {
		$value = is_multisite() ? $this->get_raw( $name ) : $this->options->get_raw( $name );

		return null === $value ? null : (string) $value;
	}

	/**
	 * Locates the storage record of a network transient without mutating the database.
	 *
	 * @internal
	 *
	 * @param string $key          The transient key name.
	 * @param bool   $with_timeout Optional. Whether to read the timeout record. Default `true`.
	 * @return array{value: string, timeout: int|null}|null The raw value and parsed timeout, or `null` if no value exists.
	 */
	private function locate_transient( string $key, bool $with_timeout = true ): ?array {
		$raw_value = $this->read_transient_row( self::TRANSIENT_PREFIX . $key );

		if ( null === $raw_value ) {
			return null;
		}

		return array(
			'value'   => $raw_value,
			'timeout' => $with_timeout ? $this->parse_transient_timeout( $this->read_transient_row( self::TRANSIENT_TIMEOUT_PREFIX . $key ) ) : null,
		);
	}

	/**
	 * Parses a raw timeout record into a Unix timestamp.
	 *
	 * Non-numeric (corrupt) timeouts are treated as "no timeout", mirroring WordPress core.
	 *
	 * @internal
	 *
	 * @param string|null $raw_timeout The raw timeout value from the database.
	 * @return int|null The Unix timestamp, or `null` if missing or non-numeric.
	 */
	private function parse_transient_timeout( ?string $raw_timeout ): ?int {
		if ( null === $raw_timeout || ! is_numeric( $raw_timeout ) ) {
			return null;
		}

		return (int) $raw_timeout;
	}

	/**
	 * Determines whether a transient timeout has elapsed.
	 *
	 * Mirrors WordPress core: a transient expires once `time()` is strictly greater than its timeout.
	 *
	 * @internal
	 *
	 * @param int|null $timeout The parsed timeout, or `null` for persistent transients.
	 * @return bool
	 */
	private function is_transient_expired( ?int $timeout ): bool {
		return null !== $timeout && time() > $timeout;
	}

	/**
	 * Forces `autoload = 'no'` on an existing single-site `wp_options` row without altering its value.
	 *
	 * @internal
	 *
	 * @param string $key The option key.
	 * @return bool `true` if the autoload flag was written, `false` if the option does not exist or on failure.
	 * @throws \Exception If write protection is active (`EXPRESSION_LAB_DATABASE_READONLY` is true).
	 */
	private function disable_single_site_autoload( string $key ): bool {
		$raw_value = $this->options->get_raw( $key );

		if ( null === $raw_value ) {
			return false;
		}

		return $this->options->update_raw( $key, (string) $raw_value, 'no' );
	}

	/**
	 * Decodes a raw stored value: strict PHP unserialization, JSON decoding, or the raw string.
	 *
	 * @internal
	 *
	 * @param string $value       The raw value.
	 * @param string $alternative The alternative method to suggest in case of failure (for error messages).
	 * @return mixed The decoded value.
	 * @throws \Exception If the serialized data contains unauthorized PHP classes.
	 */
	private function decode_value( string $value, string $alternative ) {
		// If it's a serialized string, attempt to unserialize it with strict checks.
		if ( is_serialized( $value ) ) {
			return $this->strict_unserialize( $value, $alternative );
		}

		// Otherwise, attempt to decode it as JSON.
		$json_decoded = json_decode( $value, true );

		if ( json_last_error() === JSON_ERROR_NONE ) {
			return $json_decoded;
		}

		// If it's not JSON, return the raw value.
		return $value;
	}

	/**
	 * Validates that a value can be serialized safely.
	 *
	 * @internal
	 *
	 * @param mixed  $value       The value to serialize. Must be a scalar, array, stdClass object or NULL.
	 * @param string $alternative The alternative method to suggest in case of failure (for error messages).
	 * @return void
	 * @throws \Exception If the provided value cannot be safely serialized or unserialized.
	 */
	private function strict_serialize_check( $value, string $alternative ): void {
		try {
			Helper::strict_serialize( $value );
		} catch ( \UnexpectedValueException $e ) {
			throw new \Exception( esc_html( 'Unsafe or invalid data for serialization. Use ' . $alternative . ' instead.' ) );
		}
	}

	/**
	 * Unserializes a string, checking for allowed classes.
	 *
	 * @internal
	 *
	 * @param string $value       The serialized string to unserialize.
	 * @param string $alternative The alternative method to suggest in case of failure (for error messages).
	 * @return mixed The unserialized value.
	 * @throws \Exception If the data contains invalid/unauthorized classes during unserialization.
	 */
	private function strict_unserialize( string $value, string $alternative ) {
		try {
			$unserialized = Helper::strict_unserialize( $value );
		} catch ( \UnexpectedValueException $e ) {
			throw new \Exception( esc_html( 'Unsafe or invalid serialized data. Use ' . $alternative . ' instead.' ) );
		}

		return $unserialized;
	}
}
