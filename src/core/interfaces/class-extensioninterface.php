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

namespace ExpressionLab\Core\Interfaces;

/**
 * Extension interface.
 *
 * Represents a provider of functions and constants registered into the
 * expression language execution environment.
 *
 * @since 1.0.0
 * @package ExpressionLab
 */
interface ExtensionInterface {
	/**
	 * Retrieves the functions provided by the extension.
	 *
	 * @since 1.0.0
	 *
	 * @return array Associative array of function definitions and callbacks.
	 */
	public function get_functions(): array;

	/**
	 * Retrieves the constants provided by the extension.
	 *
	 * @since 1.0.0
	 *
	 * @return array Associative array of constant definitions and values.
	 */
	public function get_constants(): array;
}
