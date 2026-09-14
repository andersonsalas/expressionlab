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
 * Reduce AST node.
 *
 * Evaluates the `reduce[iterable, fn, initial?]` special form, folding the
 * iterable into an accumulated value.
 *
 * @since 1.0.0
 * @internal
 * @package ExpressionLab
 */
class ReduceNode extends Node {
	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param Node      $iterable_node The iterable expression node.
	 * @param Node      $callback      The reducer function node.
	 * @param Node|null $initial       Optional initial accumulator value node.
	 */
	public function __construct( Node $iterable_node, Node $callback, ?Node $initial = null ) {
		$children = array(
			'iterable' => $iterable_node,
			'callback' => $callback,
		);
		if ( null !== $initial ) {
			$children['initial'] = $initial;
		}
		parent::__construct( $children );
	}

	/**
	 * Evaluates the reduce operation.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param array $functions The registered functions.
	 * @param array $values    The evaluation context values.
	 * @return mixed The accumulated result.
	 * @throws \RuntimeException         If the first argument is not iterable.
	 * @throws \InvalidArgumentException If the second argument is not a Closure.
	 */
	public function evaluate( array $functions, array $values ): mixed {
		$engine = LanguageEngine::get();
		$engine->tick();

		$iterable = $this->nodes['iterable']->evaluate( $functions, $values );
		$callback = $this->nodes['callback']->evaluate( $functions, $values );

		if ( ! is_iterable( $iterable ) ) {
			throw new \RuntimeException( 'The first argument to reduce[] must be iterable.' );
		}

		if ( ! $callback instanceof \Closure ) {
			throw new \InvalidArgumentException( 'The second argument to reduce[] must be a Closure (e.g. fn[...]).' );
		}

		$accumulator = isset( $this->nodes['initial'] )
			? $this->nodes['initial']->evaluate( $functions, $values )
			: null;

		foreach ( $iterable as $item ) {
			$engine->tick();
			$accumulator = $callback( $accumulator, $item );
		}

		return $accumulator;
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
		if ( isset( $this->nodes['initial'] ) ) {
			return array( 'reduce[', $this->nodes['iterable'], ', ', $this->nodes['callback'], ', ', $this->nodes['initial'], ']' );
		}
		return array( 'reduce[', $this->nodes['iterable'], ', ', $this->nodes['callback'], ']' );
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
		throw new \RuntimeException( 'ReduceNode compilation is not supported.' );
	}
}
