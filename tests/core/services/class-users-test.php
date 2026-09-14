<?php

// phpcs:ignoreFile

use ExpressionLab\Core\Services\Database;
use ExpressionLab\Core\Services\Users;
use ExpressionLab\Core\Models\User;

class UsersTest extends WP_UnitTestCase {

	/**
	 * Users instance.
	 *
	 * @var Users
	 */
	private $users;

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

		$this->database = new Database();
		$this->users    = new Users( $this->database );
	}

	public function test_constructor_sets_current_property() {
		// In a fresh install with no logged-in user, current may be null.
		// The test ensures the property is set (even if null) and no error is thrown.
		$this->assertTrue( property_exists( $this->users, 'current' ) );
	}

	public function test_get_by_id_returns_user_model() {
		$user_id = $this->factory->user->create( array(
			'user_login' => 'testuser_get_by_id',
			'user_email' => 'test_get_by_id@example.com',
		) );

		$result = $this->users->get( $user_id );

		$this->assertInstanceOf( User::class, $result );
		$this->assertEquals( $user_id, $result->ID );
	}

	public function test_get_by_email_returns_user_model() {
		$email   = 'test_get_by_email@example.com';
		$user_id = $this->factory->user->create( array(
			'user_login' => 'testuser_by_email',
			'user_email' => $email,
		) );

		$result = $this->users->get( $email );

		$this->assertInstanceOf( User::class, $result );
		$this->assertEquals( $user_id, $result->ID );
	}

	public function test_get_by_username_returns_user_model() {
		$login   = 'testuser_by_login';
		$user_id = $this->factory->user->create( array(
			'user_login' => $login,
			'user_email' => 'test_get_by_login@example.com',
		) );

		$result = $this->users->get( $login );

		$this->assertInstanceOf( User::class, $result );
		$this->assertEquals( $user_id, $result->ID );
	}

	public function test_get_with_invalid_id_returns_null() {
		$result = $this->users->get( 99999 );
		$this->assertNull( $result );
	}

	public function test_get_with_invalid_string_returns_null() {
		$result = $this->users->get( 'nonexistent_user_xyz' );
		$this->assertNull( $result );
	}

	public function test_get_with_invalid_email_returns_null() {
		$result = $this->users->get( 'nonexistent@example.com' );
		$this->assertNull( $result );
	}

	public function test_build_returns_user_model() {
		$user = $this->users->build();
		$this->assertInstanceOf( User::class, $user );
		$this->assertNull( $user->ID );
	}

	public function test_list_returns_array() {
		$this->factory->user->create_many( 3, array(
			'user_login' => 'list_test_user_',
		) );

		$result = $this->users->list();

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result );
	}

	public function test_list_with_where_condition() {
		$this->factory->user->create( array(
			'user_login' => 'active_user',
			'user_email' => 'active@example.com',
		) );
		$this->factory->user->create( array(
			'user_login' => 'spam_user',
			'user_email' => 'spam@example.com',
		) );

		$result = $this->users->list( array( 'user_login' => 'active_user' ) );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'active_user', $result[0]->user_login );
	}

	public function test_query_builder_where_equality() {
		$login   = 'qb_equality_user_equality';
		$this->factory->user->create( array(
			'user_login' => $login,
			'user_email' => 'qb_equality@example.com',
		) );

		$results = $this->users->where( 'user_login', $login )->get();

		$this->assertCount( 1, $results );
		$this->assertEquals( $login, $results[0]->user_login );
	}

	public function test_query_builder_where_comparison() {
		$this->factory->user->create_many( 3 );

		// Query for users with ID >= 1, which should return all created users.
		$results = $this->users->where( 'ID', '>=', 1 )->get();

		$this->assertNotEmpty( $results );
		foreach ( $results as $row ) {
			$this->assertGreaterThanOrEqual( 1, $row->ID );
		}
	}

	public function test_query_builder_where_like() {
		$this->factory->user->create( array(
			'user_login' => 'like_pattern_user',
			'user_email' => 'like_pattern@example.com',
		) );

		$results = $this->users->where( 'user_login', 'like', '%pattern%' )->get();

		$this->assertCount( 1, $results );
		$this->assertEquals( 'like_pattern_user', $results[0]->user_login );
	}

	public function test_query_builder_where_in() {
		$id1 = $this->factory->user->create();
		$id2 = $this->factory->user->create();

		$results = $this->users->where( 'ID', 'in', array( $id1, $id2 ) )->get();

		$this->assertCount( 2, $results );
	}

	public function test_query_builder_or_where() {
		$this->factory->user->create( array(
			'user_login' => 'or_where_user_a',
			'user_email' => 'or_a@example.com',
		) );
		$this->factory->user->create( array(
			'user_login' => 'or_where_user_b',
			'user_email' => 'or_b@example.com',
		) );

		$results = $this->users
			->where( 'user_login', 'or_where_user_a' )
			->or_where( 'user_login', 'or_where_user_b' )
			->get();

		$this->assertCount( 2, $results );
	}

	public function test_query_builder_order_by() {
		$ids = $this->factory->user->create_many( 3 );
		rsort( $ids );

		$results = $this->users->order_by( 'ID', 'DESC' )->get();

		$this->assertNotEmpty( $results );
		$this->assertEquals( $ids[0], (int) $results[0]->ID );
	}

	public function test_query_builder_limit() {
		$this->factory->user->create_many( 5 );

		$results = $this->users->limit( 2 )->get();

		$this->assertCount( 2, $results );
	}

	public function test_query_builder_offset() {
		$this->factory->user->create_many( 5 );

		$all_results = $this->users->order_by( 'ID', 'ASC' )->get();
		$this->assertGreaterThanOrEqual( 5, count( $all_results ) );

		$limited = $this->users->order_by( 'ID', 'ASC' )->limit( 2 )->offset( 2 )->get();

		$this->assertCount( 2, $limited );
		$this->assertEquals( $all_results[2]->ID, $limited[0]->ID );
		$this->assertEquals( $all_results[3]->ID, $limited[1]->ID );
	}

	public function test_query_builder_get_resets_state() {
		$this->factory->user->create( array(
			'user_login' => 'reset_test_user',
		) );

		// First call should return results.
		$first = $this->users->where( 'user_login', 'reset_test_user' )->get();
		$this->assertCount( 1, $first );

		// Second call without conditions should return all users (state reset).
		$second = $this->users->get();
		$this->assertNotEmpty( $second );
	}

	public function test_user_can_primitive_capability() {
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$sub_id   = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		$admin_user = $this->users->get( $admin_id );
		$sub_user   = $this->users->get( $sub_id );

		$this->assertTrue( $admin_user->can( 'manage_options' ) );
		$this->assertFalse( $sub_user->can( 'manage_options' ) );
		$this->assertTrue( $sub_user->can( 'read' ) );
	}

	public function test_user_can_meta_capability_with_args() {
		$author_a_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$author_b_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$editor_id   = $this->factory->user->create( array( 'role' => 'editor' ) );

		$post_id = $this->factory->post->create( array(
			'post_author' => $author_a_id,
			'post_status' => 'publish',
		) );

		$author_a = $this->users->get( $author_a_id );
		$author_b = $this->users->get( $author_b_id );
		$editor   = $this->users->get( $editor_id );

		// Author A created the post -> can edit it
		$this->assertTrue( $author_a->can( 'edit_post', array( $post_id ) ) );

		// Author B did not create the post -> cannot edit it
		$this->assertFalse( $author_b->can( 'edit_post', array( $post_id ) ) );

		// Editor can edit others' posts -> can edit it
		$this->assertTrue( $editor->can( 'edit_post', array( $post_id ) ) );
	}

	public function test_database_seeder_seed_users() {
		\ExpressionLab\Tests\Fixtures\DatabaseSeeder::seed_users( 25 );

		global $wpdb;
		$user_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" );
		$this->assertGreaterThanOrEqual( 25, $user_count );

		// Query seeded admin
		$admin = $this->users->where( 'user_login', 'like', 'administrator_%' )->limit( 1 )->get();
		$this->assertNotEmpty( $admin );

		$admin_model = $this->users->get( (int) $admin[0]->ID );
		$this->assertInstanceOf( User::class, $admin_model );
		$this->assertTrue( $admin_model->can( 'manage_options' ) );
	}

	public function test_set_user_login_on_existing_user_emits_warning_and_does_not_mutate() {
		$user_id = $this->factory->user->create( array(
			'user_login' => 'original_login',
		) );

		$user = $this->users->get( $user_id );
		$user->set_user_login( 'modified_login' );

		// The in-memory login should remain the original login
		$this->assertEquals( 'original_login', $user->data->user_login );

		// Verify that a warning message was added to LanguageEngine
		$messages = \ExpressionLab\Core\LanguageEngine::get()->get_messages();
		$warning_found = false;
		foreach ( $messages as $msg ) {
			if ( 'warning' === $msg['type'] && strpos( $msg['text'], 'user_login' ) !== false ) {
				$warning_found = true;
				break;
			}
		}
		$this->assertTrue( $warning_found );
	}

	public function test_user_add_cap_and_remove_cap() {
		$user_id = $this->factory->user->create( array(
			'role' => 'subscriber',
		) );

		$user = $this->users->get( $user_id );
		$this->assertFalse( $user->can( 'custom_report_cap' ) );
		$this->assertFalse( $user->can( 'publish_posts' ) );

		// Add custom capability and standard capability with method chaining
		$user->add_cap( 'custom_report_cap' )
			->add_cap( 'publish_posts' );

		$this->assertTrue( $user->can( 'custom_report_cap' ) );
		$this->assertTrue( $user->can( 'publish_posts' ) );
		$this->assertArrayHasKey( 'custom_report_cap', $user->caps );
		$this->assertArrayHasKey( 'custom_report_cap', $user->allcaps );

		// Verify persistence in fresh instance from database
		$reloaded_user = $this->users->get( $user_id );
		$this->assertTrue( $reloaded_user->can( 'custom_report_cap' ) );
		$this->assertTrue( $reloaded_user->can( 'publish_posts' ) );

		// Remove capability
		$reloaded_user->remove_cap( 'custom_report_cap' );
		$this->assertFalse( $reloaded_user->can( 'custom_report_cap' ) );

		// Verify removal persistence
		$final_user = $this->users->get( $user_id );
		$this->assertFalse( $final_user->can( 'custom_report_cap' ) );
		$this->assertTrue( $final_user->can( 'publish_posts' ) );
	}

	public function test_user_add_cap_and_remove_cap_on_uninitialized_user() {
		$new_user = $this->users->build();
		$this->assertSame( $new_user, $new_user->add_cap( 'test_cap' ) );
		$this->assertSame( $new_user, $new_user->remove_cap( 'test_cap' ) );
	}

	public function test_user_delete_rejects_admin_and_current_user() {
		$user_id = $this->factory->user->create( array(
			'role' => 'subscriber',
		) );

		// 1. Current authenticated user cannot delete themselves
		wp_set_current_user( $user_id );
		$user = $this->users->get( $user_id );
		$this->assertFalse( $user->delete() );
		$this->assertNotNull( $this->users->get( $user_id ) );

		// Reset current user
		wp_set_current_user( 0 );

		// 2. Normal deletion of a different user works
		$this->assertTrue( $user->delete() );
		$this->assertNull( $this->users->get( $user_id ) );
	}

	public function test_usermeta_delete() {
		$user_id = $this->factory->user->create();
		update_user_meta( $user_id, 'test_meta_key', 'test_value' );

		$user = $this->users->get( $user_id );
		$this->assertTrue( $user->meta->has( 'test_meta_key' ) );
		$this->assertEquals( 'test_value', $user->meta->get( 'test_meta_key' ) );

		// Delete metadata using UserMeta::delete()
		$this->assertTrue( $user->meta->delete( 'test_meta_key' ) );
		$this->assertFalse( $user->meta->has( 'test_meta_key' ) );
		$this->assertNull( $user->meta->get( 'test_meta_key' ) );
	}

	public function test_usermeta_query_builder_strictly_scopes_to_user() {
		$user_1_id = $this->factory->user->create( array( 'first_name' => 'UserOne' ) );
		$user_2_id = $this->factory->user->create( array( 'first_name' => 'UserTwo' ) );
		$user_3_id = $this->factory->user->create( array( 'first_name' => 'UserThree' ) );

		$user_1 = $this->users->get( $user_1_id );
		$user_2 = $this->users->get( $user_2_id );

		// User 1 query builder should only return user 1's first_name row
		$results_1 = $user_1->meta->where( 'meta_key', 'first_name' )->get();
		$this->assertCount( 1, $results_1 );
		$this->assertEquals( (int) $user_1_id, (int) $results_1[0]->user_id );
		$this->assertEquals( 'UserOne', $results_1[0]->meta_value );

		// User 2 query builder should only return user 2's first_name row
		$results_2 = $user_2->meta->where( 'meta_key', 'first_name' )->get();
		$this->assertCount( 1, $results_2 );
		$this->assertEquals( (int) $user_2_id, (int) $results_2[0]->user_id );
		$this->assertEquals( 'UserTwo', $results_2[0]->meta_value );

		// Verify or_where maintains user_id scoping
		$or_results = $user_1->meta->where( 'meta_key', 'first_name' )->or_where( 'meta_key', 'last_name' )->get();
		foreach ( $or_results as $row ) {
			$this->assertEquals( (int) $user_1_id, (int) $row->user_id );
		}

		// Verify query() SQLite mirror only contains user 1's metadata
		$sql_results = $user_1->meta->where( 'meta_key', 'first_name' )->query( 'SELECT * FROM wp_usermeta' );
		$this->assertCount( 1, $sql_results );
		$user_id_val = is_array( $sql_results[0] ) ? $sql_results[0]['user_id'] : $sql_results[0]->user_id;
		$this->assertEquals( (int) $user_1_id, (int) $user_id_val );
	}

	public function test_users_and_usermeta_count_and_to_sql() {
		$u1 = $this->factory->user->create( array( 'role' => 'editor' ) );
		$u2 = $this->factory->user->create( array( 'role' => 'editor' ) );

		// Users count
		$count = $this->users->where( 'ID', 'in', array( $u1, $u2 ) )->count();
		$this->assertEquals( 2, $count );

		// Users to_sql
		$sql = $this->users->where( 'ID', 'in', array( $u1, $u2 ) )->order_by( 'ID', 'DESC' )->to_sql();
		$this->assertStringContainsString( 'SELECT * FROM', $sql );
		$this->assertStringContainsString( 'ORDER BY ID DESC', $sql );

		// UserMeta count
		$user = $this->users->get( $u1 );
		update_user_meta( $u1, 'test_key_a', 'val1' );
		update_user_meta( $u1, 'test_key_b', 'val2' );

		$meta_count = $user->meta->where( 'meta_key', 'like', 'test_key_%' )->count();
		$this->assertEquals( 2, $meta_count );

		// UserMeta to_sql (scoped to user_id)
		$meta_sql = $user->meta->where( 'meta_key', 'like', 'test_key_%' )->to_sql();
		$this->assertStringContainsString( 'SELECT * FROM', $meta_sql );
		$this->assertStringContainsString( "user_id = '{$u1}'", $meta_sql );
	}
}