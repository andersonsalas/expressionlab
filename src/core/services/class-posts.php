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

use WP_Post;
use ExpressionLab\Core\LanguageEngine;
use ExpressionLab\Core\QueryBuilder;
use ExpressionLab\Core\Models\Post;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * Posts Service.
 *
 * Provides methods to query, inspect, and manage WordPress posts, pages, and Custom Post Types (CPTs).
 *
 * @package ExpressionLab
 */
final class Posts {
	use QueryBuilder;

	/**
	 * Database instance.
	 *
	 * @var Database
	 */
	private $database;

	/**
	 * Constructor.
	 *
	 * @internal
	 *
	 * @param Database $database The database instance.
	 */
	public function __construct( Database $database ) {
		$this->database = $database;
	}

	/**
	 * Returns the table name for the query builder.
	 *
	 * @internal
	 *
	 * @return string
	 */
	protected function get_query_builder_table(): string {
		return 'posts';
	}

	/**
	 * Returns the Database instance for the query builder.
	 *
	 * @internal
	 *
	 * @return Database
	 */
	protected function get_query_builder_database(): Database {
		return $this->database;
	}

	/**
	 * Retrieves a single Post model instance by identifier, or executes the query builder if no identifier is provided.
	 *
	 * When an identifier is supplied, resolves the post through a multi-tier fallback mechanism:
	 * * **Numeric ID**: Fetches directly via `get_post(absint($identifier))`.
	 * * **URL or Permalink**: Resolves through `url_to_postid()`, falls back to path resolution via
	 *    `get_page_by_path()` across all registered post types, and finally falls back to slug lookup.
	 * * **Post Slug**: Queries via `get_posts()` matching `name = sanitize_title($identifier)` across all
	 *    post types and statuses.
	 *
	 * When called without arguments (`null`), executes all accumulated **Query Builder** conditions,
	 * mirrors matching rows from `wp_posts` into an in-memory SQLite table, flushes the buffer, and returns
	 * an array of row objects.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * Posts.get(42)
	 * ```
	 *
	 * ```elscript
	 * Posts.get('hello-world')
	 * ```
	 *
	 * ```elscript
	 * Posts.get('https://example.com/sample-page/')
	 * ```
	 *
	 * ```elscript
	 * Posts.where('post_type', 'page')
	 *     .where('post_status', 'publish')
	 *     .get()
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/posts#postsget
	 *
	 * @param int|string|null $identifier Optional. Post ID, slug name, URL/permalink, or `null` to execute query builder. Default `null`.
	 * @return Post|array|null A `Post` model instance if resolved, an array of row objects if executing query builder, or `null` if not found.
	 */
	public function get( $identifier = null ) {
		if ( null === $identifier ) {
			return $this->query_builder_get_results();
		}

		$wp_post = null;

		if ( is_int( $identifier ) || ( is_string( $identifier ) && ctype_digit( $identifier ) ) ) {
			$wp_post = get_post( absint( $identifier ) );
		} elseif ( is_string( $identifier ) ) {
			// Check if identifier is a URL / permalink.
			if ( filter_var( $identifier, FILTER_VALIDATE_URL ) || str_contains( $identifier, '/' ) || str_starts_with( $identifier, 'http://' ) || str_starts_with( $identifier, 'https://' ) ) {
				$post_id = url_to_postid( $identifier );

				if ( ! $post_id ) {
					$post_id = url_to_postid( home_url( $identifier ) );
				}

				if ( $post_id ) {
					$wp_post = get_post( $post_id );
				} else {
					$path = wp_parse_url( $identifier, PHP_URL_PATH );
					if ( ! empty( $path ) ) {
						$trimmed_path = trim( $path, '/' );
						$post_by_path = get_page_by_path( $trimmed_path, OBJECT, get_post_types( array(), 'names' ) );
						if ( $post_by_path instanceof WP_Post ) {
							$wp_post = $post_by_path;
						} else {
							$slug = basename( $trimmed_path );
							if ( ! empty( $slug ) ) {
								$posts = get_posts(
									array(
										'name'        => sanitize_title( $slug ),
										'post_type'   => 'any',
										'post_status' => 'any',
										'numberposts' => 1,
									)
								);
								if ( ! empty( $posts ) ) {
									$wp_post = $posts[0];
								}
							}
						}
					}
				}
			}

			// If not found yet, search by slug.
			if ( ! ( $wp_post instanceof WP_Post ) ) {
				$posts = get_posts(
					array(
						'name'        => sanitize_title( $identifier ),
						'post_type'   => 'any',
						'post_status' => 'any',
						'numberposts' => 1,
					)
				);
				if ( ! empty( $posts ) ) {
					$wp_post = $posts[0];
				}
			}
		}

		if ( ! ( $wp_post instanceof WP_Post ) ) {
			return null;
		}

		return new Post( $wp_post, $this->database );
	}

