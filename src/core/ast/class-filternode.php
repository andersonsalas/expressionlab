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
 * Filter AST node.
 *
 * Evaluates the `filter[iterable, fn, mode?]` special form, retaining elements
 * for which the predicate closure returns truthy.
 *
 * @since 1.0.0
 * @internal
 * @package ExpressionLab
 */
class FilterNode extends Node {
	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param Node      $iterable_node The iterable expression node.
	 * @param Node      $callback      The predicate function node.
	 * @param Node|null $mode          Optional mode expression node.
	 */
	public function __construct( Node $iterable_node, Node $callback, ?Node $mode = null ) {
		$children = array(
			'iterable' => $iterable_node,
			'callback' => $callback,
		);
		if ( null !== $mode ) {
			$children['mode'] = $mode;
		}
		parent::__construct( $children );
	}

	/**
	 * Evaluates the filter operation.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param array $functions The registered functions.
	 * @param array $values    The evaluation context values.
	 * @return array Filtered array.
	 * @throws \RuntimeException         If the first argument is not iterable.
	 * @throws \InvalidArgumentException If the second argument is not a Closure.
	 */
	public function evaluate( array $functions, array $values ): mixed {
		$engine = LanguageEngine::get();
		$engine->tick();

		$iterable = $this->nodes['iterable']->evaluate( $functions, $values );
		$callback = $this->nodes['callback']->evaluate( $functions, $values );

		if ( ! is_iterable( $iterable ) ) {
			throw new \RuntimeException( 'The first argument to filter[] must be iterable.' );
		}

		if ( ! $callback instanceof \Closure ) {
			throw new \InvalidArgumentException( 'The second argument to filter[] must be a Closure (e.g. fn[...]).' );
		}

		$mode_val = 0;
		if ( isset( $this->nodes['mode'] ) ) {
			$mode_val = (int) $this->nodes['mode']->evaluate( $functions, $values );
		}

		$is_list = is_array( $iterable ) && array_is_list( $iterable );
		$result  = array();

		foreach ( $iterable as $key => $item ) {
			$engine->tick();

			$keep = false;
			if ( 2 === $mode_val ) { // ARRAY_FILTER_USE_KEY.
				$keep = (bool) $callback( $key );
			} elseif ( 1 === $mode_val ) { // ARRAY_FILTER_USE_BOTH.
				$keep = (bool) $callback( $item, $key );
			} else {
				$keep = (bool) $callback( $item );
			}

			if ( $keep ) {
				if ( $is_list && 0 === $mode_val ) {
					$result[] = $item;
				} else {
					$result[ $key ] = $item;
				}
			}
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
		if ( isset( $this->nodes['mode'] ) ) {
			return array( 'filter[', $this->nodes['iterable'], ', ', $this->nodes['callback'], ', ', $this->nodes['mode'], ']' );
		}
		return array( 'filter[', $this->nodes['iterable'], ', ', $this->nodes['callback'], ']' );
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
		throw new \RuntimeException( 'FilterNode compilation is not supported.' );
	}
}
