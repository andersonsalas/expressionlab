<?php

// phpcs:ignorefile

use ExpressionLab\Core\Services\Console;
use ExpressionLab\Core\LanguageEngine;

class ConsoleTest extends WP_UnitTestCase {
	/**
	 * Console instance.
	 *
	 * @var Console
	 */
	private $console;

	public function setUp(): void {
		parent::setUp();
		$this->console = new Console();
		// Ensure clean state
		LanguageEngine::get()->reset();
	}

	public function tearDown(): void {
		parent::tearDown();
		LanguageEngine::get()->reset();
	}

	public function test_log_adds_info_message() {
		$this->console->log( 'Test Log' );
		$messages = LanguageEngine::get()->get_messages();

		$this->assertCount( 1, $messages );
		$this->assertEquals( 'info', $messages[0]['type'] );
		$this->assertEquals( 'Test Log', $messages[0]['text'] );
	}

	public function test_warning_adds_warning_message() {
		$this->console->warning( 'Test Warning' );
		$messages = LanguageEngine::get()->get_messages();

		$this->assertCount( 1, $messages );
		$this->assertEquals( 'warning', $messages[0]['type'] );
		$this->assertEquals( 'Test Warning', $messages[0]['text'] );
	}

	public function test_error_adds_error_message() {
		$this->console->error( 'Test Error' );
		$messages = LanguageEngine::get()->get_messages();

		$this->assertCount( 1, $messages );
		$this->assertEquals( 'error', $messages[0]['type'] );
		$this->assertEquals( 'Test Error', $messages[0]['text'] );
	}

	public function test_success_adds_success_message() {
		$this->console->success( 'Test Success' );
		$messages = LanguageEngine::get()->get_messages();

		$this->assertCount( 1, $messages );
		$this->assertEquals( 'success', $messages[0]['type'] );
		$this->assertEquals( 'Test Success', $messages[0]['text'] );
	}

	public function test_encode_primitives() {
		// String
		$this->console->log( 'Hello' );
		$this->assertEquals( 'Hello', LanguageEngine::get()->get_messages()[0]['text'] );
		LanguageEngine::get()->reset();

		// Integer
		$this->console->log( 123 );
		$this->assertEquals( '123', LanguageEngine::get()->get_messages()[0]['text'] ); // Numeric is returned as is, but might be cast to string later or in assertion
		LanguageEngine::get()->reset();

		// Boolean
		$this->console->log( true );
		$msg = LanguageEngine::get()->get_messages()[0]['text'];
		$this->assertTrue( $msg === true || $msg === '1' ); 
		LanguageEngine::get()->reset();
	}

	public function test_encode_resource() {
		$resource = fopen( 'php://memory', 'r' );
		$this->console->log( $resource );
		$msg = LanguageEngine::get()->get_messages()[0]['text'];
		$this->assertEquals( '<Resource>', $msg );
		fclose( $resource );
	}

	public function test_encode_stdclass_json() {
		$obj = new stdClass();
		$obj->foo = 'bar';
		$this->console->log( $obj );
		
		$msg = LanguageEngine::get()->get_messages()[0]['text'];
		$this->assertJsonStringEqualsJsonString( '{"foo":"bar"}', $msg );
	}

	public function test_encode_custom_object() {
		$obj = new class {
		};
		// Anonymous class name is complicated, but it won't be stdClass
		$this->console->log( $obj );
		$msg = LanguageEngine::get()->get_messages()[0]['text'];
		$this->assertStringStartsWith( '<class@anonymous', $msg );
		$this->assertStringEndsWith( '>', $msg );
	}
	
	public function test_encode_named_custom_object() {
		// Since we cannot define global classes inside a method easily without side effects, 
		// we rely on existing classes or mocks.
		$mock = $this->getMockBuilder( stdClass::class )->setMockClassName( 'MyMockObject' )->getMock();
		
		$this->console->log( $mock );
		$msg = LanguageEngine::get()->get_messages()[0]['text'];
		
		$this->assertEquals( '<MyMockObject>', $msg );
	}

