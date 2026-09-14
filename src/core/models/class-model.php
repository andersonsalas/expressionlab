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

namespace ExpressionLab\Core\Models;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * Base model class.
 *
 * Provides the fundamental interface and default serialization representation
 * for entities within the expression language.
 *
 * @since 1.0.0
 * @package ExpressionLab
 */
class Model {

	/**
	 * Retrieves the value represented by this model.
	 *
	 * Designed to be overridden by subclasses to return the underlying data.
	 *
	 * @since 1.0.0
	 *
	 * @return mixed The value represented by this model, or `null` by default.
	 */
	public function get_value() {
		return null;
	}
}
