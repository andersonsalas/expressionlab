---
id: files
title: Archivos
sidebar_position: 7
---

# Archivos

Permite inspeccionar archivos, monitorear registros de depuración (*debug logs*) y analizar el consumo de disco en directorios dentro de la raíz de WordPress (`ABSPATH`) mediante métodos de solo lectura.

---

## Resolución de rutas y restricciones de acceso

El servicio `Files` impone las siguientes reglas de acceso al sistema de archivos:

* **Confinamiento a la raíz de WordPress (`ABSPATH`)**: Todas las rutas (relativas o absolutas) se canonicalizan mediante `realpath()` y se normalizan. Si una ruta intenta resolver fuera de `ABSPATH`, se lanza una `\InvalidArgumentException`.
* **Resolución de rutas relativas**: Cualquier ruta que no comience con una barra `/` o una letra de unidad en Windows se resuelve de forma relativa a `ABSPATH`.
* **Acceso de solo lectura**: El servicio ofrece operaciones exclusivas de inspección (`exists`, `is_file`, `is_dir`, `size`, `modified`, `read`, `tail`, `list`, `stats`). No expone primitivas de escritura, modificación ni eliminación.
* **Contador de operaciones**: Las operaciones de E/S de disco incrementan el contador de operaciones (`LanguageEngine::tick()`) para cumplir con los límites de tiempo de ejecución configurados.

---

## Inspección de archivos y directorios

### Files.exists()

Comprueba si existe un archivo o directorio en la ruta especificada dentro del directorio raíz de WordPress.

```elscriptsignature
Files.exists(
  string:path
): bool
```

#### Parámetros

* **`path`** (`string`, _requerido_): Ruta de archivo o directorio relativa a `ABSPATH` o absoluta dentro de `ABSPATH`.

#### Valor de retorno

Devuelve `true` si el destino existe dentro de `ABSPATH`, o `false` si no existe o si se intenta un salto de directorio (*path traversal*) fuera de `ABSPATH`.

#### Ejemplos

```elscript
/* Comprobar si debug.log existe en el directorio wp-content */
Files.exists('wp-content/debug.log')
```

```elscript
/* Comprobar si wp-config.php existe */
Files.exists('wp-config.php')
```

```elscript
/* Comprobar si existe una subcarpeta de subidas */
Files.exists('wp-content/uploads/2026/08')
```

---

### Files.is_file()

Comprueba si la ruta especificada existe y es un archivo regular.

```elscriptsignature
Files.is_file(
  string:path
): bool
```

#### Parámetros

* **`path`** (`string`, _requerido_): Ruta a inspeccionar.

#### Valor de retorno

Devuelve `true` si la ruta existe y es un archivo regular, o `false` si es un directorio, no existe o se resuelve fuera de `ABSPATH`.

#### Ejemplos

```elscript
/* Verificar si wp-config.php es un archivo regular */
Files.is_file('wp-config.php')
```

```elscript
/* Comprobar si una ruta de directorio es un archivo (evalúa a false) */
Files.is_file('wp-content/plugins')
```

---

### Files.is_dir()

Comprueba si la ruta especificada existe y es un directorio.

```elscriptsignature
Files.is_dir(
  string:path
): bool
```

#### Parámetros

* **`path`** (`string`, _requerido_): Ruta a inspeccionar.

#### Valor de retorno

Devuelve `true` si la ruta existe y es un directorio, o `false` si es un archivo regular, no existe o se resuelve fuera de `ABSPATH`.

#### Ejemplos

```elscript
/* Verificar si wp-content/plugins es un directorio */
Files.is_dir('wp-content/plugins')
```

```elscript
/* Comprobar si la ruta de un archivo es un directorio (evalúa a false) */
Files.is_dir('wp-load.php')
```

---

### Files.size()

Recupera el tamaño de un archivo o directorio en bytes y como una cadena formateada legible para humanos.

```elscriptsignature
Files.size(
  string:path
): array
```

#### Parámetros

* **`path`** (`string`, _requerido_): Ruta al archivo o directorio.

#### Valor de retorno

Devuelve un arreglo asociativo con las siguientes claves:

| Campo | Tipo | Descripción |
| :--- | :--- | :--- |
| `bytes` | `int` | Tamaño exacto del archivo o directorio en bytes. |
| `human` | `string` | Cadena de tamaño formateada con unidades binarias (`B`, `KB`, `MB`, `GB`, `TB`) redondeada a dos decimales. |

#### Manejo de errores

Lanza `\InvalidArgumentException` si la ruta no existe o se resuelve fuera de `ABSPATH`.

#### Ejemplos

```elscript
/* Recuperar métricas de tamaño para wp-config.php */
Files.size('wp-config.php')
```

```elscript
/* Recuperar métricas de tamaño para un archivo de registro */
Files.size('wp-content/debug.log')
```

```elscript
/* Acceder solo al recuento de bytes sin procesar */
Files.size('wp-load.php')['bytes']
```