	/**
	 * Retrieves all registered WordPress post type names.
	 *
	 * Queries the WordPress post type registry (`get_post_types`) and returns an indexed array
	 * containing the slugs of all registered public and internal post types (e.g., `'post'`, `'page'`,
	 * `'attachment'`, `'revision'`, and custom post types).
	 *
	 * Example:
	 *
	 * ```elscript
	 * Posts.types()
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/posts#poststypes
	 * @return array<string> An indexed array of registered post type slug strings.
	 */
	public function types(): array {
		return array_values( get_post_types( array(), 'names' ) );
	}

	/**
	 * Retrieves all registered WordPress post status names.
	 *
	 * Queries the WordPress post status registry (`get_post_stati`) and returns an indexed array
	 * containing all registered post status slugs (e.g., `'publish'`, `'draft'`, `'pending'`, `'future'`,
	 * `'private'`, `'trash'`).
	 *
	 * Example:
	 *
	 * ```elscript
	 * Posts.statuses()
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/posts#postsstatuses
	 * @return array<string> An indexed array of registered post status slug strings.
	 */
	public function statuses(): array {
		return array_values( get_post_stati( array(), 'names' ) );
	}

	/**
	 * Retrieves registered taxonomies, optionally filtered by a specific post type.
	 *
	 * When `$post_type` is specified, queries `get_object_taxonomies()` to retrieve only the taxonomy
	 * slugs associated with that particular post type. When omitted or empty, queries `get_taxonomies()`
	 * to return all registered taxonomies across the WordPress installation.
	 *
	 * Example:
	 *
	 * ```elscript
	 * Posts.taxonomies()
	 * ```
	 *
	 * ```elscript
	 * Posts.taxonomies('post')
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/posts#poststaxonomies
	 *
	 * @param string $post_type Optional. Specific post type name to filter associated taxonomies for. Default empty string.
	 * @return array<string> An indexed array of taxonomy slug strings.
	 */
	public function taxonomies( string $post_type = '' ): array {
		if ( ! empty( $post_type ) ) {
			return array_values( get_object_taxonomies( $post_type, 'names' ) );
		}
		return array_values( get_taxonomies( array(), 'names' ) );
	}

	/**
	 * Retrieves formatted terms belonging to a given taxonomy.
	 *
	 * Fetches taxonomy terms via `get_terms()`. Unless explicitly specified in `$args`, `hide_empty`
	 * defaults to `false` to include terms with zero associations.
	 *
	 * Each term is transformed into a normalized dictionary containing `term_id`, `name`, `slug`, and
	 * object `count`. Returns an empty array if the taxonomy does not exist or if a `WP_Error` occurs.
	 *
	 * Example:
	 *
	 * ```elscript
	 * Posts.terms('category')
	 * ```
	 *
	 * ```elscript
	 * Posts.terms('post_tag', { orderby: 'count', order: 'DESC', number: 10 })
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/posts#poststerms
	 *
	 * @param string $taxonomy The taxonomy slug (e.g., `'category'`, `'post_tag'`).
	 * @param array  $args     Optional. Additional query arguments passed to `get_terms()`. Default empty array.
	 * @return array Normalized list of term objects, or empty array on failure.
	 */
	public function terms( string $taxonomy, array $args = array() ): array {
		$args['taxonomy']   = $taxonomy;
		$args['hide_empty'] = $args['hide_empty'] ?? false;

		$terms = get_terms( $args );
		if ( ! is_array( $terms ) || is_wp_error( $terms ) ) {
			return array();
		}

		return array_map(
			function ( $term ) {
				return array(
					'term_id' => $term->term_id,
					'name'    => $term->name,
					'slug'    => $term->slug,
					'count'   => $term->count,
				);
			},
			$terms
		);
	}

