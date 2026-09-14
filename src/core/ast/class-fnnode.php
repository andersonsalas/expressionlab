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

namespace ExpressionLab\Core\Ast;

use ExpressionLab\Core\LanguageEngine;
use Symfony\Component\ExpressionLanguage\Node\Node;
use Symfony\Component\ExpressionLanguage\Node\ArrayNode;
use Symfony\Component\ExpressionLanguage\Node\ConstantNode;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * Function AST node.
 *
 * Evaluates the `fn[['param1', ...], body]` special form, creating a native
 * closure encapsulating the AST of the body expression for lazy evaluation.
 *
 * @since 1.0.0
 * @internal
 * @package ExpressionLab
 */
class FnNode extends Node {
	/**
	 * Extracted parameter names for quick access during invocation.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	private array $param_names = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param Node $params The parameter list node (ArrayNode of identifiers/strings).
	 * @param Node $body   The body expression node (evaluated lazily).
	 * @throws \InvalidArgumentException If the first argument is not an ArrayNode.
	 */
	public function __construct( Node $params, Node $body ) {
		if ( ! $params instanceof ArrayNode ) {
			throw new \InvalidArgumentException( 'The first argument to fn[] must be an array of parameter names (e.g. fn[[\'x\'], body]).' );
		}

		parent::__construct(
			array(
				'params' => $params,
				'body'   => $body,
			)
		);

		// Extract parameter names from the ArrayNode at construction time.
		$this->extract_param_names( $params );
	}

	/**
	 * Extracts parameter names from a params node.
	 *
	 * The params node is an ArrayNode where each element is a ConstantNode
	 * containing the parameter name as a string.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param Node $params The parameter list node.
	 * @return void
	 */
	private function extract_param_names( Node $params ): void {
		if ( $params instanceof ArrayNode ) {
			// ArrayNode stores pairs: [key0, val0, key1, val1, ...].
			$child_nodes = $params->nodes;
			$count       = count( $child_nodes );
			for ( $i = 0; $i < $count; $i += 2 ) {
				if ( isset( $child_nodes[ $i + 1 ] ) && $child_nodes[ $i + 1 ] instanceof ConstantNode ) {
					$this->param_names[] = (string) $child_nodes[ $i + 1 ]->attributes['value'];
				}
			}
		}
	}

	/**
	 * Evaluates the fn definition.
	 *
	 * Returns a PHP Closure that, when called with positional arguments,
	 * maps them to the parameter names and evaluates the body expression
	 * in a scoped context.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param array $functions The registered functions.
	 * @param array $values    The evaluation context values.
	 * @return \Closure The created closure.
	 */
	public function evaluate( array $functions, array $values ): mixed {
		$body_node   = $this->nodes['body'];
		$param_names = $this->param_names;
		$engine      = LanguageEngine::get();

		return function ( ...$args ) use ( $functions, $values, $body_node, $param_names, $engine ) {
			$engine->enter_call();
			try {
				$engine->tick();

				$scoped_values = $values;
				$parent_args   = isset( $values['args'] ) && is_array( $values['args'] ) ? $values['args'] : array();
				$current_args  = array();

				foreach ( $param_names as $idx => $param_name ) {
					$current_args[ $param_name ] = $args[ $idx ] ?? null;
				}

				// Inner parameters shadow outer parameters of the same name,
				// but sibling outer parameters remain accessible.
				$scoped_values['args'] = array_merge( $parent_args, $current_args );

				return $body_node->evaluate( $functions, $scoped_values );
			} finally {
				$engine->leave_call();
			}
		};
	}

	/**
	 * Converts the node to its array representation for dumping.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return array Array representation of the node.
	 */
	public function toArray(): array {
		return array( 'fn[', $this->nodes['params'], ', ', $this->nodes['body'], ']' );
	}

	/**
	 * Compiles the node into PHP code.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param \Symfony\Component\ExpressionLanguage\Compiler $compiler The compiler.
	 * @throws \RuntimeException If compilation is attempted.
	 */
	public function compile( \Symfony\Component\ExpressionLanguage\Compiler $compiler ): void {
		throw new \RuntimeException( 'FnNode compilation is not supported.' );
	}
}
