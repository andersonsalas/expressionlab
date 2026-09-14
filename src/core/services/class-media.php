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
use ExpressionLab\Core\Models\Attachment;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * Media Service.
 *
 * Provides methods to query, inspect, visualize, and safely delete WordPress media attachments.
 *
 * Specializes in advanced URL-to-Attachment resolution (supporting full URLs, relative paths,
 * generated thumbnail dimensions like '-300x200.jpg', and '-scaled.jpg' images).
 *
 * Sideloading and file uploads are not supported.
 *
 * @package ExpressionLab
 */
final class Media {
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
	 * Returns base conditions that are always enforced for the query builder.
	 *
	 * Scopes all queries to attachment post types.
	 *
	 * @internal
	 *
	 * @return array
	 */
	protected function get_query_builder_base_conditions(): array {
		return array( 'post_type' => 'attachment' );
	}

	/**
	 * Retrieves a single Attachment model instance by identifier, or executes the query builder if no identifier is provided.
	 *
	 * When an identifier is supplied, resolves the attachment through the following resolution modes:
	 *
	 * * **Numeric ID**: Queries via `get_post(absint($identifier))` and verifies that `post_type === 'attachment'`.
	 * * **URL, Relative Path, or Filename**: Executes deep heuristic matching via `Media.resolve_id()`.
	 * * **Attachment Slug**: Queries via `get_posts()` with `name = sanitize_title($identifier)` for attachment post types.
	 * * **Attachment Title**: Queries via `get_posts()` matching post title for attachment post types.
	 *
	 * When called without arguments (`null`), executes all accumulated **Query Builder** conditions
	 * (automatically scoped to `post_type = 'attachment'`), mirrors matching rows into an in-memory SQLite table,
	 * flushes the buffer, and returns an array of row objects.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * Media.get(42)
	 * ```
	 *
	 * ```elscript
	 * Media.get('https://example.com/wp-content/uploads/2026/08/hero-banner.jpg')
	 * ```
	 *
	 * ```elscript
	 * Media.get('https://example.com/wp-content/uploads/2026/08/hero-banner-300x200.jpg')
	 * ```
	 *
	 * ```elscript
	 * Media.where('post_mime_type', 'image/png')
	 *     .order_by('ID', 'DESC')
	 *     .limit(10)
	 *     .get()
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/media#mediaget
	 *
	 * @param int|string|null $identifier Optional. Attachment ID, slug, title, URL/path, or `null` to execute query builder. Default `null`.
	 * @return Attachment|array|null An `Attachment` model instance if resolved, an array of row objects if executing query builder, or `null` if not found.
	 */
	public function get( $identifier = null ) {
		LanguageEngine::get()->tick();

		if ( null === $identifier ) {
			return $this->query_builder_get_results();
		}

		$wp_post = null;

		if ( is_int( $identifier ) || ( is_string( $identifier ) && ctype_digit( $identifier ) ) ) {
			$post = get_post( absint( $identifier ) );
			if ( $post instanceof WP_Post && 'attachment' === $post->post_type ) {
				$wp_post = $post;
			}
		} elseif ( is_string( $identifier ) ) {
			// Check if identifier is a URL, path, or filename.
			if (
				filter_var( $identifier, FILTER_VALIDATE_URL )
				|| str_contains( $identifier, '/' )
				|| str_starts_with( $identifier, 'http://' )
				|| str_starts_with( $identifier, 'https://' )
				|| preg_match( '/\.[a-zA-Z0-9]{2,5}$/', $identifier )
			) {
				$attachment_id = $this->resolve_id( $identifier );
				if ( $attachment_id ) {
					$post = get_post( $attachment_id );
					if ( $post instanceof WP_Post && 'attachment' === $post->post_type ) {
						$wp_post = $post;
					}
				}
			}

			// If not found yet, try searching by slug or title.
			if ( ! ( $wp_post instanceof WP_Post ) ) {
				$posts = get_posts(
					array(
						'name'        => sanitize_title( $identifier ),
						'post_type'   => 'attachment',
						'post_status' => 'any',
						'numberposts' => 1,
					)
				);
				if ( ! empty( $posts ) ) {
					$wp_post = $posts[0];
				} else {
					// Search by title.
					$posts = get_posts(
						array(
							'title'       => $identifier,
							'post_type'   => 'attachment',
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

		if ( ! ( $wp_post instanceof WP_Post ) ) {
			return null;
		}

		return new Attachment( $wp_post, $this->database );
	}

	/**
	 * Resolves any media URL, thumbnail URL, generated variant URL, relative path, or filename to its attachment post ID.
	 *
	 * @internal
	 *
	 * @param string $url The media URL, sub-size thumbnail URL, relative upload path, or filename to resolve.
	 * @return int|null The resolved attachment post ID, or `null` if not found.
	 */
	private function resolve_id( string $url ): ?int {
		global $wpdb;

		$url = trim( $url );
		if ( empty( $url ) ) {
			return null;
		}

		// 1. Try native attachment_url_to_postid with full URL.
		$full_url = ( str_starts_with( $url, 'http://' ) || str_starts_with( $url, 'https://' ) )
			? $url
			: home_url( $url );

		$id = attachment_url_to_postid( $full_url );
		if ( $id > 0 ) {
			return (int) $id;
		}

		// 2. Strip thumbnail dimensions (e.g. "-300x200") and generated suffixes ("-scaled", "-rotated").
		$cleaned_full_url = preg_replace( '/-(?:\d+x\d+|scaled|rotated)(?=\.[a-zA-Z0-9]+$)/i', '', $full_url );
		if ( $cleaned_full_url !== $full_url ) {
			$id = attachment_url_to_postid( $cleaned_full_url );
			if ( $id > 0 ) {
				return (int) $id;
			}
		}

		// 3. Extract upload-relative path (e.g. "2026/08/imagen.jpg").
		$upload_dir    = wp_upload_dir();
		$base_url      = $upload_dir['baseurl'];
		$relative_path = '';

		if ( str_contains( $full_url, $base_url ) ) {
			$relative_path = ltrim( str_replace( $base_url, '', $full_url ), '/' );
		} elseif ( preg_match( '#/wp-content/uploads/(.+)$#i', $full_url, $matches ) ) {
			$relative_path = $matches[1];
		} else {
			$path          = wp_parse_url( $url, PHP_URL_PATH );
			$relative_path = ltrim( $path ?? $url, '/' );
		}

		if ( ! empty( $relative_path ) ) {
			// Query _wp_attached_file directly.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
					$relative_path
				)
			);
			if ( $id ) {
				return (int) $id;
			}

			// Try cleaned relative path (stripped dimensions/scaled).
			$cleaned_relative = preg_replace( '/-(?:\d+x\d+|scaled|rotated)(?=\.[a-zA-Z0-9]+$)/i', '', $relative_path );
			if ( $cleaned_relative !== $relative_path ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$id = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
						$cleaned_relative
					)
				);
				if ( $id ) {
					return (int) $id;
				}
			}

			// Try matching by base filename in _wp_attached_file.
			$filename = basename( $cleaned_relative );
			if ( ! empty( $filename ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$id = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT post_id FROM {$wpdb->postmeta} 
						 WHERE meta_key = '_wp_attached_file' 
						   AND (meta_value = %s OR meta_value LIKE %s) 
						 LIMIT 1",
						$filename,
						'%/' . $wpdb->esc_like( $filename )
					)
				);
				if ( $id ) {
					return (int) $id;
				}
			}

			// 4. Search in serialized _wp_attachment_metadata for sub-size filenames.
			$thumb_filename = basename( $relative_path );
			if ( ! empty( $thumb_filename ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$id = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT post_id FROM {$wpdb->postmeta} 
						 WHERE meta_key = '_wp_attachment_metadata' 
						   AND meta_value LIKE %s 
						 LIMIT 1",
						'%' . $wpdb->esc_like( '"' . $thumb_filename . '"' ) . '%'
					)
				);
				if ( $id ) {
					return (int) $id;
				}
			}
		}

