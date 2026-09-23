---
id: hooks
title: Hooks
sidebar_position: 4
---

# Hooks

Permite registrar objetos PHP personalizados, funciones, servicios y constantes en Expression Lab mediante el sistema nativo de acciones y filtros de WordPress.

---

## Habilitación de hooks

Por motivos de seguridad, los hooks de terceros están **desactivados de forma predeterminada** en Expression Lab. Para permitir que otros plugins o temas registren objetos, funciones o constantes, es necesario definir la constante `EXPRESSION_LAB_HOOKS_ENABLED` en el archivo `wp-config.php`:

```php
define( 'EXPRESSION_LAB_HOOKS_ENABLED', true );
```

:::warning Consideraciones de seguridad sobre funciones registradas
Las funciones y *callbacks* registrados mediante *hooks* se ejecutan con los privilegios estándar del entorno PHP anfitrión. Para preservar la estabilidad del entorno, evite exponer funciones que evalúen cadenas de código arbitrarias (como `eval()`) o invoquen comandos del sistema operativo (`exec()`, `shell_exec()`, `system()`). Valide y sanee los parámetros recibidos en sus funciones personalizadas.
:::

---

## Hooks de filtro (*filters*)

### expressionlab_register_objects

Inyecta instancias y servicios *singleton* de PHP en el contexto global de evaluación de expresiones.

#### Parámetros

* `$custom_objects` (*array*) Array asociativo que asigna identificadores de acceso a instancias de objetos.

#### Ejemplo mediante must-use plugin (MU-Plugin)

