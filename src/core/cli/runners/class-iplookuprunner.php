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

namespace ExpressionLab\Core\Cli\Runners;

use ExpressionLab\Core\Services\IPLookup;
use MaxMind\Db\Reader;
use splitbrain\PHPArchive\Tar;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * Service runner for GeoLite2 database management.
 *
 * @package ExpressionLab
 */
class IPLookupRunner {

	/**
	 * Initializes and returns the WordPress filesystem object.
	 *
	 * @return \WP_Filesystem_Base Filesystem instance.
	 */
	private static function get_filesystem(): \WP_Filesystem_Base {
		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		return $wp_filesystem;
	}

	/**
	 * Downloads, extracts, and installs the GeoLite2 database.
	 *
	 * @param string|null   $license_key License key or null to resolve from constant.
	 * @param bool          $force       Whether to force update if updated within 24h.
	 * @param callable|null $feedback    Optional feedback callback: fn( string $message ).
	 * @return array
	 * @throws \InvalidArgumentException If license key is missing.
	 * @throws \RuntimeException If download, extraction, or replacement fails.
	 */
	public function update( ?string $license_key = null, bool $force = false, ?callable $feedback = null ): array {
		if ( empty( $license_key ) && defined( 'EXPRESSION_LAB_MAXMIND_API_KEY' ) ) {
			$license_key = constant( 'EXPRESSION_LAB_MAXMIND_API_KEY' );
		}

		if ( empty( $license_key ) || ! is_string( $license_key ) ) {
			throw new \InvalidArgumentException( 'MaxMind license key is missing. Define EXPRESSION_LAB_MAXMIND_API_KEY in wp-config.php or use --license-key.' );
		}

		$target_path = IPLookup::get_database_path();

		if ( ! $force && file_exists( $target_path ) && ( time() - filemtime( $target_path ) < DAY_IN_SECONDS ) ) {
			return array(
				'status' => 'skipped',
				'path'   => $target_path,
				'reason' => 'recently_updated',
			);
		}

		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( null !== $feedback ) {
			$feedback( 'Downloading GeoLite2 Country database from MaxMind...' );
		}

		$url = sprintf(
			'https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-Country&license_key=%s&suffix=tar.gz',
			rawurlencode( $license_key )
		);

		$tmp_file = download_url( $url, 300 );

		if ( is_wp_error( $tmp_file ) ) {
			$error_code = $tmp_file->get_error_code();
			$error_msg  = $tmp_file->get_error_message();

			if ( 'http_401' === $error_code || false !== strpos( $error_msg, '401' ) || false !== strpos( strtolower( $error_msg ), 'unauthorized' ) ) {
				throw new \RuntimeException( 'Invalid MaxMind license key or unauthorized access (HTTP 401).' );
			}

			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI context: esc_html() garbles terminal output with HTML entities.
			throw new \RuntimeException( sanitize_text_field( sprintf( 'Failed to download GeoLite2 database: %s', $error_msg ) ) );
		}

		if ( null !== $feedback ) {
			$feedback( 'Extracting archive...' );
		}

		$tmp_extract_dir = wp_normalize_path( get_temp_dir() . 'expressionlab_geoip_' . uniqid() );
		if ( ! wp_mkdir_p( $tmp_extract_dir ) ) {
			wp_delete_file( $tmp_file );
			throw new \RuntimeException( 'Failed to create temporary extraction directory.' );
		}

		try {
			$tar = new Tar();
			$tar->open( $tmp_file );
			$tar->extract( $tmp_extract_dir );
		} catch ( \Exception $e ) {
			wp_delete_file( $tmp_file );
			self::delete_directory( $tmp_extract_dir );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI context: esc_html() garbles terminal output with HTML entities.
			throw new \RuntimeException( sanitize_text_field( sprintf( 'Failed to extract archive: %s', $e->getMessage() ) ) );
		}

		wp_delete_file( $tmp_file );

		$mmdb_file = null;
		$iterator  = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $tmp_extract_dir, \FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $item ) {
			if ( $item->isFile() && 'mmdb' === strtolower( $item->getExtension() ) ) {
				$mmdb_file = $item->getPathname();
				break;
			}
		}

		if ( null === $mmdb_file || ! file_exists( $mmdb_file ) ) {
			self::delete_directory( $tmp_extract_dir );
			throw new \RuntimeException( 'No .mmdb database file found inside the extracted MaxMind archive.' );
		}

		$target_dir = dirname( $target_path );
		if ( ! is_dir( $target_dir ) ) {
			wp_mkdir_p( $target_dir );
		}

