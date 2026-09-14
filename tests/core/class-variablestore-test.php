<?php

// phpcs:ignoreFile

use ExpressionLab\Core\VariableStore;

class VariableStoreTest extends WP_UnitTestCase {

	/**
	 * @var VariableStore
	 */
	private $store;

	public function setUp(): void {
		parent::setUp();
		$this->store = new VariableStore();
	}

	public function test_simple_set_and_get() {
		$this->store->set( 'name', 'Anderson' );
		$this->assertEquals( 'Anderson', $this->store->get( 'name' ) );
		$this->assertTrue( $this->store->has( 'name' ) );
	}

	public function test_get_non_existent_returns_default() {
		$this->assertNull( $this->store->get( 'missing' ) );
		$this->assertEquals( 'default_val', $this->store->get( 'missing', 'default_val' ) );
		$this->assertFalse( $this->store->has( 'missing' ) );
	}

	public function test_delete_variable() {
		$this->store->set( 'temp', 123 );
		$this->assertTrue( $this->store->has( 'temp' ) );
		$this->store->delete( 'temp' );
		$this->assertFalse( $this->store->has( 'temp' ) );
		$this->assertNull( $this->store->get( 'temp' ) );
	}

	public function test_clear_all_variables() {
		$this->store->set( 'a', 1 );
		$this->store->set( 'b', 2 );
		$this->assertCount( 2, $this->store->all() );
		$this->store->clear();
		$this->assertEmpty( $this->store->all() );
	}

	public function test_nested_path_set_and_get() {
		$this->store->set( 'user.profile.name', 'Alice' );
		$this->assertEquals( 'Alice', $this->store->get( 'user.profile.name' ) );
		$this->assertTrue( $this->store->has( 'user.profile.name' ) );
		$this->assertEquals(
			array(
				'profile' => array(
					'name' => 'Alice',
				),
			),
			$this->store->get( 'user' )
		);
	}

	public function test_nested_path_merge_preserves_sibling_keys() {
		$this->store->set( 'config', array( 'db' => array( 'port' => 3306 ) ) );
		$this->store->set( 'config.db.host', 'localhost' );
		$this->store->set( 'config.api.enabled', true );

		$this->assertEquals( 3306, $this->store->get( 'config.db.port' ) );
		$this->assertEquals( 'localhost', $this->store->get( 'config.db.host' ) );
		$this->assertTrue( $this->store->get( 'config.api.enabled' ) );
	}

	public function test_nested_path_stdclass_objects() {
		$obj = new \stdClass();
		$obj->app = new \stdClass();
		$obj->app->title = 'Lab';

		$this->store->set( 'system', $obj );
		$this->store->set( 'system.app.version', 2 );

		$this->assertEquals( 2, $this->store->get( 'system.app.version' ) );
		$this->assertEquals( 'Lab', $this->store->get( 'system.app.title' ) );
	}

	public function test_nested_path_delete() {
		$this->store->set( 'data.a', 1 );
		$this->store->set( 'data.b', 2 );
		$this->store->delete( 'data.a' );

		$this->assertFalse( $this->store->has( 'data.a' ) );
		$this->assertTrue( $this->store->has( 'data.b' ) );
		$this->assertEquals( array( 'b' => 2 ), $this->store->get( 'data' ) );
	}

	public function test_invalid_path_segment_throws() {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid variable name' );
		$this->store->set( 'invalid.123bad', 'val' );
	}

	public function test_empty_variable_name_throws() {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'cannot be empty' );
		$this->store->set( '', 'val' );
	}

	public function test_unsafe_serialization_rejection() {
		$this->expectException( \UnexpectedValueException::class );
		$this->expectExceptionMessage( 'Unsafe serialization detected' );
		// Anonymous class or unsupported object type.
		$this->store->set( 'unsafe', new class {} );
	}

	public function test_closure_storage_allowed() {
		$closure = function( $x ) {
			return $x * 2;
		};
		$this->store->set( 'double', $closure );
		$this->assertTrue( $this->store->has( 'double' ) );
		$retrieved = $this->store->get( 'double' );
		$this->assertInstanceOf( \Closure::class, $retrieved );
		$this->assertEquals( 10, $retrieved( 5 ) );
	}
}
