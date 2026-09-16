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

use ExpressionLab\Core\Interfaces\ExtensionInterface;
use ExpressionLab\Core\Interfaces\ServiceInterface;
use ExpressionLab\Core\Helper;
use ExpressionLab\Core\Models\Model;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage as SymfonyExpressionLanguage;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * Language engine class.
 *
 * Core execution engine that evaluates expressions, manages expression variables,
 * coordinates extensions and services, enforces computational boundaries, and
 * generates documentation outlines for the expression language.
 *
 * @since 1.0.0
 *
 * @package ExpressionLab
 */
class LanguageEngine {
	use Singleton;

	/**
	 * Maximum number of operations before triggering a safety brake.
	 *
	 * Operations are an estimation of computational effort rather than atomic instructions.
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	const MAX_OPERATIONS = 50000;

	/**
	 * Maximum recursion and call stack depth before triggering a safety brake.
	 *
	 * Prevents stack overflows and memory exhaustion from infinite recursive calls.
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	const MAX_RECURSION_DEPTH = 100;

	/**
	 * List of reserved system identifiers that cannot be overridden by hooks.
	 *
	 * @since 1.0.0
	 *
	 * @var string[]
	 */
	const RESERVED_IDENTIFIERS = array(
		'show',
		'set',
		'set_once',
		'isset',
		'unset',
		'var',
		'clear',
		'constant',
		'tick',
		'context',
		'prog',
		'args',
		'fn',
		'map',
		'filter',
		'reduce',
	);

	/**
	 * List of core library and service object names reserved by the engine.
	 *
	 * @since 1.0.0
	 *
	 * @var string[]
	 */
	const CORE_RESERVED_OBJECTS = array(
		'database',
		'options',
		'users',
		'posts',
		'media',
		'sites',
		'files',
		'http',
		'console',
		'networkoptions',
		'networksites',
		'postmeta',
		'usermeta',
		'attachment',
		'post',
		'user',
		'site',
		'siteoptions',
		'expressionlab',
		'iplookup',
	);

	/**
	 * Special form keywords recognized by the DSL parser.
	 *
	 * These identifiers trigger bracket-syntax special forms
	 * in the custom LanguageParser.
	 *
	 * @since 1.0.0
	 *
	 * @var string[]
	 */
	const SPECIAL_FORM_KEYWORDS = array(
		'prog',
		'set',
		'unset',
		'isset',
		'var',
		'args',
		'fn',
		'show',
		'map',
		'filter',
		'reduce',
	);

	/**
	 * Maximum allowed length of an expression block (in bytes).
	 *
	 * Limits the size of input payload before processing to prevent memory issues.
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	const MAX_EXPRESSION_LENGTH = 1048576; // 1 MB

	/**
	 * Extension classes.
	 *
	 * @since 1.0.0
	 *
	 * @var array|null
	 */
	private $extensions;

	/**
	 * Base core extension classes cached statically.
	 *
	 * @since 1.0.0
	 *
	 * @var array|null
	 */
	private static $base_extensions = null;

	/**
	 * Service classes.
	 *
	 * @since 1.0.0
	 *
	 * @var array|null
	 */
	private $service_classes;

	/**
	 * Base core service classes cached statically.
	 *
	 * @since 1.0.0
	 *
	 * @var array|null
	 */
	private static $base_service_classes = null;

	/**
	 * Output messages collected during expression execution.
	 *
	 * Messages can be of type 'warning', 'error', 'info', or 'success', and are added on-the-fly
	 * during expression evaluation (e.g. from the Console library).
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	private $messages = array();

	/**
	 * System diagnostic and initialization messages.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, array{type: string, text: string}>
	 */
	private $system_messages = array();

	/**
	 * Output visualization data.
	 *
	 * Array of visualization items (tables, graphs, etc.) produced during evaluation.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	private $visualizations = array();

	/**
	 * Silenced visualizations collected in non-interactive mode.
	 *
	 * Visualizations that require explicit exposure via `show()` to be emitted.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	private $silenced_visualizations = array();

	/**
	 * Registered service instances.
	 *
	 * Service instances indexed by class name for dependency injection.
	 *
	 * @since 1.0.0
	 *
	 * @var array|null
	 */
	private $services;

	/**
	 * User-defined variables storage manager.
	 *
	 * @since 1.0.0
	 *
	 * @var VariableStore
	 */
	private VariableStore $variable_store;

	/**
	 * Current recursion and call stack depth.
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	private int $call_depth = 0;

	/**
	 * Counter for active progs to control interactive mode.
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	private $active_progs = 0;

	/**
	 * Execution start time in microsecond timestamp.
	 *
	 * @since 1.0.0
	 *
	 * @var float
	 */
	private $execution_start;

	/**
	 * Counter for executed operations to trigger safety brakes.
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	private $operations;

	/**
	 * The ID of the user executing the expression, if available.
	 *
	 * @since 1.0.0
	 *
	 * @var int|null
	 */
	private $user_id;

	/**
	 * The ID of the site (in multisite) where the expression is executed, if available.
	 *
	 * @since 1.0.0
	 *
	 * @var int|null
	 */
	private $site_id;

	/**
	 * The language outliner instance.
	 *
	 * @since 1.0.0
	 *
	 * @var LanguageOutliner
	 */
	private $outliner;

	/**
	 * Initializes the language engine and resets runtime state.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function __construct() {
		$this->outliner       = new LanguageOutliner();
		$this->variable_store = new VariableStore();
		$this->reset();
	}

	/**
	 * Checks if extensibility hooks are enabled.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return bool True if hooks are enabled, false otherwise.
	 */
	public function are_hooks_enabled(): bool {
		return defined( 'EXPRESSION_LAB_HOOKS_ENABLED' ) && (bool) constant( 'EXPRESSION_LAB_HOOKS_ENABLED' );
	}

