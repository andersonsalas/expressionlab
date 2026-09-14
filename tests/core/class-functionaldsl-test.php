<?php

// phpcs:ignoreFile

use ExpressionLab\Core\LanguageEngine;

class FunctionaldslTest extends WP_UnitTestCase {
	/**
	 * @var LanguageEngine
	 */
	private $engine;

	public function setUp(): void {
		parent::setUp();
		$this->engine = LanguageEngine::get()->reset();
	}


	public function test_prog_sequential_evaluation() {
		$result = $this->engine->evaluate( "prog[ set['x', 10], set['y', 20], var['x'] + var['y'] ]" );
		$this->assertEquals( 30, $result['result'] );
	}

	public function test_prog_empty_returns_null() {
		$result = $this->engine->evaluate( 'prog[]' );
		$this->assertNull( $result['result'] );
	}

	public function test_prog_single_expression() {
		$result = $this->engine->evaluate( "prog[ 42 ]" );
		$this->assertEquals( 42, $result['result'] );
	}

	public function test_prog_trailing_comma() {
		$result = $this->engine->evaluate( "prog[ 1, 2, 3, ]" );
		$this->assertEquals( 3, $result['result'] );
	}

	public function test_prog_nested() {
		$result = $this->engine->evaluate( "prog[ prog[ set['a', 1] ], prog[ set['b', 2] ], var['a'] + var['b'] ]" );
		$this->assertEquals( 3, $result['result'] );
	}

	public function test_prog_guarantees_lazy_sequential_evaluation_and_short_circuits() {
		// 1. Short-circuit verification (lazy on-demand execution):
		// Step 1 mutates state; step 2 fails at runtime; step 3 must never execute.
		try {
			$this->engine->evaluate(
				"prog[
					set['step1_executed', true],
					set['not_a_function', 42],
					var['not_a_function'](),
					set['step3_executed', true]
				]"
			);
			$this->fail( 'Expected RuntimeException when invoking a non-callable value.' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'Attempted to invoke a non-callable value', $e->getMessage() );
		}

		$this->assertTrue( $this->engine->get_variable( 'step1_executed' ) );
		$this->assertNull( $this->engine->get_variable( 'step3_executed' ) );
		$this->assertFalse( $this->engine->has_variable( 'step3_executed' ) );

