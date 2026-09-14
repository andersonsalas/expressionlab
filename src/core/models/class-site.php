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

use ExpressionLab\Core\LanguageEngine;
use ExpressionLab\Core\Services\Database;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * Site model class.
 *
 * Represents a WordPress site in a multisite network, providing methods
 * to manage site properties, options, and associated users.
 *
 * @since 1.0.0
 * @package ExpressionLab
 */
final class Site extends Model {
	/**
	 * WordPress site instance.
	 *
	 * @since 1.0.0
	 * @internal
	 * @var \WP_Site|null
	 */
	private $site;

	/**
	 * Database service instance.
	 *
	 * @since 1.0.0
	 * @internal
	 * @var Database
	 */
	private $database;

	/**
	 * SiteOptions instance scoped to this site.
	 *
	 * @since 1.0.0
	 * @var SiteOptions
	 */
	public $options;

	/**
	 * Blog ID mapped from the WP_Site instance.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $blog_id;

	/**
	 * Network site ID mapped from the WP_Site instance.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $site_id;

	/**
	 * Domain mapped from the WP_Site instance.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $domain;

	/**
	 * Path mapped from the WP_Site instance.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $path;

	/**
	 * Registered date mapped from the WP_Site instance.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $registered;

	/**
	 * Last updated date mapped from the WP_Site instance.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $last_updated;

	/**
	 * Public status mapped from the WP_Site instance.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $public;

	/**
	 * Archived status mapped from the WP_Site instance.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $archived;

	/**
	 * Mature status mapped from the WP_Site instance.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $mature;

	/**
	 * Spam status mapped from the WP_Site instance.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $spam;

	/**
	 * Deleted status mapped from the WP_Site instance.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $deleted;

	/**
	 * Language ID mapped from the WP_Site instance.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $lang_id;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param \WP_Site|null $site     The WP_Site object to initialize the model with.
	 * @param Database      $database The database instance.
	 */
	public function __construct( ?\WP_Site $site, Database $database ) {
		$this->database = $database;
		$this->options  = new SiteOptions( $site );

		if ( null !== $site ) {
			$this->site         = $site;
			$this->blog_id      = (int) $site->blog_id;
			$this->site_id      = (int) $site->site_id;
			$this->domain       = $site->domain;
			$this->path         = $site->path;
			$this->registered   = $site->registered;
			$this->last_updated = $site->last_updated;
			$this->public       = (int) $site->public;
			$this->archived     = (int) $site->archived;
			$this->mature       = (int) $site->mature;
			$this->spam         = (int) $site->spam;
			$this->deleted      = (int) $site->deleted;
			$this->lang_id      = (int) $site->lang_id;
		}
	}

	/**
	 * Sets the domain for the site.
	 *
	 * @since 1.0.0
	 *
	 * @param string $domain The domain to set.
	 * @return Site $this The current instance for method chaining.
	 */
	public function set_domain( string $domain ) {
		$domain       = preg_replace( '#^https?://#', '', trim( $domain ) );
		$this->domain = rtrim( $domain, '/' );
		return $this;
	}

	/**
	 * Sets the path for the site.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path The path to set.
	 * @return Site $this The current instance for method chaining.
	 */
	public function set_path( string $path ) {
		$path       = '/' . trim( $path, '/' ) . '/';
		$this->path = ( '/' === $path || '//' === $path ) ? '/' : $path;
		return $this;
	}

	/**
	 * Sets the public status for the site.
	 *
	 * @since 1.0.0
	 *
	 * @param int $public The public status to set (1 for public, 0 for private).
	 * @return Site $this The current instance for method chaining.
	 */
	public function set_public( int $public ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.publicFound
		$this->public = $public;
		return $this;
	}

	/**
	 * Sets the archived status for the site.
	 *
	 * @since 1.0.0
	 *
	 * @param int $archived The archived status to set (1 for archived, 0 for not archived).
	 * @return Site $this The current instance for method chaining.
	 */
	public function set_archived( int $archived ) {
		$this->archived = $archived;
		return $this;
	}

