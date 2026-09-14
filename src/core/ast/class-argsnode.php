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
 * Args AST node.
 *
 * Evaluates the `args['name']` special form to access an argument
 * passed into the active `fn` closure's lexical scope.
 *
 * @since 1.0.0
 * @internal
 * @package ExpressionLab
 */
class ArgsNode extends Node {
	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param Node $key The key expression node.
	 */
	public function __construct( Node $key ) {
		parent::__construct( array( 'key' => $key ) );
	}

	/**
	 * Evaluates the args access expression.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param array $functions The registered functions.
	 * @param array $values    The evaluation context values.
	 * @return mixed The argument value, or `null` if not found.
	 */
	public function evaluate( array $functions, array $values ): mixed {
		$engine = LanguageEngine::get();
		$engine->tick();

		$key = (string) $this->nodes['key']->evaluate( $functions, $values );

		if ( ! isset( $values['args'] ) || ! is_array( $values['args'] ) ) {
			return null;
		}

		return $values['args'][ $key ] ?? null;
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
		return array( 'args[', $this->nodes['key'], ']' );
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
		throw new \RuntimeException( 'ArgsNode compilation is not supported.' );
	}
}