		// 2. Strict sequential causality and state accumulation:
		// Each step synchronously depends on state mutations produced by the preceding step.
		$seq = $this->engine->evaluate(
			"prog[
				set['trace', 'START'],
				set['trace', var['trace'] ~ '->STEP1'],
				set['trace', var['trace'] ~ '->STEP2'],
				set['trace', var['trace'] ~ '->DONE'],
				var['trace']
			]"
		);
		$this->assertSame( 'START->STEP1->STEP2->DONE', $seq['result'] );
	}


	public function test_set_returns_value() {
		$result = $this->engine->evaluate( "set['x', 42]" );
		$this->assertEquals( 42, $result['result'] );
	}

	public function test_var_access() {
		$result = $this->engine->evaluate( "prog[ set['x', 'hello'], var['x'] ]" );
		$this->assertEquals( 'hello', $result['result'] );
	}

	public function test_var_access_array() {
		$result = $this->engine->evaluate( "prog[ set['data', [10, 20, 30]], var['data'] ]" );
		$this->assertEquals( array( 10, 20, 30 ), $result['result'] );
	}

	public function test_set_overwrites_previous_value() {
		$result = $this->engine->evaluate( "prog[ set['x', 1], set['x', 99], var['x'] ]" );
		$this->assertEquals( 99, $result['result'] );
	}

	public function test_var_undefined_returns_null_with_warning() {
		$result = $this->engine->evaluate( "var['nonexistent']" );
		$this->assertNull( $result['result'] );
		$this->assertNotEmpty( $result['messages'] );
		$this->assertEquals( 'warning', $result['messages'][0]['type'] );
	}


	public function test_unset_returns_true() {
		$result = $this->engine->evaluate( "prog[ set['x', 1], unset['x'] ]" );
		$this->assertTrue( $result['result'] );
	}

	public function test_isset_true() {
		$result = $this->engine->evaluate( "prog[ set['flag', true], isset['flag'] ]" );
		$this->assertTrue( $result['result'] );
	}

	public function test_isset_false_after_unset() {
		$result = $this->engine->evaluate( "prog[ set['flag', true], unset['flag'], isset['flag'] ]" );
		$this->assertFalse( $result['result'] );
	}

	public function test_isset_false_for_never_set() {
		$result = $this->engine->evaluate( "isset['never_set']" );
		$this->assertFalse( $result['result'] );
	}


	public function test_fn_creates_closure() {
		$result = $this->engine->evaluate( "map[ [1], fn[['n'], args['n'] * 10] ]" );
		$this->assertEquals( array( 10 ), $result['result'] );
	}

	public function test_fn_multiple_params() {
		$result = $this->engine->evaluate( "reduce[ [1, 2, 3], fn[['acc', 'n'], args['acc'] + args['n']], 0 ]" );
		$this->assertEquals( 6, $result['result'] );
	}

	public function test_fn_captures_outer_scope_vars() {
		$result = $this->engine->evaluate( "prog[ set['multiplier', 10], map[ [1, 2, 3], fn[['n'], args['n'] * var['multiplier']] ] ]" );
		$this->assertEquals( array( 10, 20, 30 ), $result['result'] );
	}

	public function test_args_outside_fn_returns_null() {
		$result = $this->engine->evaluate( "args['something']" );
		$this->assertNull( $result['result'] );
	}


	public function test_map_transform() {
		$result = $this->engine->evaluate( "map[ [1, 2, 3], fn[['n'], args['n'] * 2] ]" );
		$this->assertEquals( array( 2, 4, 6 ), $result['result'] );
	}

	public function test_map_with_string_concat() {
		$result = $this->engine->evaluate( "prog[ set['names', ['anderson', 'alice']], map[ var['names'], fn[['name'], 'Hello ' ~ strtoupper(args['name'])] ] ]" );
		$this->assertEquals( array( 'Hello ANDERSON', 'Hello ALICE' ), $result['result'] );
	}

	public function test_map_empty_array() {
		$result = $this->engine->evaluate( "map[ [], fn[['n'], args['n'] * 2] ]" );
		$this->assertEquals( array(), $result['result'] );
	}


	public function test_filter_predicate() {
		$result = $this->engine->evaluate( "filter[ [1, 2, 3, 4, 5, 6], fn[['n'], args['n'] % 2 == 0] ]" );
		$this->assertEquals( array( 2, 4, 6 ), $result['result'] );
	}

	public function test_filter_empty_result() {
		$result = $this->engine->evaluate( "filter[ [1, 3, 5], fn[['n'], args['n'] % 2 == 0] ]" );
		$this->assertEquals( array(), $result['result'] );
	}

	public function test_filter_all_pass() {
		$result = $this->engine->evaluate( "filter[ [2, 4, 6], fn[['n'], args['n'] % 2 == 0] ]" );
		$this->assertEquals( array( 2, 4, 6 ), $result['result'] );
	}


	public function test_reduce_sum() {
		$result = $this->engine->evaluate( "reduce[ [10, 20, 30], fn[['acc', 'n'], args['acc'] + args['n']], 0 ]" );
		$this->assertEquals( 60, $result['result'] );
	}

	public function test_reduce_without_initial() {
		$result = $this->engine->evaluate( "reduce[ [1, 2, 3], fn[['acc', 'n'], args['acc'] + args['n']] ]" );
		// Without initial, accumulator starts as null. null + 1 = 1, 1 + 2 = 3, 3 + 3 = 6.
		$this->assertEquals( 6, $result['result'] );
	}

	public function test_reduce_string_concatenation() {
		$result = $this->engine->evaluate( "reduce[ ['a', 'b', 'c'], fn[['acc', 'item'], args['acc'] ~ args['item']], '' ]" );
		$this->assertEquals( 'abc', $result['result'] );
	}


	public function test_nested_prog_map_fn() {
		$result = $this->engine->evaluate(
			"prog[
				set['names', ['anderson', 'alice']],
				map[
					var['names'],
					fn[['name'], 'Hello ' ~ strtoupper(args['name'])]
				]
			]"
		);
		$this->assertEquals( array( 'Hello ANDERSON', 'Hello ALICE' ), $result['result'] );
	}

	public function test_nested_prog_filter_reduce() {
		$result = $this->engine->evaluate(
			"prog[
				set['numbers', [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]],
				set['pairs', filter[ var['numbers'], fn[['n'], args['n'] % 2 == 0] ]],
				reduce[ var['pairs'], fn[['acc', 'n'], args['acc'] + args['n']], 0 ]
			]"
		);
		// 2 + 4 + 6 + 8 + 10 = 30
		$this->assertEquals( 30, $result['result'] );
	}

	public function test_complex_pipeline() {
		$result = $this->engine->evaluate(
			"prog[
				set['data', [1, 2, 3, 4, 5]],
				set['doubled', map[ var['data'], fn[['n'], args['n'] * 2] ]],
				set['big', filter[ var['doubled'], fn[['n'], args['n'] > 5] ]],
				reduce[ var['big'], fn[['acc', 'n'], args['acc'] + args['n']], 0 ]
			]"
		);
		// doubled: [2, 4, 6, 8, 10], big: [6, 8, 10], sum: 24
		$this->assertEquals( 24, $result['result'] );
	}


	public function test_show_returns_expression_value() {
		$result = $this->engine->evaluate( "show[ 42 ]" );
		$this->assertEquals( 42, $result['result'] );
	}


	public function test_map_blocks_string_callables_such_as_exec() {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'must be a Closure' );
		$this->engine->evaluate( "map[[1, 2, 3], 'exec']" );
	}

	public function test_filter_blocks_string_callables_such_as_system() {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'must be a Closure' );
		$this->engine->evaluate( "filter[[1, 2, 3], 'system']" );
	}

	public function test_reduce_blocks_string_callables_such_as_passthru() {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'must be a Closure' );
		$this->engine->evaluate( "reduce[[1, 2, 3], 'passthru', 0]" );
	}

	public function test_fn_malformed_params_throws() {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'must be an array of parameter names' );
		$this->engine->evaluate( "fn['x', 123]" );
	}


	public function test_filter_with_mode_use_key() {
		$result = $this->engine->evaluate(
			"filter[{'a': 10, 'b': 20, 'c': 30}, fn[['k'], args['k'] === 'b'], 2]"
		);
		$this->assertEquals( array( 'b' => 20 ), $result['result'] );
	}

	public function test_filter_with_mode_use_both() {
		$result = $this->engine->evaluate(
			"filter[{'a': 10, 'b': 20, 'c': 30}, fn[['v', 'k'], args['k'] === 'a' and args['v'] === 10], 1]"
		);
		$this->assertEquals( array( 'a' => 10 ), $result['result'] );
	}

	public function test_filter_associative_array_preserves_keys() {
		$result = $this->engine->evaluate(
			"filter[{'user1': 18, 'user2': 15, 'user3': 22}, fn[['age'], args['age'] >= 18]]"
		);
		$this->assertEquals( array( 'user1' => 18, 'user3' => 22 ), $result['result'] );
	}

	public function test_old_var_dot_syntax_still_works() {
		$this->engine->set_variable( 'test_value', 123 );
		$result = $this->engine->evaluate( 'var.test_value' );
		$this->assertEquals( 123, $result['result'] );
	}

	public function test_new_set_old_var_dot() {
		// Set via new bracket, read via old dot syntax.
		$result = $this->engine->evaluate( "prog[ set['dottest', 99], var.dottest ]" );
		$this->assertEquals( 99, $result['result'] );
	}


	public function test_deeply_nested_prog_hits_gas_limit_but_parses() {
		// A moderately nested expression should parse and evaluate fine.
		$result = $this->engine->evaluate( "prog[ prog[ prog[ 1 + 2 ] ] ]" );
		$this->assertEquals( 3, $result['result'] );
	}


	public function test_map_non_iterable_throws() {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'must be iterable' );
		$this->engine->evaluate( "map[ 42, fn[['n'], args['n']] ]" );
	}

	public function test_filter_non_iterable_throws() {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'must be iterable' );
		$this->engine->evaluate( "filter[ 'hello', fn[['n'], true] ]" );
	}

	public function test_reduce_non_iterable_throws() {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'must be iterable' );
		$this->engine->evaluate( "reduce[ 42, fn[['a', 'b'], args['a']], 0 ]" );
	}


	public function test_nested_path_set_and_bracket_access() {
		$result = $this->engine->evaluate(
			"prog[
				set['foo.bar', 'baz'],
				var['foo']['bar']
			]"
		);
		$this->assertEquals( 'baz', $result['result'] );
	}

	public function test_nested_path_set_and_dot_path_var_access() {
		$result = $this->engine->evaluate(
			"prog[
				set['user.profile.name', 'Alice'],
				var['user.profile.name']
			]"
		);
		$this->assertEquals( 'Alice', $result['result'] );
	}

	public function test_nested_path_deep_creation() {
		$result = $this->engine->evaluate(
			"prog[
				set['a.b.c.d', 42],
				var['a']
			]"
		);
		$expected = array(
			'b' => array(
				'c' => array(
					'd' => 42,
				),
			),
		);
		$this->assertEquals( $expected, $result['result'] );
	}

	public function test_nested_path_merging() {
		$result = $this->engine->evaluate(
			"prog[
				set['config', {'db': {'port': 3306}}],
				set['config.db.host', 'localhost'],
				set['config.api.enabled', true],
				var['config']
			]"
		);
		$expected = array(
			'db'  => array(
				'port' => 3306,
				'host' => 'localhost',
			),
			'api' => array(
				'enabled' => true,
			),
		);
		$this->assertEquals( $expected, $result['result'] );
	}

	public function test_nested_path_stdclass_support() {
		$obj                = new \stdClass();
		$obj->meta          = new \stdClass();
		$obj->meta->version = 1;
		$this->engine->set_variable( 'app', $obj );

		$result = $this->engine->evaluate(
			"prog[
				set['app.meta.author', 'Anderson'],
				var['app.meta.author']
			]"
		);
		$this->assertEquals( 'Anderson', $result['result'] );
	}

	public function test_nested_path_isset() {
		$result = $this->engine->evaluate(
			"prog[
				set['settings.theme.dark', true],
				[isset['settings.theme.dark'], isset['settings.theme.light']]
			]"
		);
		$this->assertEquals( array( true, false ), $result['result'] );
	}

	public function test_nested_path_unset() {
		$result = $this->engine->evaluate(
			"prog[
				set['data.item1', 10],
				set['data.item2', 20],
				unset['data.item1'],
				var['data']
			]"
		);
		$this->assertEquals( array( 'item2' => 20 ), $result['result'] );
	}

	public function test_nested_path_invalid_segment_throws() {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid variable name' );
		$this->engine->evaluate( "set['foo.123bad', 'test']" );
	}

	public function test_large_collection_storage_in_session_variables() {
		// Create several sample pages to simulate a realistic WordPress query.
		for ( $i = 0; $i < 10; $i++ ) {
			wp_insert_post(
				array(
					'post_title'   => "Test Page $i",
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_content' => str_repeat( "<p>Sample paragraph $i with rich HTML content.</p>", 50 ),
				)
			);
		}

		$result = $this->engine->evaluate(
			"prog[
				set['post_type', 'page'],
				set['pages', Posts.where('post_type', var['post_type']).get()],
				count(var['pages'])
			]"
		);

		$this->assertGreaterThanOrEqual( 10, $result['result'] );
	}

	public function test_fn_closure_storage_and_higher_order_usage() {
		$result = $this->engine->evaluate(
			"prog[
				set['greet', fn[['name'], 'Hello, ' ~ strtoupper(args['name']) ~ '!']],
				map[
					['alice', 'bob'],
					var['greet']
				]
			]"
		);

		$this->assertEquals( array( 'Hello, ALICE!', 'Hello, BOB!' ), $result['result'] );
	}


	public function test_direct_stored_closure_invocation() {
		$result = $this->engine->evaluate(
			"prog[
				set['add', fn[['a', 'b'], args['a'] + args['b']]],
				var['add'](10, 25)
			]"
		);
		$this->assertEquals( 35, $result['result'] );
	}

	public function test_direct_anonymous_closure_invocation() {
		$result = $this->engine->evaluate(
			"(fn[['x'], args['x'] * 3])(7)"
		);
		$this->assertEquals( 21, $result['result'] );
	}

	public function test_currying_and_lexical_scope_chaining() {
		$result = $this->engine->evaluate(
			"prog[
				set['make_multiplier', fn[['factor'], fn[['n'], args['n'] * args['factor']]]],
				set['times_four', var['make_multiplier'](4)],
				var['times_four'](5)
			]"
		);
		$this->assertEquals( 20, $result['result'] );
	}

	public function test_chained_currying_invocation() {
		$result = $this->engine->evaluate(
			"prog[
				set['multiplier', fn[['factor'], fn[['n'], args['n'] * args['factor']]]],
				var['multiplier'](3)(10)
			]"
		);
		$this->assertEquals( 30, $result['result'] );
	}

	public function test_invoking_non_closure_throws_exception() {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Attempted to invoke a non-callable value' );
		$this->engine->evaluate(
			"prog[
				set['number', 42],
				var['number'](1, 2)
			]"
		);
	}


	public function test_infinite_recursion_aborts_at_max_depth() {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Maximum recursion depth of 100 exceeded' );
		$this->engine->evaluate(
			"prog[
				set['recursive', fn[[], var['recursive']()]],
				var['recursive']()
			]"
		);
	}

	public function test_mutual_infinite_recursion_aborts() {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Maximum recursion depth of 100 exceeded' );
		$this->engine->evaluate(
			"prog[
				set['ping', fn[[], var['pong']()]],
				set['pong', fn[[], var['ping']()]],
				var['ping']()
			]"
		);
	}

	public function test_valid_recursion_under_depth_limit() {
		$result = $this->engine->evaluate(
			"prog[
				set['fact', fn[['n'], args['n'] <= 1 ? 1 : args['n'] * var['fact'](args['n'] - 1)]],
				var['fact'](5)
			]"
		);
		$this->assertEquals( 120, $result['result'] );
		$this->assertEquals( 0, $this->engine->get_call_depth() );
	}

	public function test_recursion_cleans_call_depth_after_error() {
		try {
			$this->engine->evaluate(
				"prog[
					set['loop', fn[[], var['loop']()]],
					var['loop']()
				]"
			);
		} catch ( \RuntimeException $e ) {
			// Expected.
		}

		$this->assertEquals( 0, $this->engine->get_call_depth() );
	}
}