	/**
	 * Sets the mature status for the site.
	 *
	 * @since 1.0.0
	 *
	 * @param int $mature The mature status to set (1 for mature, 0 for not mature).
	 * @return Site $this The current instance for method chaining.
	 */
	public function set_mature( int $mature ) {
		$this->mature = $mature;
		return $this;
	}

	/**
	 * Sets the spam status for the site.
	 *
	 * @since 1.0.0
	 *
	 * @param int $spam The spam status to set (1 for spam, 0 for not spam).
	 * @return Site $this The current instance for method chaining.
	 */
	public function set_spam( int $spam ) {
		$this->spam = $spam;
		return $this;
	}

	/**
	 * Sets the deleted status for the site.
	 *
	 * @since 1.0.0
	 *
	 * @param int $deleted The deleted status to set (1 for deleted, 0 for not deleted).
	 * @return Site $this The current instance for method chaining.
	 */
	public function set_deleted( int $deleted ) {
		$this->deleted = $deleted;
		return $this;
	}

	/**
	 * Sets the language ID for the site.
	 *
	 * @since 1.0.0
	 *
	 * @param int $lang_id The language ID to set.
	 * @return Site $this The current instance for method chaining.
	 */
	public function set_lang_id( int $lang_id ) {
		$this->lang_id = $lang_id;
		return $this;
	}

	/**
	 * Saves the site to the database.
	 *
	 * @since 1.0.0
	 *
	 * @return Site|false The current instance on success, or false on failure.
	 * @throws \Exception If write protection is enabled.
	 */
	public function save() {
		if ( defined( 'EXPRESSION_LAB_DATABASE_READONLY' ) && EXPRESSION_LAB_DATABASE_READONLY ) {
			throw new \Exception( esc_html( 'Operation denied. Write protection is enabled. Set `EXPRESSION_LAB_DATABASE_READONLY` to `false` in `wp-config.php`.' ) );
		}

		if ( null === $this->blog_id ) {
			$site_id = wp_insert_site(
				array(
					'domain'       => $this->domain,
					'path'         => $this->path,
					'network_id'   => $this->site_id,
					'registered'   => current_time( 'Y-m-d H:i:s' ),
					'last_updated' => current_time( 'Y-m-d H:i:s' ),
					'public'       => $this->public ?? 1,
					'archived'     => $this->archived ?? 0,
					'mature'       => $this->mature ?? 0,
					'spam'         => $this->spam ?? 0,
					'deleted'      => $this->deleted ?? 0,
					'lang_id'      => $this->lang_id ?? 0,
				)
			);

			if ( is_wp_error( $site_id ) ) {
				LanguageEngine::get()->add_message( 'error', $site_id->get_error_message() );
				return false;
			}

			$fresh_site = get_site( $site_id );
			if ( $fresh_site instanceof \WP_Site ) {
				$this->site         = $fresh_site;
				$this->blog_id      = (int) $fresh_site->blog_id;
				$this->site_id      = (int) $fresh_site->site_id;
				$this->domain       = $fresh_site->domain;
				$this->path         = $fresh_site->path;
				$this->registered   = $fresh_site->registered;
				$this->last_updated = $fresh_site->last_updated;
				$this->public       = (int) $fresh_site->public;
				$this->archived     = (int) $fresh_site->archived;
				$this->mature       = (int) $fresh_site->mature;
				$this->spam         = (int) $fresh_site->spam;
				$this->deleted      = (int) $fresh_site->deleted;
				$this->lang_id      = (int) $fresh_site->lang_id;
				$this->options      = new SiteOptions( $fresh_site );
			}

			return $this;
		} else {
			$site_id = wp_update_site(
				$this->blog_id,
				array(
					'domain'       => $this->domain,
					'path'         => $this->path,
					'network_id'   => $this->site_id,
					'registered'   => $this->registered,
					'last_updated' => current_time( 'Y-m-d H:i:s' ),
					'public'       => $this->public,
					'archived'     => $this->archived,
					'mature'       => $this->mature,
					'spam'         => $this->spam,
					'deleted'      => $this->deleted,
					'lang_id'      => $this->lang_id,
				)
			);

			if ( is_wp_error( $site_id ) ) {
				LanguageEngine::get()->add_message( 'error', $site_id->get_error_message() );
				return false;
			}

			return $this;
		}
	}

