<?php

// phpcs:ignoreFile

use ExpressionLab\Core\Services\Cache;
use ExpressionLab\Core\LanguageEngine;

class CacheTest extends WP_UnitTestCase {

	/**
	 * Cache service instance.
	 *
	 * @var Cache
	 */
	private $cache;

	public function setUp(): void {
		parent::setUp();
		$this->cache = new Cache();
	}

	public function test_is_persistent_returns_bool() {
		$result = $this->cache->is_persistent();
		$this->assertIsBool( $result );
		$this->assertSame( (bool) wp_using_ext_object_cache(), $result );
	}

	public function test_status_returns_diagnostic_array() {
		$status = $this->cache->status();

		$this->assertIsArray( $status );
		$this->assertArrayHasKey( 'is_persistent', $status );
		$this->assertArrayHasKey( 'backend', $status );
		$this->assertArrayHasKey( 'dropin_exists', $status );
		$this->assertArrayHasKey( 'dropin_path', $status );
		$this->assertArrayHasKey( 'global_groups', $status );
		$this->assertArrayHasKey( 'non_persistent_groups', $status );
		$this->assertArrayHasKey( 'multisite_blog_id', $status );

		$this->assertIsBool( $status['is_persistent'] );
		$this->assertIsString( $status['backend'] );
		$this->assertIsBool( $status['dropin_exists'] );
		$this->assertIsArray( $status['global_groups'] );
		$this->assertIsArray( $status['non_persistent_groups'] );
	}

	public function test_status_reports_global_group_names() {
		wp_cache_add_global_groups( 'el_audit_global_group' );

		$status = $this->cache->status();

		$this->assertContains( 'el_audit_global_group', $status['global_groups'] );
		$this->assertContainsOnly( 'string', $status['global_groups'] );
	}

	public function test_status_and_stats_support_third_party_backend_shapes() {
		global $wp_object_cache;

		$original                = $wp_object_cache;
		$backend                 = new stdClass();
		$backend->global_groups  = array( 'users', 'userlogins', 'users' );
		$backend->ignored_groups = array( 'counts', 'plugins' );
		$backend->cache_hits     = '7';
		$backend->cache_misses   = 3;
		$wp_object_cache         = $backend;

		try {
			$status = $this->cache->status();
			$stats  = $this->cache->stats();
		} finally {
			$wp_object_cache = $original;
		}

		$this->assertSame( 'stdClass', $status['backend'] );
		$this->assertSame( array( 'users', 'userlogins' ), $status['global_groups'] );
		$this->assertSame( array( 'counts', 'plugins' ), $status['non_persistent_groups'] );
		$this->assertSame( 7, $stats['hits'] );
		$this->assertSame( 3, $stats['misses'] );
		$this->assertSame( '70.00%', $stats['hit_ratio'] );
	}

	public function test_stats_returns_metrics_array() {
		$stats = $this->cache->stats();

		$this->assertIsArray( $stats );
		$this->assertArrayHasKey( 'hits', $stats );
		$this->assertArrayHasKey( 'misses', $stats );
		$this->assertArrayHasKey( 'total', $stats );
		$this->assertArrayHasKey( 'hit_ratio', $stats );
		$this->assertArrayHasKey( 'is_persistent', $stats );

		$this->assertIsInt( $stats['hits'] );
		$this->assertIsInt( $stats['misses'] );
		$this->assertIsInt( $stats['total'] );
		$this->assertIsString( $stats['hit_ratio'] );
		$this->assertIsBool( $stats['is_persistent'] );
	}

	public function test_stats_emits_visualizations() {
		$this->cache->get( 'some_test_key_for_viz', 'unit_test_viz' );

		LanguageEngine::get()->reset();
		$this->cache->stats();

		$visualizations = LanguageEngine::get()->get_visualizations();
		$this->assertNotEmpty( $visualizations );
		$this->assertSame( 'table', $visualizations[0]['type'] );
		$this->assertSame( 'graph', $visualizations[1]['type'] );
		$this->assertSame( 'arc', $visualizations[1]['data']['mark']['type'] );
	}

	public function test_set_and_get_single() {
		$key   = 'unit_test_key_1';
		$val   = array( 'hello' => 'world', 'count' => 42 );
		$group = 'unit_test_group';

		$set_result = $this->cache->set( $key, $val, $group );
		$this->assertTrue( $set_result );

		$get_result = $this->cache->get( $key, $group );
		$this->assertSame( $val, $get_result );

		$has_result = $this->cache->has( $key, $group );
		$this->assertTrue( $has_result );
	}

	public function test_get_nonexistent_returns_default() {
		$key   = 'non_existent_key_' . wp_rand();
		$group = 'unit_test_group';

		$this->assertFalse( $this->cache->has( $key, $group ) );
		$this->assertNull( $this->cache->get( $key, $group ) );
		$this->assertSame( 'custom_default', $this->cache->get( $key, $group, false, 'custom_default' ) );
	}

