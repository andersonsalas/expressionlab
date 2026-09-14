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

use WP_User;
use ExpressionLab\Core\LanguageEngine;
use ExpressionLab\Core\Services\Database;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * User model class.
 *
 * Wraps a WordPress WP_User object with capability manipulation,
 * account attribute setters, persistence, and deletion methods.
 *
 * @since 1.0.0
 * @package ExpressionLab
 */
final class User extends Model {
	/**
	 * WordPress user instance.
	 *
	 * @since 1.0.0
	 * @internal
	 * @var WP_User|null
	 */
	private $user;

	/**
	 * Database service instance.
	 *
	 * @since 1.0.0
	 * @internal
	 * @var Database
	 */
	private $database;

	/**
	 * Role to assign on save.
	 *
	 * @since 1.0.0
	 * @internal
	 * @var string|null
	 */
	private $role;

	/**
	 * Whether the password has been explicitly changed.
	 *
	 * @since 1.0.0
	 * @internal
	 * @var bool
	 */
	private $pass_changed = false;

	/**
	 * Scoped user metadata manager.
	 *
	 * @since 1.0.0
	 * @var UserMeta
	 */
	public $meta;

	/**
	 * User data object mapped from the WP_User instance.
	 *
	 * @since 1.0.0
	 * @var object|null
	 */
	public $data;

	/**
	 * User ID.
	 *
	 * @since 1.0.0
	 * @var int|null
	 */
	public $ID;

	/**
	 * User capabilities mapped from the WP_User instance.
	 *
	 * @since 1.0.0
	 * @var array|null
	 */
	public $caps;

	/**
	 * User capability key mapped from the WP_User instance.
	 *
	 * @since 1.0.0
	 * @var string|null
	 */
	public $cap_key;

	/**
	 * User roles mapped from the WP_User instance.
	 *
	 * @since 1.0.0
	 * @var array|null
	 */
	public $roles;

	/**
	 * All user capabilities, including roles and custom capabilities.
	 *
	 * @since 1.0.0
	 * @var array|null
	 */
	public $allcaps;

	/**
	 * User filter mapped from the WP_User instance.
	 *
	 * @since 1.0.0
	 * @var mixed
	 */
	public $filter;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param WP_User|null $user     The WP_User object to initialize the model with.
	 * @param Database     $database The database instance.
	 */
	public function __construct( ?WP_User $user, Database $database ) {
		$this->database = $database;

		if ( null !== $user ) {
			$this->meta          = new UserMeta( $user, $database );
			$this->user          = $user;
			$data                = $user->to_array();
			$data['ID']          = (int) $user->ID;
			$data['user_status'] = (int) $user->user_status;
			$data['spam']        = (int) $user->spam;
			$data['deleted']     = (int) $user->deleted;
			$this->data          = (object) $data;
			$this->caps          = $user->caps;
			$this->cap_key       = $user->cap_key;
			$this->roles         = $user->roles;
			$this->allcaps       = $user->allcaps;
			$this->filter        = $user->filter;
			$this->ID            = $user->ID;
		} else {
			$this->data = new \stdClass();
		}
	}

	/**
	 * Checks if the user has a specific capability.
	 *
	 * @since 1.0.0
	 *
	 * @param string $capability The capability to check for (e.g., 'edit_posts').
	 * @param array  $args       Optional arguments to pass to the capability check.
	 * @return bool True if the user has the specified capability, false otherwise.
	 */
	public function can( string $capability, array $args = array() ) {
		if ( null === $this->user ) {
			return false;
		}

		$engine_site_id = LanguageEngine::get()->get_site_id();
		$switched       = false;

		if ( null !== $engine_site_id && is_multisite() && get_current_blog_id() !== (int) $engine_site_id ) {
			switch_to_blog( (int) $engine_site_id );
			$switched = true;
		}

		try {
			return $this->user->has_cap( $capability, ...$args );
		} finally {
			if ( $switched ) {
				restore_current_blog();
			}
		}
	}

