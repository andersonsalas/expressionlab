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

use ExpressionLab\Core\Services\Database;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * QueryBuilder trait.
 *
 * Provides fluent, chainable query builder methods for service and model classes that interface
 * with the Database service. Accumulates condition groups, sorting instructions, limits, and offsets,
 * then compiles and delegates execution to the Database service for in-memory SQLite mirroring or
 * direct MySQL execution.
 *
 * ### Implementation Contract
 *
 * Classes incorporating this trait must implement two protected abstract methods:
 * * `get_query_builder_table()`: Returns the database table name without prefix (e.g., `'posts'`, `'users'`).
 * * `get_query_builder_database()`: Returns the live `Database` service instance.
 *
 * ### Chainable Filtering Methods
 *
 * * `where(column, value)`: Adds an AND condition (equality or IN array).
 * * `where(column, operator, value)`: Adds an AND condition with an explicit comparison operator.
 * * `or_where(column, operator_or_value, value)`: Starts a new condition group combined via OR logic.
 * * `order_by(column, direction)`: Appends an ORDER BY sorting clause.
 * * `limit(limit)`: Restricts the maximum number of returned rows.
 * * `offset(offset)`: Sets the number of rows to skip for pagination.
 *
 * ### Terminal Execution Methods
 *
 * * `get()`: Mirrors filtered records into SQLite and returns the result array (implemented on the consuming service).
 * * `count()`: Counts matching rows directly in MySQL without data mirroring.
 * * `to_sql(is_count)`: Compiles and returns the parameterized SQL string without executing it.
 * * `query(sql)`: Mirrors the table into SQLite and executes an arbitrary SELECT query against it.
 *
 * ### Supported Comparison Operators
 *
 * * `'='`: Exact equality (default).
 * * `'>'`, `'>='`, `'<'`, `'<='`: Relational comparisons.
 * * `'!='`, `'<>'`: Inequality.
 * * `'like'`: SQL LIKE pattern matching.
 * * `'not like'`: Negated SQL LIKE pattern matching.
 * * `'in'`: Set membership (target value must be an array).
 * * `'not in'`: Negated set membership (target value must be an array).
 * * `'between'`: Range check (target value must be a two-element array `[min, max]`).
 *
 * Examples:
 *
 * ```elscript
 * where('status', 'publish')
 *     .order_by('id', 'DESC')
 *     .limit(10)
 * ```
 *
 * ```elscript
 * where('status', 'draft')
 *     .or_where('status', 'pending')
 * ```
 *
 * @since 1.0.0
 *
 * @package ExpressionLab
 */
trait QueryBuilder {
	/**
	 * Query builder condition groups.
	 *
	 * Each element is an associative array of AND conditions.
	 * Multiple groups are combined with OR logic.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	private $qb_condition_groups = array();

	/**
	 * Query builder ORDER BY clauses.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	private $qb_order_by = array();

	/**
	 * Query builder LIMIT value.
	 *
	 * @since 1.0.0
	 *
	 * @var int|null
	 */
	private $qb_limit = null;

	/**
	 * Query builder OFFSET value.
	 *
	 * @since 1.0.0
	 *
	 * @var int|null
	 */
	private $qb_offset = null;

	/**
	 * Returns the table name (without prefix) used by the query builder.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return string The table name without prefix.
	 */
	abstract protected function get_query_builder_table(): string;

	/**
	 * Returns the Database instance used by the query builder.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return Database The database service instance.
	 */
	abstract protected function get_query_builder_database(): Database;

	/**
	 * Returns base conditions that are always enforced for the model (e.g. parent foreign keys).
	 *
	 * Subclasses and models like UserMeta and PostMeta can override this method to ensure that all
	 * queries, filters, and SQL mirrors are strictly scoped to the parent entity.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return array Associative array of column-value pairs, or empty array.
	 */
	protected function get_query_builder_base_conditions(): array {
		return array();
	}

