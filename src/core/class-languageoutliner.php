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

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * Language outliner class.
 *
 * Extracts structured outline and documentation data from the expression language library.
 * Returns pure data arrays for client-side consumption.
 *
 * @since 1.0.0
 * @internal
 *
 * @package ExpressionLab
 */
class LanguageOutliner {
	/**
	 * Resolves the return type of a method or property to a known library class.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param \ReflectionMethod|\ReflectionProperty $reflection    The reflection to inspect.
	 * @param string|null                           $context_class Optional. Full class name for self/static/$this resolution. Default null.
	 * @return array|null An array containing the short name and full class name, or null if not resolvable.
	 */
	public function resolve_return_type( $reflection, ?string $context_class = null ): ?array {
		$doc = $reflection->getDocComment();
		if ( ! $doc ) {
			return null;
		}

		$return_type = null;

		if ( $reflection instanceof \ReflectionMethod ) {
			if ( preg_match( '/@return\s+(\$?[A-Za-z0-9_\\\\]+)/', $doc, $matches ) ) {
				$return_type = $matches[1];
			}
		} elseif ( $reflection instanceof \ReflectionProperty ) {
			if ( preg_match( '/@var\s+(\$?[A-Za-z0-9_\\\\]+)/', $doc, $matches ) ) {
				$return_type = $matches[1];
			}
		}

		if ( ! $return_type ) {
			return null;
		}

		// Remove nullable union type.
		$return_type = explode( '|', $return_type )[0];

		// Handle self-referencing return types.
		if ( in_array( $return_type, array( 'self', 'static', '$this' ), true ) ) {
			if ( $context_class && class_exists( $context_class ) ) {
				$ref = new \ReflectionClass( $context_class );
				return array( $ref->getShortName(), $context_class );
			}
			return null;
		}

		// Attempt to locate class.
		$namespaces = array(
			'\\ExpressionLab\\Core\\Models\\',
			'\\ExpressionLab\\Core\\Services\\',
			'',
		);

		foreach ( $namespaces as $ns ) {
			if ( class_exists( $ns . $return_type ) ) {
				$ref = new \ReflectionClass( $ns . $return_type );
				return array( $ref->getShortName(), $ns . $return_type );
			}
			if ( class_exists( $ns . ucfirst( $return_type ) ) ) {
				$ref = new \ReflectionClass( $ns . ucfirst( $return_type ) );
				return array( $ref->getShortName(), $ns . ucfirst( $return_type ) );
			}
		}

		return null;
	}

