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

use ExpressionLab\Core\Helper;
use ExpressionLab\Core\LanguageEngine;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * Files Service.
 *
 * Provides safe, strictly read-only filesystem auditing, inspection, and diagnostics
 * operations confined to the WordPress root directory (`ABSPATH`).
 *
 * @since 0.0.1
 *
 * @package ExpressionLab
 */
final class Files {
	/**
	 * Maximum allowed bytes to read in a single read() call (256 KB).
	 */
	const MAX_READ_BYTES = 262144;

	/**
	 * Maximum allowed bytes to accumulate in a single tail() call (1 MB).
	 */
	const MAX_TAIL_BYTES = 1048576;

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
	 * Resolves a path, verifying that it resides within the WordPress root directory (ABSPATH).
	 *
	 * @internal
	 *
	 * @param string $path       The relative or absolute path.
	 * @param bool   $must_exist Whether the path must already exist. Default true.
	 * @return string The canonicalized absolute path.
	 * @throws \InvalidArgumentException If the path resolves outside ABSPATH.
	 */
	private function resolve_safe_path( string $path, bool $must_exist = true ): string {
		return Helper::resolve_safe_path( $path, ABSPATH, $must_exist );
	}

	/**
	 * Formats bytes to a human-readable string.
	 *
	 * @internal
	 *
	 * @param int $bytes Number of bytes.
	 * @return string Formatted byte string.
	 */
	private function format_bytes( int $bytes ): string {
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
		$bytes = max( $bytes, 0 );
		$pow   = (int) floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
		$pow   = min( $pow, count( $units ) - 1 );
		$bytes = $bytes / pow( 1024, $pow );

		return round( $bytes, 2 ) . ' ' . $units[ $pow ];
	}

