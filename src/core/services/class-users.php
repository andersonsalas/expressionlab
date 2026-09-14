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

use WP_User;
use ExpressionLab\Core\LanguageEngine;
use ExpressionLab\Core\QueryBuilder;
use ExpressionLab\Core\Models\User;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * Users Service.
 *
 * Provides methods to search, inspect, and manage WordPress user accounts, roles, capabilities,
 * and user metadata across single sites and multisite networks.
 *
 * @package ExpressionLab
 */
final class Users {
	use QueryBuilder;

	/**
	 * Active contextual user.
	 *
	 * Holds the `User` model instance corresponding to the user context currently selected
	 * in the Expression Lab interface.
	 *
	 * Example:
	 *
	 * ```elscript
	 * Users.current
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/users#userscurrent
	 * @var User|null
	 */
	public $current;

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
		$user_id        = LanguageEngine::get()->get_user_id();
		$this->current  = null !== $user_id ? $this->get( $user_id ) : null;
	}

	/**
	 * Returns the table name for the query builder.
	 *
	 * @internal
	 *
	 * @return string
	 */
	protected function get_query_builder_table(): string {
		return 'users';
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
	 * Fetches a WP_User object based on the provided identifier.
	 *
	 * @internal
	 *
	 * @param int|string $identifier The user ID (`int`), email (`string`), or username (`string`) to search for.
	 * @return WP_User|null The WP_User object if found, or `null` if no user matches the identifier.
	 */
	private function get_by_identifier( $identifier ) {
		$user = null;

		if ( is_int( $identifier ) ) {
			// Search by user ID.
			$user = get_userdata( absint( $identifier ) );
		} elseif ( is_string( $identifier ) && is_email( $identifier ) ) {
			// Search by email.
			$user = get_user_by( 'email', $identifier );
		} elseif ( is_string( $identifier ) ) {
			// Search by username.
			$user = get_user_by( 'login', $identifier );
		} else {
			LanguageEngine::get()->add_message( 'error', 'Invalid user identifier' );
		}

		return $user;
	}

	/**
	 * Retrieves a single User model instance by identifier, or executes the query builder if no identifier is provided.
	 *
	 * When an identifier is supplied, resolves the user through the following resolution modes:
	 * * **Numeric ID**: Fetches via `get_userdata(absint($identifier))`. Returns a `User` model instance, or `null` if not found.
	 * * **Email Address**: When matching `is_email()`, queries via `get_user_by('email', $identifier)`. Returns a `User` model instance, or `null` if not found.
	 * * **Username / Login**: Queries via `get_user_by('login', $identifier)`. Returns a `User` model instance, or `null` if not found.
	 *
	 * When called without arguments (`null`), executes all accumulated **Query Builder** conditions,
	 * mirrors matching rows from `wp_users` into an in-memory SQLite table, flushes the buffer, and returns
	 * an array of row objects.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * Users.get(2)
	 * ```
	 *
	 * ```elscript
	 * Users.get('admin@example.com')
	 * ```
	 *
	 * ```elscript
	 * Users.get('editor_user')
	 * ```
	 *
	 * ```elscript
	 * Users.where('user_status', 0)
	 *     .order_by('ID', 'DESC')
	 *     .limit(10)
	 *     .get()
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/users#usersget
	 *
	 * @param int|string|null $identifier Optional. Numeric user ID, email address, username slug, or `null` to execute query builder. Default `null`.
	 * @return User|array|null A `User` model instance if resolved, an array of row objects if executing query builder, or `null` if not found.
	 */
	public function get( $identifier = null ) {
		if ( null === $identifier ) {
			return $this->query_builder_get_results();
		}

		$user = $this->get_by_identifier( $identifier );

		if ( ! ( $user instanceof \WP_User ) ) {
			return null;
		}

		return new User( $user, $this->database );
	}

	/**
	 * Factory method to instantiate a new, unsaved User model.
	 *
	 * Initializes an empty `User` model with an unassigned `ID` (`null`) and an empty `data` payload.
	 * Fluent setter methods can be chained onto the returned instance to configure user attributes
	 * prior to persisting via `save()`.
	 *
	 * Example:
	 *
	 * ```elscript
	 * Users.build()
	 *     .set_user_login('editor')
	 *     .set_user_email('editor@example.com')
	 *     .set_role('editor')
	 *     .save()
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/users#usersbuild
	 * @return User A new, unsaved `User` model instance.
	 */
	public function build() {
		return new User( null, $this->database );
	}


	/**
	 * Queries and mirrors user records from the users table into SQLite and returns the matching results.
	 *
	 * Directly mirrors rows from the `wp_users` table into an in-memory SQLite database applying
	 * declarative `where` conditions and `options` (such as `order_by`, `limit`, and `offset`).
	 * Flushes the buffer upon completion and returns all matching records as an array of row objects.
	 *
	 * Note: Filter conditions apply directly to columns of the `wp_users` table (`ID`, `user_login`,
	 * `user_nicename`, `user_email`, `user_url`, `user_registered`, `user_status`, `display_name`).
	 *
	 * Examples:
	 *
	 * ```elscript
	 * Users.list()
	 * ```
	 *
	 * ```elscript
	 * Users.list(
	 *     { 'user_status': 0 },
	 *     { 'orderby': { 'user_registered': 'DESC' }, 'limit': 20 }
	 * )
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/users#userslist
	 *
	 * @param array $where   Optional. Associative array or object defining filter conditions against `wp_users` columns. Default empty array.
	 * @param array $options Optional. Associative array or object controlling sorting, limit, and offset. Default empty array.
	 * @return array An array of matching user row objects.
	 */
	public function list( $where = array(), $options = array() ) {
		return $this->database->mirror( 'users', $where, $options )->flush();
	}
}
