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
use ExpressionLab\Core\LanguageEngine;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * PHP extension class.
 *
 * Registers whitelisted built-in PHP functions and constants into the expression language.
 *
 * @since 1.0.0
 * @package ExpressionLab
 */
class PhpExtension implements ExtensionInterface {
	/**
	 * Safe backtrack limit for PCRE operations.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const SAFE_BACKTRACK_LIMIT = '25000';

	/**
	 * Whitelisted PHP INI configuration directives permitted for diagnostic inspection.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	private const ALLOWED_INI_DIRECTIVES = array(
		'memory_limit',
		'max_execution_time',
		'max_input_time',
		'upload_max_filesize',
		'post_max_size',
		'max_input_vars',
		'display_errors',
		'default_socket_timeout',
		'date.timezone',
		'opcache.enable',
		'session.gc_maxlifetime',
		'short_open_tag',
		'pcre.backtrack_limit',
	);

	/**
	 * Executes a PCRE operation with a constrained backtrack limit.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param callable $callback The regex operation callback to execute.
	 * @return mixed Result of the callback execution.
	 */
	private static function safe_pcre_call( callable $callback ) {
		$prev_limit = ini_get( 'pcre.backtrack_limit' );

		// phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged -- Needed for safety.
		// phpcs:disable WordPress.PHP.IniSet.Risky -- We need to prevent ReDoS.
		@ini_set( 'pcre.backtrack_limit', self::SAFE_BACKTRACK_LIMIT );
		try {
			return $callback();
		} finally {
			if ( false !== $prev_limit ) {
				@ini_set( 'pcre.backtrack_limit', $prev_limit );
			}
		}
		// phpcs:enable WordPress.PHP.NoSilencedErrors.Discouraged
		// phpcs:enable WordPress.PHP.IniSet.Risky
	}

