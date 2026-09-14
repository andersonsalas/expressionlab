<?php

// phpcs:ignoreFile

use ExpressionLab\Core\LanguageEngine;


class MockCustomService {
    public $title = 'Service Title';

    /**
     * Greet method.
     *
     * @param string $name Name to greet.
     * @return string Greeting.
     */
    public function greet( string $name ): string {
        LanguageEngine::get()->tick();
        return 'Hello, ' . $name;
    }
}

class LanguageEngineHooksTest extends WP_UnitTestCase {
    private $engine;

    public function setUp(): void {
        parent::setUp();
        $this->engine = LanguageEngine::get()->reset();
    }

    public function tearDown(): void {
        $this->engine->reset();
        parent::tearDown();
    }

    public function test_are_hooks_enabled_reflects_configuration_constant() {
        $this->assertTrue( $this->engine->are_hooks_enabled() );
        $this->assertSame(
            defined( 'EXPRESSION_LAB_HOOKS_ENABLED' ) && (bool) constant( 'EXPRESSION_LAB_HOOKS_ENABLED' ),
            $this->engine->are_hooks_enabled()
        );
    }

    public function test_reserved_identifiers_cannot_be_overridden() {
        $this->assertTrue($this->engine->is_reserved_identifier('show'));
        $this->assertTrue($this->engine->is_reserved_identifier('set'));
        $this->assertTrue($this->engine->is_reserved_identifier('var'));
        $this->assertTrue($this->engine->is_reserved_identifier('clear'));
        $this->assertTrue($this->engine->is_reserved_identifier('constant'));
        $this->assertTrue($this->engine->is_reserved_identifier('tick'));
        $this->assertFalse($this->engine->is_reserved_identifier('my_custom_func'));

        // Attempt to override 'set' function via filter.
        add_filter('expressionlab_register_functions', function($funcs) {
            $funcs['set'] = function() { return 'hacked'; };
            return $funcs;
        });

        // Core 'set' should still work normally.
        $this->engine->evaluate("set['test_var', 42]");
        $this->assertEquals(42, $this->engine->get_variable('test_var'));
    }


    public function test_register_granular_function_via_hook_with_auto_tick() {
        add_filter('expressionlab_register_functions', function($funcs) {
            $funcs['add_five'] = array(
                'callback' => function($val) {
                    return $val + 5;
                },
                'docs' => array(
                    'summary' => 'Adds five to a number',
                    'parameters' => array(
                        'val' => array('type' => 'int', 'required' => true, 'description' => 'Input number')
                    ),
                    'return' => array('type' => 'int', 'description' => 'Output number')
                )
            );
            return $funcs;
        });

        $this->engine->reset();

        $result = $this->engine->evaluate('add_five(10)')['result'];
        $this->assertEquals(15, $result);

        // Check outline
        $outline = $this->engine->get_outline();
        $this->assertArrayHasKey('add_five', $outline['functions']);
        $this->assertEquals('Adds five to a number', $outline['functions']['add_five']['doc']['summary']);
    }

    public function test_register_granular_constant_and_object_via_hook() {
        add_filter('expressionlab_register_constants', function($constants) {
            $constants['MY_API_KEY'] = array(
                'value' => 'secret_123',
                'docs' => array('summary' => 'My API Key constant')
            );
            return $constants;
        });

        add_filter('expressionlab_register_objects', function($objects) {
            $objects['MyService'] = new MockCustomService();
            return $objects;
        });

        $this->engine->reset();

        $key = $this->engine->evaluate('MY_API_KEY')['result'];
        $this->assertEquals('secret_123', $key);

        $greeting = $this->engine->evaluate("MyService.greet('Anderson')")['result'];
        $this->assertEquals('Hello, Anderson', $greeting);

        $prop = $this->engine->evaluate("MyService.title")['result'];
        $this->assertEquals('Service Title', $prop);

        // Check outline for object and constant
        $outline = $this->engine->get_outline();
        $this->assertArrayHasKey('MY_API_KEY', $outline['constants']);
        $this->assertArrayHasKey('MyService', $outline['objects']);
        $this->assertArrayHasKey('greet', $outline['objects']['MyService']['methods']);
    }

    public function test_register_constant_must_be_scalar() {
        add_filter('expressionlab_register_constants', function($constants) {
            $constants['VALID_INT'] = 42;
            $constants['VALID_STR'] = 'hello';
            $constants['INVALID_ARRAY'] = array('foo' => 'bar');
            $constants['INVALID_OBJ'] = (object) array('a' => 1);
            $constants['INVALID_NESTED'] = array(
                'value' => array(1, 2, 3),
                'docs'  => array('summary' => 'Invalid array value'),
            );
            return $constants;
        });

        $this->engine->reset();

        // Valid constants should evaluate correctly.
        $this->assertEquals(42, $this->engine->evaluate('VALID_INT')['result']);
        $this->assertEquals('hello', $this->engine->evaluate('VALID_STR')['result']);

        // Invalid constants should not be in the context (throws SyntaxError).
        $syntax_error_thrown = false;
        try {
            $this->engine->evaluate('INVALID_ARRAY');
        } catch (\Symfony\Component\ExpressionLanguage\SyntaxError $e) {
            $syntax_error_thrown = true;
        }
        $this->assertTrue($syntax_error_thrown, 'Expected SyntaxError when accessing rejected non-scalar constant.');

        // Check system messages.
        $system_messages = $this->engine->get_system_messages();
        $scalar_warnings = array_filter($system_messages, function($msg) {
            return strpos($msg['text'], 'must be a scalar') !== false;
        });
        $this->assertCount(3, $scalar_warnings);

        // Check outline excludes invalid constants.
        $outline = $this->engine->get_outline();
        $this->assertArrayHasKey('VALID_INT', $outline['constants']);
        $this->assertArrayHasKey('VALID_STR', $outline['constants']);
        $this->assertArrayNotHasKey('INVALID_ARRAY', $outline['constants']);
        $this->assertArrayNotHasKey('INVALID_OBJ', $outline['constants']);
        $this->assertArrayNotHasKey('INVALID_NESTED', $outline['constants']);
    }

