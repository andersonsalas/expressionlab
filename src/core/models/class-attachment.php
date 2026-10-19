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
use ExpressionLab\Core\Helper;
use ExpressionLab\Core\LanguageEngine;
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
 * @since 0.0.1
 * @package ExpressionLab
 */
final class Attachment extends Model {
	/**
	 * WordPress post instance.
	 *
	 * @since 0.0.1
	 * @internal
	 * @var WP_Post|null
	 */
	private $post;

	/**
	 * Database service instance.
	 *
	 * @since 0.0.1
	 * @internal
	 * @var Database
	 */
	private $database;

	/**
	 * Attachment ID.
	 *
	 * @since 0.0.1
	 * @var int|null
	 */
	public $ID;

	/**
	 * Attachment title.
	 *
	 * @since 0.0.1
	 * @var string
	 */
	public $title = '';

	/**
	 * Caption (post_excerpt).
	 *
	 * @since 0.0.1
	 * @var string
	 */
	public $caption = '';

	/**
	 * Description (post_content).
	 *
	 * @since 0.0.1
	 * @var string
	 */
	public $description = '';

	/**
	 * Alternative text for images (_wp_attachment_image_alt).
	 *
	 * @since 0.0.1
	 * @var string
	 */
	public $alt = '';

	/**
	 * MIME type (e.g. 'image/jpeg', 'application/pdf').
	 *
	 * @since 0.0.1
	 * @var string
	 */
	public $mime_type = '';

	/**
	 * Full URL to the media file.
	 *
	 * @since 0.0.1
	 * @var string
	 */
	public $url = '';

	/**
	 * Absolute filesystem path to the original file.
	 *
	 * @since 0.0.1
	 * @var string
	 */
	public $file_path = '';

	/**
	 * File size in bytes.
	 *
	 * @since 0.0.1
	 * @var int
	 */
	public $filesize = 0;

	/**
	 * Human-readable file size (e.g. "1.24 MB").
	 *
	 * @since 0.0.1
	 * @var string
	 */
	public $filesize_human = '';

	/**
	 * Dimensions array with 'width' and 'height' keys.
	 *
	 * @since 0.0.1
	 * @var array
	 */
	public $dimensions = array();

	/**
	 * Array of registered thumbnail sub-sizes with URLs and dimensions.
	 *
	 * @since 0.0.1
	 * @var array
	 */
	public $sizes = array();

	/**
	 * Creation date (Y-m-d H:i:s).
	 *
	 * @since 0.0.1
	 * @var string
	 */
	public $date = '';

	/**
	 * Modified date (Y-m-d H:i:s).
	 *
	 * @since 0.0.1
	 * @var string
	 */
	public $modified = '';

	/**
	 * Author user ID.
	 *
	 * @since 0.0.1
	 * @var int
	 */
	public $author_id = 0;

	/**
	 * Parent post ID (0 if unattached/orphan).
	 *
	 * @since 0.0.1
	 * @var int
	 */
	public $parent_id = 0;

	/**
	 * Scoped metadata manager.
	 *
	 * @since 0.0.1
	 * @var PostMeta|null
	 */
	public $meta;

