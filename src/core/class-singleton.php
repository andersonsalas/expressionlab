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

namespace ExpressionLab\Core;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * Singleton trait.
 *
 * Provides a standardized implementation of the singleton pattern.
 *
 * @since 0.0.1
 * @internal
 *
 * @package ExpressionLab
 */
trait Singleton {
	/**
	 * The single instance of the class.
	 *
	 * @since 0.0.1
	 *
	 * @var static|null
	 */
	private static $instance = null;

	/**
	 * Retrieves the singleton instance of the class.
	 *
	 * @since 0.0.1
	 *
	 * @return static The singleton instance.
	 */
	public static function get() {
		if ( null === self::$instance ) {
			self::$instance = new static();
		}
		return self::$instance;
	}
}
