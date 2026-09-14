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

namespace ExpressionLab\Core\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * Security exception class.
 *
 * Thrown when authorization, nonce verification, cryptographic signatures,
 * or environment security prerequisites fail during request processing.
 *
 * @since 1.0.0
 * @internal
 *
 * @package ExpressionLab
 */
class SecurityException extends \Exception {

	/**
	 * HTTP status code associated with the security failure.
	 *
	 * @var int
	 */
	protected $status_code;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string          $message     The translated error message.
	 * @param int             $status_code The HTTP response status code (e.g. 400, 403, 500).
	 * @param int             $code        Optional. Numeric error code. Defaults to the status code.
	 * @param \Throwable|null $previous    Optional. The previous throwable used for exception chaining.
	 */
	public function __construct( string $message = '', int $status_code = 403, int $code = 0, ?\Throwable $previous = null ) {
		$numeric_code = 0 !== $code ? $code : $status_code;
		parent::__construct( $message, $numeric_code, $previous );
		$this->status_code = $status_code;
	}

	/**
	 * Retrieves the HTTP status code.
	 *
	 * @since 1.0.0
	 *
	 * @return int The HTTP status code.
	 */
	public function get_status_code(): int {
		return $this->status_code;
	}
}
