<?php

// phpcs:ignoreFile

use ExpressionLab\Core\LanguageEngine;
use ExpressionLab\Core\Services\Database;
use ExpressionLab\Tests\Fixtures\DatabaseSeeder;

class DatabaseTest extends WP_UnitTestCase {
	/**
	 * @var Database $database
	 */
	private $database;
	private $reflection_build_recursive_where;
	private $reflection_last_results;
	private $reflection_query_history;

	public static function wpSetUpBeforeClass( $factory ) {
		DatabaseSeeder::seed_posts( 5000 );
	}

	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'SQLite3' ) ) {
			$this->markTestSkipped( 'SQLite3 extension is required.' );
		}

		LanguageEngine::get()->reset();

		$this->database = new Database();

		// Use Reflection to access the private method 'build_recursive_where'
		$reflection = new ReflectionClass( $this->database );
		$this->reflection_build_recursive_where = $reflection->getMethod( 'build_recursive_where' );
		
		// Use Reflection to modify 'last_results' for $prev testing
		$this->reflection_last_results = $reflection->getProperty( 'last_results' );

		// Use Reflection to modify 'query_history' for table reference testing
		$this->reflection_query_history = $reflection->getProperty( 'query_history' );
	}

	/**
	 * Helper to assert generated SQL and values.
	 */
	private function assertWhereClause( array $conditions, string $expected_sql, array $expected_values ) {
		$flat_values = array();
		$sql = $this->reflection_build_recursive_where->invokeArgs( $this->database, array( $conditions, &$flat_values ) );
		
		$this->assertEquals( $expected_sql, $sql );
		$this->assertEquals( $expected_values, $flat_values );
	}

	public function test_simple_equality() {
		$conditions = array( 'post_status' => 'publish' );
		$this->assertWhereClause( $conditions, 'post_status = %s', array( 'publish' ) );
	}

	public function test_greater_than() {
		$conditions = array( 'ID' => array( '$gt' => 100 ) );
		$this->assertWhereClause( $conditions, 'ID > %s', array( 100 ) );
	}

	public function test_not_equal() {
		$conditions = array( 'post_status' => array( '$ne' => 'trash' ) );
		$this->assertWhereClause( $conditions, 'post_status != %s', array( 'trash' ) );
	}

	public function test_date_range_between() {
		$conditions = array( 'post_date' => array( '$between' => array( '2025-01-01', '2025-12-31' ) ) );
		$this->assertWhereClause( $conditions, 'post_date BETWEEN %s AND %s', array( '2025-01-01', '2025-12-31' ) );
	}

	public function test_is_null() {
		$conditions = array( 'meta_value' => array( '$isNull' => true ) );
		$this->assertWhereClause( $conditions, 'meta_value IS NULL', array() );
	}
	
	public function test_is_not_null_with_and() {
		// Associative array = AND group
		$conditions = array( 
			'post_content' => array( '$isNotNull' => true ),
			'post_status'  => 'publish'
		);
		
		// Order depends on internal logic, usually respecting array definition order for predictable SQL
		$this->assertWhereClause( $conditions, 'post_content IS NOT NULL AND post_status = %s', array( 'publish' ) );
	}

	public function test_in_array_implicit() {
		$conditions = array( 'ID' => array( 1, 15, 42 ) );
		$this->assertWhereClause( $conditions, 'ID IN (%s,%s,%s)', array( 1, 15, 42 ) );
	}

	public function test_explicit_in_operator() {
		$conditions = array( 'ID' => array( '$in' => array( 1, 15, 42 ) ) );
		$this->assertWhereClause( $conditions, 'ID IN (%s,%s,%s)', array( 1, 15, 42 ) );
	}

	public function test_not_in_operator() {
		$conditions = array( 'ID' => array( '$notIn' => array( 1, 2, 3 ) ) );
		$this->assertWhereClause( $conditions, 'ID NOT IN (%s,%s,%s)', array( 1, 2, 3 ) );
	}

	public function test_empty_in_generates_false_expression() {
		$conditions = array( 'ID' => array( '$in' => array() ) );
		$this->assertWhereClause( $conditions, '0 = 1', array() );

		$conditions_implicit = array( 'ID' => array() );
		$this->assertWhereClause( $conditions_implicit, '0 = 1', array() );
	}

	public function test_empty_not_in_generates_true_expression() {
		$conditions = array( 'ID' => array( '$notIn' => array() ) );
		$this->assertWhereClause( $conditions, '1 = 1', array() );
	}

	public function test_like_search() {
		$conditions = array( 'post_title' => array( '$like' => '%Update%' ) );
		$this->assertWhereClause( $conditions, 'post_title LIKE %s', array( '%Update%' ) );
	}

	public function test_not_like_search() {
		$conditions = array( 'post_title' => array( '$notLike' => '%Draft%' ) );
		$this->assertWhereClause( $conditions, 'post_title NOT LIKE %s', array( '%Draft%' ) );
	}

	public function test_not_like_search_multiple_patterns() {
		$conditions = array( 'post_title' => array( '$notLike' => array( '%Draft%', '%Trash%' ) ) );
		$this->assertWhereClause( $conditions, 'post_title NOT LIKE %s AND post_title NOT LIKE %s', array( '%Draft%', '%Trash%' ) );
	}

	public function test_complex_or_logic() {
		// Indexed array = OR group
		// [{...}, {...}] syntax in expression language
		$conditions = array(
			array( 'ID' => array( '$lt' => 10 ) ),
			array( 'post_status' => 'draft' )
		);
		
		// Logic: (ID < 10) OR (post_status = 'draft')
		// The method wraps nested parts in parenthesis
		$expected_sql = '(ID < %s) OR (post_status = %s)';
		$this->assertWhereClause( $conditions, $expected_sql, array( 10, 'draft' ) );
	}

	public function test_nested_recursive_logic() {
		// (post_type = 'post' AND post_status = 'publish') OR (post_type = 'page' AND post_status = 'draft')
		$conditions = array(
			array( 'post_type' => 'post', 'post_status' => 'publish' ),
			array( 'post_type' => 'page', 'post_status' => 'draft' )
		);
		
		$expected_sql = "(post_type = %s AND post_status = %s) OR (post_type = %s AND post_status = %s)";
		$expected_values = array( 'post', 'publish', 'page', 'draft' );
		
		$this->assertWhereClause( $conditions, $expected_sql, $expected_values );
	}

	public function test_cross_reference_prev_id() {
		// Mock previous results in the object state
		$history = array(
			array(
				'results' => array(
					array( 'ID' => 100 ),
					array( 'ID' => 101 ),
					array( 'ID' => 100 ), // Duplicate to test unique filter
				),
			),
		);
		$this->reflection_query_history->setValue( $this->database, $history );

		// Expression: { 'parent_id': '$prev.ID' }
		$conditions = array( 'parent_id' => '$prev.ID' );
		
		// It should extract IDs and build an IN clause
		$this->assertWhereClause( $conditions, 'parent_id IN (%s,%s)', array( 100, 101 ) );
	}

	public function test_cross_reference_prev_id_empty_fallback() {
		// Mock empty previous results
		$this->reflection_query_history->setValue( $this->database, array() );

		$conditions = array( 'parent_id' => '$prev.ID' );
		
		// Should default to -1 to avoid empty list SQL errors
		$this->assertWhereClause( $conditions, 'parent_id IN (%s)', array( -1 ) );
	}

	public function test_cross_reference_named_table() {
		// Mock query history with multiple tables
		$history = array(
			array(
				'table'     => 'wp_users',
				'raw_table' => 'users',
				'results'   => array(
					array(
						'ID'         => 500,
						'user_login' => 'admin',
					),
				),
			),
			array(
				'table'     => 'wp_posts',
				'raw_table' => 'posts',
				'results'   => array(
					array(
						'ID'          => 100,
						'post_author' => 500,
					),
					array(
						'ID'          => 101,
						'post_author' => 500,
					),
				),
			),
		);
		$this->reflection_query_history->setValue( $this->database, $history );

		// Reference users table explicitly (looking up raw_table 'users')
		$conditions = array( 'author_id' => '$users.ID' );
		$this->assertWhereClause( $conditions, 'author_id IN (%s)', array( 500 ) );

		// Reference posts table implicitly by suffix (wp_posts matches posts)
		$conditions2 = array( 'id_filter' => '$posts.ID' );
		$this->assertWhereClause( $conditions2, 'id_filter IN (%s,%s)', array( 100, 101 ) );
	}

	public function test_column_name_sanitization_security() {
		// Ensure dangerous characters are stripped from keys
		$conditions = array( 'user; DROP TABLE;' => '1' );
		
		// Cleaned: userDROPTABLE
		$this->assertWhereClause( $conditions, 'userDROPTABLE = %s', array( '1' ) );
	}

	public function test_column_name_only_invalid_chars_is_skipped() {
		// Column consisting entirely of invalid characters should be skipped without breaking SQL
		$conditions = array(
			';!@#'        => '1',
			'post_status' => 'publish',
		);
		
		$this->assertWhereClause( $conditions, 'post_status = %s', array( 'publish' ) );
	}

	public function test_invalid_table_name_throws_exception() {
		$this->expectException( Exception::class );
		$this->database->mirror( 'posts; DROP TABLE wp_users;--' );
	}

	public function test_table_details_invalid_name_throws_exception() {
		$this->expectException( Exception::class );
		$this->database->table_details( 'posts; DROP TABLE wp_users;--' );
	}

	public function test_table_details_global_table() {
		$details = $this->database->table_details( 'users' );
		$this->assertIsArray( $details );
		$this->assertArrayHasKey( 'columns', $details );
		$this->assertArrayHasKey( 'indexes', $details );
	}

	public function test_multi_column_associative_orderby() {
		$this->factory->post->create_many( 3, array(
			'post_type'   => 'post',
			'post_status' => 'publish',
		) );

		$options = array(
			'orderby' => array(
				'post_date' => 'DESC',
				'ID'        => 'ASC',
			),
			'limit'   => 3,
		);

		$this->database->mirror( 'posts', array( 'post_type' => 'post' ), $options );
		$history = $this->reflection_query_history->getValue( $this->database );
		$last_sql = end( $history )['sql'];

		$this->assertStringContainsString( 'ORDER BY post_date DESC, ID ASC', $last_sql );
	}

	/**
	 * Test the new $options parameter in mirror() method for SELECT, ORDER BY, and LIMIT.
	 */
	public function test_mirror_options_select_limit_orderby() {
		// Setup: Create 5 posts
		$post_ids = $this->factory->post->create_many( 5, array(
			'post_type'   => 'post',
			'post_status' => 'publish',
		) );

		// We expect IDs to be returned in DESC order if we request it.
		// Sort post_ids descending to match expectation.
		rsort( $post_ids );

		// Use specific options
		$options = array(
			'select'  => array( 'ID', 'post_title' ),
			'orderby' => array( 'ID' => 'DESC' ),
			'limit'   => 3,
		);

		// Perform mirroring on 'posts' table
		// Note: mirror returns $this, but we check internal state via reflection to verify query options
		$this->database->mirror( 'posts', array( 'post_type' => 'post' ), $options );

		// Retrieve results set in last_results property
		$results = $this->reflection_last_results->getValue( $this->database );

		// Assert Limit
		$this->assertCount( 3, $results );

		// Assert Order (DESC)
		$this->assertEquals( $post_ids[0], $results[0]['ID'] );
		$this->assertEquals( $post_ids[1], $results[1]['ID'] );
		$this->assertEquals( $post_ids[2], $results[2]['ID'] );

		// Assert Select (Column filtering)
		$first_row = $results[0];
		$this->assertArrayHasKey( 'ID', $first_row );
		$this->assertArrayHasKey( 'post_title', $first_row );
		// These columns exist in wp_posts but shouldn't be selected
		$this->assertArrayNotHasKey( 'post_content', $first_row );
		$this->assertArrayNotHasKey( 'post_date', $first_row );
	}

	/**
	 * Test the OFFSET option works correctly.
	 */
	public function test_mirror_option_offset() {
		// Setup: Create 5 posts to ensure consistent order
		// We use a specific post type to avoid interference
		$post_ids = $this->factory->post->create_many( 5, array( 'post_type' => 'offset_test' ) );
		sort( $post_ids ); // Ensure ASC order for comparison

		$options = array(
			'orderby' => array( 'ID' => 'ASC' ),
			'limit'   => 2,
			'offset'  => 2,
		);

		$this->database->mirror( 'posts', array( 'post_type' => 'offset_test' ), $options );
		$results = $this->reflection_last_results->getValue( $this->database );

		// Should fetch the 3rd and 4th posts (index 2 and 3)
		$this->assertCount( 2, $results );
		$this->assertEquals( $post_ids[2], $results[0]['ID'] );
		$this->assertEquals( $post_ids[3], $results[1]['ID'] );
	}

	/**
	 * Test select option as string (comma separated with extra spaces and commas).
	 */
	public function test_mirror_option_select_string() {
		$this->factory->post->create( array( 'post_title' => 'String Select', 'post_type' => 'select_test' ) );

		$options = array(
			'select' => 'ID, , post_title, ',
		);

		$this->database->mirror( 'posts', array( 'post_type' => 'select_test' ), $options );
		$results = $this->reflection_last_results->getValue( $this->database );

		$this->assertNotEmpty( $results );
		$this->assertArrayHasKey( 'ID', $results[0] );
		$this->assertArrayHasKey( 'post_title', $results[0] );
		$this->assertArrayNotHasKey( 'post_content', $results[0] );
	}

	/**
	 * Test mirror without limit on dataset exceeding buffer auto-caps to buffer and emits a warning.
	 */
	public function test_mirror_without_limit_auto_caps_to_buffer_and_emits_warning() {
		$this->database->buffer( 9 );
		$this->factory->post->create_many( 15, array( 'post_type' => 'auto_cap_test' ) );

		$this->database->mirror( 'posts', array( 'post_type' => 'auto_cap_test' ) );
		$results = $this->reflection_last_results->getValue( $this->database );

		$this->assertCount( 9, $results );

		$messages = LanguageEngine::get()->get_messages();
		$this->assertNotEmpty( $messages );

		$has_warning = false;
		foreach ( $messages as $msg ) {
			if ( 'warning' === $msg['type'] && strpos( $msg['text'], 'capped to 9 rows' ) !== false ) {
				$has_warning = true;
				break;
			}
		}

		$this->assertTrue( $has_warning, 'Expected a warning about auto-capping to buffer limit.' );
	}

	/**
	 * Test that specifying a LIMIT within buffer size succeeds even when table total exceeds buffer.
	 */
	public function test_mirror_with_limit_on_dataset_larger_than_buffer_succeeds() {
		$this->database->buffer(5);
		$this->factory->post->create_many( 15, array( 'post_type' => 'limit_large_test' ) );

		// We ask for limit: 3, which is <= 5 buffer limit, even though table has 15 rows.
		$this->database->mirror(
			'posts',
			array( 'post_type' => 'limit_large_test' ),
			array( 'limit' => 3 )
		);

		$results = $this->reflection_last_results->getValue( $this->database );
		$this->assertCount( 3, $results );
	}

	/**
	 * Test that specifying a LIMIT exceeding the buffer size triggers safety.
	 */
	public function test_mirror_with_limit_exceeding_buffer_triggers_safety() {
		$this->database->buffer(5);
		$this->factory->post->create_many( 15, array( 'post_type' => 'limit_exceed_test' ) );

		$this->expectException( Exception::class );
		$this->database->mirror(
			'posts',
			array( 'post_type' => 'limit_exceed_test' ),
			array( 'limit' => 10 )
		);
	}

	/**
	 * Test cumulative buffer tracking across multiple mirror operations.
	 */
	public function test_cumulative_buffer_tracking_with_multiple_mirrors() {
		$this->database->buffer(10);
		$this->factory->post->create_many( 20, array( 'post_type' => 'cumulative_test' ) );

		// First mirror loads 6 rows (buffer has 6 used, 4 remaining).
		$this->database->mirror(
			'posts',
			array( 'post_type' => 'cumulative_test' ),
			array( 'limit' => 6 )
		);

		// Second mirror asks for 6 rows (6 + 6 = 12 > 10) -> must trigger safety.
		$this->expectException( Exception::class );
		$this->database->mirror(
			'posts',
			array( 'post_type' => 'cumulative_test' ),
			array( 'limit' => 6 )
		);
	}

	/**
	 * Test querying the bootstrapped 5,000+ deterministic dataset with limit sampling.
	 */
	public function test_mirror_with_limit_on_bootstrapped_large_dataset() {
		// Table has at least 5,000 deterministic posts. Default buffer is 1000.
		// Asking for limit 5 must succeed without Memory Safety exception.
		$this->database->mirror(
			'posts',
			array( 'post_status' => 'publish' ),
			array( 'limit' => 5, 'orderby' => array( 'ID' => 'DESC' ) )
		);

		$results = $this->reflection_last_results->getValue( $this->database );
		$this->assertCount( 5, $results );
	}

	/**
	 * Test querying the bootstrapped 5,000+ deterministic dataset without limit auto-caps to default buffer 1,000.
	 */
	public function test_mirror_without_limit_on_bootstrapped_large_dataset_auto_caps_to_1000() {
		// Table has at least 5,000 deterministic posts. No limit specified -> auto-caps to 1000 and emits warning.
		$this->database->mirror( 'posts', array( 'post_status' => 'publish' ) );

		$results = $this->reflection_last_results->getValue( $this->database );
		$this->assertCount( 1000, $results );

		$messages = LanguageEngine::get()->get_messages();
		$has_warning = false;
		foreach ( $messages as $msg ) {
			if ( 'warning' === $msg['type'] && strpos( $msg['text'], 'capped to 1,000 rows' ) !== false ) {
				$has_warning = true;
				break;
			}
		}
		$this->assertTrue( $has_warning );
	}

	/**
	 * Test sequential mirrors with different select columns properly recreates SQLite schema.
	 */
	public function test_sequential_mirrors_with_different_select_columns_recreates_sqlite_table() {
		// First mirror: 23 columns
		$this->database->mirror(
			'posts',
			array( 'post_type' => 'post' ),
			array( 'limit' => 5 )
		);

		// Second mirror on same table: only 2 columns
		$this->database->mirror(
			'posts',
			array( 'post_type' => 'page' ),
			array(
				'select' => array( 'ID', 'post_title' ),
				'limit'  => 3,
			)
		);

		$results = $this->database->fetch( null, false );

		$this->assertCount( 3, $results );
		$this->assertArrayHasKey( 'ID', $results[0] );
		$this->assertArrayHasKey( 'post_title', $results[0] );
		$this->assertArrayNotHasKey( 'post_content', $results[0] );
		$this->assertArrayNotHasKey( 'post_author', $results[0] );
	}

	/**
	 * Test sequential mirrors replaces data in SQLite table instead of accumulating dirty state.
	 */
	public function test_sequential_mirrors_replaces_data_in_sqlite_query() {
		// First mirror: post_type = 'post'
		$this->database->mirror(
			'posts',
			array( 'post_type' => 'post' ),
			array( 'limit' => 5 )
		);

		// Second mirror: post_type = 'page'
		$this->database->mirror(
			'posts',
			array( 'post_type' => 'page' ),
			array( 'limit' => 3 )
		);

		$sqlite_results = $this->database->query( 'SELECT DISTINCT post_type FROM wp_posts' );

		$this->assertCount( 1, $sqlite_results );
		$this->assertEquals( 'page', $sqlite_results[0]['post_type'] );
	}

	/**
	 * Test mirror with 'as' alias option creates custom named tables and allows UNION ALL queries.
	 */
	public function test_mirror_with_as_alias_option_creates_custom_named_tables() {
		$this->database->mirror(
			'posts',
			array( 'post_type' => 'post' ),
			array(
				'as'    => 'my_posts',
				'limit' => 5,
			)
		);

		$this->database->mirror(
			'posts',
			array( 'post_type' => 'page' ),
			array(
				'as'    => 'my_pages',
				'limit' => 3,
			)
		);

		// Both tables must coexist simultaneously in SQLite
		$union_results = $this->database->query(
			"SELECT 'post' as tipo, ID, post_title FROM my_posts UNION ALL SELECT 'page' as tipo, ID, post_title FROM my_pages"
		);

		$this->assertCount( 8, $union_results );

		// Test fetch with alias
		$posts_fetched = $this->database->fetch( 'my_posts', false );
		$this->assertCount( 5, $posts_fetched );

		$pages_fetched = $this->database->fetch( 'my_pages', false );
		$this->assertCount( 3, $pages_fetched );
	}

	/**
	 * Test mirror with invalid alias throws exception.
	 */
	public function test_mirror_with_invalid_alias_throws_exception() {
		$this->expectException( Exception::class );
		$this->database->mirror(
			'posts',
			array(),
			array( 'as' => 'invalid table name!' )
		);
	}

	/**
	 * Test query with CTE (WITH clause).
	 */
	public function test_query_with_cte_and_explain() {
		$this->database->mirror(
			'posts',
			array( 'post_type' => 'post' ),
			array(
				'as'    => 'cte_posts',
				'limit' => 4,
			)
		);

		$cte_results = $this->database->query(
			'WITH sample_cte AS (SELECT ID, post_title FROM cte_posts) SELECT * FROM sample_cte'
		);

		$this->assertCount( 4, $cte_results );
	}

	/**
	 * Test memory buffer (memory limit)
	 */
	public function test_memory_safety_buffer_memory_limit() {
		$this->database->buffer(null, 0); // Force an immediate memory limit breach.
		$this->factory->post->create_many( 1, array( 'post_type' => 'buffer_test' ) );
		$this->expectException( Exception::class );
		$this->database->mirror( 'posts', array( 'post_type' => 'buffer_test' ) );
	}

	/**
	 * Test Database::compile generates expected query strings.
	 */
	public function test_compile() {
		global $wpdb;
		$table = $wpdb->posts;

		$sql = $this->database->compile( 'posts', array( 'post_type' => 'page', 'post_status' => 'publish' ) );
		$this->assertEquals( "SELECT * FROM `{$table}` WHERE post_type = 'page' AND post_status = 'publish'", $sql );

		$sql_count = $this->database->compile( 'posts', array( 'post_type' => 'page' ), null, true );
		$this->assertEquals( "SELECT COUNT(*) FROM `{$table}` WHERE post_type = 'page'", $sql_count );

		$sql_options = $this->database->compile(
			'posts',
			array( 'post_status' => 'publish' ),
			array(
				'select'  => array( 'ID', 'post_title' ),
				'orderby' => array( 'ID' => 'DESC' ),
				'limit'   => 5,
				'offset'  => 10,
			)
		);
		$this->assertEquals( "SELECT ID, post_title FROM `{$table}` WHERE post_status = 'publish' ORDER BY ID DESC LIMIT 5 OFFSET 10", $sql_options );
	}

	/**
	 * Test Database::count returns accurate counts without mirroring.
	 */
	public function test_database_count() {
		$this->factory->post->create_many( 3, array( 'post_type' => 'custom_doc' ) );
		$count = $this->database->count( 'posts', array( 'post_type' => 'custom_doc' ) );
		$this->assertEquals( 3, $count );
	}

	/**
	 * Test Database::get_queries returns queries executed on wpdb.
	 */
	public function test_get_queries() {
		$this->database->flush();
		$this->assertEmpty( $this->database->get_queries() );

		$this->database->count( 'posts', array( 'post_type' => 'post' ) );
		$queries = $this->database->get_queries();
		$this->assertNotEmpty( $queries );
		$this->assertStringContainsString( 'SELECT COUNT(*)', $queries[0] );

		$this->database->mirror( 'posts', array( 'post_type' => 'post' ), array( 'limit' => 2 ) );
		$mirror_queries = $this->database->get_queries();
		$this->assertGreaterThan( count( $queries ), count( $mirror_queries ) );

		// Test chain method call
		$chain_queries = $this->database->mirror( 'users', array(), array( 'limit' => 2 ) )->get_queries();
		$this->assertIsArray( $chain_queries );
		$this->assertNotEmpty( $chain_queries );

		$this->database->flush();
		$this->assertEmpty( $this->database->get_queries() );
	}

	/**
	 * Test Database::get_queries cleans WordPress internal placeholder escapes from LIKE queries.
	 */
	public function test_get_queries_like_query_removes_placeholder_escape() {
		$this->database->flush();
		$queries = $this->database->mirror( 'users', array( 'user_email' => array( '$like' => '%@example.com' ) ), array( 'limit' => 2 ) )->get_queries();
		$this->assertNotEmpty( $queries );
		$has_cleaned_like = false;
		foreach ( $queries as $q ) {
			if ( strpos( $q, 'user_email LIKE' ) !== false ) {
				$this->assertStringNotContainsString( '{', $q );
				$this->assertStringContainsString( "user_email LIKE '%@example.com'", $q );
				$has_cleaned_like = true;
			}
		}
		$this->assertTrue( $has_cleaned_like );
	}

	/**
	 * Test Database::query rejects multi-statement / stacked queries with semicolons.
	 */
	public function test_query_rejects_multi_statement_queries() {
		$this->database->mirror( 'posts', array(), array( 'limit' => 1 ) );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Multi-statement queries are not allowed.' );

		$this->database->query( 'SELECT 1; DROP TABLE wp_posts;' );
	}

	/**
	 * Test recursive WHERE clause depth limit throws exception when exceeded.
	 */
	public function test_build_recursive_where_depth_limit() {
		// Build deeply nested condition array > 50 levels.
		$nested = array( 'post_status' => 'publish' );
		for ( $i = 0; $i < 55; $i++ ) {
			$nested = array( $nested );
		}

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'WHERE clause nesting depth limit exceeded (max: 50).' );

		$flat_values = array();
		$this->reflection_build_recursive_where->invokeArgs( $this->database, array( $nested, &$flat_values ) );
	}

	/**
	 * Test explicit $eq operator in recursive WHERE compilation.
	 */
	public function test_explicit_eq_operator() {
		$conditions = array( 'post_status' => array( '$eq' => 'publish' ) );
		$this->assertWhereClause( $conditions, 'post_status = %s', array( 'publish' ) );
	}
}

