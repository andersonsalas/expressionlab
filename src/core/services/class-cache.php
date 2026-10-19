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

use ExpressionLab\Core\Interfaces\ServiceInterface;
use ExpressionLab\Core\LanguageEngine;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * Cache Service.
 *
 * Provides methods to inspect, diagnose, and manipulate the WordPress object cache subsystem.
 *
 * @since 0.3.0
 *
 * @package ExpressionLab
 */
final class Cache implements ServiceInterface {

	/**
	 * Ensures write protection is disabled.
	 *
	 * @internal
	 *
	 * @throws \Exception If database write protection is active.
	 */
	private function validate_write_permission(): void {
		if ( defined( 'EXPRESSION_LAB_DATABASE_READONLY' ) && EXPRESSION_LAB_DATABASE_READONLY ) {
			throw new \Exception( esc_html( 'Operation denied. Write protection is enabled. Set `EXPRESSION_LAB_DATABASE_READONLY` to `false` in `wp-config.php`.' ) );
		}
	}

	/**
	 * Validates that a cache key is a non-empty string or an integer.
	 *
	 * Whitespace-only strings are rejected as well, mirroring `WP_Object_Cache::is_valid_key()`,
	 * so invalid keys never reach the backend (which would emit a `_doing_it_wrong()` notice).
	 *
	 * @internal
	 *
	 * @param mixed $key The cache key to validate.
	 * @throws \InvalidArgumentException If the key is not a string or integer, or is an empty string.
	 */
	private function validate_key( $key ): void {
		if ( ( ! is_string( $key ) && ! is_int( $key ) ) || ( is_string( $key ) && '' === trim( $key ) ) ) {
			throw new \InvalidArgumentException( esc_html( 'Invalid cache key. Keys must be non-empty strings or integers.' ) );
		}
	}

	/**
	 * Validates that a cache group is a string.
	 *
	 * @internal
	 *
	 * @param mixed $group The cache group to validate.
	 * @throws \InvalidArgumentException If the group is not a string.
	 */
	private function validate_group( $group ): void {
		if ( ! is_string( $group ) ) {
			throw new \InvalidArgumentException( esc_html( 'Invalid cache group. Groups must be strings.' ) );
		}
	}

	/**
	 * Validates that a cache expiration is a non-negative integer number of seconds.
	 *
	 * Negative values are rejected because several persistent backends silently convert them
	 * into entries that never expire.
	 *
	 * @internal
	 *
	 * @param mixed $expire The expiration time in seconds.
	 * @throws \InvalidArgumentException If the expiration is not a non-negative integer.
	 */
	private function validate_expire( $expire ): void {
		if ( ! is_int( $expire ) || $expire < 0 ) {
			throw new \InvalidArgumentException( esc_html( 'Invalid cache expiration. Expiration must be a non-negative integer number of seconds.' ) );
		}
	}

	/**
	 * Validates that a value argument was provided when setting a scalar cache key.
	 *
	 * @internal
	 *
	 * @param int $arg_count The number of arguments passed to the caller.
	 * @throws \InvalidArgumentException If the value argument was omitted.
	 */
	private function validate_value_presence( int $arg_count ): void {
		if ( $arg_count < 2 ) {
			throw new \InvalidArgumentException( esc_html( 'Cache::set() requires a value when setting a scalar key.' ) );
		}
	}

	/**
	 * Reads the first available property from the active object cache backend.
	 *
	 * Drop-ins expose diagnostics under different property names (e.g. `non_persistent_groups`,
	 * `ignored_groups`, `no_mc_groups`). Reads go through `isset()` so that missing or protected
	 * properties are skipped, and any exception raised by magic accessors is contained.
	 *
	 * @internal
	 *
	 * @param mixed    $backend    The object cache instance.
	 * @param string[] $properties Candidate property names, in priority order.
	 * @return mixed The property value, or null if none is readable.
	 */
	private function read_backend_property( $backend, array $properties ) {
		if ( ! is_object( $backend ) ) {
			return null;
		}

		foreach ( $properties as $property ) {
			try {
				if ( isset( $backend->$property ) ) {
					return $backend->$property;
				}
			} catch ( \Throwable $e ) {
				continue;
			}
		}

		return null;
	}

