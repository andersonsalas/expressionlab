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

use ExpressionLab\Core\Interfaces\ServiceInterface;
use ExpressionLab\Core\LanguageEngine;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

// phpcs:disable Squiz.PHP.CommentedOutCode.Found

/**
 * Database Service.
 *
 * This is an abstraction for database operations. It provides methods to mirror database tables into
 * an in-memory SQLite database, allowing complex querying and data manipulation without affecting the
 * live database.
 *
 * @package ExpressionLab
 */
final class Database implements ServiceInterface {
	/**
	 * SQLite3 instance.
	 *
	 * @var \SQLite3
	 */
	private $sqlite;

	/**
	 * Live database instance.
	 *
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * Last results from mirroring.
	 *
	 * @var array
	 */
	private $last_results = array();

	/**
	 * Last mirrored table name.
	 *
	 * @var string
	 */
	private $last_table = null;

	/**
	 * Query history of mirrored operations.
	 *
	 * @var array
	 */
	private $query_history = array();

	/**
	 * Log of all SQL queries executed against MySQL via $wpdb.
	 *
	 * @var array<string>
	 */
	private $wpdb_queries = array();

	/**
	 * Maximum buffer rows for data retrieval.
	 *
	 * Please note that trying to fetch large datasets may lead to performance issues,
	 * or even an Out-Of-Memory error. Use with caution.
	 *
	 * @var int
	 */
	private $buffer_max_rows = 1000;

	/**
	 * Maximum buffer memory (in MB) for data retrieval.
	 *
	 * Please note that the actual memory usage can vary based on the data types and
	 * structure of the mirrored tables. See also `$php_overhead_estimation_factor` for more details
	 * on how memory usage is estimated.
	 *
	 * @var int
	 */
	private $buffer_max_memory = 50;

	/**
	 * PHP overhead estimation factor for memory usage calculations.
	 *
	 * This is a multiplier to estimate the actual memory usage in PHP based on the raw data
	 * size, as PHP arrays and objects can take significantly more memory than the raw data size
	 * due to internal structures and overhead.
	 *
	 * @var float
	 */
	private $buffer_php_overhead_factor = 3.5;

	/**
	 * Constructor.
	 *
	 * @internal
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	/**
	 * Destructor.
	 *
	 * @internal
	 */
	public function __destruct() {
		if ( null !== $this->sqlite ) {
			$this->sqlite->close();
			$this->sqlite = null;
		}
	}

	/**
	 * Sets the maximum buffer rows, memory limit, and overhead factor for data mirroring.
	 *
	 * This configuration relies on a **heuristic estimation**, not an exact calculation, to predict memory usage.
	 * These strict limits exist to protect the server from fatal Out-Of-Memory (OOM) errors
	 * that could otherwise cause the PHP process or database instance to crash or restart.
	 *
	 * Example:
	 *
	 * ```elscript
	 * Database.buffer( 1200, 200, 5.0 )
	 *     .mirror('posts')
	 *     .flush()
	 * ```
	 *
	 * Buffer configuration persists across subsequent mirror operations and is retained
	 * even after calling flush().
	 *
	 * WARNING: Significantly increasing these values elevates the risk of server instability.
	 *
	 * @see https://expressionlab.io/docs/api-reference/database#databasebuffer
	 *
	 * @param int|null   $rows                The maximum number of rows to mirror (`1000` by default).
	 * @param int|null   $memory              The maximum memory (in MB) to use for data mirroring (`50` MB by default).
	 * @param float|null $php_overhead_factor The heuristic multiplier to estimate actual PHP memory usage (`3.5` by default).
	 * @return Database
	 */
	public function buffer( ?int $rows = 1000, ?int $memory = 50, ?float $php_overhead_factor = 3.5 ): Database {
		$this->buffer_max_rows            = $rows ?? 1000;
		$this->buffer_max_memory          = $memory ?? 50;
		$this->buffer_php_overhead_factor = $php_overhead_factor ?? 3.5;
		return $this;
	}

