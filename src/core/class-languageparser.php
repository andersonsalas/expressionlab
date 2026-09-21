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

use ExpressionLab\Core\Ast\ProgNode;
use ExpressionLab\Core\Ast\SetNode;
use ExpressionLab\Core\Ast\UnsetNode;
use ExpressionLab\Core\Ast\IssetNode;
use ExpressionLab\Core\Ast\VarNode;
use ExpressionLab\Core\Ast\ArgsNode;
use ExpressionLab\Core\Ast\FnNode;
use ExpressionLab\Core\Ast\ShowNode;
use ExpressionLab\Core\Ast\MapNode;
use ExpressionLab\Core\Ast\FilterNode;
use ExpressionLab\Core\Ast\ReduceNode;
use Symfony\Component\ExpressionLanguage\Node\NameNode;
use Symfony\Component\ExpressionLanguage\Node\Node;
use Symfony\Component\ExpressionLanguage\Node\GetAttrNode;
use Symfony\Component\ExpressionLanguage\Node\ConstantNode;
use Symfony\Component\ExpressionLanguage\Node\ArgumentsNode;
use Symfony\Component\ExpressionLanguage\Parser;
use Symfony\Component\ExpressionLanguage\Token;
use Symfony\Component\ExpressionLanguage\TokenStream;
use Symfony\Component\ExpressionLanguage\SyntaxError;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * Custom parser extending Symfony Expression Language Parser.
 *
 * Intercepts special form keywords followed by bracket syntax (`keyword[...]`)
 * and produces custom AST nodes instead of the standard GetAttrNode array access.
 *
 * This is a formal Type-2 (context-free) grammar extension - no regex preprocessing.
 *
 * @since 1.0.0
 * @internal
 *
 * @package ExpressionLab
 */
class LanguageParser extends Parser {
	/**
	 * Map of special form keywords to their handler methods.
	 *
	 * @since 1.0.0
	 *
	 * @var string[]
	 */
	private const KEYWORD_MAP = array(
		'prog'   => 'parse_prog',
		'set'    => 'parse_set',
		'unset'  => 'parse_unset',
		'isset'  => 'parse_isset',
		'var'    => 'parse_var',
		'args'   => 'parse_args',
		'fn'     => 'parse_fn',
		'show'   => 'parse_show',
		'map'    => 'parse_map',
		'filter' => 'parse_filter',
		'reduce' => 'parse_reduce',
	);

	/**
	 * Accesses the private TokenStream from the parent Parser.
	 *
	 * Symfony's Parser declares `private TokenStream $stream;`.
	 * Accessed via Closure binding.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return TokenStream The current token stream.
	 */
	private function get_stream(): TokenStream {
		$getter = \Closure::bind(
			fn() => $this->stream, // phpcs:ignore WordPress.WhiteSpace.DisallowInlineTabs
			$this,
			Parser::class
		);
		return $getter();
	}

