---
id: console
title: Consola
sidebar_position: 9
---

# Consola

Permite emitir mensajes de diagnóstico estructurados (`log`, `warning`, `error`, `success`) y generar tablas de datos interactivas en el panel de resultados de Expression Lab.

---

## Normalización de tipos y manejo de datos

Al enviar datos a cualquiera de los métodos de registro (`log`, `warning`, `error`, `success`), el valor se procesa mediante una normalización interna antes de agregarse al búfer de mensajes:

| Tipo de Dato | Comportamiento de normalización | Formato de salida resultante |
| :--- | :--- | :--- |
| **Primitivos escalares** (`string`, `int`, `float`, `bool`) | Se conservan en su tipo nativo sin alteraciones. | `'Operación completada'`, `120`, `true` |
| **Array** (`array`) | Se serializa a formato JSON mediante `wp_json_encode()`. | `'{"status":"ok","items":[1,2,3]}'` |
| **Clase estándar** (`stdClass`) | Se serializa a formato JSON mediante `wp_json_encode()`. | `'{"id":101,"active":true}'` |
| **Objeto stringable** (con `__toString()`) | Se convierte a texto mediante el método `__toString()` de la instancia. | `'Representación en cadena del objeto'` |
| **Objeto de clase personalizada** | Se formatea como un descriptor que indica el nombre de la clase. | `'<WP_Post>'`, `'<WP_User>'` o `'<Resource>'` |

Los mensajes se almacenan como registros asociativos que incluyen `type` (`'info'`, `'warning'`, `'error'` o `'success'`) y `text` (la salida ya normalizada), y se entregan en la respuesta de ejecución de la expresión.

---

## Métodos de registro de mensajes

### Console.log()

Registra un mensaje informativo (nivel `info`) en el panel de salida.

```elscriptsignature
Console.log(
  mixed:message
): void
```

#### Parámetros

* **`message`** (`mixed`, _requerido_): Contenido del mensaje. Puede ser una cadena, número, booleano, array u objeto.

#### Valor de retorno

Devuelve `null` (`void`).

#### Ejemplos

```elscript
/* Registrar un mensaje informativo */
Console.log('Secuencia de inicialización completada')
```

```elscript
/* Registrar un valor numérico */
Console.log(1024)
```

```elscript
/* Registrar un objeto o mapa estructurado */
Console.log({ status: 'healthy', memory_usage_mb: 42.5 })
```

```elscript
/* Registrar una expresión concatenada */
Console.log('Tema activo: ' ~ Options.get('stylesheet'))
```

---

### Console.warning()

Registra un mensaje de advertencia (nivel `warning`) en el panel de salida.

```elscriptsignature
Console.warning(
  mixed:message
): void
```

#### Parámetros

* **`message`** (`mixed`, _requerido_): Contenido del mensaje de advertencia.

#### Valor de retorno

Devuelve `null` (`void`).

#### Ejemplos

```elscript
/* Registrar una advertencia de configuración */
Console.warning('El tamaño de opciones autoload se aproxima al umbral recomendado de 800 KB')
```

```elscript
/* Registrar una advertencia estructurada */
Console.warning({ warning: 'Alto uso de memoria', allocated_mb: 210, limit_mb: 256 })
```

---

### Console.error()

Registra un mensaje de error (nivel `error`) en el panel de salida.

```elscriptsignature
Console.error(
  mixed:message
): void
```

#### Parámetros

* **`message`** (`mixed`, _requerido_): Contenido del mensaje de error.

#### Valor de retorno

Devuelve `null` (`void`).

#### Ejemplos

```elscript
/* Registrar un error de ejecución */
Console.error('El endpoint remoto devolvió un código de estado no esperado')
```

```elscript
/* Registrar detalles estructurados de error */
Console.error({ error_code: 'REST_INVALID_ARGUMENT', field: 'email' })
```

---

### Console.success()

Registra un mensaje de éxito (nivel `success`) en el panel de salida.

```elscriptsignature
Console.success(
  mixed:message
): void
```

#### Parámetros

* **`message`** (`mixed`, _requerido_): Contenido del mensaje de confirmación.

#### Valor de retorno

Devuelve `null` (`void`).

#### Ejemplos