		$index_file = wp_normalize_path( $target_dir . '/index.html' );
		if ( ! file_exists( $index_file ) ) {
			self::get_filesystem()->put_contents( $index_file, '' );
		}

		$tmp_target = wp_normalize_path( $target_path . '.tmp.' . uniqid() );

		if ( ! self::get_filesystem()->copy( $mmdb_file, $tmp_target, true, 0644 ) ) {
			self::delete_directory( $tmp_extract_dir );
			throw new \RuntimeException( 'Failed to copy extracted database to destination directory.' );
		}

		self::delete_directory( $tmp_extract_dir );

		if ( ! self::get_filesystem()->move( $tmp_target, $target_path, true ) ) {
			wp_delete_file( $tmp_target );
			throw new \RuntimeException( 'Failed to replace active database file atomically.' );
		}

		$size_formatted = size_format( (int) filesize( $target_path ) );
		$build_date     = 'Unknown';

		try {
			$reader   = new Reader( $target_path );
			$metadata = $reader->metadata();
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			if ( isset( $metadata->buildEpoch ) ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$build_date = gmdate( 'Y-m-d H:i:s \U\T\C', $metadata->buildEpoch );
			}
		} catch ( \Exception $e ) {
			unset( $e );
		}

		return array(
			'status'         => 'updated',
			'path'           => $target_path,
			'size'           => (int) filesize( $target_path ),
			'size_formatted' => $size_formatted,
			'build_date'     => $build_date,
		);
	}

	/**
	 * Deletes the local database file if it exists.
	 *
	 * @return bool True if deleted, false if file did not exist.
	 * @throws \RuntimeException If deletion fails.
	 */
	public function delete(): bool {
		$target_path = IPLookup::get_database_path();

		if ( ! file_exists( $target_path ) ) {
			return false;
		}

		if ( ! self::get_filesystem()->delete( $target_path ) ) {
			throw new \RuntimeException( 'Failed to delete database file. Check filesystem permissions.' );
		}

		return true;
	}

	/**
	 * Retrieves the status and metadata of the local database.
	 *
	 * @return array
	 */
	public function get_status(): array {
		$path        = IPLookup::get_database_path();
		$custom_path = defined( 'EXPRESSION_LAB_MAXMIND_PATH' ) ? constant( 'EXPRESSION_LAB_MAXMIND_PATH' ) : null;
		$path_type   = ( ! empty( $custom_path ) && is_string( $custom_path ) ) ? 'Custom (EXPRESSION_LAB_MAXMIND_PATH)' : 'Default (deterministic hash)';

		$has_api_key = defined( 'EXPRESSION_LAB_MAXMIND_API_KEY' ) && ! empty( constant( 'EXPRESSION_LAB_MAXMIND_API_KEY' ) );
		$installed   = file_exists( $path ) && is_readable( $path );

		$status = array(
			'installed'          => $installed,
			'path'               => $path,
			'path_type'          => $path_type,
			'license_configured' => $has_api_key,
			'size'               => null,
			'size_formatted'     => null,
			'modified'           => null,
			'database_type'      => null,
			'ip_version'         => null,
			'build_epoch'        => null,
			'build_date'         => null,
			'age_days'           => null,
			'is_stale'           => false,
			'reader_error'       => null,
		);

		if ( $installed ) {
			$filesize   = (int) filesize( $path );
			$file_mtime = (int) filemtime( $path );

			$status['size']           = $filesize;
			$status['size_formatted'] = size_format( $filesize );
			$status['modified']       = gmdate( 'Y-m-d H:i:s \U\T\C', $file_mtime );

			try {
				$reader   = new Reader( $path );
				$metadata = $reader->metadata();

				// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				if ( isset( $metadata->databaseType ) ) {
					$status['database_type'] = (string) $metadata->databaseType;
				}
				if ( isset( $metadata->ipVersion ) ) {
					$status['ip_version'] = (int) $metadata->ipVersion;
				}
				if ( isset( $metadata->buildEpoch ) ) {
					$status['build_epoch'] = (int) $metadata->buildEpoch;
					$status['build_date']  = gmdate( 'Y-m-d H:i:s \U\T\C', $metadata->buildEpoch );
					$age_days              = (int) floor( ( time() - $metadata->buildEpoch ) / DAY_IN_SECONDS );
					$status['age_days']    = $age_days;
					$status['is_stale']    = $age_days > 30;
				}
				// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			} catch ( \Exception $e ) {
				$status['reader_error'] = $e->getMessage();
			}
		}

		return $status;
	}

	/**
	 * Recursively deletes a directory and its contents.
	 *
	 * @param string $dir Directory path.
	 * @return void
	 */
	private static function delete_directory( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		self::get_filesystem()->delete( $dir, true );
	}
}
