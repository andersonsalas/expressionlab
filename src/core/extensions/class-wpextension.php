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
use Symfony\Component\ExpressionLanguage\ExpressionFunction;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * WordPress extension class.
 *
 * Registers built-in WordPress utility functions and time constants into the expression language.
 *
 * @since 1.0.0
 * @package ExpressionLab
 */
class WpExtension implements ExtensionInterface {
	/**
	 * Retrieves all WordPress extension functions and their documentation metadata.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array{docs: array, callback: \Symfony\Component\ExpressionLanguage\ExpressionFunction}> Associative array of registered functions.
	 */
	public function get_functions(): array {
		$functions = array(
			'wp_json_encode'          => array(
				'docs'     => array(
					'summary'     => 'Encode a variable into JSON, with some sanity checks',
					'description' => "Encodes a variable into `JSON`, with sanity checks to handle `UTF-8` and recursion properly.\n\nExample:\n\n```elscript\nwp_json_encode({ 'status': 'success', 'code': 200 })\n```",
					'parameters'  => array(
						'data'    => array(
							'type'        => 'mixed',
							'description' => 'Variable (usually an `array` or `object`) to encode as `JSON`.',
							'required'    => true,
							'default'     => null,
						),
						'options' => array(
							'type'        => 'int',
							'description' => 'Bitmask consisting of `JSON` options.',
							'required'    => false,
							'default'     => 0,
						),
						'depth'   => array(
							'type'        => 'int',
							'description' => 'Maximum depth.',
							'required'    => false,
							'default'     => 512,
						),
					),
					'see'         => array(
						'https://developer.wordpress.org/reference/functions/wp_json_encode/',
					),
					'return'      => array(
						'type'        => 'string|false',
						'description' => 'The `JSON` encoded `string`, or `false` if it cannot be encoded.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'wp_json_encode' ),
			),
			'wp_strip_all_tags'       => array(
				'docs'     => array(
					'summary'     => 'Properly strip all HTML tags including script and style',
					'description' => "Removes all HTML markup and strips the contents of `<script>` and `<style>` tags completely.\n\nExample:\n\n```elscript\nwp_strip_all_tags('<p>Hello <strong>World</strong></p>')\n```",
					'parameters'  => array(
						'text'          => array(
							'type'        => 'string',
							'description' => '`string` containing HTML tags.',
							'required'    => true,
							'default'     => null,
						),
						'remove_breaks' => array(
							'type'        => 'bool',
							'description' => 'Whether to remove left over line breaks and white space chars.',
							'required'    => false,
							'default'     => false,
						),
					),
					'see'         => array(
						'https://developer.wordpress.org/reference/functions/wp_strip_all_tags/',
					),
					'return'      => array(
						'type'        => 'string',
						'description' => 'The processed `string`.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'wp_strip_all_tags' ),
			),
			'wp_specialchars_decode'  => array(
				'docs'     => array(
					'summary'     => 'Converts a number of special HTML entities back to characters',
					'description' => "Converts HTML entities such as `&amp;`, `&lt;`, `&gt;`, `&quot;`, and `&#039;` back to characters.\n\nExample:\n\n```elscript\nwp_specialchars_decode('&lt;strong&gt;Hello&lt;/strong&gt;')\n```",
					'parameters'  => array(
						'text'        => array(
							'type'        => 'string',
							'description' => 'The text which contains HTML entities to decode.',
							'required'    => true,
							'default'     => null,
						),
						'quote_style' => array(
							'type'        => 'int|string',
							'description' => 'Converts double quotes when `ENT_COMPAT`, both single and double quotes when `ENT_QUOTES`, or neither when `ENT_NOQUOTES`.',
							'required'    => false,
							'default'     => '`ENT_NOQUOTES`',
						),
					),
					'see'         => array(
						'https://developer.wordpress.org/reference/functions/wp_specialchars_decode/',
					),
					'return'      => array(
						'type'        => 'string',
						'description' => 'The decoded text.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'wp_specialchars_decode' ),
			),
			'wp_unslash'              => array(
				'docs'     => array(
					'summary'     => 'Removes slashes from a string or globally from an array of strings',
					'description' => "Reverses `wp_slash()` or `stripslashes_deep()` on `string` and `array` values.\n\nExample:\n\n```elscript\nwp_unslash('It\\\\\'s a test')\n```",
					'parameters'  => array(
						'value' => array(
							'type'        => 'string|array',
							'description' => '`string` or `array` of strings to unslash.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://developer.wordpress.org/reference/functions/wp_unslash/',
					),
					'return'      => array(
						'type'        => 'string|array',
						'description' => 'Unslashed value.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'wp_unslash' ),
			),
			'wp_slash'                => array(
				'docs'     => array(
					'summary'     => 'Adds slashes to a string or recursively adds slashes to strings within an array',
					'description' => "Adds slashes to a `string` or `array` of strings.\n\nExamples:\n\n```elscript\nwp_slash(\"It's a test\")\n// or\nwp_slash('It\\'s a test')\n```",
					'parameters'  => array(
						'value' => array(
							'type'        => 'string|array',
							'description' => '`string` or `array` of strings to slash.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://developer.wordpress.org/reference/functions/wp_slash/',
					),
					'return'      => array(
						'type'        => 'string|array',
						'description' => 'Slashed value.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'wp_slash' ),
			),
			'wp_parse_url'            => array(
				'docs'     => array(
					'summary'     => 'A wrapper for PHP\'s parse_url, handling protocol-relative URLs and encoding',
					'description' => "More robust than PHP's native `parse_url()`. Properly handles protocol-relative URLs and `UTF-8` characters.\n\nExample:\n\n```elscript\nwp_parse_url('https://example.com/blog?page=2#top')\n```",
					'parameters'  => array(
						'url'       => array(
							'type'        => 'string',
							'description' => 'The URL to parse.',
							'required'    => true,
							'default'     => null,
						),
						'component' => array(
							'type'        => 'int',
							'description' => 'Specific URL component to retrieve (`PHP_URL_SCHEME`, `PHP_URL_HOST`, `PHP_URL_PATH`, etc.). Defaults to `-1` (all).',
							'required'    => false,
							'default'     => -1,
						),
					),
					'see'         => array(
						'https://developer.wordpress.org/reference/functions/wp_parse_url/',
					),
					'return'      => array(
						'type'        => 'mixed',
						'description' => '`array` of URL parts, or `string`/`int` if component is specified, `false` on malformed URL.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'wp_parse_url' ),
			),
			'add_query_arg'           => array(
				'docs'     => array(
					'summary'     => 'Retrieves a modified URL query string with added or changed arguments',
					'description' => "Adds or updates query arguments in a given URL or query string.\n\nExample:\n\n```elscript\nadd_query_arg('filter', 'active', 'https://example.com/posts')\n```",
					'parameters'  => array(
						'key'   => array(
							'type'        => 'string|array',
							'description' => 'Either a query variable key, or an associative `array` of `key => value` pairs.',
							'required'    => true,
							'default'     => null,
						),
						'value' => array(
							'type'        => 'string|false',
							'description' => 'Query variable value if `key` is `string`, or target URL if `key` is `array`.',
							'required'    => false,
							'default'     => false,
						),
						'url'   => array(
							'type'        => 'string|false',
							'description' => 'URL to modify. If omitted, uses current request URI.',
							'required'    => false,
							'default'     => false,
						),
					),
					'see'         => array(
						'https://developer.wordpress.org/reference/functions/add_query_arg/',
					),
					'return'      => array(
						'type'        => 'string',
						'description' => 'New URL query string with the added arguments.',
					),
				),
				'callback' => new ExpressionFunction(
					'add_query_arg',
					function ( ...$args ) {
						return 'add_query_arg(' . implode( ', ', $args ) . ')';
					},
					function ( $values, ...$args ) {
						return add_query_arg( ...$args );
					}
				),
			),
			'remove_query_arg'        => array(
				'docs'     => array(
					'summary'     => 'Removes an argument or list of arguments from a query string or URL',
					'description' => "Removes one or more query parameters from a URL.\n\nExample:\n\n```elscript\nremove_query_arg('filter', 'https://example.com/posts?filter=active')\n```",
					'parameters'  => array(
						'key'   => array(
							'type'        => 'string|array',
							'description' => 'Query key or `array` of query keys to remove.',
							'required'    => true,
							'default'     => null,
						),
						'query' => array(
							'type'        => 'string|false',
							'description' => 'When `false`, uses the current request URL. Otherwise, the specified URL.',
							'required'    => false,
							'default'     => false,
						),
					),
					'see'         => array(
						'https://developer.wordpress.org/reference/functions/remove_query_arg/',
					),
					'return'      => array(
						'type'        => 'string',
						'description' => 'New URL query string with the removed arguments.',
					),
				),
				'callback' => new ExpressionFunction(
					'remove_query_arg',
					function ( ...$args ) {
						return 'remove_query_arg(' . implode( ', ', $args ) . ')';
					},
					function ( $values, ...$args ) {
						return remove_query_arg( ...$args );
					}
				),
			),
			'wp_list_pluck'           => array(
				'docs'     => array(
					'summary'     => 'Plucks an array of values from an array of objects or associative arrays',
					'description' => "Extracts a specific property or key from a list of objects or arrays, optionally re-indexing by another key.\n\nExample:\n\n```elscript\nwp_list_pluck([{ 'id': 1, 'title': 'Post 1' }, { 'id': 2, 'title': 'Post 2' }], 'title')\n```",
					'parameters'  => array(
						'list'      => array(
							'type'        => 'array',
							'description' => 'An `array` of objects or arrays.',
							'required'    => true,
							'default'     => null,
						),
						'field'     => array(
							'type'        => 'int|string',
							'description' => 'Field name or `array` key from which to retrieve values.',
							'required'    => true,
							'default'     => null,
						),
						'index_key' => array(
							'type'        => 'int|string|null',
							'description' => 'Optional. Field from the `object` to use as keys for the new `array`.',
							'required'    => false,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://developer.wordpress.org/reference/functions/wp_list_pluck/',
					),
					'return'      => array(
						'type'        => 'array',
						'description' => '`array` of plucked values.',
					),
				),
				'callback' => new ExpressionFunction(
					'wp_list_pluck',
					function ( $input_list, $field, $index_key = null ) {
						return 'wp_list_pluck(' . $input_list . ', ' . $field . ( $index_key ? ', ' . $index_key : '' ) . ')';
					},
					function ( $values, $input_list, $field, $index_key = null ) {
						return wp_list_pluck( (array) $input_list, $field, $index_key );
					}
				),
			),
			'is_serialized'           => array(
				'docs'     => array(
					'summary'     => 'Checks if a value is serialized (Safe Read-Only)',
					'description' => "Checks whether `string` value is serialized without performing any deserialization.\n\nExample:\n\n```elscript\nis_serialized('a:1:{s:4:\"test\";b:1;}')\n```",
					'parameters'  => array(
						'data'   => array(
							'type'        => 'string',
							'description' => 'Value to check.',
							'required'    => true,
							'default'     => null,
						),
						'strict' => array(
							'type'        => 'bool',
							'description' => 'Whether to be strict about the end of the `string`.',
							'required'    => false,
							'default'     => true,
						),
					),
					'see'         => array(
						'https://developer.wordpress.org/reference/functions/is_serialized/',
					),
					'return'      => array(
						'type'        => 'bool',
						'description' => '`true` if data is serialized, `false` otherwise.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'is_serialized' ),
			),
			'is_email'                => array(
				'docs'     => array(
					'summary'     => 'Verifies that an email is valid',
					'description' => "Checks whether an email address format is valid according to WordPress standards.\n\nExample:\n\n```elscript\nis_email('user@example.com')\n```",
					'parameters'  => array(
						'email' => array(
							'type'        => 'string',
							'description' => 'Email address to verify.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://developer.wordpress.org/reference/functions/is_email/',
					),
					'return'      => array(
						'type'        => 'string|false',
						'description' => 'The valid email address on success, `false` on failure.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'is_email' ),
			),
			'sanitize_key'            => array(
				'docs'     => array(
					'summary'     => 'Sanitizes a string key',
					'description' => "Keys are used as internal identifiers. Lowercase alphanumeric characters, dashes, and underscores are allowed.\n\nExample:\n\n```elscript\nsanitize_key('My_Custom Key 123!')\n```",
					'parameters'  => array(
						'key' => array(
							'type'        => 'string',
							'description' => '`string` key to sanitize.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://developer.wordpress.org/reference/functions/sanitize_key/',
					),
					'return'      => array(
						'type'        => 'string',
						'description' => 'Sanitized key.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'sanitize_key' ),
			),
			'sanitize_title'          => array(
				'docs'     => array(
					'summary'     => 'Sanitizes a string into a valid URL slug',
					'description' => "Sanitizes a title or `string`, replacing whitespace with dashes and stripping accents.\n\nExample:\n\n```elscript\nsanitize_title('Hello World! Sample Post')\n```",
					'parameters'  => array(
						'title'          => array(
							'type'        => 'string',
							'description' => 'The title to be sanitized.',
							'required'    => true,
							'default'     => null,
						),
						'fallback_title' => array(
							'type'        => 'string',
							'description' => 'A title to use if `title` is empty.',
							'required'    => false,
							'default'     => "''",
						),
					),
					'see'         => array(
						'https://developer.wordpress.org/reference/functions/sanitize_title/',
					),
					'return'      => array(
						'type'        => 'string',
						'description' => 'The sanitized title.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'sanitize_title' ),
			),
			'wp_basename'             => array(
				'docs'     => array(
					'summary'     => 'i18n-friendly version of PHP\'s basename()',
					'description' => "Returns the trailing name component of `path`, taking multibyte filenames into account.\n\nExample:\n\n```elscript\nwp_basename('/var/www/html/wp-content/themes/theme.php')\n```",
					'parameters'  => array(
						'path'   => array(
							'type'        => 'string',
							'description' => 'A file path.',
							'required'    => true,
							'default'     => null,
						),
						'suffix' => array(
							'type'        => 'string',
							'description' => 'If the filename ends in `suffix` this will also be cut off.',
							'required'    => false,
							'default'     => "''",
						),
					),
					'see'         => array(
						'https://developer.wordpress.org/reference/functions/wp_basename/',
					),
					'return'      => array(
						'type'        => 'string',
						'description' => 'The trailing name component of the given `path`.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'wp_basename' ),
			),
			'is_multisite'            => array(
				'docs'     => array(
					'summary'     => 'Determines whether Multisite is enabled',
					'description' => "Returns `true` if WordPress is running in Multisite network mode.\n\nExample:\n\n```elscript\nis_multisite()\n```",
					'parameters'  => array(),
					'see'         => array(
						'https://developer.wordpress.org/reference/functions/is_multisite/',
					),
					'return'      => array(
						'type'        => 'bool',
						'description' => '`true` if Multisite is enabled, `false` otherwise.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'is_multisite' ),
			),
			'wp_get_environment_type' => array(
				'docs'     => array(
					'summary'     => 'Retrieves the current environment type',
					'description' => "Returns the current environment type: `'production'`, `'staging'`, `'development'`, or `'local'`.\n\nExample:\n\n```elscript\nwp_get_environment_type()\n```",
					'parameters'  => array(),
					'see'         => array(
						'https://developer.wordpress.org/reference/functions/wp_get_environment_type/',
					),
					'return'      => array(
						'type'        => 'string',
						'description' => 'The current environment type.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'wp_get_environment_type' ),
			),
		);

		return $functions;
	}

	/**
	 * Retrieves all WordPress extension constants and their documentation metadata.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array{docs: array, value: mixed}> Associative array of registered constants.
	 */
	public function get_constants(): array {
		return array(
			'HOUR_IN_SECONDS'  => array(
				'docs'  => array(
					'summary'     => 'Number of seconds in one hour',
					'description' => "This constant defines the number of seconds in one hour (`3600` seconds).\n\nExample:\n\n```elscript\nHOUR_IN_SECONDS\n```",
					'type'        => 'int',
				),
				'value' => HOUR_IN_SECONDS,
			),
			'DAY_IN_SECONDS'   => array(
				'docs'  => array(
					'summary'     => 'Number of seconds in one day',
					'description' => "This constant defines the number of seconds in one day (`86400` seconds).\n\nExample:\n\n```elscript\nDAY_IN_SECONDS\n```",
					'type'        => 'int',
				),
				'value' => DAY_IN_SECONDS,
			),
			'WEEK_IN_SECONDS'  => array(
				'docs'  => array(
					'summary'     => 'Number of seconds in one week',
					'description' => "This constant defines the number of seconds in one week (`604800` seconds).\n\nExample:\n\n```elscript\nWEEK_IN_SECONDS\n```",
					'type'        => 'int',
				),
				'value' => WEEK_IN_SECONDS,
			),
			'MONTH_IN_SECONDS' => array(
				'docs'  => array(
					'summary'     => 'Number of seconds in one month',
					'description' => "This constant defines the number of seconds in one month (approximately `2629746` seconds).\n\nExample:\n\n```elscript\nMONTH_IN_SECONDS\n```",
					'type'        => 'int',
				),
				'value' => MONTH_IN_SECONDS,
			),
			'YEAR_IN_SECONDS'  => array(
				'docs'  => array(
					'summary'     => 'Number of seconds in one year',
					'description' => "This constant defines the number of seconds in one year (approximately `31556926` seconds).\n\nExample:\n\n```elscript\nYEAR_IN_SECONDS\n```",
					'type'        => 'int',
				),
				'value' => YEAR_IN_SECONDS,
			),
		);
	}
}