	/**
	 * Resets the runtime engine state.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return $this The engine instance.
	 */
	public function reset() {
		$this->variable_store->clear();
		$this->messages                = array();
		$this->system_messages         = array();
		$this->visualizations          = array();
		$this->silenced_visualizations = array();
		$this->active_progs            = 0;
		$this->execution_start         = microtime( true );
		$this->operations              = 0;
		$this->call_depth              = 0;
		$this->user_id                 = null;
		$this->site_id                 = null;
		if ( $this->are_hooks_enabled() ) {
			$this->extensions      = null;
			$this->service_classes = null;
		}
		return $this;
	}

	/**
	 * Checks if an identifier is reserved by the engine.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string $name The identifier name.
	 * @return bool True if reserved, false otherwise.
	 */
	public function is_reserved_identifier( string $name ): bool {
		$normalized = strtolower( trim( $name ) );

		if ( in_array( $normalized, self::RESERVED_IDENTIFIERS, true ) ) {
			return true;
		}

		if ( in_array( $normalized, self::CORE_RESERVED_OBJECTS, true ) ) {
			return true;
		}

		if ( is_array( self::$base_service_classes ) ) {
			foreach ( self::$base_service_classes as $class_name ) {
				if ( class_exists( $class_name ) ) {
					$reflection = new \ReflectionClass( $class_name );
					if ( strtolower( $reflection->getShortName() ) === $normalized ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Checks execution time and operation count against configured limits.
	 *
	 * Called periodically during expression execution to enforce computational limits.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param float|null $remaining_time Optional. Variable passed by reference to receive remaining time. Default null.
	 * @param int        $operation_cost Optional. The estimated computational cost of the operation. Default 1.
	 *
	 * @throws \RuntimeException If maximum operations or execution time limit is exceeded.
	 */
	public function tick( &$remaining_time = null, int $operation_cost = 1 ) {
		$this->operations += $operation_cost;
		if ( $this->operations > self::MAX_OPERATIONS ) {
			throw new \RuntimeException( esc_html( 'Memory safety triggered. Maximum number of operations exceeded.' ) );
		}

		if ( empty( $this->execution_start ) ) {
			$this->execution_start = microtime( true );
		}

		$used_time      = microtime( true ) - $this->execution_start;
		$remaining_time = EXPRESSION_LAB_MAX_EXECUTION_LIMIT - $used_time;
		if ( $used_time > EXPRESSION_LAB_MAX_EXECUTION_LIMIT ) {
			$seconds = 1 === EXPRESSION_LAB_MAX_EXECUTION_LIMIT ? 'second' : 'seconds';
			throw new \RuntimeException( esc_html( 'Execution time of ' . EXPRESSION_LAB_MAX_EXECUTION_LIMIT . ' ' . $seconds . ' limit exceeded.' ) );
		}
	}

	/**
	 * Enters a function or closure call frame and increments call depth.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @throws \RuntimeException If maximum recursion depth is exceeded.
	 */
	public function enter_call(): void {
		if ( $this->call_depth >= self::MAX_RECURSION_DEPTH ) {
			throw new \RuntimeException( esc_html( 'Maximum recursion depth of ' . self::MAX_RECURSION_DEPTH . ' exceeded.' ) );
		}
		++$this->call_depth;
	}

	/**
	 * Leaves a function or closure call frame and decrements call depth.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function leave_call(): void {
		$this->call_depth = max( 0, $this->call_depth - 1 );
	}

	/**
	 * Retrieves the current call depth.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return int The current call depth.
	 */
	public function get_call_depth(): int {
		return $this->call_depth;
	}

	/**
	 * Retrieves all user-defined expression variables.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return array The array of stored variables.
	 */
	public function get_variables(): array {
		return $this->variable_store->all();
	}

	/**
	 * Retrieves the VariableStore instance.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return VariableStore The variable store instance.
	 */
	public function get_variable_store(): VariableStore {
		return $this->variable_store;
	}

	/**
	 * Retrieves a expression variable or nested path value.
	 *
	 * Supports both simple variable names ('foo') and dot-separated paths ('foo.bar.baz').
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string $name          The variable name or path.
	 * @param mixed  $default_value Optional. Default value if not found. Default null.
	 * @return mixed The variable value or default value.
	 *
	 * @throws \InvalidArgumentException If the path syntax is invalid.
	 */
	public function get_variable( string $name, $default_value = null ) {
		return $this->variable_store->get( $name, $default_value );
	}

	/**
	 * Sets a expression variable or nested path value.
	 *
	 * Supports both simple variable names ('foo') and dot-separated paths ('foo.bar.baz').
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string $name  The variable name or path.
	 * @param mixed  $value The variable value. Must be a scalar, array, stdClass object, or null.
	 *
	 * @throws \InvalidArgumentException If the value is not of an allowed type or path is invalid.
	 */
	public function set_variable( string $name, $value ): void {
		$this->variable_store->set( $name, $value );
	}

	/**
	 * Checks if a expression variable or nested path exists.
	 *
	 * Supports both simple variable names ('foo') and dot-separated paths ('foo.bar.baz').
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string $name The variable name or path.
	 * @return bool True if the variable exists, false otherwise.
	 */
	public function has_variable( string $name ): bool {
		return $this->variable_store->has( $name );
	}

	/**
	 * Deletes a expression variable or nested path leaf.
	 *
	 * Supports both simple variable names ('foo') and dot-separated paths ('foo.bar.baz').
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string $name The variable name or path.
	 *
	 * @throws \InvalidArgumentException If the path syntax is invalid.
	 */
	public function delete_variable( string $name ): void {
		$this->variable_store->delete( $name );
	}

	/**
	 * Clears all user-defined expression variables.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function clear_variables(): void {
		$this->variable_store->clear();
	}

	/**
	 * Adds a message to the execution output.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string $type Message type: 'warning', 'error', 'info', 'success', or 'update'.
	 * @param string $text Message text.
	 */
	public function add_message( $type, $text ) {
		if ( ! in_array( $type, array( 'warning', 'error', 'info', 'success', 'update' ), true ) ) {
			$type = 'info';
		}
		$this->messages[] = array(
			'type' => $type,
			'text' => $text,
		);
	}

	/**
	 * Retrieves all collected execution messages.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return array List of message associative arrays.
	 */
	public function get_messages() {
		return $this->messages;
	}

	/**
	 * Clears all collected execution messages.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function clear_messages() {
		$this->messages = array();
	}

	/**
	 * Adds a system diagnostic message.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string $type Message type: 'warning', 'error', 'info', 'success', or 'update'.
	 * @param string $text Message text.
	 */
	public function add_system_message( string $type, string $text ): void {
		if ( ! in_array( $type, array( 'warning', 'error', 'info', 'success', 'update' ), true ) ) {
			$type = 'info';
		}
		$this->system_messages[] = array(
			'type' => $type,
			'text' => $text,
		);
	}

	/**
	 * Collects system diagnostic messages and inspects for conflicts with core identifiers.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	private function collect_system_messages(): void {
		$update = Updater::get()->get_available_update();
		if ( ! empty( $update['version'] ) ) {
			$this->add_system_message(
				'update',
				sprintf(
					/* translators: 1: new version number, 2: current version number */
					__( 'Update available: Version %1$s is available (current: %2$s).', 'expression-lab' ),
					$update['version'],
					EXPRESSION_LAB_VERSION
				)
			);
		}

		if ( defined( 'EXPRESSION_LAB_SANDBOX_ENABLED' ) && false === EXPRESSION_LAB_SANDBOX_ENABLED ) {
			$this->add_system_message(
				'warning',
				__( 'Security sandbox is disabled. Any actions performed without sandbox protection are AT YOUR OWN RISK.', 'expression-lab' )
			);
		}

		if ( Helper::is_debug_mode() ) {
			$this->add_system_message(
				'warning',
				__( 'Debug mode is enabled. This poses a security risk in production environments.', 'expression-lab' )
			);
		}

		if ( $this->are_hooks_enabled() ) {
			$this->add_system_message(
				'warning',
				__( 'Hooks enabled: Custom objects and functions from active plugins are loaded in the console.', 'expression-lab' )
			);

			// Check custom constants.
			try {
				$custom_constants = apply_filters( 'expressionlab_register_constants', array() );
				if ( is_array( $custom_constants ) ) {
					foreach ( $custom_constants as $name => $data ) {
						if ( ! is_string( $name ) ) {
							continue;
						}
						if ( $this->is_reserved_identifier( $name ) ) {
							$this->add_system_message(
								'warning',
								/* translators: %s: constant name */
								sprintf( __( "Cannot register custom constant '%s': identifier is reserved by core.", 'expression-lab' ), esc_html( $name ) )
							);
							continue;
						}
						$value = is_array( $data ) && array_key_exists( 'value', $data ) ? $data['value'] : $data;
						if ( ! is_scalar( $value ) && null !== $value ) {
							$this->add_system_message(
								'warning',
								/* translators: %s: constant name */
								sprintf( __( "Cannot register custom constant '%s': constant value must be a scalar (string, integer, float, boolean).", 'expression-lab' ), esc_html( $name ) )
							);
						}
					}
				}
			} catch ( \Throwable $e ) {
				$this->add_system_message(
					'error',
					/* translators: %s: error message */
					sprintf( __( 'Error checking custom constants: %s', 'expression-lab' ), esc_html( $e->getMessage() ) )
				);
			}

			// Check custom objects.
			try {
				$custom_objects = apply_filters( 'expressionlab_register_objects', array() );
				if ( is_array( $custom_objects ) ) {
					foreach ( $custom_objects as $name => $instance ) {
						if ( is_string( $name ) && $this->is_reserved_identifier( $name ) ) {
							$this->add_system_message(
								'warning',
								/* translators: %s: object name */
								sprintf( __( "Cannot register custom object '%s': identifier is reserved by core.", 'expression-lab' ), esc_html( $name ) )
							);
						}
					}
				}
			} catch ( \Throwable $e ) {
				$this->add_system_message(
					'error',
					/* translators: %s: error message */
					sprintf( __( 'Error checking custom objects: %s', 'expression-lab' ), esc_html( $e->getMessage() ) )
				);
			}

			// Check custom functions.
			try {
				$custom_functions = apply_filters( 'expressionlab_register_functions', array() );
				if ( is_array( $custom_functions ) ) {
					foreach ( $custom_functions as $func_name => $func_data ) {
						if ( is_string( $func_name ) && $this->is_reserved_identifier( $func_name ) ) {
							$this->add_system_message(
								'warning',
								/* translators: %s: function name */
								sprintf( __( "Cannot register custom function '%s': identifier is reserved by core.", 'expression-lab' ), esc_html( $func_name ) )
							);
						}
					}
				}
			} catch ( \Throwable $e ) {
				$this->add_system_message(
					'error',
					/* translators: %s: error message */
					sprintf( __( 'Error checking custom functions: %s', 'expression-lab' ), esc_html( $e->getMessage() ) )
				);
			}
		}
	}

	/**
	 * Retrieves system diagnostic and initialization messages.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return array<int, array{type: string, text: string}> List of system message objects.
	 */
	public function get_system_messages(): array {
		if ( empty( $this->system_messages ) ) {
			$this->collect_system_messages();
		}
		return $this->system_messages;
	}

	/**
	 * Clears all system diagnostic messages.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function clear_system_messages(): void {
		$this->system_messages = array();
	}

	/**
	 * Adds a visualization data payload to the output.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param array $visualization The visualization data payload.
	 * @param bool  $bypass        Optional. Whether to add the visualization even in non-interactive mode. Default false.
	 */
	public function add_visualization( $visualization, bool $bypass = false ) {
		if ( ! $this->is_interactive_mode() && ! $bypass ) {
			$this->silenced_visualizations[] = $visualization;
			return;
		}
		$this->visualizations[] = $visualization;
	}

	/**
	 * Retrieves all collected visualizations.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return array List of visualization data items.
	 */
	public function get_visualizations() {
		return $this->visualizations;
	}

	/**
	 * Retrieves all silenced visualizations.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return array List of silenced visualization data items.
	 */
	public function get_silenced_visualizations() {
		return $this->silenced_visualizations;
	}

	/**
	 * Sets the user ID for the current execution context.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param int|null $user_id The user ID or null to unset.
	 * @return self The engine instance.
	 */
	public function set_user_id( ?int $user_id ): self {
		$this->user_id = $user_id;
		return $this;
	}

	/**
	 * Retrieves the user ID for the current execution context.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return int|null The user ID or null if not set.
	 */
	public function get_user_id(): ?int {
		return $this->user_id;
	}

	/**
	 * Sets the site ID for the current execution context.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param int|null $site_id The site ID or null to unset.
	 * @return self The engine instance.
	 */
	public function set_site_id( ?int $site_id ): self {
		$this->site_id = $site_id;
		return $this;
	}

	/**
	 * Retrieves the site ID for the current execution context.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return int|null The site ID or null if not set.
	 */
	public function get_site_id(): ?int {
		return $this->site_id;
	}

	/**
	 * Populates the execution context variables with user and site information.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param int|null $user_id Optional. Output variable passed by reference to receive user ID. Default null.
	 * @param int|null $site_id Optional. Output variable passed by reference to receive site ID. Default null.
	 */
	public function context( &$user_id = null, &$site_id = null ) {
		$user_id = $this->user_id;
		$site_id = $this->site_id;
	}

	/**
	 * Clears all output visualizations.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function clear_visualizations() {
		$this->visualizations = array();
	}

	/**
	 * Clears all silenced visualizations.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function clear_silenced_visualizations() {
		$this->silenced_visualizations = array();
	}

	/**
	 * Checks if implicit visualizations are allowed in the current mode.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return bool True if interactive visualizations are allowed, false otherwise.
	 */
	public function is_interactive_mode(): bool {
		return 0 === $this->active_progs;
	}

	/**
	 * Retrieves the current active prog nesting depth.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return int The current active prog depth.
	 */
	public function get_prog_depth(): int {
		return $this->active_progs;
	}

	/**
	 * Increments the active prog depth counter.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function increase_prog_depth() {
		$this->active_progs++; // phpcs:ignore
	}

	/**
	 * Decrements the active prog depth counter.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function decrease_prog_depth() {
		$this->active_progs--; // phpcs:ignore
	}

	/**
	 * Begins a new visualization group.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return $this The engine instance.
	 */
	public function begin_visualization_group() {
		if ( ! $this->is_interactive_mode() ) {
			$this->silenced_visualizations = array();
		}
		return $this;
	}

	/**
	 * Resolves a class instance with dependency injection.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string $class_name The class name to resolve.
	 * @return object The resolved instance.
	 *
	 * @throws \ReflectionException If the class does not exist.
	 */
	private function resolve_instance( string $class_name ) {
		// Initialize services container if needed.
		if ( ! is_array( $this->services ) ) {
			$this->services = array();
		}

		// If it's a service and already instantiated, return it.
		if ( isset( $this->services[ $class_name ] ) ) {
			return $this->services[ $class_name ];
		}

		$reflection  = new \ReflectionClass( $class_name );
		$constructor = $reflection->getConstructor();
		$args        = array();

		if ( $constructor ) {
			foreach ( $constructor->getParameters() as $param ) {
				$type = $param->getType();
				if ( $type && ! $type->isBuiltin() ) {
					$dependency_class = $type->getName();
					// Recursive resolution.
					$args[] = $this->resolve_instance( $dependency_class );
				} elseif ( $param->isDefaultValueAvailable() ) {
					$args[] = $param->getDefaultValue();
				} else {
					$args[] = null;
				}
			}
		}

		$instance = $reflection->newInstanceArgs( $args );

		// If it implements ServiceInterface, register it as a service.
		if ( in_array( ServiceInterface::class, $reflection->getInterfaceNames(), true ) ) {
			$this->services[ $class_name ] = $instance;
		}

		return $instance;
	}

	/**
	 * Initializes standard extension classes.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	private function init_extensions() {
		if ( null !== $this->extensions ) {
			return;
		}

		if ( null === self::$base_extensions ) {
			$ext_dir  = EXPRESSION_LAB_DIR . 'src/core/extensions/';
			$iterator = new \DirectoryIterator( $ext_dir );

			foreach ( $iterator as $fileinfo ) {
				if ( $fileinfo->isFile() && 'class-' === substr( $fileinfo->getFilename(), 0, 6 ) && 'php' === $fileinfo->getExtension() ) {
					require_once $fileinfo->getPathname();
				}
			}

			// Capture extension classes under the Extensions namespace implementing ExtensionInterface.
			self::$base_extensions = array_values(
				array_filter(
					get_declared_classes(),
					function ( $class_name ) {
						if ( ! str_starts_with( $class_name, 'ExpressionLab\\Core\\Extensions\\' ) ) {
							return false;
						}
						$ref = new \ReflectionClass( $class_name );
						return $ref->implementsInterface( ExtensionInterface::class );
					}
				)
			);
		}

		$this->extensions = self::$base_extensions;
	}

	/**
	 * Initializes standard service and model classes.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	private function init_services() {
		if ( null !== $this->service_classes ) {
			return;
		}

		if ( null === self::$base_service_classes ) {
			$services_dir = EXPRESSION_LAB_DIR . 'src/core/services/';
			$iterator     = new \DirectoryIterator( $services_dir );

			foreach ( $iterator as $fileinfo ) {
				if ( $fileinfo->isFile() && 'class-' === substr( $fileinfo->getFilename(), 0, 6 ) && 'php' === $fileinfo->getExtension() ) {
					// Skip the internal testing class.
					if ( 'class-expressionlab.php' === $fileinfo->getFilename() && ! EXPRESSION_LAB_TEST_ENV ) {
						continue;
					}
					require_once $fileinfo->getPathname();
				}
			}

			$models_dir = EXPRESSION_LAB_DIR . 'src/core/models/';
			$iterator   = new \DirectoryIterator( $models_dir );

			foreach ( $iterator as $fileinfo ) {
				if ( $fileinfo->isFile() && 'class-' === substr( $fileinfo->getFilename(), 0, 6 ) && 'php' === $fileinfo->getExtension() ) {
					require_once $fileinfo->getPathname();
				}
			}

			// Capture service classes under the Services namespace (excluding internal test class unless test env).
			self::$base_service_classes = array_values(
				array_filter(
					get_declared_classes(),
					function ( $class_name ) {
						if ( ! str_starts_with( $class_name, 'ExpressionLab\\Core\\Services\\' ) ) {
							return false;
						}
						if ( 'ExpressionLab\\Core\\Services\\ExpressionLab' === $class_name && ! EXPRESSION_LAB_TEST_ENV ) {
							return false;
						}
						return true;
					}
				)
			);
		}

		$this->service_classes = self::$base_service_classes;
	}

	/**
	 * Compiles the execution context for expression evaluation.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param SymfonyExpressionLanguage $language The expression language instance.
	 * @return array The evaluation context array.
	 */
	private function get_context( SymfonyExpressionLanguage $language ) {
		$this->init_extensions();
		$this->init_services();

		$context = array();

		// 1. Register language extensions (functions and constants).
		foreach ( $this->extensions as $class ) {
			$instance = new $class();
			$funcs    = $instance->get_functions();

			foreach ( $funcs as $name => $data ) {
				if ( is_array( $data ) && isset( $data['callback'] ) ) {
					$language->addFunction( $data['callback'] );
				} elseif ( is_string( $data ) ) {
					$language->addFunction( ExpressionFunction::fromPhp( $data, $name ) );
				} else {
					$language->addFunction( ExpressionFunction::fromPhp( $name ) );
				}
			}

			$constants = $instance->get_constants();
			foreach ( $constants as $name => $data ) {
				$context[ $name ] = $data['value'];
			}
		}

		// 2. Register runtime services (objects).
		foreach ( $this->service_classes as $class ) {
			$reflection                             = new \ReflectionClass( $class );
			$instance                               = $this->resolve_instance( $class );
			$context[ $reflection->getShortName() ] = $instance;
		}

		$instance = $this;

		// Special functions and objects.

		$context['clear'] = null;
		$context['args']  = array();
		$context['var']   = new class( $instance ) {
			/**
			 * The parent instance to access variable methods.
			 *
			 * @since 1.0.0
			 *
			 * @var LanguageEngine
			 */
			private $instance;

			/**
			 * Initializes the anonymous variable accessor.
			 *
			 * @since 1.0.0
			 *
			 * @param LanguageEngine $instance The parent instance to access variable methods.
			 */
			public function __construct( $instance ) {
				$this->instance = $instance;
			}

			/**
			 * Magic getter for variables.
			 *
			 * @since 1.0.0
			 *
			 * @param string $var_name The variable name.
			 * @return mixed The variable value or null if not defined.
			 */
			public function __get( $var_name ) {
				if ( ! $this->instance->has_variable( $var_name ) ) {
					$this->instance->add_message( 'warning', "Undefined variable: $var_name" );
					return null;
				}
				return $this->instance->get_variable( $var_name );
			}
		};

		$language->addFunction(
			new ExpressionFunction(
				'constant',
				function () {
					throw new \RuntimeException( 'The "constant" function is disabled for security reasons.' );
				},
				function () {
					throw new \RuntimeException( 'The "constant" function is disabled for security reasons.' );
				}
			)
		);

		if ( $this->are_hooks_enabled() ) {
			// Granular constants.
			try {
				/**
				 * Filters custom constants available in the expression context.
				 *
				 * @param array $custom_constants Array of constant names => [ 'value' => mixed, 'docs' => array ].
				 */
				$custom_constants = apply_filters( 'expressionlab_register_constants', array() );
				if ( is_array( $custom_constants ) ) {
					foreach ( $custom_constants as $name => $data ) {
						if ( ! is_string( $name ) || $this->is_reserved_identifier( $name ) || array_key_exists( $name, $context ) ) {
							$this->add_message( 'warning', sprintf( "Cannot register custom constant '%s': identifier is reserved by core.", esc_html( (string) $name ) ) );
							continue;
						}
						$value = is_array( $data ) && array_key_exists( 'value', $data ) ? $data['value'] : $data;
						if ( ! is_scalar( $value ) && null !== $value ) {
							$this->add_message( 'warning', sprintf( "Cannot register custom constant '%s': constant value must be a scalar (string, integer, float, boolean).", esc_html( (string) $name ) ) );
							continue;
						}
						$context[ $name ] = $value;
					}
				}
			} catch ( \Throwable $e ) {
				$this->add_message( 'error', 'Error registering custom constants: ' . $e->getMessage() );
			}

			// Granular objects.
			try {
				/**
				 * Filters custom objects available in the expression context.
				 *
				 * @param array $custom_objects Array of object names => object instances.
				 */
				$custom_objects = apply_filters( 'expressionlab_register_objects', array() );
				if ( is_array( $custom_objects ) ) {
					foreach ( $custom_objects as $name => $object_instance ) {
						if ( ! is_string( $name ) || $this->is_reserved_identifier( $name ) || array_key_exists( $name, $context ) ) {
							$this->add_message( 'warning', sprintf( "Cannot register custom object '%s': identifier is reserved by core.", esc_html( (string) $name ) ) );
							continue;
						}
						if ( is_object( $object_instance ) ) {
							$context[ $name ] = $object_instance;
						}
					}
				}
			} catch ( \Throwable $e ) {
				$this->add_message( 'error', 'Error registering custom objects: ' . $e->getMessage() );
			}

			// Granular functions.
			try {
				/**
				 * Filters custom functions available in the expression language.
				 *
				 * @param array $custom_functions Array of function names => function config / callbacks.
				 */
				$custom_functions = apply_filters( 'expressionlab_register_functions', array() );
				if ( is_array( $custom_functions ) ) {
					foreach ( $custom_functions as $func_name => $func_data ) {
						if ( ! is_string( $func_name ) || $this->is_reserved_identifier( $func_name ) ) {
							$this->add_message( 'warning', sprintf( "Cannot register custom function '%s': identifier is reserved by core.", esc_html( (string) $func_name ) ) );
							continue;
						}

						if ( $func_data instanceof ExpressionFunction ) {
							$language->addFunction( $func_data );
						} elseif ( is_array( $func_data ) && isset( $func_data['callback'] ) && is_callable( $func_data['callback'] ) ) {
							$callback = $func_data['callback'];
							$language->addFunction(
								new ExpressionFunction(
									$func_name,
									function () {
										// Not implemented in compiled mode.
									},
									function ( $context, ...$args ) use ( $callback ) {
										$engine = LanguageEngine::get();
										$engine->enter_call();
										try {
											$engine->tick();
											return call_user_func_array( $callback, $args );
										} finally {
											$engine->leave_call();
										}
									}
								)
							);
						} elseif ( is_callable( $func_data ) ) {
							$callback = $func_data;
							$language->addFunction(
								new ExpressionFunction(
									$func_name,
									function () {
										// Not implemented in compiled mode.
									},
									function ( $context, ...$args ) use ( $callback ) {
										$engine = LanguageEngine::get();
										$engine->enter_call();
										try {
											$engine->tick();
											return call_user_func_array( $callback, $args );
										} finally {
											$engine->leave_call();
										}
									}
								)
							);
						}
					}
				}
			} catch ( \Throwable $e ) {
				$this->add_message( 'error', 'Error registering custom functions: ' . $e->getMessage() );
			}
		}

		return $context;
	}

	/**
	 * Evaluates an expression string.
	 *
	 * @since 1.0.0
	 *
	 * @param string $expression The expression to evaluate.
	 * @return array The result array containing 'result', 'object_type', 'output', 'errors', 'messages', and 'visualizations'.
	 *
	 * @throws \InvalidArgumentException If the expression is empty or exceeds the maximum length.
	 */
	public function evaluate( string $expression ): array {
		if ( 0 === strlen( $expression ) ) {
			throw new \InvalidArgumentException( 'No expression provided' );
		}

		if ( strlen( $expression ) > self::MAX_EXPRESSION_LENGTH ) {
			throw new \InvalidArgumentException( 'Expression length exceeds the maximum allowed size.' );
		}

		$this->execution_start = microtime( true );

		$language = new \ExpressionLab\Core\ExpressionLanguage();
		$context  = $this->get_context( $language );

		if ( $this->are_hooks_enabled() ) {
			/**
			 * Fires before evaluating an expression.
			 *
			 * @param string $expression The expression string.
			 * @param array  $context    The evaluation context.
			 */
			do_action( 'expressionlab_before_evaluate', $expression, $context );
		}

		$parsed_expression = $language->parse( $expression, array_keys( $context ) );
		$this->validate_ast( $parsed_expression->getNodes() );

		// 1. Capture printed output.
		ob_start();

		// 2. Capture non-fatal errors such as warnings or notices.
		$captured_errors = array();

		// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting
		// phpcs:disable WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_error_reporting

		set_error_handler(
			function ( $errno, $errstr, $errfile, $errline ) use ( &$captured_errors ) {
				// Don't capture suppressed errors (with @).
				if ( 0 === error_reporting() ) {
					return false;
				}
				$captured_errors[] = "#$errno: $errstr in $errfile:$errline";
				return true; // Don't execute PHP internal error handler.
			}
		);

		try {
			$result = $language->evaluate( $parsed_expression, $context );
		} finally {
			// Restore handlers.
			restore_error_handler();
			$output           = ob_get_clean();
			$this->call_depth = 0;
		}

		$object_type = is_object( $result ) && 'stdClass' !== get_class( $result ) ? get_class( $result ) : null;

		if ( $result instanceof Model ) {
			$result = $result->get_value();
		}

		if ( $result instanceof \Closure || is_resource( $result ) ) {
			$result = null; // Don't return closures or resources.
		}

		if ( is_object( $result ) && 'stdClass' !== get_class( $result ) ) {
			$result = null; // Don't return arbitrary objects.
		}

		// phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		// phpcs:enable WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting
		// phpcs:enable WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_error_reporting

		if ( $this->are_hooks_enabled() ) {
			/**
			 * Fires after evaluating an expression.
			 *
			 * @param mixed $result         The evaluation result.
			 * @param float $execution_time The execution time in seconds.
			 * @param int   $operations     The estimated number of operations.
			 */
			do_action( 'expressionlab_after_evaluate', $result, microtime( true ) - $this->execution_start, $this->operations );
		}

		return array(
			'result'         => $result,
			'object_type'    => $object_type,
			'output'         => $output,
			'errors'         => $captured_errors,
			'messages'       => self::get_messages(),
			'visualizations' => self::get_visualizations(),
		);
	}

	/**
	 * Recursively validates the AST nodes for safety limits.
	 *
	 * Traverses the AST iteratively using a stack to prevent call stack overflows,
	 * ensuring limits on nesting depth and node count are respected.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param \Symfony\Component\ExpressionLanguage\Node\Node $root_node The AST root node to validate.
	 *
	 * @throws \RuntimeException If the AST is too deep, too large, or hits execution limits.
	 */
	private function validate_ast( \Symfony\Component\ExpressionLanguage\Node\Node $root_node ) {
		$stack      = array( array( $root_node, 1 ) ); // array( node, current_depth ).
		$max_depth  = 250;
		$max_nodes  = self::MAX_EXPRESSION_LENGTH;
		$node_count = 0;

		while ( ! empty( $stack ) ) {
			list( $node, $depth ) = array_pop( $stack );
			++$node_count;

			// Horizontal emergency brake.
			if ( $node_count > $max_nodes ) {
				throw new \RuntimeException( esc_html( 'AST validation failed: Expression is too complex (too many nodes).' ) );
			}

			// Vertical emergency brake.
			if ( $depth > $max_depth ) {
				throw new \RuntimeException( esc_html( 'AST validation failed: Expression is too deeply nested.' ) );
			}

			// Consume the general "Gas" of the engine.
			if ( 0 === $node_count % 50 ) {
				$this->tick( $remaining_time, 50 );
			}

			// Stack the child nodes to continue iterating.
			foreach ( $node->nodes as $child ) {
				if ( $child instanceof \Symfony\Component\ExpressionLanguage\Node\Node ) {
					$stack[] = array( $child, $depth + 1 );
				}
			}
		}

		// Ensure to consume the remaining nodes that didn't fit into the modulo of 50 for ticking.
		$remainder = $node_count % 50;

		if ( $remainder > 0 ) {
			$this->tick( $remaining_time, $remainder );
		}
	}

	/**
	 * Retrieves the outline data structure of the language library.
	 *
	 * @since 1.0.0
	 *
	 * @return array The outline data array containing objects, functions, constants, and typeRegistry.
	 */
	public function get_outline(): array {
		$this->init_extensions();
		$this->init_services();

		$outline = array(
			'objects'      => array(),
			'functions'    => array(),
			'constants'    => array(),
			'typeRegistry' => array(),
		);

		// 1. Process extensions (constants and functions).
		foreach ( $this->extensions as $class ) {
			$instance  = new $class();
			$constants = $instance->get_constants();
			$functions = $instance->get_functions();

			foreach ( $constants as $constant_name => $constant_data ) {
				$insert_text = '';
				$parsed_doc  = $this->outliner->build_constant_docs( $constant_name, $constant_data, $insert_text );

				$outline['constants'][ $constant_name ] = array(
					'doc'        => $parsed_doc,
					'insertText' => $insert_text,
				);
			}

			foreach ( $functions as $function_name => $function_data ) {
				$insert_text = '';
				$parsed_doc  = $this->outliner->build_function_docs( $function_name, $function_data, $insert_text );

				$outline['functions'][ $function_name ] = array(
					'doc'        => $parsed_doc,
					'insertText' => $insert_text,
				);
			}
		}

		// 2. Process runtime services (objects).
		$object_classes = array(); // shortName => fullClassName, for type registry.

		foreach ( $this->service_classes as $class ) {
			$reflection                    = new \ReflectionClass( $class );
			$short_name                    = $reflection->getShortName();
			$object_classes[ $short_name ] = $class;
			$constants                     = $reflection->getReflectionConstants();
			$properties                    = $reflection->getProperties( \ReflectionProperty::IS_PUBLIC );
			$methods                       = $reflection->getMethods( \ReflectionMethod::IS_PUBLIC );

			foreach ( $constants as $constant ) {
				if ( ! $constant->isPublic() ) {
					continue;
				}

				$name             = $constant->getName();
				$object_signature = $short_name . '::' . $name;
				$insert_text      = '';
				$parsed_doc       = $this->outliner->build_object_constant_docs( $object_signature, $constant, $insert_text );

				$outline['objects'][ $short_name ]['constants'][ $name ] = array(
					'doc'        => $parsed_doc,
					'insertText' => $insert_text,
				);
			}

			foreach ( $properties as $property ) {
				if ( $property->isStatic() ) {
					continue;
				}

				// Skip Model properties (Classes inheriting from Model::class).
				if ( is_a( $property->getDeclaringClass()->getName(), Model::class, true ) ) {
					continue;
				}

				$name             = $property->getName();
				$object_signature = $short_name . '.' . $name;
				$insert_text      = '';
				$parsed_doc       = $this->outliner->build_property_docs( $object_signature, $property, $insert_text );

				$return_info = $this->outliner->resolve_return_type( $property, $class );
				$return_type = $return_info ? $return_info[0] : null;

				$outline['objects'][ $short_name ]['properties'][ $name ] = array(
					'doc'        => $parsed_doc,
					'insertText' => $insert_text,
					'returnType' => $return_type,
				);
			}

			foreach ( $methods as $method ) {
				if ( $method->isConstructor() || $method->isDestructor() || $method->isStatic() ) {
					continue;
				}

				// Skip magic methods.
				if ( 0 === strpos( $method->getName(), '__' ) ) {
					continue;
				}

				// Skip Model methods (Classes inheriting from Model::class).
				if ( is_a( $method->getDeclaringClass()->getName(), Model::class, true ) ) {
					continue;
				}

				$raw_comment      = $method->getDocComment();
				$object_signature = $short_name . '.' . $method->getName();
				$parsed_doc       = null;
				$insert_text      = '';

				if ( $raw_comment ) {
					// Skip @internal methods.
					if ( strpos( $raw_comment, '@internal' ) !== false ) {
						continue;
					}

					$parsed_doc = $this->outliner->build_object_docs( $object_signature, $method, $insert_text );
				}

				$return_info = $this->outliner->resolve_return_type( $method, $class );
				$return_type = $return_info ? $return_info[0] : null;

				$outline['objects'][ $short_name ]['methods'][ $method->getName() ] = array(
					'doc'        => $parsed_doc,
					'insertText' => $insert_text,
					'returnType' => $return_type,
				);
			}
		}

		// Build type registry for lazy chain resolution.
		$outline['typeRegistry'] = $this->outliner->build_type_registry( $object_classes );

		if ( $this->are_hooks_enabled() ) {
			// Add custom constants to outline.
			// phpcs:disable Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			try {
				$custom_constants = apply_filters( 'expressionlab_register_constants', array() );
				if ( is_array( $custom_constants ) ) {
					foreach ( $custom_constants as $constant_name => $constant_data ) {
						if ( ! is_string( $constant_name ) || $this->is_reserved_identifier( $constant_name ) || isset( $outline['constants'][ $constant_name ] ) ) {
							continue;
						}
						$value = is_array( $constant_data ) && array_key_exists( 'value', $constant_data ) ? $constant_data['value'] : $constant_data;
						if ( ! is_scalar( $value ) && null !== $value ) {
							continue;
						}
						$constant_info                          = is_array( $constant_data ) && array_key_exists( 'value', $constant_data ) ? $constant_data : array( 'value' => $constant_data );
						$insert_text                            = '';
						$parsed_doc                             = $this->outliner->build_constant_docs( $constant_name, $constant_info, $insert_text );
						$outline['constants'][ $constant_name ] = array(
							'doc'        => $parsed_doc,
							'insertText' => $insert_text,
						);
					}
				}
			} catch ( \Throwable $e ) {
				// Silently ignore outline errors from third-party hooks.
			}

			// Add custom functions to outline.
			try {
				$custom_functions = apply_filters( 'expressionlab_register_functions', array() );
				if ( is_array( $custom_functions ) ) {
					foreach ( $custom_functions as $function_name => $function_data ) {
						if ( ! is_string( $function_name ) || $this->is_reserved_identifier( $function_name ) || isset( $outline['functions'][ $function_name ] ) ) {
							continue;
						}
						$insert_text                            = '';
						$parsed_doc                             = $this->outliner->build_function_docs( $function_name, is_array( $function_data ) ? $function_data : array(), $insert_text );
						$outline['functions'][ $function_name ] = array(
							'doc'        => $parsed_doc,
							'insertText' => $insert_text,
						);
					}
				}
			} catch ( \Throwable $e ) {
				// Silently ignore outline errors from third-party hooks.
			}

			// Add custom objects to outline.
			try {
				$custom_objects = apply_filters( 'expressionlab_register_objects', array() );
				if ( is_array( $custom_objects ) ) {
					foreach ( $custom_objects as $object_name => $object_instance ) {
						if ( ! is_string( $object_name ) || $this->is_reserved_identifier( $object_name ) || ! is_object( $object_instance ) || isset( $outline['objects'][ $object_name ] ) ) {
							continue;
						}
						$reflection = new \ReflectionClass( $object_instance );
						$class      = get_class( $object_instance );
						$short_name = $object_name;

						$object_classes[ $short_name ] = $class;
						$constants                     = $reflection->getReflectionConstants();
						$properties                    = $reflection->getProperties( \ReflectionProperty::IS_PUBLIC );
						$methods                       = $reflection->getMethods( \ReflectionMethod::IS_PUBLIC );

						foreach ( $constants as $constant ) {
							if ( ! $constant->isPublic() ) {
								continue;
							}
							$name             = $constant->getName();
							$object_signature = $short_name . '::' . $name;
							$insert_text      = '';
							$parsed_doc       = $this->outliner->build_object_constant_docs( $object_signature, $constant, $insert_text );
							$outline['objects'][ $short_name ]['constants'][ $name ] = array(
								'doc'        => $parsed_doc,
								'insertText' => $insert_text,
							);
						}

						foreach ( $properties as $property ) {
							if ( $property->isStatic() || is_a( $property->getDeclaringClass()->getName(), Model::class, true ) ) {
								continue;
							}
							$name             = $property->getName();
							$object_signature = $short_name . '.' . $name;
							$insert_text      = '';
							$parsed_doc       = $this->outliner->build_property_docs( $object_signature, $property, $insert_text );
							$return_info      = $this->outliner->resolve_return_type( $property, $class );
							$return_type      = $return_info ? $return_info[0] : null;
							$outline['objects'][ $short_name ]['properties'][ $name ] = array(
								'doc'        => $parsed_doc,
								'insertText' => $insert_text,
								'returnType' => $return_type,
							);
						}

						foreach ( $methods as $method ) {
							if ( $method->isConstructor() || $method->isDestructor() || $method->isStatic() || 0 === strpos( $method->getName(), '__' ) || is_a( $method->getDeclaringClass()->getName(), Model::class, true ) ) {
								continue;
							}
							$raw_comment      = $method->getDocComment();
							$object_signature = $short_name . '.' . $method->getName();
							$parsed_doc       = null;
							$insert_text      = '';

							if ( $raw_comment ) {
								if ( strpos( $raw_comment, '@internal' ) !== false ) {
									continue;
								}
								$parsed_doc = $this->outliner->build_object_docs( $object_signature, $method, $insert_text );
							}

							$return_info = $this->outliner->resolve_return_type( $method, $class );
							$return_type = $return_info ? $return_info[0] : null;

							$outline['objects'][ $short_name ]['methods'][ $method->getName() ] = array(
								'doc'        => $parsed_doc,
								'insertText' => $insert_text,
								'returnType' => $return_type,
							);
						}
					}
				}
			} catch ( \Throwable $e ) {
				// Silently ignore outline errors from third-party hooks.
			}
			// phpcs:enable Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		}

		// Sort alphabetically.
		ksort( $outline['objects'] );
		ksort( $outline['functions'] );
		ksort( $outline['constants'] );

		return $outline;
	}
}