	/**
	 * Calculates a statistical breakdown and generates interactive visualizations of posts grouped by type and status.
	 *
	 * Executes an aggregate database query against `wp_posts` and renders the following interactive
	 * visualizations:
	 * * **Table Visualization**: Tabular view titled `Table: Posts distribution by type and status`.
	 * * **Vega-Lite Bar Chart**: Interactive visualization titled `Graph: Posts distribution by type`,
	 *    grouping records by `post_type` and color-coding by `post_status`.
	 *
	 * Emits a warning notification if no records exist in the posts table.
	 *
	 * Example:
	 *
	 * ```elscript
	 * Posts.stats()
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/posts#postsstats
	 * @return array Aggregated distribution list, or empty array if no posts found.
	 */
	public function stats(): array {
		global $wpdb;

		LanguageEngine::get()->tick();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			"SELECT post_type, post_status, COUNT(*) as count 
			 FROM {$wpdb->posts} 
			 GROUP BY post_type, post_status 
			 ORDER BY count DESC",
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			LanguageEngine::get()->add_message( 'warning', 'No posts found to display statistics.' );
			return array();
		}

		$formatted = array_map(
			function ( $row ) {
				return array(
					'post_type'   => (string) $row['post_type'],
					'post_status' => (string) $row['post_status'],
					'count'       => (int) $row['count'],
				);
			},
			$rows
		);

		// 1. Table Visualization.
		LanguageEngine::get()->begin_visualization_group()->add_visualization(
			array(
				'type'  => 'table',
				'title' => 'Table: Posts distribution by type and status',
				'data'  => $formatted,
			)
		);

		// 2. Vega-Lite Bar Chart Visualization.
		LanguageEngine::get()->add_visualization(
			array(
				'type'  => 'graph',
				'title' => 'Graph: Posts distribution by type',
				'data'  => array(
					'$schema'     => 'https://vega.github.io/schema/vega-lite/v6.json',
					'description' => 'Posts distribution graph',
					'width'       => 'container',
					'height'      => 320,
					'padding'     => 5,
					'data'        => array(
						'values' => $formatted,
					),
					'mark'        => array(
						'type'    => 'bar',
						'tooltip' => true,
					),
					'encoding'    => array(
						'x'     => array(
							'field' => 'post_type',
							'type'  => 'nominal',
							'axis'  => array(
								'title'         => 'Post Type',
								'labelAngle'    => -45,
								'titleFontSize' => 14,
								'labelFontSize' => 13,
								'labelFont'     => 'Cascadia Mono, monospace',
								'titleFont'     => 'Roboto Slab, serif',
							),
						),
						'y'     => array(
							'field' => 'count',
							'type'  => 'quantitative',
							'axis'  => array(
								'title'         => 'Total Count',
								'titleFontSize' => 14,
								'labelFontSize' => 13,
								'labelFont'     => 'Cascadia Mono, monospace',
								'titleFont'     => 'Roboto Slab, serif',
							),
						),
						'color' => array(
							'field'  => 'post_status',
							'type'   => 'nominal',
							'legend' => array(
								'title'         => 'Status',
								'titleFontSize' => 14,
								'labelFontSize' => 13,
								'labelFont'     => 'Cascadia Mono, monospace',
								'titleFont'     => 'Roboto Slab, serif',
							),
						),
					),
				),
			)
		);

		return $formatted;
	}
}
