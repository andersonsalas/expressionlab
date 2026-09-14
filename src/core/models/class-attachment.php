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
 * Attachment model class.
 *
 * Wraps a WordPress WP_Post object of post_type 'attachment' with specialized
 * media properties, dimensions, file paths, and thumbnail size helpers.
 *
 * @since 1.0.0
 * @package ExpressionLab
 */
final class Attachment extends Model {
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
	 * Attachment ID.
	 *
	 * @since 1.0.0
	 * @var int|null
	 */
	public $ID;

	/**
	 * Attachment title.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $title = '';

	/**
	 * Caption (post_excerpt).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $caption = '';

	/**
	 * Description (post_content).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $description = '';

	/**
	 * Alternative text for images (_wp_attachment_image_alt).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $alt = '';

	/**
	 * MIME type (e.g. 'image/jpeg', 'application/pdf').
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $mime_type = '';

	/**
	 * Full URL to the media file.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $url = '';

	/**
	 * Absolute filesystem path to the original file.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $file_path = '';

	/**
	 * File size in bytes.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $filesize = 0;

	/**
	 * Human-readable file size (e.g. "1.24 MB").
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $filesize_human = '';

	/**
	 * Dimensions array with 'width' and 'height' keys.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	public $dimensions = array();

	/**
	 * Array of registered thumbnail sub-sizes with URLs and dimensions.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	public $sizes = array();

	/**
	 * Creation date (Y-m-d H:i:s).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $date = '';

	/**
	 * Modified date (Y-m-d H:i:s).
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
	 * Parent post ID (0 if unattached/orphan).
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $parent_id = 0;

	/**
	 * Scoped metadata manager.
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
			$this->post        = $post;
			$this->ID          = (int) $post->ID;
			$this->title       = (string) $post->post_title;
			$this->caption     = (string) $post->post_excerpt;
			$this->description = (string) $post->post_content;
			$this->mime_type   = (string) $post->post_mime_type;
			$this->date        = (string) $post->post_date;
			$this->modified    = (string) $post->post_modified;
			$this->author_id   = (int) $post->post_author;
			$this->parent_id   = (int) $post->post_parent;

			$this->meta = new PostMeta( $post, $database );

			$this->alt       = (string) get_post_meta( $post->ID, '_wp_attachment_image_alt', true );
			$this->url       = (string) wp_get_attachment_url( $post->ID );
			$this->file_path = (string) get_attached_file( $post->ID );

			// Compute file size.
			if ( ! empty( $this->file_path ) && file_exists( $this->file_path ) ) {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				$this->filesize       = (int) @filesize( $this->file_path );
				$this->filesize_human = $this->format_bytes( $this->filesize );
			}

			// Load attachment metadata (dimensions and sub-sizes).
			$metadata = wp_get_attachment_metadata( $post->ID );
			if ( is_array( $metadata ) ) {
				if ( isset( $metadata['width'], $metadata['height'] ) ) {
					$this->dimensions = array(
						'width'  => (int) $metadata['width'],
						'height' => (int) $metadata['height'],
					);
				}

				if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
					$upload_dir = wp_upload_dir();
					$base_url   = trailingslashit( $upload_dir['baseurl'] );
					$base_dir   = trailingslashit( $upload_dir['basedir'] );

					// Extract subfolder from attached file (e.g. "2026/08/").
					$attached_file = get_post_meta( $post->ID, '_wp_attached_file', true );
					$sub_dir       = dirname( $attached_file );
					$sub_path      = ( '.' === $sub_dir || empty( $sub_dir ) ) ? '' : trailingslashit( $sub_dir );

					foreach ( $metadata['sizes'] as $size_name => $size_info ) {
						$file_name                 = $size_info['file'] ?? '';
						$this->sizes[ $size_name ] = array(
							'file'      => $file_name,
							'width'     => (int) ( $size_info['width'] ?? 0 ),
							'height'    => (int) ( $size_info['height'] ?? 0 ),
							'mime_type' => (string) ( $size_info['mime-type'] ?? '' ),
							'url'       => $base_url . $sub_path . $file_name,
							'path'      => $base_dir . $sub_path . $file_name,
							'filesize'  => (int) ( $size_info['filesize'] ?? 0 ),
						);
					}
				}
			}
		}
	}

	/**
	 * Retrieves the URL for a specific image size or the original file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $size Image size name (e.g. 'full', 'thumbnail', 'medium', 'large').
	 * @return string Media URL, or empty string if post is not loaded.
	 */
	public function get_url( string $size = 'full' ): string {
		if ( null === $this->post ) {
			return '';
		}

		if ( 'full' === $size || empty( $size ) ) {
			return $this->url;
		}

		$src = wp_get_attachment_image_src( $this->ID, $size );
		if ( is_array( $src ) && ! empty( $src[0] ) ) {
			return (string) $src[0];
		}

		return isset( $this->sizes[ $size ]['url'] ) ? $this->sizes[ $size ]['url'] : $this->url;
	}