```elscript
/* Acceder a la cadena formateada legible para humanos */
Files.size('wp-load.php')['human']
```

---

### Files.modified()

Recupera la marca de tiempo de la última modificación y la cadena de fecha y hora formateada en ISO UTC para un archivo o directorio.

```elscriptsignature
Files.modified(
  string:path
): array
```

#### Parámetros

* **`path`** (`string`, _requerido_): Ruta al archivo o directorio.

#### Valor de retorno

Devuelve un arreglo asociativo con las siguientes claves:

| Campo | Tipo | Descripción |
| :--- | :--- | :--- |
| `timestamp` | `int` | Marca de tiempo Unix de la última modificación. |
| `formatted` | `string` | Cadena de fecha y hora formateada en UTC (`YYYY-MM-DD HH:MM:SS UTC`). |

#### Manejo de errores

Lanza `\InvalidArgumentException` si la ruta no existe o se resuelve fuera de `ABSPATH`.

#### Ejemplos

```elscript
/* Recuperar metadatos de modificación para wp-config.php */
Files.modified('wp-config.php')
```

```elscript
/* Inspeccionar fecha y hora de modificación formateada para un directorio de plugin */
Files.modified('wp-content/plugins')['formatted']
```

---

## Lectura y recuperación de contenido

### Files.read()

Lee el contenido de un archivo hasta un límite máximo de bytes especificado.

```elscriptsignature
Files.read(
  string:path,
  int:max_bytes = Files.MAX_READ_BYTES
): string
```

#### Parámetros

* **`path`** (`string`, _requerido_): Ruta al archivo.
* **`max_bytes`** (`int`, _opcional_): Número máximo de bytes a leer. El valor predeterminado es `Files.MAX_READ_BYTES` (`262144` bytes = 256 KB). Acotado entre `1` y `Files.MAX_READ_BYTES`.

#### Valor de retorno

Devuelve un `string` con el contenido del archivo sin procesar hasta `max_bytes`.

#### Manejo de errores

* Lanza `\InvalidArgumentException` si la ruta no existe o se resuelve fuera de `ABSPATH`.
* Lanza `\RuntimeException` si la ruta de destino es un directorio (`'Cannot read a directory as a file.'`).
* Lanza `\RuntimeException` si el contenido del archivo no se puede leer (`'Failed to read file contents.'`).

#### Ejemplos

```elscript
/* Leer hasta 256 KB de un archivo de registro (límite predeterminado) */
Files.read('wp-content/debug.log')
```

```elscript
/* Leer los primeros 512 bytes de un archivo de configuración */
Files.read('wp-config.php', 512)
```

---

### Files.tail()

Lee las últimas *N* líneas de un archivo buscando hacia atrás desde el final del archivo en modo binario (`fseek`), sin cargar el archivo completo en memoria.

```elscriptsignature
Files.tail(
  string:path,
  int:lines = 50
): array
```

#### Parámetros

* **`path`** (`string`, _requerido_): Ruta al archivo.
* **`lines`** (`int`, _opcional_): Número de líneas a recuperar del final del archivo. El valor predeterminado es `50`. Acotado entre `1` y `500`.

#### Valor de retorno

Devuelve un arreglo de cadenas (`string[]`), donde cada elemento es una línea del final del archivo.

#### Manejo de errores

* Lanza `\InvalidArgumentException` si la ruta no existe o se resuelve fuera de `ABSPATH`.
* Lanza `\RuntimeException` si la ruta de destino es un directorio (`'Cannot tail a directory.'`).
* Lanza `\RuntimeException` si el archivo no se puede abrir para lectura (`'Failed to open file for tailing.'`).

#### Ejemplos

```elscript
/* Recuperar las últimas 50 líneas de debug.log (límite predeterminado) */
Files.tail('wp-content/debug.log')
```

```elscript
/* Recuperar las últimas 15 líneas del registro de depuración */
Files.tail('wp-content/debug.log', 15)
```

```elscript
/* Recuperar el máximo permitido de 500 líneas de un archivo de registro */
Files.tail('wp-content/debug.log', 500)
```

---

## Recorrido de directorios y diagnóstico

### Files.list()

Lista los archivos y directorios inmediatos contenidos dentro de una ruta de directorio especificada. Las entradas de puntos (`.` y `..`) se omiten. Los resultados se ordenan con los directorios primero (en orden alfabético sin distinción de mayúsculas y minúsculas), seguidos de los archivos (en orden alfabético sin distinción de mayúsculas y minúsculas).

```elscriptsignature
Files.list(
  string:path = '.'
): array
```

#### Parámetros

* **`path`** (`string`, _opcional_): Ruta de directorio relativa a `ABSPATH` o absoluta dentro de `ABSPATH`. El valor predeterminado es `'.'` (el directorio raíz de WordPress `ABSPATH`).

#### Valor de retorno

Devuelve un arreglo indexado de arreglos asociativos, cada uno representando un elemento del sistema de archivos:

