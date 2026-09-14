<?php

// phpcs:ignoreFile

use ExpressionLab\Core\Extensions\PhpExtension;
use ExpressionLab\Core\Interfaces\ExtensionInterface;
use ExpressionLab\Core\LanguageEngine;

class PhpExtensionTest extends WP_UnitTestCase {
	/**
	 * @var LanguageEngine
	 */
	private $engine;

	public function setUp(): void {
		parent::setUp();
		$this->engine = LanguageEngine::get()->reset();
	}

	public function test_implements_extension_interface() {
		$extension = new PhpExtension();
		$this->assertInstanceOf( ExtensionInterface::class, $extension );
	}

	public function test_get_functions_returns_standard_php_functions() {
		$extension = new PhpExtension();
		$functions = $extension->get_functions();

		$this->assertIsArray( $functions );
		$this->assertArrayHasKey( 'strlen', $functions );
		$this->assertArrayHasKey( 'substr', $functions );
		$this->assertArrayHasKey( 'strtoupper', $functions );
		$this->assertArrayHasKey( 'strtolower', $functions );
		$this->assertArrayHasKey( 'abs', $functions );
		$this->assertArrayHasKey( 'round', $functions );
		$this->assertArrayHasKey( 'json_encode', $functions );
		$this->assertArrayHasKey( 'json_decode', $functions );
		$this->assertArrayHasKey( 'explode', $functions );
		$this->assertArrayHasKey( 'sprintf', $functions );
		$this->assertArrayHasKey( 'regex_match', $functions );
		$this->assertArrayHasKey( 'regex_match_all', $functions );
		$this->assertArrayHasKey( 'regex_replace', $functions );
		$this->assertArrayHasKey( 'first', $functions );
		$this->assertArrayHasKey( 'last', $functions );
		$this->assertArrayHasKey( 'array_key_first', $functions );
		$this->assertArrayHasKey( 'array_key_last', $functions );
		$this->assertArrayHasKey( 'array_chunk', $functions );
		$this->assertArrayHasKey( 'array_combine', $functions );
		$this->assertArrayHasKey( 'str_contains', $functions );
		$this->assertArrayHasKey( 'str_starts_with', $functions );
		$this->assertArrayHasKey( 'str_ends_with', $functions );
		$this->assertArrayHasKey( 'stripos', $functions );
		$this->assertArrayHasKey( 'substr_count', $functions );
		$this->assertArrayHasKey( 'urlencode', $functions );
		$this->assertArrayHasKey( 'urldecode', $functions );
		$this->assertArrayHasKey( 'mb_strlen', $functions );
		$this->assertArrayHasKey( 'mb_substr', $functions );
		$this->assertArrayHasKey( 'mb_strpos', $functions );
		$this->assertArrayHasKey( 'mb_strtolower', $functions );
		$this->assertArrayHasKey( 'mb_strtoupper', $functions );
		$this->assertArrayHasKey( 'base64_encode', $functions );
		$this->assertArrayHasKey( 'base64_decode', $functions );
		$this->assertArrayHasKey( 'hash', $functions );
		$this->assertArrayHasKey( 'hash_hmac', $functions );
		$this->assertArrayHasKey( 'ini_get', $functions );
		$this->assertArrayHasKey( 'memory_get_usage', $functions );
		$this->assertArrayHasKey( 'memory_get_peak_usage', $functions );
		$this->assertArrayHasKey( 'extension_loaded', $functions );
		$this->assertArrayHasKey( 'phpversion', $functions );
		$this->assertArrayHasKey( 'version_compare', $functions );
		$this->assertArrayHasKey( 'mt_rand', $functions );
		$this->assertArrayHasKey( 'number_format', $functions );
	}

	public function test_get_constants_returns_array() {
		$extension = new PhpExtension();
		$constants = $extension->get_constants();
		$this->assertIsArray( $constants );
		$this->assertArrayHasKey( 'PREG_PATTERN_ORDER', $constants );
		$this->assertArrayHasKey( 'PREG_SET_ORDER', $constants );
	}

	public function test_first_and_last_evaluation() {
		$this->assertSame( 10, $this->engine->evaluate( 'first([10, 20, 30])' )['result'] );
		$this->assertNull( $this->engine->evaluate( 'first([])' )['result'] );
		$this->assertSame( 30, $this->engine->evaluate( 'last([10, 20, 30])' )['result'] );
		$this->assertNull( $this->engine->evaluate( 'last([])' )['result'] );
	}

	public function test_string_modern_functions() {
		$this->assertTrue( $this->engine->evaluate( "str_contains('hello world', 'world')" )['result'] );
		$this->assertFalse( $this->engine->evaluate( "str_contains('hello world', 'xyz')" )['result'] );
		$this->assertTrue( $this->engine->evaluate( "str_starts_with('hello world', 'hello')" )['result'] );
		$this->assertTrue( $this->engine->evaluate( "str_ends_with('hello world', 'world')" )['result'] );
		$this->assertSame( 6, $this->engine->evaluate( "stripos('Hello World', 'WORLD')" )['result'] );
		$this->assertSame( 3, $this->engine->evaluate( "substr_count('abc abc abc', 'abc')" )['result'] );
		$this->assertSame( 'a+b%2Bc', $this->engine->evaluate( "urlencode('a b+c')" )['result'] );
		$this->assertSame( 'a b+c', $this->engine->evaluate( "urldecode('a+b%2Bc')" )['result'] );
	}