	/**
	 * Builds a type registry for lazy chain resolution.
	 *
	 * Instead of materializing an exponential tree of chained methods (O(N^D)),
	 * this builds a flat type graph (O(T×M)) where each type maps its members
	 * to their return types. The client walks the graph lazily at autocomplete time.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param array $object_classes Map of shortName => fullClassName for library object classes.
	 * @return array The type registry: typeName => [ memberName => [ doc, insertText, type, returnType ] ].
	 */
	public function build_type_registry( array $object_classes ): array {
		if ( defined( 'EXPRESSION_LAB_TEST_ENV' ) && EXPRESSION_LAB_TEST_ENV ) {
			return array();
		}

		$registry    = array();
		$known_types = $object_classes; // shortName => fullClassName.
		$queue       = array_keys( $known_types );
		$processed   = array();

		while ( ! empty( $queue ) ) {
			$short_name = array_shift( $queue );

			if ( isset( $processed[ $short_name ] ) ) {
				continue;
			}
			$processed[ $short_name ] = true;

			$full_class = $known_types[ $short_name ];
			$ref        = new \ReflectionClass( $full_class );
			$members    = array();

			// Properties (including Model-inherited ones for chain completions).
			foreach ( $ref->getProperties( \ReflectionProperty::IS_PUBLIC ) as $prop ) {
				if ( $prop->isStatic() ) {
					continue;
				}

				$name        = $prop->getName();
				$insert_text = '';
				$prop_doc    = $this->build_property_docs( $name, $prop, $insert_text );
				$return_info = $this->resolve_return_type( $prop, $full_class );
				$return_type = $return_info ? $return_info[0] : null;

				if ( $return_type && ! isset( $known_types[ $return_type ] ) && $return_info ) {
					$known_types[ $return_type ] = $return_info[1];
					$queue[]                     = $return_type;
				}

				$members[ $name ] = array(
					'doc'        => $prop_doc,
					'insertText' => $insert_text,
					'type'       => 'Property',
					'returnType' => $return_type,
				);
			}

			// Methods (including Model-inherited ones for chain completions).
			foreach ( $ref->getMethods( \ReflectionMethod::IS_PUBLIC ) as $method ) {
				if ( $method->isStatic() || $method->isConstructor() || $method->isDestructor() ) {
					continue;
				}

				if ( 0 === strpos( $method->getName(), '__' ) ) {
					continue;
				}

				// Exclude get_value() on Model classes.
				if ( is_a( $full_class, 'ExpressionLab\\Core\\Models\\Model', true ) && 'get_value' === $method->getName() ) {
					continue;
				}

				$raw_comment = $method->getDocComment();
				if ( ! $raw_comment ) {
					continue;
				}
				if ( false !== strpos( $raw_comment, '@internal' ) ) {
					continue;
				}

				$name        = $method->getName();
				$insert_text = '';

				try {
					$method_doc = $this->build_object_docs( $name, $method, $insert_text );
				} catch ( \Exception $e ) {
					$method_doc = array(
						'summary'    => 'Method ' . $name,
						'parameters' => array(),
						'return'     => null,
					);
				}

				$return_info = $this->resolve_return_type( $method, $full_class );
				$return_type = $return_info ? $return_info[0] : null;

				if ( $return_type && ! isset( $known_types[ $return_type ] ) && $return_info ) {
					$known_types[ $return_type ] = $return_info[1];
					$queue[]                     = $return_type;
				}

				$members[ $name ] = array(
					'doc'        => $method_doc,
					'insertText' => $insert_text,
					'type'       => 'Method',
					'returnType' => $return_type,
				);
			}

			$registry[ $short_name ] = $members;
		}

		return $registry;
	}