	/**
	 * Adds a capability to the user.
	 *
	 * @since 1.0.0
	 *
	 * @param string $capability The capability name to add (e.g., 'edit_custom_posts').
	 * @param bool   $grant      Whether to grant the capability. Default true.
	 * @return User $this The current instance for method chaining.
	 * @throws \Exception If write protection is enabled.
	 */
	public function add_cap( string $capability, bool $grant = true ) {
		if ( defined( 'EXPRESSION_LAB_DATABASE_READONLY' ) && EXPRESSION_LAB_DATABASE_READONLY ) {
			throw new \Exception( esc_html( 'Operation denied. Write protection is enabled. Set `EXPRESSION_LAB_DATABASE_READONLY` to `false` in `wp-config.php`.' ) );
		}

		if ( null === $this->user || null === $this->ID ) {
			LanguageEngine::get()->add_message( 'error', 'Cannot add capability to an unsaved or uninitialized user.' );
			return $this;
		}

		$engine_site_id = LanguageEngine::get()->get_site_id();
		$switched       = false;

		if ( null !== $engine_site_id && is_multisite() && get_current_blog_id() !== (int) $engine_site_id ) {
			switch_to_blog( (int) $engine_site_id );
			$switched = true;
		}

		try {
			if ( method_exists( $this->user, 'for_site' ) && null !== $engine_site_id && is_multisite() ) {
				$this->user->for_site( (int) $engine_site_id );
			}
			$this->user->add_cap( $capability, $grant );
			$this->caps    = $this->user->caps;
			$this->allcaps = $this->user->allcaps;
			$this->roles   = $this->user->roles;
			$this->cap_key = $this->user->cap_key;
		} finally {
			if ( $switched ) {
				restore_current_blog();
			}
		}

		return $this;
	}

	/**
	 * Removes a capability from the user.
	 *
	 * @since 1.0.0
	 *
	 * @param string $capability The capability name to remove.
	 * @return User $this The current instance for method chaining.
	 * @throws \Exception If write protection is enabled.
	 */
	public function remove_cap( string $capability ) {
		if ( defined( 'EXPRESSION_LAB_DATABASE_READONLY' ) && EXPRESSION_LAB_DATABASE_READONLY ) {
			throw new \Exception( esc_html( 'Operation denied. Write protection is enabled. Set `EXPRESSION_LAB_DATABASE_READONLY` to `false` in `wp-config.php`.' ) );
		}

		if ( null === $this->user || null === $this->ID ) {
			LanguageEngine::get()->add_message( 'error', 'Cannot remove capability from an unsaved or uninitialized user.' );
			return $this;
		}

		$engine_site_id = LanguageEngine::get()->get_site_id();
		$switched       = false;

		if ( null !== $engine_site_id && is_multisite() && get_current_blog_id() !== (int) $engine_site_id ) {
			switch_to_blog( (int) $engine_site_id );
			$switched = true;
		}

		try {
			if ( method_exists( $this->user, 'for_site' ) && null !== $engine_site_id && is_multisite() ) {
				$this->user->for_site( (int) $engine_site_id );
			}
			$this->user->remove_cap( $capability );
			$this->caps    = $this->user->caps;
			$this->allcaps = $this->user->allcaps;
			$this->roles   = $this->user->roles;
			$this->cap_key = $this->user->cap_key;
		} finally {
			if ( $switched ) {
				restore_current_blog();
			}
		}

		return $this;
	}

	/**
	 * Sets the user login username.
	 *
	 * @since 1.0.0
	 *
	 * @param string $user_login The user login to set.
	 * @return User $this The current instance for method chaining.
	 */
	public function set_user_login( string $user_login ) {
		if ( null !== $this->ID ) {
			LanguageEngine::get()->add_message( 'warning', 'WordPress does not allow modifying the login username on existing users. The user_login field cannot be changed.' );
			return $this;
		}

		$this->data->user_login = $user_login;
		return $this;
	}

	/**
	 * Sets the user password.
	 *
	 * @since 1.0.0
	 *
	 * @param string $user_pass The password to set.
	 * @return User $this The current instance for method chaining.
	 */
	public function set_user_pass( string $user_pass ) {
		$this->data->user_pass = $user_pass;
		$this->pass_changed    = true;
		return $this;
	}

	/**
	 * Sets the user nicename.
	 *
	 * @since 1.0.0
	 *
	 * @param string $user_nicename The nicename to set.
	 * @return User $this The current instance for method chaining.
	 */
	public function set_user_nicename( string $user_nicename ) {
		$this->data->user_nicename = $user_nicename;
		return $this;
	}

	/**
	 * Sets the user email.
	 *
	 * @since 1.0.0
	 *
	 * @param string $user_email The email to set.
	 * @return User $this The current instance for method chaining.
	 */
	public function set_user_email( string $user_email ) {
		$this->data->user_email = $user_email;
		return $this;
	}

	/**
	 * Sets the user URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $user_url The URL to set.
	 * @return User $this The current instance for method chaining.
	 */
	public function set_user_url( string $user_url ) {
		$this->data->user_url = $user_url;
		return $this;
	}

