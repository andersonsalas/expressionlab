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

use ExpressionLab\Core\LanguageEngine;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * Console Service.
 *
 * Provides logging methods and interactive data table visualization capabilities directly inside
 * the Expression Lab console.
 *
 * @package ExpressionLab
 */
final class Console {
	/**
	 * Encodes data for console output.
	 *
	 * Handles various data types, including resources, objects, and primitives.
	 *
	 * @internal
	 *
	 * @param mixed $data The data to encode.
	 * @return mixed The encoded data suitable for console output.
	 */
	private function encode( $data ) {
		if ( is_resource( $data ) ) {
			return '<Resource>'; // Edge case. This should never occur.
		}

		if ( is_object( $data ) ) {
			if ( method_exists( $data, '__toString' ) ) {
				return (string) $data;
			}

			if ( 'stdClass' !== get_class( $data ) ) {
				return '<' . get_class( $data ) . '>';
			}
		}

		if ( is_string( $data ) || is_numeric( $data ) || is_bool( $data ) ) {
			return $data;
		}

		return wp_json_encode( $data, true );
	}

	/**
	 * Log an informational message (`info` level) to the Expression Lab console.
	 *
	 * Normalizes scalars, serializes arrays/stdClass to JSON, invokes `__toString()`
	 * on stringable objects, or labels custom objects with their class name before
	 * queuing the message into the execution runtime.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * Console.log('Initialization sequence completed')
	 * ```
	 *
	 * ```elscript
	 * Console.log(1024)
	 * ```
	 *
	 * ```elscript
	 * Console.log({
	 *      'status': 'healthy',
	 *      'memory_usage_mb': 42.5
	 * })
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/console#consolelog
	 *
	 * @param mixed $message The message payload to output (scalar, array, or object).
	 * @return void
	 */
	public function log( $message ) {
		LanguageEngine::get()->add_message( 'info', $this->encode( $message ) );
	}

	/**
	 * Log a warning message (`warning` level) to the Expression Lab console.
	 *
	 * Queues a warning diagnostic entry into the runtime output panel with automatic
	 * type normalization and JSON serialization for complex payloads.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * Console.warning('Autoload option size is approaching the 800 KB threshold')
	 * ```
	 *
	 * ```elscript
	 * Console.warning({
	 *      'warning': 'High memory usage',
	 *      'allocated_mb': 210,
	 *      'limit_mb': 256
	 * })
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/console#consolewarning
	 *
	 * @param mixed $message The warning message payload to output (scalar, array, or object).
	 * @return void
	 */
	public function warning( $message ) {
		LanguageEngine::get()->add_message( 'warning', $this->encode( $message ) );
	}

	/**
	 * Log an error message (`error` level) to the Expression Lab console.
	 *
	 * Queues an error diagnostic entry into the runtime output panel without aborting
	 * expression evaluation.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * Console.error('Target endpoint returned non-200 status code')
	 * ```
	 *
	 * ```elscript
	 * Console.error({
	 *      'error_code': 'REST_INVALID_ARGUMENT',
	 *      'field': 'email'
	 * })
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/console#consoleerror
	 *
	 * @param mixed $message The error message payload to output (scalar, array, or object).
	 * @return void
	 */
	public function error( $message ) {
		LanguageEngine::get()->add_message( 'error', $this->encode( $message ) );
	}

	/**
	 * Log a success message (`success` level) to the Expression Lab console.
	 *
	 * Queues a positive confirmation entry into the runtime output panel.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * Console.success('Database table mirrored successfully')
	 * ```
	 *
	 * ```elscript
	 * Console.success({
	 *      'updated': true,
	 *      'timestamp': 1725055200
	 * })
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/console#consolesuccess
	 *
	 * @param mixed $message The success message payload to output (scalar, array, or object).
	 * @return void
	 */
	public function success( $message ) {
		LanguageEngine::get()->add_message( 'success', $this->encode( $message ) );
	}

