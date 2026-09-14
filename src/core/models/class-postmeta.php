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

use WP_Post;
use ExpressionLab\Core\QueryBuilder;
use ExpressionLab\Core\Services\Database;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * Post metadata model class.
 *
 * Provides metadata CRUD operations and query building scoped to a specific post.
 *
 * @since 1.0.0
 * @package ExpressionLab
 */
final class PostMeta {
	use QueryBuilder;

	/**
	 * Target post.
	 *
	 * @since 1.0.0
	 * @internal
	 * @var WP_Post
	 */
	private $post;

	/**
	 * Database instance.
	 *
	 * @since 1.0.0
	 * @internal
	 * @var Database
	 */
	private $database;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param WP_Post  $post     The WP_Post object.
	 * @param Database $database The database instance.
	 */
	public function __construct( WP_Post $post, Database $database ) {
		$this->database = $database;
		$this->post     = $post;
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
		return 'postmeta';
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
	 * Retrieves the query builder base conditions scoped to the post.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return array<string, mixed> Base condition clauses.
	 */
	protected function get_query_builder_base_conditions(): array {
		return array( 'post_id' => (int) $this->post->ID );
	}

	/**
	 * Retrieves a metadata value or executes the query builder if no key is provided.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $key    The meta key, or `null` to execute the query builder.
	 * @param bool        $single Whether to return a single value or an array of values.
	 * @return mixed Metadata value or array of query builder results.
	 */
	public function get( ?string $key = null, bool $single = true ) {
		if ( null === $key ) {
			return $this->query_builder_get_results();
		}

		return get_post_meta( $this->post->ID, $key, $single );
	}

	/**
	 * Retrieves all metadata entries for the post.
	 *
	 * @since 1.0.0
	 *
	 * @return array Associative array of metadata key-value pairs.
	 */
	public function all(): array {
		$raw = get_post_meta( $this->post->ID );
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$result = array();
		foreach ( $raw as $key => $values ) {
			$result[ $key ] = count( $values ) === 1 ? maybe_unserialize( $values[0] ) : array_map( 'maybe_unserialize', $values );
		}

		return $result;
	}

	/**
	 * Sets a metadata value for the post.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key   The meta key.
	 * @param mixed  $value The meta value.
	 * @return bool True on successful update, false on failure.
	 * @throws \Exception If write protection is enabled.
	 */
	public function set( string $key, $value ): bool {
		if ( defined( 'EXPRESSION_LAB_DATABASE_READONLY' ) && EXPRESSION_LAB_DATABASE_READONLY ) {
			throw new \Exception( esc_html( 'Operation denied. Write protection is enabled. Set `EXPRESSION_LAB_DATABASE_READONLY` to `false` in `wp-config.php`.' ) );
		}

		return (bool) update_post_meta( $this->post->ID, $key, $value );
	}

	/**
	 * Deletes a metadata entry for the post.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The meta key.
	 * @return bool True on successful deletion, false on failure.
	 * @throws \Exception If write protection is enabled.
	 */
	public function delete( string $key ): bool {
		if ( defined( 'EXPRESSION_LAB_DATABASE_READONLY' ) && EXPRESSION_LAB_DATABASE_READONLY ) {
			throw new \Exception( esc_html( 'Operation denied. Write protection is enabled. Set `EXPRESSION_LAB_DATABASE_READONLY` to `false` in `wp-config.php`.' ) );
		}

		return (bool) delete_post_meta( $this->post->ID, $key );
	}
}
