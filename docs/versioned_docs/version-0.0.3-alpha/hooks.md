---
id: hooks
title: Hooks
sidebar_position: 4
---

# Hooks

Register custom PHP objects, helper functions, services, and constants into Expression Lab using standard WordPress action and filter hooks.

---

## Enabling Hooks in Your Environment

For security reasons, third-party WordPress hooks are **disabled by default** in Expression Lab. To allow custom plugins or themes to register objects, functions, or constants, you must explicitly enable the hooks feature flag in your `wp-config.php` file:

```php
define( 'EXPRESSION_LAB_HOOKS_ENABLED', true );
```

:::warning Security Considerations for Registered Callbacks
Callbacks and functions registered via hooks execute with the standard privileges of the host PHP environment. To maintain environment security, avoid exposing functions that evaluate arbitrary strings (such as `eval()`) or invoke shell commands (`exec()`, `shell_exec()`, `system()`). Validate and sanitize all input parameters within your custom callbacks.
:::

---

## Filter Hooks

### expressionlab_register_objects

Injects custom PHP objects and singleton services into the global expression evaluation context.

#### Parameters

* `$custom_objects` (*array*) Associative array mapping identifier names to object instances.

#### Quick Start via Must-Use Plugin (MU-Plugin)

To test custom objects quickly, create a [Must-Use Plugin](https://developer.wordpress.org/advanced-administration/plugins/mu-plugins/) (e.g. `wp-content/mu-plugins/my-analytics-client.php`):

```php title="wp-content/mu-plugins/my-analytics-client.php"
<?php
/**
 * Plugin Name: Expression Lab - Custom Analytics Service
 * Description: Registers a custom service object into Expression Lab.
 *
 * @package expression-lab-custom-analytics-service
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

class MyPlugin_Analytics_Client {
	/**
	 * Registered events.
	 *
	 * @internal
	 * @var array
	 */
	private $events = array();

	/**
	 * Track a custom diagnostic event.
	 *
	 * Note that this docblock description will be rendered in the console sidebar.
	 *
	 * @param string $event The name of the event.
	 * @param array  $payload Additional data to send with the event.
	 * @return self
	 */
	public function track( string $event, array $payload = array() ): self {
		$this->events[] = array(
			'event'     => $event,
			'payload'   => $payload,
			'timestamp' => time(),
		);

		return $this;
	}

	/**
	 * Retrieve diagnostic metrics.
	 */
	public function get_events(): array {
		return $this->events;
	}
}

add_filter(
	'expressionlab_register_objects',
	function ( array $objects ) {
		$objects['Analytics'] = new MyPlugin_Analytics_Client();
		return $objects;
	}
);
```

Once saved, reload the Expression Lab console and interact with the service:

```elscript
prog[
    Analytics.track('info', { count: 1 }),
    Analytics.track('info', { count: 2 }),
    Analytics.get_events()
]
```

---

### expressionlab_register_functions

Registers custom functions into the Expression Language engine with optional documentation metadata for the console autocomplete explorer.

#### Parameters

* `$custom_functions` (*array*) Array of function names mapped to callback arrays.

#### Quick Start via Must-Use Plugin (MU-Plugin)

To test custom functions quickly, create a [Must-Use Plugin](https://developer.wordpress.org/advanced-administration/plugins/mu-plugins/) (e.g. `wp-content/mu-plugins/my-discount-function.php`):

```php title="wp-content/mu-plugins/my-discount-function.php"
<?php
/**
 * Plugin Name: Expression Lab - Custom Discount Function
 * Description: Registers a custom discount calculation function into Expression Lab.
 *
 * @package expression-lab-custom-discount-function
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

add_filter(
	'expressionlab_register_functions',
	function ( array $functions ) {
		$functions['calculate_discount'] = array(
			'callback' => function ( float $price, float $percent ) {
				return $price - ( $price * ( $percent / 100 ) );
			},
			'docs'     => array(
				'summary'     => 'Calculates discounted price from base price and percentage.',
				'description' => 'Returns price with discount applied.',
				'parameters'  => array(
					'price'   => array(
						'type'        => 'float',
						'description' => 'Original price',
					),
					'percent' => array(
						'type'        => 'float',
						'description' => 'Discount percentage (0-100)',
					),
				),
				'return'      => array( 'type' => 'float' ),
			),
		);

		return $functions;
	}
);
```

Once saved, reload the Expression Lab console and interact with the function:

```elscript
calculate_discount(120.0, 15)
```


---

### expressionlab_register_constants

Exposes custom configuration flags, version numbers, or API endpoints as global constants within the expression scope.

#### Parameters

* `$custom_constants` (*array*) Associative array mapping constant names to values or config arrays with documentation metadata.

:::tip Scalar Values Only
Custom constants must be scalar primitives (`string`, `int`, `float`, `bool`, or `null`). If you need to register complex structures, singletons, or services with methods, use `expressionlab_register_objects` instead.
:::

#### Quick Start via Must-Use Plugin (MU-Plugin)

To test custom constants quickly, create a [Must-Use Plugin](https://developer.wordpress.org/advanced-administration/plugins/mu-plugins/) (e.g. `wp-content/mu-plugins/my-custom-constants.php`):

```php title="wp-content/mu-plugins/my-custom-constants.php"
<?php
/**
 * Plugin Name: Expression Lab - Custom Global Constants
 * Description: Registers custom global constants into Expression Lab.
 *
 * @package expression-lab-custom-constants
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

add_filter(
	'expressionlab_register_constants',
	function ( array $constants ) {
		// Simple constant registration.
		$constants['DEFAULT_BATCH_SIZE'] = 100;

		// Constant registration with rich documentation for the sidebar outline explorer.
		$constants['API_ENDPOINT'] = array(
			'value' => 'https://api.example.com/v1',
			'docs'  => array(
				'summary'     => 'API Endpoint URL',
				'description' => 'Base URL endpoint for external service integration.',
				'type'        => 'string',
			),
		);

		return $constants;
	}
);
```

Once saved, reload the Expression Lab console and interact with the constants:

```elscript
DEFAULT_BATCH_SIZE * 2
```

---

### expressionlab_console_banners

Injects or customizes startup banner notices and diagnostic alerts rendered in the Expression Lab console header.

#### Parameters

* `$banner` (*array*) Array of banner message items formatted as `['type' => 'info'|'warning'|'error'|'success', 'text' => string]`.

#### Quick Start via Must-Use Plugin (MU-Plugin)

To test custom console banners quickly, create a [Must-Use Plugin](https://developer.wordpress.org/advanced-administration/plugins/mu-plugins/) (e.g. `wp-content/mu-plugins/my-console-banner.php`):

```php title="wp-content/mu-plugins/my-console-banner.php"
<?php
/**
 * Plugin Name: Expression Lab - Custom Console Banner
 * Description: Injects custom diagnostic notices and environment alerts into the Expression Lab console header.
 *
 * @package expression-lab-custom-console-banner
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

add_filter(
	'expressionlab_console_banners',
	function ( array $banner ) {
		$banner[] = array(
			'type' => 'success',
			'text' => 'Staging environment: Connected to test database cluster.',
		);

		return $banner;
	}
);
```

Once saved, reload the Expression Lab console to see the custom notification rendered in the top banner.

---

## Action Hooks (Lifecycle)

Expression Lab emits action hooks during expression execution to support auditing, performance monitoring, and telemetry logging.

### expressionlab_before_evaluate

Fires immediately before an expression is compiled and evaluated.

#### Parameters

* `$expression` (*string*) The raw DSL expression string submitted by the administrator.
* `$context` (*array*) The evaluation context array passed to the engine.

#### Quick Start via Must-Use Plugin (MU-Plugin)

To test pre-evaluation lifecycle hooks quickly, create a [Must-Use Plugin](https://developer.wordpress.org/advanced-administration/plugins/mu-plugins/) (e.g. `wp-content/mu-plugins/my-before-evaluate.php`):

```php title="wp-content/mu-plugins/my-before-evaluate.php"
<?php
/**
 * Plugin Name: Expression Lab - Before Evaluate Hook Example
 * Description: Logs incoming DSL expression strings before evaluation for security auditing.
 *
 * @package expression-lab-example-plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

add_action(
	'expressionlab_before_evaluate',
	function ( string $expression, array $context ) {
		// Log expression execution for security auditing.
		error_log( sprintf( '[ExpressionLab Audit] Executing: %s', $expression ) );
	},
	10,
	2
);
```

Once saved, evaluate any expression in the console and inspect your `wp-content/debug.log` file to verify the audit log entries.

---

### expressionlab_after_evaluate

Fires immediately after expression evaluation completes.

#### Parameters

* `$result` (*mixed*) The output or return value produced by the expression.
* `$duration` (*float*) Total execution time in seconds (measured with microsecond precision).
* `$operations` (*int*) Total operation watchdog ticks recorded during execution.

#### Quick Start via Must-Use Plugin (MU-Plugin)

To test post-evaluation telemetry hooks quickly, create a [Must-Use Plugin](https://developer.wordpress.org/advanced-administration/plugins/mu-plugins/) (e.g. `wp-content/mu-plugins/my-after-evaluate.php`):

```php title="wp-content/mu-plugins/my-after-evaluate.php"
<?php
/**
 * Plugin Name: Expression Lab - After Evaluate Hook Example
 * Description: Records expression execution time and operation watchdog tick metrics.
 *
 * @package expression-lab-example-plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

add_action(
	'expressionlab_after_evaluate',
	function ( $result, float $duration, int $operations ) {
		// Log telemetry metrics for performance monitoring.
		if ( $duration > 0.5 || $operations > 1000 ) {
			error_log( sprintf( '[ExpressionLab Telemetry] Slow evaluation (%.4fs, %d operations)', $duration, $operations ) );
		}
	},
	10,
	3
);
```

Once saved, evaluate expressions in the console to monitor duration and operations telemetry in your server logs.