	/**
	 * Appends a WHERE condition combined with AND logic within the current condition group.
	 *
	 * Conditions added within the same group are evaluated together using `AND` logic. If no group
	 * exists yet, initializes the primary condition group. Parses conditions into the internal
	 * recursive format expected by `Database::mirror()`.
	 *
	 * #### Call Signatures:
	 * * **Equality (two arguments)**: `where('column', 'value')`
	 * * **IN Set Membership (two arguments with array)**: `where('column', ['val1', 'val2'])`
	 * * **Comparison Operator (three arguments)**: `where('column', 'operator', 'value')`
	 *
	 * #### Supported Comparison Operators:
	 * * `'='`: Exact equality.
	 * * `'>'`, `'>='`, `'<'`, `'<='`: Relational comparisons.
	 * * `'!='`, `'<>'`: Inequality.
	 * * `'like'`: SQL `LIKE` pattern matching.
	 * * `'not like'`: Negated SQL `LIKE` pattern matching.
	 * * `'in'`: Set membership (value must be an array).
	 * * `'not in'`: Negated set membership (value must be an array).
	 * * `'between'`: Range check (value must be a two-element array `[min, max]`).
	 *
	 * Examples:
	 *
	 * ```elscript
	 * where('status', 'publish')
	 * ```
	 *
	 * ```elscript
	 * where('id', '>', 100)
	 * ```
	 *
	 * ```elscript
	 * where('role', ['administrator', 'editor'])
	 * ```
	 *
	 * ```elscript
	 * where('created_at', 'between', ['2026-01-01', '2026-12-31'])
	 * ```
	 *
	 * @since 1.0.0
	 *
	 * @param string     $column            The database column name to filter by.
	 * @param mixed      $operator_or_value Optional. Comparison operator string when using three arguments, or the target value when using two arguments. Default null.
	 * @param mixed|null $value             Optional. The comparison target value when using three arguments. Default null.
	 * @return static The current query builder instance for fluent chaining.
	 */
	public function where( $column, $operator_or_value = null, $value = null ) {
		$parsed = $this->query_builder_parse_condition( $column, $operator_or_value, $value );

		if ( empty( $this->qb_condition_groups ) ) {
			$this->qb_condition_groups[] = array();
		}

		$index = count( $this->qb_condition_groups ) - 1;

		$this->qb_condition_groups[ $index ] = array_merge(
			$this->qb_condition_groups[ $index ],
			$parsed
		);

		return $this;
	}

	/**
	 * Appends a WHERE condition that initiates a new condition group combined via OR logic.
	 *
	 * Each call to `or_where()` finalizes the preceding condition group and starts a new group
	 * joined with previous groups via `OR` logic. Subsequent calls to `where()` append conditions
	 * to this new group combined using `AND` logic.
	 *
	 * Accepts the exact same two-argument and three-argument call signatures and operators as `where()`.
	 *
	 * #### Logic Structure:
	 * Compiles condition groups into grouped SQL expressions equivalent to:
	 * `([Group 1 conditions] AND ...) OR ([Group 2 conditions] AND ...)`
	 *
	 * Examples:
	 *
	 * ```elscript
	 * where('status', 'draft')
	 *     .or_where('status', 'pending')
	 * ```
	 *
	 * ```elscript
	 * where('type', 'post')
	 *     .where('status', 'publish')
	 *     .or_where('type', 'page')
	 *     .where('status', 'pending')
	 * ```
	 *
	 * @since 1.0.0
	 *
	 * @param string     $column            The database column name to filter by.
	 * @param mixed      $operator_or_value Optional. Comparison operator string when using three arguments, or the target value when using two arguments. Default null.
	 * @param mixed|null $value             Optional. The comparison target value when using three arguments. Default null.
	 * @return static The current query builder instance for fluent chaining.
	 */
	public function or_where( $column, $operator_or_value = null, $value = null ) {
		$parsed = $this->query_builder_parse_condition( $column, $operator_or_value, $value );

		$this->qb_condition_groups[] = $parsed;

		return $this;
	}

	/**
	 * Appends an ORDER BY clause to specify sorting direction for query results.
	 *
	 * Can be chained multiple times to apply multi-column sorting. Sorting clauses are evaluated
	 * in the exact sequence in which they were registered.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * order_by('id', 'DESC')
	 * ```
	 *
	 * ```elscript
	 * order_by('type', 'ASC')
	 *     .order_by('date', 'DESC')
	 * ```
	 *
	 * @since 1.0.0
	 *
	 * @param string $column    The database column name to sort by.
	 * @param string $direction Optional. The sort direction (`'ASC'` or `'DESC'`). Default `'ASC'`.
	 * @return static The current query builder instance for fluent chaining.
	 */
	public function order_by( string $column, string $direction = 'ASC' ) {
		$this->qb_order_by[ $column ] = strtoupper( $direction ) === 'DESC' ? 'DESC' : 'ASC';
		return $this;
	}