    public function test_lifecycle_actions_are_triggered() {
        $before_triggered = false;
        $after_triggered = false;
        $captured_expression = '';
        $captured_result = null;

        add_action('expressionlab_before_evaluate', function($expr, $context) use (&$before_triggered, &$captured_expression) {
            $before_triggered = true;
            $captured_expression = $expr;
        }, 10, 2);

        add_action('expressionlab_after_evaluate', function($result, $time, $ops) use (&$after_triggered, &$captured_result) {
            $after_triggered = true;
            $captured_result = $result;
        }, 10, 3);

        $eval = $this->engine->evaluate('100 + 200');

        $this->assertTrue($before_triggered);
        $this->assertEquals('100 + 200', $captured_expression);
        $this->assertTrue($after_triggered);
        $this->assertEquals(300, $captured_result);
    }

    public function test_core_objects_are_reserved_and_cannot_be_overridden() {
        $this->assertTrue($this->engine->is_reserved_identifier('Database'));
        $this->assertTrue($this->engine->is_reserved_identifier('database'));
        $this->assertTrue($this->engine->is_reserved_identifier('DATABASE'));
        $this->assertTrue($this->engine->is_reserved_identifier('Options'));
        $this->assertTrue($this->engine->is_reserved_identifier('Users'));
        $this->assertTrue($this->engine->is_reserved_identifier('Posts'));
        $this->assertTrue($this->engine->is_reserved_identifier('Media'));
        $this->assertTrue($this->engine->is_reserved_identifier('Sites'));
        $this->assertTrue($this->engine->is_reserved_identifier('Files'));
        $this->assertTrue($this->engine->is_reserved_identifier('Http'));
        $this->assertTrue($this->engine->is_reserved_identifier('Console'));
        $this->assertTrue($this->engine->is_reserved_identifier('NetworkOptions'));
        $this->assertTrue($this->engine->is_reserved_identifier('NetworkSites'));

        // Attempt to hijack 'Database' object via hook.
        add_filter('expressionlab_register_objects', function($objects) {
            $objects['Database'] = new MockCustomService();
            return $objects;
        });

        $this->engine->reset();

        // Database should remain the native Database library instance, not MockCustomService.
        $eval = $this->engine->evaluate("Database.compile('posts')");
        $this->assertIsString($eval['result']);
        $this->assertStringContainsString('SELECT', $eval['result']);

        // Verify warning message is generated.
        $messages = $this->engine->get_messages();
        $warning_found = false;
        foreach ($messages as $msg) {
            if ($msg['type'] === 'warning' && strpos($msg['text'], 'Database') !== false) {
                $warning_found = true;
                break;
            }
        }
        $this->assertTrue($warning_found, 'Expected a warning message when attempting to override reserved Database object.');
    }

    public function test_core_objects_cannot_be_polluted_in_outline() {
        // Attempt to inject custom methods into 'Database' object in outline.
        add_filter('expressionlab_register_objects', function($objects) {
            $objects['Database'] = new MockCustomService();
            return $objects;
        });

        $this->engine->reset();
        $outline = $this->engine->get_outline();

        // Outline for Database must exist and contain native methods.
        $this->assertArrayHasKey('Database', $outline['objects']);
        $this->assertArrayHasKey('mirror', $outline['objects']['Database']['methods']);
        $this->assertArrayHasKey('table_list', $outline['objects']['Database']['methods']);

        // Custom method 'greet' from MockCustomService MUST NOT be merged into Database methods.
        $this->assertArrayNotHasKey('greet', $outline['objects']['Database']['methods']);
    }

    public function test_get_system_messages() {
        add_filter('expressionlab_register_objects', function($objects) {
            $objects['Database'] = new MockCustomService();
            return $objects;
        });

        add_filter('expressionlab_register_functions', function($funcs) {
            $funcs['set'] = function() { return 1; };
            return $funcs;
        });

        $system_messages = $this->engine->get_system_messages();
        $this->assertCount(3, $system_messages);
        $this->assertEquals('warning', $system_messages[0]['type']);
        $this->assertStringContainsString('Hooks enabled', $system_messages[0]['text']);
        $this->assertEquals('warning', $system_messages[1]['type']);
        $this->assertStringContainsString('Database', $system_messages[1]['text']);
        $this->assertEquals('warning', $system_messages[2]['type']);
        $this->assertStringContainsString('set', $system_messages[2]['text']);
    }

    public function test_console_banners_filter() {
        add_filter('expressionlab_console_banners', function($banner) {
            $banner[] = array(
                'type' => 'info',
                'text' => 'Custom plugin notice: Everything is running smoothly.',
            );
            return $banner;
        });

        $banner = array(
            array('type' => 'info', 'text' => 'Expression Lab'),
        );

        $banner = apply_filters('expressionlab_console_banners', $banner);
        $this->assertCount(2, $banner);
        $this->assertEquals('Custom plugin notice: Everything is running smoothly.', $banner[1]['text']);
    }
}
