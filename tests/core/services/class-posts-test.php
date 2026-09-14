<?php

// phpcs:ignoreFile

use ExpressionLab\Core\LanguageEngine;
use ExpressionLab\Core\Services\Database;
use ExpressionLab\Core\Services\Posts;
use ExpressionLab\Core\Models\Post;

class PostsTest extends WP_UnitTestCase {

	/**
	 * Posts instance.
	 *
	 * @var Posts
	 */
	private $posts;

	/**
	 * Database instance.
	 *
	 * @var Database
	 */
	private $database;

	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'SQLite3' ) ) {
			$this->markTestSkipped( 'SQLite3 extension is required.' );
		}

		LanguageEngine::get()->reset();

		$this->database = new Database();
		$this->posts    = new Posts( $this->database );
	}

	public function test_get_by_id_returns_post_model() {
		$post_id = $this->factory->post->create( array(
			'post_title'   => 'Sample Post',
			'post_content' => 'Hello World Content',
			'post_status'  => 'publish',
		) );

		$result = $this->posts->get( $post_id );

		$this->assertInstanceOf( Post::class, $result );
		$this->assertEquals( $post_id, $result->ID );
		$this->assertEquals( 'Sample Post', $result->title );
		$this->assertEquals( 'publish', $result->status );
	}

	public function test_get_by_slug_returns_post_model() {
		$post_id = $this->factory->post->create( array(
			'post_title' => 'Unique Slug Post',
			'post_name'  => 'unique-slug-test',
		) );

		$result = $this->posts->get( 'unique-slug-test' );

		$this->assertInstanceOf( Post::class, $result );
		$this->assertEquals( $post_id, $result->ID );
	}

	public function test_get_by_permalink_returns_post_model() {
		$post_id = $this->factory->post->create( array(
			'post_title' => 'Permalink Post',
			'post_name'  => 'permalink-post-test',
		) );

		$permalink = get_permalink( $post_id );
		$result    = $this->posts->get( $permalink );

		$this->assertInstanceOf( Post::class, $result );
		$this->assertEquals( $post_id, $result->ID );
	}

	public function test_get_by_relative_url_returns_post_model() {
		$post_id = $this->factory->post->create( array(
			'post_title' => 'Relative URL Post',
			'post_name'  => 'relative-url-post-test',
		) );

		$permalink = get_permalink( $post_id );
		$path      = wp_parse_url( $permalink, PHP_URL_PATH );
		$result    = $this->posts->get( $path );

		$this->assertInstanceOf( Post::class, $result );
		$this->assertEquals( $post_id, $result->ID );
	}

	public function test_get_nonexistent_returns_null() {
		$this->assertNull( $this->posts->get( 999999 ) );
	}

	public function test_query_builder_where_get() {
		$this->factory->post->create( array(
			'post_title' => 'Post A',
			'post_type'  => 'page',
		) );

		$this->factory->post->create( array(
			'post_title' => 'Post B',
			'post_type'  => 'post',
		) );

		$pages = $this->posts->where( 'post_type', 'page' )->get();

		$this->assertIsArray( $pages );
		$this->assertNotEmpty( $pages );
		foreach ( $pages as $page ) {
			$this->assertEquals( 'page', $page->post_type );
		}
	}

	public function test_post_metadata_operations() {
		$post_id = $this->factory->post->create();
		$post    = $this->posts->get( $post_id );

		$this->assertNotNull( $post );
		$this->assertNotNull( $post->meta );

		// Set and get meta
		$post->meta->set( 'custom_key', 'custom_value' );
		$this->assertEquals( 'custom_value', $post->meta->get( 'custom_key' ) );

		// Delete meta
		$post->meta->delete( 'custom_key' );
		$this->assertEmpty( $post->meta->get( 'custom_key' ) );
	}

	public function test_post_model_get_permalink_and_get_terms() {
		$post_id = $this->factory->post->create( array( 'post_title' => 'Test Post With Categories' ) );
		$term_id = $this->factory->term->create( array( 'taxonomy' => 'category', 'name' => 'Test Category' ) );
		wp_set_post_terms( $post_id, array( $term_id ), 'category' );

		$post = $this->posts->get( $post_id );
		$this->assertNotNull( $post );
		$this->assertNotEmpty( $post->get_permalink() );

		$terms = $post->get_terms( 'category' );
		$this->assertIsArray( $terms );
		$this->assertNotEmpty( $terms );
		$this->assertEquals( 'Test Category', $terms[0]['name'] );
	}

	public function test_types_and_statuses() {
		$types = $this->posts->types();
		$this->assertContains( 'post', $types );
		$this->assertContains( 'page', $types );

		$statuses = $this->posts->statuses();
		$this->assertContains( 'publish', $statuses );
		$this->assertContains( 'draft', $statuses );
	}

	public function test_stats_visualization() {
		$this->factory->post->create( array( 'post_type' => 'post', 'post_status' => 'publish' ) );
		$stats = $this->posts->stats();

		$this->assertIsArray( $stats );
		$this->assertNotEmpty( $stats );
	}

	public function test_query_builder_count_and_to_sql() {
		$this->factory->post->create_many( 4, array( 'post_type' => 'special_cpt' ) );

		$count = $this->posts->where( 'post_type', 'special_cpt' )->count();
		$this->assertEquals( 4, $count );

		$sql = $this->posts->where( 'post_type', 'special_cpt' )->order_by( 'ID', 'DESC' )->to_sql();
		$this->assertStringContainsString( 'SELECT * FROM', $sql );
		$this->assertStringContainsString( "post_type = 'special_cpt'", $sql );
		$this->assertStringContainsString( 'ORDER BY ID DESC', $sql );

		$sql_count = $this->posts->where( 'post_type', 'special_cpt' )->to_sql( true );
		$this->assertStringContainsString( 'SELECT COUNT(*) FROM', $sql_count );
	}

	public function test_postmeta_query_builder_where_get() {
		$post_id = $this->factory->post->create();
		$post    = $this->posts->get( $post_id );

		update_post_meta( $post_id, '_wp_page_template', 'default' );
		update_post_meta( $post_id, '_wp_custom_setting', 'enabled' );
		update_post_meta( $post_id, 'regular_meta', 'hello' );

		$results = $post->meta->where( 'meta_key', 'like', '_wp_%' )->get();

		$this->assertIsArray( $results );
		$this->assertCount( 2, $results );

		$keys = array_map( function( $row ) {
			return $row->meta_key;
		}, $results );

		$this->assertContains( '_wp_page_template', $keys );
		$this->assertContains( '_wp_custom_setting', $keys );
		$this->assertNotContains( 'regular_meta', $keys );
	}

	public function test_postmeta_query_builder_strictly_scopes_to_post() {
		$post_1_id = $this->factory->post->create();
		$post_2_id = $this->factory->post->create();

		$post_1 = $this->posts->get( $post_1_id );
		$post_2 = $this->posts->get( $post_2_id );

		update_post_meta( $post_1_id, '_custom_key', 'val_post_1' );
		update_post_meta( $post_2_id, '_custom_key', 'val_post_2' );

		$results_1 = $post_1->meta->where( 'meta_key', '_custom_key' )->get();
		$this->assertCount( 1, $results_1 );
		$this->assertEquals( 'val_post_1', $results_1[0]->meta_value );

		$results_2 = $post_2->meta->where( 'meta_key', '_custom_key' )->get();
		$this->assertCount( 1, $results_2 );
		$this->assertEquals( 'val_post_2', $results_2[0]->meta_value );
	}

	public function test_postmeta_query_builder_count_and_to_sql() {
		$post_id = $this->factory->post->create();
		$post    = $this->posts->get( $post_id );

		update_post_meta( $post_id, '_wp_one', '1' );
		update_post_meta( $post_id, '_wp_two', '2' );
		update_post_meta( $post_id, '_wp_three', '3' );

		$count = $post->meta->where( 'meta_key', 'like', '_wp_%' )->count();
		$this->assertEquals( 3, $count );

		$sql = $post->meta->where( 'meta_key', 'like', '_wp_%' )->to_sql();
		$this->assertStringContainsString( 'SELECT * FROM', $sql );
		$this->assertStringContainsString( "post_id = '{$post_id}'", $sql );
		$this->assertStringContainsString( "meta_key LIKE '_wp_%'", $sql );
		$this->assertStringNotContainsString( '{', $sql );

		$sql_count = $post->meta->where( 'meta_key', 'like', '_wp_%' )->to_sql( true );
		$this->assertStringContainsString( 'SELECT COUNT(*) FROM', $sql_count );
		$this->assertStringContainsString( "post_id = '{$post_id}'", $sql_count );
		$this->assertStringContainsString( "meta_key LIKE '_wp_%'", $sql_count );
		$this->assertStringNotContainsString( '{', $sql_count );
	}

	public function test_expression_language_postmeta_where_get() {
		$post_id = $this->factory->post->create();

		update_post_meta( $post_id, '_wp_attached_file', 'image.png' );
		update_post_meta( $post_id, '_wp_page_template', 'single.php' );
		update_post_meta( $post_id, 'custom_secret', 'secret_value' );

		$engine = LanguageEngine::get()->reset();
		$result = $engine->evaluate( "Posts.get({$post_id}).meta.where('meta_key', 'like', '_wp_%').get()" );

		$this->assertIsArray( $result['result'] );
		$this->assertCount( 2, $result['result'] );
		$keys = array_map( function( $row ) {
			return $row->meta_key;
		}, $result['result'] );
		$this->assertContains( '_wp_attached_file', $keys );
		$this->assertContains( '_wp_page_template', $keys );

		// Verify to_sql via expression language returns literal % without placeholder escapes
		$to_sql_result = $engine->evaluate( "Posts.get({$post_id}).meta.where('meta_key', 'like', '_wp_%').to_sql()" );
		$this->assertStringContainsString( "meta_key LIKE '_wp_%'", $to_sql_result['result'] );
		$this->assertStringNotContainsString( '{', $to_sql_result['result'] );
	}
}