Para registrar objetos personalizados, se puede crear un [Must-Use Plugin](https://developer.wordpress.org/advanced-administration/plugins/mu-plugins/) (por ejemplo, `wp-content/mu-plugins/my-analytics-client.php`):

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
	 * Eventos registrados.
	 *
	 * @internal
	 * @var array
	 */
	private $events = array();

	/**
	 * Registra un evento de diagnóstico personalizado.
	 *
	 * Esta descripción se muestra en la barra lateral de la consola.
	 *
	 * @param string $event   Nombre del evento.
	 * @param array  $payload Datos adicionales asociados al evento.
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
	 * Recupera los eventos registrados.
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

Tras guardar el archivo y recargar la consola, es posible invocar el servicio:

```elscript
prog[
    Analytics.track('info', { count: 1 }),
    Analytics.track('info', { count: 2 }),
    Analytics.get_events()
]
```

---

### expressionlab_register_functions

Registra funciones personalizadas en el motor de expresiones, incluyendo metadatos de documentación opcionales para el autocompletado y el explorador lateral de la consola.

#### Parámetros

* `$custom_functions` (*array*) Array asociativo donde las claves son los nombres de las funciones y los valores son arrays de configuración con el *callback* y la documentación.

#### Ejemplo mediante must-use plugin (MU-Plugin)

Ejemplo en `wp-content/mu-plugins/my-discount-function.php`:

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
				'summary'     => 'Calcula el precio final aplicando un porcentaje de descuento.',
				'description' => 'Devuelve el precio resultante tras aplicar el descuento especificado.',
				'parameters'  => array(
					'price'   => array(
						'type'        => 'float',
						'description' => 'Precio base original',
					),
					'percent' => array(
						'type'        => 'float',
						'description' => 'Porcentaje de descuento (0 a 100)',
					),
				),
				'return'      => array( 'type' => 'float' ),
			),
		);

		return $functions;
	}
);
```

Tras guardar el archivo y recargar la consola, la función queda disponible para evaluación:

```elscript
calculate_discount(120.0, 15)
```

---

### expressionlab_register_constants

Expone indicadores de configuración, versiones o rutas de API como constantes globales legibles en las expresiones.

#### Parámetros

* `$custom_constants` (*array*) Array asociativo que asigna nombres de constantes a valores escalares o a estructuras con metadatos de documentación.

:::tip Restricción a valores escalares
Las constantes personalizadas deben ser tipos primitivos escalares (`string`, `int`, `float`, `bool` o `null`). Si requieres exponer objetos con métodos o estructuras dinámicas, utiliza el filtro `expressionlab_register_objects`.
:::

#### Ejemplo mediante must-use plugin (MU-Plugin)

Ejemplo en `wp-content/mu-plugins/my-custom-constants.php`:

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
		// Registro directo de una constante escalar.
		$constants['DEFAULT_BATCH_SIZE'] = 100;

		// Registro con metadatos enriquecidos para el explorador lateral.
		$constants['API_ENDPOINT'] = array(
			'value' => 'https://api.example.com/v1',
			'docs'  => array(
				'summary'     => 'URL base de la API',
				'description' => 'Punto de enlace principal para integraciones con servicios externos.',
				'type'        => 'string',
			),
		);

		return $constants;
	}
);
```

Tras guardar el archivo y recargar la consola, las constantes pueden utilizarse en cualquier expresión:

```elscript
DEFAULT_BATCH_SIZE * 2
```

---

### expressionlab_console_banners

Permite inyectar avisos, alertas de diagnóstico o recordatorios de entorno en la cabecera de la consola de Expression Lab.

#### Parámetros

* `$banner` (*array*) Array de notificaciones formateadas como `['type' => 'info'|'warning'|'error'|'success', 'text' => string]`.

#### Ejemplo mediante must-use plugin (MU-Plugin)

Ejemplo en `wp-content/mu-plugins/my-console-banner.php`:

```php title="wp-content/mu-plugins/my-console-banner.php"
<?php
/**
 * Plugin Name: Expression Lab - Custom Console Banner
 * Description: Injects custom diagnostic notices into the Expression Lab console header.
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
			'text' => 'Entorno de pruebas (Staging): Conectado al clúster de base de datos de test.',
		);

		return $banner;
	}
);
```

Al guardar el archivo y recargar la consola, se muestra la notificación destacada en la parte superior.

---

## Hooks de acción (*actions*)

Expression Lab dispara acciones durante las distintas fases de ejecución de las expresiones para facilitar tareas de auditoría, telemetría y monitorización de rendimiento.

### expressionlab_before_evaluate

Se dispara justo antes de analizar, compilar y evaluar la expresión.

#### Parámetros

* `$expression` (*string*) Cadena con la expresión DSL en bruto enviada por el usuario.
* `$context` (*array*) Array de variables y servicios contextuales pasados al motor de ejecución.

#### Ejemplo mediante must-use plugin (MU-Plugin)

Ejemplo en `wp-content/mu-plugins/my-before-evaluate.php`:

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
		// Registrar la expresión para auditoría de seguridad.
		error_log( sprintf( '[ExpressionLab Audit] Ejecutando: %s', $expression ) );
	},
	10,
	2
);
```

Al evaluar expresiones en la consola, las entradas quedan registradas en el archivo `wp-content/debug.log`.

---

### expressionlab_after_evaluate

Se dispara al finalizar la evaluación de la expresión.

#### Parámetros

* `$result` (*mixed*) Resultado devuelto por la expresión evaluada.
* `$duration` (*float*) Tiempo total transcurrido en segundos (medido con precisión de microsegundos).
* `$operations` (*int*) Número total de operaciones elementales (*gas ticks*) computadas durante la ejecución.

#### Ejemplo mediante must-use plugin (MU-Plugin)

Ejemplo en `wp-content/mu-plugins/my-after-evaluate.php`:

```php title="wp-content/mu-plugins/my-after-evaluate.php"
<?php
/**
 * Plugin Name: Expression Lab - After Evaluate Hook Example
 * Description: Records expression execution time and operation tick metrics.
 *
 * @package expression-lab-example-plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

add_action(
	'expressionlab_after_evaluate',
	function ( $result, float $duration, int $operations ) {
		// Registrar telemetría si la ejecución supera umbrales establecidos.
		if ( $duration > 0.5 || $operations > 1000 ) {
			error_log( sprintf( '[ExpressionLab Telemetry] Evaluación lenta (%.4fs, %d operaciones)', $duration, $operations ) );
		}
	},
	10,
	3
);
```

Tras guardar el archivo, la ejecución de expresiones permite registrar el tiempo de respuesta y el cómputo de operaciones en los logs del servidor.