	/**
	 * Parses a function docblock and returns structured documentation.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string     $function_name The function name.
	 * @param array|null $function_data The function data (if any).
	 * @param string     $insert_text   The generated insert text for CodeMirror, passed by reference.
	 * @return array Structured documentation array.
	 */
	public function build_function_docs( string $function_name, array|null $function_data, string &$insert_text ): array {
		$reflection = null;

		if ( function_exists( $function_name ) ) {
			$reflection = new \ReflectionFunction( $function_name );
		}

		$summary     = '';
		$description = '';
		$tags        = array();
		$return_type = 'mixed';

		if ( null !== $function_data ) {
			// Function data provided. This is likely a native PHP function.
			$summary     = $function_data['docs']['summary'] ?? $function_name . ' function';
			$description = $function_data['docs']['description'] ?? '';

			if ( ! empty( $function_data['docs']['parameters'] ) ) {
				foreach ( $function_data['docs']['parameters'] as $param_name => $param_data ) {
					$tag_data = array(
						'name'        => 'param',
						'type'        => $param_data['type'],
						'required'    => $param_data['required'] ?? false,
						'variable'    => $param_name,
						'description' => $param_data['description'],
					);

					if ( array_key_exists( 'default', $param_data ) ) {
						$tag_data['default'] = $param_data['default'];
					}

					$tags[] = $tag_data;
				}
			}

			if ( ! empty( $function_data['docs']['see'] ) ) {
				foreach ( $function_data['docs']['see'] as $see ) {
					$tags[] = array(
						'name' => 'see',
						'ref'  => $see,
					);
				}
			}

			if ( ! empty( $function_data['docs']['return'] ) ) {
				$tags[]      = array(
					'name'        => 'return',
					'type'        => $function_data['docs']['return']['type'],
					'description' => $function_data['docs']['return']['description'],
				);
				$return_type = $function_data['docs']['return']['type'];
			}
		} else {
			// No function data. Try to parse docblock.
			$raw_comment = $reflection->getDocComment();
			$instance    = null;

			// Get default values from Reflection.
			$default_values = array();
			foreach ( $reflection->getParameters() as $param ) {
				if ( $param->isDefaultValueAvailable() ) {
					$default_values[ $param->getName() ] = $param->getDefaultValue();
				}
			}

			if ( $raw_comment ) {
				$instance = \phpDocumentor\Reflection\DocBlockFactory::createInstance()->create( $raw_comment );
			}

			if ( null !== $instance ) {
				$summary     = trim( $instance->getSummary() );
				$description = trim( $instance->getDescription()->render() );

				foreach ( $instance->getTags() as $tag ) {
					switch ( $tag->getName() ) {
						case 'param':
							/**
							 * "Param" tag.
							 *
							 * @var \phpDocumentor\Reflection\DocBlock\Tags\Param $tag
							 */
							$tag_data = array(
								'name'        => 'param',
								'type'        => (string) $tag->getType(),
								'variable'    => $tag->getVariableName(),
								'description' => (string) $tag->getDescription(),
								'required'    => ! in_array( $tag->getVariableName(), array_keys( $default_values ), true ),
							);

							$var_name = $tag->getVariableName();
							if ( array_key_exists( $var_name, $default_values ) ) {
								$tag_data['default'] = $default_values[ $var_name ];
							}

							$tags[] = $tag_data;
							break;
						case 'see':
							/**
							 * "See" tag.
							 *
							 * @var \phpDocumentor\Reflection\DocBlock\Tags\See $tag
							 */
							$tags[] = array(
								'name' => 'see',
								'ref'  => trim( (string) $tag->getReference() ),
							);
							break;
						case 'return':
							/**
							 * "Return" tag.
							 *
							 * @var \phpDocumentor\Reflection\DocBlock\Tags\Return_ $tag
							 */
							$tags[]      = array(
								'name'        => 'return',
								'type'        => (string) $tag->getType(),
								'description' => (string) $tag->getDescription(),
							);
							$return_type = (string) $tag->getType();
							break;
						case 'since':
							// Ignore "since" tags.
							break;
						default:
							/**
							 * Generic tag.
							 *
							 * @var \phpDocumentor\Reflection\DocBlock\Tags\BaseTag $tag
							 */
							$tags[] = array(
								'name'        => $tag->getName(),
								'description' => (string) $tag->getDescription(),
							);
							break;
					}
				}
			}
		}

		// Build structured result.
		$result = array(
			'summary'     => $summary,
			'description' => $description,
			'parameters'  => array(),
			'return'      => null,
		);

		foreach ( $tags as $tag ) {
			if ( 'param' === $tag['name'] ) {
				$param = array(
					'name'        => $tag['variable'],
					'type'        => $tag['type'],
					'description' => $tag['description'],
					'required'    => $tag['required'],
				);
				if ( array_key_exists( 'default', $tag ) ) {
					$param['default'] = $this->format_value( $tag['default'] );
				}
				$result['parameters'][] = $param;
			} elseif ( 'return' === $tag['name'] ) {
				$result['return'] = array(
					'type'        => $tag['type'],
					'description' => $tag['description'],
				);
			} elseif ( 'see' === $tag['name'] ) {
				$result['see'][] = $tag['ref'];
			}
		}

		// CodeMirror insert text.
		$insert_text   = $function_name . '(';
		$param_texts   = array();
		$param_index   = 1;
		$skip_optional = true;

		foreach ( $tags as $tag ) {
			if ( 'param' === $tag['name'] ) {
				$placeholder = $tag['variable'];
				if ( false === $tag['required'] && $skip_optional ) {
					continue;
				}
				if ( false === $tag['required'] && array_key_exists( 'default', $tag ) ) {
					$placeholder = $this->format_value( $tag['default'] );
				}
				$param_texts[] = '${' . $param_index . ':' . $placeholder . '}';
				++$param_index;
			}
		}

		$insert_text .= implode( ', ', $param_texts );
		$insert_text .= ')';

		// Build the signature string for the client.
		$params_list = array();
		foreach ( $tags as $tag ) {
			if ( 'param' === $tag['name'] ) {
				$param_str = '';
				if ( ! empty( $tag['type'] ) ) {
					if ( false === $tag['required'] ) {
						$param_str .= '[';
					}
					$param_str .= $tag['type'] . ':';
				}

				$param_str .= $tag['variable'];

				if ( false === $tag['required'] ) {
					$param_value = $tag['default'] ?? null;
					if ( null !== $param_value ) {
						// If the string is surrounded by ``, do not format it.
						if ( '`' === substr( $param_value, 0, 1 ) && '`' === substr( $param_value, -1 ) ) {
							$param_value = substr( $param_value, 1, -1 );
						} else {
							$param_value = $this->format_value( $param_value );
						}
					} else {
						$param_value = 'null';
					}
					$param_str .= ' = ' . $param_value . ']';
				}
				$params_list[] = $param_str;
			}
		}

		$result['signature'] = $function_name . '(' . implode( ', ', $params_list ) . ') => ' . $return_type;

		return $result;
	}