	public function test_has_distinguishes_false_from_miss() {
		$key   = 'bool_false_key_' . wp_rand();
		$group = 'unit_test_group';

		$this->cache->set( $key, false, $group );

		$this->assertTrue( $this->cache->has( $key, $group ) );
		$this->assertFalse( $this->cache->get( $key, $group, false, 'fallback' ) );

		$missing = 'actually_missing_' . wp_rand();
		$this->assertFalse( $this->cache->has( $missing, $group ) );
		$this->assertSame( 'fallback', $this->cache->get( $missing, $group, false, 'fallback' ) );
	}

	public function test_get_multiple_batch() {
		$group = 'unit_test_batch_get';
		$this->cache->set( 'batch_1', 'val_1', $group );
		$this->cache->set( 'batch_2', 'val_2', $group );
		$this->cache->set( 'batch_false', false, $group );

		$results = $this->cache->get( array( 'batch_1', 'batch_2', 'batch_false', 'batch_missing' ), $group, false, 'def_val' );

		$this->assertIsArray( $results );
		$this->assertSame( 'val_1', $results['batch_1'] );
		$this->assertSame( 'val_2', $results['batch_2'] );
		$this->assertFalse( $results['batch_false'] );
		$this->assertSame( 'def_val', $results['batch_missing'] );

		$empty_results = $this->cache->get( array(), $group );
		$this->assertSame( array(), $empty_results );
	}

	public function test_set_multiple_with_argument_sniffing() {
		$group = 'unit_test_batch_set';
		$data  = array(
			'sniff_1' => 'alpha',
			'sniff_2' => 'beta',
		);

		$set_result = $this->cache->set( $data, $group, 3600 );
		$this->assertTrue( $set_result );

		$this->assertSame( 'alpha', $this->cache->get( 'sniff_1', $group ) );
		$this->assertSame( 'beta', $this->cache->get( 'sniff_2', $group ) );
	}

	public function test_delete_single() {
		$key   = 'to_delete_' . wp_rand();
		$group = 'unit_test_delete';

		$this->cache->set( $key, 'active_data', $group );
		$this->assertTrue( $this->cache->has( $key, $group ) );

		$del_result = $this->cache->delete( $key, $group );
		$this->assertTrue( $del_result );
		$this->assertFalse( $this->cache->has( $key, $group ) );
	}

	public function test_delete_multiple_batch() {
		$group = 'unit_test_delete_batch';
		$this->cache->set( 'del_1', 'val_1', $group );
		$this->cache->set( 'del_2', 'val_2', $group );

		$this->assertTrue( $this->cache->has( 'del_1', $group ) );
		$this->assertTrue( $this->cache->has( 'del_2', $group ) );

		$del_result = $this->cache->delete( array( 'del_1', 'del_2' ), $group );
		$this->assertTrue( $del_result );

		$this->assertFalse( $this->cache->has( 'del_1', $group ) );
		$this->assertFalse( $this->cache->has( 'del_2', $group ) );

		$this->cache->set( 'del_dup', 'val', $group );
		$this->assertTrue( $this->cache->delete( array( 'del_dup', 'del_dup' ), $group ) );
		$this->assertFalse( $this->cache->has( 'del_dup', $group ) );
	}

	public function test_batch_operations_tick_once_per_key() {
		$engine     = LanguageEngine::get();
		$operations = new \ReflectionProperty( LanguageEngine::class, 'operations' );
		$group      = 'unit_test_ticks';

		$engine->reset();

		$this->cache->set( array( 'tick_a' => 1, 'tick_b' => false, 'tick_c' => 3 ), $group );
		$this->assertSame( 3, $operations->getValue( $engine ) );

		$this->cache->get( array( 'tick_a', 'tick_b', 'tick_missing' ), $group );
		$this->assertSame( 6, $operations->getValue( $engine ) );

		$this->cache->delete( array( 'tick_a', 'tick_c' ), $group );
		$this->assertSame( 8, $operations->getValue( $engine ) );

		$this->cache->get( array(), $group );
		$this->assertSame( 9, $operations->getValue( $engine ) );
	}

	public function test_set_rejects_misplaced_or_invalid_group_and_expiration() {
		$this->assert_invalid_argument( fn() => $this->cache->set( 'bad_meta', 'val', 300 ) );
		$this->assert_invalid_argument( fn() => $this->cache->set( 'bad_meta', 'val', 'unit_test_meta', -1 ) );
		$this->assert_invalid_argument( fn() => $this->cache->set( array( 'bad_meta' => 'val' ), array( 'unit_test_meta' ) ) );
		$this->assert_invalid_argument( fn() => $this->cache->set( array( 'bad_meta' => 'val' ), 'unit_test_meta', '60' ) );
		$this->assert_invalid_argument( fn() => $this->cache->set( array( 'bad_meta' => 'val' ), 'unit_test_meta', -5 ) );

		$this->assertFalse( $this->cache->has( 'bad_meta' ) );
		$this->assertFalse( $this->cache->has( 'bad_meta', 'unit_test_meta' ) );
	}

