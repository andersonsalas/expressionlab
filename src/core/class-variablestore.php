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
 * Variable store class.
 *
 * Encapsulates user-defined session variables within the expression evaluation
 * context. Supports simple variable names as well as nested dot-separated path
 * manipulation (assoc-in, get-in, has-in, dissoc-in).
 *
 * @since 1.0.0
 *
 * @package ExpressionLab
 */
class VariableStore {

	/**
	 * In-memory variable storage.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	private array $variables = array();

	/**
	 * Retrieves all stored variables.
	 *
	 * @since 1.0.0
	 *
	 * @return array The associative array of all stored variables.
	 */
	public function all(): array {
		return $this->variables;
	}

	/**
	 * Retrieves a variable or nested path value.
	 *
	 * Supports both simple variable names ('foo') and dot-separated paths ('foo.bar.baz').
	 *
	 * @since 1.0.0
	 *
	 * @param string $name          The variable name or path.
	 * @param mixed  $default_value Optional. The default value to return if not found. Default null.
	 * @return mixed The variable value or default value.
	 *
	 * @throws \InvalidArgumentException If the path syntax is invalid.
	 */
	public function get( string $name, $default_value = null ) {
		$segments = $this->validate_path_segments( $name );
		$root     = array_shift( $segments );

		if ( ! array_key_exists( $root, $this->variables ) ) {
			return $default_value;
		}

		if ( empty( $segments ) ) {
			return $this->variables[ $root ];
		}

		return $this->get_in( $this->variables[ $root ], $segments, $default_value );
	}

	/**
	 * Sets a variable or nested path value.
	 *
	 * Supports both simple variable names ('foo') and dot-separated paths ('foo.bar.baz').
	 *
	 * @since 1.0.0
	 *
	 * @param string $name  The variable name or path.
	 * @param mixed  $value The value to assign.
	 *
	 * @throws \InvalidArgumentException If the name/path or value is invalid.
	 */
	public function set( string $name, $value ): void {
		$segments = $this->validate_path_segments( $name );
		$root     = array_shift( $segments );

		if ( empty( $segments ) ) {
			$this->validate_variable_value( $value );
			$this->variables[ $root ] = $value;
			return;
		}

		$current_root = $this->variables[ $root ] ?? array();
		$updated_root = $this->assoc_in( $current_root, $segments, $value );

		$this->validate_variable_value( $updated_root );
		$this->variables[ $root ] = $updated_root;
	}

	/**
	 * Checks if a variable or nested path exists.
	 *
	 * Supports both simple variable names ('foo') and dot-separated paths ('foo.bar.baz').
	 *
	 * @since 1.0.0
	 *
	 * @param string $name The variable name or path.
	 * @return bool True if the variable or path exists, false otherwise.
	 */
	public function has( string $name ): bool {
		try {
			$segments = $this->validate_path_segments( $name );
		} catch ( \InvalidArgumentException $e ) {
			return false;
		}

		$root = array_shift( $segments );

		if ( ! array_key_exists( $root, $this->variables ) ) {
			return false;
		}

		if ( empty( $segments ) ) {
			return true;
		}

		return $this->has_in( $this->variables[ $root ], $segments );
	}

	/**
	 * Deletes a variable or nested path leaf.
	 *
	 * Supports both simple variable names ('foo') and dot-separated paths ('foo.bar.baz').
	 *
	 * @since 1.0.0
	 *
	 * @param string $name The variable name or path.
	 *
	 * @throws \InvalidArgumentException If the path syntax is invalid.
	 */
	public function delete( string $name ): void {
		$segments = $this->validate_path_segments( $name );
		$root     = array_shift( $segments );

		if ( empty( $segments ) ) {
			unset( $this->variables[ $root ] );
			return;
		}

		if ( array_key_exists( $root, $this->variables ) ) {
			$this->variables[ $root ] = $this->dissoc_in( $this->variables[ $root ], $segments );
		}
	}

	/**
	 * Clears all stored variables.
	 *
	 * @since 1.0.0
	 */
	public function clear(): void {
		$this->variables = array();
	}

	/**
	 * Validates that a value to be stored in the session is of an allowed type.
	 *
	 * Allowed types: scalars (int, float, string, bool), null, arrays of allowed types,
	 * stdClass instances, and \Closure instances (from fn[...] expressions).
	 *
	 * Rejects all other object instances and resources to ensure safe serialization.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param mixed $value The value to validate.
	 * @param int   $depth Optional. Current recursion depth. Default 0.
	 *
	 * @throws \UnexpectedValueException If the value contains disallowed types or exceeds maximum depth.
	 */
	private function validate_variable_value( mixed $value, int $depth = 0 ): void {
		if ( $depth > 50 ) {
			throw new \UnexpectedValueException( 'Unsafe serialization detected: nesting depth limit exceeded.' );
		}

		if ( null === $value || is_scalar( $value ) || $value instanceof \Closure ) {
			return;
		}

		if ( is_object( $value ) ) {
			if ( ! ( $value instanceof \stdClass ) ) {
				throw new \UnexpectedValueException( 'Unsafe serialization detected.' );
			}

			foreach ( get_object_vars( $value ) as $prop_val ) {
				$this->validate_variable_value( $prop_val, $depth + 1 );
			}
			return;
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				$this->validate_variable_value( $item, $depth + 1 );
			}
			return;
		}