	/**
	 * Overrides postfix expression parsing to intercept special forms and invocations.
	 *
	 * Handles four postfix cases in a unified loop:
	 * 1. Special form keywords (`prog[...]`, `fn[...]`, etc.) - intercepted
	 *    when a NameNode matches a registered keyword followed by `[`.
	 * 2. Member access (`.`, `?.`) - resolves properties and method calls
	 *    while restricting access to internal and magic methods.
	 * 3. Array indexing (`[`) - resolves array element access.
	 * 4. Direct expression invocation (`(`) - wraps the callee node in a
	 *    `CallNode` with parsed arguments. Enables `var['fn'](args)`,
	 *    `(fn[['x'], body])(7)`, and curried chains `f(1)(2)`.
	 *
	 * @since 1.0.0
	 *
	 * @param Node $node The primary expression node.
	 * @return GetAttrNode|Node The resulting AST node.
	 * @throws \Symfony\Component\ExpressionLanguage\SyntaxError If member name is invalid or refers to a restricted internal method.
	 */
	public function parsePostfixExpression( Node $node ): GetAttrNode|Node {
		// 1. Intercept special form keywords (prog, set, fn, etc.) followed by '['.
		if (
			$node instanceof NameNode
			&& isset( $node->attributes['name'] )
			&& isset( self::KEYWORD_MAP[ $node->attributes['name'] ] )
		) {
			$stream  = $this->get_stream();
			$keyword = $node->attributes['name'];

			if ( $stream->current->test( Token::PUNCTUATION_TYPE, '[' ) ) {
				$handler = self::KEYWORD_MAP[ $keyword ];
				$result  = $this->$handler( $stream );

				// Recursively handles chained access, such as nested property access or invocation.
				return $this->parsePostfixExpression( $result );
			}
		}

		// 2. Unified postfix loop for '.', '?.', '[', and '(' tokens.
		$stream = $this->get_stream();
		$token  = $stream->current;

		while ( Token::PUNCTUATION_TYPE === $token->type ) {
			if ( '.' === $token->value || '?.' === $token->value ) {
				$is_null_safe = '?.' === $token->value;
				$stream->next();
				$token = $stream->current;
				$stream->next();

				if (
					Token::NAME_TYPE !== $token->type
					&& ( Token::OPERATOR_TYPE !== $token->type || ! preg_match( '/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/A', $token->value ) )
				) {
					throw new SyntaxError(
						esc_html( 'Expected name.' ),
						absint( $token->cursor ),
						esc_html( $stream->getExpression() )
					);
				}

				if ( str_starts_with( strtolower( $token->value ), '__' ) ) {
					throw new SyntaxError(
						esc_html( sprintf( 'Access to magic method or internal property "%s" is not allowed.', esc_html( $token->value ) ) ),
						absint( $token->cursor ),
						esc_html( $stream->getExpression() )
					);
				}

				$arg = new ConstantNode( $token->value, true, $is_null_safe );

				$arguments = new ArgumentsNode();
				if ( $stream->current->test( Token::PUNCTUATION_TYPE, '(' ) ) {
					$type = GetAttrNode::METHOD_CALL;
					foreach ( $this->parseArguments()->nodes as $n ) {
						$arguments->addElement( $n );
					}
				} else {
					$type = GetAttrNode::PROPERTY_CALL;
				}

				$node  = new GetAttrNode( $node, $arg, $arguments, $type );
				$token = $stream->current;
			} elseif ( '[' === $token->value ) {
				$stream->next();
				$arg = $this->parseExpression();
				$stream->expect( Token::PUNCTUATION_TYPE, ']' );

				$node  = new GetAttrNode( $node, $arg, new ArgumentsNode(), GetAttrNode::ARRAY_CALL );
				$token = $stream->current;
			} elseif ( '(' === $token->value ) {
				// Direct expression invocation: expr(arg1, arg2, ...).
				$arguments = $this->parseArguments();
				$node      = new Ast\CallNode( $node, $arguments );
				$token     = $stream->current;
			} else {
				break;
			}
		}

		return $node;
	}

	/**
	 * Parses bracket arguments: consumes `[`, parses comma-separated expressions, expects `]`.
	 *
	 * Supports trailing commas.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param TokenStream $stream The token stream.
	 * @return Node[] The parsed argument nodes.
	 */
	private function parse_bracket_arguments( TokenStream $stream ): array {
		$stream->expect( Token::PUNCTUATION_TYPE, '[', 'A special form must begin with an opening bracket' );

		$args = array();

		while ( ! $stream->current->test( Token::PUNCTUATION_TYPE, ']' ) ) {
			if ( ! empty( $args ) ) {
				$stream->expect( Token::PUNCTUATION_TYPE, ',', 'Arguments must be separated by a comma' );

				// Trailing comma support.
				if ( $stream->current->test( Token::PUNCTUATION_TYPE, ']' ) ) {
					break;
				}
			}

			$args[] = $this->parseExpression();
		}

		$stream->expect( Token::PUNCTUATION_TYPE, ']', 'A special form must be closed by a bracket' );

		return $args;
	}

	/**
	 * Parses `prog[expr1, expr2, ..., exprN]`.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param TokenStream $stream The token stream.
	 * @return ProgNode The prog AST node.
	 */
	private function parse_prog( TokenStream $stream ): ProgNode {
		$args = $this->parse_bracket_arguments( $stream );
		return new ProgNode( $args );
	}

	/**
	 * Parses `set['name', value]`.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param TokenStream $stream The token stream.
	 * @return SetNode The set AST node.
	 *
	 * @throws \RuntimeException If required arguments are missing.
	 */
	private function parse_set( TokenStream $stream ): SetNode {
		$args = $this->parse_bracket_arguments( $stream );

		if ( count( $args ) < 2 ) {
			throw new \RuntimeException( 'set[] requires exactly 2 arguments: set[name, value]' );
		}

		return new SetNode( $args[0], $args[1] );
	}

	/**
	 * Parses `unset['name']`.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param TokenStream $stream The token stream.
	 * @return UnsetNode The unset AST node.
	 *
	 * @throws \RuntimeException If required arguments are missing.
	 */
	private function parse_unset( TokenStream $stream ): UnsetNode {
		$args = $this->parse_bracket_arguments( $stream );

		if ( count( $args ) < 1 ) {
			throw new \RuntimeException( 'unset[] requires exactly 1 argument: unset[name]' );
		}

		return new UnsetNode( $args[0] );
	}