	/**
	 * Check whether a file or directory exists within the WordPress root directory.
	 *
	 * Evaluates path existence safely within `ABSPATH`. If the path resolves outside
	 * `ABSPATH` or is invalid, the check safely returns `false` without throwing an exception.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * Files.exists('wp-content/debug.log')
	 * ```
	 *
	 * ```elscript
	 * Files.exists('wp-config.php')
	 * ```
	 *
	 * ```elscript
	 * Files.exists('wp-content/uploads/2026/08')
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/files#filesexists
	 *
	 * @param string $path File or directory path relative to ABSPATH or absolute within ABSPATH.
	 * @return bool True if target exists within ABSPATH, false otherwise.
	 */
	public function exists( string $path ): bool {
		try {
			$safe_path = $this->resolve_safe_path( $path, true );
			return file_exists( $safe_path );
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Check whether the specified path exists and is a regular file.
	 *
	 * Conforms to `ABSPATH` boundaries. Returns `false` if the target is a directory,
	 * does not exist, or attempts directory traversal outside `ABSPATH`.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * Files.is_file('wp-config.php')
	 * ```
	 *
	 * ```elscript
	 * Files.is_file('wp-content/plugins')
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/files#filesis_file
	 *
	 * @param string $path File path relative to ABSPATH or absolute within ABSPATH.
	 * @return bool True if the path exists and is a regular file, false otherwise.
	 */
	public function is_file( string $path ): bool {
		try {
			$safe_path = $this->resolve_safe_path( $path, true );
			return is_file( $safe_path );
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Check whether the specified path exists and is a directory.
	 *
	 * Conforms to `ABSPATH` boundaries. Returns `false` if the target is a regular file,
	 * does not exist, or attempts directory traversal outside `ABSPATH`.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * Files.is_dir('wp-content/plugins')
	 * ```
	 *
	 * ```elscript
	 * Files.is_dir('wp-load.php')
	 * ```
	 *
	 * @param string $path Directory path relative to ABSPATH or absolute within ABSPATH.
	 * @see https://expressionlab.io/docs/api-reference/files#filesis_dir
	 * @return bool True if the path exists and is a directory, false otherwise.
	 */
	public function is_dir( string $path ): bool {
		try {
			$safe_path = $this->resolve_safe_path( $path, true );
			return is_dir( $safe_path );
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Retrieve the size of a file or directory in bytes and as a formatted human-readable string.
	 *
	 * Return structure:
	 * * `bytes` (`int`): Exact file or directory size in bytes.
	 * * `human` (`string`): Formatted size string with binary units (`B`, `KB`, `MB`, `GB`, `TB`) rounded to two decimal places.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * Files.size('wp-config.php')
	 * ```
	 *
	 * ```elscript
	 * Files.size('wp-content/debug.log')
	 * ```
	 *
	 * ```elscript
	 * Files.size('wp-load.php')['bytes']
	 * ```
	 *
	 * ```elscript
	 * Files.size('wp-content/debug.log')['human']
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/files#filessize
	 *
	 * @param string $path File or directory path relative to ABSPATH or absolute within ABSPATH.
	 * @return array Associative array containing `bytes` (int) and `human` (string).
	 * @throws \InvalidArgumentException If the path does not exist or resolves outside ABSPATH.
	 */
	public function size( string $path ): array {
		$safe_path = $this->resolve_safe_path( $path, true );
		LanguageEngine::get()->tick();

		$bytes = (int) filesize( $safe_path );

		return array(
			'bytes' => $bytes,
			'human' => $this->format_bytes( $bytes ),
		);
	}

	/**
	 * Retrieve the last modification timestamp and UTC ISO-formatted datetime string.
	 *
	 * Return structure:
	 * * `timestamp` (`int`): Unix epoch timestamp of the last modification.
	 * * `formatted` (`string`): UTC formatted datetime string (`YYYY-MM-DD HH:MM:SS UTC`).
	 *
	 * Examples:
	 *
	 * ```elscript
	 * Files.modified('wp-config.php')
	 * ```
	 *
	 * ```elscript
	 * Files.modified('wp-content/plugins')['formatted']
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/files#filesmodified
	 *
	 * @param string $path File or directory path relative to ABSPATH or absolute within ABSPATH.
	 * @return array Associative array containing `timestamp` (int) and `formatted` (string).
	 * @throws \InvalidArgumentException If the path does not exist or resolves outside ABSPATH.
	 */
	public function modified( string $path ): array {
		$safe_path = $this->resolve_safe_path( $path, true );
		$mtime     = (int) filemtime( $safe_path );

		return array(
			'timestamp' => $mtime,
			'formatted' => gmdate( 'Y-m-d H:i:s', $mtime ) . ' UTC',
		);
	}

	/**
	 * Read the content of a file up to a specified maximum byte limit.
	 *
	 * Reads file content safely within `ABSPATH`. The `$max_bytes` parameter is clamped
	 * between `1` and `Files.MAX_READ_BYTES` (`262,144` bytes = `256` KB) to prevent memory exhaustion.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * Files.read('wp-content/debug.log')
	 * ```
	 *
	 * ```elscript
	 * Files.read('wp-config.php', 512)
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/files#filesread
	 *
	 * @param string $path      File path relative to ABSPATH or absolute within ABSPATH.
	 * @param int    $max_bytes Optional. Maximum number of bytes to read (clamped between `1` and `262144`). Default `Files.MAX_READ_BYTES` (`262144`).
	 * @return string|false File content or Data URI on success, or `false` if binary file cannot be read as text or image exceeds budget.
	 * @throws \InvalidArgumentException|\RuntimeException If the path does not exist or resolves outside ABSPATH, or if the target path is a directory.
	 */
	public function read( string $path, int $max_bytes = self::MAX_READ_BYTES ) {
		$safe_path = $this->resolve_safe_path( $path, true );

		if ( is_dir( $safe_path ) ) {
			throw new \RuntimeException( 'Cannot read a directory as a file.' );
		}

		LanguageEngine::get()->tick();

		$mime = 'application/octet-stream';
		if ( class_exists( '\finfo' ) ) {
			$finfo = new \finfo( FILEINFO_MIME_TYPE );
			$res   = $finfo->file( $safe_path );
			if ( false !== $res ) {
				$mime = (string) $res;
			}
		}

		$filename    = basename( $safe_path );
		$image_mimes = array(
			'image/jpeg',
			'image/png',
			'image/gif',
			'image/webp',
			'image/avif',
			'image/svg+xml',
		);

		// Handle images: read if within budget and return Data URI.
		if ( in_array( $mime, $image_mimes, true ) ) {
			$size = (int) filesize( $safe_path );
			if ( $size > LanguageEngine::MAX_EMBEDDED_BINARY_BYTES || ! LanguageEngine::get()->can_allocate_binary( $size ) ) {
				LanguageEngine::get()->add_message(
					'warning',
					sprintf( 'Image file exceeds maximum preview budget of %s (%s).', $this->format_bytes( LanguageEngine::MAX_EMBEDDED_BINARY_BYTES ), $this->format_bytes( $size ) )
				);
				return false;
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$raw_bytes = file_get_contents( $safe_path );
			if ( false === $raw_bytes ) {
				throw new \RuntimeException( 'Failed to read image file contents.' );
			}

			$dimensions = array(
				'width'  => 0,
				'height' => 0,
			);

			if ( 'image/svg+xml' !== $mime && function_exists( 'getimagesize' ) ) {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				$img_info = @getimagesize( $safe_path );
				if ( is_array( $img_info ) ) {
					$dimensions = array(
						'width'  => (int) $img_info[0],
						'height' => (int) $img_info[1],
					);
				}
			}

			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			$base64 = base64_encode( $raw_bytes );
			LanguageEngine::get()->allocate_binary( $size );

			LanguageEngine::get()->begin_visualization_group()->add_visualization(
				array(
					'type'       => 'image',
					'title'      => "Image: {$filename}",
					'filename'   => $filename,
					'path'       => $path,
					'mime'       => $mime,
					'base64'     => $base64,
					'dimensions' => $dimensions,
					'bytes'      => $size,
					'size_human' => $this->format_bytes( $size ),
				)
			);

			return "data:{$mime};base64,{$base64}";
		}

		// Check for non-image binary file.
		$is_text_mime = str_starts_with( $mime, 'text/' ) || in_array(
			$mime,
			array(
				'application/json',
				'application/xml',
				'application/javascript',
				'application/x-javascript',
				'application/x-php',
				'application/x-httpd-php',
				'application/sql',
				'application/x-sh',
				'application/xhtml+xml',
			),
			true
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$sample         = file_get_contents( $safe_path, false, null, 0, 512 );
		$has_null_bytes = false !== $sample && str_contains( $sample, "\0" );

		if ( ! $is_text_mime || $has_null_bytes ) {
			LanguageEngine::get()->add_message(
				'warning',
				sprintf( 'Binary file detected: %s cannot be read as plain text.', $filename )
			);
			return false;
		}

		$max_bytes = min( max( 1, $max_bytes ), self::MAX_READ_BYTES );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = file_get_contents( $safe_path, false, null, 0, $max_bytes );

		if ( false === $content ) {
			throw new \RuntimeException( 'Failed to read file contents.' );
		}

		LanguageEngine::get()->begin_visualization_group()->add_visualization(
			array(
				'type'       => 'file',
				'title'      => 'File: ' . $filename,
				'filename'   => $filename,
				'path'       => $path,
				'content'    => $content,
				'bytes'      => strlen( $content ),
				'size_human' => $this->format_bytes( strlen( $content ) ),
				'ext'        => strtolower( pathinfo( $safe_path, PATHINFO_EXTENSION ) ),
				'line_count' => substr_count( $content, "\n" ) + 1,
			)
		);

		return $content;
	}

	/**
	 * Reads the last N lines of a file without loading the entire file into memory.
	 *
	 * Reads lines by seeking backwards from EOF in binary mode (`fseek`), making it ideal
	 * for large log files (e.g. `debug.log`). The `$lines` argument is clamped between `1` and `500`.
	 * Buffer accumulation is guarded by `Files.MAX_TAIL_BYTES` (1 MB) against runaway single-line files.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * Files.tail('wp-content/debug.log')
	 * ```
	 *
	 * ```elscript
	 * Files.tail('wp-content/debug.log', 15)
	 * ```
	 *
	 * ```elscript
	 * Files.tail('wp-content/debug.log', 500)
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/files#filestail
	 *
	 * @param string $path  File path relative to ABSPATH or absolute within ABSPATH.
	 * @param int    $lines Number of lines to retrieve from the end (clamped between `1` and `500`). Default `50`.
	 * @return array Array of lines retrieved from the end of the file.
	 * @throws \InvalidArgumentException|\RuntimeException If the path does not exist or resolves outside ABSPATH, or if the target path is a directory or cannot be opened for reading.
	 */
	public function tail( string $path, int $lines = 50 ): array {
		$safe_path = $this->resolve_safe_path( $path, true );

		if ( is_dir( $safe_path ) ) {
			throw new \RuntimeException( 'Cannot tail a directory.' );
		}

		$lines = min( max( 1, $lines ), 500 );
		LanguageEngine::get()->tick();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$file = fopen( $safe_path, 'rb' );
		if ( ! $file ) {
			throw new \RuntimeException( 'Failed to open file for tailing.' );
		}

		$output     = array();
		$buffer_len = 4096;

		try {
			fseek( $file, 0, SEEK_END );
			$pos        = ftell( $file );
			$str        = '';
			$str_len    = 0;
			$line_count = 0;

			while ( $pos > 0 && $line_count <= $lines ) {
				LanguageEngine::get()->tick();

				$seek = min( $pos, $buffer_len );
				$pos -= $seek;
				fseek( $file, $pos, SEEK_SET );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
				$chunk    = fread( $file, $seek );
				$str      = $chunk . $str;
				$str_len += $seek;

				// Guard against unbounded memory growth (e.g., a massive single-line file).
				if ( $str_len > self::MAX_TAIL_BYTES ) {
					break;
				}

				$output     = explode( "\n", $str );
				$line_count = count( $output );
			}
		} finally {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			fclose( $file );
		}

		$lines_slice = array_slice( $output, -$lines );
		$joined      = implode( "\n", $lines_slice );

		if ( str_contains( $joined, "\0" ) ) {
			LanguageEngine::get()->add_message(
				'warning',
				sprintf( 'Binary file detected: %s cannot be visualized as plain text.', basename( $safe_path ) )
			);
			return $lines_slice;
		}

		LanguageEngine::get()->begin_visualization_group()->add_visualization(
			array(
				'type'       => 'file',
				'title'      => 'File: ' . basename( $safe_path ) . " (tail {$lines})",
				'filename'   => basename( $safe_path ),
				'path'       => $path,
				'content'    => $joined,
				'bytes'      => strlen( $joined ),
				'size_human' => $this->format_bytes( strlen( $joined ) ),
				'ext'        => strtolower( pathinfo( $safe_path, PATHINFO_EXTENSION ) ),
				'line_count' => count( $lines_slice ),
			)
		);

		return $lines_slice;
	}

	/**
	 * Inspect and generate an interactive visualization for a file or image within ABSPATH.
	 *
	 * Detects the MIME type of the target file via magic bytes. If the target is an image
	 * (`image/jpeg`, `image/png`, `image/gif`, `image/webp`, `image/avif`, `image/svg+xml`),
	 * verifies dimensions and size (up to 2 MB) and registers an `'image'` visualization.
	 * If the file is code or text, reads content up to `$max_bytes` and registers a `'file'` visualization.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * Files.show('wp-config.php')
	 * ```
	 *
	 * ```elscript
	 * Files.show('wp-content/themes/twentytwentyfour/screenshot.png')
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/files#filesshow
	 *
	 * @param string $path      File path relative to ABSPATH or absolute within ABSPATH.
	 * @param int    $max_bytes Optional. Maximum number of bytes to read for text files. Default `Files.MAX_READ_BYTES`.
	 * @return array Diagnostic file metadata summary.
	 * @throws \InvalidArgumentException|\RuntimeException If the path does not exist, resolves outside ABSPATH, or target is a directory.
	 */
	public function show( string $path, int $max_bytes = self::MAX_READ_BYTES ): array {
		$safe_path = $this->resolve_safe_path( $path, true );

		if ( is_dir( $safe_path ) ) {
			throw new \RuntimeException( 'Cannot show a directory as a file.' );
		}

		LanguageEngine::get()->tick();

		$mime = 'application/octet-stream';
		if ( class_exists( '\finfo' ) ) {
			$finfo = new \finfo( FILEINFO_MIME_TYPE );
			$res   = $finfo->file( $safe_path );
			if ( false !== $res ) {
				$mime = (string) $res;
			}
		}

		$filename = basename( $safe_path );
		$ext      = strtolower( pathinfo( $safe_path, PATHINFO_EXTENSION ) );
		$size     = (int) filesize( $safe_path );

		$image_mimes = array(
			'image/jpeg',
			'image/png',
			'image/gif',
			'image/webp',
			'image/avif',
			'image/svg+xml',
		);

		if ( in_array( $mime, $image_mimes, true ) ) {
			if ( $size > LanguageEngine::MAX_EMBEDDED_BINARY_BYTES || ! LanguageEngine::get()->can_allocate_binary( $size ) ) {
				throw new \RuntimeException( esc_html( "Image file exceeds maximum preview budget of {$this->format_bytes( LanguageEngine::MAX_EMBEDDED_BINARY_BYTES )} ({$this->format_bytes( $size )})." ) );
			}

			$dimensions = array(
				'width'  => 0,
				'height' => 0,
			);

			if ( 'image/svg+xml' !== $mime && function_exists( 'getimagesize' ) ) {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				$img_info = @getimagesize( $safe_path );
				if ( is_array( $img_info ) ) {
					$dimensions = array(
						'width'  => (int) $img_info[0],
						'height' => (int) $img_info[1],
					);
				}
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$raw_bytes = file_get_contents( $safe_path );
			if ( false === $raw_bytes ) {
				throw new \RuntimeException( 'Failed to read image file contents.' );
			}

			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			$base64 = base64_encode( $raw_bytes );
			LanguageEngine::get()->allocate_binary( $size );

			LanguageEngine::get()->begin_visualization_group()->add_visualization(
				array(
					'type'       => 'image',
					'title'      => "Image: {$filename}",
					'filename'   => $filename,
					'path'       => $path,
					'mime'       => $mime,
					'base64'     => $base64,
					'dimensions' => $dimensions,
					'bytes'      => $size,
					'size_human' => $this->format_bytes( $size ),
				)
			);

			return array(
				'path'       => $path,
				'name'       => $filename,
				'type'       => 'image',
				'mime'       => $mime,
				'bytes'      => $size,
				'size'       => $this->format_bytes( $size ),
				'dimensions' => $dimensions,
			);
		}

		// Text/code file.
		$max_bytes = min( max( 1, $max_bytes ), self::MAX_READ_BYTES );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = file_get_contents( $safe_path, false, null, 0, $max_bytes );
		if ( false === $content ) {
			throw new \RuntimeException( 'Failed to read file contents.' );
		}

		if ( str_contains( substr( $content, 0, 512 ), "\0" ) ) {
			throw new \RuntimeException( esc_html( "Binary file detected: {$filename} cannot be shown as plain text." ) );
		}

		LanguageEngine::get()->begin_visualization_group()->add_visualization(
			array(
				'type'       => 'file',
				'title'      => "File: {$filename}",
				'filename'   => $filename,
				'path'       => $path,
				'content'    => $content,
				'bytes'      => strlen( $content ),
				'size_human' => $this->format_bytes( strlen( $content ) ),
				'mime'       => $mime,
				'ext'        => $ext,
				'line_count' => substr_count( $content, "\n" ) + 1,
			)
		);

		return array(
			'path'       => $path,
			'name'       => $filename,
			'type'       => 'file',
			'mime'       => $mime,
			'bytes'      => strlen( $content ),
			'size'       => $this->format_bytes( strlen( $content ) ),
			'line_count' => substr_count( $content, "\n" ) + 1,
		);
	}

	/**
	 * List contents of a directory with item type, formatted size, and modification timestamp.
	 *
	 * Inspects immediate directory entries within `ABSPATH`. Dot entries (`.` and `..`) are excluded.
	 * Results are sorted with directories first (alphabetical, case-insensitive), followed by
	 * regular files (alphabetical, case-insensitive).
	 *
	 * Return item structure:
	 * * `name` (`string`): Filename or directory name.
	 * * `type` (`string`): Entry type (`'dir'` or `'file'`).
	 * * `size` (`string|null`): Formatted human-readable size for files, or `null` for directories.
	 * * `modified` (`string`): UTC modification datetime formatted as `YYYY-MM-DD HH:MM:SS`.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * Files.list()
	 * ```
	 *
	 * ```elscript
	 * Files.list('wp-content')
	 * ```
	 *
	 * ```elscript
	 * Files.list('wp-content/plugins')
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/files#filelist
	 *
	 * @param string $path Directory path relative to ABSPATH or absolute within ABSPATH. Default `'.'`.
	 * @return array Indexed array of associative arrays with `name`, `type`, `size`, and `modified`.
	 * @throws \InvalidArgumentException|\RuntimeException If the path does not exist or resolves outside ABSPATH, or if the specified path is not a directory.
	 */
	public function list( string $path = '.' ): array {
		$safe_path = $this->resolve_safe_path( $path, true );

		if ( ! is_dir( $safe_path ) ) {
			throw new \RuntimeException( esc_html( "The specified path is not a directory: $path" ) );
		}

		LanguageEngine::get()->tick();

		$dir   = new \DirectoryIterator( $safe_path );
		$items = array();

		foreach ( $dir as $item ) {
			LanguageEngine::get()->tick();

			if ( $item->isDot() ) {
				continue;
			}

			$items[] = array(
				'name'     => $item->getFilename(),
				'type'     => $item->isDir() ? 'dir' : 'file',
				'size'     => $item->isDir() ? null : $this->format_bytes( (int) $item->getSize() ),
				'modified' => gmdate( 'Y-m-d H:i:s', $item->getMTime() ),
			);
		}

		usort(
			$items,
			function ( $a, $b ) {
				if ( $a['type'] !== $b['type'] ) {
					return 'dir' === $a['type'] ? -1 : 1;
				}
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return $items;
	}

	/**
	 * Compute disk space usage statistics for a directory and render visual breakdowns.
	 *
	 * Aggregates file counts and byte sizes grouped by file extension. Automatically generates
	 * interactive visual diagnostics in the Expression Lab console:
	 * 1. **Table Visualization**: Tabular distribution (`Table: Directory disk distribution by extension`) displaying `extension`, `files`, `size`, and `bytes`.
	 * 2. **Radial Chart**: Interactive radial (pie) chart (`Graph: Disk size distribution by extension`) displaying cumulative disk size per file extension.
	 *
	 * Return structure:
	 * * `path` (`string`): Normalized absolute path of the inspected directory.
	 * * `files` (`int`): Total count of regular files in the directory.
	 * * `directories` (`int`): Total count of immediate subdirectories.
	 * * `total_bytes` (`int`): Cumulative size of all files in bytes.
	 * * `total_size` (`string`): Formatted human-readable cumulative size string.
	 * * `by_ext` (`array`): List of extension summary records sorted descending by cumulative byte size.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * Files.stats()
	 * ```
	 *
	 * ```elscript
	 * Files.stats('wp-content/uploads')
	 * ```
	 *
	 * ```elscript
	 * Files.stats('wp-content/themes')['files']
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/files#filesstats
	 *
	 * @param string $path Directory path relative to ABSPATH or absolute within ABSPATH. Default `'.'`.
	 * @return array Aggregated statistics summary containing `path`, `files`, `directories`, `total_bytes`, `total_size`, and `by_ext`.
	 * @throws \InvalidArgumentException|\RuntimeException If the path does not exist or resolves outside ABSPATH, or if the specified path is not a directory.
	 */
	public function stats( string $path = '.' ): array {
		$safe_path = $this->resolve_safe_path( $path, true );

		if ( ! is_dir( $safe_path ) ) {
			throw new \RuntimeException( esc_html( "The specified path is not a directory: $path" ) );
		}

		$dir        = new \DirectoryIterator( $safe_path );
		$extensions = array();
		$total_size = 0;
		$file_count = 0;
		$dir_count  = 0;

		foreach ( $dir as $item ) {
			LanguageEngine::get()->tick();

			if ( $item->isDot() ) {
				continue;
			}

			if ( $item->isDir() ) {
				++$dir_count;
			} else {
				++$file_count;
				$size        = (int) $item->getSize();
				$total_size += $size;
				$ext         = strtolower( $item->getExtension() );
				$ext_key     = empty( $ext ) ? '(none)' : '.' . $ext;

				if ( ! isset( $extensions[ $ext_key ] ) ) {
					$extensions[ $ext_key ] = array(
						'extension' => $ext_key,
						'count'     => 0,
						'size'      => 0,
					);
				}

				++$extensions[ $ext_key ]['count'];
				$extensions[ $ext_key ]['size'] += $size;
			}
		}

		$ext_list = array_values( $extensions );
		usort(
			$ext_list,
			function ( $a, $b ) {
				return $b['size'] <=> $a['size'];
			}
		);

		// Format sizes for display.
		$table_data = array_map(
			function ( $row ) {
				return array(
					'extension' => $row['extension'],
					'files'     => $row['count'],
					'size'      => $this->format_bytes( $row['size'] ),
					'bytes'     => $row['size'],
				);
			},
			$ext_list
		);

		if ( empty( $ext_list ) ) {
			LanguageEngine::get()->add_message( 'warning', 'The specified directory contains no files to display statistics.' );
		} else {
			// 1. Table Visualization.
			LanguageEngine::get()->begin_visualization_group()->add_visualization(
				array(
					'type'  => 'table',
					'title' => 'Table: Directory disk distribution by extension',
					'data'  => $table_data,
				)
			);

			// 2. Vega-Lite Radial Chart Visualization.
			LanguageEngine::get()->add_visualization(
				array(
					'type'  => 'graph',
					'title' => 'Graph: Disk size distribution by extension',
					'data'  => array(
						'$schema'     => 'https://vega.github.io/schema/vega-lite/v6.json',
						'description' => 'Files extension size distribution graph',
						'title'       => 'Disk Size Distribution by Extension',
						'width'       => 'container',
						'height'      => 300,
						'padding'     => 20,
						'data'        => array(
							'values' => $ext_list,
						),
						'mark'        => array(
							'type'    => 'arc',
							'tooltip' => true,
						),
						'encoding'    => array(
							'theta' => array(
								'field' => 'size',
								'type'  => 'quantitative',
							),
							'color' => array(
								'field'  => 'extension',
								'type'   => 'nominal',
								'legend' => array(
									'title' => 'Extension',
								),
							),
						),
					),
				)
			);
		}

		return array(
			'path'        => $safe_path,
			'files'       => $file_count,
			'directories' => $dir_count,
			'total_bytes' => $total_size,
			'total_size'  => $this->format_bytes( $total_size ),
			'by_ext'      => $table_data,
		);
	}
}