	/**
	 * Normalizes a backend group registry into a flat list of group names.
	 *
	 * Core's `WP_Object_Cache` stores groups as a `group => true` map, while most drop-ins
	 * store plain lists of group names. Both shapes are supported.
	 *
	 * @internal
	 *
	 * @param mixed $groups The raw group registry.
	 * @return string[] Unique group names.
	 */
	private function normalize_groups( $groups ): array {
		if ( ! is_array( $groups ) ) {
			return array();
		}

		$names = array();

		foreach ( $groups as $index => $value ) {
			if ( is_string( $value ) ) {
				$names[] = $value;
			} elseif ( is_string( $index ) ) {
				$names[] = $index;
			}
		}

		return array_values( array_unique( $names ) );
	}

	/**
	 * Checks whether the site uses an external persistent object cache.
	 *
	 * Returns `true` if an external object cache drop-in (such as Redis, Memcached, etc.)
	 * is active, or `false` if WordPress is using the default non-persistent in-memory cache.
	 *
	 * Example:
	 *
	 * ```elscript
	 * Cache.is_persistent()
	 * ```
	 *
	 * @see https://developer.wordpress.org/reference/functions/wp_using_ext_object_cache/
	 * @see https://expressionlab.io/docs/api-reference/cache#cacheis_persistent
	 *
	 * @return bool True if an external persistent object cache is in use, false otherwise.
	 */
	public function is_persistent(): bool {
		LanguageEngine::get()->tick();
		return (bool) wp_using_ext_object_cache();
	}

	/**
	 * Retrieves runtime metadata and diagnostic status of the object cache subsystem.
	 *
	 * Inspects whether an external drop-in is active, the underlying object cache class,
	 * registered global groups, non-persistent groups, and current blog context.
	 *
	 * Example:
	 *
	 * ```elscript
	 * Cache.status()
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/cache#cachestatus
	 *
	 * @return array Status diagnostic report.
	 */
	public function status(): array {
		$engine_site_id = LanguageEngine::get()->get_site_id();
		$switched       = false;

		if ( null !== $engine_site_id && is_multisite() && get_current_blog_id() !== (int) $engine_site_id ) {
			switch_to_blog( (int) $engine_site_id );
			$switched = true;
		}

		try {
			LanguageEngine::get()->tick();

			global $wp_object_cache;

			$dropin_file   = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/object-cache.php' : ABSPATH . 'wp-content/object-cache.php';
			$dropin_exists = file_exists( $dropin_file );
			$backend       = is_object( $wp_object_cache ) ? get_class( $wp_object_cache ) : 'none';

			$global_groups         = $this->normalize_groups( $this->read_backend_property( $wp_object_cache, array( 'global_groups' ) ) );
			$non_persistent_groups = $this->normalize_groups( $this->read_backend_property( $wp_object_cache, array( 'non_persistent_groups', 'ignored_groups', 'no_mc_groups' ) ) );

			return array(
				'is_persistent'         => (bool) wp_using_ext_object_cache(),
				'backend'               => $backend,
				'dropin_exists'         => $dropin_exists,
				'dropin_path'           => $dropin_exists ? $dropin_file : null,
				'global_groups'         => $global_groups,
				'non_persistent_groups' => $non_persistent_groups,
				'multisite_blog_id'     => is_multisite() ? get_current_blog_id() : null,
			);
		} finally {
			if ( $switched ) {
				restore_current_blog();
			}
		}
	}

