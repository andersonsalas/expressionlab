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
 * Options Service.
 *
 * Provides methods to inspect, manage, and audit WordPress site options stored in the `wp_options` table.
 *
 * Supports direct raw database access, custom serialization formats, and autoload storage diagnostics.
 *
 * @package ExpressionLab
 */
class Options {
	/**
	 * Format constant for JSON serialization.
	 *
	 * Represents a value that needs to be serialized as JSON.
	 */
	public const FORMAT_JSON = 'json';

	/**
	 * Format constant for PHP serialization.
	 *
	 * Represents a value that needs to be serialized using PHP's `serialize` function.
	 */
	public const FORMAT_SERIALIZED = 'serialized';

	/**
	 * Format constant for PHP serialization with object casting.
	 *
	 * Similar to `FORMAT_SERIALIZED` but the value will be casted to `stdClass`.
	 */
	public const FORMAT_SERIALIZED_OBJECT = 'serialized_object';

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
	 * Retrieves the exact, unmodified raw option value directly from the database.
	 *
	 * Queries the `option_value` column of the `wp_options` table without performing any
	 * deserialization or JSON decoding. Respects the active multisite blog context.
	 *
	 * Example:
	 *
	 * ```elscript
	 * Options.get_raw('cron')
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/options#optionsget_raw
	 *
	 * @param string $key           The option key.
	 * @param mixed  $default_value Optional. Default value returned if the option does not exist. Default `null`.
	 * @return string|mixed|null The raw string option value from the database, or `default_value` if not found.
	 */
	public function get_raw( string $key, $default_value = null ) {
		$engine_site_id = LanguageEngine::get()->get_site_id();
		$switched       = false;

		if ( null !== $engine_site_id && is_multisite() && get_current_blog_id() !== (int) $engine_site_id ) {
			switch_to_blog( (int) $engine_site_id );
			$switched = true;
		}

		try {
			// Retrieve raw option value for inspection before unserializing.
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- They are actually prepared.
			LanguageEngine::get()->tick();
			$query = $this->wpdb->prepare( "SELECT option_value FROM {$this->wpdb->options} WHERE option_name = %s", $key );
			$value = $this->wpdb->get_var( $query );
			LanguageEngine::get()->tick( $remaining_time, is_string( $value ) ? strlen( $value ) : 1 ); // The length of the value is a good proxy for the operation cost.
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

			return $value ?? $default_value;
		} finally {
			if ( $switched ) {
				restore_current_blog();
			}
		}
	}

	/**
	 * Retrieves an option value, decoding JSON strings or unserializing allowed types.
	 *
	 * If the stored value is a serialized string, it is unserialized allowing only `stdClass`
	 * instances, arrays, and scalar types. Unserialized values containing unauthorized classes
	 * trigger an exception.
	 *
	 * If the value is valid JSON, it is parsed into an associative array. Scalar values (strings,
	 * integers, booleans) and unparsed strings are returned directly.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * Options.get(
	 *      'my_plugin_settings',
	 *      { 'enabled': false, 'timeout': 30 }
	 * )
	 * ```
	 *
	 * ```elscript
	 * Options.get('active_plugins')
	 * ```
	 *
	 * @see https://developer.wordpress.org/reference/functions/get_option/
	 * @see https://expressionlab.io/docs/api-reference/options#optionsget
	 *
	 * @param string $key           The option key.
	 * @param mixed  $default_value Optional. Default value returned if the option does not exist. Default `null`.
	 * @return mixed The processed option value, or `default_value` if not found.
	 * @throws \Exception If the serialized data contains unauthorized PHP classes.
	 */
	public function get( string $key, $default_value = null ) {
		$value = $this->get_raw( $key, $default_value );

		// If it's not a string, it can't be serialized or JSON (probably an array or direct object).
		if ( ! is_string( $value ) ) {
			return $value;
		}

		// If it's a serialized string, attempt to unserialize it with strict checks.
		if ( is_serialized( $value ) ) {
			return $this->strict_unserialize( $value, 'Options.get_raw()' );
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
	 * Updates or inserts a raw option value directly in the database without serialization or encoding.
	 *
	 * Enforces write protection via the `EXPRESSION_LAB_DATABASE_READONLY` constant. On update, ensures
	 * atomic cache coherence by purging the key from the WordPress object cache (`wp_cache_delete`)
	 * and invalidating the global `alloptions` cache if present.
	 *
	 * Example:
	 *
	 * ```elscript
	 * Options.update_raw(
	 *      'heavy_cache_entry',
	 *      'a:1:{s:4:"data";s:6:"sample";}',
	 *      false
	 * )
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/options#optionsupdate_raw
	 *
	 * @param string           $key      The option key.
	 * @param string           $value    The raw string payload to store.
	 * @param string|bool|null $autoload Optional. Controls autoload behavior (`'yes'` or `true`; `'no'` or `false`). If `null`, preserves existing autoload setting on update, or defaults to `'no'` on insert.
	 * @return bool `true` if value was updated or inserted, `false` if unchanged or on database error.
	 * @throws \Exception If write protection is active (`EXPRESSION_LAB_DATABASE_READONLY` is true).
	 */
	public function update_raw( string $key, string $value, $autoload = null ): bool {
		if ( defined( 'EXPRESSION_LAB_DATABASE_READONLY' ) && EXPRESSION_LAB_DATABASE_READONLY ) {
			throw new \Exception( esc_html( 'Operation denied. Write protection is enabled. Set `EXPRESSION_LAB_DATABASE_READONLY` to `false` in `wp-config.php`.' ) );
		}

		$engine_site_id = LanguageEngine::get()->get_site_id();
		$switched       = false;

		if ( null !== $engine_site_id && is_multisite() && get_current_blog_id() !== (int) $engine_site_id ) {
			switch_to_blog( (int) $engine_site_id );
			$switched = true;
		}

		try {
			$old_value = $this->get_raw( $key );

			// Normalize autoload if provided.
			if ( null !== $autoload ) {
				if ( is_bool( $autoload ) ) {
					$autoload = $autoload ? 'yes' : 'no';
				} else {
					$autoload = (string) $autoload;
				}
			}

			// Do not update if the new value is the same as the old value and autoload is not changing.
			if ( $value === $old_value && null === $autoload ) {
				return false;
			}

			LanguageEngine::get()->tick( $remaining_time, strlen( $value ) );

			// Determine if it's an INSERT or UPDATE.
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- They are actually prepared.
			$query         = $this->wpdb->prepare( "SELECT count(*) FROM {$this->wpdb->options} WHERE option_name = %s", $key );
			$option_exists = ( (int) $this->wpdb->get_var( $query ) ) > 0;
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

			$result = false;

			if ( $option_exists ) {
				// Update value and optionally autoload.
				$update_data   = array( 'option_value' => $value );
				$update_format = array( '%s' );

				if ( null !== $autoload ) {
					$update_data['autoload'] = $autoload;
					$update_format[]         = '%s';
				}

				$result = $this->wpdb->update(
					$this->wpdb->options,
					$update_data,
					array( 'option_name' => $key ),
					$update_format,
					array( '%s' ) // Where format.
				);
			} else {
				// Insert.
				$autoload = ( null === $autoload ) ? 'no' : $autoload; // Defaults to 'no' if not specified.
				$result   = $this->wpdb->insert(
					$this->wpdb->options,
					array(
						'option_name'  => $key,
						'option_value' => $value,
						'autoload'     => $autoload,
					),
					array( '%s', '%s', '%s' )
				);
			}

			if ( false === $result ) {
				return false; // Database error.
			}

			// Clear the cache.
			wp_cache_delete( $key, 'options' );

			// Since we can't be sure if it was autoload without another select, we clear it for safety.
			$alloptions = wp_load_alloptions();

			if ( isset( $alloptions[ $key ] ) ) {
				wp_cache_delete( 'alloptions', 'options' );
				LanguageEngine::get()->tick( $remaining_time, 1000 ); // Huge penalization here.
			}

			return true;
		} finally {
			if ( $switched ) {
				restore_current_blog();
			}
		}
	}

	/**
	 * Updates an existing option or creates a new one with strict serialization safety checks.
	 *
	 * Enforces write protection (`EXPRESSION_LAB_DATABASE_READONLY`) and format conflict protections
	 * to prevent silent data corruption:
	 * - If the existing option is PHP-serialized, updating with `Options.FORMAT_JSON` is rejected.
	 * - If the existing option is JSON, updating with PHP serialization is rejected.
	 * - Pre-flight unserialization detects non-`stdClass` objects and throws an exception to avoid class loss.
	 *
	 * For intentional format conversions or low-level writes, use `Options.update_raw()` instead.
	 *
	 * Example:
	 *
	 * ```elscript
	 * Options.update('my_plugin_settings', {
	 *     'api_key': 'sk_live_12345',
	 *     'debug_mode': true,
	 *     'retries': 3
	 * })
	 * ```
	 *
	 * ```elscript
	 * Options.update(
	 *      'my_app_state',
	 *      {
	 *          'theme': 'dark',
	 *          'notifications': true
	 *      },
	 *      Options.FORMAT_JSON
	 * )
	 * ```
	 *
	 * @see https://developer.wordpress.org/reference/functions/update_option/
	 * @see https://expressionlab.io/docs/api-reference/options#optionsupdate
	 *
	 * @param string $key                The option key.
	 * @param mixed  $value              The option value to store.
	 * @param string $serialization_type Optional. Serialization format: `Options.FORMAT_SERIALIZED`,
	 *                                   `Options.FORMAT_SERIALIZED_OBJECT`, or `Options.FORMAT_JSON`.
	 *                                   Default `Options.FORMAT_SERIALIZED`.
	 * @return bool `true` if value was updated or inserted, `false` if unchanged or on database error.
	 * @throws \Exception If write protection is active, serialization fails, or a format mismatch is detected.
	 */
	public function update( string $key, $value, string $serialization_type = self::FORMAT_SERIALIZED ): bool {
		if ( defined( 'EXPRESSION_LAB_DATABASE_READONLY' ) && EXPRESSION_LAB_DATABASE_READONLY ) {
			throw new \Exception( esc_html( 'Operation denied. Write protection is enabled. Set `EXPRESSION_LAB_DATABASE_READONLY` to `false` in `wp-config.php`.' ) );
		}

		$engine_site_id = LanguageEngine::get()->get_site_id();
		$switched       = false;

		if ( null !== $engine_site_id && is_multisite() && get_current_blog_id() !== (int) $engine_site_id ) {
			switch_to_blog( (int) $engine_site_id );
			$switched = true;
		}

		try {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- They are actually prepared.
			LanguageEngine::get()->tick();
			$query         = $this->wpdb->prepare( "SELECT count(*) FROM {$this->wpdb->options} WHERE option_name = %s", $key );
			$option_exists = ( (int) $this->wpdb->get_var( $query ) ) > 0;
			LanguageEngine::get()->tick();
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

			if ( $option_exists ) {
				$raw_value = $this->get_raw( $key );

				if ( is_string( $raw_value ) && is_serialized( $raw_value ) ) {
					// Safety check: prevent accidental overwriting of serialized data with incompatible formats.
					if ( self::FORMAT_JSON === $serialization_type ) {
						throw new \Exception( esc_html( 'Cannot update with JSON serialization because the existing value is serialized. Use `Options.update_raw()` instead.' ) );
					}

					// Safety check: prevent class information loss if the existing value is serialized and contains objects different from stdClass.
					$this->strict_serialize_check( $raw_value, 'Options.update_raw()' );
				}

				if ( is_string( $raw_value ) ) {
					// Safety check: prevent accidental overwriting of JSON data with incompatible formats.
					$decoded = json_decode( $raw_value, true );
					if ( json_last_error() === JSON_ERROR_NONE && self::FORMAT_JSON !== $serialization_type ) {
						throw new \Exception( esc_html( 'Cannot update with PHP serialization because the existing value is JSON. Use `Options.update_raw()` instead.' ) );
					}
				}
			}

			if ( self::FORMAT_SERIALIZED_OBJECT === $serialization_type ) {
				$value = (object) $value;
			} elseif ( self::FORMAT_JSON === $serialization_type ) {
				$value = wp_json_encode( $value );
			}

			if ( self::FORMAT_JSON !== $serialization_type ) {
				// Safety check: reject any serialized value that contains non-stdClass objects.
				$this->strict_serialize_check( $value, 'Options.update_raw()' );
			}

			return update_option( $key, $value );
		} finally {
			if ( $switched ) {
				restore_current_blog();
			}
		}
	}

	/**
	 * Deletes an option from the database and purges related object cache entries.
	 *
	 * Enforces write protection via `EXPRESSION_LAB_DATABASE_READONLY`. Respects the active
	 * multisite blog context.
	 *
	 * Example:
	 *
	 * ```elscript
	 * Options.delete('obsolete_plugin_config')
	 * ```
	 *
	 * @see https://developer.wordpress.org/reference/functions/delete_option/
	 * @see https://expressionlab.io/docs/api-reference/options#optionsdelete
	 *
	 * @param string $key The option key to remove.
	 * @return bool `true` on success, `false` on failure.
	 * @throws \Exception If write protection is active (`EXPRESSION_LAB_DATABASE_READONLY` is true).
	 */
	public function delete( string $key ): bool {
		if ( defined( 'EXPRESSION_LAB_DATABASE_READONLY' ) && EXPRESSION_LAB_DATABASE_READONLY ) {
			throw new \Exception( esc_html( 'Operation denied. Write protection is enabled. Set `EXPRESSION_LAB_DATABASE_READONLY` to `false` in `wp-config.php`.' ) );
		}

		$engine_site_id = LanguageEngine::get()->get_site_id();
		$switched       = false;

		if ( null !== $engine_site_id && is_multisite() && get_current_blog_id() !== (int) $engine_site_id ) {
			switch_to_blog( (int) $engine_site_id );
			$switched = true;
		}

		try {
			LanguageEngine::get()->tick();
			$result = delete_option( $key );

			return $result;
		} finally {
			if ( $switched ) {
				restore_current_blog();
			}
		}
	}

	/**
	 * Calculates a statistical breakdown and generates interactive visualizations of option prefixes and autoload ratios in the database.
	 *
	 * Executes a memory-optimized heuristic scan of the `wp_options` table and automatically
	 * renders interactive Vega-Lite visualizations (bar chart of prefix sizes, pie chart of
	 * autoload ratios) alongside detailed metric breakdown tables.
	 *
	 * Because this method queries and groups option keys, it should be used with caution on
	 * large databases. The `sample_limit` parameter allows constraining the sampled row count.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * Options.stats()
	 * ```
	 *
	 * ```elscript
	 * Options.stats(50000, 10)
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/options#optionsstats
	 *
	 * @param int|null $sample_limit Optional. The maximum number of options to sample for distribution calculation. Default `100000`.
	 * @param int      $graph_limit  Optional. The maximum number of prefixes to include in the prefix graph visualization. Default `20`.
	 * @return array{prefixes: array, autoload: array} Prefix metrics and autoload distribution data.
	 */
	public function stats( ?int $sample_limit = 100000, int $graph_limit = 20 ): array {
		LanguageEngine::get()->tick();

		$sample_limit   = $sample_limit ?? 100000;
		$engine_site_id = LanguageEngine::get()->get_site_id();
		$switched       = false;

		if ( null !== $engine_site_id && is_multisite() && get_current_blog_id() !== (int) $engine_site_id ) {
			switch_to_blog( (int) $engine_site_id );
			$switched = true;
		}

		try {
			// 1. Prefix distribution calculation.
			$prefixes = $this->calculate_prefix_distribution( $sample_limit );

			if ( ! empty( $prefixes ) ) {
				// Generate table for prefix distribution.
				LanguageEngine::get()->begin_visualization_group()->add_visualization(
					array(
						'type'  => 'table',
						'title' => 'Table: Prefix distribution',
						'data'  => $prefixes,
					)
				);

				// Generate graph for prefix distribution.
				LanguageEngine::get()->add_visualization(
					array(
						'type'  => 'graph',
						'title' => 'Graph: Prefix distribution',
						'data'  => array(
							'$schema'     => 'https://vega.github.io/schema/vega-lite/v6.json',
							'description' => 'Prefix distribution graph',
							'title'       => 'Option Prefix Distribution',
							'width'       => 'container',
							'height'      => 400,
							'padding'     => 5,
							'data'        => array(
								'values' => array_slice( $prefixes, 0, $graph_limit ),
							),
							'mark'        => array(
								'type'    => 'bar',
								'tooltip' => true,
							),
							'encoding'    => array(
								'x'     => array(
									'field' => 'prefix',
									'type'  => 'nominal',
									'axis'  => array(
										'title'      => 'Prefix',
										'labelAngle' => -45,
									),
									'sort'  => '-y',
								),
								'y'     => array(
									'field' => 'count',
									'type'  => 'quantitative',
									'axis'  => array(
										'title' => 'Count',
									),
								),
								'color' => array(
									'value' => '#3858e9',
								),
							),
						),
					)
				);
			}

			// 2. Autoloaded vs non-autoloaded distribution.
			$autoload = $this->calculate_autoload_distribution( $sample_limit );

			if ( ! empty( $autoload ) ) {
				// Generate table for autoload distribution.
				LanguageEngine::get()->add_visualization(
					array(
						'type'  => 'table',
						'title' => 'Table: Autoload distribution',
						'data'  => $autoload,
					)
				);

				// Generate graph for autoload distribution (pie chart).
				LanguageEngine::get()->add_visualization(
					array(
						'type'  => 'graph',
						'title' => 'Graph: Autoload distribution',
						'data'  => array(
							'$schema'     => 'https://vega.github.io/schema/vega-lite/v6.json',
							'description' => 'Autoload distribution graph',
							'title'       => 'Autoload Distribution',
							'width'       => 'container',
							'height'      => 300,
							'padding'     => 20,
							'data'        => array(
								'values' => $autoload,
							),
							'mark'        => array(
								'type'    => 'arc',
								'tooltip' => true,
							),
							'encoding'    => array(
								'theta' => array(
									'field' => 'count',
									'type'  => 'quantitative',
								),
								'color' => array(
									'field'  => 'autoload',
									'type'   => 'nominal',
									'legend' => array(
										'title' => 'Autoload distribution',
									),
								),
							),
						),
					),
				);
			}

			return array(
				'prefixes' => $prefixes,
				'autoload' => $autoload,
			);
		} finally {
			if ( $switched ) {
				restore_current_blog();
			}
		}
	}

	/**
	 * Calculates the prefix distribution of option keys in the database.
	 *
	 * @internal
	 *
	 * @param int $sample_limit The maximum number of options to sample for distribution calculation. Default 100000.
	 * @return array An array of prefix distribution data.
	 */
	private function calculate_prefix_distribution( int $sample_limit = 100000 ): array {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- They are actually prepared.
		$query = $this->wpdb->prepare(
			"SELECT 
				-- PREFIX HEURISTICS --
				CASE
					-- 1. Group all transients (WP temporary cache)
					WHEN option_name LIKE %s OR option_name LIKE %s THEN '(Transients)'
					
					-- 2. Detect normal prefixes
					ELSE 
						CASE
							-- If no underscore, it's an orphan or root option (e.g. siteurl)
							WHEN LOCATE('_', option_name) = 0 THEN '(No prefix)'
							
							-- If starts with underscore (e.g. _my_plugin), ignore the first underscore
							WHEN SUBSTRING(option_name, 1, 1) = '_' THEN 
								SUBSTRING_INDEX(SUBSTRING(option_name, 2), '_', 1)
							
							-- \"Smart\" Logic: If prefix is very short (<3 chars) or numeric, take the first 2 blocks
							-- E.g. \"wp_user_roles\" -> returns \"wp_user\" instead of \"wp\"
							WHEN LENGTH(SUBSTRING_INDEX(option_name, '_', 1)) <= 3 
								OR SUBSTRING_INDEX(option_name, '_', 1) REGEXP '^[0-9]+\$' THEN 
								SUBSTRING_INDEX(option_name, '_', 2)
							
							-- Default, take up to the first underscore
							ELSE SUBSTRING_INDEX(option_name, '_', 1)
						END
				END AS prefix,

				-- QUANTITY METRICS --
				COUNT(*) AS count,

				-- WEIGHT METRICS (True performance impact) --
				ROUND(SUM(val_len) / 1024, 2) AS size_kb,

				-- AUTOLOAD: Critical data for TTFB --
				ROUND(SUM(
					CASE 
						WHEN autoload IN ('yes', 'on', 'auto', '1') THEN val_len 
						ELSE 0 
					END
				) / 1024, 2) AS autoload_size_kb,
				
				-- DANGER PERCENTAGE (percent of this group loaded on every visit) --
				ROUND(
					(SUM(CASE WHEN autoload IN ('yes', 'on', 'auto', '1') THEN val_len ELSE 0 END) / 
					NULLIF(SUM(val_len), 0)) * 100, 
				0) AS autoload_percentage

			FROM (SELECT option_name, LENGTH(option_value) AS val_len, autoload FROM {$this->wpdb->options} LIMIT %d) AS subquery
			GROUP BY prefix
			ORDER BY size_kb DESC
			LIMIT 50;",
			'_transient_%',
			'_site_transient_%',
			$sample_limit
		);

		LanguageEngine::get()->tick();
		$prefixes = $this->wpdb->get_results( $query, ARRAY_A );
		LanguageEngine::get()->tick();
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		if ( ! is_array( $prefixes ) || empty( $prefixes ) ) {
			return array();
		}

		// Cast count to integer for better visualization and consistency.
		foreach ( $prefixes as &$item ) {
			$item['count']               = (int) $item['count'];
			$item['size_kb']             = (float) $item['size_kb'];
			$item['autoload_size_kb']    = (float) $item['autoload_size_kb'];
			$item['autoload_percentage'] = (float) $item['autoload_percentage'];
		}

		return $prefixes;
	}

	/**
	 * Calculates the distribution of autoloaded vs non-autoloaded options in the database.
	 *
	 * @internal
	 *
	 * @param int $sample_limit The maximum number of options to sample for distribution calculation. Default 100000.
	 * @return array An array of autoload distribution data.
	 */
	private function calculate_autoload_distribution( int $sample_limit = 100000 ): array {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- They are actually prepared.
		$query = $this->wpdb->prepare(
			"SELECT 
				autoload, 
				COUNT(*) as count, 
				ROUND(SUM( val_len ) / 1024, 2) as size_kb
			FROM (SELECT autoload, LENGTH(option_value) AS val_len FROM {$this->wpdb->options} LIMIT %d) AS subquery
			GROUP BY autoload
			ORDER BY count DESC",
			$sample_limit
		);
		LanguageEngine::get()->tick();
		$autoload = $this->wpdb->get_results( $query, ARRAY_A );
		LanguageEngine::get()->tick();
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		if ( ! is_array( $autoload ) || empty( $autoload ) ) {
			return array();
		}

		// Cast string values to more readable formats for visualization.
		foreach ( $autoload as &$item ) {
			$item['size_kb'] = (float) $item['size_kb'];
			$item['count']   = (int) $item['count'];
		}

		LanguageEngine::get()->tick();

		return $autoload;
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
			throw new \Exception( esc_html( 'Unsafe or invalid data for serialization. Use `' . $alternative . '` instead.' ) );
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
			throw new \Exception( esc_html( 'Unsafe or invalid serialized data. Use `' . $alternative . '` instead.' ) );
		}

		return $unserialized;
	}
}
