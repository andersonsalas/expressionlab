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

namespace ExpressionLab\Core\Services;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * ExpressionLab Service.
 *
 * Internal class for unit testing.
 *
 * @internal
 * @package ExpressionLab
 */
class ExpressionLab {
	/**
	 * Returns the input value directly for engine testing.
	 *
	 * @internal
	 *
	 * @param mixed $value The value to return.
	 * @return mixed The input value.
	 */
	public function loopback( $value ) {
		return $value;
	}
}