```elscript
/* Registrar confirmación de éxito */
Console.success('Tabla replicada con éxito en SQLite')
```

```elscript
/* Registrar metadatos estructurados de confirmación */
Console.success({ updated: true, timestamp: 1725055200 })
```

---

## Visualización de tablas

### Console.table()

Renderiza una tabla interactiva en la salida de la consola.

`Console.table()` admite dos formatos de entrada: **Formato Asociativo (Registros)** y **Formato Posicional (Matriz)**.

```elscriptsignature
Console.table(
  array:arg1,
  array|string:arg2 = [],
  string:title = 'Table'
): void
```

#### Formatos soportados

##### 1. Formato asociativo (registros)

Se proporciona un array de arrays asociativos u objetos como `$arg1`, y de forma opcional el título de la tabla como `$arg2`.

* **Detección automática de columnas**: Los encabezados se infieren de las claves del primer elemento del array.
* **Normalización de filas**: Si una fila posterior carece de una columna presente en el primer elemento, esa celda se completa con `null`.
* **Título**: Se pasa como segundo argumento (`$arg2`). Si se omite, el valor predeterminado es `'Table'`.

```elscriptsignature
Console.table(
  array:data,
  string:title = 'Table'
): void
```

##### 2. Formato posicional (matriz)

Se proporciona un array indexado con los nombres de las columnas como `$arg1`, una matriz bidimensional con los valores como `$arg2`, y de forma opcional el título como `$title` (`$arg3`).

* **Mapeo por posición**: Los valores de cada fila se asocian por su índice numérico a los encabezados de `$arg1`.
* **Normalización de celdas**: Si una fila contiene menos valores que el total de columnas, las celdas restantes se rellenan con `null`.
* **Título**: Se pasa como tercer argumento (`$title`). Si se omite, el valor predeterminado es `'Table'`.

```elscriptsignature
Console.table(
  array:headers,
  array:values,
  string:title = 'Table'
): void
```

#### Parámetros

* **`arg1`** (`array`, _requerido_): Array de registros asociativos/objetos (formato asociativo) o array indexado de nombres de columnas (formato posicional).
* **`arg2`** (`array|string`, _opcional_): Título de la tabla (formato asociativo) o matriz de valores por filas (formato posicional). El valor predeterminado es `[]`.
* **`title`** (`string`, _opcional_): Título de la tabla cuando se emplea el formato posicional. El valor predeterminado es `'Table'`.

#### Valor de retorno

Devuelve `null` (`void`). Registra una carga de tipo `table` en el motor para su visualización gráfica en la consola.

#### Manejo de errores

* Lanza `\InvalidArgumentException` (`"Data cannot be empty."`) si `$arg1` es un array vacío.
* Lanza `\InvalidArgumentException` (`"Values cannot be empty when using positional headers."`) si `$arg1` especifica encabezados posicionales pero `$arg2` está vacío o no es un array.

#### Ejemplos

```elscript
/* Formato asociativo con título personalizado */
Console.table([
  { id: 1, name: 'Alice', role: 'Administrator' },
  { id: 2, name: 'Bob', role: 'Editor' }
], 'Miembros del Equipo')
```

```elscript
/* Formato asociativo con título predeterminado */
Console.table([
  { option: 'blogname', value: 'Expression Lab' },
  { option: 'admin_email', value: 'admin@example.com' }
])
```

```elscript
/* Formato posicional con título personalizado */
Console.table(
  ['ID', 'Usuario', 'Correo Electrónico'],
  [
    [1, 'admin', 'admin@example.com'],
    [2, 'editor', 'editor@example.com']
  ],
  'Directorio de Usuarios'
)
```

```elscript
/* Formato posicional con comparación de métricas */
Console.table(
  ['Métrica', 'Actual', 'Umbral'],
  [
    ['Tamaño Autoload', '450 KB', '800 KB'],
    ['Total Tablas', 12, 50]
  ],
  'Métricas de Rendimiento'
)
```

```elscript
/* Tabulación de comprobaciones del sistema de archivos */
Console.table([
  { path: 'wp-config.php', exists: Files.exists('wp-config.php') },
  { path: 'wp-content/debug.log', exists: Files.exists('wp-content/debug.log') }
], 'Verificaciones del Sistema de Archivos')
```
