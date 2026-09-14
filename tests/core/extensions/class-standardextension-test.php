<?php

// phpcs:ignoreFile

use ExpressionLab\Core\Extensions\StandardExtension;
use ExpressionLab\Core\Interfaces\ExtensionInterface;

class StandardExtensionTest extends WP_UnitTestCase {

	public function test_implements_extension_interface() {
		$extension = new StandardExtension();
		$this->assertInstanceOf( ExtensionInterface::class, $extension );
	}

	public function test_get_functions_returns_array() {
		$extension = new StandardExtension();
		$this->assertIsArray( $extension->get_functions() );
	}

	public function test_get_constants_contains_version() {
		$extension = new StandardExtension();
		$constants = $extension->get_constants();

		$this->assertIsArray( $constants );
		$this->assertArrayHasKey( 'EXPRESSION_LAB_VERSION', $constants );
		$this->assertSame( EXPRESSION_LAB_VERSION, $constants['EXPRESSION_LAB_VERSION']['value'] );
	}
}