	/**
	 * Retrieves runtime performance metrics and statistics from the object cache.
	 *
	 * Returns cache hits, misses, total operations, hit ratio percentage, and persistence state.
	 *
	 * Counters belong to the request-wide object cache instance (not to a specific site), so
	 * no multisite context switch is performed: switching would itself generate cache reads
	 * and distort the reported figures.
	 *
	 * Example:
	 *
	 * ```elscript
	 * Cache.stats()
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/cache#cachestats
	 *
	 * @return array Statistical report.
	 */
	public function stats(): array {
		LanguageEngine::get()->tick();

		global $wp_object_cache;

		$raw_hits   = $this->read_backend_property( $wp_object_cache, array( 'cache_hits' ) );
		$raw_misses = $this->read_backend_property( $wp_object_cache, array( 'cache_misses' ) );
		$hits       = is_numeric( $raw_hits ) ? max( 0, (int) $raw_hits ) : 0;
		$misses     = is_numeric( $raw_misses ) ? max( 0, (int) $raw_misses ) : 0;

		$total = $hits + $misses;
		$ratio = $total > 0 ? sprintf( '%.2f%%', ( $hits / $total ) * 100 ) : '0.00%';

		$chart_data = array(
			array(
				'metric' => 'Hits',
				'count'  => $hits,
			),
			array(
				'metric' => 'Misses',
				'count'  => $misses,
			),
		);

		$table_data = array(
			array(
				'metric'     => 'Hits',
				'count'      => $hits,
				'percentage' => $ratio,
			),
			array(
				'metric'     => 'Misses',
				'count'      => $misses,
				'percentage' => $total > 0 ? sprintf( '%.2f%%', ( $misses / $total ) * 100 ) : '0.00%',
			),
		);

		if ( 0 === $total ) {
			LanguageEngine::get()->add_message( 'warning', 'No cache operations recorded to display statistics.' );
		} else {
			LanguageEngine::get()->begin_visualization_group()->add_visualization(
				array(
					'type'  => 'table',
					'title' => 'Table: Cache hit distribution',
					'data'  => $table_data,
				)
			);

			LanguageEngine::get()->add_visualization(
				array(
					'type'  => 'graph',
					'title' => 'Graph: Cache hit distribution',
					'data'  => array(
						'$schema'     => 'https://vega.github.io/schema/vega-lite/v6.json',
						'description' => 'Cache hit and miss distribution graph',
						'title'       => 'Cache Hit vs Miss Distribution',
						'width'       => 'container',
						'height'      => 300,
						'padding'     => 20,
						'data'        => array(
							'values' => $chart_data,
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
								'field'  => 'metric',
								'type'   => 'nominal',
								'legend' => array(
									'title' => 'Metric',
								),
							),
						),
					),
				)
			);
		}