	/**
	 * Parses a method docblock and returns structured documentation.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string            $identifier  The function/method identifier.
	 * @param \ReflectionMethod $method      The method reflection.
	 * @param string            $insert_text The generated insert text for CodeMirror, passed by reference.
	 * @return array Structured documentation array.
	 */
	public function build_object_docs( $identifier, \ReflectionMethod $method, &$insert_text ): array {
		$raw_comment = $method->getDocComment();
		$instance    = \phpDocumentor\Reflection\DocBlockFactory::createInstance()->create( $raw_comment );
		$summary     = trim( $instance->getSummary() );

		// Get default values from Reflection.
		$default_values        = array();
		$default_values_pretty = array();

		foreach ( $method->getParameters() as $param ) {
			if ( $param->isDefaultValueAvailable() ) {
				$default_values[ $param->getName() ] = $param->getDefaultValue();

				if ( $param->isDefaultValueConstant() ) {
					$constant_name = $param->getDefaultValueConstantName();

					if ( false !== strpos( $constant_name, '::' ) ) {
						list( $class, $const ) = explode( '::', $constant_name );
						$class_parts           = explode( '\\', $class );
						$short_class           = end( $class_parts );

						if ( 'self' === $short_class ) {
							$short_class = $method->getDeclaringClass()->getShortName();
						}

						$default_values_pretty[ $param->getName() ] = $short_class . '.' . $const;
					} else {
						$default_values_pretty[ $param->getName() ] = $constant_name;
					}
				}
			}
		}

		$description = trim( $instance->getDescription()->render() );

		$result = array(
			'summary'     => $summary,
			'description' => $description,
			'parameters'  => array(),
			'return'      => null,
		);

		// Build signature parts for the client.
		$signature_params = array();

		foreach ( $instance->getTags() as $tag ) {
			switch ( $tag->getName() ) {
				case 'param':
					/**
					 * "Param" tag.
					 *
					 * @var \phpDocumentor\Reflection\DocBlock\Tags\Param $tag
					 */
					$param = array(
						'name'        => $tag->getVariableName(),
						'type'        => (string) $tag->getType(),
						'description' => (string) $tag->getDescription(),
						'required'    => ! in_array( $tag->getVariableName(), array_keys( $default_values ), true ),
					);
					if ( array_key_exists( $tag->getVariableName(), $default_values ) ) {
						$param['default'] = isset( $default_values_pretty[ $tag->getVariableName() ] )
							? $default_values_pretty[ $tag->getVariableName() ]
							: $this->format_value( $default_values[ $tag->getVariableName() ] );
					}
					$result['parameters'][] = $param;

					// Build signature param string.
					$is_required = $param['required'];
					$param_str   = '';

					if ( ! $is_required ) {
						$param_str .= '[';
					}

					$param_str .= (string) $tag->getType() . ':';
					$param_str .= $tag->getVariableName();

					if ( ! $is_required ) {
						$val        = isset( $default_values_pretty[ $tag->getVariableName() ] )
							? $default_values_pretty[ $tag->getVariableName() ]
							: ( $this->format_value( $default_values[ $tag->getVariableName() ] ) ?? 'null' );
						$param_str .= ' = ' . $val . ']';
					}

					$signature_params[] = $param_str;
					break;
				case 'return':
					/**
					 * "Return" tag.
					 *
					 * @var \phpDocumentor\Reflection\DocBlock\Tags\Return_ $tag
					 */
					$result['return'] = array(
						'type'        => ltrim( (string) $tag->getType(), '\\' ),
						'description' => (string) $tag->getDescription(),
					);
					break;
				case 'see':
					/**
					 * "See" tag.
					 *
					 * @var \phpDocumentor\Reflection\DocBlock\Tags\See $tag
					 */
					$result['see'][] = trim( (string) $tag->getReference() );
					break;
			}
		}

		// Build signature string.
		$return_type_str     = $result['return'] ? $result['return']['type'] : 'void';
		$result['signature'] = $identifier . '(' . implode( ', ', $signature_params ) . ') => ' . $return_type_str;

		// CodeMirror insert text.
		$insert_text   = $identifier . '(';
		$param_texts   = array();
		$param_index   = 1;
		$skip_optional = true;

		foreach ( $instance->getTags() as $tag ) {
			if ( 'param' === $tag->getName() ) {
				/**
				 * "Param" tag.
				 *
				 * @var \phpDocumentor\Reflection\DocBlock\Tags\Param $tag
				 */
				$placeholder = $tag->getVariableName();

				if ( in_array( $placeholder, array_keys( $default_values ), true ) && $skip_optional ) {
					continue;
				}

				if ( array_key_exists( $placeholder, $default_values ) ) {
					$placeholder = isset( $default_values_pretty[ $placeholder ] )
						? $default_values_pretty[ $placeholder ]
						: $this->format_value( $default_values[ $placeholder ] );
				}

				$param_texts[] = '${' . $param_index . ':' . $placeholder . '}';
				++$param_index;
			}
		}

		$insert_text .= implode( ', ', $param_texts );
		$insert_text .= ')';

		return $result;
	}