		// 5. Query by guid.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} 
				 WHERE post_type = 'attachment' 
				   AND (guid = %s OR guid = %s) 
				 LIMIT 1",
				$url,
				$full_url
			)
		);
		if ( $id ) {
			return (int) $id;
		}

		return null;
	}

	/**
	 * Deletes a media attachment record and permanently removes all associated physical files from the server.
	 *
	 * Resolves the attachment through `Media.get()`, verifies write protection settings, deletes the
	 * attachment database entry, and purges all associated files (original upload and generated image sub-sizes).
	 *
	 * Examples:
	 *
	 * ```elscript
	 * Media.delete(42)
	 * ```
	 *
	 * ```elscript
	 * Media.delete(42, true)
	 * ```
	 *
	 * ```elscript
	 * Media.delete('https://example.com/wp-content/uploads/2026/08/obsolete-photo-300x200.jpg', true)
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/media#mediadelete
	 *
	 * @param int|string $identifier Attachment ID, slug, title, or URL.
	 * @param bool       $force      Optional. Whether to bypass trash and force permanent file deletion. Default `false`.
	 * @return bool `true` on successful deletion, `false` if attachment was not found or deletion failed.
	 * @throws \Exception If database or filesystem write protection is enabled (`EXPRESSION_LAB_DATABASE_READONLY` or `EXPRESSION_LAB_FILESYSTEM_READONLY`).
	 */
	public function delete( $identifier, bool $force = false ): bool {
		LanguageEngine::get()->tick();

		if ( defined( 'EXPRESSION_LAB_DATABASE_READONLY' ) && EXPRESSION_LAB_DATABASE_READONLY ) {
			throw new \Exception( esc_html( 'Operation denied. Write protection is enabled. Set `EXPRESSION_LAB_DATABASE_READONLY` to `false` in `wp-config.php`.' ) );
		}

		if ( defined( 'EXPRESSION_LAB_FILESYSTEM_READONLY' ) && EXPRESSION_LAB_FILESYSTEM_READONLY ) {
			throw new \Exception( esc_html( 'Operation denied. Filesystem write protection is enabled. Set `EXPRESSION_LAB_FILESYSTEM_READONLY` to `false` in `wp-config.php`.' ) );
		}

		$attachment = $this->get( $identifier );
		if ( ! ( $attachment instanceof Attachment ) ) {
			return false;
		}

		return $attachment->delete( $force );
	}

	/**
	 * Retrieves an alphabetical list of all distinct MIME types currently stored in the media library.
	 *
	 * Queries `wp_posts` for distinct `post_mime_type` values where `post_type = 'attachment'`,
	 * returning an indexed list of unique MIME type identifiers (e.g., `'image/jpeg'`, `'image/png'`, `'application/pdf'`).
	 *
	 * Example:
	 *
	 * ```elscript
	 * Media.mime_types()
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/media#mediamime_types
	 * @return array<string> An indexed array of unique MIME type strings.
	 */
	public function mime_types(): array {
		global $wpdb;

		LanguageEngine::get()->tick();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_col(
			"SELECT DISTINCT post_mime_type 
			 FROM {$wpdb->posts} 
			 WHERE post_type = 'attachment' 
			   AND post_mime_type != '' 
			 ORDER BY post_mime_type ASC"
		);

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Retrieves unattached media attachments (orphan files in the media library without a parent post).
	 *
	 * Queries attachments where `post_parent = 0` and `post_status = 'inherit'`, returning an array of
	 * initialized `Attachment` model instances.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * Media.unattached()
	 * ```
	 *
	 * ```elscript
	 * Media.unattached(10)
	 * ```
	 *
	 * @param int $limit Optional. Maximum number of unattached attachments to return. Default `50`.
	 * @see https://expressionlab.io/docs/api-reference/media#mediaunattached
	 * @return array<Attachment> An array of `Attachment` model instances representing unattached items.
	 */
	public function unattached( int $limit = 50 ): array {
		LanguageEngine::get()->tick();

		$posts = get_posts(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'post_parent' => 0,
				'numberposts' => $limit,
			)
		);

		return array_map(
			function ( $post ) {
				return new Attachment( $post, $this->database );
			},
			$posts
		);
	}

	/**
	 * Calculates a statistical breakdown and generates interactive visualizations for the media library grouped by MIME type.
	 *
	 * Executes an aggregate database query against `wp_posts` and renders two interactive
	 * visualizations:
	 *
	 * * **Table Visualization**: Tabular view titled `Table: Media distribution by MIME type`.
	 * * **Vega-Lite Radial Chart**: Interactive pie/donut visualization titled `Graph: Media distribution by MIME type`.
	 *
	 * Emits a warning notification if no media attachments exist.
	 *
	 * Example:
	 *
	 * ```elscript
	 * Media.stats()
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/media#mediastats
	 * @return array Aggregated list of MIME type distribution records, or empty array if no attachments found.
	 */
	public function stats(): array {
		global $wpdb;

		LanguageEngine::get()->tick();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			"SELECT post_mime_type, COUNT(*) as count 
			 FROM {$wpdb->posts} 
			 WHERE post_type = 'attachment' 
			 GROUP BY post_mime_type 
			 ORDER BY count DESC",
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			LanguageEngine::get()->add_message( 'warning', 'No media attachments found to display statistics.' );
			return array();
		}

		$formatted = array_map(
			function ( $row ) {
				return array(
					'mime_type' => ! empty( $row['post_mime_type'] ) ? (string) $row['post_mime_type'] : 'unspecified',
					'count'     => (int) $row['count'],
				);
			},
			$rows
		);

		// 1. Table Visualization.
		LanguageEngine::get()->begin_visualization_group()->add_visualization(
			array(
				'type'  => 'table',
				'title' => 'Table: Media distribution by MIME type',
				'data'  => $formatted,
			)
		);

		// 2. Vega-Lite Radial Chart Visualization.
		LanguageEngine::get()->add_visualization(
			array(
				'type'  => 'graph',
				'title' => 'Graph: Media distribution by MIME type',
				'data'  => array(
					'$schema'     => 'https://vega.github.io/schema/vega-lite/v6.json',
					'description' => 'Media distribution graph',
					'width'       => 'container',
					'height'      => 300,
					'padding'     => 20,
					'data'        => array(
						'values' => $formatted,
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
							'field'  => 'mime_type',
							'type'   => 'nominal',
							'legend' => array(
								'title'         => 'MIME Type',
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