	/**
	 * @group multisite
	 */
	public function test_multisite_operations_target_engine_site_and_restore_context() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite test.' );
		}

		$main_blog_id = get_current_blog_id();
		$blog_id      = self::factory()->blog->create();
		$group        = 'unit_test_multisite';

		LanguageEngine::get()->set_site_id( $blog_id );

		try {
			$this->assertTrue( $this->cache->set( 'ms_key', 'site_value', $group ) );
			$this->assertSame( $blog_id, $this->cache->status()['multisite_blog_id'] );
			$this->assert_invalid_argument( fn() => $this->cache->get( '' ) );
			$this->assertSame( $main_blog_id, get_current_blog_id() );

			$first  = $this->cache->stats();
			$second = $this->cache->stats();
			$this->assertSame( $first['hits'], $second['hits'] );
			$this->assertSame( $first['misses'], $second['misses'] );
		} finally {
			LanguageEngine::get()->set_site_id( null );
		}

		$this->assertFalse( $this->cache->has( 'ms_key', $group ) );

		switch_to_blog( $blog_id );
		$this->assertSame( 'site_value', wp_cache_get( 'ms_key', $group ) );
		restore_current_blog();
	}

	public function test_engine_evaluation_with_cache_service() {
		$engine = LanguageEngine::get();
		$engine->reset();

		$eval_status = $engine->evaluate( 'Cache.is_persistent()' );
		$this->assertIsBool( $eval_status['result'] );

		$eval_set = $engine->evaluate( "Cache.set('el_eval_key', 'el_eval_val', 'el_test_group')" );
		$this->assertTrue( $eval_set['result'] );

		$eval_has = $engine->evaluate( "Cache.has('el_eval_key', 'el_test_group')" );
		$this->assertTrue( $eval_has['result'] );

		$eval_get = $engine->evaluate( "Cache.get('el_eval_key', 'el_test_group')" );
		$this->assertSame( 'el_eval_val', $eval_get['result'] );

		$eval_del = $engine->evaluate( "Cache.delete('el_eval_key', 'el_test_group')" );
		$this->assertTrue( $eval_del['result'] );

		$eval_has_after = $engine->evaluate( "Cache.has('el_eval_key', 'el_test_group')" );
		$this->assertFalse( $eval_has_after['result'] );
	}

	public function test_invalid_key_throws_exception() {
		$invalid_keys = array(
			'',
			'   ',
			null,
			true,
			false,
			1.5,
			new stdClass(),
			array( array( 'bad' ) ),
			array( 'valid_key', '' ),
			array( 'valid_key', null ),
		);

		foreach ( $invalid_keys as $key ) {
			$this->assert_invalid_argument( fn() => $this->cache->get( $key ) );
			$this->assert_invalid_argument( fn() => $this->cache->has( $key ) );
			$this->assert_invalid_argument( fn() => $this->cache->delete( $key ) );

			if ( ! is_array( $key ) ) {
				$this->assert_invalid_argument( fn() => $this->cache->set( $key, 'val' ) );
			}
		}

		$this->assert_invalid_argument( fn() => $this->cache->set( array( 'valid_key' => 'val', '' => 'val' ) ) );
		$this->assert_invalid_argument( fn() => $this->cache->set( array( 'valid_key' => 'val', '  ' => 'val' ) ) );
		$this->assertFalse( $this->cache->has( 'valid_key' ) );
	}

	public function test_set_scalar_without_value_throws_exception() {
		$this->assert_invalid_argument( fn() => $this->cache->set( 'missing_val_key' ) );

		$engine = LanguageEngine::get();
		$engine->reset();

		$this->expectException( \InvalidArgumentException::class );
		$engine->evaluate( "Cache.set('a')" );
	}

	public function test_set_scalar_with_explicit_null_is_allowed() {
		$key   = 'explicit_null_' . wp_rand();
		$group = 'unit_test_null';

		$this->assertTrue( $this->cache->set( $key, null, $group ) );
		$this->assertTrue( $this->cache->has( $key, $group ) );
		$this->assertNull( $this->cache->get( $key, $group ) );
	}

	private function assert_invalid_argument( callable $callback ) {
		try {
			$callback();
			$this->fail( 'Expected InvalidArgumentException was not thrown.' );
		} catch ( \InvalidArgumentException $e ) {
			$this->assertInstanceOf( \InvalidArgumentException::class, $e );
		}
	}
}