	/**
	 * Builds structured documentation for a library constant.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string $constant_name The name of the constant.
	 * @param array  $constant_data The data for the constant.
	 * @param string $insert_text   The insert text, passed by reference.
	 * @return array Structured documentation array.
	 */
	public function build_constant_docs( string $constant_name, array $constant_data, string &$insert_text ): array {
		$summary     = $constant_data['docs']['summary'] ?? $constant_name . ' constant';
		$description = $constant_data['docs']['description'] ?? '';
		$type        = $constant_data['docs']['type'] ?? 'mixed';
		$value       = array_key_exists( 'value', $constant_data ) ? $constant_data['value'] : null;

		$insert_text = $constant_name;

		$result = array(
			'summary'     => $summary,
			'description' => $description,
			'type'        => $type,
			'value'       => $value,
			'signature'   => $constant_name,
		);

		if ( ! empty( $constant_data['docs']['see'] ) ) {
			$result['see'] = (array) $constant_data['docs']['see'];
		}

		return $result;
	}

	/**
	 * Builds structured documentation for an object constant via reflection.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string                   $identifier  The constant signature.
	 * @param \ReflectionClassConstant $constant    The reflection constant object.
	 * @param string                   $insert_text The insert text, passed by reference.
	 * @return array Structured documentation array.
	 */
	public function build_object_constant_docs( string $identifier, \ReflectionClassConstant $constant, string &$insert_text ): array {
		$raw_comment = $constant->getDocComment();
		$summary     = '';
		$description = '';
		$value       = $constant->getValue();
		$type        = gettype( $value );
		$see_tags    = array();

		if ( 'integer' === $type ) {
			$type = 'int';
		} elseif ( 'boolean' === $type ) {
			$type = 'bool';
		} elseif ( 'double' === $type ) {
			$type = 'float';
		} elseif ( 'NULL' === $type ) {
			$type = 'null';
		}

		if ( $raw_comment ) {
			$instance    = \phpDocumentor\Reflection\DocBlockFactory::createInstance()->create( $raw_comment );
			$summary     = trim( $instance->getSummary() );
			$description = trim( $instance->getDescription()->render() );

			foreach ( $instance->getTags() as $tag ) {
				if ( 'var' === $tag->getName() ) {
					/**
					 * Try to find var tag.
					 *
					 * @var \phpDocumentor\Reflection\DocBlock\Tags\Var_ $tag
					 */
					$type = (string) $tag->getType();
				} elseif ( 'see' === $tag->getName() ) {
					/**
					 * "See" tag.
					 *
					 * @var \phpDocumentor\Reflection\DocBlock\Tags\See $tag
					 */
					$see_tags[] = trim( (string) $tag->getReference() );
				}
			}
		}

		$insert_text = str_replace( '::', '.', $identifier );

		// Format value for display.
		if ( is_string( $value ) ) {
			$value_str = "'" . $value . "'";
		} elseif ( is_bool( $value ) ) {
			$value_str = $value ? 'true' : 'false';
		} elseif ( is_null( $value ) ) {
			$value_str = 'null';
		} elseif ( is_array( $value ) ) {
			$value_str = wp_json_encode( $value );
		} else {
			$value_str = (string) $value;
		}

		$result = array(
			'summary'     => $summary,
			'description' => $description,
			'type'        => $type,
			'value'       => $value,
			'valueStr'    => $value_str,
			'signature'   => str_replace( '::', '.', $identifier ),
		);

		if ( ! empty( $see_tags ) ) {
			$result['see'] = $see_tags;
		}

		return $result;
	}

