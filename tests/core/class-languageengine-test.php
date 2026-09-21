<?php

// phpcs:ignoreFile

use ExpressionLab\Core\LanguageEngine;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class LanguageEngineTest extends WP_UnitTestCase {
    /**
     * @var \ExpressionLab\Core\LanguageEngine $engine
     */
    private $engine;
    private $context;
    private $language;
    private $defined_functions;

    public function setUp(): void {
        parent::setUp();
        $this->engine = LanguageEngine::get()->reset();

        // Setup for internal inspection.
        $this->language = new ExpressionLanguage();
        
        $reflectionContext = new \ReflectionMethod($this->engine, 'get_context');
        $this->context = $reflectionContext->invoke($this->engine, $this->language);	
        
        $reflectionFunctions = new \ReflectionProperty($this->language, 'functions');
        $this->defined_functions = $reflectionFunctions->getValue($this->language);
    }


    public function test_engine_library_functions_are_accessible() {
        $this->assertEquals(4, $this->engine->evaluate("strlen('test')")['result']);
        $this->assertEquals('hello', $this->engine->evaluate("wp_strip_all_tags('<p>hello</p>')")['result']);
    }

    public function test_engine_library_constants_are_accessible() {
        $this->assertEquals(PHP_VERSION, $this->engine->evaluate("PHP_VERSION")['result']);
        $this->assertEquals(EXPRESSION_LAB_VERSION, $this->engine->evaluate("EXPRESSION_LAB_VERSION")['result']);
    }

    public function test_engine_declarative_functions_are_defined() {
        $this->assertArrayHasKey('strlen', $this->defined_functions);
        $this->assertArrayHasKey('wp_strip_all_tags', $this->defined_functions);
    }

    public function test_engine_imperative_constructs_are_not_defined() {
        $this->assertArrayNotHasKey('lambda', $this->defined_functions);
        $this->assertArrayNotHasKey('prog', $this->defined_functions);
        $this->assertArrayNotHasKey('loop', $this->defined_functions);
        $this->assertArrayNotHasKey('break', $this->defined_functions);
        $this->assertArrayNotHasKey('continue', $this->defined_functions);
        $this->assertArrayNotHasKey('return', $this->defined_functions);
        $this->assertArrayNotHasKey('set', $this->defined_functions);
        $this->assertArrayNotHasKey('set_once', $this->defined_functions);
        $this->assertArrayNotHasKey('isset', $this->defined_functions);
        $this->assertArrayNotHasKey('unset', $this->defined_functions);
        $this->assertArrayNotHasKey('show', $this->defined_functions);
    }

    public function test_engine_var_variable_is_defined() {
        $this->assertArrayHasKey('var', $this->context);
    }


    public function test_basic_arithmetic_operations() {
        $this->assertEquals(7, $this->engine->evaluate('1 + 2 * 3')['result']);
        $this->assertEquals(5, $this->engine->evaluate('10 / 2')['result']);
        $this->assertEquals(8, $this->engine->evaluate('2 ** 3')['result']);
        $this->assertEquals(1, $this->engine->evaluate('5 % 2')['result']);
    }

    public function test_boolean_and_comparison_logic() {
        $this->assertTrue($this->engine->evaluate('true and true')['result']);
        $this->assertFalse($this->engine->evaluate('true and false')['result']);
        $this->assertTrue($this->engine->evaluate('10 > 5 or 3 < 1')['result']);
        $this->assertTrue($this->engine->evaluate('not false')['result']);
        $this->assertTrue($this->engine->evaluate('10 >= 10')['result']);
        $this->assertTrue($this->engine->evaluate("'foo' === 'foo'")['result']);
    }

    public function test_ternary_operator() {
        $this->assertEquals('yes', $this->engine->evaluate("10 > 5 ? 'yes' : 'no'")['result']);
        $this->assertEquals('fallback', $this->engine->evaluate("null ?: 'fallback'")['result']);
    }

    public function test_string_concatenation() {
        $this->assertEquals('hello world', $this->engine->evaluate("'hello ' ~ 'world'")['result']);
    }

    public function test_arrays_and_objects() {
        $this->assertEquals(2, $this->engine->evaluate('[1, 2, 3][1]')['result']);
        $this->assertEquals('bar', $this->engine->evaluate("{'foo': 'bar'}['foo']")['result']);
        $this->assertTrue($this->engine->evaluate("'apple' in ['apple', 'banana']")['result']);
        $this->assertFalse($this->engine->evaluate("'cherry' in ['apple', 'banana']")['result']);
    }

    public function test_basic_library_objects_are_accessible() {
        $expression = "ExpressionLab.loopback('test_value_123')";
        $result = $this->engine->evaluate($expression);
        $this->assertEquals('test_value_123', $result['result']);
    }


    public function test_variables_can_be_set_and_retrieved() {
        $this->engine->evaluate("set['test_var', 'test_value']");
        $result = $this->engine->evaluate("var['test_var']");
        $this->assertEquals('test_value', $result['result']);

        $result = $this->engine->evaluate("prog[ set['foo', 'bar'], var['foo'] ]");
        $this->assertEquals('bar', $result['result']);
    }

    public function test_variables_can_be_overwritten() {
        $this->engine->evaluate("prog[ set['foo', 'bar'], set['foo', 'baz'], var['foo'] ]");
        $result = $this->engine->evaluate("var['foo']");
        $this->assertEquals('baz', $result['result']);
    }

    public function test_variable_isset_and_unset() {
        $this->engine->evaluate("set['item', 42]");
        $this->assertTrue($this->engine->evaluate("isset['item']")['result']);
        
        $this->engine->evaluate("unset['item']");
        $this->assertFalse($this->engine->evaluate("isset['item']")['result']);
        $this->assertNull($this->engine->evaluate("var['item']")['result']);
    }

    public function test_undefined_variable_generates_warning_and_returns_null() {
        $result = $this->engine->evaluate("var['non_existent_var']");
        $this->assertNull($result['result']);
        $this->assertNotEmpty($result['messages']);
        
        $warning_found = false;
        foreach ($result['messages'] as $message) {
            if ('warning' === $message['type'] && false !== strpos($message['text'], 'non_existent_var')) {
                $warning_found = true;
                break;
            }
        }
        $this->assertTrue($warning_found, 'Warning message for undefined variable was not found in messages.');
    }


    public function test_security_non_stdclass_objects_are_not_returned() {
        $result = $this->engine->evaluate('Database');
        $this->assertNull($result['result']);
    }

    public function test_security_constant_function_throws_exception() {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The "constant" function is disabled for security reasons.');
        $this->engine->evaluate("constant('DB_PASSWORD')");
    }

    public function test_security_clear_variable_returns_null() {
        $result = $this->engine->evaluate("clear");
        $this->assertNull($result['result']);
    }

    public function test_security_variable_names_cannot_start_with_number() {
        $this->expectException(\InvalidArgumentException::class);
        $this->engine->evaluate("set['123var','a']");
    }

    public function test_security_variable_names_cannot_contain_invalid_chars() {
        $this->expectException(\InvalidArgumentException::class);
        $this->engine->evaluate("set['my-var', 'val']");
    }

    public function test_security_empty_expression_throws_exception() {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No expression provided');
        $this->engine->evaluate('');
    }

    public function test_security_ast_nested_depth_limit() {
        $reflection = new \ReflectionMethod( $this->engine, 'validate_ast' );

        // Build a node deeply nested with 260 levels
        $root = new \Symfony\Component\ExpressionLanguage\Node\ConstantNode( 1 );
        for ( $i = 0; $i < 260; $i++ ) {
            $parent        = new \Symfony\Component\ExpressionLanguage\Node\ConstantNode( $i );
            $parent->nodes = array( $root );
            $root          = $parent;
        }

        $this->expectException( \RuntimeException::class );
        $this->expectExceptionMessage( 'AST validation failed: Expression is too deeply nested.' );
        $reflection->invoke( $this->engine, $root );
    }

    public function test_security_execution_gas_limit() {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Maximum number of operations exceeded');
        
        // Exhaust gas
        $this->engine->tick($remaining, 60000);
    }


    public function test_get_outline_structure() {
        $outline = $this->engine->get_outline();

        $this->assertArrayHasKey('functions', $outline);
        $this->assertArrayHasKey('objects', $outline);
        $this->assertArrayHasKey('constants', $outline);
        $this->assertArrayHasKey('typeRegistry', $outline);

        $this->assertArrayHasKey('strlen', $outline['functions']);
        $this->assertNotNull($outline['functions']['strlen']['doc'] ?? null);
        $this->assertNotNull($outline['functions']['strlen']['doc']['summary'] ?? null);
        $this->assertNotNull($outline['functions']['strlen']['doc']['description'] ?? null);
        $this->assertNotNull($outline['functions']['strlen']['doc']['signature'] ?? null);
        $this->assertNotNull($outline['functions']['strlen']['doc']['return'] ?? null);

        $this->assertArrayHasKey('Options', $outline['objects']);
        $this->assertNotNull($outline['objects']['Options']['methods']['get'] ?? null);
        $this->assertNotNull($outline['objects']['Options']['methods']['get']['doc']['summary'] ?? null);
        $this->assertNotNull($outline['objects']['Options']['methods']['get']['doc']['description'] ?? null);
        $this->assertNotNull($outline['objects']['Options']['methods']['get']['doc']['signature'] ?? null);
        $this->assertNotNull($outline['objects']['Options']['methods']['get']['doc']['return'] ?? null);

        $this->assertArrayHasKey('NetworkSites', $outline['objects']);
        $this->assertNotNull($outline['objects']['NetworkSites']['properties']['current'] ?? null);
        $this->assertContains('https://expressionlab.io/docs/api-reference/sites#networksitescurrent', $outline['objects']['NetworkSites']['properties']['current']['doc']['see'] ?? array());

        $this->assertArrayHasKey('Database', $outline['objects']);
        $this->assertNotNull($outline['objects']['Database']['methods']['mirror'] ?? null);
        $this->assertContains('https://expressionlab.io/docs/api-reference/database#databasemirror', $outline['objects']['Database']['methods']['mirror']['doc']['see'] ?? array());

        $this->assertArrayHasKey('EXPRESSION_LAB_VERSION', $outline['constants']);
        $this->assertNotNull($outline['constants']['EXPRESSION_LAB_VERSION'] ?? null);
    }

    public function test_add_system_message_accepts_update_type() {
        $this->engine->clear_system_messages();
        $this->engine->add_system_message( 'update', 'Test update message' );

        $messages = $this->engine->get_system_messages();
        $this->assertNotEmpty( $messages );
        $last = end( $messages );
        $this->assertSame( 'update', $last['type'] );
        $this->assertSame( 'Test update message', $last['text'] );
    }

    public function test_add_message_accepts_update_type() {
        $this->engine->clear_messages();
        $this->engine->add_message( 'update', 'Test execution update message' );

        $messages = $this->engine->get_messages();
        $this->assertCount( 1, $messages );
        $this->assertSame( 'update', $messages[0]['type'] );
        $this->assertSame( 'Test execution update message', $messages[0]['text'] );
    }

    public function test_collect_system_messages_includes_update_notification_when_update_available() {
        $this->engine->reset();

        set_site_transient(
            \ExpressionLab\Core\Updater::TRANSIENT_KEY,
            array(
                'version' => '99.0.0',
            ),
            HOUR_IN_SECONDS
        );

        try {
            $system_messages = $this->engine->get_system_messages();
            $this->assertNotEmpty( $system_messages );

            $update_messages = array_filter(
                $system_messages,
                function( $msg ) {
                    return 'update' === $msg['type'];
                }
            );

            $this->assertNotEmpty( $update_messages );
            $update_msg = reset( $update_messages );
            $this->assertSame( 'update', $update_msg['type'] );
            $this->assertStringContainsString( 'Update available: Version 99.0.0 is available', $update_msg['text'] );
            $this->assertStringContainsString( 'current: ' . EXPRESSION_LAB_VERSION, $update_msg['text'] );
        } finally {
            delete_site_transient( \ExpressionLab\Core\Updater::TRANSIENT_KEY );
            $this->engine->reset();
        }
    }

    public function test_collect_system_messages_does_not_include_debug_mode_warning_when_disabled() {
        $this->engine->reset();

        $this->assertFalse( \ExpressionLab\Core\Helper::is_debug_mode() );

        $system_messages = $this->engine->get_system_messages();

        $debug_messages = array_filter(
            $system_messages,
            function( $msg ) {
                return 'warning' === $msg['type'] && false !== strpos( $msg['text'], 'Debug mode is enabled' );
            }
        );

        $this->assertEmpty( $debug_messages );
    }

    public function test_magic_and_internal_methods_are_rejected() {
        $magic_methods = array(
            '__construct',
            '__destruct',
            '__clone',
            '__wakeup',
            '__sleep',
            '__serialize',
            '__unserialize',
            '__invoke',
            '__set_state',
            '__debugInfo',
        );

        foreach ( $magic_methods as $method ) {
            $thrown = false;
            try {
                $this->engine->evaluate( "Database.{$method}()" );
            } catch ( \Symfony\Component\ExpressionLanguage\SyntaxError $e ) {
                $thrown = true;
                $this->assertStringContainsString(
                    'Access to magic method or internal property',
                    $e->getMessage()
                );
                $this->assertStringContainsString(
                    $method,
                    $e->getMessage()
                );
            }

            $this->assertTrue( $thrown, "Expected SyntaxError when calling {$method}()" );
        }
    }

    public function test_magic_methods_are_rejected_case_insensitively() {
        $variants = array(
            'Database.__CONSTRUCT()',
            'Database.__Construct()',
            'Database.__DESTRUCT()',
            'Posts.__Clone()',
        );

        foreach ( $variants as $expr ) {
            $thrown = false;
            try {
                $this->engine->evaluate( $expr );
            } catch ( \Symfony\Component\ExpressionLanguage\SyntaxError $e ) {
                $thrown = true;
            }

            $this->assertTrue( $thrown, "Expected SyntaxError for expression: {$expr}" );
        }
    }

    public function test_magic_methods_are_rejected_with_nullsafe_operator() {
        $this->expectException( \Symfony\Component\ExpressionLanguage\SyntaxError::class );
        $this->expectExceptionMessage( 'Access to magic method or internal property' );
        $this->engine->evaluate( 'Database?.__construct()' );
    }

    public function test_magic_properties_are_rejected() {
        $this->expectException( \Symfony\Component\ExpressionLanguage\SyntaxError::class );
        $this->expectExceptionMessage( 'Access to magic method or internal property' );
        $this->engine->evaluate( 'Database.__construct' );
    }

    public function test_magic_methods_are_rejected_in_nested_expressions() {
        $this->expectException( \Symfony\Component\ExpressionLanguage\SyntaxError::class );
        $this->expectExceptionMessage( 'Access to magic method or internal property' );
        $this->engine->evaluate( 'prog[ set["db", Database], var["db"].__destruct() ]' );
    }

    public function test_legitimate_member_access_functions_normally() {
        $result = $this->engine->evaluate( "ExpressionLab.loopback('hello')" );
        $this->assertSame( 'hello', $result['result'] );

        $nullsafe_result = $this->engine->evaluate( "ExpressionLab?.loopback('world')" );
        $this->assertSame( 'world', $nullsafe_result['result'] );
    }
}