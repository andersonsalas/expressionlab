<?php

// phpcs:ignoreFile

use ExpressionLab\Core\Loader;
use ExpressionLab\Admin\Pages\Onboarding;
use ExpressionLab\Admin\Pages\Console;

class LoaderTest extends WP_UnitTestCase {

	public function test_loader_is_singleton() {
		$loader1 = Loader::get();
		$loader2 = Loader::get();

		$this->assertSame( $loader1, $loader2 );
	}

	public function test_loader_boot_attaches_hooks_and_instantiates_pages() {
		$loader = Loader::get();
		$loader->boot();

		$this->assertNotFalse(
			has_action( 'init', array( $loader, 'load_textdomain' ) ),
			'Loader::boot() must register load_textdomain on the init hook.'
		);

		$this->assertInstanceOf( Onboarding::class, Onboarding::get() );
		$this->assertInstanceOf( Console::class, Console::get() );
	}

	public function test_load_textdomain_executes_without_errors() {
		$loader = Loader::get();

		// Should complete without throwing warnings or exceptions.
		$loader->load_textdomain();
		$this->assertTrue( true );
	}
}
