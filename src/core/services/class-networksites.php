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

use ExpressionLab\Core\LanguageEngine;
use ExpressionLab\Core\Models\Site;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * Network Sites Service.
 *
 * Provides methods to discover, inspect, list, and instantiate WordPress multisite subsite models.
 *
 * Exclusively functional within WordPress Multisite environments.
 *
 * @package ExpressionLab
 */
final class NetworkSites {
	/**
	 * Database instance.
	 *
	 * @var Database
	 */
	private $database;

	/**
	 * Active contextual site model.
	 *
	 * Holds the `Site` model instance corresponding to the subsite context currently selected
	 * in the Expression Lab interface or the current blog ID in multisite environments.
	 * Evaluates to `null` in single-site installations.
	 *
	 * Example:
	 *
	 * ```elscript
	 * NetworkSites.current
	 * ```
	 *
	 * @var Site|null
	 * @see https://expressionlab.io/docs/api-reference/sites#networksitescurrent
	 */
	public $current;

	/**
	 * Constructor.
	 *
	 * @internal
	 *
	 * @param Database $database The database instance.
	 */
	public function __construct( Database $database ) {
		$this->database = $database;
		if ( is_multisite() ) {
			$current_site_id = LanguageEngine::get()->get_site_id() ?? get_current_blog_id();
			$this->current   = $this->get( $current_site_id );
		}
	}

	/**
	 * Fetches a WP_Site object based on the provided identifier.
	 *
	 * The identifier can be a site ID (int or numeric string), domain, domain/path, or full URL.
	 * Handles both subdomain and subdirectory multisite configurations,
	 * and automatically retries with/without the www prefix.
	 *
	 * @internal
	 *
	 * @param mixed $identifier The site identifier (int ID, numeric string, or string domain/path/URL).
	 * @return \WP_Site|null The site object or null if not found.
	 */
	private function get_by_identifier( $identifier ) {
		$site = null;

		if ( is_int( $identifier ) || ( is_string( $identifier ) && ctype_digit( $identifier ) ) ) {
			$site = get_site( (int) $identifier );
		} elseif ( is_string( $identifier ) ) {
			// Strip protocol if a full URL was provided.
			$identifier = preg_replace( '#^https?://#', '', trim( $identifier ) );
			// Remove trailing slash for consistent parsing.
			$identifier = rtrim( $identifier, '/' );

			// Split into domain and path components.
			$slash_pos = strpos( $identifier, '/' );
			if ( false !== $slash_pos ) {
				$domain = substr( $identifier, 0, $slash_pos );
				$path   = '/' . trim( substr( $identifier, $slash_pos + 1 ), '/' ) . '/';
			} else {
				$domain = $identifier;
				$path   = '/';
			}

			// Attempt lookup with the exact domain and path.
			$site = get_site_by_path( $domain, $path );

			// If not found, retry toggling the www prefix.
			if ( ! $site instanceof \WP_Site ) {
				$alt_domain = 0 === strpos( $domain, 'www.' )
					? substr( $domain, 4 )
					: 'www.' . $domain;
				$site       = get_site_by_path( $alt_domain, $path );
			}

			// Ensure we return a valid WP_Site or null.
			if ( ! $site instanceof \WP_Site ) {
				$site = null;
			}
		}

		return $site;
	}

	/**
	 * Validates that the current installation is a multisite before performing network site operations.
	 *
	 * @internal
	 *
	 * @throws \Exception If accessed in a non-multisite installation.
	 */
	private function validate_multisite() {
		if ( ! is_multisite() ) {
			throw new \Exception( esc_html( 'Network sites are only available in multisite installations.' ) );
		}
	}

