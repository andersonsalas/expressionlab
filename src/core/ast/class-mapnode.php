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
 * Map AST node.
 *
 * Evaluates the `map[iterable, fn]` special form, applying the callback
 * closure to each element of the iterable.
 *
 * @since 1.0.0
 * @internal
 * @package ExpressionLab
 */
class MapNode extends Node {
	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param Node $iterable_node The iterable expression node.
	 * @param Node $callback      The callback function node.
	 */
	public function __construct( Node $iterable_node, Node $callback ) {
		parent::__construct(
			array(
				'iterable' => $iterable_node,
				'callback' => $callback,
			)
		);
	}

	/**
	 * Evaluates the map operation.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param array $functions The registered functions.
	 * @param array $values    The evaluation context values.
	 * @return array Transformed array.
	 * @throws \RuntimeException         If the first argument is not iterable.
	 * @throws \InvalidArgumentException If the second argument is not a Closure.
	 */
	public function evaluate( array $functions, array $values ): mixed {
		$engine = LanguageEngine::get();
		$engine->tick();

		$iterable = $this->nodes['iterable']->evaluate( $functions, $values );
		$callback = $this->nodes['callback']->evaluate( $functions, $values );

		if ( ! is_iterable( $iterable ) ) {
			throw new \RuntimeException( 'The first argument to map[] must be iterable.' );
		}

		if ( ! $callback instanceof \Closure ) {
			throw new \InvalidArgumentException( 'The second argument to map[] must be a Closure (e.g. fn[...]).' );
		}

		$result = array();

		foreach ( $iterable as $item ) {
			$engine->tick();
			$result[] = $callback( $item );
		}

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
		return array( 'map[', $this->nodes['iterable'], ', ', $this->nodes['callback'], ']' );
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
		throw new \RuntimeException( 'MapNode compilation is not supported.' );
	}
}