	/**
	 * Render an interactive data table visualization in the Expression Lab console.
	 *
	 * Supports two distinct input formats:
	 * * **Associative (Record) Format**: `arg1` is an array of associative arrays or objects.
	 *   Headers are dynamically extracted from the keys of the first item, subsequent missing keys
	 *   default to `null`, and `arg2` acts as the optional table title string (default `'Table'`).
	 * * **Positional (Matrix) Format**: `arg1` is an indexed array of header name strings,
	 *   `arg2` is a two-dimensional array of row values mapped by index, and `arg3`
	 *   provides the optional table title (default `'Table'`).
	 *
	 * Examples of associative format:
	 *
	 * ```elscript
	 * Console.table([
	 *      {
	 *          'id': 1,
	 *          'name': 'Alice',
	 *          'role': 'Administrator'
	 *      },
	 *      {
	 *          'id': 2,
	 *          'name': 'Bob',
	 *          'role': 'Editor'
	 *      }
	 * ], 'Team Members')
	 * ```
	 *
	 * ```elscript
	 * Console.table([
	 *      {
	 *          'option': 'blogname',
	 *          'value': 'Expression Lab'
	 *      },
	 *      {
	 *          'option': 'admin_email',
	 *          'value': 'admin@example.com'
	 *      }
	 * ], 'Options')
	 * ```
	 *
	 * Examples of positional format:
	 *
	 * ```elscript
	 * Console.table(
	 *      ['ID', 'Username', 'Email'],
	 *      [
	 *          [1, 'admin', 'admin@example.com'],
	 *          [2, 'editor', 'editor@example.com']
	 *      ],
	 *      'User Directory'
	 * )
	 * ```
	 *
	 * ```elscript
	 * Console.table(
	 *      ['Metric', 'Current', 'Threshold'],
	 *      [
	 *          ['Autoload Size', '450 KB', '800 KB'],
	 *          ['Total Tables', 12, 50]
	 *      ],
	 *      'Performance Metrics'
	 * )
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/console#consoletable
	 *
	 * @param array        $arg1  Array of associative records/objects (associative format) or indexed array of column header names (positional format).
	 * @param array|string $arg2  Table title string (associative format) or two-dimensional array of row values (positional format). Default empty array.
	 * @param string       $title Optional table title used when calling in positional format. Default 'Table'.
	 * @return void
	 * @throws \InvalidArgumentException If data is empty or positional values are missing/invalid.
	 */
	public function table( array $arg1, $arg2 = array(), string $title = 'Table' ) {
		if ( empty( $arg1 ) ) {
			throw new \InvalidArgumentException( 'Data cannot be empty.' );
		}

		$data           = array();
		$first_element  = reset( $arg1 );
		$is_associative = is_array( $first_element ) || is_object( $first_element );

		if ( $is_associative ) {
			// Format: Associative.
			$table_title = is_string( $arg2 ) ? $arg2 : ( empty( $arg2 ) ? 'Table' : $title );
			$headers     = array_keys( (array) $first_element );

			foreach ( $arg1 as $row ) {
				$row_array = (array) $row;
				$row_data  = array();
				foreach ( $headers as $header ) {
					$row_data[ $header ] = $row_array[ $header ] ?? null;
				}
				$data[] = $row_data;
			}
		} else {
			// Format: Positional.
			$headers     = $arg1;
			$values      = $arg2;
			$table_title = $title;

			if ( empty( $values ) || ! is_array( $values ) ) {
				throw new \InvalidArgumentException( 'Values cannot be empty when using positional headers.' );
			}

			foreach ( $values as $row ) {
				$row_data = array();
				foreach ( $headers as $index => $header ) {
					$row_data[ $header ] = $row[ $index ] ?? null;
				}
				$data[] = $row_data;
			}
		}

		LanguageEngine::get()->begin_visualization_group()
			->add_visualization(
				array(
					'type'  => 'table',
					'title' => $table_title,
					'data'  => $data,
				)
			);
	}
}