	/**
	 * Constructor.
	 *
	 * @since 0.0.1
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

			// Compute file size safely.
			if ( ! empty( $this->file_path ) ) {
				try {
					$safe_file_path = $this->resolve_safe_path( $this->file_path, true );
					// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					$this->filesize       = (int) @filesize( $safe_file_path );
					$this->filesize_human = $this->format_bytes( $this->filesize );
				} catch ( \Exception $e ) {
					$this->filesize       = 0;
					$this->filesize_human = $this->format_bytes( 0 );
				}
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
						$file_name     = $size_info['file'] ?? '';
						$sub_path_file = $base_dir . $sub_path . $file_name;
						$fsize         = (int) ( $size_info['filesize'] ?? 0 );

						if ( 0 === $fsize && ! empty( $sub_path_file ) ) {
							try {
								$safe_sub_path = $this->resolve_safe_path( $sub_path_file, true );
								// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
								$fsize = (int) @filesize( $safe_sub_path );
							} catch ( \Exception $e ) {
								$fsize = 0;
							}
						}
						$this->sizes[ $size_name ] = array(
							'file'           => $file_name,
							'width'          => (int) ( $size_info['width'] ?? 0 ),
							'height'         => (int) ( $size_info['height'] ?? 0 ),
							'mime_type'      => (string) ( $size_info['mime-type'] ?? '' ),
							'filesize'       => $fsize,
							'filesize_human' => $this->format_bytes( $fsize ),
							'url'            => $base_url . $sub_path . $file_name,
							'path'           => $sub_path_file,
							'file_path'      => $sub_path_file,
						);
					}
				}
			}
		}
	}

	/**
	 * Retrieves the URL for a specific image size or the original file.
	 *
	 * @since 0.0.1
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
	 * @since 0.0.1
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

		if ( isset( $this->sizes[ $size ]['file_path'] ) ) {
			return $this->sizes[ $size ]['file_path'];
		}

		return isset( $this->sizes[ $size ]['path'] ) ? $this->sizes[ $size ]['path'] : $this->file_path;
	}

	/**
	 * Retrieves image dimensions for a specific size.
	 *
	 * @since 0.0.1
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
	 * @since 0.0.1
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
	 * @since 0.0.1
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
	 * Resolves an attachment file path safely, ensuring it is confined to allowed boundaries
	 * and free from stream wrappers, null bytes, or directory traversal.
	 *
	 * @since 0.3.0
	 * @internal
	 *
	 * @param string $path       The filesystem path to validate.
	 * @param bool   $must_exist Whether the file must exist on disk. Default true.
	 * @return string The canonicalized absolute path.
	 * @throws \InvalidArgumentException If the path resolves outside allowed boundaries, contains stream wrappers or null bytes.
	 */
	private function resolve_safe_path( string $path, bool $must_exist = true ): string {
		$path = trim( $path );
		if ( '' === $path ) {
			throw new \InvalidArgumentException( 'Access denied: Path cannot be empty.' );
		}

		// Reject stream wrappers (e.g. phar://, php://, file://, compress.zlib://).
		if ( preg_match( '/(?:^|[\\\\\/])[a-z0-9][a-z0-9.+\-]*:\/\//i', $path ) ) {
			throw new \InvalidArgumentException( 'Access denied: Stream wrappers are not permitted.' );
		}

		// Reject null byte injection or invalid control characters.
		if ( str_contains( $path, "\0" ) ) {
			throw new \InvalidArgumentException( 'Access denied: Path contains invalid characters.' );
		}

		$upload_dir = wp_upload_dir();
		$basedir    = ( empty( $upload_dir['error'] ) && ! empty( $upload_dir['basedir'] ) )
			? (string) $upload_dir['basedir']
			: ( defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH );

		try {
			return Helper::resolve_safe_path( $path, $basedir, $must_exist );
		} catch ( \InvalidArgumentException $e ) {
			if ( $must_exist && str_contains( $e->getMessage(), 'does not exist' ) ) {
				throw $e;
			}

			// If not in uploads basedir, check WP_CONTENT_DIR if distinct from basedir.
			if ( defined( 'WP_CONTENT_DIR' ) && WP_CONTENT_DIR !== $basedir ) {
				return Helper::resolve_safe_path( $path, WP_CONTENT_DIR, $must_exist );
			}

			throw $e;
		}
	}