	/**
	 * Mirrors a MySQL table to the in-memory SQLite database using recursive logic.
	 *
	 * The `where` parameter supports a recursive structure to build complex SQL `WHERE` clauses.
	 *
	 * This structure follows the Symfony Expression Language syntax conventions.
	 *
	 * If no `where` conditions are provided, the entire table will be mirrored (subject to buffer limits).
	 *
	 * ### Objects (curly braces) represent an `AND` group
	 *
	 * This expression:
	 *
	 * ```elscript
	 * Database.mirror(
	 *     'posts', {
	 *          'post_status' : 'publish',
	 *          'post_type' : 'post'
	 *      }
	 * ).fetch()
	 * ```
	 *
	 * Compiles to:
	 *
	 * ```sql
	 * SELECT * FROM wp_posts WHERE post_status = 'publish' AND post_type = 'post'
	 * ```
	 *
	 * ### Arrays (square brackets) represent an `OR` group
	 *
	 * This expression:
	 *
	 * ```elscript
	 * Database.mirror(
	 *     'posts', [
	 *          { 'post_status' : 'draft' },
	 *          { 'post_status' : 'pending' }
	 *     ]
	 * ).fetch()
	 * ```
	 *
	 * Compiles to:
	 *
	 * ```sql
	 * SELECT * FROM wp_posts WHERE post_status = 'draft' OR post_status = 'pending'
	 * ```
	 *
	 * ### Nested groups
	 *
	 * This expression:
	 *
	 * ```elscript
	 * Database.mirror(
	 *     'posts', [
	 *         {
	 *              'post_status' : 'publish',
	 *              'post_type' : 'post'
	 *         },
	 *         {
	 *              'post_status' : 'draft'
	 *         }
	 *     ]
	 * ).fetch()
	 * ```
	 *
	 * Compiles to:
	 *
	 * ```sql
	 * SELECT * FROM wp_posts WHERE (post_status = 'publish' AND post_type = 'post') OR (post_status = 'draft')
	 * ```
	 *
	 * ### Complex conditions
	 *
	 * This expression:
	 *
	 * ```elscript
	 * Database.mirror(
	 *     'posts', {
	 *         'post_date' : {
	 *              '$gt' : '2024-01-01'
	 *          },
	 *         'post_status' : {
	 *              '$in' : [ 'publish', 'draft' ]
	 *          }
	 *     }
	 * ).fetch()
	 * ```
	 *
	 * Compiles to:
	 *
	 * ```sql
	 * SELECT * FROM wp_posts WHERE post_date > '2024-01-01' AND post_status IN ('publish', 'draft')
	 * ```
	 *
	 * ### Cross-reference with mirrored tables
	 *
	 * Use the special `$prev.COLUMN_NAME` syntax to reference values from the last mirrored table,
	 * or `$TABLE_NAME.COLUMN_NAME` to reference any previously mirrored table.
	 *
	 * This allows chaining queries based on previous results:
	 *
	 * ```elscript
	 * Database.mirror( 'posts',
	 *      { 'post_author' : 1 },
	 *      { 'limit' : 10 }
	 * ).mirror( 'postmeta',
	 *      { 'post_id' : '$posts.ID' }
	 * ).fetch()
	 * ```
	 *
	 * Compiled SQL:
	 *
	 * ```sql
	 * SELECT * FROM wp_posts WHERE post_author IN ( ... IDs from last mirrored table ... )
	 * ```
	 *
	 * ### Supported Operators
	 *
	 * - `$eq`, `$gt`, `$lt`, `$gte`, `$lte`, `$ne`: Comparison operators.
	 * - `$isNull`, `$isNotNull`: Null checks.
	 * - `$between`: Range checks.
	 * - `$like`, `$notLike`: Pattern matching operators.
	 * - `$in`, `$notIn`: Membership checks.
	 *
	 * ### Configuration Options
	 *
	 * Use the `$options` argument (third parameter) to control limits, selection, sorting, and aliases:
	 *
	 * - `prefix` (`string`): Custom table prefix override.
	 * - `select` (`string|array`): Columns to select (default: `*`).
	 * - `limit` (`int`): Maximum number of rows to retrieve.
	 * - `offset` (`int`): Number of rows to skip (default: `0`).
	 * - `orderby` (`array`): Sorting conditions.
	 * - `as` (`string`): Custom table alias for the in-memory SQLite table (e.g. `'my_posts'`).
	 *
	 * Example:
	 *
	 * ```elscript
	 * Database.mirror( 'posts', null, {
	 *     'select': [ 'ID', 'post_title' ],
	 *     'limit': 5,
	 *     'offset': 0,
	 *     'orderby': { 'ID': 'DESC' },
	 *     'as': 'recent_posts'
	 * } ).fetch()
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/database#databasemirror
	 *
	 * @param string     $table   The table name (without prefix).
	 * @param array|null $where   Recursive structure of conditions (Object = `AND`, Array = `OR`).
	 * @param array|null $options Optional additional options for mirroring (`prefix`, `select`, `limit`, `offset`, `orderby`, `as`).
	 * @return Database
	 * @throws \Exception If table name or alias is invalid, if table does not exist, if database query fails, or if memory safety checks are triggered.
	 */
	public function mirror( $table, ?array $where = array(), ?array $options = null ): Database {
		if ( class_exists( 'SQLite3' ) && null === $this->sqlite && false === EXPRESSION_LAB_DISABLE_SQLITE ) {
			$this->sqlite = new \SQLite3( ':memory:' ); // phpcs:ignore
		}

		$clean_table = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $table );
		if ( empty( $clean_table ) || $clean_table !== $table ) {
			throw new \Exception( esc_html( "Invalid table name: {$table}" ) );
		}

		$site_id   = LanguageEngine::get()->get_site_id();
		$is_global = in_array( $clean_table, $this->wpdb->global_tables, true );

		if ( is_multisite() && ! empty( $this->wpdb->ms_global_tables ) ) {
			$is_global = $is_global || in_array( $clean_table, $this->wpdb->ms_global_tables, true );
		}

		if ( $is_global ) {
			$default_prefix = $this->wpdb->base_prefix;
			$effective_site = $site_id ?? ( is_multisite() ? get_current_blog_id() : null );
			if ( is_multisite() && get_main_site_id() !== $effective_site ) {
				LanguageEngine::get()->add_message( 'info', "NOTE: Mirroring global table '{$clean_table}' with base prefix '{$default_prefix}'" );
			}
		} else {
			$default_prefix = ( $site_id && is_multisite() ) ? $this->wpdb->get_blog_prefix( $site_id ) : $this->wpdb->get_blog_prefix();
		}

		$prefix       = $options['prefix'] ?? null;
		$prefix       = $prefix ?? $default_prefix;
		$prefix       = preg_replace( '/[^a-zA-Z0-9_]/', '', $prefix ); // Sanitize prefix.
		$target_table = $prefix . $clean_table;

