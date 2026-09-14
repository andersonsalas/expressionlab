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
use ExpressionLab\Core\Services\Database;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * Post model class.
 *
 * Wraps a WordPress WP_Post object with convenience properties and methods
 * for the expression language environment.
 *
 * @since 1.0.0
 * @package ExpressionLab
 */
final class Post extends Model {
	/**
	 * WordPress post instance.
	 *
	 * @since 1.0.0
	 * @internal
	 * @var WP_Post|null
	 */
	private $post;

	/**
	 * Database service instance.
	 *
	 * @since 1.0.0
	 * @internal
	 * @var Database
	 */
	private $database;

	/**
	 * Post ID.
	 *
	 * @since 1.0.0
	 * @var int|null
	 */
	public $ID;

	/**
	 * Post title.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $title = '';

	/**
	 * Post content.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $content = '';

	/**
	 * Post excerpt.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $excerpt = '';

	/**
	 * Post status (e.g. 'publish', 'draft').
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $status = '';

	/**
	 * Post type (e.g. 'post', 'page', custom post type).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $type = '';

	/**
	 * Post slug (post_name).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $slug = '';

	/**
	 * Post creation date (Y-m-d H:i:s).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $date = '';

	/**
	 * Post modified date (Y-m-d H:i:s).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $modified = '';

	/**
	 * Author user ID.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $author_id = 0;

	/**
	 * Parent post ID.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $parent_id = 0;

	/**
	 * Comment count.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $comment_count = 0;

	/**
	 * Scoped post metadata manager.
	 *
	 * @since 1.0.0
	 * @var PostMeta|null
	 */
	public $meta;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param WP_Post|null $post     The WP_Post object.
	 * @param Database     $database The database instance.
	 */
	public function __construct( ?WP_Post $post, Database $database ) {
		$this->database = $database;

		if ( null !== $post ) {
			$this->post          = $post;
			$this->ID            = (int) $post->ID;
			$this->title         = (string) $post->post_title;
			$this->content       = (string) $post->post_content;
			$this->excerpt       = (string) $post->post_excerpt;
			$this->status        = (string) $post->post_status;
			$this->type          = (string) $post->post_type;
			$this->slug          = (string) $post->post_name;
			$this->date          = (string) $post->post_date;
			$this->modified      = (string) $post->post_modified;
			$this->author_id     = (int) $post->post_author;
			$this->parent_id     = (int) $post->post_parent;
			$this->comment_count = (int) $post->comment_count;
			$this->meta          = new PostMeta( $post, $database );
		}
	}

	/**
	 * Retrieves the permalink URL for this post.
	 *
	 * @since 1.0.0
	 *
	 * @return string|false Post permalink URL on success, or `false` on failure.
	 */
	public function get_permalink() {
		if ( null === $this->post ) {
			return false;
		}
		return get_permalink( $this->post );
	}

	/**
	 * Retrieves terms for a given taxonomy associated with this post.
	 *
	 * @since 1.0.0
	 *
	 * @param string $taxonomy Taxonomy name (e.g. 'category', 'post_tag').
	 * @return array List of term arrays with term_id, name, and slug.
	 */
	public function get_terms( string $taxonomy ): array {
		if ( null === $this->post ) {
			return array();
		}
		$terms = get_the_terms( $this->post->ID, $taxonomy );
		if ( ! is_array( $terms ) ) {
			return array();
		}
		return array_map(
			function ( $term ) {
				return array(
					'term_id' => $term->term_id,
					'name'    => $term->name,
					'slug'    => $term->slug,
				);
			},
			$terms
		);
	}

	/**
	 * Retrieves an associative array representation of the post.
	 *
	 * @since 1.0.0
	 *
	 * @return array Associative array of post attributes.
	 */
	public function get_value() {
		if ( null === $this->post ) {
			return array();
		}

		return array(
			'ID'            => $this->ID,
			'title'         => $this->title,
			'slug'          => $this->slug,
			'type'          => $this->type,
			'status'        => $this->status,
			'author_id'     => $this->author_id,
			'date'          => $this->date,
			'modified'      => $this->modified,
			'comment_count' => $this->comment_count,
		);
	}
}