	/**
	 * Retrieves the absolute filesystem path for a specific image size or original file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $size Image size name.
	 * @return string Absolute file path, or empty string if post is not loaded.
	 */
	public function get_path( string $size = 'full' ): string {
		if ( null === $this->post ) {
			return '';
		}

		if ( 'full' === $size || empty( $size ) ) {
			return $this->file_path;
		}

		return isset( $this->sizes[ $size ]['path'] ) ? $this->sizes[ $size ]['path'] : $this->file_path;
	}

	/**
	 * Retrieves image dimensions for a specific size.
	 *
	 * @since 1.0.0
	 *
	 * @param string $size Image size name.
	 * @return array Array with 'width' and 'height' keys.
	 */
	public function get_dimensions( string $size = 'full' ): array {
		if ( 'full' === $size || empty( $size ) ) {
			return $this->dimensions;
		}

		if ( isset( $this->sizes[ $size ] ) ) {
			return array(
				'width'  => $this->sizes[ $size ]['width'],
				'height' => $this->sizes[ $size ]['height'],
			);
		}

		return $this->dimensions;
	}

	/**
	 * Deletes this attachment and its associated physical files.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $force Whether to bypass trash and force deletion.
	 * @return bool True on success, false on failure.
	 * @throws \Exception If database or filesystem write protection is enabled.
	 */
	public function delete( bool $force = false ): bool {
		if ( defined( 'EXPRESSION_LAB_DATABASE_READONLY' ) && EXPRESSION_LAB_DATABASE_READONLY ) {
			throw new \Exception( esc_html( 'Operation denied. Write protection is enabled. Set `EXPRESSION_LAB_DATABASE_READONLY` to `false` in `wp-config.php`.' ) );
		}

		if ( defined( 'EXPRESSION_LAB_FILESYSTEM_READONLY' ) && EXPRESSION_LAB_FILESYSTEM_READONLY ) {
			throw new \Exception( esc_html( 'Operation denied. Filesystem write protection is enabled. Set `EXPRESSION_LAB_FILESYSTEM_READONLY` to `false` in `wp-config.php`.' ) );
		}

		if ( null === $this->post || ! $this->ID ) {
			return false;
		}

		return (bool) wp_delete_attachment( $this->ID, $force );
	}

	/**
	 * Formats a byte count into a human-readable string.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param int $bytes Number of bytes.
	 * @return string Formatted byte size string.
	 */
	private function format_bytes( int $bytes ): string {
		if ( $bytes <= 0 ) {
			return '0 B';
		}

		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
		$i     = (int) floor( log( $bytes, 1024 ) );
		$i     = min( $i, count( $units ) - 1 );

		return round( $bytes / pow( 1024, $i ), 2 ) . ' ' . $units[ $i ];
	}

	/**
	 * Retrieves an associative array representation of the attachment.
	 *
	 * @since 1.0.0
	 *
	 * @return array Associative array of attachment attributes.
	 */
	public function get_value() {
		if ( null === $this->post ) {
			return array();
		}

		return array(
			'ID'             => $this->ID,
			'title'          => $this->title,
			'caption'        => $this->caption,
			'description'    => $this->description,
			'alt'            => $this->alt,
			'mime_type'      => $this->mime_type,
			'url'            => $this->url,
			'file_path'      => $this->file_path,
			'filesize'       => $this->filesize,
			'filesize_human' => $this->filesize_human,
			'dimensions'     => $this->dimensions,
			'date'           => $this->date,
			'modified'       => $this->modified,
			'author_id'      => $this->author_id,
			'parent_id'      => $this->parent_id,
		);
	}
}