		// Check if table exists.
		LanguageEngine::get()->tick();
		$tables_query         = $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $this->wpdb->esc_like( $target_table ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$this->wpdb_queries[] = $this->wpdb->remove_placeholder_escape( $tables_query );
		$tables               = $this->wpdb->get_col( $tables_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		LanguageEngine::get()->tick();

		if ( empty( $tables ) ) {
			throw new \Exception( esc_html( "Table does not exist: {$target_table}" ) );
		}

		LanguageEngine::get()->tick( $remaining_time );

		$max_exec_ms       = max( 1, (int) ceil( ( $remaining_time ?? 1 ) * 1000 ) );
		$optimization_hint = "/*+ MAX_EXECUTION_TIME({$max_exec_ms}) */";

		$mirror_options = $options ?? array();
		if ( ! isset( $mirror_options['limit'] ) ) {
			$mirror_options['limit'] = $this->buffer_max_rows;
		}

		$query       = $this->compile( $clean_table, $where, $mirror_options, false, $optimization_hint );
		$count_query = $this->compile( $clean_table, $where, $options, true, $optimization_hint );

		LanguageEngine::get()->tick();
		$this->wpdb_queries[] = $this->wpdb->remove_placeholder_escape( $count_query );
		$count                = (int) $this->wpdb->get_var( $count_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		LanguageEngine::get()->tick( $remaining_time, $count ); // Yes, calculated count is the operation cost here.

		// Calculate total buffered rows from history.
		$total_buffered_rows = 0;
		foreach ( $this->query_history as $history_item ) {
			$total_buffered_rows += $history_item['count'] ?? 0;
		}

		// Calculate the effective number of rows that will actually be returned/buffered in memory.
		$requested_limit = $options['limit'] ?? null;

		if ( isset( $requested_limit ) ) {
			$requested_limit_val = abs( (int) $requested_limit );
			$rows_to_evaluate    = min( $count, $requested_limit_val );

			if ( isset( $options['offset'] ) ) {
				$offset_val             = abs( (int) $options['offset'] );
				$remaining_after_offset = max( 0, $count - $offset_val );
				$rows_to_evaluate       = min( $remaining_after_offset, $requested_limit_val );
			}

			// Explicit limit check: If the requested limit exceeds available buffer rows, throw exception.
			if ( $rows_to_evaluate + $total_buffered_rows > $this->buffer_max_rows ) {
				$mbr = 1 === $this->buffer_max_rows ? 'row' : 'rows';
				$lrr = 1 === $total_buffered_rows ? 'row' : 'rows';
				throw new \Exception( esc_html( "Memory safety triggered. Max in-memory buffer of {$this->buffer_max_rows} {$mbr} will be exceeded by a query that requested {$rows_to_evaluate} rows (Current buffer has " . $total_buffered_rows . " {$lrr})" ) );
			}
		} else {
			// No explicit limit was requested. Check remaining buffer capacity.
			$remaining_buffer = max( 0, $this->buffer_max_rows - $total_buffered_rows );

			if ( $remaining_buffer <= 0 ) {
				$mbr = 1 === $this->buffer_max_rows ? 'row' : 'rows';
				$lrr = 1 === $total_buffered_rows ? 'row' : 'rows';
				throw new \Exception( esc_html( "Memory safety triggered. Max in-memory buffer of {$this->buffer_max_rows} {$mbr} is already reached (Current buffer has " . $total_buffered_rows . " {$lrr})" ) );
			}

			if ( $count > $remaining_buffer ) {
				// Auto-cap to available buffer and notify developer via warning (Workbench-style).
				$rows_to_evaluate = $remaining_buffer;
				LanguageEngine::get()->add_message(
					'warning',
					sprintf(
						"Table '%s' has %s matching rows. Automatically capped to %s rows to protect in-memory buffer. Specify { 'limit': N } or use Database.buffer(%d) to retrieve more.",
						$clean_table,
						number_format( $count ),
						number_format( $rows_to_evaluate ),
						$count
					)
				);
			} else {
				$rows_to_evaluate = $count;
			}
		}

		// Buffer check: Estimate memory usage and prevent mirroring if it exceeds the
		// max buffer memory limit. This is a very rough estimate based on row count and
		// a simple calculation, as actual memory usage can vary greatly based on data
		// types and structure.

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- We need the full table status for the estimation, and this is an internal query not exposed to user input.
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- These names comes from the database engine.
		LanguageEngine::get()->tick();
		$table_status_query   = $this->wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $this->wpdb->esc_like( $target_table ) );
		$this->wpdb_queries[] = $this->wpdb->remove_placeholder_escape( $table_status_query );
		$table_status         = $this->wpdb->get_row( $table_status_query );
		LanguageEngine::get()->tick();

		if ( $table_status && isset( $table_status->Avg_row_length ) ) {
			$avg_row_bytes = (int) $table_status->Avg_row_length;

			// If Avg_row_length is 0 (happens in some un-analyzed InnoDB tables), we use a
			// conservative fallback (e.g. 1KB per row) or try Data_length / Rows.
			if ( 0 === $avg_row_bytes && $table_status->Rows > 0 ) {
				$avg_row_bytes = (int) ( $table_status->Data_length / $table_status->Rows );
			}

			// If it is still 0, assume a safe default value (e.g. 1024 bytes).
			if ( 0 === $avg_row_bytes ) {
				$avg_row_bytes = 1024;
			}

			// Raw size calculation (Raw SQL Size) based on the effective rows to be loaded in memory.
			$estimated_raw_bytes = $rows_to_evaluate * $avg_row_bytes;

			// PHP inflation factor: PHP arrays take up much more than raw data.
			// A factor of 3x to 4x is prudent to avoid real OOM.
			$php_overhead_factor        = $this->buffer_php_overhead_factor;
			$estimated_php_memory_bytes = $estimated_raw_bytes * $php_overhead_factor;
			$estimated_php_memory_mb    = $estimated_php_memory_bytes / 1024 / 1024;

			// User-defined limit.
			if ( $estimated_php_memory_mb > $this->buffer_max_memory ) {
				throw new \Exception(
					esc_html(
						sprintf(
							'Memory safety triggered. The query is estimated to require ~%.2f MB of RAM, which exceeds the limit of %d MB. (Based on %d rows with avg density of %d bytes)',
							$estimated_php_memory_mb,
							$this->buffer_max_memory,
							$rows_to_evaluate,
							$avg_row_bytes
						)
					)
				);
			}
		}
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		// If we reach here, we can safely mirror the data.
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		LanguageEngine::get()->tick();
		$this->wpdb_queries[] = $this->wpdb->remove_placeholder_escape( $query );
		$results              = $this->wpdb->get_results( $query, ARRAY_A );
		LanguageEngine::get()->tick( $remaining_time, count( $results ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		$alias = null;
		if ( isset( $options['as'] ) && is_string( $options['as'] ) ) {
			$clean_alias = preg_replace( '/[^a-zA-Z0-9_]/', '', $options['as'] );
			if ( empty( $clean_alias ) || $clean_alias !== $options['as'] ) {
				throw new \Exception( esc_html( "Invalid alias name: {$options['as']}" ) );
			}
			$alias = $clean_alias;
		}

		$sqlite_table = $alias ?? ( 'wp_' . $clean_table );

		$this->last_results = $results;
		$this->last_table   = $alias ?? $clean_table;

		$this->query_history[] = array(
			'table'        => $target_table,
			'raw_table'    => $clean_table,
			'sqlite_table' => $sqlite_table,
			'as'           => $alias,
			'where'        => $where,
			'options'      => $options,
			'sql'          => $query,
			'count'        => count( $results ),
			'memory'       => isset( $estimated_php_memory_mb ) ? round( $estimated_php_memory_mb, 2 ) : 'N/A',
			'results'      => $results,
		);

		if ( $this->wpdb->last_error ) {
			// Remove quote to prevent breaking exception message.
			$last_error = str_replace( "'", '', $this->wpdb->last_error );
			throw new \Exception( esc_html( 'Database error during mirroring: ' . $last_error ) );
		}

		if ( null !== $this->sqlite ) {
			LanguageEngine::get()->tick();
			$this->create_sqlite_schema( $sqlite_table, $results );
			LanguageEngine::get()->tick();
		}

		return $this;
	}

	/**
	 * Fetches the results from the latest mirrored table(s) from SQLite.
	 *
	 * Requires at least one mirrored table prior to calling this method.
	 *
	 * Example:
	 *
	 * ```elscript
	 * Database.mirror('posts').fetch()
	 * ```
	 *
	 * Mirrored tables always have the `wp_` prefix unless an alias is specified with `'as'`.
	 *
	 * @see https://expressionlab.io/docs/api-reference/database#databasefetch
	 *
	 * @param string|null $table     Optional table name or alias to get results from. If `null`, uses last mirrored table.
	 * @param bool        $as_object Whether to return results as objects. Default `true` returns objects, `false` returns associative arrays.
	 * @return array
	 * @throws \Exception If no table has been mirrored yet or if the specified table is not found in the history.
	 */
	public function fetch( ?string $table = null, bool $as_object = true ): array {
		$table_name   = $table ?? $this->last_table;
		$clean_name   = $table_name ? preg_replace( '/[^a-zA-Z0-9_]/', '', $table_name ) : null;
		$target_table = 'wp_' . $clean_name;
		$data         = array();

		if ( empty( $clean_name ) ) {
			return $data;
		}

		// Resolve the exact SQLite table name (checking aliases first).
		$sqlite_target = $target_table;
		$display_title = $target_table;

		foreach ( array_reverse( $this->query_history ) as $history_item ) {
			if (
				( isset( $history_item['as'] ) && $history_item['as'] === $clean_name ) ||
				( isset( $history_item['sqlite_table'] ) && $history_item['sqlite_table'] === $clean_name )
			) {
				$sqlite_target = $history_item['sqlite_table'];
				$display_title = $sqlite_target;
				break;
			}
		}

		if ( null !== $this->sqlite ) {
			LanguageEngine::get()->tick();
			$result = $this->sqlite->query( "SELECT * FROM \"{$sqlite_target}\"" );

			// Fallback: try raw clean_name if wp_ prefix failed and not found in history.
			if ( ! $result && $sqlite_target !== $clean_name ) {
				$result = $this->sqlite->query( "SELECT * FROM \"{$clean_name}\"" );
				if ( $result ) {
					$display_title = $clean_name;
				}
			}
			LanguageEngine::get()->tick();

			// Check if result is valid.
			if ( ! $result ) {
				return $data;
			}

			while ( $row = $result->fetchArray( SQLITE3_ASSOC ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
				$data[] = ! $as_object ? $row : (object) $row;
			}
		} else {
			$founded = false;
			foreach ( array_reverse( $this->query_history ) as $history_item ) {
				if (
					( isset( $history_item['as'] ) && $history_item['as'] === $clean_name ) ||
					( isset( $history_item['sqlite_table'] ) && $history_item['sqlite_table'] === $clean_name ) ||
					( isset( $history_item['raw_table'] ) && $history_item['raw_table'] === $clean_name ) ||
					( isset( $history_item['table'] ) && ( $history_item['table'] === $target_table || $history_item['table'] === $clean_name ) )
				) {
					$data    = $history_item['results'];
					$founded = true;
					break;
				}
			}

			if ( ! $founded ) {
				throw new \Exception( esc_html( "Unknown table: {$table}" ) );
			}
		}

		LanguageEngine::get()->tick();

		$this->last_table   = $clean_name;
		$this->last_results = $data;

		// Table visualization data.
		LanguageEngine::get()->begin_visualization_group()->add_visualization(
			array(
				'type'  => 'table',
				'title' => "Table: {$display_title}",
				'data'  => $data,
			)
		);

		return $data;
	}

	/**
	 * Fetches the results from the latest mirrored table(s) and then flushes the in-memory SQLite database.
	 *
	 * Identical to `fetch`, but also frees memory after retrieving data from SQLite. This method is recommended when working with large datasets.
	 *
	 * Requires at least one mirrored table prior to calling this method.
	 *
	 * Example:
	 *
	 * ```elscript
	 * Database.mirror('posts').flush()
	 * ```
	 *
	 * Mirrored tables always have the `wp_` prefix unless an alias is specified with `'as'`.
	 *
	 * @see https://expressionlab.io/docs/api-reference/database#databaseflush
	 *
	 * @param string|null $table     Optional table name (without prefix) to get results from before flushing. If `null`, uses last mirrored table.
	 * @param bool        $as_object Whether to return results as objects. Default `true` returns objects, `false` returns associative arrays.
	 * @return array The last mirrored results before flushing.
	 * @throws \Exception If no table has been mirrored yet or if the specified table is not found in the history.
	 */
	public function flush( ?string $table = null, bool $as_object = true ) {
		$data = $this->fetch( $table, $as_object );

		// Reset state for next mirror operation.
		$this->last_table    = null;
		$this->last_results  = array();
		$this->query_history = array();
		$this->wpdb_queries  = array();

		// Destroy the SQLite database and create a new one to free memory.
		if ( null !== $this->sqlite ) {
			$this->sqlite->close();
			$this->sqlite = new \SQLite3( ':memory:' ); // phpcs:ignore -- We are using SQLite3, not the old sqlite extension.
		}

		return $data;
	}

	/**
	 * Build recursive WHERE clause from conditions.
	 *
	 * @internal
	 *
	 * @param array $conditions The conditions array.
	 * @param array &$flat_values Reference to flat values array for prepared statements.
	 * @param int   $depth Recursion depth level for stack safety.
	 * @return string The WHERE clause.
	 * @throws \Exception If nesting depth exceeds maximum allowed limit.
	 */
	private function build_recursive_where( array $conditions, &$flat_values, int $depth = 0 ) {
		if ( $depth > 50 ) {
			throw new \Exception( 'WHERE clause nesting depth limit exceeded (max: 50).' );
		}

		if ( empty( $conditions ) ) {
			return '';
		}

		// Determine if we are in an AND (associative) or OR (indexed) group.
		$is_assoc = ( array_keys( $conditions ) !== range( 0, count( $conditions ) - 1 ) );
		$operator = $is_assoc ? 'AND' : 'OR';
		$parts    = array();

		foreach ( $conditions as $column => $value ) {
			// CASE 1: Nested branch (Recursion).
			// Handles arrays of objects or deep logic nesting.
			if ( is_int( $column ) && is_array( $value ) ) {
				$inner = $this->build_recursive_where( $value, $flat_values, $depth + 1 );
				if ( $inner ) {
					$parts[] = "({$inner})";
				}
				continue;
			}

			// Allow only alphanumeric and underscore characters for column names.
			$safe_col = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $column );

			if ( empty( $safe_col ) ) {
				continue;
			}

			// CASE 2: Cross-reference ($prev.ID or $table.column).
			// Links the current query with results from a previously mirrored table.
			if ( is_string( $value ) && strpos( $value, '$' ) === 0 && strpos( $value, '.' ) !== false ) {
				// Remove leading $.
				$ref_string                  = substr( $value, 1 );
				list( $ref_table, $ref_col ) = explode( '.', $ref_string, 2 );

				$source_data = array();

				if ( 'prev' === $ref_table ) {
					// Use the last element of query_history.
					$last = end( $this->query_history );
					if ( $last && isset( $last['results'] ) ) {
						$source_data = $last['results'];
					}
				} else {
					// Find in query_history (reverse search to get latest).
					for ( $i = count( $this->query_history ) - 1; $i >= 0; $i-- ) {
						$history_item = $this->query_history[ $i ];

						// Check 'raw_table' if available, or fall back to string matching.
						if ( isset( $history_item['raw_table'] ) && $history_item['raw_table'] === $ref_table ) {
							$source_data = $history_item['results'];
							break;
						}

						// Fallback match: Does 'wp_posts' end with '_posts'? Or equals 'posts'?
						if ( $history_item['table'] === $ref_table || substr( $history_item['table'], -strlen( '_' . $ref_table ) ) === '_' . $ref_table ) {
							$source_data = $history_item['results'];
							break;
						}
					}
				}

				$ids   = array_unique( array_column( $source_data, $ref_col ) );
				$value = empty( $ids ) ? array( -1 ) : $ids;
			}

			// CASE 3: Complex operators and specialized arrays.
			if ( is_array( $value ) ) {
				// 3.1: Logical Comparison Operators ($eq, $gt, $lt, $gte, $lte, $ne).
				$comparison_map = array(
					'$eq'  => '=',
					'$gt'  => '>',
					'$gte' => '>=',
					'$lt'  => '<',
					'$lte' => '<=',
					'$ne'  => '!=',
				);

				foreach ( $comparison_map as $key => $sql_op ) {
					if ( isset( $value[ $key ] ) ) {
						$parts[]       = "{$safe_col} {$sql_op} %s";
						$flat_values[] = $value[ $key ];
						continue 2; // Move to next column in foreach.
					}
				}

				// 3.2: Nullability ($isNull, $isNotNull).
				if ( isset( $value['$isNull'] ) ) {
					$parts[] = "{$safe_col} IS NULL";
					continue;
				}
				if ( isset( $value['$isNotNull'] ) ) {
					$parts[] = "{$safe_col} IS NOT NULL";
					continue;
				}

				// 3.3: Range ($between).
				if ( isset( $value['$between'] ) && is_array( $value['$between'] ) && count( $value['$between'] ) === 2 ) {
					$parts[]       = "{$safe_col} BETWEEN %s AND %s";
					$flat_values[] = $value['$between'][0];
					$flat_values[] = $value['$between'][1];
					continue;
				}

				// 3.4: Legacy Search and Collections (LIKE, NOT LIKE, IN)
				if ( isset( $value['$like'] ) ) {
					foreach ( (array) $value['$like'] as $like_val ) {
						$parts[]       = "{$safe_col} LIKE %s";
						$flat_values[] = $like_val;
					}
					continue;
				}

				if ( isset( $value['$notLike'] ) ) {
					foreach ( (array) $value['$notLike'] as $not_like_val ) {
						$parts[]       = "{$safe_col} NOT LIKE %s";
						$flat_values[] = $not_like_val;
					}
					continue;
				}

				// Default behavior for arrays: Treated as 'IN' clause.
				$op   = isset( $value['$notIn'] ) ? 'NOT IN' : 'IN';
				$vals = $value['$notIn'] ?? ( $value['$in'] ?? $value );

				if ( is_array( $vals ) ) {
					if ( empty( $vals ) ) {
						// An empty IN clause is always false (0 = 1); an empty NOT IN is always true (1 = 1).
						$parts[] = ( 'NOT IN' === $op ) ? '1 = 1' : '0 = 1';
					} else {
						$placeholders = implode( ',', array_fill( 0, count( $vals ), '%s' ) );
						$parts[]      = "{$safe_col} {$op} ({$placeholders})";
						foreach ( $vals as $v ) {
							$flat_values[] = $v;
						}
					}
				}
			} else {
				// CASE 4: Simple equality (The standard 'col = val' behavior).
				$parts[]       = "{$safe_col} = %s";
				$flat_values[] = $value;
			}
		}

		return implode( " {$operator} ", $parts );
	}

	/**
	 * Executes an SQL query against the in-memory SQLite database and returns results.
	 *
	 * Requires at least one mirrored table prior to calling this method.
	 *
	 * Example:
	 *
	 * ```elscript
	 * Database.mirror('posts')
	 *      .query('SELECT * FROM wp_posts')
	 * ```
	 *
	 * Mirrored tables always have the `wp_` prefix unless an alias is specified with `'as'`.
	 *
	 * @see https://expressionlab.io/docs/api-reference/database#databasequery
	 *
	 * @param string $sql The SQL query.
	 * @return array The query results.
	 * @throws \Exception If `SQLite3` extension is missing, mirroring is disabled, no `SQLite3` instance is available, multiple statements are submitted, or an `SQLite` execution error occurs.
	 */
	public function query( $sql ) {
		if ( ! class_exists( 'SQLite3' ) ) {
			throw new \Exception( 'SQLite3 extension not available in the server.' );
		}

		if ( true === EXPRESSION_LAB_DISABLE_SQLITE ) {
			throw new \Exception( 'Mirroring is disabled by the EXPRESSION_LAB_DISABLE_SQLITE constant.' );
		}

		if ( null === $this->sqlite ) {
			throw new \Exception( 'No SQLite3 instance available. Ensure at least one table has been mirrored.' );
		}

		// Basic validation to ensure we are running SELECTs, CTEs (WITH), or EXPLAIN queries.
		$sql = trim( $sql );

		// Reject multi-statement (stacked) queries.
		if ( substr_count( rtrim( $sql, "; \t\n\r" ), ';' ) > 0 ) {
			throw new \Exception( 'Multi-statement queries are not allowed.' );
		}

		$first_keyword = strtoupper( (string) strtok( $sql, " \t\n\r" ) );
		if ( ! in_array( $first_keyword, array( 'SELECT', 'WITH', 'EXPLAIN', '(SELECT' ), true ) && stripos( $sql, 'SELECT' ) !== 0 ) {
			return array();
		}

		LanguageEngine::get()->tick();
		$result = $this->sqlite->query( $sql );
		LanguageEngine::get()->tick();

		if ( ! $result ) {
			// Check if there is an error in the SQLite query and log it for debugging.
			$error = $this->sqlite->lastErrorMsg();
			if ( $error ) {
				throw new \Exception( esc_html( 'SQLite query error: ' . $error ) );
			}
			return array();
		}

		$data = array();

		while ( $row = $result->fetchArray( SQLITE3_ASSOC ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			$data[] = $row;
		}

		LanguageEngine::get()->tick();

		LanguageEngine::get()->begin_visualization_group()->add_visualization(
			array(
				'type'  => 'table',
				'title' => 'Query results',
				'data'  => $data,
			)
		);

		$this->last_results = $data;

		return $data;
	}

	/**
	 * Compiles a query into a prepared SQL string without executing it.
	 *
	 * @internal
	 *
	 * @param string     $table             The table name (without prefix).
	 * @param array|null $where             Recursive structure of conditions.
	 * @param array|null $options           Optional additional options (`prefix`, `select`, `limit`, `offset`, `orderby`).
	 * @param bool       $is_count          Whether to compile a SELECT COUNT(*) query. Default `false`.
	 * @param string     $optimization_hint Optional MySQL optimizer hint (e.g. `/*+ MAX_EXECUTION_TIME(ms) * /`).
	 * @return string The compiled SQL statement with prepared values.
	 * @throws \Exception If table name is invalid or WHERE clause nesting depth limit is exceeded.
	 */
	public function compile( string $table, ?array $where = array(), ?array $options = null, bool $is_count = false, string $optimization_hint = '' ): string {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		// phpcs:disable Generic.Formatting.MultipleStatementAlignment.NotSame
		$clean_table = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $table );
		if ( empty( $clean_table ) || $clean_table !== $table ) {
			throw new \Exception( esc_html( "Invalid table name: {$table}" ) );
		}

		$site_id   = LanguageEngine::get()->get_site_id();
		$is_global = in_array( $clean_table, $this->wpdb->global_tables, true );

		if ( is_multisite() && ! empty( $this->wpdb->ms_global_tables ) ) {
			$is_global = $is_global || in_array( $clean_table, $this->wpdb->ms_global_tables, true );
		}

		if ( $is_global ) {
			$default_prefix = $this->wpdb->base_prefix;
		} else {
			$default_prefix = ( $site_id && is_multisite() ) ? $this->wpdb->get_blog_prefix( $site_id ) : $this->wpdb->get_blog_prefix();
		}

		$prefix       = $options['prefix'] ?? null;
		$prefix       = $prefix ?? $default_prefix;
		$prefix       = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $prefix );
		$target_table = $prefix . $clean_table;

		$where       = $where ?? array();
		$flat_values = array();
		$where_sql   = $this->build_recursive_where( $where, $flat_values );

		if ( $is_count ) {
			$select = 'COUNT(*)';
		} else {
			$select_opt = $options['select'] ?? '*';
			if ( is_array( $select_opt ) && ! empty( $select_opt ) ) {
				$cleaned_columns = array_filter(
					array_map(
						function ( $col ) {
							return preg_replace( '/[^a-zA-Z0-9_]/', '', $col );
						},
						$select_opt
					)
				);
				$select          = empty( $cleaned_columns ) ? '*' : implode( ', ', $cleaned_columns );
			} elseif ( is_string( $select_opt ) && '*' !== trim( $select_opt ) ) {
				$columns         = explode( ',', $select_opt );
				$cleaned_columns = array_filter(
					array_map(
						function ( $col ) {
							return preg_replace( '/[^a-zA-Z0-9_]/', '', trim( $col ) );
						},
						$columns
					)
				);
				$select          = empty( $cleaned_columns ) ? '*' : implode( ', ', $cleaned_columns );
			} else {
				$select = '*';
			}
		}

		$hint_part = ! empty( $optimization_hint ) ? " {$optimization_hint}" : '';
		$query     = "SELECT{$hint_part} {$select} FROM `{$target_table}`";

		if ( ! empty( $where_sql ) ) {
			$query .= " WHERE {$where_sql}";
		}

		if ( ! $is_count && isset( $options['orderby'] ) && is_array( $options['orderby'] ) && ! empty( $options['orderby'] ) ) {
			$order_parts = array();
			$is_assoc    = ( array_keys( $options['orderby'] ) !== range( 0, count( $options['orderby'] ) - 1 ) );
			$order_list  = $is_assoc ? array( $options['orderby'] ) : $options['orderby'];

			foreach ( $order_list as $order_cond ) {
				if ( is_array( $order_cond ) ) {
					foreach ( $order_cond as $col => $dir ) {
						$safe_col = preg_replace( '/[^a-zA-Z0-9_]/', '', $col );
						if ( ! empty( $safe_col ) ) {
							$dir           = ( is_string( $dir ) && strtoupper( $dir ) === 'DESC' ) ? 'DESC' : 'ASC';
							$order_parts[] = "{$safe_col} {$dir}";
						}
					}
				}
			}

			if ( ! empty( $order_parts ) ) {
				$query .= ' ORDER BY ' . implode( ', ', $order_parts );
			}
		}

		if ( ! $is_count ) {
			if ( isset( $options['limit'] ) ) {
				$limit  = abs( (int) $options['limit'] );
				$query .= " LIMIT {$limit}";
			}

			if ( isset( $options['offset'] ) ) {
				$offset = abs( (int) $options['offset'] );
				$query .= " OFFSET {$offset}";
			}
		}

		if ( ! empty( $flat_values ) ) {
			$query = $this->wpdb->prepare( $query, $flat_values );
		}

		return $query;
		// phpcs:enable Generic.Formatting.MultipleStatementAlignment.NotSame
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Counts matching rows in a MySQL table without mirroring data into SQLite.
	 *
	 * This method is similar to `Database.mirror()` except that it counts rows instead of returning them.
	 *
	 * It can be used to efficiently count rows in large tables without incurring the overhead of mirroring data to SQLite.
	 *
	 * Example:
	 *
	 * ```elscript
	 * Database.count(
	 *      'postmeta',
	 *      { 'meta_key' : { '$like' : '_%' } }
	 * )
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/database#databasecount
	 *
	 * @param string     $table   The table name (without prefix).
	 * @param array|null $where   Recursive structure of conditions.
	 * @param array|null $options Optional additional options (`prefix`).
	 * @return int The total count of matching rows.
	 * @throws \Exception If table name is invalid, nesting depth limit is exceeded, or database query fails.
	 */
	public function count( string $table, ?array $where = array(), ?array $options = null ): int {
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		LanguageEngine::get()->tick();

		$count_sql = $this->compile( $table, $where, $options, true );

		LanguageEngine::get()->tick();
		$this->wpdb_queries[] = $this->wpdb->remove_placeholder_escape( $count_sql );
		$count                = $this->wpdb->get_var( $count_sql );
		LanguageEngine::get()->tick();

		if ( $this->wpdb->last_error ) {
			$last_error = str_replace( "'", '', $this->wpdb->last_error );
			throw new \Exception( esc_html( 'Database error during count: ' . $last_error ) );
		}

		return (int) $count;
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Returns all SQL queries executed against MySQL ($wpdb) in the current expression.
	 *
	 * Example:
	 *
	 * ```elscript
	 * Database.mirror( 'posts',
	 *      { 'post_author' : 1 },
	 *      { 'limit' : 10 }
	 * ).mirror( 'postmeta',
	 *      { 'post_id' : '$posts.ID' }
	 * ).get_queries()
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/database#databaseget_queries
	 * @return array<string> List of executed SQL queries.
	 */
	public function get_queries(): array {
		LanguageEngine::get()->tick();
		return $this->wpdb_queries;
	}

	/**
	 * Helper to create SQLite schema and insert data.
	 *
	 * @internal
	 *
	 * @param string $table   Table name.
	 * @param array  $results Data rows.
	 * @throws \Throwable If schema creation or transaction fails.
	 */
	private function create_sqlite_schema( $table, $results ) {
		// If results are empty, we might still want to create the table structure if we knew it.
		// But in this dynamic mirror, we rely on results to define structure.
		// If no results, we can't infer schema easily without a separate DESCRIBE query.
		// For now, if empty, we just return. Chains relying on this table will fail or return empty.
		if ( empty( $results ) ) {
			return;
		}

		// Validate table name for SQLite (alphanumeric only).
		$table = preg_replace( '/[^a-zA-Z0-9_]/', '', $table );

		// Drop previous table if re-mirroring to prevent schema collision,
		// column mismatch errors, and dirty state accumulation.
		$this->sqlite->exec( "DROP TABLE IF EXISTS \"{$table}\"" );

		// Infer schema from the first row.
		$first_row = $results[0];
		$columns   = array();

		foreach ( array_keys( $first_row ) as $col ) {
			// Sanitize column names for SQLite.
			$safe_col = preg_replace( '/[^a-zA-Z0-9_]/', '', $col );

			// Use TEXT for flexibility as we are just mirroring data for reading.
			$columns[] = "\"{$safe_col}\" TEXT";
		}

		$columns_sql = implode( ', ', $columns );
		$create_sql  = "CREATE TABLE \"{$table}\" ({$columns_sql})";
		$this->sqlite->exec( $create_sql );

		// Insert Data in Transaction with safe rollback on error.
		$this->sqlite->exec( 'BEGIN TRANSACTION' );

		try {
			$placeholders = array_fill( 0, count( $first_row ), '?' );
			$stmt_sql     = "INSERT INTO \"{$table}\" VALUES (" . implode( ', ', $placeholders ) . ')';
			$stmt         = $this->sqlite->prepare( $stmt_sql );

			if ( $stmt ) {
				foreach ( $results as $row ) {
					$stmt->reset();
					$i = 1;
					foreach ( $row as $val ) {
						if ( null === $val ) {
							$stmt->bindValue( $i++, null, SQLITE3_NULL );
						} else {
							$stmt->bindValue( $i++, $val, SQLITE3_TEXT );
						}
					}
					$stmt->execute();
				}
				$stmt->close();
			}

			LanguageEngine::get()->tick();
			$this->sqlite->exec( 'COMMIT' );
			LanguageEngine::get()->tick();
		} catch ( \Throwable $e ) {
			$this->sqlite->exec( 'ROLLBACK' );
			throw $e;
		}
	}

	/**
	 * Lists all tables in the current WordPress database with engine, row count, and size metrics.
	 *
	 * Returns tabular and graphical distribution visualizer data.
	 *
	 * @see https://expressionlab.io/docs/api-reference/database#databasetable_list
	 * @return array List of tables with `name`, `engine`, `rows`, and `size` columns.
	 */
	public function table_list() {
		// SHOW TABLE STATUS is the most agnostic way to get Engine and Size info.
		// It works on MyISAM, InnoDB, and practically any MySQL/MariaDB version.
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		LanguageEngine::get()->tick();
		$site_id              = LanguageEngine::get()->get_site_id();
		$prefix               = ( $site_id && is_multisite() ) ? $this->wpdb->get_blog_prefix( $site_id ) : $this->wpdb->get_blog_prefix();
		$query                = $this->wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $this->wpdb->esc_like( $prefix ) . '%' );
		$this->wpdb_queries[] = $this->wpdb->remove_placeholder_escape( $query );
		$raw_tables           = $this->wpdb->get_results( $query );
		LanguageEngine::get()->tick();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$tables = array();
		foreach ( $raw_tables as $table ) {
			// Calculate approximate size in MB.
			$size_bytes = (int) $table->Data_length + (int) $table->Index_length;
			$size_mb    = round( $size_bytes / 1024 / 1024, 2 );

			$tables[] = array(
				'name'   => $table->Name,
				'engine' => $table->Engine,
				'rows'   => (int) $table->Rows,
				'size'   => $size_mb . ' MB',
			);
		}
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		// Table visualization data.
		LanguageEngine::get()->begin_visualization_group()->add_visualization(
			array(
				'type'  => 'table',
				'title' => 'Database Tables',
				'data'  => $tables,
			)
		);

		// Graphical visualization of tables by total rows (pie chart).
		// We limit the graph to the top 15 largest tables to avoid clutter.
		$top_tables = $tables;
		usort(
			$top_tables,
			function ( $a, $b ) {
				return $b['rows'] <=> $a['rows'];
			}
		);
		$top_tables = array_slice( $top_tables, 0, 15 );

		LanguageEngine::get()->add_visualization(
			array(
				'type'  => 'graph',
				'title' => 'Graph: Tables by Row Count',
				'data'  => array(
					'$schema'     => 'https://vega.github.io/schema/vega-lite/v6.json',
					'description' => 'Tables distribution by rows',
					'width'       => 'container',
					'height'      => 300,
					'padding'     => 20,
					'data'        => array(
						'values' => $top_tables,
					),
					'mark'        => array(
						'type'    => 'arc',
						'tooltip' => true,
					),
					'encoding'    => array(
						'theta' => array(
							'field' => 'rows',
							'type'  => 'quantitative',
						),
						'color' => array(
							'field'  => 'name',
							'type'   => 'nominal',
							'legend' => array(
								'title'         => 'Table Name',
								'titleFontSize' => 14,
								'labelFontSize' => 13,
								'labelFont'     => 'Cascadia Mono, monospace',
								'titleFont'     => 'Roboto Slab, serif',
							),
						),
					),
				),
			)
		);

		// Graphical visualization of tables by table size (pie chart).
		$top_tables = $tables;
		usort(
			$top_tables,
			function ( $a, $b ) {
				// Extract numeric size for sorting (remove ' MB' suffix).
				$size_a = (float) str_replace( ' MB', '', $a['size'] );
				$size_b = (float) str_replace( ' MB', '', $b['size'] );
				return $size_b <=> $size_a;
			}
		);
		$top_tables = array_slice( $top_tables, 0, 15 );

		// Prepare data for the graph (convert string size to float).
		$graph_data = array_map(
			function ( $t ) {
				$t['size'] = (float) str_replace( ' MB', '', $t['size'] );
				return $t;
			},
			$top_tables
		);

		LanguageEngine::get()->add_visualization(
			array(
				'type'  => 'graph',
				'title' => 'Graph: Tables by Size (MB)',
				'data'  => array(
					'$schema'     => 'https://vega.github.io/schema/vega-lite/v6.json',
					'description' => 'Tables distribution by size',
					'width'       => 'container',
					'height'      => 300,
					'padding'     => 20,
					'data'        => array(
						'values' => $graph_data,
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
							'field'  => 'name',
							'type'   => 'nominal',
							'legend' => array(
								'title'         => 'Table Name',
								'titleFontSize' => 14,
								'labelFontSize' => 13,
								'labelFont'     => 'Cascadia Mono, monospace',
								'titleFont'     => 'Roboto Slab, serif',
							),
						),
					),
				),
			)
		);

		return $tables;
	}

	/**
	 * Gets detailed column and index specifications for a specific database table.
	 *
	 * The table name must be specified without prefix. Renders column and index visualizations to the console.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * Database.table_details('posts')
	 * ```
	 *
	 * ```elscript
	 * Database.table_details('users')
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/database#databasetable_details
	 *
	 * @param string $table_name The name of the table (without prefix).
	 * @return array Detailed information about the table's columns and indexes.
	 * @throws \Exception If the table does not exist or if the table name is invalid.
	 */
	public function table_details( $table_name ) {
		// Validate table name structure (alphanumeric and underscores only).
		$clean_table = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $table_name );

		if ( empty( $clean_table ) || $clean_table !== $table_name ) {
			throw new \Exception( esc_html( "Invalid table name: {$table_name}" ) );
		}

		$site_id   = LanguageEngine::get()->get_site_id();
		$is_global = in_array( $clean_table, $this->wpdb->global_tables, true );

		if ( is_multisite() && ! empty( $this->wpdb->ms_global_tables ) ) {
			$is_global = $is_global || in_array( $clean_table, $this->wpdb->ms_global_tables, true );
		}

		if ( $is_global ) {
			$prefix = $this->wpdb->base_prefix;
		} else {
			$prefix = ( $site_id && is_multisite() ) ? $this->wpdb->get_blog_prefix( $site_id ) : $this->wpdb->get_blog_prefix();
		}

		$prefix       = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $prefix );
		$target_table = $prefix . $clean_table;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		LanguageEngine::get()->tick();
		$columns_query        = "SHOW FULL COLUMNS FROM `{$target_table}`";
		$indexes_query        = "SHOW INDEXES FROM `{$target_table}`";
		$this->wpdb_queries[] = $columns_query;
		$this->wpdb_queries[] = $indexes_query;
		$columns              = $this->wpdb->get_results( $columns_query );
		$indexes              = $this->wpdb->get_results( $indexes_query );
		LanguageEngine::get()->tick();
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		if ( empty( $columns ) ) {
			throw new \Exception( esc_html( "Table does not exist: {$target_table}" ) );
		}

		// Table visualization data for columns.
		LanguageEngine::get()->begin_visualization_group()->add_visualization(
			array(
				'type'  => 'table',
				'title' => "Table Details: {$target_table} - Columns",
				'data'  => $columns,
			)
		);

		// Table visualization data for indexes.
		LanguageEngine::get()->add_visualization(
			array(
				'type'  => 'table',
				'title' => "Table Details: {$target_table} - Indexes",
				'data'  => $indexes,
			)
		);

		return array(
			'columns' => $columns,
			'indexes' => $indexes,
		);
	}
}
