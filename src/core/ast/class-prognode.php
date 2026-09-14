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
 * Program block AST node.
 *
 * Evaluates the `prog[expr1, expr2, ..., exprN]` special form sequentially,
 * returning the result of the final expression.
 *
 * @since 1.0.0
 * @internal
 * @package ExpressionLab
 */
class ProgNode extends Node {

	/**
	 * Evaluates the program block.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param array $functions The registered functions.
	 * @param array $values    The evaluation context values.
	 * @return mixed Result of the final expression, or `null` if empty.
	 */
	public function evaluate( array $functions, array $values ): mixed {
		$engine = LanguageEngine::get();
		$engine->increase_prog_depth();
		$engine->begin_visualization_group();

		$result = null;

		foreach ( $this->nodes as $i => $node ) {
			if ( 0 === $i % 50 ) {
				$engine->tick();
			}
			$result = $node->evaluate( $functions, $values );
		}

		$engine->decrease_prog_depth();

		return $result;
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
		$array = array( 'prog[' );
		$first = true;
		foreach ( $this->nodes as $node ) {
			if ( ! $first ) {
				$array[] = ', ';
			}
			$first   = false;
			$array[] = $node;
		}
		$array[] = ']';
		return $array;
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
		throw new \RuntimeException( 'ProgNode compilation is not supported.' );
	}
}