	/**
	 * Sets the display name.
	 *
	 * @since 1.0.0
	 *
	 * @param string $display_name The display name to set.
	 * @return User $this The current instance for method chaining.
	 */
	public function set_display_name( string $display_name ) {
		$this->data->display_name = $display_name;
		return $this;
	}

	/**
	 * Sets the role to assign on save.
	 *
	 * @since 1.0.0
	 *
	 * @param string $role The role to set.
	 * @return User $this The current instance for method chaining.
	 */
	public function set_role( string $role ) {
		$this->role = $role;
		return $this;
	}

	/**
	 * Saves the user to the database.
	 *
	 * @since 1.0.0
	 *
	 * @return User|false The current instance on success, or false on failure.
	 * @throws \Exception If write protection is enabled.
	 */
	public function save() {
		if ( defined( 'EXPRESSION_LAB_DATABASE_READONLY' ) && EXPRESSION_LAB_DATABASE_READONLY ) {
			throw new \Exception( esc_html( 'Operation denied. Write protection is enabled. Set `EXPRESSION_LAB_DATABASE_READONLY` to `false` in `wp-config.php`.' ) );
		}

		if ( null === $this->ID ) {
			$userdata = array(
				'user_login'    => $this->data->user_login ?? '',
				'user_pass'     => $this->data->user_pass ?? wp_generate_password(),
				'user_nicename' => $this->data->user_nicename ?? '',
				'user_email'    => $this->data->user_email ?? '',
				'user_url'      => $this->data->user_url ?? '',
				'display_name'  => $this->data->display_name ?? '',
			);

			if ( null !== $this->role ) {
				$userdata['role'] = $this->role;
			}

			$user_id = wp_insert_user( $userdata );

			if ( is_wp_error( $user_id ) ) {
				LanguageEngine::get()->add_message( 'error', $user_id->get_error_message() );
				return false;
			}

			return new User( get_userdata( $user_id ), $this->database );
		} else {
			$userdata = array(
				'ID'            => $this->ID,
				'user_nicename' => $this->data->user_nicename,
				'user_email'    => $this->data->user_email,
				'user_url'      => $this->data->user_url,
				'display_name'  => $this->data->display_name,
			);

			if ( $this->pass_changed ) {
				$userdata['user_pass'] = $this->data->user_pass;
			}

			if ( null !== $this->role ) {
				$userdata['role'] = $this->role;
			}

			$user_id = wp_update_user( $userdata );

			if ( is_wp_error( $user_id ) ) {
				LanguageEngine::get()->add_message( 'error', $user_id->get_error_message() );
				return false;
			}

			return $this;
		}
	}

	/**
	 * Deletes the user from the database.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $reassign The ID of the user to reassign posts to, or null to not reassign. (Single site only).
	 * @return bool True on successful deletion, false on failure.
	 * @throws \Exception If write protection is enabled.
	 */
	public function delete( ?int $reassign = null ) {
		if ( defined( 'EXPRESSION_LAB_DATABASE_READONLY' ) && EXPRESSION_LAB_DATABASE_READONLY ) {
			throw new \Exception( esc_html( 'Operation denied. Write protection is enabled. Set `EXPRESSION_LAB_DATABASE_READONLY` to `false` in `wp-config.php`.' ) );
		}

		if ( null === $this->ID ) {
			return false;
		}

		if ( defined( 'EXPRESSION_LAB_ADMIN_USER_ID' ) && (int) constant( 'EXPRESSION_LAB_ADMIN_USER_ID' ) === (int) $this->ID ) {
			LanguageEngine::get()->add_message( 'error', 'Operation denied. Cannot delete the designated Expression Lab administrator user (EXPRESSION_LAB_ADMIN_USER_ID).' );
			return false;
		}

		$current_user_id = get_current_user_id();
		if ( 0 !== $current_user_id && $current_user_id === (int) $this->ID ) {
			LanguageEngine::get()->add_message( 'error', 'Operation denied. Cannot delete the currently authenticated WordPress user.' );
			return false;
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';

		if ( ! is_multisite() ) {
			$result = wp_delete_user( $this->ID, $reassign );
		} else {
			$result = wpmu_delete_user( $this->ID );
		}

		return (bool) $result;
	}

	/**
	 * Retrieves an associative array representation of the user.
	 *
	 * @since 1.0.0
	 *
	 * @return array Associative array of user attributes.
	 */
	public function get_value() {
		return array(
			'ID'      => $this->ID,
			'data'    => $this->data,
			'caps'    => $this->caps,
			'cap_key' => $this->cap_key,
			'roles'   => $this->roles,
			'allcaps' => $this->allcaps,
			'filter'  => $this->filter,
		);
	}
}