| Campo | Tipo | Descripción |
| :--- | :--- | :--- |
| `name` | `string` | Nombre del archivo o directorio. |
| `type` | `string` | Tipo de entrada (`'dir'` o `'file'`). |
| `size` | `?string` | Tamaño formateado y legible para archivos, o `null` para directorios. |
| `modified` | `string` | Fecha y hora de modificación en UTC formateada como `YYYY-MM-DD HH:MM:SS`. |

#### Manejo de errores

* Lanza `\InvalidArgumentException` si la ruta no existe o se resuelve fuera de `ABSPATH`.
* Lanza `\RuntimeException` si la ruta de destino no es un directorio.

#### Ejemplos

```elscript
/* Listar el contenido del directorio raíz de WordPress */
Files.list()
```

```elscript
/* Listar el contenido del directorio wp-content */
Files.list('wp-content')
```

```elscript
/* Listar elementos dentro del directorio de plugins */
Files.list('wp-content/plugins')
```

```elscript
/* Listar elementos dentro del directorio de temas activos */
Files.list('wp-content/themes')
```

---

### Files.stats()

Calcula estadísticas de uso de espacio en disco para un directorio, agregando recuentos de archivos y tamaños en bytes agrupados por extensión de archivo. Genera visualizaciones interactivas en la consola de Expression Lab.

```elscriptsignature
Files.stats(
  string:path = '.'
): array
```

#### Parámetros

* **`path`** (`string`, _opcional_): Ruta del directorio a inspeccionar relativa a `ABSPATH` o absoluta dentro de `ABSPATH`. El valor predeterminado es `'.'` (el directorio raíz de WordPress `ABSPATH`).

#### Visualizaciones generadas

Al ejecutarse en Expression Lab, `Files.stats()` renderiza:

1. **Tabla de visualización (`Table: Directory disk distribution by extension`)**: Distribución tabular que muestra `extension`, `files`, `size` y `bytes`.
2. **Gráfico radial Vega-Lite (`Graph: Disk size distribution by extension`)**: Gráfico circular/radial interactivo que muestra el tamaño acumulado en disco por extensión de archivo.

#### Valor de retorno

Devuelve un arreglo asociativo con la siguiente estructura:

| Campo | Tipo | Descripción |
| :--- | :--- | :--- |
| `path` | `string` | Ruta absoluta normalizada del directorio inspeccionado. |
| `files` | `int` | Recuento total de archivos regulares en el directorio. |
| `directories` | `int` | Recuento total de subdirectorios inmediatos. |
| `total_bytes` | `int` | Tamaño acumulado de todos los archivos en bytes. |
| `total_size` | `string` | Cadena de tamaño acumulado formateada y legible. |
| `by_ext` | `array` | Lista de registros de resumen por extensión ordenados de forma descendente por tamaño en bytes. |

Cada elemento en `by_ext` contiene:

| Campo | Tipo | Descripción |
| :--- | :--- | :--- |
| `extension` | `string` | Extensión de archivo (por ejemplo, `'.php'`, `'.js'`, `'.css'` o `'(none)'`). |
| `files` | `int` | Número de archivos que coinciden con la extensión. |
| `size` | `string` | Tamaño acumulado formateado y legible para esta extensión. |
| `bytes` | `int` | Recuento acumulado de bytes para esta extensión. |

#### Manejo de errores

* Lanza `\InvalidArgumentException` si la ruta no existe o se resuelve fuera de `ABSPATH`.
* Lanza `\RuntimeException` si la ruta de destino no es un directorio.

#### Ejemplos

```elscript
/* Analizar la distribución de disco en el directorio raíz de WordPress */
Files.stats()
```

```elscript
/* Analizar la distribución de disco del directorio de subidas (uploads) */
Files.stats('wp-content/uploads')
```

```elscript
/* Analizar la distribución de disco del directorio de plugins */
Files.stats('wp-content/plugins')
```

```elscript
/* Recuperar el recuento total de archivos del directorio */
Files.stats('wp-content/themes')['files']
```

---

## Propiedades y constantes

### Files.MAX_READ_BYTES

Una propiedad de solo lectura que expone el límite máximo de bytes aplicado en operaciones individuales de `read()` (`262144` bytes = 256 KB).

```elscriptsignature
Files.MAX_READ_BYTES: int
```

* **Tipo**: `int` (Solo lectura)
* **Valor**: `262144`

#### Ejemplo

```elscript
/* Inspeccionar el límite máximo de lectura en bytes */
Files.MAX_READ_BYTES
```

---

### Files.MAX_TAIL_BYTES

Una propiedad de solo lectura que expone el límite máximo acumulado de bytes aplicado en operaciones de transmisión `tail()` (`1048576` bytes = 1 MB) para evitar la asignación desmedida de memoria en archivos de una sola línea.

```elscriptsignature
Files.MAX_TAIL_BYTES: int
```

* **Tipo**: `int` (Solo lectura)
* **Valor**: `1048576`

#### Ejemplo

```elscript
/* Inspeccionar el límite máximo del búfer de tail */
Files.MAX_TAIL_BYTES
```
