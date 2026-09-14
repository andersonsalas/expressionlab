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
 * Exclusively functional in multisite environments, always targeting the main network context (`site_id`).
 *
 * @package ExpressionLab
 */
final class NetworkOptions {
	/**
	 * Live database instance.
	 *
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * Constructor.
	 *
	 * @internal
	 */
	public function __construct() {
		global $wpdb;

		$this->wpdb = $wpdb;
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
			throw new \Exception( esc_html( 'Network options are only available in multisite installations.' ) );
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

		// If it's a serialized string, attempt to unserialize it with strict checks.
		if ( is_serialized( $value ) ) {
			return $this->strict_unserialize( $value, 'NetworkOptions.get_raw()' );
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
			throw new \Exception( esc_html( 'Network options are only available in multisite installations.' ) );
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
			throw new \Exception( esc_html( 'Network options are only available in multisite installations.' ) );
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
			throw new \Exception( esc_html( 'Network options are only available in multisite installations.' ) );
		}

		if ( defined( 'EXPRESSION_LAB_DATABASE_READONLY' ) && EXPRESSION_LAB_DATABASE_READONLY ) {
			throw new \Exception( esc_html( 'Operation denied. Write protection is enabled. Set `EXPRESSION_LAB_DATABASE_READONLY` to `false` in `wp-config.php`.' ) );
		}

		$network_id = get_current_network_id();
		LanguageEngine::get()->tick();
		return delete_network_option( $network_id, $key );
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