	/**
	 * Retrieves a Site model instance from the network by ID, domain, path, or URL.
	 *
	 * Resolves the target subsite through the following resolution modes:
	 * * **Numeric ID**: Evaluates integer or digit string identifiers via `get_site((int) $identifier)`.
	 * * **Domain, Path, or URL**: Strips protocol schemes (`http://`, `https://`), parses domain and path components,
	 *    queries `get_site_by_path()`, and automatically retries with or without the `www.` prefix.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * NetworkSites.get(2)
	 * ```
	 *
	 * ```elscript
	 * NetworkSites.get('example.com/client-a/')
	 * ```
	 *
	 * ```elscript
	 * NetworkSites.get('shop.example.com')
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/sites#networksitesget
	 *
	 * @param int|string $identifier The numeric blog ID, domain, domain/path, or URL of the site to retrieve.
	 * @return Site|null A `Site` model instance if resolved, or `null` if not found.
	 * @throws \Exception If accessed in a non-multisite installation.
	 */
	public function get( $identifier ) {
		$this->validate_multisite();

		LanguageEngine::get()->tick();
		$site = $this->get_by_identifier( $identifier );

		if ( ! is_wp_error( $site ) && $site instanceof \WP_Site ) {
			return new Site( $site, $this->database );
		}

		return null;
	}

	/**
	 * Factory method to instantiate a new, unsaved Site model for the current network.
	 *
	 * Initializes an empty `Site` model pre-configured with the current network ID (`site_id = get_current_network_id()`).
	 * Fluent setter methods (`set_domain`, `set_path`, `set_public`, etc.) can be chained onto the returned
	 * instance prior to persisting via `save()`.
	 *
	 * Example:
	 *
	 * ```elscript
	 * NetworkSites.build()
	 *     .set_domain('portal.example.com')
	 *     .set_path('/')
	 *     .save()
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/sites#networksitesbuild
	 *
	 * @return Site A new, unsaved `Site` model instance.
	 * @throws \Exception If accessed in a non-multisite installation.
	 */
	public function build() {
		$this->validate_multisite();

		$site          = new Site( null, $this->database );
		$site->site_id = get_current_network_id();
		return $site;
	}

	/**
	 * Retrieves a paginated list of sites across the multisite network and registers a table visualization.
	 *
	 * Queries `get_sites()` applying `$limit` (bounded between 1 and 1000, default 100) and `$offset` (default 0).
	 * Formats matching sites into normalized dictionary records and automatically registers an interactive
	 * table visualization titled `'Network sites'` with the `LanguageEngine`.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * NetworkSites.list()
	 * ```
	 *
	 * ```elscript
	 * NetworkSites.list(20, 40)
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/sites#networksiteslist
	 *
	 * @param int|null $limit  Optional. Maximum number of sites to return (capped at 1000). Default `100`.
	 * @param int      $offset Optional. Number of sites to skip for pagination. Default `0`.
	 * @return array Normalized list of site dictionaries with core network attributes.
	 * @throws \Exception If accessed in a non-multisite installation.
	 */
	public function list( ?int $limit = 100, int $offset = 0 ) {
		$this->validate_multisite();

		$max_limit = 1000;
		$limit     = null === $limit ? 100 : min( max( 1, $limit ), $max_limit );
		$offset    = max( 0, $offset );
		LanguageEngine::get()->tick();

		$sites_objects = get_sites(
			array(
				'number' => $limit,
				'offset' => $offset,
			)
		);

		$sites = array();
		if ( is_array( $sites_objects ) ) {
			foreach ( $sites_objects as $site_obj ) {
				$sites[] = array(
					'blog_id'      => (int) $site_obj->blog_id,
					'site_id'      => (int) $site_obj->site_id,
					'domain'       => $site_obj->domain,
					'path'         => $site_obj->path,
					'registered'   => $site_obj->registered,
					'last_updated' => $site_obj->last_updated,
					'public'       => (int) $site_obj->public,
					'archived'     => (int) $site_obj->archived,
					'mature'       => (int) $site_obj->mature,
					'spam'         => (int) $site_obj->spam,
					'deleted'      => (int) $site_obj->deleted,
					'lang_id'      => (int) $site_obj->lang_id,
				);
			}
		}

		if ( ! empty( $sites ) ) {
			// Table visualization data.
			LanguageEngine::get()->begin_visualization_group()->add_visualization(
				array(
					'type'  => 'table',
					'title' => 'Network sites',
					'data'  => $sites,
				)
			);
		}

		return $sites;
	}
}
