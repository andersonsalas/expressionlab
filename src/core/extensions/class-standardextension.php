<?php
/**
 * This file is part of the Expression Lab plugin.
 *
 * (c) Anderson Salas <github@andersonsalas.com>
 *
 * See the LICENSE file for license information.
 *
 * @package ExpressionLab
 */

namespace ExpressionLab\Core\Extensions;

use ExpressionLab\Core\Interfaces\ExtensionInterface;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * Standard extension class.
 *
 * Registers core engine constants and default language definitions.
 *
 * @since 1.0.0
 * @package ExpressionLab
 */
class StandardExtension implements ExtensionInterface {
	/**
	 * Retrieves the functions provided by the standard extension.
	 *
	 * @since 1.0.0
	 *
	 * @return array Empty array as standard functions are registered directly by the engine.
	 */
	public function get_functions(): array {
		return array();
	}

	/**
	 * Retrieves the constants provided by the standard extension.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array{docs: array, value: mixed}> Associative array of constant definitions and values.
	 */
	public function get_constants(): array {
		return array(
			'EXPRESSION_LAB_VERSION' => array(
				'docs'  => array(
					'summary' => 'The current Expression Lab version as a string',
					'type'    => 'string',
				),
				'value' => EXPRESSION_LAB_VERSION,
			),
		);
	}
}
