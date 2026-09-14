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

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * Call AST node.
 *
 * Evaluates direct expression invocations (`expr(arg1, arg2, ...)`),
 * validating that the callee is a closure and invoking it with arguments.
 *
 * @since 1.0.0
 * @internal
 * @package ExpressionLab
 */
class CallNode extends Node {
	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param Node $callee    The expression returning the closure.
	 * @param Node $arguments The evaluated arguments list node.
	 */
	public function __construct( Node $callee, Node $arguments ) {
		parent::__construct(
			array(
				'callee'    => $callee,
				'arguments' => $arguments,
			)
		);
	}

	/**
	 * Evaluates the call expression.
	 *
	 * Evaluates the callee expression, validates it is a closure, evaluates each
	 * argument node, and invokes the closure with the evaluated arguments.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param array $functions The registered functions.
	 * @param array $values    The evaluation context values.
	 * @return mixed Result of the closure invocation.
	 * @throws \RuntimeException If the callee is not a Closure.
	 */
	public function evaluate( array $functions, array $values ): mixed {
		$engine = LanguageEngine::get();
		$engine->tick();

		$callee = $this->nodes['callee']->evaluate( $functions, $values );

		if ( ! $callee instanceof \Closure ) {
			$type = is_object( $callee ) ? get_class( $callee ) : gettype( $callee );
			throw new \RuntimeException( esc_html( "Attempted to invoke a non-callable value of type $type." ) );
		}

		$evaluated_args = array();
		$arg_nodes      = $this->nodes['arguments']->nodes;

		foreach ( $arg_nodes as $arg_node ) {
			$evaluated_args[] = $arg_node->evaluate( $functions, $values );
		}

		return $callee( ...$evaluated_args );
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
		return array( $this->nodes['callee'], '(', $this->nodes['arguments'], ')' );
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
		throw new \RuntimeException( 'CallNode compilation is not supported.' );
	}
}
