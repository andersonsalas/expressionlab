<?php

// phpcs:ignoreFile

use ExpressionLab\Admin\Pages\Console;
use ExpressionLab\Core\Helper;

class AdminBarTest extends WP_UnitTestCase {
    /**
     * @var \WP_Admin_Bar
     */
    private $admin_bar;

    public function setUp(): void {
        parent::setUp();
        require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';
        $this->admin_bar = new WP_Admin_Bar();
        $this->admin_bar->initialize();
    }

    public function tearDown(): void {
        unset( $_GET['page'] );
        set_current_screen( 'front' );
        parent::tearDown();
    }

    public function test_helper_is_debug_mode_returns_boolean() {
        $this->assertIsBool( Helper::is_debug_mode() );
    }

    public function test_is_current_screen_detects_expressionlab_screen() {
        set_current_screen( 'tools_page_expressionlab' );
        $_GET['page'] = 'expressionlab';
        $this->assertTrue( Helper::is_current_screen() );

        set_current_screen( 'edit-post' );
        unset( $_GET['page'] );
        $this->assertFalse( Helper::is_current_screen() );

        set_current_screen( 'dashboard' );
        unset( $_GET['page'] );
        $this->assertFalse( Helper::is_current_screen() );
    }

    public function test_badge_not_added_when_debug_mode_disabled() {
        $this->assertFalse( EXPRESSION_LAB_DEBUG_MODE );

        set_current_screen( 'tools_page_expressionlab' );
        $_GET['page'] = 'expressionlab';

        $console = Console::get();
        $console->add_debug_admin_bar_badge( $this->admin_bar );

        $node = $this->admin_bar->get_node( 'expressionlab-debug-badge' );
        $this->assertNull( $node );
    }
}