		throw new \UnexpectedValueException( 'Unsafe serialization detected.' );
	}

	/**
	 * Validates a variable name or dot-separated path.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string $path The variable name or path.
	 * @return string[] Array of path segments.
	 *
	 * @throws \InvalidArgumentException If any segment is invalid.
	 */
	private function validate_path_segments( string $path ): array {
		if ( '' === $path ) {
			throw new \InvalidArgumentException( 'Variable name cannot be empty.' );
		}

		$segments = explode( '.', $path );

		foreach ( $segments as $segment ) {
			if ( ! preg_match( '/^[a-zA-Z_][a-zA-Z0-9_]*$/', $segment ) ) {
				throw new \InvalidArgumentException( esc_html( 'Invalid variable name: ' . $path ) );
			}
		}

		return $segments;
	}

	/**
	 * Recursively inserts or updates a value in a nested array or stdClass structure.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param mixed    $target   The target array, stdClass, or scalar.
	 * @param string[] $segments The path segments.
	 * @param mixed    $value    The value to set.
	 * @return mixed The updated target structure.
	 */
	private function assoc_in( mixed $target, array $segments, mixed $value ): mixed {
		if ( empty( $segments ) ) {
			return $value;
		}

		$key = array_shift( $segments );

		if ( is_object( $target ) && $target instanceof \stdClass ) {
			$clone         = clone $target;
			$current_child = $clone->$key ?? null;
			$clone->$key   = $this->assoc_in( $current_child, $segments, $value );
			return $clone;
		}

		$array         = is_array( $target ) ? $target : array();
		$current_child = $array[ $key ] ?? null;
		$array[ $key ] = $this->assoc_in( $current_child, $segments, $value );
		return $array;
	}

	/**
	 * Recursively retrieves a value from a nested array or stdClass structure.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param mixed    $target        The target array or stdClass.
	 * @param string[] $segments      The path segments.
	 * @param mixed    $default_value Optional. The default value if not found. Default null.
	 * @return mixed The retrieved value or default.
	 */
	private function get_in( mixed $target, array $segments, mixed $default_value = null ): mixed {
		$current = $target;

		foreach ( $segments as $segment ) {
			if ( is_array( $current ) && array_key_exists( $segment, $current ) ) {
				$current = $current[ $segment ];
			} elseif ( is_object( $current ) && $current instanceof \stdClass && property_exists( $current, $segment ) ) {
				$current = $current->$segment;
			} else {
				return $default_value;
			}
		}

		return $current;
	}

	/**
	 * Recursively checks if a path exists in a nested array or stdClass structure.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param mixed    $target   The target array or stdClass.
	 * @param string[] $segments The path segments.
	 * @return bool True if path exists, false otherwise.
	 */
	private function has_in( mixed $target, array $segments ): bool {
		$current = $target;

		foreach ( $segments as $segment ) {
			if ( is_array( $current ) && array_key_exists( $segment, $current ) ) {
				$current = $current[ $segment ];
			} elseif ( is_object( $current ) && $current instanceof \stdClass && property_exists( $current, $segment ) ) {
				$current = $current->$segment;
			} else {
				return false;
			}
		}

		return true;
	}

	/**
	 * Recursively removes a key from a nested array or stdClass structure.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param mixed    $target   The target array or stdClass.
	 * @param string[] $segments The path segments.
	 * @return mixed The updated target structure.
	 */
	private function dissoc_in( mixed $target, array $segments ): mixed {
		if ( empty( $segments ) ) {
			return $target;
		}

		$key = array_shift( $segments );

		if ( is_object( $target ) && $target instanceof \stdClass ) {
			$clone = clone $target;
			if ( empty( $segments ) ) {
				unset( $clone->$key );
			} elseif ( isset( $clone->$key ) ) {
				$clone->$key = $this->dissoc_in( $clone->$key, $segments );
			}
			return $clone;
		}

		if ( is_array( $target ) ) {
			$array = $target;
			if ( empty( $segments ) ) {
				unset( $array[ $key ] );
			} elseif ( isset( $array[ $key ] ) ) {
				$array[ $key ] = $this->dissoc_in( $array[ $key ], $segments );
			}
			return $array;
		}

		return $target;
	}
}
