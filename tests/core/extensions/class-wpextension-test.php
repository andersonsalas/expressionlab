<?php

// phpcs:ignoreFile

use ExpressionLab\Core\Extensions\WpExtension;
use ExpressionLab\Core\Interfaces\ExtensionInterface;
use ExpressionLab\Core\LanguageEngine;

class WpExtensionTest extends WP_UnitTestCase {
	/**
	 * @var LanguageEngine
	 */
	private $engine;

	public function setUp(): void {
		parent::setUp();
		$this->engine = LanguageEngine::get()->reset();
	}

	public function test_implements_extension_interface() {
		$extension = new WpExtension();
		$this->assertInstanceOf( ExtensionInterface::class, $extension );
	}

	public function test_get_functions_returns_wp_functions() {
		$extension = new WpExtension();
		$functions = $extension->get_functions();

		$this->assertIsArray( $functions );
		$this->assertArrayHasKey( 'wp_json_encode', $functions );
		$this->assertArrayHasKey( 'wp_strip_all_tags', $functions );
		$this->assertArrayHasKey( 'wp_specialchars_decode', $functions );
		$this->assertArrayHasKey( 'wp_unslash', $functions );
		$this->assertArrayHasKey( 'wp_slash', $functions );
		$this->assertArrayHasKey( 'wp_parse_url', $functions );
		$this->assertArrayHasKey( 'add_query_arg', $functions );
		$this->assertArrayHasKey( 'remove_query_arg', $functions );
		$this->assertArrayHasKey( 'wp_list_pluck', $functions );
		$this->assertArrayHasKey( 'is_serialized', $functions );
		$this->assertArrayHasKey( 'is_email', $functions );
		$this->assertArrayHasKey( 'sanitize_key', $functions );
		$this->assertArrayHasKey( 'sanitize_title', $functions );
		$this->assertArrayHasKey( 'wp_basename', $functions );
		$this->assertArrayHasKey( 'is_multisite', $functions );
		$this->assertArrayHasKey( 'wp_get_environment_type', $functions );
	}

	public function test_get_constants_contains_time_constants() {
		$extension = new WpExtension();
		$constants = $extension->get_constants();

		$this->assertIsArray( $constants );
		$this->assertArrayHasKey( 'HOUR_IN_SECONDS', $constants );
		$this->assertArrayHasKey( 'DAY_IN_SECONDS', $constants );
		$this->assertArrayHasKey( 'WEEK_IN_SECONDS', $constants );
		$this->assertArrayHasKey( 'MONTH_IN_SECONDS', $constants );
		$this->assertArrayHasKey( 'YEAR_IN_SECONDS', $constants );
		$this->assertSame( HOUR_IN_SECONDS, $constants['HOUR_IN_SECONDS']['value'] );
	}

	public function test_wp_functions_evaluation() {
		// wp_strip_all_tags strips <style> and <script> contents as well.
		$this->assertSame( 'Hello World', $this->engine->evaluate( "wp_strip_all_tags('<style>body{color:red;}</style>Hello <b>World</b>')" )['result'] );

		// wp_parse_url
		$parsed = $this->engine->evaluate( "wp_parse_url('https://example.com/test?a=1')" )['result'];
		$this->assertSame( 'example.com', $parsed['host'] );
		$this->assertSame( '/test', $parsed['path'] );

		// add_query_arg & remove_query_arg
		$this->assertSame( 'https://example.com?foo=bar', $this->engine->evaluate( "add_query_arg('foo', 'bar', 'https://example.com')" )['result'] );
		$this->assertSame( 'https://example.com?baz=1', $this->engine->evaluate( "remove_query_arg('foo', 'https://example.com?foo=bar&baz=1')" )['result'] );

		// wp_list_pluck
		$plucked = $this->engine->evaluate( "wp_list_pluck([{'id': 1, 'name': 'Alice'}, {'id': 2, 'name': 'Bob'}], 'name')" )['result'];
		$this->assertSame( array( 'Alice', 'Bob' ), $plucked );

		// is_serialized
		$this->assertTrue( $this->engine->evaluate( "is_serialized('a:1:{i:0;s:3:\"foo\";}')" )['result'] );
		$this->assertFalse( $this->engine->evaluate( "is_serialized('just a normal string')" )['result'] );

		// is_email
		$this->assertSame( 'admin@example.com', $this->engine->evaluate( "is_email('admin@example.com')" )['result'] );
		$this->assertFalse( $this->engine->evaluate( "is_email('invalid-email')" )['result'] );

		// sanitize_key & sanitize_title
		$this->assertSame( 'mykey', $this->engine->evaluate( "sanitize_key('My Key!')" )['result'] );
		$this->assertSame( 'my-post-slug', $this->engine->evaluate( "sanitize_title('My Post Slug!')" )['result'] );

		// wp_basename
		$this->assertSame( 'image.png', $this->engine->evaluate( "wp_basename('/uploads/2026/09/image.png')" )['result'] );

		// wp_slash & wp_unslash
		$this->assertSame( "It\'s a test", $this->engine->evaluate( 'wp_slash("It\'s a test")' )['result'] );
		$this->assertSame( "It\'s a test", $this->engine->evaluate( "wp_slash('It\\'s a test')" )['result'] );
		$this->assertSame( "It's a test", $this->engine->evaluate( "wp_unslash('It\\\\\\'s a test')" )['result'] );

		// Environment predicates
		$this->assertIsBool( $this->engine->evaluate( 'is_multisite()' )['result'] );
		$this->assertIsString( $this->engine->evaluate( 'wp_get_environment_type()' )['result'] );
	}
}
