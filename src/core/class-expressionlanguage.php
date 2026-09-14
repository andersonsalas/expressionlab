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

use Symfony\Component\ExpressionLanguage\ExpressionLanguage as SymfonyExpressionLanguage;
use Symfony\Component\ExpressionLanguage\Lexer;
use Symfony\Component\ExpressionLanguage\ParsedExpression;
use Symfony\Component\ExpressionLanguage\Expression;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * Custom ExpressionLanguage class extending Symfony's Expression Language.
 *
 * Injects the custom LanguageParser instead of the stock Parser,
 * and ensures all special form keywords are recognized as valid names
 * during parsing.
 *
 * @since 1.0.0
 * @internal
 *
 * @package ExpressionLab
 */
class ExpressionLanguage extends SymfonyExpressionLanguage {
	/**
	 * Special form keywords that must be injected into the names array
	 * so the parser does not reject them as unknown variables.
	 *
	 * @since 1.0.0
	 *
	 * @var string[]
	 */
	private const SPECIAL_FORM_KEYWORDS = array(
		'prog',
		'set',
		'unset',
		'isset',
		'var',
		'args',
		'fn',
		'show',
		'map',
		'filter',
		'reduce',
	);

	/**
	 * Cached custom parser instance.
	 *
	 * @since 1.0.0
	 *
	 * @var LanguageParser|null
	 */
	private ?LanguageParser $custom_parser = null;

	/**
	 * Cached lexer instance.
	 *
	 * @since 1.0.0
	 *
	 * @var Lexer|null
	 */
	private ?Lexer $custom_lexer = null;

	/**
	 * Parses an expression using the custom LanguageParser.
	 *
	 * Injects all special form keywords into the names array so the parser
	 * recognizes them, then uses LanguageParser to produce custom AST nodes.
	 *
	 * @since 1.0.0
	 *
	 * @param Expression|string $expression The expression to parse.
	 * @param array             $names      The valid variable names.
	 * @param int               $flags      Optional. Parser flags. Default 0.
	 *
	 * @return ParsedExpression The parsed expression.
	 */
	public function parse( Expression|string $expression, array $names, int $flags = 0 ): ParsedExpression {
		if ( $expression instanceof ParsedExpression ) {
			return $expression;
		}

		// Inject special form keywords as valid names.
		foreach ( self::SPECIAL_FORM_KEYWORDS as $keyword ) {
			if ( ! in_array( $keyword, $names, true ) ) {
				$names[] = $keyword;
			}
		}

		$lexer  = $this->get_custom_lexer();
		$parser = $this->get_custom_parser();

		$nodes = $parser->parse( $lexer->tokenize( (string) $expression ), $names, $flags );

		return new ParsedExpression( (string) $expression, $nodes );
	}

	/**
	 * Retrieves or creates the custom lexer instance.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return Lexer The lexer instance.
	 */
	private function get_custom_lexer(): Lexer {
		if ( null === $this->custom_lexer ) {
			$this->custom_lexer = new Lexer();
		}
		return $this->custom_lexer;
	}

	/**
	 * Retrieves or creates the custom parser instance.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return LanguageParser The language parser instance.
	 */
	private function get_custom_parser(): LanguageParser {
		if ( null === $this->custom_parser ) {
			$this->custom_parser = new LanguageParser( $this->functions );
		}
		return $this->custom_parser;
	}
}