	/**
	 * Sets the maximum number of rows to retrieve from the mirrored dataset.
	 *
	 * Restricts the result set to the specified limit. Frequently combined with `offset()` to
	 * implement paginated result processing.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * limit(10)
	 * ```
	 *
	 * ```elscript
	 * order_by('id', 'DESC')
	 *     .limit(5)
	 * ```
	 *
	 * @since 1.0.0
	 *
	 * @param int $limit The maximum number of records to retrieve.
	 * @return static The current query builder instance for fluent chaining.
	 */
	public function limit( int $limit ) {
		$this->qb_limit = $limit;
		return $this;
	}

	/**
	 * Sets the number of rows to skip before beginning to return results.
	 *
	 * Applies an offset clause for data pagination, skipping the specified number of rows from
	 * the beginning of the matching result set.
	 *
	 * Example:
	 *
	 * ```elscript
	 * offset(20)
	 *     .limit(10)
	 * ```
	 *
	 * @since 1.0.0
	 *
	 * @param int $offset The number of rows to skip.
	 * @return static The current query builder instance for fluent chaining.
	 */
	public function offset( int $offset ) {
		$this->qb_offset = $offset;
		return $this;
	}

	/**
	 * Mirrors the filtered table into SQLite and executes an arbitrary SQL query.
	 *
	 * Applies accumulated condition groups, ordering, limits, and offsets to mirror matching records
	 * from MySQL into the in-memory SQLite database. Then executes the provided raw SQL `SELECT`
	 * query against the mirrored SQLite table.
	 *
	 * Mirrored tables in SQLite retain their full WordPress table name with database prefix (e.g., `wp_posts`, `wp_users`).
	 *
	 * Automatically resets the query builder state after execution, allowing the instance to be reused.
	 *
	 * Example:
	 *
	 * ```elscript
	 * where('status', 'publish')
	 *     .query('SELECT type, COUNT(*) AS total FROM wp_posts GROUP BY type ORDER BY total DESC')
	 * ```
	 *
	 * @since 1.0.0
	 *
	 * @param string $sql The raw SQL SELECT query to execute against the in-memory SQLite table.
	 * @return array The query result rows as an array of objects.
	 */
	public function query( string $sql ) {
		$where   = $this->query_builder_build_where();
		$options = $this->query_builder_build_options();

		$db = $this->get_query_builder_database();
		$db->mirror( $this->get_query_builder_table(), $where, $options );
		$result = $db->query( $sql );

		$this->query_builder_reset();

		return $result;
	}

	/**
	 * Counts matching rows directly in MySQL without mirroring data into SQLite.
	 *
	 * Compiles accumulated condition groups into an optimized `SELECT COUNT(*)` query and executes
	 * it directly against the live MySQL database via `$wpdb`. Bypasses data mirroring and SQLite
	 * initialization for maximum performance.
	 *
	 * Automatically resets the query builder state after execution.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * count()
	 * ```
	 *
	 * ```elscript
	 * where('status', 'publish')
	 *     .count()
	 * ```
	 *
	 * @since 1.0.0
	 *
	 * @return int The total count of matching records in the database.
	 */
	public function count(): int {
		$where   = $this->query_builder_build_where();
		$options = $this->query_builder_build_options();

		$count = $this->get_query_builder_database()->count( $this->get_query_builder_table(), $where, $options );

		$this->query_builder_reset();

		return $count;
	}

	/**
	 * Compiles and returns the SQL query string for the accumulated conditions without executing it.
	 *
	 * Translates accumulated condition groups, ordering, limit, and offset settings into a parameterized
	 * SQL statement compatible with the target MySQL table. Useful for query inspection, debugging, and
	 * performance optimization.
	 *
	 * Automatically resets the query builder state after compilation.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * where('status', 'publish')
	 *     .order_by('id', 'DESC')
	 *     .limit(10)
	 *     .to_sql()
	 * ```
	 *
	 * ```elscript
	 * where('status', 'draft')
	 *     .to_sql(true)
	 * ```
	 *
	 * @since 1.0.0
	 *
	 * @param bool $is_count Optional. Whether to compile a `SELECT COUNT(*)` query instead of a row projection. Default false.
	 * @return string The compiled SQL query string.
	 */
	public function to_sql( bool $is_count = false ): string {
		global $wpdb;

		$where   = $this->query_builder_build_where();
		$options = $this->query_builder_build_options();

		$sql = $this->get_query_builder_database()->compile( $this->get_query_builder_table(), $where, $options, $is_count );

		if ( isset( $wpdb ) && method_exists( $wpdb, 'remove_placeholder_escape' ) ) {
			$sql = $wpdb->remove_placeholder_escape( $sql );
		}

		$this->query_builder_reset();

		return $sql;
	}

