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

namespace ExpressionLab\Core;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * Plugin updater class.
 *
 * Handles update checks and package verification.
 *
 * @since 1.0.0
 * @internal
 *
 * @package ExpressionLab
 */
class Updater {
	use Singleton;

	/**
	 * Default remote update manifest URL.
	 *
	 * @var string
	 */
	const MANIFEST_URL = 'https://updates.expressionlab.com/update.json';

	/**
	 * Default transient key for caching the update manifest.
	 *
	 * @var string
	 */
	const TRANSIENT_KEY = 'expressionlab_update_manifest';

	/**
	 * Default plugin basename.
	 *
	 * @var string
	 */
	const PLUGIN_BASENAME = 'expressionlab/expressionlab.php';

	/**
	 * Default plugin slug.
	 *
	 * @var string
	 */
	const PLUGIN_SLUG = 'expressionlab';

	/**
	 * Default homepage URL.
	 *
	 * @var string
	 */
	const HOMEPAGE_URL = 'https://expressionlab.com';

	/**
	 * Initializes update hooks.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $force Whether to force initialization in test environments.
	 */
	public function init( bool $force = false ): void {
		if ( ! $force && defined( 'EXPRESSION_LAB_TEST_ENV' ) && EXPRESSION_LAB_TEST_ENV ) {
			return;
		}

		add_filter( 'site_transient_update_plugins', array( $this, 'check_for_updates' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_pre_download', array( $this, 'verify_package_integrity' ), 10, 4 );
	}

	/**
	 * Checks for plugin updates against the remote manifest.
	 *
	 * @since 1.0.0
	 *
	 * @param object|false $transient The update_plugins site transient.
	 * @return object|false Modified transient with update information if available.
	 */
	public function check_for_updates( $transient ) {
		if ( empty( $transient ) || ! is_object( $transient ) ) {
			return $transient;
		}

		$manifest = $this->get_manifest();
		if ( ! $manifest || empty( $manifest['version'] ) ) {
			return $transient;
		}

		$current_version = $this->get_plugin_version();
		$plugin_basename = $this->get_plugin_basename();
		$slug            = $this->get_plugin_slug();
		$homepage_url    = $this->get_homepage_url();

		if ( version_compare( $manifest['version'], $current_version, '>' ) ) {
			$item = (object) array(
				'id'           => $slug,
				'slug'         => $slug,
				'plugin'       => $plugin_basename,
				'new_version'  => $manifest['version'],
				'url'          => $homepage_url,
				'package'      => $manifest['download_url'] ?? '',
				'requires'     => $manifest['requires'] ?? '6.4',
				'requires_php' => $manifest['requires_php'] ?? '8.1',
				'tested'       => $manifest['tested'] ?? '6.9.4',
			);

			$transient->response[ $plugin_basename ] = $item;
			unset( $transient->no_update[ $plugin_basename ] );
		} else {
			$item = (object) array(
				'id'           => $slug,
				'slug'         => $slug,
				'plugin'       => $plugin_basename,
				'new_version'  => $current_version,
				'url'          => $homepage_url,
				'package'      => '',
				'requires'     => $manifest['requires'] ?? '6.4',
				'requires_php' => $manifest['requires_php'] ?? '8.1',
				'tested'       => $manifest['tested'] ?? '6.9.4',
			);

			$transient->no_update[ $plugin_basename ] = $item;
			unset( $transient->response[ $plugin_basename ] );
		}

		return $transient;
	}

	/**
	 * Provides plugin information for the WordPress modal dialog.
	 *
	 * @since 1.0.0
	 *
	 * @param false|object|array $result The result object or array.
	 * @param string             $action The type of information requested.
	 * @param object             $args   Plugin API arguments.
	 * @return false|object Plugin information object if matching slug.
	 */
	public function plugin_info( $result, string $action, $args ) {
		$slug = $this->get_plugin_slug();

		if ( 'plugin_information' !== $action || empty( $args->slug ) || $slug !== $args->slug ) {
			return $result;
		}

		$manifest = $this->get_manifest();
		if ( ! $manifest ) {
			return $result;
		}

		$sections = array(
			'description' => wp_kses_post( $manifest['sections']['description'] ?? '' ),
			'changelog'   => wp_kses_post( $manifest['sections']['changelog'] ?? '' ),
		);

		return (object) array(
			'name'          => $manifest['name'] ?? 'Expression Lab',
			'slug'          => $slug,
			'version'       => $manifest['version'] ?? $this->get_plugin_version(),
			'author'        => '<a href="https://andersonsalas.com">Anderson Salas</a>',
			'homepage'      => $this->get_homepage_url(),
			'requires'      => $manifest['requires'] ?? '6.4',
			'tested'        => $manifest['tested'] ?? '6.9.4',
			'requires_php'  => $manifest['requires_php'] ?? '8.1',
			'last_updated'  => $manifest['last_updated'] ?? '',
			'sections'      => $sections,
			'download_link' => $manifest['download_url'] ?? '',
		);
	}

	/**
	 * Verifies package integrity before installation.
	 *
	 * @since 1.0.0
	 *
	 * @param false|string|\WP_Error $reply      The pre-download response.
	 * @param string                 $package    The remote package URL.
	 * @param \WP_Upgrader           $upgrader   The upgrader instance.
	 * @param array                  $hook_extra Extra hook arguments.
	 * @return string|\WP_Error Path to downloaded file, or WP_Error on failure.
	 */
	public function verify_package_integrity( $reply, string $package, $upgrader, array $hook_extra ) {
		if ( empty( $hook_extra['plugin'] ) || $this->get_plugin_basename() !== $hook_extra['plugin'] ) {
			return $reply;
		}

		if ( 0 !== strpos( $package, 'https://' ) ) {
			return new \WP_Error(
				'expressionlab_insecure_package_url',
				__( 'Security error: Package download URL must use HTTPS. Update aborted.', 'expression-lab' )
			);
		}

		$download_file = download_url( $package, 300 );
		if ( is_wp_error( $download_file ) ) {
			return $download_file;
		}

		$manifest = $this->get_manifest();

		if ( empty( $manifest['sha256'] ) ) {
			wp_delete_file( $download_file );
			return new \WP_Error(
				'expressionlab_missing_checksum',
				__( 'Security error: Release manifest is missing SHA-256 checksum. Update aborted.', 'expression-lab' )
			);
		}

		$actual_sha256 = hash_file( 'sha256', $download_file );
		if ( ! hash_equals( strtolower( (string) $manifest['sha256'] ), strtolower( (string) $actual_sha256 ) ) ) {
			wp_delete_file( $download_file );
			return new \WP_Error(
				'expressionlab_checksum_mismatch',
				__( 'Security error: Downloaded package SHA-256 checksum does not match the release manifest. Update aborted.', 'expression-lab' )
			);
		}

		if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
			wp_delete_file( $download_file );
			return new \WP_Error(
				'expressionlab_sodium_unavailable',
				__( 'Security error: Libsodium extension is not available. Ed25519 signature verification cannot proceed. Update aborted.', 'expression-lab' )
			);
		}

		$pub_hex = $this->get_public_key();

		if ( empty( $pub_hex ) ) {
			wp_delete_file( $download_file );
			return new \WP_Error(
				'expressionlab_missing_public_key',
				__( 'Security error: Ed25519 public key is not configured. Update aborted.', 'expression-lab' )
			);
		}

		if ( empty( $manifest['signature'] ) ) {
			wp_delete_file( $download_file );
			return new \WP_Error(
				'expressionlab_missing_signature',
				__( 'Security error: Release manifest is missing Ed25519 signature. Update aborted.', 'expression-lab' )
			);
		}

		$sig_hex = (string) $manifest['signature'];

		if ( ! ctype_xdigit( $sig_hex ) || 128 !== strlen( $sig_hex ) || ! ctype_xdigit( $pub_hex ) || 64 !== strlen( $pub_hex ) ) {
			wp_delete_file( $download_file );
			return new \WP_Error(
				'expressionlab_invalid_signature_format',
				__( 'Security error: Malformed cryptographic signature or public key. Update aborted.', 'expression-lab' )
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local temporary file.
		$zip_content = file_get_contents( $download_file );
		$sig_bin     = sodium_hex2bin( $sig_hex );
		$pubkey_bin  = sodium_hex2bin( $pub_hex );

		if ( ! sodium_crypto_sign_verify_detached( $sig_bin, $zip_content, $pubkey_bin ) ) {
			wp_delete_file( $download_file );
			return new \WP_Error(
				'expressionlab_signature_mismatch',
				__( 'Critical security error: Cryptographic signature verification failed. The package may have been tampered with. Update aborted.', 'expression-lab' )
			);
		}

		return $download_file;
	}

	/**
	 * Retrieves the current plugin version or throws a LogicException.
	 *
	 * @since 1.0.0
	 * @throws \LogicException If the EXPRESSION_LAB_VERSION constant is not defined.
	 *
	 * @return string Current plugin version string.
	 */
	public function get_plugin_version(): string {
		if ( ! defined( 'EXPRESSION_LAB_VERSION' ) ) {
			throw new \LogicException( 'Expression Lab version constant (EXPRESSION_LAB_VERSION) must be defined.' );
		}

		return (string) EXPRESSION_LAB_VERSION;
	}

	/**
	 * Checks whether internal debug mode is active.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if debug mode is active.
	 */
	public function is_debug_mode(): bool {
		return defined( 'EXPRESSION_LAB_DEBUG_MODE' ) && true === constant( 'EXPRESSION_LAB_DEBUG_MODE' );
	}

	/**
	 * Retrieves the plugin basename.
	 *
	 * @since 1.0.0
	 *
	 * @return string The plugin basename.
	 */
	public function get_plugin_basename(): string {
		if ( defined( 'EXPRESSION_LAB_BASENAME' ) ) {
			return (string) constant( 'EXPRESSION_LAB_BASENAME' );
		}

		return self::PLUGIN_BASENAME;
	}

	/**
	 * Retrieves the plugin slug.
	 *
	 * @since 1.0.0
	 *
	 * @return string The plugin slug.
	 */
	public function get_plugin_slug(): string {
		if ( defined( 'EXPRESSION_LAB_SLUG' ) ) {
			return (string) constant( 'EXPRESSION_LAB_SLUG' );
		}

		return self::PLUGIN_SLUG;
	}

	/**
	 * Retrieves the plugin homepage URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string The plugin homepage URL.
	 */
	public function get_homepage_url(): string {
		if ( defined( 'EXPRESSION_LAB_HOMEPAGE_URL' ) ) {
			return (string) constant( 'EXPRESSION_LAB_HOMEPAGE_URL' );
		}

		return self::HOMEPAGE_URL;
	}

	/**
	 * Retrieves the transient key for caching the update manifest.
	 *
	 * @since 1.0.0
	 *
	 * @return string The transient key.
	 */
	public function get_transient_key(): string {
		if ( defined( 'EXPRESSION_LAB_UPDATE_TRANSIENT_KEY' ) ) {
			return (string) constant( 'EXPRESSION_LAB_UPDATE_TRANSIENT_KEY' );
		}

		return self::TRANSIENT_KEY;
	}

	/**
	 * Retrieves the configured Ed25519 public key.
	 *
	 * @since 1.0.0
	 *
	 * @return string The hex-encoded public key, or empty string if not configured.
	 */
	public function get_public_key(): string {
		if ( defined( 'EXPRESSION_LAB_PUBLIC_KEY' ) ) {
			return (string) constant( 'EXPRESSION_LAB_PUBLIC_KEY' );
		}

		return '';
	}

	/**
	 * Retrieves the update manifest URL.
	 *
	 * Uses EXPRESSION_LAB_CUSTOM_MANIFEST_URL when EXPRESSION_LAB_DEBUG_MODE
	 * is enabled, or falls back to EXPRESSION_LAB_MANIFEST_URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string The update manifest URL.
	 */
	public function get_manifest_url(): string {
		$is_debug = $this->is_debug_mode();

		if ( $is_debug && defined( 'EXPRESSION_LAB_CUSTOM_MANIFEST_URL' ) ) {
			$custom_url = (string) constant( 'EXPRESSION_LAB_CUSTOM_MANIFEST_URL' );
			if ( ! empty( $custom_url ) ) {
				return $custom_url;
			}
		}

		if ( defined( 'EXPRESSION_LAB_MANIFEST_URL' ) ) {
			return (string) constant( 'EXPRESSION_LAB_MANIFEST_URL' );
		}

		return self::MANIFEST_URL;
	}

	/**
	 * Checks if an update is available and returns the manifest information.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $force_refresh Whether to bypass cache.
	 * @return array|null Manifest data if a newer version is available, null otherwise.
	 */
	public function get_available_update( bool $force_refresh = false ): ?array {
		$manifest = $this->get_manifest( $force_refresh );
		if ( ! $manifest || empty( $manifest['version'] ) ) {
			return null;
		}

		$current_version = $this->get_plugin_version();
		if ( version_compare( $manifest['version'], $current_version, '>' ) ) {
			return $manifest;
		}

		return null;
	}

	/**
	 * Retrieves and caches the remote release manifest.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $force_refresh Whether to bypass the transient cache.
	 * @return array|null Manifest array or null on failure.
	 */
	public function get_manifest( bool $force_refresh = false ): ?array {
		$is_debug      = $this->is_debug_mode();
		$transient_key = $this->get_transient_key();

		if ( ! $force_refresh && ! $is_debug ) {
			$cached = get_site_transient( $transient_key );
			if ( is_array( $cached ) && ! empty( $cached['version'] ) ) {
				return $cached;
			}
		}

		$manifest_url = $this->get_manifest_url();
		$manifest     = null;

		// Support reading from a local file in debug mode.
		if ( $is_debug ) {
			$file_path = 0 === strpos( $manifest_url, 'file://' ) ? substr( $manifest_url, 7 ) : $manifest_url;
			if ( file_exists( $file_path ) && is_readable( $file_path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file read in debug mode.
				$body     = file_get_contents( $file_path );
				$manifest = json_decode( $body, true );
			}
		}

		if ( ! is_array( $manifest ) || empty( $manifest['version'] ) ) {
			$request_args = array(
				'timeout'    => 10,
				'user-agent' => 'ExpressionLab/' . $this->get_plugin_version() . '; ' . home_url(),
			);

			if ( $is_debug ) {
				$request_args['sslverify'] = false;
			}

			$response = wp_remote_get( $manifest_url, $request_args );

			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				$body     = wp_remote_retrieve_body( $response );
				$manifest = json_decode( $body, true );
			}
		}

		if ( ! is_array( $manifest ) || empty( $manifest['version'] ) ) {
			return null;
		}

		// Cache manifest for 12 hours in production, or 10 seconds in debug mode.
		$cache_ttl = $is_debug ? 10 : ( 12 * HOUR_IN_SECONDS );
		set_site_transient( $transient_key, $manifest, $cache_ttl );

		return $manifest;
	}
}