	/**
	 * Generates an interactive image visualization for this attachment in the console.
	 *
	 * Resolves the physical path for the requested sub-size (default `'medium'`), verifies
	 * MIME magic bytes via finfo, guards against oversized files, encodes image bytes
	 * in Base64, and registers an `'image'` visualization payload.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * Media.get(42).show()
	 * ```
	 *
	 * ```elscript
	 * Media.get(42).show('large')
	 * ```
	 *
	 * @since 0.3.0
	 *
	 * @param string $size Optional. Image size name (`'medium'`, `'thumbnail'`, `'large'`, `'full'`). Default `'medium'`.
	 * @return self This attachment instance for method chaining.
	 * @throws \RuntimeException If physical file does not exist, is unreadable, exceeds size limit, or is not an allowed image format.
	 */
	public function show( string $size = 'medium' ): self {
		LanguageEngine::get()->tick();

		$path          = $this->get_path( $size );
		$resolved_size = $size;

		$is_valid_file = function ( ?string $candidate ): bool {
			if ( empty( $candidate ) ) {
				return false;
			}
			try {
				$this->resolve_safe_path( $candidate, true );
				return true;
			} catch ( \Exception $e ) {
				return false;
			}
		};

		// Fallback for 'medium' or requested size when not found.
		if ( ! $is_valid_file( $path ) ) {
			if ( 'medium' === $size && ! empty( $this->sizes ) ) {
				$thumb_path = $this->sizes['thumbnail']['file_path'] ?? ( $this->sizes['thumbnail']['path'] ?? null );
				if ( $is_valid_file( $thumb_path ) ) {
					$path          = $thumb_path;
					$resolved_size = 'thumbnail';
				} else {
					// Find the smallest variant available.
					$smallest_size  = null;
					$smallest_bytes = PHP_INT_MAX;
					foreach ( $this->sizes as $s_name => $s_info ) {
						$s_path = $s_info['file_path'] ?? ( $s_info['path'] ?? '' );
						if ( $is_valid_file( $s_path ) ) {
							try {
								$safe_s_path = $this->resolve_safe_path( $s_path, true );
								// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
								$raw_fsize = @filesize( $safe_s_path );
								$s_bytes   = (int) ( $s_info['filesize'] ?? ( false !== $raw_fsize ? $raw_fsize : 0 ) );
								if ( $s_bytes < $smallest_bytes ) {
									$smallest_bytes = $s_bytes;
									$smallest_size  = $s_name;
									$path           = $s_path;
								}
							// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
							} catch ( \Exception $e ) {
								// Ignore invalid candidate.
							}
						}
					}
					if ( null !== $smallest_size ) {
						$resolved_size = $smallest_size;
					}
				}
			}

			// Fall back to original file if size was not found.
			if ( ! $is_valid_file( $path ) ) {
				$path          = $this->file_path;
				$resolved_size = 'full';
			}
		}

		if ( empty( $path ) ) {
			throw new \RuntimeException( esc_html( "Attachment #{$this->ID} physical file not found or unreadable." ) );
		}

		try {
			$path = $this->resolve_safe_path( $path, true );
		} catch ( \InvalidArgumentException $e ) {
			if ( str_contains( $e->getMessage(), 'does not exist' ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				throw new \RuntimeException( esc_html( "Attachment #{$this->ID} physical file not found or unreadable." ), 0, $e );
			}
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \RuntimeException( esc_html( "Attachment #{$this->ID} physical file path is invalid or outside allowed boundary." ), 0, $e );
		}

		if ( ! is_readable( $path ) ) {
			throw new \RuntimeException( esc_html( "Attachment #{$this->ID} physical file not found or unreadable." ) );
		}

		// Validate magic bytes.
		$mime = $this->mime_type;
		if ( class_exists( '\finfo' ) ) {
			$finfo = new \finfo( FILEINFO_MIME_TYPE );
			$res   = $finfo->file( $path );
			if ( false !== $res ) {
				$mime = (string) $res;
			}
		}

		$allowed_mimes = array(
			'image/jpeg',
			'image/png',
			'image/gif',
			'image/webp',
			'image/avif',
			'image/svg+xml',
		);

		if ( ! in_array( $mime, $allowed_mimes, true ) ) {
			throw new \RuntimeException( esc_html( "Attachment #{$this->ID} file is not a supported image format ({$mime})." ) );
		}

		$bytes = (int) filesize( $path );
		if ( $bytes > LanguageEngine::MAX_EMBEDDED_BINARY_BYTES || ! LanguageEngine::get()->can_allocate_binary( $bytes ) ) {
			throw new \RuntimeException( esc_html( "Attachment image file exceeds maximum preview budget of {$this->format_bytes( LanguageEngine::MAX_EMBEDDED_BINARY_BYTES )} ({$this->format_bytes( $bytes )})." ) );
		}

		$dimensions = $this->get_dimensions( $resolved_size );
		if ( empty( $dimensions['width'] ) && 'image/svg+xml' !== $mime && function_exists( 'getimagesize' ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$img_info = @getimagesize( $path );
			if ( is_array( $img_info ) ) {
				$dimensions = array(
					'width'  => (int) $img_info[0],
					'height' => (int) $img_info[1],
				);
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$raw_data = file_get_contents( $path );
		if ( false === $raw_data ) {
			throw new \RuntimeException( esc_html( "Failed to read attachment file: {$path}" ) );
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$base64 = base64_encode( $raw_data );
		LanguageEngine::get()->allocate_binary( $bytes );

		LanguageEngine::get()->begin_visualization_group()->add_visualization(
			array(
				'type'          => 'image',
				'title'         => 'Image: ' . ( ! empty( $this->title ) ? $this->title : basename( $path ) ),
				'filename'      => basename( $path ),
				'path'          => $path,
				'mime'          => $mime,
				'base64'        => $base64,
				'dimensions'    => $dimensions,
				'bytes'         => $bytes,
				'size_human'    => $this->format_bytes( $bytes ),
				'size_name'     => $resolved_size,
				'attachment_id' => $this->ID,
			)
		);

		return $this;
	}

	/**
	 * Retrieves an associative array representation of the attachment.
	 *
	 * @since 0.0.1
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
			'sizes'          => $this->sizes,
		);
	}
}