		return array(
			'hits'          => $hits,
			'misses'        => $misses,
			'total'         => $total,
			'hit_ratio'     => $ratio,
			'is_persistent' => (bool) wp_using_ext_object_cache(),
		);
	}

	/**
	 * Retrieves cached data for a specific key or batch of keys within a group.
	 *
	 * When a single scalar key is provided, returns its cached value or `default_value` if not found.
	 *
	 * When an array of keys is provided, returns an associative array mapping each key to its
	 * cached value or `default_value`.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * Cache.get('active_plugins', 'options')
	 * ```
	 *
	 * ```elscript
	 * Cache.get(['post_1', 'post_2'], 'posts', false, null)
	 * ```
	 *
	 * @see https://developer.wordpress.org/reference/functions/wp_cache_get/
	 * @see https://developer.wordpress.org/reference/functions/wp_cache_get_multiple/
	 * @see https://expressionlab.io/docs/api-reference/cache#cacheget
	 *
	 * @param string|int|array $key           The cache key or array of cache keys to retrieve.
	 * @param string           $group         Optional. Where the cache contents are grouped. Default empty string.
	 * @param bool             $force         Optional. Whether to force an update of the local cache from the persistent cache. Default false.
	 * @param mixed            $default_value Optional. Default value returned if the key is not found in cache. Default null.
	 * @return mixed The cached value(s) or default value if not found.
	 * @throws \InvalidArgumentException If the key is not a string or integer, or is an empty string.
	 */
	public function get( $key, string $group = '', bool $force = false, $default_value = null ) {
		$engine_site_id = LanguageEngine::get()->get_site_id();
		$switched       = false;

		if ( null !== $engine_site_id && is_multisite() && get_current_blog_id() !== (int) $engine_site_id ) {
			switch_to_blog( (int) $engine_site_id );
			$switched = true;
		}

		try {
			if ( is_array( $key ) ) {
				$count          = max( 1, count( $key ) );
				$remaining_time = null;
				LanguageEngine::get()->tick( $remaining_time, $count );

				if ( empty( $key ) ) {
					return array();
				}

				foreach ( $key as $k ) {
					$this->validate_key( $k );
				}

				$results = array();

				if ( function_exists( 'wp_cache_get_multiple' ) ) {
					$fetched = wp_cache_get_multiple( $key, $group, $force );
					foreach ( $key as $k ) {
						$k_str = (string) $k;
						if ( is_array( $fetched ) && array_key_exists( $k, $fetched ) ) {
							$val = $fetched[ $k ];
							if ( false === $val ) {
								$found = false;
								wp_cache_get( $k, $group, false, $found );
								$results[ $k_str ] = $found ? false : $default_value;
							} else {
								$results[ $k_str ] = $val;
							}
						} else {
							$results[ $k_str ] = $default_value;
						}
					}
				} else {
					foreach ( $key as $k ) {
						$found                  = false;
						$val                    = wp_cache_get( $k, $group, $force, $found );
						$results[ (string) $k ] = $found ? $val : $default_value;
					}
				}

				return $results;
			}

			LanguageEngine::get()->tick();
			$this->validate_key( $key );
			$found = false;
			$value = wp_cache_get( $key, $group, $force, $found );

			return $found ? $value : $default_value;
		} finally {
			if ( $switched ) {
				restore_current_blog();
			}
		}
	}

	/**
	 * Determines whether a specific key exists in the cache group.
	 *
	 * Unlike `get()`, which returns false or default for non-existent keys, `has()`
	 * distinguishes definitively between a missing key and a key storing boolean `false`.
	 *
	 * Example:
	 *
	 * ```elscript
	 * Cache.has('user_meta_cache', 'users')
	 * ```
	 *
	 * @see https://developer.wordpress.org/reference/functions/wp_cache_get/
	 * @see https://expressionlab.io/docs/api-reference/cache#cachehas
	 *
	 * @param string|int $key   The cache key to check.
	 * @param string     $group Optional. Where the cache contents are grouped. Default empty string.
	 * @return bool True if the key exists in cache, false otherwise.
	 * @throws \InvalidArgumentException If the key is not a string or integer, or is an empty string.
	 */
	public function has( $key, string $group = '' ): bool {
		$engine_site_id = LanguageEngine::get()->get_site_id();
		$switched       = false;

		if ( null !== $engine_site_id && is_multisite() && get_current_blog_id() !== (int) $engine_site_id ) {
			switch_to_blog( (int) $engine_site_id );
			$switched = true;
		}

		try {
			LanguageEngine::get()->tick();
			$this->validate_key( $key );
			$found = false;
			wp_cache_get( $key, $group, false, $found );
			return (bool) $found;
		} finally {
			if ( $switched ) {
				restore_current_blog();
			}
		}
	}

	/**
	 * Sets the value of one or multiple cache keys.
	 *
	 * Supports both single-key and batch associative array signatures.
	 *
	 * Single key:
	 *
	 * ```elscript
	 * Cache.set('key', value, 'group', 3600)
	 * ```
	 *
	 * Batch keys:
	 *
	 * ```elscript
	 * Cache.set({ 'k1': 'v1', 'k2': 'v2' }, 'group', 3600)
	 * ```
	 *
	 * Examples:
	 *
	 * ```elscript
	 * Cache.set('sample_token', 'xyz-123', 'tokens', 300)
	 * ```
	 *
	 * ```elscript
	 * Cache.set({ 'token_1': 'abc', 'token_2': 'def' }, 'tokens', 600)
	 * ```
	 *
	 * @see https://developer.wordpress.org/reference/functions/wp_cache_set/
	 * @see https://developer.wordpress.org/reference/functions/wp_cache_set_multiple/
	 * @see https://expressionlab.io/docs/api-reference/cache#cacheset
	 *
	 * @param string|int|array $key    The cache key (string/int) or associative array pairs.
	 * @param mixed            $data   Optional. The cache data when `key` is scalar (required if `key` is scalar), or the group name (string) when `key` is an array.
	 * @param string|int       $group  Optional. The cache group (string) when `key` is scalar, or expiration (int) when `key` is an array.
	 * @param int              $expire Optional. Non-negative expiration time in seconds. Default 0 (no expiration).
	 * @return bool True on success, false on failure.
	 * @throws \Exception If write protection is active (`EXPRESSION_LAB_DATABASE_READONLY` is true).
	 * @throws \InvalidArgumentException If a key, group, or expiration is invalid, or if the value is missing for a scalar key.
	 */
	public function set( $key, $data = null, $group = '', int $expire = 0 ): bool {
		$this->validate_write_permission();

		$engine_site_id = LanguageEngine::get()->get_site_id();
		$switched       = false;

		if ( null !== $engine_site_id && is_multisite() && get_current_blog_id() !== (int) $engine_site_id ) {
			switch_to_blog( (int) $engine_site_id );
			$switched = true;
		}

		try {
			if ( is_array( $key ) ) {
				$actual_group   = null === $data ? '' : $data;
				$actual_expire  = ( '' === $group || null === $group ) ? $expire : $group;
				$count          = max( 1, count( $key ) );
				$remaining_time = null;

				LanguageEngine::get()->tick( $remaining_time, $count );

				foreach ( array_keys( $key ) as $k ) {
					$this->validate_key( $k );
				}

				$this->validate_group( $actual_group );
				$this->validate_expire( $actual_expire );

				if ( function_exists( 'wp_cache_set_multiple' ) ) {
					$results = wp_cache_set_multiple( $key, $actual_group, $actual_expire );
					return is_array( $results ) && ! in_array( false, $results, true );
				}

				$success = true;

				foreach ( $key as $k => $v ) {
					$success = wp_cache_set( $k, $v, $actual_group, $actual_expire ) && $success;
				}

				return $success;
			}

			LanguageEngine::get()->tick();
			$this->validate_value_presence( func_num_args() );
			$this->validate_key( $key );
			$this->validate_group( $group );
			$this->validate_expire( $expire );

			return wp_cache_set( $key, $data, $group, $expire );
		} finally {
			if ( $switched ) {
				restore_current_blog();
			}
		}
	}

	/**
	 * Removes one or multiple keys from the cache.
	 *
	 * Enforces write protection via the `EXPRESSION_LAB_DATABASE_READONLY` constant.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * Cache.delete('sample_token', 'tokens')
	 * ```
	 *
	 * ```elscript
	 * Cache.delete(['token_1', 'token_2'], 'tokens')
	 * ```
	 *
	 * @see https://developer.wordpress.org/reference/functions/wp_cache_delete/
	 * @see https://developer.wordpress.org/reference/functions/wp_cache_delete_multiple/
	 * @see https://expressionlab.io/docs/api-reference/cache#cachedelete
	 *
	 * @param string|int|array $key   The cache key or array of keys to delete.
	 * @param string           $group Optional. Where the cache contents are grouped. Default empty string.
	 * @return bool True on success, false on failure.
	 * @throws \Exception If write protection is active (`EXPRESSION_LAB_DATABASE_READONLY` is true).
	 * @throws \InvalidArgumentException If the key is not a string or integer, or is an empty string.
	 */
	public function delete( $key, string $group = '' ): bool {
		$this->validate_write_permission();

		$engine_site_id = LanguageEngine::get()->get_site_id();
		$switched       = false;

		if ( null !== $engine_site_id && is_multisite() && get_current_blog_id() !== (int) $engine_site_id ) {
			switch_to_blog( (int) $engine_site_id );
			$switched = true;
		}

		try {
			if ( is_array( $key ) ) {
				$count          = max( 1, count( $key ) );
				$remaining_time = null;
				LanguageEngine::get()->tick( $remaining_time, $count );

				foreach ( $key as $k ) {
					$this->validate_key( $k );
				}

				// Duplicate keys would report a spurious failure once the first occurrence is deleted.
				$key = array_values( array_unique( $key ) );

				if ( function_exists( 'wp_cache_delete_multiple' ) ) {
					$results = wp_cache_delete_multiple( $key, $group );
					return is_array( $results ) && ! in_array( false, $results, true );
				}

				$success = true;

				foreach ( $key as $k ) {
					$success = wp_cache_delete( $k, $group ) && $success;
				}
				return $success;
			}

			LanguageEngine::get()->tick();
			$this->validate_key( $key );
			return wp_cache_delete( $key, $group );
		} finally {
			if ( $switched ) {
				restore_current_blog();
			}
		}
	}
}