	/**
	 * Builds structured documentation for an object property.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string              $identifier  The property signature.
	 * @param \ReflectionProperty $property    The reflection property object.
	 * @param string              $insert_text The insert text, passed by reference.
	 * @return array Structured documentation array.
	 */
	public function build_property_docs( string $identifier, \ReflectionProperty $property, string &$insert_text ): array {
		$raw_comment = $property->getDocComment();
		$summary     = '';
		$description = '';
		$type        = 'mixed';
		$see_tags    = array();

		// Try getting type from PHP 7.4+ type hinting.
		if ( method_exists( $property, 'getType' ) && $property->getType() ) {
			$type_obj = $property->getType();
			if ( method_exists( $type_obj, 'getName' ) ) {
				$type = $type_obj->getName();
			} else {
				$type = (string) $type_obj;
			}
		}

		// Try to get default value.
		$default_value_str = 'null';
		if ( $property->isDefault() && $property->hasDefaultValue() ) {
			$default_val       = $property->getDefaultValue();
			$default_value_str = $this->format_value( $default_val );
		}

		if ( $raw_comment ) {
			$instance    = \phpDocumentor\Reflection\DocBlockFactory::createInstance()->create( $raw_comment );
			$summary     = trim( $instance->getSummary() );
			$description = trim( $instance->getDescription()->render() );

			foreach ( $instance->getTags() as $tag ) {
				switch ( $tag->getName() ) {
					case 'var':
						if ( method_exists( $tag, 'getType' ) ) {
							/**
							 * Try to find var tag.
							 *
							 * @var \phpDocumentor\Reflection\DocBlock\Tags\Var_ $tag
							 */
							$type = (string) $tag->getType();
						}
						break;
					case 'see':
						/**
						 * "See" tag.
						 *
						 * @var \phpDocumentor\Reflection\DocBlock\Tags\See $tag
						 */
						$see_tags[] = trim( (string) $tag->getReference() );
						break;
				}
			}
		}

		$insert_text = $identifier;

		$result = array(
			'summary'     => $summary,
			'description' => $description,
			'type'        => $type,
			'signature'   => $identifier,
		);

		if ( ! empty( $see_tags ) ) {
			$result['see'] = $see_tags;
		}

		if ( $property->isDefault() && $property->hasDefaultValue() ) {
			$result['default'] = $default_value_str;
		}

		return $result;
	}

	/**
	 * Formats a value for CodeMirror snippets.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param mixed $value The value to format.
	 * @return string The formatted string.
	 */
	public function format_value( $value ) {
		if ( is_null( $value ) ) {
			return 'null';
		}
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}
		if ( is_string( $value ) ) {
			return "'" . $value . "'";
		}
		if ( is_array( $value ) ) {
			return '[]';
		}
		return (string) $value;
	}
}