	/**
	 * Parses `isset['name']`.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param TokenStream $stream The token stream.
	 * @return IssetNode The isset AST node.
	 *
	 * @throws \RuntimeException If required arguments are missing.
	 */
	private function parse_isset( TokenStream $stream ): IssetNode {
		$args = $this->parse_bracket_arguments( $stream );

		if ( count( $args ) < 1 ) {
			throw new \RuntimeException( 'isset[] requires exactly 1 argument: isset[name]' );
		}

		return new IssetNode( $args[0] );
	}

	/**
	 * Parses `var['name']`.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param TokenStream $stream The token stream.
	 * @return VarNode The var AST node.
	 *
	 * @throws \RuntimeException If required arguments are missing.
	 */
	private function parse_var( TokenStream $stream ): VarNode {
		$args = $this->parse_bracket_arguments( $stream );

		if ( count( $args ) < 1 ) {
			throw new \RuntimeException( 'var[] requires exactly 1 argument: var[name]' );
		}

		return new VarNode( $args[0] );
	}

	/**
	 * Parses `args['name']`.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param TokenStream $stream The token stream.
	 * @return ArgsNode The args AST node.
	 *
	 * @throws \RuntimeException If required arguments are missing.
	 */
	private function parse_args( TokenStream $stream ): ArgsNode {
		$args = $this->parse_bracket_arguments( $stream );

		if ( count( $args ) < 1 ) {
			throw new \RuntimeException( 'args[] requires exactly 1 argument: args[name]' );
		}

		return new ArgsNode( $args[0] );
	}

	/**
	 * Parses `fn[['param1', ...], body_expression]`.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param TokenStream $stream The token stream.
	 * @return FnNode The function AST node.
	 *
	 * @throws \RuntimeException If required arguments are missing.
	 */
	private function parse_fn( TokenStream $stream ): FnNode {
		$args = $this->parse_bracket_arguments( $stream );

		if ( count( $args ) < 2 ) {
			throw new \RuntimeException( 'fn[] requires exactly 2 arguments: fn[params_array, body_expression]' );
		}

		return new FnNode( $args[0], $args[1] );
	}

	/**
	 * Parses `show[expression]`.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param TokenStream $stream The token stream.
	 * @return ShowNode The show AST node.
	 *
	 * @throws \RuntimeException If required arguments are missing.
	 */
	private function parse_show( TokenStream $stream ): ShowNode {
		$args = $this->parse_bracket_arguments( $stream );

		if ( count( $args ) < 1 ) {
			throw new \RuntimeException( 'show[] requires exactly 1 argument: show[expression]' );
		}

		return new ShowNode( $args[0] );
	}

	/**
	 * Parses `map[iterable, fn]`.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param TokenStream $stream The token stream.
	 * @return MapNode The map AST node.
	 *
	 * @throws \RuntimeException If required arguments are missing.
	 */
	private function parse_map( TokenStream $stream ): MapNode {
		$args = $this->parse_bracket_arguments( $stream );

		if ( count( $args ) < 2 ) {
			throw new \RuntimeException( 'map[] requires exactly 2 arguments: map[iterable, fn]' );
		}

		return new MapNode( $args[0], $args[1] );
	}

	/**
	 * Parses `filter[iterable, fn, mode?]`.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param TokenStream $stream The token stream.
	 * @return FilterNode The filter AST node.
	 *
	 * @throws \RuntimeException If required arguments are missing.
	 */
	private function parse_filter( TokenStream $stream ): FilterNode {
		$args = $this->parse_bracket_arguments( $stream );

		if ( count( $args ) < 2 ) {
			throw new \RuntimeException( 'filter[] requires at least 2 arguments: filter[iterable, fn]' );
		}

		$mode = isset( $args[2] ) ? $args[2] : null;

		return new FilterNode( $args[0], $args[1], $mode );
	}

	/**
	 * Parses `reduce[iterable, fn, initial?]`.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param TokenStream $stream The token stream.
	 * @return ReduceNode The reduce AST node.
	 *
	 * @throws \RuntimeException If required arguments are missing.
	 */
	private function parse_reduce( TokenStream $stream ): ReduceNode {
		$args = $this->parse_bracket_arguments( $stream );

		if ( count( $args ) < 2 ) {
			throw new \RuntimeException( 'reduce[] requires at least 2 arguments: reduce[iterable, fn]' );
		}

		$initial = isset( $args[2] ) ? $args[2] : null;

		return new ReduceNode( $args[0], $args[1], $initial );
	}
}
