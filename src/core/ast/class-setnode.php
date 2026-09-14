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
 * Set AST node.
 *
 * Evaluates the `set['name', value]` special form, binding the value into
 * the engine session storage.
 *
 * @since 1.0.0
 * @internal
 * @package ExpressionLab
 */
class SetNode extends Node {
	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param Node $key   The key expression node.
	 * @param Node $value The value expression node.
	 */
	public function __construct( Node $key, Node $value ) {
		parent::__construct(
			array(
				'key'   => $key,
				'value' => $value,
			)
		);
	}

	/**
	 * Evaluates the set operation.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param array $functions The registered functions.
	 * @param array $values    The evaluation context values.
	 * @return mixed The assigned value.
	 */
	public function evaluate( array $functions, array $values ): mixed {
		$engine = LanguageEngine::get();
		$engine->tick();

		$key   = $this->nodes['key']->evaluate( $functions, $values );
		$value = $this->nodes['value']->evaluate( $functions, $values );

		$engine->set_variable( (string) $key, $value );

		return $value;
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
		return array( 'set[', $this->nodes['key'], ', ', $this->nodes['value'], ']' );
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
		throw new \RuntimeException( 'SetNode compilation is not supported.' );
	}
}