	public function test_multibyte_string_functions() {
		$this->assertSame( 7, $this->engine->evaluate( "mb_strlen('canción')" )['result'] );
		$this->assertSame( 'canc', $this->engine->evaluate( "mb_substr('canción', 0, 4)" )['result'] );
		$this->assertSame( 3, $this->engine->evaluate( "mb_strpos('canción', 'ción')" )['result'] );
		$this->assertSame( 'canción', $this->engine->evaluate( "mb_strtolower('CANCIÓN')" )['result'] );
		$this->assertSame( 'CANCIÓN', $this->engine->evaluate( "mb_strtoupper('canción')" )['result'] );
	}

	public function test_array_utilities() {
		$this->assertSame( 'a', $this->engine->evaluate( "array_key_first({'a': 1, 'b': 2})" )['result'] );
		$this->assertSame( 'b', $this->engine->evaluate( "array_key_last({'a': 1, 'b': 2})" )['result'] );
		$this->assertSame( array( array( 1, 2 ), array( 3, 4 ) ), $this->engine->evaluate( 'array_chunk([1, 2, 3, 4], 2)' )['result'] );
		$this->assertSame( array( 'x' => 10, 'y' => 20 ), $this->engine->evaluate( "array_combine(['x', 'y'], [10, 20])" )['result'] );
	}

	public function test_explode_safety_limit() {
		$res = $this->engine->evaluate( "explode(',', 'a,b,c')" )['result'];
		$this->assertSame( array( 'a', 'b', 'c' ), $res );
	}

	public function test_json_decode_associative_by_default() {
		$res = $this->engine->evaluate( "json_decode('{\"name\":\"ExpressionLab\",\"active\":true}')" )['result'];
		$this->assertIsArray( $res );
		$this->assertSame( 'ExpressionLab', $res['name'] );
		$this->assertTrue( $res['active'] );
	}

	public function test_regex_match_and_all() {
		$res = $this->engine->evaluate( "regex_match('/[0-9]+/', 'item456price')" )['result'];
		$this->assertIsArray( $res );
		$this->assertSame( '456', $res[0] );

		$res_all = $this->engine->evaluate( "regex_match_all('/[0-9]/', 'a1b2c3')" )['result'];
		$this->assertIsArray( $res_all );
		$this->assertSame( array( '1', '2', '3' ), $res_all[0] );
	}

	public function test_cryptography_and_diagnostics() {
		$this->assertSame( 'dGVzdA==', $this->engine->evaluate( "base64_encode('test')" )['result'] );
		$this->assertSame( 'test', $this->engine->evaluate( "base64_decode('dGVzdA==')" )['result'] );
		$this->assertSame( hash( 'sha256', 'hello' ), $this->engine->evaluate( "hash('sha256', 'hello')" )['result'] );
		$this->assertSame( hash_hmac( 'sha256', 'hello', 'secret' ), $this->engine->evaluate( "hash_hmac('sha256', 'hello', 'secret')" )['result'] );
		$this->assertIsInt( $this->engine->evaluate( 'memory_get_usage()' )['result'] );
		$this->assertIsInt( $this->engine->evaluate( 'memory_get_peak_usage()' )['result'] );
		$this->assertTrue( $this->engine->evaluate( "extension_loaded('json')" )['result'] );
		$this->assertIsString( $this->engine->evaluate( 'phpversion()' )['result'] );
	}

	public function test_ini_get_whitelist() {
		$this->assertNotEmpty( $this->engine->evaluate( "ini_get('memory_limit')" )['result'] );
		$this->assertFalse( $this->engine->evaluate( "ini_get('open_basedir_or_secret_xyz')" )['result'] );
	}

	public function test_sprintf_catches_errors() {
		$res = $this->engine->evaluate( "sprintf('Hello %s', 'world')" )['result'];
		$this->assertSame( 'Hello world', $res );
	}

	public function test_version_compare() {
		$this->assertSame( -1, $this->engine->evaluate( "version_compare('1.0.0', '2.0.0')" )['result'] );
		$this->assertSame( 1, $this->engine->evaluate( "version_compare('2.0.0', '1.0.0')" )['result'] );
		$this->assertSame( 0, $this->engine->evaluate( "version_compare('1.0.0', '1.0.0')" )['result'] );
		$this->assertTrue( $this->engine->evaluate( "version_compare('1.2.0', '1.1.0', '>')" )['result'] );
		$this->assertFalse( $this->engine->evaluate( "version_compare('1.0.0', '2.0.0', '>=')" )['result'] );
		$this->assertTrue( $this->engine->evaluate( "version_compare(PHP_VERSION, '5.0.0', '>=')" )['result'] );
	}
}