	/**
	 * Deletes the site from the database.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True on successful deletion, false on failure.
	 * @throws \Exception If write protection is enabled.
	 */
	public function delete() {
		if ( defined( 'EXPRESSION_LAB_DATABASE_READONLY' ) && EXPRESSION_LAB_DATABASE_READONLY ) {
			throw new \Exception( esc_html( 'Operation denied. Write protection is enabled. Set `EXPRESSION_LAB_DATABASE_READONLY` to `false` in `wp-config.php`.' ) );
		}

		if ( null === $this->blog_id ) {
			return false;
		}

		$result = wp_delete_site( $this->blog_id );

		if ( is_wp_error( $result ) ) {
			LanguageEngine::get()->add_message( 'error', $result->get_error_message() );
			return false;
		}

		return true;
	}

	/**
	 * Lists users associated with the site.
	 *
	 * @since 1.0.0
	 *
	 * @return User[] An array of User model instances associated with the site.
	 */
	public function list_users() {
		if ( null === $this->blog_id ) {
			return array();
		}

		$users = get_users(
			array(
				'blog_id' => $this->blog_id,
			)
		);

		foreach ( $users as &$user ) {
			$user = new User( $user, $this->database );
		}

		return $users;
	}

	/**
	 * Adds a user to the site with the specified role.
	 *
	 * @since 1.0.0
	 *
	 * @param User   $user The user to add to the site.
	 * @param string $role The role to assign to the user on this site.
	 * @return bool True on success, false on failure.
	 * @throws \Exception If write protection is enabled.
	 */
	public function add_user( User $user, string $role = 'subscriber' ) {
		if ( defined( 'EXPRESSION_LAB_DATABASE_READONLY' ) && EXPRESSION_LAB_DATABASE_READONLY ) {
			throw new \Exception( esc_html( 'Operation denied. Write protection is enabled. Set `EXPRESSION_LAB_DATABASE_READONLY` to `false` in `wp-config.php`.' ) );
		}

		if ( null === $this->blog_id || null === $user->ID ) {
			return false;
		}

		$result = add_user_to_blog( $this->blog_id, $user->ID, $role );

		if ( is_wp_error( $result ) ) {
			LanguageEngine::get()->add_message( 'error', $result->get_error_message() );
			return false;
		}

		return true;
	}

	/**
	 * Removes a user from the site.
	 *
	 * @since 1.0.0
	 *
	 * @param User $user The user to remove from the site.
	 * @return bool True on success, false on failure.
	 * @throws \Exception If write protection is enabled.
	 */
	public function remove_user( User $user ) {
		if ( defined( 'EXPRESSION_LAB_DATABASE_READONLY' ) && EXPRESSION_LAB_DATABASE_READONLY ) {
			throw new \Exception( esc_html( 'Operation denied. Write protection is enabled. Set `EXPRESSION_LAB_DATABASE_READONLY` to `false` in `wp-config.php`.' ) );
		}

		if ( null === $this->blog_id || null === $user->ID ) {
			return false;
		}

		$result = remove_user_from_blog( $user->ID, $this->blog_id );

		if ( is_wp_error( $result ) ) {
			LanguageEngine::get()->add_message( 'error', $result->get_error_message() );
			return false;
		}

		return true;
	}

	/**
	 * Retrieves an associative array representation of the site.
	 *
	 * @since 1.0.0
	 *
	 * @return array Associative array of site attributes.
	 */
	public function get_value() {
		return array(
			'blog_id'      => $this->blog_id,
			'site_id'      => $this->site_id,
			'domain'       => $this->domain,
			'path'         => $this->path,
			'registered'   => $this->registered,
			'last_updated' => $this->last_updated,
			'public'       => $this->public,
			'archived'     => $this->archived,
			'mature'       => $this->mature,
			'spam'         => $this->spam,
			'deleted'      => $this->deleted,
			'lang_id'      => $this->lang_id,
		);
	}
}
