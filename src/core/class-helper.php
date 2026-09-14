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
 * Plugin helper class.
 *
 * Contains static utility and helper methods for configuration, authorization,
 * serialization, and formatting across the plugin.
 *
 * @since 1.0.0
 *
 * @package ExpressionLab
 */
class Helper {
	/**
	 * Checks if the plugin is fully configured.
	 *
	 * Verifies that all required constants for plugin operation are defined
	 * and non-empty.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the plugin is fully configured, false otherwise.
	 */
	public static function plugin_is_fully_configured(): bool {
		return defined( 'EXPRESSION_LAB_ADMIN_USER_ID' )
			&& ! empty( constant( 'EXPRESSION_LAB_ADMIN_USER_ID' ) )
			&& defined( 'EXPRESSION_LAB_ADMIN_PUBLIC_KEY' )
			&& ! empty( constant( 'EXPRESSION_LAB_ADMIN_PUBLIC_KEY' ) )
			&& defined( 'EXPRESSION_LAB_ADMIN_SALT' )
			&& ! empty( constant( 'EXPRESSION_LAB_ADMIN_SALT' ) );
	}

	/**
	 * Checks if Expression Lab internal debug mode is enabled.
	 *
	 * Debug mode is controlled deterministically via the EXPRESSION_LAB_DEBUG_MODE
	 * constant in wp-config.php.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if debug mode is enabled, false otherwise.
	 */
	public static function is_debug_mode(): bool {
		return defined( 'EXPRESSION_LAB_DEBUG_MODE' ) && true === constant( 'EXPRESSION_LAB_DEBUG_MODE' );
	}

	/**
	 * Checks if the current admin screen belongs to Expression Lab.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if on Expression Lab admin screen, false otherwise.
	 */
	public static function is_current_screen(): bool {
		if ( ! is_admin() ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['page'] ) && 'expressionlab' === sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return true;
		}

		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && ! empty( $screen->id ) && false !== strpos( $screen->id, 'page_expressionlab' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if the current user is the Expression Lab admin user.
	 *
	 * Verifies user capabilities and ensures the current logged-in user matches
	 * the administrator ID defined by the `EXPRESSION_LAB_ADMIN_USER_ID` constant.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the current user is the Expression Lab admin, false otherwise.
	 */
	public static function user_is_expressionlab_admin(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			if ( ! current_user_can( 'manage_network_options' ) ) {
				return false;
			}
		} elseif ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$allowed_admin_id = defined( 'EXPRESSION_LAB_ADMIN_USER_ID' )
			? (int) constant( 'EXPRESSION_LAB_ADMIN_USER_ID' )
			: null;

		return ! empty( wp_get_current_user()->ID ) && wp_get_current_user()->ID === $allowed_admin_id;
	}

	/**
	 * Serializes a value, validating permitted types.
	 *
	 * Ensures that only scalar values, arrays, null, or stdClass instances are
	 * serialized, verifying round-trip serialization against unauthorized classes.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The value to serialize. Must be a scalar, array, stdClass object, or null.
	 * @return mixed The input value after serialization validation.
	 *
	 * @throws \UnexpectedValueException If the provided value contains disallowed classes or fails round-trip serialization.
	 */
	public static function strict_serialize( $value ) {
		// Quick test to ensure only allowed types are serialized.
		if ( is_object( $value ) && ! ( $value instanceof \stdClass ) ) {
			throw new \UnexpectedValueException( 'Unsafe serialization detected.' );
		}

		// Now we perform a test serialization and unserialization to ensure that the value can be safely round-tripped without issues.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		self::strict_unserialize( @serialize( $value ) );

		return $value;
	}

	/**
	 * Unserializes a string, allowing only stdClass objects.
	 *
	 * Performs inspection of the unserialized data to ensure that only stdClass
	 * instances are permitted, rejecting any incomplete class definitions.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value The serialized string to unserialize.
	 * @return mixed The unserialized value, or the original value if it is not a serialized string.
	 *
	 * @throws \UnexpectedValueException If the provided value is not a valid serialized string or contains disallowed classes.
	 */
	public static function strict_unserialize( string $value ) {
		$remaining_time = null;
		LanguageEngine::get()->tick( $remaining_time, max( 1, (int) ceil( strlen( $value ) / 1024 ) ) );

		if ( ! is_serialized( $value ) ) {
			return $value; // Not a serialized string, return as is.
		}

		// Only stdClass is allowed to be unserialized.
		$unserialized = @unserialize( $value, array( 'allowed_classes' => array( \stdClass::class ) ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize

		// If unserialization completely fails (false) and it wasn't the serialized boolean false, just abort.
		if ( false === $unserialized && 'b:0;' !== $value ) {
			throw new \UnexpectedValueException( 'Invalid serialization detected.' );
		}

		// Deep Inspection (Recursive Scan).
		if ( self::strict_unserialize_has_incomplete_classes( $unserialized ) ) {
			throw new \UnexpectedValueException( 'Unsafe serialization detected.' );
		}

		return $unserialized;
	}

	/**
	 * Recursively checks if data contains an incomplete class instance.
	 *
	 * Traverses arrays and stdClass properties to verify that no __PHP_Incomplete_Class
	 * instances are present.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param mixed $data The data structure to inspect.
	 * @return bool True if an incomplete class is found, false otherwise.
	 */
	private static function strict_unserialize_has_incomplete_classes( $data ): bool {
		if ( is_array( $data ) ) {
			foreach ( $data as $value ) {
				if ( self::strict_unserialize_has_incomplete_classes( $value ) ) {
					return true;
				}
			}
		} elseif ( is_object( $data ) ) {
			if ( $data instanceof \__PHP_Incomplete_Class ) {
				return true;
			}
			// If it's a stdClass, we need to check its properties as well.
			foreach ( get_object_vars( $data ) as $value ) {
				if ( self::strict_unserialize_has_incomplete_classes( $value ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Formats a string as an inline Markdown code span.
	 *
	 * Handles internal backticks according to the CommonMark specification.
	 *
	 * @since 1.0.0
	 *
	 * @param string $code The code snippet to wrap.
	 * @return string The formatted Markdown code span.
	 */
	public static function format_markdown_code_span( string $code ): string {
		preg_match_all( '/`+/', $code, $matches );
		$max_ticks = 0;
		if ( ! empty( $matches[0] ) ) {
			foreach ( $matches[0] as $match ) {
				$max_ticks = max( $max_ticks, strlen( $match ) );
			}
		}

		$delimiter         = str_repeat( '`', $max_ticks + 1 );
		$starts_with_tick  = '' !== $code && '`' === $code[0];
		$ends_with_tick    = '' !== $code && '`' === substr( $code, -1 );
		$starts_with_space = '' !== $code && ' ' === $code[0];
		$ends_with_space   = '' !== $code && ' ' === substr( $code, -1 );

		if ( $max_ticks > 0 || $starts_with_tick || $ends_with_tick || $starts_with_space || $ends_with_space ) {
			return $delimiter . ' ' . $code . ' ' . $delimiter;
		}

		return $delimiter . $code . $delimiter;
	}
}