	public function test_encode_object_with_tostring() {
		$obj = new class {
			public function __toString() {
				return 'I am a string';
			}
		};

		$this->console->log( $obj );
		$msg = LanguageEngine::get()->get_messages()[0]['text'];
		$this->assertEquals( 'I am a string', $msg );
	}

	public function test_encode_array() {
		$arr = array( 'a' => 1, 'b' => 2 );
		$this->console->log( $arr );
		$msg = LanguageEngine::get()->get_messages()[0]['text'];
		$this->assertJsonStringEqualsJsonString( '{"a":1,"b":2}', $msg );
	}

	public function test_table_visualization() {
		$headers = array( 'Name', 'Age' );
		$values  = array(
			array( 'Alice', 30 ),
			array( 'Bob', 25 ),
		);

		$this->console->table( $headers, $values, 'Users' );

		$visualizations = LanguageEngine::get()->get_visualizations();
		$this->assertCount( 1, $visualizations );
		
		$viz = $visualizations[0];
		$this->assertEquals( 'table', $viz['type'] );
		$this->assertEquals( 'Users', $viz['title'] );
		$this->assertCount( 2, $viz['data'] );
		
		$this->assertEquals( 'Alice', $viz['data'][0]['Name'] );
		$this->assertEquals( 30, $viz['data'][0]['Age'] );
		$this->assertEquals( 'Bob', $viz['data'][1]['Name'] );
	}

	public function test_table_empty_data() {
		$this->expectException( \InvalidArgumentException::class );
		$this->console->table( array( 'H1' ), array() );
	}

	public function test_table_empty_data_first_arg() {
		$this->expectException( \InvalidArgumentException::class );
		$this->console->table( array(), array( array( 1 ) ) );
	}

	public function test_table_associative_array() {
		$data = array(
			array( 'Nombre' => 'Alice', 'Edad' => 30 ),
			array( 'Nombre' => 'Bob', 'Edad' => 25 ),
		);

		// Using the single argument format
		$this->console->table( $data );

		$visualizations = LanguageEngine::get()->get_visualizations();
		$this->assertCount( 1, $visualizations );
		
		$viz = $visualizations[0];
		$this->assertEquals( 'table', $viz['type'] );
		$this->assertEquals( 'Table', $viz['title'] ); // Title by default
		$this->assertCount( 2, $viz['data'] );
		
		$this->assertEquals( 'Alice', $viz['data'][0]['Nombre'] );
		$this->assertEquals( 30, $viz['data'][0]['Edad'] );
		$this->assertEquals( 'Bob', $viz['data'][1]['Nombre'] );
	}

	public function test_table_associative_array_with_title() {
		$data = array(
			array( 'A' => 1, 'B' => 2 ),
		);

		// Passing the title as the second argument
		$this->console->table( $data, 'Mi Tabla Asociativa' );

		$viz = LanguageEngine::get()->get_visualizations()[0];
		$this->assertEquals( 'Mi Tabla Asociativa', $viz['title'] );
		$this->assertEquals( 1, $viz['data'][0]['A'] );
		$this->assertEquals( 2, $viz['data'][0]['B'] );
	}
	
	public function test_table_associative_objects() {
		$obj1 = new stdClass();
		$obj1->ID = 101;
		$obj1->Status = 'Active';

		$obj2 = new stdClass();
		$obj2->ID = 102;
		$obj2->Status = 'Pending';

		$this->console->table( array( $obj1, $obj2 ), 'System Status' );

		$viz = LanguageEngine::get()->get_visualizations()[0];
		$this->assertEquals( 'System Status', $viz['title'] );
		$this->assertEquals( 101, $viz['data'][0]['ID'] );
		$this->assertEquals( 'Pending', $viz['data'][1]['Status'] );
	}
	
	public function test_table_missing_columns() {
		$headers = array( 'A', 'B' );
		$values = array(
			array( 1 ), // Missing B
		);
		
		$this->console->table( $headers, $values );
		
		$viz = LanguageEngine::get()->get_visualizations()[0];
		$this->assertEquals( 1, $viz['data'][0]['A'] );
		$this->assertNull( $viz['data'][0]['B'] );
	}
}