	/**
	 * Parses a condition into the Database-compatible recursive WHERE format.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string     $column            The column name.
	 * @param mixed      $operator_or_value The operator or value.
	 * @param mixed|null $value             Optional. The value for the three-argument form.
	 * @return array The parsed condition as an associative array.
	 */
	private function query_builder_parse_condition( string $column, $operator_or_value, $value ): array {
		// Two-argument invocation form with implicit equality or array set membership.
		if ( null === $value ) {
			return array( $column => $operator_or_value );
		}

		// Three-argument invocation form with explicit comparison operator.
		$operator = strtolower( trim( $operator_or_value ) );

		$operator_map = array(
			'>'        => '$gt',
			'>='       => '$gte',
			'<'        => '$lt',
			'<='       => '$lte',
			'!='       => '$ne',
			'<>'       => '$ne',
			'like'     => '$like',
			'not like' => '$notLike',
			'in'       => '$in',
			'not in'   => '$notIn',
			'between'  => '$between',
		);

		// Equality operator or unrecognized operator: treat as simple equality.
		if ( '=' === $operator || ! isset( $operator_map[ $operator ] ) ) {
			return array( $column => $value );
		}

		return array( $column => array( $operator_map[ $operator ] => $value ) );
	}

	/**
	 * Builds the WHERE array from accumulated condition groups.
	 *
	 * Returns the structure expected by `Database::mirror()`:
	 * - Single group: associative array (AND).
	 * - Multiple groups: indexed array of associative arrays (OR of ANDs).
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return array|null The WHERE conditions, or null if none.
	 */
	private function query_builder_build_where(): ?array {
		$base_conditions = $this->get_query_builder_base_conditions();

		if ( empty( $this->qb_condition_groups ) ) {
			return ! empty( $base_conditions ) ? $base_conditions : null;
		}

		$groups = array_filter(
			$this->qb_condition_groups,
			function ( $group ) {
				return ! empty( $group );
			}
		);

		if ( empty( $groups ) ) {
			return ! empty( $base_conditions ) ? $base_conditions : null;
		}

		// Apply base conditions to every group to preserve scoping across OR condition groups.
		if ( ! empty( $base_conditions ) ) {
			foreach ( $groups as &$group ) {
				$group = array_merge( $base_conditions, $group );
			}
		}

		// Single group: return as AND (associative array).
		if ( 1 === count( $groups ) ) {
			return reset( $groups );
		}

		// Multiple groups: return as OR (indexed array).
		return array_values( $groups );
	}

	/**
	 * Builds the options array from accumulated configuration.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return array|null The options array, or null if no options were set.
	 */
	private function query_builder_build_options(): ?array {
		$options = array();

		if ( ! empty( $this->qb_order_by ) ) {
			$options['orderby'] = $this->qb_order_by;
		}

		if ( null !== $this->qb_limit ) {
			$options['limit'] = $this->qb_limit;
		}

		if ( null !== $this->qb_offset ) {
			$options['offset'] = $this->qb_offset;
		}

		return ! empty( $options ) ? $options : null;
	}

	/**
	 * Resets the query builder state for reuse.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	private function query_builder_reset(): void {
		$this->qb_condition_groups = array();
		$this->qb_order_by         = array();
		$this->qb_limit            = null;
		$this->qb_offset           = null;
	}

	/**
	 * Executes the built query, retrieves all matching results, and flushes the SQLite buffer.
	 *
	 * Mirrors the table into the in-memory SQLite database using the accumulated
	 * conditions and options, fetches all results, and cleans up the SQLite instance.
	 *
	 * The query builder state is reset after execution, allowing the instance to be reused
	 * for a new query.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return array The matching results as an array of objects.
	 */
	public function query_builder_get_results() {
		$where   = $this->query_builder_build_where();
		$options = $this->query_builder_build_options();

		$result = $this->get_query_builder_database()
			->mirror( $this->get_query_builder_table(), $where, $options )
			->flush();

		$this->query_builder_reset();

		return $result;
	}
}
