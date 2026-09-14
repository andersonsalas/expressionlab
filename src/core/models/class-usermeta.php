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

namespace ExpressionLab\Core\Models;

use WP_User;
use ExpressionLab\Core\LanguageEngine;
use ExpressionLab\Core\Helper;
use ExpressionLab\Core\QueryBuilder;
use ExpressionLab\Core\Services\Database;
use ExpressionLab\Core\Services\Options;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * User metadata model class.
 *
 * Provides metadata CRUD operations and query building scoped to a specific user.
 *
 * @since 1.0.0
 * @package ExpressionLab
 */
final class UserMeta {
	use QueryBuilder;

	/**
	 * Target user instance.
	 *
	 * @since 1.0.0
	 * @internal
	 * @var WP_User
	 */
	private $user;

	/**
	 * Database service instance.
	 *
	 * @since 1.0.0
	 * @internal
	 * @var Database
	 */
	private $database;

	/**
	 * WordPress database global instance.
	 *
	 * @since 1.0.0
	 * @internal
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * Magic method to expose constants as properties.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string $name Property name.
	 * @return mixed Property value if constant exists, or `null`.
	 */
	public function __get( string $name ) {
		if ( defined( "self::$name" ) ) {
			return constant( "self::$name" );
		}
		return null;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param WP_User  $user     The WP_User object.
	 * @param Database $database The database instance.
	 */
	public function __construct( WP_User $user, Database $database ) {
		global $wpdb;

		$this->database = $database;
		$this->user     = $user;
		$this->wpdb     = $wpdb;
	}

	/**
	 * Retrieves the query builder table name.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return string Table name without prefix.
	 */
	protected function get_query_builder_table(): string {
		return 'usermeta';
	}

	/**
	 * Retrieves the query builder database instance.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return Database Database service instance.
	 */
	protected function get_query_builder_database(): Database {
		return $this->database;
	}

	/**
	 * Retrieves the query builder base conditions scoped to the user.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return array<string, mixed> Base condition clauses.
	 */
	protected function get_query_builder_base_conditions(): array {
		return array( 'user_id' => (int) $this->user->ID );
	}

	/**
	 * Retrieves a raw metadata value without unserialization.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key           The meta key.
	 * @param mixed  $default_value The default value if the meta is not set.
	 * @return mixed The meta value or default.
	 */
	public function get_raw( string $key, $default_value = null ) {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		LanguageEngine::get()->tick();
		$query = $wpdb->prepare( "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s LIMIT 1", $this->user->ID, $key );
		$value = $wpdb->get_var( $query );
		LanguageEngine::get()->tick( $remaining_time, is_string( $value ) ? strlen( $value ) : 1 );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared

		return $value ?? $default_value;
	}

	/**
	 * Retrieves a metadata value or executes the query builder if no key is provided.
	 *
	 * Attempts to unserialize the value if stored as serialized PHP or JSON.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $key           The meta key, or `null` to execute the query builder.
	 * @param mixed       $default_value The default value if the meta is not set.
	 * @return mixed The processed meta value or query builder results.
	 * @throws \Exception If the data contains invalid classes during unserialization.
	 */
	public function get( ?string $key = null, $default_value = null ) {
		if ( null === $key ) {
			return $this->query_builder_get_results();
		}

		$value = $this->get_raw( $key, $default_value );

		if ( ! is_string( $value ) ) {
			return $value;
		}

		if ( is_serialized( $value ) ) {
			return $this->strict_unserialize( $value, 'User.meta_get_raw()' );
		}

		$json_decoded = json_decode( $value, true );

		if ( json_last_error() === JSON_ERROR_NONE ) {
			return $json_decoded;
		}

		return $value;
	}

	/**
	 * Retrieves an associative array of all metadata keys and values for the user.
	 *
	 * @since 1.0.0
	 *
	 * @return array Associative array of metadata key-value pairs.
	 */
	public function list() {
		return $this->database->mirror( 'usermeta', array( 'user_id' => $this->user->ID ) )->flush();
	}

	/**
	 * Checks if a specific metadata key exists for the user.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The meta key to check for existence.
	 * @return bool True if the meta key exists for the user, false otherwise.
	 */
	public function has( string $key ) {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = $this->wpdb->prepare( "SELECT COUNT(*) FROM {$this->wpdb->usermeta} WHERE user_id = %d AND meta_key = %s", $this->user->ID, $key );
		$count = $this->wpdb->get_var( $query );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (bool) $count;
	}

	/**
	 * Updates a metadata value without serialization.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key   The meta key.
	 * @param string $value The raw string value to store.
	 * @return bool True on success, false on failure.
	 * @throws \Exception If the operation is denied due to write protection.
	 */
	public function update_raw( string $key, string $value ): bool {
		if ( defined( 'EXPRESSION_LAB_DATABASE_READONLY' ) && EXPRESSION_LAB_DATABASE_READONLY ) {
			throw new \Exception( esc_html( 'Operation denied. Write protection is enabled. Set `EXPRESSION_LAB_DATABASE_READONLY` to `false` in `wp-config.php`.' ) );
		}

		$old_value = $this->get_raw( $key );

		if ( $value === $old_value ) {
			return false;
		}

		LanguageEngine::get()->tick( $remaining_time, strlen( $value ) );
		$result = update_user_meta( $this->user->ID, $key, $value );

		return (bool) $result;
	}

	/**
	 * Updates a metadata value using the specified serialization format.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key                The meta key.
	 * @param mixed  $value              The meta value.
	 * @param string $serialization_type Serialization format type.
	 * @return bool True on success, false on failure.
	 * @throws \Exception If write protection is enabled or serialization issues occur.
	 */
	public function update( string $key, $value, string $serialization_type = Options::FORMAT_SERIALIZED ): bool {
		if ( defined( 'EXPRESSION_LAB_DATABASE_READONLY' ) && EXPRESSION_LAB_DATABASE_READONLY ) {
			throw new \Exception( esc_html( 'Operation denied. Write protection is enabled. Set `EXPRESSION_LAB_DATABASE_READONLY` to `false` in `wp-config.php`.' ) );
		}

		$raw_value = $this->get_raw( $key );

		if ( null !== $raw_value ) {
			if ( is_string( $raw_value ) && is_serialized( $raw_value ) ) {
				if ( Options::FORMAT_JSON === $serialization_type ) {
					throw new \Exception( esc_html( 'Cannot update with JSON serialization because the existing value is serialized. Use update_raw() instead.' ) );
				}
				$this->strict_serialize_check( $raw_value, 'User.update_raw()' );
			}

			if ( is_string( $raw_value ) ) {
				$decoded = json_decode( $raw_value, true );
				if ( json_last_error() === JSON_ERROR_NONE && Options::FORMAT_JSON !== $serialization_type ) {
					throw new \Exception( esc_html( 'Cannot update with PHP serialization because the existing value is JSON. Use update_raw() instead.' ) );
				}
			}
		}

		if ( Options::FORMAT_SERIALIZED_OBJECT === $serialization_type ) {
			$value = (object) $value;
		} elseif ( Options::FORMAT_JSON === $serialization_type ) {
			$value = wp_json_encode( $value );
		}

		if ( Options::FORMAT_JSON !== $serialization_type ) {
			$this->strict_serialize_check( $value, 'update_raw()' );
		}

		$result = update_user_meta( $this->user->ID, $key, $value );

		return (bool) $result;
	}

	/**
	 * Deletes a metadata entry for the user.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The meta key.
	 * @return bool True on success, false on failure.
	 * @throws \Exception If the operation is denied due to write protection.
	 */
	public function delete( string $key ): bool {
		if ( defined( 'EXPRESSION_LAB_DATABASE_READONLY' ) && EXPRESSION_LAB_DATABASE_READONLY ) {
			throw new \Exception( esc_html( 'Operation denied. Write protection is enabled. Set `EXPRESSION_LAB_DATABASE_READONLY` to `false` in `wp-config.php`.' ) );
		}

		LanguageEngine::get()->tick();
		$result = delete_user_meta( $this->user->ID, $key );

		return (bool) $result;
	}

	/**
	 * Validates a value for serialization.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param mixed  $value       The value to serialize.
	 * @param string $alternative The alternative method recommendation.
	 * @return void
	 * @throws \Exception If serialization validation fails.
	 */
	private function strict_serialize_check( $value, string $alternative ): void {
		try {
			Helper::strict_serialize( $value );
		} catch ( \UnexpectedValueException $e ) {
			throw new \Exception( esc_html( 'Unsafe or invalid data for serialization. Use ' . $alternative . ' instead.' ) );
		}
	}

	/**
	 * Unserializes a string using strict validation.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string $value       The serialized string.
	 * @param string $alternative The alternative method recommendation.
	 * @return mixed Unserialized value.
	 * @throws \Exception If unserialization validation fails.
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