	/**
	 * Retrieves all PHP extension functions and their documentation metadata.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array{docs: array, callback: \Symfony\Component\ExpressionLanguage\ExpressionFunction}> Associative array of registered functions.
	 */
	public function get_functions(): array {
		$functions = array(
			// #region 1. String Functions
			'strlen'                => array(
				'docs'     => array(
					'summary'     => 'Get string length',
					'description' => "Returns the length of the given `string`.\n\nExample:\n\n```elscript\nstrlen('Hello World')\n```",
					'parameters'  => array(
						'string' => array(
							'type'        => 'string',
							'description' => 'The `string` being measured for length.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.strlen.php',
					),
					'return'      => array(
						'type'        => 'int',
						'description' => 'The length of the `string` in bytes.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'strlen' ),
			),
			'strpos'                => array(
				'docs'     => array(
					'summary'     => 'Find the position of the first occurrence of a substring in a string',
					'description' => "Find the numeric position of the first occurrence of `needle` in the `haystack` `string`.\n\nExample:\n\n```elscript\nstrpos('Hello World', 'World')\n```",
					'parameters'  => array(
						'haystack' => array(
							'type'        => 'string',
							'description' => 'The `string` to search in.',
							'required'    => true,
							'default'     => null,
						),
						'needle'   => array(
							'type'        => 'string',
							'description' => 'The substring to search for.',
							'required'    => true,
							'default'     => null,
						),
						'offset'   => array(
							'type'        => 'int',
							'description' => 'If specified, search will start this number of characters into the `string`.',
							'required'    => false,
							'default'     => 0,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.strpos.php',
					),
					'return'      => array(
						'type'        => 'int|false',
						'description' => 'The position of the first occurrence of `needle` in `haystack`, or `false` if `needle` is not found.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'strpos' ),
			),
			'replace'               => array(
				'docs'     => array(
					'summary'     => 'Replace all occurrences of the search string with the replacement string',
					'description' => "Alias of `str_replace()`, this function returns a `string` or an `array` with all occurrences of `search` in `subject` replaced with the given `replace` value.\n\nExample:\n\n```elscript\nreplace('World', 'Universe', 'Hello World')\n```",
					'parameters'  => array(
						'search'  => array(
							'type'        => 'array|string',
							'description' => 'The value being searched for, otherwise known as the `needle`. An `array` may be used to designate multiple needles.',
							'required'    => true,
							'default'     => null,
						),
						'replace' => array(
							'type'        => 'array|string',
							'description' => 'The replacement value that replaces found `search` values. An `array` may be used to designate multiple replacements.',
							'required'    => true,
							'default'     => null,
						),
						'subject' => array(
							'type'        => 'array|string',
							'description' => 'The `string` or `array` being searched and replaced on, otherwise known as the `haystack`.',
							'required'    => true,
							'default'     => null,
						),
						'count'   => array(
							'type'        => 'int',
							'description' => '(This is ignored by the expression engine) If passed, this will hold the number of matched and replaced needles.',
							'required'    => false,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.str-replace.php',
					),
					'return'      => array(
						'type'        => 'array|string',
						'description' => 'This function returns a `string` or an `array` with the replaced values.',
					),
				),
				'callback' => new ExpressionFunction(
					'replace',
					// Pass by reference $count parameter is ignored due to security reasons.
					// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
					// phpcs:disable Universal.WhiteSpace.DisallowInlineTabs.NonIndentTabsUsed
					function ( $search, $replace, $subject,	$count = null ) {
						// Compilation is not supported.
					},
					function ( $values, $search, $replace, $subject, $count = null ) {
						return str_replace( $search, $replace, $subject );
					}
					// phpcs:enable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
					// phpcs:enable Universal.WhiteSpace.DisallowInlineTabs.NonIndentTabsUsed
				),
			),
			'ireplace'              => array(
				'docs'     => array(
					'summary'     => 'Replace all occurrences of the search string with the replacement string (case-insensitive)',
					'description' => "Alias of `str_ireplace()`, this function returns a `string` or an `array` with all occurrences of `search` in `subject` replaced with the given `replace` value, ignoring case.\n\nExample:\n\n```elscript\nireplace('world', 'Universe', 'Hello WORLD')\n```",
					'parameters'  => array(
						'search'  => array(
							'type'        => 'array|string',
							'description' => 'The value being searched for, otherwise known as the `needle`. An `array` may be used to designate multiple needles.',
							'required'    => true,
							'default'     => null,
						),
						'replace' => array(
							'type'        => 'array|string',
							'description' => 'The replacement value that replaces found `search` values. An `array` may be used to designate multiple replacements.',
							'required'    => true,
							'default'     => null,
						),
						'subject' => array(
							'type'        => 'array|string',
							'description' => 'The `string` or `array` being searched and replaced on, otherwise known as the `haystack`.',
							'required'    => true,
							'default'     => null,
						),
						'count'   => array(
							'type'        => 'int',
							'description' => '(This is ignored by the expression engine) If passed, this will hold the number of matched and replaced needles.',
							'required'    => false,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.str-ireplace.php',
					),
					'return'      => array(
						'type'        => 'array|string',
						'description' => 'This function returns a `string` or an `array` with the replaced values.',
					),
				),
				'callback' => new ExpressionFunction(
					'ireplace',
					// Pass by reference $count parameter is ignored due to security reasons.
					// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
					// phpcs:disable Universal.WhiteSpace.DisallowInlineTabs.NonIndentTabsUsed
					function ( $search, $replace, $subject,	$count = null ) {
						// Compilation is not supported.
					},
					function ( $values, $search, $replace, $subject, $count = null ) {
						return str_ireplace( $search, $replace, $subject );
					}
					// phpcs:enable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
					// phpcs:enable Universal.WhiteSpace.DisallowInlineTabs.NonIndentTabsUsed
				),
			),
			'strtolower'            => array(
				'docs'     => array(
					'summary'     => 'Make a string lowercase',
					'description' => "Returns `string` with all `ASCII` alphabetic characters converted to lowercase.\n\nExample:\n\n```elscript\nstrtolower('HELLO WORLD')\n```",
					'parameters'  => array(
						'string' => array(
							'type'        => 'string',
							'description' => 'The input `string`.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.strtolower.php',
					),
					'return'      => array(
						'type'        => 'string',
						'description' => 'Returns the lowercased `string`.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'strtolower' ),
			),
			'strtoupper'            => array(
				'docs'     => array(
					'summary'     => 'Make a string uppercase',
					'description' => "Returns `string` with all `ASCII` alphabetic characters converted to uppercase.\n\nExample:\n\n```elscript\nstrtoupper('hello world')\n```",
					'parameters'  => array(
						'string' => array(
							'type'        => 'string',
							'description' => 'The input `string`.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.strtoupper.php',
					),
					'return'      => array(
						'type'        => 'string',
						'description' => 'Returns the uppercased `string`.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'strtoupper' ),
			),
			'substr'                => array(
				'docs'     => array(
					'summary'     => 'Return part of a string',
					'description' => "Returns the portion of `string` specified by the `offset` and `length` parameters.\n\nExample:\n\n```elscript\nsubstr('Hello World', 0, 5)\n```",
					'parameters'  => array(
						'string' => array(
							'type'        => 'string',
							'description' => 'The input `string`.',
							'required'    => true,
							'default'     => null,
						),
						'offset' => array(
							'type'        => 'int',
							'description' => 'If `offset` is non-negative, the returned `string` will start at the `offset`\'th position in `string`, counting from `0`.',
							'required'    => true,
							'default'     => null,
						),
						'length' => array(
							'type'        => 'int',
							'description' => 'If `length` is given and is positive, the `string` returned will contain at most `length` characters beginning from `offset` (depending on the length of `string`).',
							'required'    => false,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.substr.php',
					),
					'return'      => array(
						'type'        => 'string',
						'description' => 'Returns the extracted part of `string`, or an empty `string`.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'substr' ),
			),
			'trim'                  => array(
				'docs'     => array(
					'summary'     => 'Strip whitespace (or other characters) from the beginning and end of a string',
					'description' => "This function returns a `string` with whitespace stripped from the beginning and end of `string`.\n\nExample:\n\n```elscript\ntrim('  Hello World  ')\n```",
					'parameters'  => array(
						'string'     => array(
							'type'        => 'string',
							'description' => 'The `string` that will be trimmed.',
							'required'    => true,
							'default'     => null,
						),
						'characters' => array(
							'type'        => 'string',
							'description' => 'Optionally, the stripped characters can also be specified using the `characters` parameter. Simply list all characters that need to be stripped. With `..` it is possible to specify an incrementing range of characters.',
							'required'    => false,
							'default'     => '`" \n\r\t\v\x00"`',
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.trim.php',
					),
					'return'      => array(
						'type'        => 'string',
						'description' => 'The trimmed `string`.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'trim' ),
			),
			'ltrim'                 => array(
				'docs'     => array(
					'summary'     => 'Strip whitespace (or other characters) from the beginning of a string',
					'description' => "Strip whitespace (or other characters) from the beginning of a `string`.\n\nExample:\n\n```elscript\nltrim('/path/to/page/', '/')\n```",
					'parameters'  => array(
						'string'     => array(
							'type'        => 'string',
							'description' => 'The input `string`.',
							'required'    => true,
							'default'     => null,
						),
						'characters' => array(
							'type'        => 'string',
							'description' => 'Optionally, the stripped characters can also be specified using the `characters` parameter. Simply list all characters that need to be stripped. With `..` it is possible to specify an incrementing range of characters.',
							'required'    => false,
							'default'     => '`" \n\r\t\v\x00"`',
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.ltrim.php',
					),
					'return'      => array(
						'type'        => 'string',
						'description' => 'This function returns a `string` with whitespace stripped from the beginning of `string`.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'ltrim' ),
			),
			'rtrim'                 => array(
				'docs'     => array(
					'summary'     => 'Strip whitespace (or other characters) from the end of a string',
					'description' => "This function returns a `string` with whitespace (or other characters) stripped from the end of `string`.\n\nExample:\n\n```elscript\nrtrim('Hello World!!!', '!')\n```",
					'parameters'  => array(
						'string'     => array(
							'type'        => 'string',
							'description' => 'The input `string`.',
							'required'    => true,
							'default'     => null,
						),
						'characters' => array(
							'type'        => 'string',
							'description' => 'Optionally, the stripped characters can also be specified using the `characters` parameter. Simply list all characters that need to be stripped. With `..` it is possible to specify an incrementing range of characters.',
							'required'    => false,
							'default'     => '`" \n\r\t\v\x00"`',
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.rtrim.php',
					),
					'return'      => array(
						'type'        => 'string',
						'description' => 'Returns the modified `string`.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'rtrim' ),
			),
			'implode'               => array(
				'docs'     => array(
					'summary'     => 'Join array elements with a string',
					'description' => "Join `array` elements with a `separator` `string`.\n\nExample:\n\n```elscript\nimplode(', ', ['apple', 'banana', 'orange'])\n```",
					'parameters'  => array(
						'separator' => array(
							'type'        => 'string',
							'description' => 'Optional. Defaults to an empty `string`.',
							'required'    => true,
							'default'     => null,
						),
						'array'     => array(
							'type'        => 'array',
							'description' => 'The `array` of strings to implode.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.implode.php',
					),
					'return'      => array(
						'type'        => 'string',
						'description' => 'Returns a `string` containing a `string` representation of all the `array` elements in the same order, with the `separator` `string` between each element.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'implode' ),
			),
			'explode'               => array(
				'docs'     => array(
					'summary'     => 'Split a string by a string (Safety Capped)',
					'description' => "Returns an `array` of strings, each of which is a substring of `string` formed by splitting it on boundaries formed by the `separator`. Capped at `10000` elements by default to prevent memory exhaustion.\n\nExample:\n\n```elscript\nexplode(',', 'apple,banana,orange')\n```",
					'parameters'  => array(
						'separator'  => array(
							'type'        => 'string',
							'description' => 'The boundary `string`.',
							'required'    => true,
							'default'     => null,
						),
						'the_string' => array(
							'type'        => 'string',
							'description' => 'The input string.',
							'required'    => true,
							'default'     => null,
						),
						'limit'      => array(
							'type'        => 'int',
							'description' => 'If `limit` is set and positive, the returned `array` will contain a maximum of `limit` elements with the last element containing the rest of `string`.',
							'required'    => false,
							'default'     => 10000,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.explode.php',
					),
					'return'      => array(
						'type'        => 'array',
						'description' => 'Returns an `array` of strings created by splitting the `string` parameter on boundaries formed by the `separator`.',
					),
				),
				'callback' => new ExpressionFunction(
					'explode',
					function ( $separator, $the_string, $limit = 10000 ) {
						// Compilation is not supported.
					},
					function ( $values, $separator, $the_string, $limit = 10000 ) {
						$max_limit = 10000;
						$limit_int = ( null === $limit || ! is_numeric( $limit ) ) ? $max_limit : (int) $limit;
						if ( $limit_int > $max_limit || 0 === $limit_int ) {
							$limit_int = $max_limit;
						}
						return explode( (string) $separator, (string) $the_string, $limit_int );
					}
				),
			),
			'sprintf'               => array(
				'docs'     => array(
					'summary'     => 'Return a formatted string',
					'description' => "Return a formatted `string` produced according to `format`.\n\nExample:\n\n```elscript\nsprintf('Hello %s, you have %d messages', 'Alice', 5)\n```",
					'parameters'  => array(
						'format'    => array(
							'type'        => 'string',
							'description' => 'The format `string` is composed of zero or more directives.',
							'required'    => true,
							'default'     => null,
						),
						'...values' => array(
							'type'        => 'mixed',
							'description' => 'The format `string` is composed of zero or more directives.',
							'required'    => false,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.sprintf.php',
					),
					'return'      => array(
						'type'        => 'string',
						'description' => 'Returns a `string` produced according to the formatting `string` `format`.',
					),
				),
				'callback' => new ExpressionFunction(
					'sprintf',
					function ( $format, ...$args ) {
						// Compilation is not supported.
					},
					function ( $values, $format, ...$args ) {
						try {
							return sprintf( (string) $format, ...$args );
						} catch ( \Throwable $e ) {
							LanguageEngine::get()->add_message(
								'error',
								'sprintf() error: ' . $e->getMessage() . ' in `' . $format . '`'
							);
							return null;
						}
					}
				),
			),
			'regex_match'           => array(
				'docs'     => array(
					'summary'     => 'Perform a regular expression match (ReDoS Protected)',
					'description' => "Searches `subject` for a match to the regular expression given in `pattern`. Backtrack limit is constrained to `25000`.\n\nExample:\n\n```elscript\nregex_match('/^post_([0-9]+)\$/', 'post_123')\n```",
					'parameters'  => array(
						'pattern' => array(
							'type'        => 'string',
							'description' => 'The pattern to search for, as a `string`.',
							'required'    => true,
							'default'     => null,
						),
						'subject' => array(
							'type'        => 'string',
							'description' => 'The input `string`.',
							'required'    => true,
							'default'     => null,
						),
						'flags'   => array(
							'type'        => 'int',
							'description' => 'Can be `PREG_OFFSET_CAPTURE` or `PREG_UNMATCHED_AS_NULL`.',
							'required'    => false,
							'default'     => 0,
						),
						'offset'  => array(
							'type'        => 'int',
							'description' => 'Normally, the search starts from the beginning of the `subject` `string`.',
							'required'    => false,
							'default'     => 0,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.preg-match.php',
					),
					'return'      => array(
						'type'        => 'mixed|false',
						'description' => 'Returns an `array` with the matches found, or `null` if no match occurred.',
					),
				),
				'callback' => new ExpressionFunction(
					'regex_match',
					function ( $pattern, $subject, $flags = 0, $offset = 0 ) {
						// Compilation is not supported.
					},
					function ( $values, $pattern, $subject, $flags = 0, $offset = 0 ) {
						return self::safe_pcre_call(
							function () use ( $pattern, $subject, $flags, $offset ) {
								$matches = null;
								// phpcs:ignore
								$result  = @preg_match( (string) $pattern, (string) $subject, $matches, (int) $flags, (int) $offset );
								if ( false === $result || PREG_NO_ERROR !== preg_last_error() ) {
									return false;
								}
								return $matches;
							}
						);
					}
				),
			),
			'regex_match_all'       => array(
				'docs'     => array(
					'summary'     => 'Perform a global regular expression match (ReDoS Protected)',
					'description' => "Searches `subject` for all matches to the regular expression given in `pattern` and puts them in `matches` in the order specified by `flags`. Backtrack limit is constrained to `25000`.\n\nExample:\n\n```elscript\nregex_match_all('/[0-9]+/', '10 apples, 20 oranges')\n```",
					'parameters'  => array(
						'pattern' => array(
							'type'        => 'string',
							'description' => 'The pattern to search for, as a `string`.',
							'required'    => true,
							'default'     => null,
						),
						'subject' => array(
							'type'        => 'string',
							'description' => 'The input `string`.',
							'required'    => true,
							'default'     => null,
						),
						'flags'   => array(
							'type'        => 'int',
							'description' => 'Can be a combination of `PREG_PATTERN_ORDER`, `PREG_SET_ORDER`, `PREG_OFFSET_CAPTURE`, and `PREG_UNMATCHED_AS_NULL`.',
							'required'    => false,
							'default'     => 0,
						),
						'offset'  => array(
							'type'        => 'int',
							'description' => 'Normally, the search starts from the beginning of the `subject` `string`.',
							'required'    => false,
							'default'     => 0,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.preg-match-all.php',
					),
					'return'      => array(
						'type'        => 'array',
						'description' => 'Returns an `array` of all matches, or `null` if an error occurs or no matches are found.',
					),
				),
				'callback' => new ExpressionFunction(
					'regex_match_all',
					function ( $pattern, $subject, $flags = 0, $offset = 0 ) {
						// Compilation is not supported.
					},
					function ( $values, $pattern, $subject, $flags = 0, $offset = 0 ) {
						return self::safe_pcre_call(
							function () use ( $pattern, $subject, $flags, $offset ) {
								$matches = array();
								// phpcs:ignore
								$result  = @preg_match_all( (string) $pattern, (string) $subject, $matches, (int) $flags, (int) $offset );
								if ( false === $result || PREG_NO_ERROR !== preg_last_error() ) {
									return array();
								}
								return $matches;
							}
						);
					}
				),
			),
			'regex_replace'         => array(
				'docs'     => array(
					'summary'     => 'Perform a regular expression search and replace (ReDoS Protected)',
					'description' => "Searches `subject` for matches to `pattern` and replaces them with `replacement`. Backtrack limit is constrained to `25000` to prevent ReDoS.\n\nExample:\n\n```elscript\nregex_replace('/[0-9]+/', '#', 'item123')\n```",
					'parameters'  => array(
						'pattern'     => array(
							'type'        => 'array|string',
							'description' => 'The pattern to search for. It can be either a `string` or an `array` with strings.',
							'required'    => true,
							'default'     => null,
						),
						'replacement' => array(
							'type'        => 'array|string',
							'description' => 'The `string` or an `array` with strings to replace.',
							'required'    => true,
							'default'     => null,
						),
						'subject'     => array(
							'type'        => 'array|string',
							'description' => 'The `string` or an `array` with strings to search and replace.',
							'required'    => true,
							'default'     => null,
						),
						'limit'       => array(
							'type'        => 'int',
							'description' => 'The maximum possible replacements for each `pattern` in each `subject` `string`. Defaults to `-1` (no limit).',
							'required'    => false,
							'default'     => -1,
						),
						'count'       => array(
							'type'        => 'int',
							'description' => '(This is ignored by the expression engine) If specified, this variable will be filled with the number of replacements done.',
							'required'    => false,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.preg-replace.php',
					),
					'return'      => array(
						'type'        => 'array|string|null',
						'description' => 'Returns an `array` if the `subject` parameter is an `array`, or a `string` otherwise.',
					),
				),
				'callback' => new ExpressionFunction(
					'regex_replace',
					// Pass by reference $count parameter is ignored due to security reasons.
					// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
					// phpcs:disable Universal.WhiteSpace.DisallowInlineTabs.NonIndentTabsUsed
					function ( $pattern, $replacement, $subject, $limit = -1, $count = null ) {
						// Compilation is not supported.
					},
					function ( $values, $pattern, $replacement, $subject, $limit = -1, $count = null ) {
						return self::safe_pcre_call(
							function () use ( $pattern, $replacement, $subject, $limit ) {
								// phpcs:ignore
								$result = @preg_replace( $pattern, $replacement, $subject, (int) $limit );
								if ( null === $result || PREG_NO_ERROR !== preg_last_error() ) {
									return $subject;
								}
								return $result;
							}
						);
					}
					// phpcs:enable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
					// phpcs:enable Universal.WhiteSpace.DisallowInlineTabs.NonIndentTabsUsed
				),
			),
			'str_contains'          => array(
				'docs'     => array(
					'summary'     => 'Determine if a string contains a given substring',
					'description' => "Performs a case-sensitive check indicating if `needle` is contained in `haystack`.\n\nExample:\n\n```elscript\nstr_contains('Expression Lab', 'Lab')\n```",
					'parameters'  => array(
						'haystack' => array(
							'type'        => 'string',
							'description' => 'The `string` to search in.',
							'required'    => true,
							'default'     => null,
						),
						'needle'   => array(
							'type'        => 'string',
							'description' => 'The substring to search for in the `haystack`.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.str-contains.php',
					),
					'return'      => array(
						'type'        => 'bool',
						'description' => 'Returns `true` if `needle` is in `haystack`, `false` otherwise.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'str_contains' ),
			),
			'str_starts_with'       => array(
				'docs'     => array(
					'summary'     => 'Checks if a string starts with a given substring',
					'description' => "Performs a case-sensitive check indicating if `haystack` begins with `needle`.\n\nExample:\n\n```elscript\nstr_starts_with('wp_posts', 'wp_')\n```",
					'parameters'  => array(
						'haystack' => array(
							'type'        => 'string',
							'description' => 'The `string` to search in.',
							'required'    => true,
							'default'     => null,
						),
						'needle'   => array(
							'type'        => 'string',
							'description' => 'The substring to search for in the `haystack`.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.str-starts-with.php',
					),
					'return'      => array(
						'type'        => 'bool',
						'description' => 'Returns `true` if `haystack` begins with `needle`, `false` otherwise.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'str_starts_with' ),
			),
			'str_ends_with'         => array(
				'docs'     => array(
					'summary'     => 'Checks if a string ends with a given substring',
					'description' => "Performs a case-sensitive check indicating if `haystack` ends with `needle`.\n\nExample:\n\n```elscript\nstr_ends_with('document.pdf', '.pdf')\n```",
					'parameters'  => array(
						'haystack' => array(
							'type'        => 'string',
							'description' => 'The `string` to search in.',
							'required'    => true,
							'default'     => null,
						),
						'needle'   => array(
							'type'        => 'string',
							'description' => 'The substring to search for in the `haystack`.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.str-ends-with.php',
					),
					'return'      => array(
						'type'        => 'bool',
						'description' => 'Returns `true` if `haystack` ends with `needle`, `false` otherwise.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'str_ends_with' ),
			),
			'stripos'               => array(
				'docs'     => array(
					'summary'     => 'Find position of first occurrence of a case-insensitive substring',
					'description' => "Find the numeric position of the first occurrence of `needle` in the `haystack` `string`, case-insensitive.\n\nExample:\n\n```elscript\nstripos('Hello World', 'world')\n```",
					'parameters'  => array(
						'haystack' => array(
							'type'        => 'string',
							'description' => 'The `string` to search in.',
							'required'    => true,
							'default'     => null,
						),
						'needle'   => array(
							'type'        => 'string',
							'description' => 'The substring to search for.',
							'required'    => true,
							'default'     => null,
						),
						'offset'   => array(
							'type'        => 'int',
							'description' => 'If specified, search will start this number of characters into the `string`.',
							'required'    => false,
							'default'     => 0,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.stripos.php',
					),
					'return'      => array(
						'type'        => 'int|false',
						'description' => 'Returns the position of where the `needle` exists relative to the beginning of the `haystack` `string` (independent of `offset`). Also note that `string` positions start at `0`, and not `1`. Returns `false` if the `needle` was not found.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'stripos' ),
			),
			'substr_count'          => array(
				'docs'     => array(
					'summary'     => 'Count the number of substring occurrences',
					'description' => "Returns the number of times the `needle` substring occurs in the `haystack` `string`. Note that `needle` is case-sensitive.\n\nExample:\n\n```elscript\nsubstr_count('banana', 'an')\n```",
					'parameters'  => array(
						'haystack' => array(
							'type'        => 'string',
							'description' => 'The `string` to search in.',
							'required'    => true,
							'default'     => null,
						),
						'needle'   => array(
							'type'        => 'string',
							'description' => 'The substring to search for.',
							'required'    => true,
							'default'     => null,
						),
						'offset'   => array(
							'type'        => 'int',
							'description' => 'The `offset` where to start counting. If the `offset` is negative, counting starts from the end of the `string`.',
							'required'    => false,
							'default'     => 0,
						),
						'length'   => array(
							'type'        => 'int|null',
							'description' => 'The maximum length after the specified `offset` to search for the substring. It outputs a warning if the `offset` plus the `length` is greater than the `haystack` length.',
							'required'    => false,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.substr-count.php',
					),
					'return'      => array(
						'type'        => 'int',
						'description' => 'This function returns an `int`.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'substr_count' ),
			),
			'urlencode'             => array(
				'docs'     => array(
					'summary'     => 'URL-encodes string',
					'description' => "This function is convenient when encoding a `string` to be used in a query part of a `URL`, as a convenient way to pass variables to the next page.\n\nExample:\n\n```elscript\nurlencode('category=news&order=desc')\n```",
					'parameters'  => array(
						'string' => array(
							'type'        => 'string',
							'description' => 'The `string` to be encoded.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.urlencode.php',
					),
					'return'      => array(
						'type'        => 'string',
						'description' => 'Returns a `string` in which all non-alphanumeric characters except `-_.~` have been replaced with a percent (`%`) sign followed by two hex digits and spaces encoded as plus (`+`) signs.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'urlencode' ),
			),
			'urldecode'             => array(
				'docs'     => array(
					'summary'     => 'Decodes URL-encoded string',
					'description' => "Decodes any `%##` encoding in the given `string`. Plus symbols (`'+'`) are decoded to a space character.\n\nExample:\n\n```elscript\nurldecode('category%3Dnews%26order%3Ddesc')\n```",
					'parameters'  => array(
						'string' => array(
							'type'        => 'string',
							'description' => 'The `string` to be decoded.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.urldecode.php',
					),
					'return'      => array(
						'type'        => 'string',
						'description' => 'Returns the decoded `string`.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'urldecode' ),
			),
			'mb_strlen'             => array(
				'docs'     => array(
					'summary'     => 'Get string length (multibyte safe)',
					'description' => "Gets the length of a `string`, taking character encoding into account.\n\nExample:\n\n```elscript\nmb_strlen('Español', 'UTF-8')\n```",
					'parameters'  => array(
						'the_string' => array(
							'type'        => 'string',
							'description' => 'The `string` being measured for length.',
							'required'    => true,
							'default'     => null,
						),
						'encoding'   => array(
							'type'        => 'string|null',
							'description' => 'The character encoding. If omitted, `UTF-8` is used.',
							'required'    => false,
							'default'     => "'UTF-8'",
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.mb-strlen.php',
					),
					'return'      => array(
						'type'        => 'int',
						'description' => 'The length of the `string` in characters, or `false` on failure.',
					),
				),
				'callback' => new ExpressionFunction(
					'mb_strlen',
					function ( $the_string, $encoding = 'UTF-8' ) {
						// Compilation is not supported.
					},
					function ( $values, $the_string, $encoding = 'UTF-8' ) {
						return mb_strlen( (string) $the_string, $encoding ? $encoding : 'UTF-8' );
					}
				),
			),
			'mb_substr'             => array(
				'docs'     => array(
					'summary'     => 'Get part of string (multibyte safe)',
					'description' => "Performs a multi-byte safe `substr()` operation based on number of characters.\n\nExample:\n\n```elscript\nmb_substr('Español', 0, 4, 'UTF-8')\n```",
					'parameters'  => array(
						'the_string' => array(
							'type'        => 'string',
							'description' => 'The `string` to extract the substring from.',
							'required'    => true,
							'default'     => null,
						),
						'start'      => array(
							'type'        => 'int',
							'description' => 'If `start` is not negative, the returned `string` will start at the `start`\'th position in `the_string`, counting from zero.',
							'required'    => true,
							'default'     => null,
						),
						'length'     => array(
							'type'        => 'int|null',
							'description' => 'Maximum number of characters to use from `the_string`.',
							'required'    => false,
							'default'     => null,
						),
						'encoding'   => array(
							'type'        => 'string|null',
							'description' => 'The character encoding. If omitted, `UTF-8` is used.',
							'required'    => false,
							'default'     => "'UTF-8'",
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.mb-substr.php',
					),
					'return'      => array(
						'type'        => 'string',
						'description' => 'Returns the extracted part of `the_string` or `false` on failure.',
					),
				),
				'callback' => new ExpressionFunction(
					'mb_substr',
					function ( $the_string, $start, $length = null, $encoding = 'UTF-8' ) {
						// Compilation is not supported.
					},
					function ( $values, $the_string, $start, $length = null, $encoding = 'UTF-8' ) {
						return mb_substr( (string) $the_string, (int) $start, null !== $length ? (int) $length : null, $encoding ? $encoding : 'UTF-8' );
					}
				),
			),
			'mb_strpos'             => array(
				'docs'     => array(
					'summary'     => 'Find position of first occurrence of string (multibyte safe)',
					'description' => "Performs a multi-byte safe `strpos()` operation based on number of characters.\n\nExample:\n\n```elscript\nmb_strpos('Español', 'ñ', 0, 'UTF-8')\n```",
					'parameters'  => array(
						'haystack' => array(
							'type'        => 'string',
							'description' => 'The `string` being checked.',
							'required'    => true,
							'default'     => null,
						),
						'needle'   => array(
							'type'        => 'string',
							'description' => 'The `string` being searched for.',
							'required'    => true,
							'default'     => null,
						),
						'offset'   => array(
							'type'        => 'int',
							'description' => 'The search `offset`. If it is not specified, `0` is used.',
							'required'    => false,
							'default'     => 0,
						),
						'encoding' => array(
							'type'        => 'string|null',
							'description' => 'The character encoding. If omitted, `UTF-8` is used.',
							'required'    => false,
							'default'     => "'UTF-8'",
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.mb-strpos.php',
					),
					'return'      => array(
						'type'        => 'int|false',
						'description' => 'Returns the numeric position of the first occurrence of `needle` in `haystack`, or `false` if `needle` is not found.',
					),
				),
				'callback' => new ExpressionFunction(
					'mb_strpos',
					function ( $haystack, $needle, $offset = 0, $encoding = 'UTF-8' ) {
						// Compilation is not supported.
					},
					function ( $values, $haystack, $needle, $offset = 0, $encoding = 'UTF-8' ) {
						return mb_strpos( (string) $haystack, (string) $needle, (int) $offset, $encoding ? $encoding : 'UTF-8' );
					}
				),
			),
			'mb_strtolower'         => array(
				'docs'     => array(
					'summary'     => 'Make a string lowercase (multibyte safe)',
					'description' => "Returns `string` with all alphabetic characters converted to lowercase, taking character encoding into account.\n\nExample:\n\n```elscript\nmb_strtolower('ESPAÑOL', 'UTF-8')\n```",
					'parameters'  => array(
						'the_string' => array(
							'type'        => 'string',
							'description' => 'The `string` being lowercased.',
							'required'    => true,
							'default'     => null,
						),
						'encoding'   => array(
							'type'        => 'string|null',
							'description' => 'The character encoding. If omitted, `UTF-8` is used.',
							'required'    => false,
							'default'     => "'UTF-8'",
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.mb-strtolower.php',
					),
					'return'      => array(
						'type'        => 'string',
						'description' => 'Returns `string` with all alphabetic characters converted to lowercase.',
					),
				),
				'callback' => new ExpressionFunction(
					'mb_strtolower',
					function ( $the_string, $encoding = 'UTF-8' ) {
						// Compilation is not supported.
					},
					function ( $values, $the_string, $encoding = 'UTF-8' ) {
						return mb_strtolower( (string) $the_string, $encoding ? $encoding : 'UTF-8' );
					}
				),
			),
			'mb_strtoupper'         => array(
				'docs'     => array(
					'summary'     => 'Make a string uppercase (multibyte safe)',
					'description' => "Returns `string` with all alphabetic characters converted to uppercase, taking character encoding into account.\n\nExample:\n\n```elscript\nmb_strtoupper('español', 'UTF-8')\n```",
					'parameters'  => array(
						'the_string' => array(
							'type'        => 'string',
							'description' => 'The `string` being uppercased.',
							'required'    => true,
							'default'     => null,
						),
						'encoding'   => array(
							'type'        => 'string|null',
							'description' => 'The character encoding. If omitted, `UTF-8` is used.',
							'required'    => false,
							'default'     => "'UTF-8'",
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.mb-strtoupper.php',
					),
					'return'      => array(
						'type'        => 'string',
						'description' => 'Returns `string` with all alphabetic characters converted to uppercase.',
					),
				),
				'callback' => new ExpressionFunction(
					'mb_strtoupper',
					function ( $the_string, $encoding = 'UTF-8' ) {
						// Compilation is not supported.
					},
					function ( $values, $the_string, $encoding = 'UTF-8' ) {
						return mb_strtoupper( (string) $the_string, $encoding ? $encoding : 'UTF-8' );
					}
				),
			),
			'ucfirst'               => array(
				'docs'     => array(
					'summary'     => 'Make a string\'s first character uppercase',
					'description' => "Returns a `string` with the first character of `string` capitalized, if that character is an `ASCII` character.\n\nExample:\n\n```elscript\nucfirst('hello world')\n```",
					'parameters'  => array(
						'string' => array(
							'type'        => 'string',
							'description' => 'The input `string`.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.ucfirst.php',
					),
					'return'      => array(
						'type'        => 'string',
						'description' => 'Returns the resulting `string`.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'ucfirst' ),
			),
			'ucwords'               => array(
				'docs'     => array(
					'summary'     => 'Uppercase the first character of each word in a string',
					'description' => "Returns a `string` with the first character of each word in `string` capitalized, if that character is an `ASCII` character.\n\nExample:\n\n```elscript\nucwords('hello world')\n```",
					'parameters'  => array(
						'string'     => array(
							'type'        => 'string',
							'description' => 'The input `string`.',
							'required'    => true,
							'default'     => null,
						),
						'separators' => array(
							'type'        => 'string',
							'description' => 'The optional `separators` contains the word separator characters.',
							'required'    => false,
							'default'     => '`" \t\r\n\f\v"`',
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.ucwords.php',
					),
					'return'      => array(
						'type'        => 'string',
						'description' => 'Returns the modified `string`.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'ucwords' ),
			),
			// #endregion

			// #region 2. Array Functions
			'in_array'              => array(
				'docs'     => array(
					'summary'     => 'Checks if a value exists in an array',
					'description' => "Checks if `needle` is in `haystack` using loose or strict comparison.\n\nExample:\n\n```elscript\nin_array('admin', ['admin', 'editor', 'author'])\n```\n\nAlternatively, the `in` language constructor can be used:\n\n```elscript\n'admin' in ['admin', 'editor', 'author']\n```\nThere are also a built-in `not in` operator available:\n\n```elscript\n'admin' not in ['editor', 'author']\n```",
					'parameters'  => array(
						'needle'   => array(
							'type'        => 'mixed',
							'description' => 'The searched value.',
							'required'    => true,
							'default'     => null,
						),
						'haystack' => array(
							'type'        => 'array',
							'description' => 'The `array`.',
							'required'    => true,
							'default'     => null,
						),
						'strict'   => array(
							'type'        => 'bool',
							'description' => 'If `strict` is set to `true` then the function will also check the types of the `needle` in the `haystack`.',
							'required'    => false,
							'default'     => false,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.in-array.php',
						'https://expressionlab.io/docs/getting-started/basic-syntax#membership-operators-in-not-in',
					),
					'return'      => array(
						'type'        => 'bool',
						'description' => 'Returns `true` if `needle` is found in the `array`, `false` otherwise.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'in_array' ),
			),
			'merge'                 => array(
				'docs'     => array(
					'summary'     => 'Merge one or more arrays',
					'description' => "Merges the elements of one or more arrays together so that the values of one are appended to the end of the previous one.\n\nExample:\n\n```elscript\nmerge([1, 2], [3, 4])\n```",
					'parameters'  => array(
						'...arrays' => array(
							'type'        => 'array',
							'description' => 'Variable list of arrays to merge.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.array-merge.php',
					),
					'return'      => array(
						'type'        => 'array',
						'description' => 'Returns the resulting `array`.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'array_merge', 'merge' ),
			),
			'count'                 => array(
				'docs'     => array(
					'summary'     => 'Counts all elements in an array or in a Countable object',
					'description' => "Counts all elements in an `array` or countable `object`.\n\nExample:\n\n```elscript\ncount(['apple', 'banana', 'cherry'])\n```",
					'parameters'  => array(
						'value' => array(
							'type'        => 'Countable|array',
							'description' => 'An `array` or countable `object`.',
							'required'    => true,
							'default'     => null,
						),
						'mode'  => array(
							'type'        => 'int',
							'description' => 'If the optional `mode` parameter is set to `COUNT_RECURSIVE` (or `1`), `count()` will recursively count the `array`.',
							'required'    => false,
							'default'     => '`COUNT_NORMAL`',
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.count.php',
					),
					'return'      => array(
						'type'        => 'int',
						'description' => 'Returns the number of elements in `value`.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'count' ),
			),
			'keys'                  => array(
				'docs'     => array(
					'summary'     => 'Return all the keys or a subset of the keys of an array',
					'description' => "Returns the keys, numeric and string, from the `array`.\n\nExample:\n\n```elscript\nkeys({ 'id': 1, 'name': 'Admin', 'role': 'administrator' })\n```",
					'parameters'  => array(
						// Note: we are implicitly using the simplified version of array_keys() that only accepts the array parameter.
						'array' => array(
							'type'        => 'array',
							'description' => 'An `array` containing keys to return.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.array-keys.php',
					),
					'return'      => array(
						'type'        => 'array',
						'description' => 'Returns an `array` of all the keys in `array`.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'array_keys', 'keys' ),
			),
			'values'                => array(
				'docs'     => array(
					'summary'     => 'Return all the values of an array',
					'description' => "Returns all the values from the `array` and indexes the `array` numerically.\n\nExample:\n\n```elscript\nvalues({ 'id': 1, 'name': 'Admin', 'role': 'administrator' })\n```",
					'parameters'  => array(
						'array' => array(
							'type'        => 'array',
							'description' => 'The `array`.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.array-values.php',
					),
					'return'      => array(
						'type'        => 'array',
						'description' => 'Returns an indexed `array` of values.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'array_values', 'values' ),
			),
			'sum'                   => array(
				'docs'     => array(
					'summary'     => 'Calculate the sum of values in an array',
					'description' => "Returns the sum of values in an `array`.\n\nExample:\n\n```elscript\nsum([10, 20, 30, 40])\n```",
					'parameters'  => array(
						'array' => array(
							'type'        => 'array',
							'description' => 'The `array`.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.array-sum.php',
					),
					'return'      => array(
						'type'        => 'int|float',
						'description' => 'Returns the sum of values as an `int` or `float`.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'array_sum', 'sum' ),
			),
			'diff'                  => array(
				'docs'     => array(
					'summary'     => 'Computes the difference of arrays',
					'description' => "Compares `array` against one or more other arrays and returns the values in `array` that are not present in any of the other arrays.\n\nExample:\n\n```elscript\ndiff([1, 2, 3, 4], [2, 4])\n```",
					'parameters'  => array(
						'array1'    => array(
							'type'        => 'array',
							'description' => 'The array to compare from.',
							'required'    => true,
							'default'     => null,
						),
						'...arrays' => array(
							'type'        => 'array',
							'description' => 'Arrays to compare against',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.array-diff.php',
					),
					'return'      => array(
						'type'        => 'array',
						'description' => 'Returns an `array` containing all the entries from `array` that are not present in any of the other arrays.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'array_diff', 'diff' ),
			),
			'intersect'             => array(
				'docs'     => array(
					'summary'     => 'Computes the intersection of arrays',
					'description' => "Returns an `array` containing all the values of `array` that are present in all the arguments.\n\nExample:\n\n```elscript\nintersect([1, 2, 3], [2, 3, 4])\n```",
					'parameters'  => array(
						'array'     => array(
							'type'        => 'array',
							'description' => 'The `array` with master values to check.',
							'required'    => true,
							'default'     => null,
						),
						'...arrays' => array(
							'type'        => 'array',
							'description' => 'The `array` with master values to check.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.array-intersect.php',
					),
					'return'      => array(
						'type'        => 'array',
						'description' => 'Returns an `array` containing all of the values in `array` whose values exist in all of the parameters.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'array_intersect', 'intersect' ),
			),
			'flip'                  => array(
				'docs'     => array(
					'summary'     => 'Exchanges all keys with their associated values in an array',
					'description' => "Returns an `array` in flip order, i.e. keys from `array` become values and values from `array` become keys.\n\nExample:\n\n```elscript\nflip({ 'a': 1, 'b': 2, 'c': 3 })\n```",
					'parameters'  => array(
						'array' => array(
							'type'        => 'array',
							'description' => 'An `array` of `key => value` pairs to be flipped.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.array-flip.php',
					),
					'return'      => array(
						'type'        => 'array',
						'description' => 'Returns the flipped `array`.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'array_flip', 'flip' ),
			),
			'reverse'               => array(
				'docs'     => array(
					'summary'     => 'Return an array with elements in reverse order',
					'description' => "Takes an input `array` and returns a new `array` with the order of the elements reversed.\n\nExample:\n\n```elscript\nreverse([1, 2, 3, 4, 5])\n```",
					'parameters'  => array(
						'array'         => array(
							'type'        => 'array',
							'description' => 'The input `array`.',
							'required'    => true,
							'default'     => null,
						),
						'preserve_keys' => array(
							'type'        => 'bool',
							'description' => 'If set to `true` numeric keys are preserved. Non-numeric keys are not affected by this setting and will always be preserved.',
							'required'    => false,
							'default'     => false,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.array-reverse.php',
					),
					'return'      => array(
						'type'        => 'array',
						'description' => 'Returns the reversed `array`.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'array_reverse', 'reverse' ),
			),
			'unique'                => array(
				'docs'     => array(
					'summary'     => 'Removes duplicate values from an array',
					'description' => "Takes an input `array` and returns a new `array` without duplicate values.\n\nExample:\n\n```elscript\nunique(['apple', 'banana', 'apple', 'orange'])\n```",
					'parameters'  => array(
						'array' => array(
							'type'        => 'array',
							'description' => 'The input `array`.',
							'required'    => true,
							'default'     => null,
						),
						'flags' => array(
							'type'        => 'int',
							'description' => 'The optional second parameter `flags` may be used to modify the comparison behavior.',
							'required'    => false,
							'default'     => '`SORT_STRING`',
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.array-unique.php',
					),
					'return'      => array(
						'type'        => 'array',
						'description' => 'Returns the filtered `array`.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'array_unique', 'unique' ),
			),
			'column'                => array(
				'docs'     => array(
					'summary'     => 'Return the values from a single column in the input array',
					'description' => "Returns the values from a single column of the `array`, identified by the `column_key`.\n\nExample:\n\n```elscript\ncolumn([{ 'id': 1, 'name': 'Alice' }, { 'id': 2, 'name': 'Bob' }], 'name')\n```",
					'parameters'  => array(
						'array'      => array(
							'type'        => 'array',
							'description' => 'A multi-dimensional `array` or an `array` of objects from which to pull a column of values from.',
							'required'    => true,
							'default'     => null,
						),
						'column_key' => array(
							'type'        => 'int|string',
							'description' => 'The column of values to return.',
							'required'    => true,
							'default'     => null,
						),
						'index_key'  => array(
							'type'        => 'int|string|null',
							'description' => 'The column to use as index/keys for the returned `array`.',
							'required'    => false,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.array-column.php',
					),
					'return'      => array(
						'type'        => 'array',
						'description' => 'Returns an `array` of values representing a single column from the input `array`.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'array_column', 'column' ),
			),
			'slice'                 => array(
				'docs'     => array(
					'summary'     => 'Extract a slice of the array',
					'description' => "Extracts a slice of the `array`.\n\nExample:\n\n```elscript\nslice([1, 2, 3, 4, 5], 1, 3)\n```",
					'parameters'  => array(
						'array'         => array(
							'type'        => 'array',
							'description' => 'The input `array`.',
							'required'    => true,
							'default'     => null,
						),
						'offset'        => array(
							'type'        => 'int',
							'description' => 'If `offset` is non-negative, the sequence will start at that `offset` in the `array`.',
							'required'    => true,
							'default'     => null,
						),
						'length'        => array(
							'type'        => 'int|null',
							'description' => 'If `length` is given and is positive, then the sequence will have up to that many elements in it.',
							'required'    => false,
							'default'     => null,
						),
						'preserve_keys' => array(
							'type'        => 'bool',
							'description' => 'Note that `slice()` will reset and reorder the integer `array` indices by default unless `preserve_keys` is set to `true`.',
							'required'    => false,
							'default'     => false,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.array-slice.php',
					),
					'return'      => array(
						'type'        => 'array',
						'description' => 'Returns the slice.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'array_slice', 'slice' ),
			),
			'search'                => array(
				'docs'     => array(
					'summary'     => 'Searches the array for a given value and returns the first corresponding key if successful',
					'description' => "Searches `haystack` for `needle` and returns the key if found, `false` otherwise.\n\nExample:\n\n```elscript\nsearch('banana', ['apple', 'banana', 'cherry'])\n```",
					'parameters'  => array(
						'needle'   => array(
							'type'        => 'mixed',
							'description' => 'The searched value.',
							'required'    => true,
							'default'     => null,
						),
						'haystack' => array(
							'type'        => 'array',
							'description' => 'The `array`.',
							'required'    => true,
							'default'     => null,
						),
						'strict'   => array(
							'type'        => 'bool',
							'description' => 'If `strict` is set to `true`, `search()` will search for identical elements.',
							'required'    => false,
							'default'     => false,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.array-search.php',
					),
					'return'      => array(
						'type'        => 'int|string|false',
						'description' => 'Returns the key for `needle` if it is found in the `array`, `false` otherwise.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'array_search', 'search' ),
			),
			'key_exists'            => array(
				'docs'     => array(
					'summary'     => 'Checks if the given key or index exists in the array',
					'description' => "Checks if the given `key` or `index` exists in the `array`.\n\nExample:\n\n```elscript\nkey_exists('role', { 'name': 'Admin', 'role': 'administrator' })\n```",
					'parameters'  => array(
						'key'   => array(
							'type'        => 'string|int|float|bool|resource|null',
							'description' => 'Value to check.',
							'required'    => true,
							'default'     => null,
						),
						'array' => array(
							'type'        => 'array',
							'description' => 'An `array` with keys to check.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.array-key-exists.php',
					),
					'return'      => array(
						'type'        => 'bool',
						'description' => 'Returns `true` on success or `false` on failure.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'array_key_exists', 'key_exists' ),
			),
			'sort'                  => array(
				'docs'     => array(
					'summary'     => 'Sort an array in ascending order (Adaptation)',
					'description' => "Sorts an `array` in place (returns a sorted copy in expression context) in ascending order.\n\nExample:\n\n```elscript\nsort([5, 3, 1, 4, 2])\n```",
					'parameters'  => array(
						'array' => array(
							'type'        => 'array',
							'description' => 'The input `array`.',
							'required'    => true,
							'default'     => null,
						),
						'flags' => array(
							'type'        => 'int',
							'description' => 'The optional second parameter `flags` may be used to modify the sorting behavior.',
							'required'    => false,
							'default'     => '`SORT_REGULAR`',
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.sort.php',
					),
					'return'      => array(
						'type'        => 'array',
						'description' => 'Returns the sorted `array`.',
					),
				),
				'callback' => new ExpressionFunction(
					'sort',
					function ( ...$args ) {
						// Compilation is not supported.
					},
					function ( $values, ...$args ) {
						$array = array_shift( $args );
						$flags = array_shift( $args ) ?? SORT_REGULAR;
						$sorted_array = $array;
						sort( $sorted_array, $flags );
						return $sorted_array;
					}
				),
			),
			'rsort'                 => array(
				'docs'     => array(
					'summary'     => 'Sort an array in descending order (Adaptation)',
					'description' => "Sorts an `array` in place (returns a sorted copy in expression context) in descending order.\n\nExample:\n\n```elscript\nrsort([1, 2, 3, 4, 5])\n```",
					'parameters'  => array(
						'array' => array(
							'type'        => 'array',
							'description' => 'The input `array`.',
							'required'    => true,
							'default'     => null,
						),
						'flags' => array(
							'type'        => 'int',
							'description' => 'The optional second parameter `flags` may be used to modify the sorting behavior.',
							'required'    => false,
							'default'     => '`SORT_REGULAR`',
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.rsort.php',
					),
					'return'      => array(
						'type'        => 'array',
						'description' => 'Returns the sorted `array`.',
					),
				),
				'callback' => new ExpressionFunction(
					'rsort',
					function ( ...$args ) {
						// Compilation is not supported.
					},
					function ( $values, ...$args ) {
						$array = array_shift( $args );
						$flags = array_shift( $args ) ?? SORT_REGULAR;
						$sorted_array = $array;
						rsort( $sorted_array, $flags );
						return $sorted_array;
					}
				),
			),
			'asort'                 => array(
				'docs'     => array(
					'summary'     => 'Sort an array in ascending order and maintain index association (Adaptation)',
					'description' => "Sorts an `array` in place such that `array` indices maintain their correlation with the `array` elements to which they are associated, in ascending order.\n\nExample:\n\n```elscript\nasort({ 'b': 2, 'a': 1, 'c': 3 })\n```",
					'parameters'  => array(
						'array' => array(
							'type'        => 'array',
							'description' => 'The input `array`.',
							'required'    => true,
							'default'     => null,
						),
						'flags' => array(
							'type'        => 'int',
							'description' => 'The optional second parameter `flags` may be used to modify the sorting behavior.',
							'required'    => false,
							'default'     => '`SORT_REGULAR`',
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.asort.php',
					),
					'return'      => array(
						'type'        => 'array',
						'description' => 'Returns the sorted `array`.',
					),
				),
				'callback' => new ExpressionFunction(
					'asort',
					function ( ...$args ) {
						// Compilation is not supported.
					},
					function ( $values, ...$args ) {
						$array = array_shift( $args );
						$flags = array_shift( $args ) ?? SORT_REGULAR;
						$sorted_array = $array;
						asort( $sorted_array, $flags );
						return $sorted_array;
					}
				),
			),
			'arsort'                => array(
				'docs'     => array(
					'summary'     => 'Sort an array in descending order and maintain index association (Adaptation)',
					'description' => "Sorts an `array` in place such that `array` indices maintain their correlation with the `array` elements to which they are associated, in descending order.\n\nExample:\n\n```elscript\narsort({ 'a': 1, 'c': 3, 'b': 2 })\n```",
					'parameters'  => array(
						'array' => array(
							'type'        => 'array',
							'description' => 'The input `array`.',
							'required'    => true,
							'default'     => null,
						),
						'flags' => array(
							'type'        => 'int',
							'description' => 'The optional second parameter `flags` may be used to modify the sorting behavior.',
							'required'    => false,
							'default'     => '`SORT_REGULAR`',
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.arsort.php',
					),
					'return'      => array(
						'type'        => 'array',
						'description' => 'Returns the sorted `array`.',
					),
				),
				'callback' => new ExpressionFunction(
					'arsort',
					function ( ...$args ) {
						// Compilation is not supported.
					},
					function ( $values, ...$args ) {
						$array = array_shift( $args );
						$flags = array_shift( $args ) ?? SORT_REGULAR;
						$sorted_array = $array;
						arsort( $sorted_array, $flags );
						return $sorted_array;
					}
				),
			),
			'ksort'                 => array(
				'docs'     => array(
					'summary'     => 'Sort an array by key in ascending order (Adaptation)',
					'description' => "Sorts an `array` in place by keys in ascending order.\n\nExample:\n\n```elscript\nksort({ 'z': 26, 'a': 1, 'm': 13 })\n```",
					'parameters'  => array(
						'array' => array(
							'type'        => 'array',
							'description' => 'The input `array`.',
							'required'    => true,
							'default'     => null,
						),
						'flags' => array(
							'type'        => 'int',
							'description' => 'The optional second parameter `flags` may be used to modify the sorting behavior.',
							'required'    => false,
							'default'     => '`SORT_REGULAR`',
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.ksort.php',
					),
					'return'      => array(
						'type'        => 'array',
						'description' => 'Returns the sorted `array`.',
					),
				),
				'callback' => new ExpressionFunction(
					'ksort',
					function ( ...$args ) {
						// Compilation is not supported.
					},
					function ( $values, ...$args ) {
						$array = array_shift( $args );
						$flags = array_shift( $args ) ?? SORT_REGULAR;
						$sorted_array = $array;
						ksort( $sorted_array, $flags );
						return $sorted_array;
					}
				),
			),
			'krsort'                => array(
				'docs'     => array(
					'summary'     => 'Sort an array by key in descending order (Adaptation)',
					'description' => "Sorts an `array` in place by keys in descending order.\n\nExample:\n\n```elscript\nkrsort({ 'a': 1, 'z': 26, 'm': 13 })\n```",
					'parameters'  => array(
						'array' => array(
							'type'        => 'array',
							'description' => 'The input `array`.',
							'required'    => true,
							'default'     => null,
						),
						'flags' => array(
							'type'        => 'int',
							'description' => 'The optional second parameter `flags` may be used to modify the sorting behavior.',
							'required'    => false,
							'default'     => '`SORT_REGULAR`',
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.krsort.php',
					),
					'return'      => array(
						'type'        => 'array',
						'description' => 'Returns the sorted `array`.',
					),
				),
				'callback' => new ExpressionFunction(
					'krsort',
					function ( ...$args ) {
						// Compilation is not supported.
					},
					function ( $values, ...$args ) {
						$array = array_shift( $args );
						$flags = array_shift( $args ) ?? SORT_REGULAR;
						$sorted_array = $array;
						krsort( $sorted_array, $flags );
						return $sorted_array;
					}
				),
			),
			'shuffle'               => array(
				'docs'     => array(
					'summary'     => 'Shuffle an array (Adaptation)',
					'description' => "Randomizes the order of the elements in an `array`.\n\nExample:\n\n```elscript\nshuffle([1, 2, 3, 4, 5])\n```",
					'parameters'  => array(
						'array' => array(
							'type'        => 'array',
							'description' => 'The `array`.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.shuffle.php',
					),
					'return'      => array(
						'type'        => 'array',
						'description' => 'Returns the shuffled `array`.',
					),
				),
				'callback' => new ExpressionFunction(
					'shuffle',
					function ( ...$args ) {
						// Compilation is not supported.
					},
					function ( $values, ...$args ) {
						$array = array_shift( $args );
						$shuffled_array = $array;
						shuffle( $shuffled_array );
						return $shuffled_array;
					}
				),
			),
			'first'                 => array(
				'docs'     => array(
					'summary'     => 'Return the first element of an array',
					'description' => "Alias of `array_key_first()`, returns the first key of the given `array` without affecting the internal `array` pointer.\n\nExample:\n\n```elscript\nfirst(['apple', 'banana', 'cherry'])\n```",
					'parameters'  => array(
						'the_array' => array(
							'type'        => 'array',
							'description' => 'The input array.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.reset.php',
					),
					'return'      => array(
						'type'        => 'mixed',
						'description' => 'Returns the first key of `array` if the `array` is not empty; `null` otherwise.',
					),
				),
				'callback' => new ExpressionFunction(
					'first',
					function ( $the_array ) {
						// Compilation is not supported.
					},
					function ( $values, $the_array ) {
						if ( ! is_array( $the_array ) || empty( $the_array ) ) {
							return null;
						}
						$copy = $the_array;
						return reset( $copy );
					}
				),
			),
			'last'                  => array(
				'docs'     => array(
					'summary'     => 'Return the last element of an array',
					'description' => "Alias of `array_key_last()`, returns the last key of the given `array` without affecting the internal `array` pointer.\n\nExample:\n\n```elscript\nlast(['apple', 'banana', 'cherry'])\n```",
					'parameters'  => array(
						'the_array' => array(
							'type'        => 'array',
							'description' => 'The input array.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.end.php',
					),
					'return'      => array(
						'type'        => 'mixed',
						'description' => 'Returns the last key of `array` if the `array` is not empty; `null` otherwise.',
					),
				),
				'callback' => new ExpressionFunction(
					'last',
					function ( $the_array ) {
						// Compilation is not supported.
					},
					function ( $values, $the_array ) {
						if ( ! is_array( $the_array ) || empty( $the_array ) ) {
							return null;
						}
						$copy = $the_array;
						return end( $copy );
					}
				),
			),
			'array_key_first'       => array(
				'docs'     => array(
					'summary'     => 'Gets the first key of an array',
					'description' => "Gets the first key of the given `array` without affecting the internal `array` pointer.\n\nExample:\n\n```elscript\narray_key_first({ 'first_key': 1, 'second_key': 2 })\n```",
					'parameters'  => array(
						'array' => array(
							'type'        => 'array',
							'description' => 'An `array`.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.array-key-first.php',
					),
					'return'      => array(
						'type'        => 'int|string|null',
						'description' => 'Returns the first key of `array` if the `array` is not empty; `null` otherwise.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'array_key_first' ),
			),
			'array_key_last'        => array(
				'docs'     => array(
					'summary'     => 'Gets the last key of an array',
					'description' => "Gets the last key of the given `array` without affecting the internal `array` pointer.\n\nExample:\n\n```elscript\narray_key_last({ 'first_key': 1, 'second_key': 2 })\n```",
					'parameters'  => array(
						'array' => array(
							'type'        => 'array',
							'description' => 'An `array`.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.array-key-last.php',
					),
					'return'      => array(
						'type'        => 'int|string|null',
						'description' => 'Returns the last key of `array` if the `array` is not empty; `null` otherwise.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'array_key_last' ),
			),
			'array_chunk'           => array(
				'docs'     => array(
					'summary'     => 'Split an array into chunks',
					'description' => "Chunks an `array` into arrays with `length` elements. The last chunk may contain less than `length` elements.\n\nExample:\n\n```elscript\narray_chunk([1, 2, 3, 4, 5, 6], 2)\n```",
					'parameters'  => array(
						'array'         => array(
							'type'        => 'array',
							'description' => 'The `array` to work on.',
							'required'    => true,
							'default'     => null,
						),
						'length'        => array(
							'type'        => 'int',
							'description' => 'The size of each chunk.',
							'required'    => true,
							'default'     => null,
						),
						'preserve_keys' => array(
							'type'        => 'bool',
							'description' => 'When set to `true` keys will be preserved. Default is `false` which will reindex the chunk numerically.',
							'required'    => false,
							'default'     => false,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.array-chunk.php',
					),
					'return'      => array(
						'type'        => 'array',
						'description' => 'Returns a multidimensional numerically indexed `array`, starting with zero, with each dimension containing `length` elements.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'array_chunk' ),
			),
			'array_combine'         => array(
				'docs'     => array(
					'summary'     => 'Creates an array by using one array for keys and another for its values',
					'description' => "Creates an `array` by using one `array` for keys and another for its values.\n\nExample:\n\n```elscript\narray_combine(['name', 'role'], ['Alice', 'administrator'])\n```",
					'parameters'  => array(
						'keys'   => array(
							'type'        => 'array',
							'description' => '`array` of keys to be used. Illegal values for key will be converted to `string`.',
							'required'    => true,
							'default'     => null,
						),
						'values' => array(
							'type'        => 'array',
							'description' => '`array` of values to be used.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.array-combine.php',
					),
					'return'      => array(
						'type'        => 'array',
						'description' => 'Returns the combined `array`.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'array_combine' ),
			),
			// #endregion

			// #region 3. Math Functions
			'abs'                   => array(
				'docs'     => array(
					'summary'     => 'Absolute value',
					'description' => "Returns the absolute value of `num`.\n\nExample:\n\n```elscript\nabs(-42)\n```",
					'parameters'  => array(
						'num' => array(
							'type'        => 'int|float',
							'description' => 'The numeric value to process.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.abs.php',
					),
					'return'      => array(
						'type'        => 'int|float',
						'description' => 'The absolute value of `num`.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'abs' ),
			),
			'ceil'                  => array(
				'docs'     => array(
					'summary'     => 'Round fractions up',
					'description' => "Returns the next highest integer value by rounding up `num` if necessary.\n\nExample:\n\n```elscript\nceil(4.2)\n```",
					'parameters'  => array(
						'num' => array(
							'type'        => 'float',
							'description' => 'The value to round.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.ceil.php',
					),
					'return'      => array(
						'type'        => 'float',
						'description' => 'The next highest integer value of `num`.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'ceil' ),
			),
			'floor'                 => array(
				'docs'     => array(
					'summary'     => 'Round fractions down',
					'description' => "Returns the next lowest integer value (as `float`) by rounding down `num` if necessary.\n\nExample:\n\n```elscript\nfloor(4.9)\n```",
					'parameters'  => array(
						'num' => array(
							'type'        => 'int|float',
							'description' => 'The numeric value to round.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.floor.php',
					),
					'return'      => array(
						'type'        => 'float',
						'description' => 'The next lowest integer value of `num`.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'floor' ),
			),
			'round'                 => array(
				'docs'     => array(
					'summary'     => 'Round a float',
					'description' => "Returns the rounded value of `num` to specified `precision` (number of digits after the decimal point).\n\nExample:\n\n```elscript\nround(3.14159, 2)\n```",
					'parameters'  => array(
						'num'       => array(
							'type'        => 'int|float',
							'description' => 'The value to round.',
							'required'    => true,
							'default'     => null,
						),
						'precision' => array(
							'type'        => 'int',
							'description' => 'The optional number of decimal digits to round to.',
							'required'    => false,
							'default'     => 0,
						),
						'mode'      => array(
							'type'        => 'int',
							'description' => 'One of `PHP_ROUND_HALF_UP`, `PHP_ROUND_HALF_DOWN`, `PHP_ROUND_HALF_EVEN`, or `PHP_ROUND_HALF_ODD`.',
							'required'    => false,
							'default'     => '`PHP_ROUND_HALF_UP`',
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.round.php',
					),
					'return'      => array(
						'type'        => 'float',
						'description' => 'The value rounded to the given `precision` as a `float`.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'round' ),
			),
			'max'                   => array(
				'docs'     => array(
					'summary'     => 'Finds the highest value',
					'description' => "If the first and only parameter is an `array`, `max()` returns the highest value in that `array`. If at least two parameters are provided, `max()` returns the biggest of these values.\n\nExample:\n\n```elscript\nmax(10, 20, 5, 42)\n```",
					'parameters'  => array(
						'...values' => array(
							'type'        => 'mixed',
							'description' => 'Any comparable value.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.max.php',
					),
					'return'      => array(
						'type'        => 'mixed',
						'description' => 'The maximum value.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'max' ),
			),
			'min'                   => array(
				'docs'     => array(
					'summary'     => 'Finds the lowest value',
					'description' => "If the first and only parameter is an `array`, `min()` returns the lowest value in that `array`. If at least two parameters are provided, `min()` returns the smallest of these values.\n\nExample:\n\n```elscript\nmin(10, 20, 5, 42)\n```",
					'parameters'  => array(
						'...values' => array(
							'type'        => 'mixed',
							'description' => 'Any comparable value.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.min.php',
					),
					'return'      => array(
						'type'        => 'mixed',
						'description' => 'The minimum value.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'min' ),
			),
			'pow'                   => array(
				'docs'     => array(
					'summary'     => 'Exponential expression',
					'description' => "Returns `num` raised to the power of `exponent`.\n\nExample:\n\n```elscript\npow(2, 8)\n```",
					'parameters'  => array(
						'num'      => array(
							'type'        => 'mixed',
							'description' => 'The base to use.',
							'required'    => true,
							'default'     => null,
						),
						'exponent' => array(
							'type'        => 'mixed',
							'description' => 'The exponent.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.pow.php',
					),
					'return'      => array(
						'type'        => 'int|float|object',
						'description' => '`num` raised to the power of `exponent`.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'pow' ),
			),
			'sqrt'                  => array(
				'docs'     => array(
					'summary'     => 'Square root',
					'description' => "Returns the square root of `num`.\n\nExample:\n\n```elscript\nsqrt(144)\n```",
					'parameters'  => array(
						'num' => array(
							'type'        => 'int|float',
							'description' => 'The argument to treat.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.sqrt.php',
					),
					'return'      => array(
						'type'        => 'float',
						'description' => 'The square root of `num` or the special value `false` for negative or non-numeric values.',
					),
				),
				'callback' => new ExpressionFunction(
					'sqrt',
					function ( $num ) {
						// Compilation is not supported.
					},
					function ( $values, $num ) {
						if ( ! is_numeric( $num ) ) {
							LanguageEngine::get()->add_message( 'error', 'sqrt() error: `' . $num . '` is not a number' );
							return false;
						}
						if ( $num < 0 ) {
							LanguageEngine::get()->add_message( 'error', 'sqrt() error: `' . $num . '` is negative' );
							return false;
						}
						return sqrt( (float) $num );
					}
				),
			),
			'rand'                  => array(
				'docs'     => array(
					'summary'     => 'Generate a random integer',
					'description' => "Generates a pseudo-random integer between `min` and `max` (inclusive).\n\nExample:\n\n```elscript\nrand(1, 100)\n```",
					'parameters'  => array(
						'min' => array(
							'type'        => 'int',
							'description' => 'The lowest value to return (default: `0`).',
							'required'    => false,
							'default'     => 0,
						),
						'max' => array(
							'type'        => 'int',
							'description' => 'The highest value to return (default: `' . getrandmax() . '`).',
							'required'    => false,
							'default'     => getrandmax(),
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.rand.php',
					),
					'return'      => array(
						'type'        => 'int',
						'description' => 'A pseudo-random value between `min` (or `0`) and `max` (or `' . getrandmax() . '`, inclusive).',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'rand' ),
			),
			'mt_rand'               => array(
				'docs'     => array(
					'summary'     => 'Generate a random value via Mersenne Twister RNG',
					'description' => "Generates a random value via the Mersenne Twister algorithm, producing a pseudo-random integer between `min` and `max` (inclusive).\n\nExample:\n\n```elscript\nmt_rand(1, 100)\n```",
					'parameters'  => array(
						'min' => array(
							'type'        => 'int',
							'description' => 'Optional lowest value to be returned (default: `0`).',
							'required'    => false,
							'default'     => 0,
						),
						'max' => array(
							'type'        => 'int',
							'description' => 'Optional highest value to be returned (default: `' . mt_getrandmax() . '`).',
							'required'    => false,
							'default'     => mt_getrandmax(),
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.mt-rand.php',
					),
					'return'      => array(
						'type'        => 'int',
						'description' => 'A random integer value between `min` (or `0`) and `max` (or `' . mt_getrandmax() . '`, inclusive).',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'mt_rand' ),
			),
			'number_format'         => array(
				'docs'     => array(
					'summary'     => 'Format a number with grouped thousands',
					'description' => "Formats a number with grouped thousands and optionally decimal digits.\n\nExample:\n\n```elscript\nnumber_format(1234567.89, 2, '.', ',')\n```",
					'parameters'  => array(
						'num'                 => array(
							'type'        => 'float',
							'description' => 'The number being formatted.',
							'required'    => true,
							'default'     => null,
						),
						'decimals'            => array(
							'type'        => 'int',
							'description' => 'Sets the number of decimal points.',
							'required'    => false,
							'default'     => 0,
						),
						'decimal_separator'   => array(
							'type'        => 'string',
							'description' => 'Sets the separator for the decimal point.',
							'required'    => false,
							'default'     => "'.'",
						),
						'thousands_separator' => array(
							'type'        => 'string',
							'description' => 'Sets the thousands separator.',
							'required'    => false,
							'default'     => "','",
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.number-format.php',
					),
					'return'      => array(
						'type'        => 'string',
						'description' => 'A formatted version of `num`.',
					),
				),
				'callback' => new ExpressionFunction(
					'number_format',
					function ( ...$args ) {
						// Compilation is not supported.
					},
					function ( $values, $num, $decimals = 0, $decimal_separator = '.', $thousands_separator = ',' ) {
						return number_format( (float) $num, (int) $decimals, (string) $decimal_separator, (string) $thousands_separator );
					}
				),
			),
			// #endregion

			// #region 4. Date & Time Functions
			'date'                  => array(
				'docs'     => array(
					'summary'     => 'Format a Unix timestamp',
					'description' => "Returns a `string` formatted according to the given format `string` using the given integer `timestamp` (or current time if omitted).\n\nExample:\n\n```elscript\ndate('Y-m-d H:i:s')\n```",
					'parameters'  => array(
						'format'    => array(
							'type'        => 'string',
							'description' => 'Format accepted by `date()`.',
							'required'    => true,
							'default'     => null,
						),
						'timestamp' => array(
							'type'        => 'int|null',
							'description' => 'The optional `timestamp` parameter is an integer Unix timestamp that defaults to the current local time if omitted or `null`.',
							'required'    => false,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.date.php',
					),
					'return'      => array(
						'type'        => 'string',
						'description' => 'Returns a formatted date `string`.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'date' ),
			),
			'strtotime'             => array(
				'docs'     => array(
					'summary'     => 'Parse about any English textual datetime description into a Unix timestamp',
					'description' => "Parses an English textual datetime description into a Unix timestamp.\n\nExample:\n\n```elscript\nstrtotime('+1 day')\n```",
					'parameters'  => array(
						'datetime'      => array(
							'type'        => 'string',
							'description' => 'A date/time `string`.',
							'required'    => true,
							'default'     => null,
						),
						'baseTimestamp' => array(
							'type'        => 'int|null',
							'description' => 'A date/time `string`.',
							'required'    => false,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.strtotime.php',
					),
					'return'      => array(
						'type'        => 'int|false',
						'description' => 'Returns a timestamp on success, `false` otherwise.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'strtotime' ),
			),
			'time'                  => array(
				'docs'     => array(
					'summary'     => 'Return current Unix timestamp',
					'description' => "Returns the current time measured in the number of seconds since the Unix Epoch (`January 1 1970 00:00:00 GMT`).\n\nExample:\n\n```elscript\ntime()\n```",
					'parameters'  => array(),
					'see'         => array(
						'https://www.php.net/manual/en/function.time.php',
					),
					'return'      => array(
						'type'        => 'int',
						'description' => 'Returns the current timestamp.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'time' ),
			),
			'microtime'             => array(
				'docs'     => array(
					'summary'     => 'Return current Unix timestamp with microseconds',
					'description' => "Returns the current Unix timestamp with microseconds.\n\nExample:\n\n```elscript\nmicrotime(true)\n```",
					'parameters'  => array(
						'as_float' => array(
							'type'        => 'bool',
							'description' => 'If used and set to `true`, `microtime()` will return a `float` instead of a `string`.',
							'required'    => false,
							'default'     => false,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.microtime.php',
					),
					'return'      => array(
						'type'        => 'string|float',
						'description' => 'Returns a `string` or `float` representing the current time.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'microtime' ),
			),
			// #endregion

			// #region 5. Type Handling & Objects
			'is_array'              => array(
				'docs'     => array(
					'summary'     => 'Finds whether a variable is an array',
					'description' => "Finds whether the given variable is an `array`.\n\nExample:\n\n```elscript\nis_array([1, 2, 3])\n```",
					'parameters'  => array(
						'value' => array(
							'type'        => 'mixed',
							'description' => 'The variable being evaluated.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.is-array.php',
					),
					'return'      => array(
						'type'        => 'bool',
						'description' => 'Returns `true` if `value` is an `array`, `false` otherwise.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'is_array' ),
			),
			'is_string'             => array(
				'docs'     => array(
					'summary'     => 'Find whether the type of a variable is string',
					'description' => "Finds whether the type of the given variable is `string`.\n\nExample:\n\n```elscript\nis_string('hello')\n```",
					'parameters'  => array(
						'value' => array(
							'type'        => 'mixed',
							'description' => 'The variable being evaluated.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.is-string.php',
					),
					'return'      => array(
						'type'        => 'bool',
						'description' => 'Returns `true` if `value` is of type `string`, `false` otherwise.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'is_string' ),
			),
			'is_int'                => array(
				'docs'     => array(
					'summary'     => 'Find whether the type of a variable is integer',
					'description' => "Finds whether the type of the given variable is integer.\n\nExample:\n\n```elscript\nis_int(42)\n```",
					'parameters'  => array(
						'value' => array(
							'type'        => 'mixed',
							'description' => 'The variable being evaluated.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.is-int.php',
					),
					'return'      => array(
						'type'        => 'bool',
						'description' => 'Returns `true` if `value` is an `int`, `false` otherwise.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'is_int' ),
			),
			'is_float'              => array(
				'docs'     => array(
					'summary'     => 'Finds whether the type of a variable is float',
					'description' => "Finds whether the type of the given variable is `float`.\n\nExample:\n\n```elscript\nis_float(3.14)\n```",
					'parameters'  => array(
						'value' => array(
							'type'        => 'mixed',
							'description' => 'The variable being evaluated.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.is-float.php',
					),
					'return'      => array(
						'type'        => 'bool',
						'description' => 'Returns `true` if `value` is a `float`, `false` otherwise.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'is_float' ),
			),
			'is_bool'               => array(
				'docs'     => array(
					'summary'     => 'Finds out whether a variable is a boolean',
					'description' => "Finds whether the given variable is a boolean (`bool`).\n\nExample:\n\n```elscript\nis_bool(true)\n```",
					'parameters'  => array(
						'value' => array(
							'type'        => 'mixed',
							'description' => 'The variable being evaluated.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.is-bool.php',
					),
					'return'      => array(
						'type'        => 'bool',
						'description' => 'Returns `true` if `value` is a `bool`, `false` otherwise.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'is_bool' ),
			),
			'is_null'               => array(
				'docs'     => array(
					'summary'     => 'Finds whether a variable is null',
					'description' => "Finds whether the given variable is `null`.\n\nExample:\n\n```elscript\nis_null(null)\n```",
					'parameters'  => array(
						'value' => array(
							'type'        => 'mixed',
							'description' => 'The variable being evaluated.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.is-null.php',
					),
					'return'      => array(
						'type'        => 'bool',
						'description' => 'Returns `true` if `value` is `null`, `false` otherwise.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'is_null' ),
			),
			'is_numeric'            => array(
				'docs'     => array(
					'summary'     => 'Finds whether a variable is a number or a numeric string',
					'description' => "Finds whether the given variable is a number or a numeric `string`.\n\nExample:\n\n```elscript\nis_numeric('123.45')\n```",
					'parameters'  => array(
						'value' => array(
							'type'        => 'mixed',
							'description' => 'The variable being evaluated.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.is-numeric.php',
					),
					'return'      => array(
						'type'        => 'bool',
						'description' => 'Returns `true` if `value` is a number or a numeric `string`, `false` otherwise.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'is_numeric' ),
			),
			'is_object'             => array(
				'docs'     => array(
					'summary'     => 'Finds whether a variable is an object',
					'description' => "Finds whether the given variable is an `object`.\n\nExample:\n\n```elscript\nis_object(Posts)\n```",
					'parameters'  => array(
						'value' => array(
							'type'        => 'mixed',
							'description' => 'The variable being evaluated.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.is-object.php',
					),
					'return'      => array(
						'type'        => 'bool',
						'description' => 'Returns `true` if `value` is an `object`, `false` otherwise.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'is_object' ),
			),
			'is_closure'            => array(
				'docs'     => array(
					'summary'     => 'Verify that the given value is a closure.',
					'description' => "Use this function to check that the given value is a closure.\n\nExample:\n\n```elscript\nis_closure(fn[['x'], args['x'] ** 2])\n```",
					'parameters'  => array(
						'value' => array(
							'type'        => 'mixed',
							'description' => 'The value to check.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/language.operators.type.php',
						'https://www.php.net/manual/en/class.closure.php',
					),
					'return'      => array(
						'type'        => 'bool',
						'description' => 'Returns `true` if `value` is an instance of `\Closure`, `false` otherwise.',
					),
				),
				'callback' => new ExpressionFunction(
					'is_closure',
					function ( ...$args ) {
						// Compilation is not supported.
					},
					function ( $context, $value ) {
						return $value instanceof \Closure;
					}
				),
			),
			'is_scalar'             => array(
				'docs'     => array(
					'summary'     => 'Finds whether a variable is a scalar',
					'description' => "Finds whether the given variable is a scalar (`int`, `float`, `string`, or `bool`).\n\nExample:\n\n```elscript\nis_scalar('hello')\n```",
					'parameters'  => array(
						'value' => array(
							'type'        => 'mixed',
							'description' => 'The variable being evaluated.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.is-scalar.php',
					),
					'return'      => array(
						'type'        => 'bool',
						'description' => 'Returns `true` if `value` is a scalar, `false` otherwise.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'is_scalar' ),
			),
			'gettype'               => array(
				'docs'     => array(
					'summary'     => 'Get the type of a variable',
					'description' => "Returns the type of the given variable.\n\nExample:\n\n```elscript\ngettype([1, 2, 3])\n```",
					'parameters'  => array(
						'value' => array(
							'type'        => 'mixed',
							'description' => 'The variable being evaluated.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.gettype.php',
					),
					'return'      => array(
						'type'        => 'string',
						'description' => 'Possible values for the returned `string` are: `\'boolean\'`, `\'integer\'`, `\'double\'`, `\'string\'`, `\'array\'`, `\'object\'`, `\'resource\'`, `\'NULL\'`, `\'unknown type\'`.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'gettype' ),
			),
			'intval'                => array(
				'docs'     => array(
					'summary'     => 'Get the integer value of a variable',
					'description' => "Get the integer value of a variable.\n\nExample:\n\n```elscript\nintval('42')\n```",
					'parameters'  => array(
						'value' => array(
							'type'        => 'mixed',
							'description' => 'The scalar value being converted to an `int`.',
							'required'    => true,
							'default'     => null,
						),
						'base'  => array(
							'type'        => 'int',
							'description' => 'The base for the conversion.',
							'required'    => false,
							'default'     => 10,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.intval.php',
					),
					'return'      => array(
						'type'        => 'int',
						'description' => 'The integer value of `value` on success, or `0` on failure.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'intval' ),
			),
			'floatval'              => array(
				'docs'     => array(
					'summary'     => 'Get float value of a variable',
					'description' => "Get float value of a variable.\n\nExample:\n\n```elscript\nfloatval('3.14')\n```",
					'parameters'  => array(
						'value' => array(
							'type'        => 'mixed',
							'description' => 'May be any scalar type.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.floatval.php',
					),
					'return'      => array(
						'type'        => 'float',
						'description' => 'The `float` value of the given variable.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'floatval' ),
			),
			'strval'                => array(
				'docs'     => array(
					'summary'     => 'Get string value of a variable',
					'description' => "Get string value of a variable.\n\nExample:\n\n```elscript\nstrval(123)\n```",
					'parameters'  => array(
						'value' => array(
							'type'        => 'mixed',
							'description' => 'The variable that is being converted to a `string`.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.strval.php',
					),
					'return'      => array(
						'type'        => 'string',
						'description' => 'The `string` value of `value`.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'strval' ),
			),
			'boolval'               => array(
				'docs'     => array(
					'summary'     => 'Get the boolean value of a variable',
					'description' => "Get the boolean value of a variable.\n\nExample:\n\n```elscript\nboolval(1)\n```",
					'parameters'  => array(
						'value' => array(
							'type'        => 'mixed',
							'description' => 'The scalar value being converted to a `bool`.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.boolval.php',
					),
					'return'      => array(
						'type'        => 'bool',
						'description' => 'The `bool` value of `value`.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'boolval' ),
			),
			// #endregion

			// #region 6. Miscellaneous Functions
			'json_encode'           => array(
				'docs'     => array(
					'summary'     => 'Returns the JSON representation of a value',
					'description' => "Returns a `string` containing the `JSON` representation of the supplied `value`.\n\nExample:\n\n```elscript\njson_encode({ 'success': true, 'items': [1, 2, 3] }, JSON_PRETTY_PRINT)\n```",
					'parameters'  => array(
						'value' => array(
							'type'        => 'mixed',
							'description' => 'The `value` being encoded. Can be any type except a `resource`.',
							'required'    => true,
							'default'     => null,
						),
						'flags' => array(
							'type'        => 'int',
							'description' => 'Bitmask consisting of `JSON_HEX_TAG`, `JSON_HEX_AMP`, `JSON_HEX_APOS`, `JSON_HEX_QUOT`, `JSON_FORCE_OBJECT`, `JSON_NUMERIC_CHECK`, `JSON_UNESCAPED_SLASHES`, `JSON_PRETTY_PRINT`, `JSON_UNESCAPED_UNICODE`.',
							'required'    => false,
							'default'     => 0,
						),
						'depth' => array(
							'type'        => 'int',
							'description' => 'Set the maximum depth. Must be greater than zero.',
							'required'    => false,
							'default'     => 512,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.json-encode.php',
					),
					'return'      => array(
						'type'        => 'string|false',
						'description' => 'Returns a `JSON` encoded `string` on success or `false` on failure.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'json_encode' ),
			),
			'json_decode'           => array(
				'docs'     => array(
					'summary'     => 'Decodes a JSON string (Safe Associative Array by default)',
					'description' => "Takes a `JSON` encoded `string` and converts it into a PHP value.\n\nExample:\n\n```elscript\njson_decode('{\"success\":true,\"items\":[1,2,3]}', true)\n```",
					'parameters'  => array(
						'json'        => array(
							'type'        => 'string',
							'description' => 'The `json` `string` being decoded.',
							'required'    => true,
							'default'     => null,
						),
						'associative' => array(
							'type'        => 'bool',
							'description' => 'When `true`, `JSON` objects will be returned as associative arrays; when `false`, `JSON` objects will be returned as objects.',
							'required'    => false,
							'default'     => true,
						),
						'depth'       => array(
							'type'        => 'int',
							'description' => 'Maximum nesting depth of the structure being decoded.',
							'required'    => false,
							'default'     => 64,
						),
						'flags'       => array(
							'type'        => 'int',
							'description' => 'Bitmask of `JSON_BIGINT_AS_STRING`, `JSON_INVALID_UTF8_IGNORE`, `JSON_INVALID_UTF8_SUBSTITUTE`, `JSON_OBJECT_AS_ARRAY`, `JSON_THROW_ON_ERROR`.',
							'required'    => false,
							'default'     => 0,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.json-decode.php',
					),
					'return'      => array(
						'type'        => 'mixed',
						'description' => 'Returns the value encoded in `json` in appropriate PHP type.',
					),
				),
				'callback' => new ExpressionFunction(
					'json_decode',
					function ( $json, $associative = true, $depth = 64, $flags = 0 ) {
						// Compilation is not supported.
					},
					function ( $values, $json, $associative = true, $depth = 64, $flags = 0 ) {
						$safe_depth = is_numeric( $depth ) ? min( max( 1, (int) $depth ), 128 ) : 64;
						$safe_flags = is_numeric( $flags ) ? (int) $flags : 0;
						return json_decode( (string) $json, (bool) $associative, $safe_depth, $safe_flags );
					}
				),
			),
			'base64_encode'         => array(
				'docs'     => array(
					'summary'     => 'Encodes data with MIME base64',
					'description' => "Encodes the given `string` with base64.\n\nExample:\n\n```elscript\nbase64_encode('Hello World')\n```",
					'parameters'  => array(
						'string' => array(
							'type'        => 'string',
							'description' => 'The data to encode.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.base64-encode.php',
					),
					'return'      => array(
						'type'        => 'string',
						'description' => 'The encoded data, as a `string`.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'base64_encode' ),
			),
			'base64_decode'         => array(
				'docs'     => array(
					'summary'     => 'Decodes data encoded with MIME base64',
					'description' => "Decodes a base64 encoded `string`.\n\nExample:\n\n```elscript\nbase64_decode('SGVsbG8gV29ybGQ=')\n```",
					'parameters'  => array(
						'string' => array(
							'type'        => 'string',
							'description' => 'The encoded data.',
							'required'    => true,
							'default'     => null,
						),
						'strict' => array(
							'type'        => 'bool',
							'description' => 'If the input contains characters from outside the base64 alphabet, the function will return `false`.',
							'required'    => false,
							'default'     => false,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.base64-decode.php',
					),
					'return'      => array(
						'type'        => 'string|false',
						'description' => 'Returns the decoded data or `false` on failure.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'base64_decode' ),
			),
			'hash'                  => array(
				'docs'     => array(
					'summary'     => 'Generate a hash value (message digest)',
					'description' => "Generate a hash value (message digest).\n\nExample:\n\n```elscript\nhash('sha256', 'my_secret_token')\n```",
					'parameters'  => array(
						'algo'   => array(
							'type'        => 'string',
							'description' => 'Name of selected hashing algorithm (e.g. `\'md5\'`, `\'sha256\'`, `\'haval160,4\'`, etc.).',
							'required'    => true,
							'default'     => null,
						),
						'data'   => array(
							'type'        => 'string',
							'description' => 'Message to be hashed.',
							'required'    => true,
							'default'     => null,
						),
						'binary' => array(
							'type'        => 'bool',
							'description' => 'When set to `true`, outputs raw binary data. `false` outputs lowercase hexits.',
							'required'    => false,
							'default'     => false,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.hash.php',
					),
					'return'      => array(
						'type'        => 'string|false',
						'description' => 'Returns a `string` containing the calculated message digest as lowercase hexits unless `binary` is set to `true`.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'hash' ),
			),
			'hash_hmac'             => array(
				'docs'     => array(
					'summary'     => 'Generate a keyed hash value using the HMAC method',
					'description' => "Generate a keyed hash value using the HMAC method.\n\nExample:\n\n```elscript\nhash_hmac('sha256', 'message_data', 'secret_key')\n```",
					'parameters'  => array(
						'algo'   => array(
							'type'        => 'string',
							'description' => 'Name of selected hashing algorithm (e.g. `\'md5\'`, `\'sha256\'`, `\'haval160,4\'`, etc.).',
							'required'    => true,
							'default'     => null,
						),
						'data'   => array(
							'type'        => 'string',
							'description' => 'Message to be hashed.',
							'required'    => true,
							'default'     => null,
						),
						'key'    => array(
							'type'        => 'string',
							'description' => 'Shared secret key used for generating the HMAC variant of the message digest.',
							'required'    => true,
							'default'     => null,
						),
						'binary' => array(
							'type'        => 'bool',
							'description' => 'When set to `true`, outputs raw binary data. `false` outputs lowercase hexits.',
							'required'    => false,
							'default'     => false,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.hash-hmac.php',
					),
					'return'      => array(
						'type'        => 'string|false',
						'description' => 'Returns a `string` containing the calculated message digest as lowercase hexits unless `binary` is set to `true`.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'hash_hmac' ),
			),
			'ini_get'               => array(
				'docs'     => array(
					'summary'     => 'Gets the value of a configuration option (Safe Whitelist)',
					'description' => "Retrieves the value of a whitelisted configuration option for diagnostics. Safe subset only.\n\nExample:\n\n```elscript\nini_get('memory_limit')\n```",
					'parameters'  => array(
						'option' => array(
							'type'        => 'string',
							'description' => 'The configuration option name (must be in allowed list).',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.ini-get.php',
					),
					'return'      => array(
						'type'        => 'string|false',
						'description' => 'Returns the value of the configuration option as a `string` on success, or an empty `string` for `null` values. Returns `false` if the configuration option doesn\'t exist or is not allowed.',
					),
				),
				'callback' => new ExpressionFunction(
					'ini_get',
					function ( $option ) {
						// Compilation is not supported.
					},
					function ( $values, $option ) {
						if ( ! is_string( $option ) || ! in_array( strtolower( $option ), self::ALLOWED_INI_DIRECTIVES, true ) ) {
							return false;
						}
						return ini_get( $option );
					}
				),
			),
			'memory_get_usage'      => array(
				'docs'     => array(
					'summary'     => 'Returns the amount of memory allocated to PHP',
					'description' => "Returns the amount of memory, in bytes, that's currently being allocated to your PHP script.\n\nExample:\n\n```elscript\nmemory_get_usage(true)\n```",
					'parameters'  => array(
						'real_usage' => array(
							'type'        => 'bool',
							'description' => 'Set this to `true` to get the real size of memory allocated from system. If not set or `false` only the memory used by `emalloc()` is reported.',
							'required'    => false,
							'default'     => false,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.memory-get-usage.php',
					),
					'return'      => array(
						'type'        => 'int',
						'description' => 'Returns the memory usage in bytes.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'memory_get_usage' ),
			),
			'memory_get_peak_usage' => array(
				'docs'     => array(
					'summary'     => 'Returns the peak of memory allocated by PHP',
					'description' => "Returns the peak of memory, in bytes, that's been allocated to your PHP script.\n\nExample:\n\n```elscript\nmemory_get_peak_usage(true)\n```",
					'parameters'  => array(
						'real_usage' => array(
							'type'        => 'bool',
							'description' => 'Set this to `true` to get the real size of memory allocated from system. If not set or `false` only the memory used by `emalloc()` is reported.',
							'required'    => false,
							'default'     => false,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.memory-get-peak-usage.php',
					),
					'return'      => array(
						'type'        => 'int',
						'description' => 'Returns the memory peak in bytes.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'memory_get_peak_usage' ),
			),
			'extension_loaded'      => array(
				'docs'     => array(
					'summary'     => 'Find out whether an extension is loaded',
					'description' => "Finds out whether an extension is loaded.\n\nExample:\n\n```elscript\nextension_loaded('mbstring')\n```",
					'parameters'  => array(
						'extension' => array(
							'type'        => 'string',
							'description' => 'The extension name. This parameter is case-insensitive.',
							'required'    => true,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.extension-loaded.php',
					),
					'return'      => array(
						'type'        => 'bool',
						'description' => 'Returns `true` if the extension identified by `extension` is loaded, `false` otherwise.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'extension_loaded' ),
			),
			'phpversion'            => array(
				'docs'     => array(
					'summary'     => 'Gets the current PHP version or extension version',
					'description' => "Gets the current PHP version.\n\nExample:\n\n```elscript\nphpversion()\n```",
					'parameters'  => array(
						'extension' => array(
							'type'        => 'string|null',
							'description' => 'An optional extension name.',
							'required'    => false,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.phpversion.php',
					),
					'return'      => array(
						'type'        => 'string|false',
						'description' => 'If the optional `extension` parameter is specified, `phpversion()` returns the version of that extension, or `false` if the extension is not loaded.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'phpversion' ),
			),
			'version_compare'       => array(
				'docs'     => array(
					'summary'     => 'Compares two "PHP-standardized" version number strings',
					'description' => "Compares two \"PHP-standardized\" version number strings.\n\nExample:\n\n```elscript\nversion_compare(PHP_VERSION, '8.0.0', '>=')\n```",
					'parameters'  => array(
						'version1' => array(
							'type'        => 'string',
							'description' => 'First version number.',
							'required'    => true,
							'default'     => null,
						),
						'version2' => array(
							'type'        => 'string',
							'description' => 'Second version number.',
							'required'    => true,
							'default'     => null,
						),
						'operator' => array(
							'type'        => 'string|null',
							'description' => 'An optional operator. The possible operators are: `<`, `lt`, `<=`, `le`, `>`, `gt`, `>=`, `ge`, `==`, `=`, `eq`, `!=`, `<>`, `ne`.',
							'required'    => false,
							'default'     => null,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.version-compare.php',
					),
					'return'      => array(
						'type'        => 'int|bool',
						'description' => 'Returns `-1` if the first version is lower than the second, `0` if they are equal, and `1` if the second is lower. When using the optional operator argument, returns `true` if the relationship is the one specified by the operator, `false` otherwise.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'version_compare' ),
			),
			'md5'                   => array(
				'docs'     => array(
					'summary'     => 'Calculate the md5 hash of a string',
					'description' => "Calculate the md5 hash of a `string`.\n\nExample:\n\n```elscript\nmd5('secret_string')\n```",
					'parameters'  => array(
						'string' => array(
							'type'        => 'string',
							'description' => 'The `string`.',
							'required'    => true,
							'default'     => null,
						),
						'binary' => array(
							'type'        => 'bool',
							'description' => 'If the optional `binary` is set to `true`, then the md5 digest is instead returned in raw binary format with a length of `16`.',
							'required'    => false,
							'default'     => false,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.md5.php',
					),
					'return'      => array(
						'type'        => 'string',
						'description' => 'Returns the hash as a `32`-character hexadecimal number.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'md5' ),
			),
			'sha1'                  => array(
				'docs'     => array(
					'summary'     => 'Calculate the sha1 hash of a string',
					'description' => "Calculate the sha1 hash of a `string`.\n\nExample:\n\n```elscript\nsha1('secret_string')\n```",
					'parameters'  => array(
						'string' => array(
							'type'        => 'string',
							'description' => 'The input `string`.',
							'required'    => true,
							'default'     => null,
						),
						'binary' => array(
							'type'        => 'bool',
							'description' => 'If the optional `binary` is set to `true`, then the sha1 digest is instead returned in raw binary format with a length of `20`.',
							'required'    => false,
							'default'     => false,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.sha1.php',
					),
					'return'      => array(
						'type'        => 'string',
						'description' => 'Returns the sha1 hash as a `string`.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'sha1' ),
			),
			'uniqid'                => array(
				'docs'     => array(
					'summary'     => 'Generate a time-based identifier',
					'description' => "Gets a prefixed unique identifier based on the current time in microseconds.\n\nExample:\n\n```elscript\nuniqid('item_')\n```",
					'parameters'  => array(
						'prefix'       => array(
							'type'        => 'string',
							'description' => 'Can be useful, for instance, if you generate identifiers simultaneously on several hosts.',
							'required'    => false,
							'default'     => '',
						),
						'more_entropy' => array(
							'type'        => 'bool',
							'description' => 'If set to `true`, `uniqid()` will add additional entropy (using the combined linear congruential generator) at the end of the return value.',
							'required'    => false,
							'default'     => false,
						),
					),
					'see'         => array(
						'https://www.php.net/manual/en/function.uniqid.php',
					),
					'return'      => array(
						'type'        => 'string',
						'description' => 'Returns the unique identifier, as a `string`.',
					),
				),
				'callback' => ExpressionFunction::fromPhp( 'uniqid' ),
			),
			// #endregion
		);

		return $functions;
	}

	/**
	 * Retrieves all PHP extension constants and their documentation metadata.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array{docs: array, value: mixed}> Associative array of registered constants.
	 */
	public function get_constants(): array {
		return array(
			'PHP_VERSION'                     => array(
				'docs'  => array(
					'summary' => 'The current PHP version as a string',
					'type'    => 'string',
				),
				'value' => PHP_VERSION,
			),
			'JSON_BIGINT_AS_STRING'           => array(
				'docs'  => array(
					'summary' => 'Decodes large integers as their original `string` value',
					'type'    => 'int',
				),
				'value' => JSON_BIGINT_AS_STRING,
			),
			'JSON_OBJECT_AS_ARRAY'            => array(
				'docs'  => array(
					'summary' => 'Decodes `JSON` objects as PHP `array`',
					'type'    => 'int',
				),
				'value' => JSON_OBJECT_AS_ARRAY,
			),
			'JSON_HEX_TAG'                    => array(
				'docs'  => array(
					'summary' => 'All `<` and `>` are converted to `\u003C` and `\u003E`',
					'type'    => 'int',
				),
				'value' => JSON_HEX_TAG,
			),
			'JSON_HEX_AMP'                    => array(
				'docs'  => array(
					'summary' => 'All `&` are converted to `\u0026`',
					'type'    => 'int',
				),
				'value' => JSON_HEX_AMP,
			),
			'JSON_HEX_APOS'                   => array(
				'docs'  => array(
					'summary' => 'All `\'` are converted to `\u0027`',
					'type'    => 'int',
				),
				'value' => JSON_HEX_APOS,
			),
			'JSON_HEX_QUOT'                   => array(
				'docs'  => array(
					'summary' => 'All `"` are converted to `\u0022`',
					'type'    => 'int',
				),
				'value' => JSON_HEX_QUOT,
			),
			'JSON_FORCE_OBJECT'               => array(
				'docs'  => array(
					'summary' => 'Outputs an `object` rather than an `array` when a non-associative `array` is used',
					'type'    => 'int',
				),
				'value' => JSON_FORCE_OBJECT,
			),
			'JSON_NUMERIC_CHECK'              => array(
				'docs'  => array(
					'summary' => 'Encodes numeric `string` values as numbers',
					'type'    => 'int',
				),
				'value' => JSON_NUMERIC_CHECK,
			),
			'JSON_PRETTY_PRINT'               => array(
				'docs'  => array(
					'summary' => 'Use whitespace in returned data to format it',
					'type'    => 'int',
				),
				'value' => JSON_PRETTY_PRINT,
			),
			'JSON_UNESCAPED_SLASHES'          => array(
				'docs'  => array(
					'summary' => 'Don\'t escape `/`',
					'type'    => 'int',
				),
				'value' => JSON_UNESCAPED_SLASHES,
			),
			'JSON_UNESCAPED_UNICODE'          => array(
				'docs'  => array(
					'summary' => 'Encode multibyte `Unicode` characters literally',
					'type'    => 'int',
				),
				'value' => JSON_UNESCAPED_UNICODE,
			),
			'JSON_PARTIAL_OUTPUT_ON_ERROR'    => array(
				'docs'  => array(
					'summary' => 'Substitute some unencodable values instead of failing',
					'type'    => 'int',
				),
				'value' => JSON_PARTIAL_OUTPUT_ON_ERROR,
			),
			'JSON_PRESERVE_ZERO_FRACTION'     => array(
				'docs'  => array(
					'summary' => 'Ensures that `float` values are always encoded as a `float` value',
					'type'    => 'int',
				),
				'value' => JSON_PRESERVE_ZERO_FRACTION,
			),
			'JSON_UNESCAPED_LINE_TERMINATORS' => array(
				'docs'  => array(
					'summary' => 'The line terminators are kept unescaped',
					'type'    => 'int',
				),
				'value' => JSON_UNESCAPED_LINE_TERMINATORS,
			),
			'JSON_INVALID_UTF8_IGNORE'        => array(
				'docs'  => array(
					'summary' => 'Ignore invalid `UTF-8` characters',
					'type'    => 'int',
				),
				'value' => JSON_INVALID_UTF8_IGNORE,
			),
			'JSON_INVALID_UTF8_SUBSTITUTE'    => array(
				'docs'  => array(
					'summary' => 'Convert invalid `UTF-8` characters to `\0xfffd`',
					'type'    => 'int',
				),
				'value' => JSON_INVALID_UTF8_SUBSTITUTE,
			),
			'PHP_INT_MAX'                     => array(
				'docs'  => array(
					'summary' => 'The largest `int` supported in this build of PHP. Usually `int(2147483647)` in 32-bit systems and `int(9223372036854775807)` in 64-bit systems.',
					'type'    => 'int',
				),
				'value' => PHP_INT_MAX,
			),
			'PREG_OFFSET_CAPTURE'             => array(
				'docs'  => array(
					'summary' => 'If this flag is set, for every occurring match the appendant byte `offset` will also be returned.',
					'type'    => 'int',
				),
				'value' => PREG_OFFSET_CAPTURE,
			),
			'PREG_UNMATCHED_AS_NULL'          => array(
				'docs'  => array(
					'summary' => 'This flag tells `preg_match()` and `preg_match_all()` to include unmatched subpatterns in `$matches` as `null` values.',
					'type'    => 'int',
				),
				'value' => PREG_UNMATCHED_AS_NULL,
			),
			'PREG_PATTERN_ORDER'              => array(
				'docs'  => array(
					'summary' => 'Orders results so that `$matches[0]` is an `array` of full pattern matches, `$matches[1]` is an `array` of strings matched by the first parenthesized subpattern, and so on.',
					'type'    => 'int',
				),
				'value' => PREG_PATTERN_ORDER,
			),
			'PREG_SET_ORDER'                  => array(
				'docs'  => array(
					'summary' => 'Orders results so that `$matches[0]` is an `array` of first set of matches, `$matches[1]` is an `array` of second set of matches, and so on.',
					'type'    => 'int',
				),
				'value' => PREG_SET_ORDER,
			),
			'COUNT_NORMAL'                    => array(
				'docs'  => array(
					'summary' => 'Default mode for `count()`. Counts the elements in an `array` or something in an `object`.',
					'type'    => 'int',
				),
				'value' => COUNT_NORMAL,
			),
			'COUNT_RECURSIVE'                 => array(
				'docs'  => array(
					'summary' => 'If the optional `mode` parameter is set to `COUNT_RECURSIVE` (or `1`), `count()` will recursively count the `array`.',
					'type'    => 'int',
				),
				'value' => COUNT_RECURSIVE,
			),
			'ARRAY_FILTER_USE_BOTH'           => array(
				'docs'  => array(
					'summary' => 'Pass both `value` and `key` as arguments to `callback` instead of the value',
					'type'    => 'int',
				),
				'value' => ARRAY_FILTER_USE_BOTH,
			),
			'ARRAY_FILTER_USE_KEY'            => array(
				'docs'  => array(
					'summary' => 'Pass `key` as the only argument to `callback` instead of the value',
					'type'    => 'int',
				),
				'value' => ARRAY_FILTER_USE_KEY,
			),
			'SORT_STRING'                     => array(
				'docs'  => array(
					'summary' => 'Compare items as `string` values',
					'type'    => 'int',
				),
				'value' => SORT_STRING,
			),
			'SORT_NUMERIC'                    => array(
				'docs'  => array(
					'summary' => 'Compare items numerically',
					'type'    => 'int',
				),
				'value' => SORT_NUMERIC,
			),
			'SORT_REGULAR'                    => array(
				'docs'  => array(
					'summary' => 'Compare items normally (don\'t change types)',
					'type'    => 'int',
				),
				'value' => SORT_REGULAR,
			),
			'SORT_LOCALE_STRING'              => array(
				'docs'  => array(
					'summary' => 'Compare items as `string` values, based on the current locale',
					'type'    => 'int',
				),
				'value' => SORT_LOCALE_STRING,
			),
			'SORT_NATURAL'                    => array(
				'docs'  => array(
					'summary' => 'Compare items as `string` values using "natural order" algorithm',
					'type'    => 'int',
				),
				'value' => SORT_NATURAL,
			),
			'SORT_FLAG_CASE'                  => array(
				'docs'  => array(
					'summary' => 'Can be combined (bitwise OR) with `SORT_STRING` or `SORT_NATURAL` to sort strings case-insensitively',
					'type'    => 'int',
				),
				'value' => SORT_FLAG_CASE,
			),
			'PHP_ROUND_HALF_UP'               => array(
				'docs'  => array(
					'summary' => 'Rounding half away from `0`.',
					'type'    => 'int',
				),
				'value' => PHP_ROUND_HALF_UP,
			),
			'PHP_ROUND_HALF_DOWN'             => array(
				'docs'  => array(
					'summary' => 'Rounding half toward `0`.',
					'type'    => 'int',
				),
				'value' => PHP_ROUND_HALF_DOWN,
			),
			'PHP_ROUND_HALF_EVEN'             => array(
				'docs'  => array(
					'summary' => 'Rounding half to even numbers.',
					'type'    => 'int',
				),
				'value' => PHP_ROUND_HALF_EVEN,
			),
			'PHP_ROUND_HALF_ODD'              => array(
				'docs'  => array(
					'summary' => 'Rounding half to odd numbers.',
					'type'    => 'int',
				),
				'value' => PHP_ROUND_HALF_ODD,
			),
		);
	}
}
