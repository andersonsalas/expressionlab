---
id: media
title: Medios
sidebar_position: 6
---

# Medios

Permite localizar archivos adjuntos en la biblioteca de medios, detectar archivos huérfanos o desvinculados, inspeccionar tipos MIME y resolver URLs y dimensiones según cada tamaño registrado.

La arquitectura de gestión de medios consta de tres componentes interconectados:

1. **Servicio Media (`Media`)**: El punto de entrada principal para búsquedas de archivos adjuntos por ID numérico, slug, título o URL/ruta, descubrimiento de archivos adjuntos huérfanos, inspección de tipos MIME, eliminación y encadenamiento del constructor de consultas.
2. **Modelo Attachment (`Attachment`)**: Una entidad que representa un archivo adjunto individual de WordPress, exponiendo propiedades normalizadas (`ID`, `title`, `caption`, `description`, `alt`, `mime_type`, `url`, `file_path`, `filesize`, `filesize_human`, `dimensions`, `sizes`, `date`, `modified`, `author_id`, `parent_id`), métodos de resolución específicos de tamaño (`get_url()`, `get_path()`, `get_dimensions()`) y eliminación (`delete()`).
3. **Modelo PostMeta (`PostMeta`)**: Un repositorio de metadatos accesible mediante `attachment.meta` que proporciona operaciones CRUD de metadatos e integración del constructor de consultas vinculada al archivo adjunto principal.

---

## Acceso y recuperación de archivos adjuntos

### Media.get()

Recupera una única instancia del modelo `Attachment` por identificador (ID numérico, slug, título, URL, ruta relativa o nombre de archivo), o ejecuta las condiciones acumuladas del constructor de consultas cuando se llama sin argumentos.

```elscriptsignature
Media.get(
  int|string|null:identifier = null
): Attachment|array|null
```

#### Modos de resolución

* **Por ID numérico** (`int` o cadena numérica):
  Consulta a través de `get_post(absint($identifier))` y verifica que `post_type === 'attachment'`. Devuelve una instancia del modelo `Attachment`, o `null` si el archivo adjunto no existe.
* **Por URL, ruta o nombre de archivo** (`string` que coincida con formatos de URL, que comience con `http://` o `https://`, que contenga separadores de ruta `/` o que termine con una extensión de archivo como `.jpg`, `.png`, `.pdf`):
  1. Si se resuelve un ID, consulta `get_post()` y verifica `post_type === 'attachment'`.
  2. Devuelve una instancia del modelo `Attachment`, o `null` si no se encuentra ningún archivo adjunto coincidente.
* **Por slug o nombre del archivo adjunto** (`string`):
  Consulta a través de `get_posts()` con `name = sanitize_title($identifier)` con `post_type = 'attachment'` y `post_status = 'any'`. Devuelve una instancia del modelo `Attachment`, o `null` si no hay coincidencias.
* **Por título** (`string`):
  Consulta a través de `get_posts()` con `title = $identifier` con `post_type = 'attachment'` y `post_status = 'any'`. Devuelve una instancia del modelo `Attachment`, o `null` si no hay coincidencias.
* **Sin argumentos** (`null`):
  Ejecuta las condiciones acumuladas del `QueryBuilder` contra `wp_posts`, replica las filas coincidentes en una tabla SQLite en memoria, vacía el búfer y devuelve los resultados como un arreglo de objetos de fila.

#### Ejemplos

```elscript
/* Buscar archivo adjunto por ID numérico */
Media.get(42)
```

```elscript
/* Buscar archivo adjunto por URL del archivo original */
Media.get('https://example.com/wp-content/uploads/2026/08/hero-banner.jpg')
```

```elscript
/* Buscar archivo adjunto utilizando una URL de dimensión de miniatura generada */
Media.get('https://example.com/wp-content/uploads/2026/08/hero-banner-300x200.jpg')
```

```elscript
/* Buscar archivo adjunto utilizando una URL de imagen escalada de WordPress */
Media.get('https://example.com/wp-content/uploads/2026/08/hero-banner-scaled.jpg')
```

```elscript
/* Buscar archivo adjunto por ruta de subida relativa */
Media.get('2026/08/hero-banner.jpg')
```

```elscript
/* Buscar archivo adjunto por slug */
Media.get('hero-banner')
```

---

### Media.resolve_id()

Resuelve cualquier URL de un archivo adjunto, URL de miniatura, URL de variante generada, ruta relativa o nombre de archivo a su ID de post correspondiente en WordPress.

```elscriptsignature
Media.resolve_id(string:url): ?int
```

#### Parámetros

* **`url`** (`string`, _requerido_): La URL del archivo adjunto, URL de miniatura de tamaño secundario, ruta de subida relativa o nombre de archivo a resolver.

#### Pipeline de resolución

`resolve_id()` aplica un algoritmo secuencial de coincidencia heurística en 5 etapas:

1. **Resolución de URL nativa directa**:
   Normaliza URLs relativas usando `home_url()` y ejecuta `attachment_url_to_postid($full_url)`.
2. **Eliminación de sufijos de dimensión y modificadores**:
   Elimina sufijos de dimensión de miniatura generados (`-300x200`, `-1024x768`) y modificadores de procesadores de imágenes (`-scaled`, `-rotated`) mediante el patrón `/-(?:\d+x\d+|scaled|rotated)(?=\.[a-zA-Z0-9]+$)/i` y vuelve a consultar `attachment_url_to_postid()`.
3. **Ruta relativa a uploads y búsqueda en metadatos**:
   Extrae la ruta relativa a `wp_upload_dir()['baseurl']` o `/wp-content/uploads/` y realiza las siguientes comprobaciones en la base de datos:
   * Coincidencia directa contra `_wp_attached_file` en `wp_postmeta`.
   * Coincidencia de ruta relativa limpia contra `_wp_attached_file` sin sufijos de dimensión ni `-scaled`.
   * Coincidencia de nombre de archivo contra `_wp_attached_file` por nombre base (`meta_value = filename` o `meta_value LIKE '%/filename'`).
   * Búsqueda de tamaño secundario dentro de filas serializadas `_wp_attachment_metadata` que coincidan con el nombre base de la miniatura.
4. **Por GUID (fallback)**:
   Consulta `wp_posts` donde `post_type = 'attachment'` comparando `guid` con la URL sin procesar o la URL completa normalizada.
5. **Por defecto (fallback)**:
   Devuelve `null` si todas las heurísticas no logran resolver un ID.

#### Ejemplos

```elscript
/* Resolver ID desde una URL completa de miniatura */
Media.resolve_id('https://example.com/wp-content/uploads/2026/08/product-photo-150x150.jpg')
```

```elscript
/* Resolver ID desde una ruta relativa */
Media.resolve_id('/wp-content/uploads/2026/08/document.pdf')
```

---

### Media.unattached()

Recupera archivos adjuntos no asignados (archivos huérfanos en la biblioteca de medios donde `post_parent = 0` y `post_status = 'inherit'`).

```elscriptsignature
Media.unattached(int:limit = 50): array
```

#### Parámetros

* **`limit`** (`int`, _opcional_): Número máximo de archivos adjuntos sin asignar a devolver. El valor predeterminado es `50`.

#### Valor de retorno

Devuelve un arreglo de instancias del modelo `Attachment` que representan archivos adjuntos sin asignar.

#### Ejemplos

```elscript
/* Recuperar hasta 50 archivos adjuntos sin asignar */
Media.unattached()
```

```elscript
/* Recuperar hasta 10 archivos adjuntos sin asignar */
Media.unattached(10)
```

---

### Media.mime_types()

Recupera una lista alfabética de todos los tipos MIME distintos almacenados en la biblioteca de medios de WordPress.

```elscriptsignature
Media.mime_types(): array
```

#### Valor de retorno

Devuelve un arreglo indexado de cadenas que contiene tipos MIME únicos encontrados en `wp_posts` donde `post_type = 'attachment'` (por ejemplo, `['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'video/mp4']`).

#### Ejemplo

```elscript
/* Recuperar todos los tipos MIME distintos en la biblioteca de medios */
Media.mime_types()
```

---

### Media.delete()

Elimina un archivo adjunto de la base de datos y borra todos los archivos físicos asociados (archivo original y miniaturas de tamaños secundarios generadas) del sistema de archivos del servidor de forma definitiva.

```elscriptsignature
Media.delete(
  int:attachment_id,
  bool:force = false
): bool
```

#### Parámetros

* **`attachment_id`** (`int`, _requerido_): El ID del archivo adjunto a eliminar.
* **`force`** (`bool`, _opcional_): Cuando es `true`, omite la papelera y elimina el registro y los archivos de forma definitiva. El valor predeterminado es `false`.

#### Valor de retorno

Devuelve `true` si el archivo adjunto fue resuelto y eliminado, o `false` en caso de error.

:::warning Protección contra Escritura
Eliminar archivos adjuntos modifica tanto la base de datos como el sistema de archivos del servidor.

Lanza una excepción si `EXPRESSION_LAB_DATABASE_READONLY` o `EXPRESSION_LAB_FILESYSTEM_READONLY` están establecidos en `true`. Ambas constantes deben establecerse en `false` en `wp-config.php`.
:::

#### Ejemplos

```elscript
/* Enviar archivo adjunto a la papelera por ID numérico */
Media.delete(42)
```

```elscript
/* Eliminar de forma definitiva el archivo adjunto y los archivos físicos omitiendo la papelera */
Media.delete(42, true)
```

```elscript
/* Eliminar archivo adjunto identificado por URL de miniatura */
Media.delete('https://example.com/wp-content/uploads/2026/08/obsolete-image-300x200.jpg', true)
```

---

## Constructor de consultas de archivos adjuntos (query builder)

El servicio `Media` incorpora métodos encadenables de constructor de consultas dirigidos a la tabla `wp_posts`. Todas las operaciones encadenadas en `Media` se delimitan a archivos adjuntos mediante una condición base interna (`post_type = 'attachment'`). Las condiciones se acumulan en grupos y se compilan en SQL parametrizado cuando se ejecutan mediante `get()` o `query()`. Los datos replicados se colocan en una tabla SQLite en memoria y se descartan al vaciar el búfer.

### Media.where()

Agrega una condición `WHERE` combinada con lógica `AND` dentro del grupo de condiciones actual.

```elscriptsignature
Media.where(
  string:column,
  mixed:operator_or_value,
  mixed:value = null
): Media
```

#### Firmas de llamada

* **Igualdad** (Dos argumentos): `Media.where('column', 'value')`
* **Condición IN** (Dos argumentos con arreglo): `Media.where('column', ['val1', 'val2'])`
* **Operador de comparación** (Tres argumentos): `Media.where('column', 'operator', 'value')`

#### Operadores de comparación compatibles

La forma de tres argumentos `Media.where(column, operator, value)` acepta operadores de comparación estándar de SQL como cadenas:

| Operador | Descripción | Ejemplo |
| :--- | :--- | :--- |
| `'='` | Igualdad exacta (predeterminado) | `Media.where('post_mime_type', '=', 'image/png').get()`, o `Media.where('post_mime_type', 'image/png').get()` |
| `'>'` | Mayor que | `Media.where('ID', '>', 100).get()` |
| `'>='` | Mayor o igual que | `Media.where('ID', '>=', 100).get()` |
| `'<'` | Menor que | `Media.where('ID', '<', 50).get()` |
| `'<='` | Menor o igual que | `Media.where('ID', '<=', 50).get()` |
| `'!='`, `'<>'` | No igual | `Media.where('post_mime_type', '!=', 'image/jpeg').get()` |
| `'like'` | Coincidencia de patrones SQL LIKE | `Media.where('post_title', 'like', '%logo%').get()` |
| `'not like'` | Coincidencia de patrones SQL LIKE negada | `Media.where('post_title', 'not like', '%draft%').get()` |
| `'in'` | Pertenencia a conjuntos (el valor debe ser un arreglo) | `Media.where('post_mime_type', 'in', ['image/jpeg', 'image/png']).get()` |
| `'not in'` | Pertenencia a conjuntos negada (el valor debe ser un arreglo) | `Media.where('post_mime_type', 'not in', ['image/gif', 'image/bmp']).get()` |
| `'between'` | Comprobación de rango (el valor debe ser un arreglo de dos elementos `[min, max]`) | `Media.where('post_date', 'between', ['2026-01-01', '2026-12-31']).get()` |

:::note Sintaxis de QueryBuilder vs Database.mirror
Los métodos del `QueryBuilder` (`where()`, `or_where()`) aceptan cadenas de operadores SQL estándar (`'>'`, `'like'`, `'between'`) y las compilan en los objetos de condición recursivos con prefijo `$` requeridos por `Database.mirror()`.
:::

#### Ejemplos

```elscript
/* Consultar imágenes JPEG */
Media.where('post_mime_type', 'image/jpeg').get()
```

```elscript
/* Consultar archivos adjuntos subidos por un usuario específico */
Media.where('post_author', 1).get()
```

```elscript
/* Consultar documentos PDF que coincidan con un patrón de título */
Media.where('post_mime_type', 'application/pdf').where('post_title', 'like', '%Report%').get()
```

---

### Media.or_where()

Agrega una condición `WHERE` que inicia un nuevo grupo de condiciones combinado con los grupos anteriores mediante lógica `OR`. Las condiciones añadidas dentro de cada grupo usando `where()` se combinan mediante `AND`. La delimitación a `post_type = 'attachment'` se conserva en todos los grupos.

```elscriptsignature
Media.or_where(
  string:column,
  mixed:operator_or_value,
  mixed:value = null
): Media
```

#### Ejemplo

```elscript
/* Recuperar archivos adjuntos que sean imágenes PNG o documentos PDF */
Media.where('post_mime_type', 'image/png')
  .or_where('post_mime_type', 'application/pdf')
  .get()
```

---

### Media.order_by()

Especifica el ordenamiento de columnas para los resultados de la consulta. Se puede encadenar varias veces para aplicar ordenamiento multi-columna.

```elscriptsignature
Media.order_by(
  string:column,
  string:direction = 'ASC'
): Media
```

#### Parámetros

* **`column`** (`string`, _requerido_): Nombre de la columna por la cual ordenar (por ejemplo, `'ID'`, `'post_date'`, `'post_title'`, `'post_mime_type'`).
* **`direction`** (`string`, _opcional_): Dirección de ordenamiento (`'ASC'` o `'DESC'`). El valor predeterminado es `'ASC'`.

#### Ejemplos

```elscript
/* Ordenar archivos adjuntos por fecha de creación descendente */
Media.order_by('post_date', 'DESC').get()
```

```elscript
/* Ordenamiento multi-columna: ordenar por tipo MIME ascendente, luego por ID descendente */
Media.order_by('post_mime_type', 'ASC')
  .order_by('ID', 'DESC')
  .get()
```

---

### Media.limit()

Establece el número máximo de registros de archivos adjuntos a devolver.

```elscriptsignature
Media.limit(int:limit): Media
```

#### Ejemplo

```elscript
/* Recuperar los 10 archivos adjuntos más recientes */
Media.order_by('post_date', 'DESC')
  .limit(10)
  .get()
```

---

### Media.offset()

Establece el número de filas a omitir para la paginación.

```elscriptsignature
Media.offset(int:offset): Media
```

#### Ejemplo

```elscript
/* Omitir 20 registros y recuperar los siguientes 10 */
Media.order_by('ID', 'DESC')
  .offset(20)
  .limit(10)
  .get()
```

---

### Media.query()

Replica la tabla `wp_posts` en SQLite utilizando las condiciones acumuladas (incluyendo la restricción `post_type = 'attachment'`) y opciones, luego ejecuta una consulta SQL `SELECT` arbitraria contra la base de datos SQLite en memoria. Reinicia el estado del constructor tras la ejecución.

```elscriptsignature
Media.query(string:sql): array
```

#### Ejemplo

```elscript
/* Ejecutar agregación SQL arbitraria en archivos adjuntos replicados */
Media.query('SELECT post_mime_type, COUNT(*) AS total FROM wp_posts GROUP BY post_mime_type ORDER BY total DESC')
```

:::info Extensión SQLite3 Requerida
La extensión de PHP SQLite3 **debe** estar instalada y habilitada en el servidor para que `Media.query()` funcione.
:::

---

### Media.count()

Cuenta registros coincidentes en la base de datos MySQL sin almacenar filas en memoria ni inicializar una tabla SQLite. Aplica `post_type = 'attachment'`. Reinicia el estado del constructor tras la ejecución.

```elscriptsignature
Media.count(): int
```

#### Ejemplos

```elscript
/* Contar el total de archivos adjuntos en la biblioteca de medios */
Media.count()
```

```elscript
/* Contar imágenes PNG subidas por el usuario 1 */
Media.where('post_mime_type', 'image/png').where('post_author', 1).count()
```

---

### Media.to_sql()

Compila y devuelve la cadena de consulta SQL para las condiciones acumuladas sin ejecutarla. Aplica la delimitación `post_type = 'attachment'`. Reinicia el estado del constructor tras la compilación.

```elscriptsignature
Media.to_sql(bool:is_count = false): string
```

#### Parámetros

* **`is_count`** (`bool`, _opcional_): Cuando es `true`, compila una consulta `SELECT COUNT(*)` en lugar de una proyección de columnas. El valor predeterminado es `false`.

#### Ejemplos

```elscript
/* Previsualizar consulta SQL SELECT compilada */
Media.where('post_mime_type', 'image/webp')
  .order_by('ID', 'DESC')
  .limit(5)
  .to_sql()
```

```elscript
/* Previsualizar consulta SQL COUNT compilada */
Media.where('post_mime_type', 'image/webp').to_sql(true)
```

---

## Estadísticas y distribución

### Media.stats()

Calcula un desglose estadístico de todos los archivos adjuntos en la tabla `wp_posts` agrupados por tipo MIME, generando visualizaciones interactivas en la consola de Expression Lab.

```elscriptsignature
Media.stats(): array
```

#### Qué genera stats()

Al ejecutarse en Expression Lab, `Media.stats()` consulta MySQL y renderiza:

1. **Tabla de visualización (`Table: Media distribution by MIME type`)**: Distribución tabular que muestra `mime_type` y `count` de registros.
2. **Gráfico radial Vega-Lite (`Graph: Media distribution by MIME type`)**: Gráfico circular/radial interactivo que agrupa los recuentos por `mime_type`.

#### Valor de retorno

Devuelve un arreglo de arreglos asociativos con la siguiente estructura:

```elscript
[
  {
    mime_type: 'image/jpeg',
    count: 245
  },
  {
    mime_type: 'image/png',
    count: 84
  },
  {
    mime_type: 'application/pdf',
    count: 19
  }
]
```

#### Ejemplo

```elscript
/* Generar estadísticas y visualizaciones de distribución de tipos MIME de archivos adjuntos */
Media.stats()
```

<VegaLite spec={{
  "$schema": "https://vega.github.io/schema/vega/v6.json",
  "description": "Media distribution graph",
  "autosize": {
    "type": "fit-x",
    "contains": "padding"
  },
  "background": "white",
  "padding": 20,
  "height": 300,
  "title": {
    "anchor": "start",
    "text": "Media Distribution by MIME Type"
  },
  "style": "view",
  "data": [
    {
      "name": "source_0",
      "values": [
        {
          "mime_type": "image/jpeg",
          "count": 3
        },
        {
          "mime_type": "image/png",
          "count": 1
        },
        {
          "mime_type": "image/webp",
          "count": 1
        }
      ]
    },
    {
      "name": "data_0",
      "source": "source_0",
      "transform": [
        {
          "type": "stack",
          "groupby": [],
          "field": "count",
          "sort": {
            "field": [
              "mime_type"
            ],
            "order": [
              "ascending"
            ]
          },
          "as": [
            "count_start",
            "count_end"
          ],
          "offset": "zero"
        },
        {
          "type": "filter",
          "expr": "isValid(datum[\"count\"]) && isFinite(+datum[\"count\"])"
        }
      ]
    }
  ],
  "signals": [
    {
      "name": "width",
      "init": "isFinite(containerSize()[0]) ? containerSize()[0] : 300",
      "on": [
        {
          "update": "isFinite(containerSize()[0]) ? containerSize()[0] : 300",
          "events": "window:resize"
        }
      ]
    }
  ],
  "marks": [
    {
      "name": "marks",
      "type": "arc",
      "style": [
        "arc"
      ],
      "from": {
        "data": "data_0"
      },
      "encode": {
        "update": {
          "tooltip": {
            "signal": "{\"count\": format(datum[\"count\"], \"\"), \"mime_type\": isValid(datum[\"mime_type\"]) ? isArray(datum[\"mime_type\"]) ? join(datum[\"mime_type\"], '\\n') : datum[\"mime_type\"] : \"\"+datum[\"mime_type\"]}"
          },
          "fill": {
            "scale": "color",
            "field": "mime_type"
          },
          "description": {
            "signal": "\"count: \" + (format(datum[\"count\"], \"\")) + \"; mime_type: \" + (isValid(datum[\"mime_type\"]) ? isArray(datum[\"mime_type\"]) ? join(datum[\"mime_type\"], ' ') : datum[\"mime_type\"] : \"\"+datum[\"mime_type\"])"
          },
          "x": {
            "signal": "width",
            "mult": 0.5
          },
          "y": {
            "signal": "height",
            "mult": 0.5
          },
          "outerRadius": {
            "signal": "min(width,height)/2"
          },
          "innerRadius": {
            "value": 0
          },
          "startAngle": {
            "scale": "theta",
            "field": "count_end"
          },
          "endAngle": {
            "scale": "theta",
            "field": "count_start"
          }
        }
      }
    }
  ],
  "scales": [
    {
      "name": "theta",
      "type": "linear",
      "domain": {
        "data": "data_0",
        "fields": [
          "count_start",
          "count_end"
        ]
      },
      "range": [
        0,
        6.283185307179586
      ],
      "zero": true
    },
    {
      "name": "color",
      "type": "ordinal",
      "domain": {
        "data": "data_0",
        "field": "mime_type",
        "sort": true
      },
      "range": "category"
    }
  ],
  "legends": [
    {
      "title": "MIME Type",
      "fill": "color",
      "symbolType": "circle"
    }
  ],
  "config": {
    "axis": {
      "titleFont": "Roboto Slab, serif",
      "titleFontSize": 13,
      "titleFontWeight": "bold",
      "labelFont": "Cascadia Mono, monospace",
      "labelFontSize": 12,
      "gridColor": "#E5E5E5",
      "tickColor": "#888888",
      "domainColor": "#888888"
    },
    "axisBottom": {
      "labelAngle": -45
    },
    "legend": {
      "titleFont": "Roboto Slab, serif",
      "titleFontSize": 13,
      "titleFontWeight": "bold",
      "labelFont": "Cascadia Mono, monospace",
      "labelFontSize": 12
    },
    "style": {
      "guide-label": {
        "font": "system-ui, -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif"
      },
      "guide-title": {
        "font": "system-ui, -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif"
      },
      "group-title": {
        "font": "Roboto Slab, serif",
        "fontSize": 16,
        "fontWeight": "bold",
        "fill": "#1e293b"
      },
      "group-subtitle": {
        "font": "system-ui, -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif"
      },
      "cell": {
        "stroke": "transparent"
      },
      "text": {
        "font": "system-ui, -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif"
      }
    }
  }
}} />

---

## Propiedades del modelo Attachment

Las instancias del modelo `Attachment` representan un archivo adjunto individual de WordPress y sus metadatos asociados.

### Attachment.ID

El identificador numérico de clave primaria del archivo adjunto en la tabla `wp_posts`.

```elscriptsignature
Attachment.ID: ?int
```

* **Tipo**: `?int` (Solo lectura)
* **Descripción**: ID de post en base de datos mapeado desde `WP_Post::$ID`.

#### Ejemplo

```elscript
Media.get(42).ID
```

---

### Attachment.title

El título del archivo adjunto.

```elscriptsignature
Attachment.title: string
```

* **Tipo**: `string` (Solo lectura)
* **Descripción**: Valor de cadena mapeado desde `WP_Post::$post_title`.

#### Ejemplo

```elscript
Media.get(42).title
```

---

### Attachment.caption

La leyenda del archivo adjunto.

```elscriptsignature
Attachment.caption: string
```

* **Tipo**: `string` (Solo lectura)
* **Descripción**: Valor de cadena mapeado desde `WP_Post::$post_excerpt`.

#### Ejemplo

```elscript
Media.get(42).caption
```

---

### Attachment.description

El cuerpo de descripción del archivo adjunto.

```elscriptsignature
Attachment.description: string
```

* **Tipo**: `string` (Solo lectura)
* **Descripción**: Valor de cadena mapeado desde `WP_Post::$post_content`.

#### Ejemplo

```elscript
Media.get(42).description
```

---

### Attachment.alt

El texto alternativo para imágenes.

```elscriptsignature
Attachment.alt: string
```

* **Tipo**: `string` (Solo lectura)
* **Descripción**: Valor recuperado del metadato de post `_wp_attachment_image_alt`.

#### Ejemplo

```elscript
Media.get(42).alt
```

---

### Attachment.mime_type

El tipo MIME del archivo adjunto.

```elscriptsignature
Attachment.mime_type: string
```

* **Tipo**: `string` (Solo lectura)
* **Descripción**: Valor de cadena (por ejemplo, `'image/jpeg'`, `'image/png'`, `'application/pdf'`) mapeado desde `WP_Post::$post_mime_type`.

#### Ejemplo

```elscript
Media.get(42).mime_type
```

---

### Attachment.url

La URL pública completa al archivo original.

```elscriptsignature
Attachment.url: string
```

* **Tipo**: `string` (Solo lectura)
* **Descripción**: Cadena URL resuelta mediante `wp_get_attachment_url()`.

#### Ejemplo

```elscript
Media.get(42).url
```

---

### Attachment.file_path

La ruta absoluta del sistema de archivos al archivo original en el servidor.

```elscriptsignature
Attachment.file_path: string
```

* **Tipo**: `string` (Solo lectura)
* **Descripción**: Cadena de ruta absoluta resuelta a través de `get_attached_file()`.

#### Ejemplo

```elscript
Media.get(42).file_path
```

---

### Attachment.filesize

El tamaño físico en bytes del archivo original en disco.

```elscriptsignature
Attachment.filesize: int
```

* **Tipo**: `int` (Solo lectura)
* **Descripción**: Recuento entero de bytes calculado a partir de `filesize($file_path)`. Devuelve `0` si el archivo no existe en el disco.

#### Ejemplo

```elscript
Media.get(42).filesize
```

---

### Attachment.filesize_human

El tamaño de archivo formateado y legible para humanos del archivo original.

```elscriptsignature
Attachment.filesize_human: string
```

* **Tipo**: `string` (Solo lectura)
* **Descripción**: Cadena formateada con el sufijo de unidad binaria apropiado (por ejemplo, `'1.24 MB'`, `'450.5 KB'`, `'0 B'`).

#### Ejemplo

```elscript
Media.get(42).filesize_human
```

---

### Attachment.dimensions

Las dimensiones de ancho y alto del archivo original.

```elscriptsignature
Attachment.dimensions: array
```

* **Tipo**: `array` (Solo lectura)
* **Descripción**: Arreglo asociativo con claves enteras `width` y `height` extraídas de `_wp_attachment_metadata`. Arreglo vacío `[]` si no está disponible o no es una imagen.

#### Ejemplo

```elscript
Media.get(42).dimensions
```

---

### Attachment.sizes

Un diccionario que asigna cada nombre de tamaño de imagen secundario registrado a sus metadatos y rutas completos.

```elscriptsignature
Attachment.sizes: array
```

* **Tipo**: `array` (Solo lectura)
* **Descripción**: Arreglo asociativo donde cada clave es un slug de tamaño (por ejemplo, `'thumbnail'`, `'medium'`, `'medium_large'`, `'large'`) que apunta a un objeto con los siguientes campos:

| Campo | Tipo | Descripción |
| :--- | :--- | :--- |
| `file` | `string` | Nombre de archivo del tamaño secundario generado (por ejemplo, `'banner-300x200.jpg'`). |
| `width` | `int` | Ancho del tamaño secundario en píxeles. |
| `height` | `int` | Alto del tamaño secundario en píxeles. |
| `mime_type` | `string` | Tipo MIME del archivo de tamaño secundario. |
| `url` | `string` | URL pública completa a la imagen de tamaño secundario. |
| `path` | `string` | Ruta absoluta del sistema de archivos del servidor al archivo de tamaño secundario. |
| `filesize` | `int` | Tamaño del archivo en bytes (si lo proporcionan los metadatos de WordPress). |

#### Ejemplo

```elscript
Media.get(42).sizes
```

---

### Attachment.date

La marca de tiempo de creación del archivo adjunto.

```elscriptsignature
Attachment.date: string
```

* **Tipo**: `string` (Solo lectura)
* **Descripción**: Cadena de marca de tiempo MySQL formateada como `YYYY-MM-DD HH:MM:SS`, mapeada desde `WP_Post::$post_date`.

#### Ejemplo

```elscript
Media.get(42).date
```

---

### Attachment.modified

La marca de tiempo de última modificación del archivo adjunto.

```elscriptsignature
Attachment.modified: string
```

* **Tipo**: `string` (Solo lectura)
* **Descripción**: Cadena de marca de tiempo MySQL formateada como `YYYY-MM-DD HH:MM:SS`, mapeada desde `WP_Post::$post_modified`.

#### Ejemplo

```elscript
Media.get(42).modified
```

---

### Attachment.author_id

El ID de base de datos del usuario que subió el archivo adjunto.

```elscriptsignature
Attachment.author_id: int
```

* **Tipo**: `int` (Solo lectura)
* **Descripción**: ID numérico del usuario autor mapeado desde `WP_Post::$post_author`.

#### Ejemplo

```elscript
Media.get(42).author_id
```

---

### Attachment.parent_id

El ID de base de datos de la publicación superior a la que está vinculado el archivo adjunto.

```elscriptsignature
Attachment.parent_id: int
```

* **Tipo**: `int` (Solo lectura)
* **Descripción**: ID numérico de la publicación principal mapeado desde `WP_Post::$post_parent`. Devuelve `0` si el archivo adjunto no está asignado o es huérfano.

#### Ejemplo

```elscript
Media.get(42).parent_id
```

---

### Attachment.meta

La instancia delimitada del repositorio de metadatos `PostMeta` asociada con este archivo adjunto.

```elscriptsignature
Attachment.meta: ?PostMeta
```

* **Tipo**: `?PostMeta` (Solo lectura)
* **Descripción**: Proporciona operaciones CRUD de metadatos (`get`, `set`, `delete`, `all`) y filtrado con constructor de consultas delimitado al ID de base de datos de este archivo adjunto.

#### Ejemplos

```elscript
/* Recuperar metadatos de ruta de archivo adjunto */
Media.get(42).meta.get('_wp_attached_file')
```

```elscript
/* Recuperar todos los metadatos registrados para el archivo adjunto */
Media.get(42).meta.all()
```

---

## Métodos del modelo Attachment

### Attachment.get_url()

Recupera la URL pública completa para un tamaño de imagen registrado específico o el archivo original.

```elscriptsignature
Attachment.get_url(string:size = 'full'): string
```

#### Parámetros

* **`size`** (`string`, _opcional_): Slug del tamaño de imagen registrado (por ejemplo, `'thumbnail'`, `'medium'`, `'large'`, `'full'`). El valor predeterminado es `'full'`.

#### Valor de retorno

Devuelve la cadena URL resuelta a través de `wp_get_attachment_image_src()` o el registro interno de tamaños del modelo. Devuelve la URL del archivo original si es `'full'` o está vacío.

#### Ejemplos

```elscript
/* Obtener URL del archivo original/completo */
Media.get(42).get_url()
```

```elscript
/* Obtener URL de miniatura mediana */
Media.get(42).get_url('medium')
```

```elscript
/* Obtener URL de miniatura personalizada */
Media.get(42).get_url('thumbnail')
```

---

### Attachment.get_path()

Recupera la ruta absoluta del sistema de archivos en el servidor para un tamaño de imagen registrado específico o el archivo original.

```elscriptsignature
Attachment.get_path(string:size = 'full'): string
```

#### Parámetros

* **`size`** (`string`, _opcional_): Slug del tamaño de imagen registrado (por ejemplo, `'thumbnail'`, `'medium'`, `'large'`, `'full'`). El valor predeterminado es `'full'`.

#### Valor de retorno

Devuelve la cadena de ruta absoluta del sistema de archivos al archivo de tamaño especificado. Devuelve el `file_path` original si es `'full'` o está vacío.

#### Ejemplos

```elscript
/* Obtener ruta absoluta del sistema de archivos al archivo original */
Media.get(42).get_path()
```

```elscript
/* Obtener ruta absoluta del sistema de archivos a la miniatura mediana */
Media.get(42).get_path('medium')
```

---

### Attachment.get_dimensions()

Recupera las dimensiones en píxeles (`width` y `height`) para un tamaño de imagen registrado específico o la imagen original.

```elscriptsignature
Attachment.get_dimensions(string:size = 'full'): array
```

#### Parámetros

* **`size`** (`string`, _opcional_): Slug del tamaño de imagen registrado (por ejemplo, `'thumbnail'`, `'medium'`, `'large'`, `'full'`). El valor predeterminado es `'full'`.

#### Valor de retorno

Devuelve un arreglo asociativo con claves enteras `width` y `height` (por ejemplo, `{ width: 300, height: 200 }`).

#### Ejemplos

```elscript
/* Obtener dimensiones de la imagen original */
Media.get(42).get_dimensions()
```

```elscript
/* Obtener dimensiones de la miniatura mediana */
Media.get(42).get_dimensions('medium')
```

---

### Attachment.delete()

Elimina este archivo adjunto de la base de datos y borra todos los archivos físicos asociados del sistema de archivos de forma definitiva.

```elscriptsignature
Attachment.delete(bool:force = false): bool
```

#### Parámetros

* **`force`** (`bool`, _opcional_): Cuando es `true`, omite la papelera y elimina el registro y los archivos de forma definitiva. El valor predeterminado es `false`.

#### Valor de retorno

Devuelve `true` tras una eliminación exitosa, o `false` en caso de error.

:::warning Protección contra Escritura
Eliminar un archivo adjunto modifica tanto la base de datos como el sistema de archivos del servidor.

Lanza una excepción si `EXPRESSION_LAB_DATABASE_READONLY` o `EXPRESSION_LAB_FILESYSTEM_READONLY` están establecidos en `true`. Ambas constantes deben establecerse en `false` en `wp-config.php` para permitir la eliminación.
:::

#### Ejemplos

```elscript
/* Enviar archivo adjunto a la papelera */
Media.get(42).delete()
```

```elscript
/* Eliminar de forma definitiva el archivo adjunto y los archivos físicos */
Media.get(42).delete(true)
```

---

## Gestión de metadatos de archivos adjuntos

Las operaciones de metadatos de archivos adjuntos se acceden a través de la propiedad `meta` en una instancia del modelo `Attachment` (por ejemplo, `Media.get(42).meta`).

### PostMeta.get()

Recupera un valor de metadatos para el archivo adjunto utilizando `get_post_meta()`, o ejecuta las condiciones acumuladas del constructor de consultas en `wp_postmeta` cuando se llama sin argumentos.

```elscriptsignature
PostMeta.get(
  ?string:key = null,
  bool:single = true
): mixed
```

#### Parámetros

* **`key`** (`?string`, _opcional_): El nombre de la clave de metadatos. Si se omite o es `null`, ejecuta las condiciones acumuladas del constructor de consultas en `wp_postmeta` para este archivo adjunto. El valor predeterminado es `null`.
* **`single`** (`bool`, _opcional_): Si se devuelve un valor único (`true`) o un arreglo de valores (`false`). El valor predeterminado es `true`.

#### Valor de retorno

Devuelve el valor de metadatos almacenado, o un arreglo de resultados del constructor de consultas cuando `$key` es `null`.

#### Ejemplos

```elscript
/* Recuperar un valor único de metadatos */
Media.get(42).meta.get('_wp_attachment_image_alt')
```

```elscript
/* Recuperar todos los valores para una clave meta no única */
Media.get(42).meta.get('custom_tags', false)
```

---

### PostMeta.all()

Recupera todas las entradas de metadatos para el archivo adjunto desde `wp_postmeta`. Los valores se procesan mediante `maybe_unserialize()`.

```elscriptsignature
PostMeta.all(): array
```

#### Valor de retorno

Devuelve un arreglo asociativo que asigna cada clave meta a su valor (o arreglo de valores para claves con múltiples entradas).

#### Ejemplo

```elscript
/* Inspeccionar todos los metadatos registrados para un archivo adjunto */
Media.get(42).meta.all()
```

---

### PostMeta.set()

Establece o actualiza una entrada de metadatos para el archivo adjunto.

```elscriptsignature
PostMeta.set(
  string:key,
  mixed:value
): bool
```

#### Parámetros

* **`key`** (`string`, _requerido_): El nombre de la clave de metadatos.
* **`value`** (`mixed`, _requerido_): El valor a almacenar.

#### Valor de retorno

Devuelve `true` si la base de datos se actualizó, `false` en caso contrario.

:::warning Protección contra Escritura
Lanza una excepción si `EXPRESSION_LAB_DATABASE_READONLY` está establecido en `true`.
:::

#### Ejemplo

```elscript
/* Actualizar metadatos personalizados */
Media.get(42).meta.set('license_type', 'CC-BY-4.0')
```

---

### PostMeta.delete()

Elimina una entrada de metadatos para el archivo adjunto de `wp_postmeta`.

```elscriptsignature
PostMeta.delete(string:key): bool
```

#### Parámetros

* **`key`** (`string`, _requerido_): El nombre de la clave de metadatos a eliminar.

#### Valor de retorno

Devuelve `true` si se eliminó con éxito, `false` en caso contrario.

:::warning Protección contra Escritura
Lanza una excepción si `EXPRESSION_LAB_DATABASE_READONLY` está establecido en `true`.
:::

#### Ejemplo

```elscript
/* Eliminar entrada de metadatos */
Media.get(42).meta.delete('temp_import_hash')
```

---

## Constructor de consultas en metadatos de archivos adjuntos

El modelo `PostMeta` implementa el trait `QueryBuilder`. Todas las operaciones del constructor de consultas encadenadas en `Attachment.meta` se delimitan al archivo adjunto principal mediante `post_id = Attachment.ID`.

Los registros de metadatos replicados se colocan en una tabla SQLite en memoria y se descartan al vaciar el búfer.

### PostMeta.where()

Agrega una condición `WHERE` combinada con lógica `AND` dentro del grupo de condiciones actual en `wp_postmeta`.

```elscriptsignature
PostMeta.where(
  string:column,
  mixed:operator_or_value,
  mixed:value = null
): PostMeta
```

#### Firmas de llamada

* **Igualdad** (Dos argumentos): `attachment.meta.where('column', 'value')`
* **Condición IN** (Dos argumentos con arreglo): `attachment.meta.where('column', ['val1', 'val2'])`
* **Operador de comparación** (Tres argumentos): `attachment.meta.where('column', 'operator', 'value')`

#### Operadores de comparación compatibles

| Operador | Descripción | Ejemplo |
| :--- | :--- | :--- |
| `'='` | Igualdad exacta (predeterminado) | `Media.get(42).meta.where('meta_key', '=', '_wp_attached_file').get()` |
| `'>'` | Mayor que | `Media.get(42).meta.where('meta_id', '>', 100).get()` |
| `'>='` | Mayor o igual que | `Media.get(42).meta.where('meta_id', '>=', 100).get()` |
| `'<'` | Menor que | `Media.get(42).meta.where('meta_id', '<', 500).get()` |
| `'<='` | Menor o igual que | `Media.get(42).meta.where('meta_id', '<=', 500).get()` |
| `'!='`, `'<>'` | No igual | `Media.get(42).meta.where('meta_key', '!=', '_wp_attached_file').get()` |
| `'like'` | Coincidencia de patrones SQL LIKE | `Media.get(42).meta.where('meta_key', 'like', '_wp_%').get()` |
| `'not like'` | Coincidencia de patrones SQL LIKE negada | `Media.get(42).meta.where('meta_key', 'not like', '_wp_%').get()` |
| `'in'` | Pertenencia a conjuntos (el valor debe ser un arreglo) | `Media.get(42).meta.where('meta_key', 'in', ['_wp_attached_file', '_wp_attachment_metadata']).get()` |
| `'not in'` | Pertenencia a conjuntos negada (el valor debe ser un arreglo) | `Media.get(42).meta.where('meta_key', 'not in', ['_wp_attached_file', '_edit_lock']).get()` |
| `'between'` | Comprobación de rango (el valor debe ser un arreglo de dos elementos `[min, max]`) | `Media.get(42).meta.where('meta_id', 'between', [100, 200]).get()` |

#### Ejemplos

```elscript
/* Consultar claves de metadatos que comiencen con _wp_ */
Media.get(42).meta.where('meta_key', 'like', '_wp_%').get()
```

```elscript
/* Consultar claves de metadatos específicas */
Media.get(42).meta.where('meta_key', 'in', ['_wp_attached_file', '_wp_attachment_metadata']).get()
```

---

### PostMeta.or_where()

Agrega una condición `WHERE` que inicia un nuevo grupo de condiciones combinado con los grupos anteriores mediante lógica `OR`. Las condiciones añadidas dentro de cada grupo usando `where()` se combinan mediante `AND`. La delimitación a `post_id = Attachment.ID` se conserva en todos los grupos.

```elscriptsignature
PostMeta.or_where(
  string:column,
  mixed:operator_or_value,
  mixed:value = null
): PostMeta
```

#### Ejemplo

```elscript
/* Consultar claves de metadatos que coincidan con cualquiera de dos patrones */
Media.get(42).meta.where('meta_key', '_wp_attached_file')
  .or_where('meta_key', '_wp_attachment_metadata')
  .get()
```

---

### PostMeta.order_by()

Especifica el ordenamiento de columnas para los resultados de consultas de metadatos replicados.

```elscriptsignature
PostMeta.order_by(
  string:column,
  string:direction = 'ASC'
): PostMeta
```

#### Parámetros

* **`column`** (`string`, _requerido_): Nombre de la columna por la cual ordenar (`'meta_id'`, `'meta_key'`, `'meta_value'`).
* **`direction`** (`string`, _opcional_): Dirección de ordenamiento (`'ASC'` o `'DESC'`). El valor predeterminado es `'ASC'`.

#### Ejemplo

```elscript
/* Ordenar filas de metadatos por meta_id descendente */
Media.get(42).meta.where('meta_key', 'like', '_wp_%')
  .order_by('meta_id', 'DESC')
  .get()
```

---

### PostMeta.limit()

Establece el número máximo de filas de metadatos a devolver.

```elscriptsignature
PostMeta.limit(int:limit): PostMeta
```

#### Ejemplo

```elscript
/* Recuperar las primeras 5 filas de metadatos */
Media.get(42).meta.order_by('meta_id', 'ASC')
  .limit(5)
  .get()
```

---

### PostMeta.offset()

Establece el número de filas a omitir para la paginación a través de las filas de metadatos.

```elscriptsignature
PostMeta.offset(int:offset): PostMeta
```

#### Ejemplo

```elscript
/* Paginar filas de metadatos */
Media.get(42).meta.order_by('meta_id', 'ASC')
  .offset(5)
  .limit(5)
  .get()
```

---

### PostMeta.query()

Replica la tabla `wp_postmeta` en SQLite utilizando las condiciones acumuladas (incluyendo la restricción de `post_id` principal) y opciones, luego ejecuta una consulta SQL `SELECT` arbitraria contra la base de datos SQLite en memoria. Reinicia el estado del constructor tras la ejecución.

```elscriptsignature
PostMeta.query(string:sql): array
```

#### Ejemplo

```elscript
/* Ejecutar consulta SQL directa contra metadatos de archivo adjunto replicados */
Media.get(42).meta.where('meta_key', 'like', '_%')
  .query('SELECT meta_id, meta_key, meta_value FROM wp_postmeta ORDER BY meta_id ASC')
```

:::info Extensión SQLite3 Requerida
La extensión de PHP SQLite3 **debe** estar instalada y habilitada en el servidor para que `PostMeta.query()` funcione.
:::

---

### PostMeta.count()

Cuenta registros de metadatos coincidentes para el archivo adjunto en MySQL sin almacenar filas en memoria ni inicializar una tabla SQLite. Aplica la delimitación de `post_id`. Reinicia el estado del constructor tras la ejecución.

```elscriptsignature
PostMeta.count(): int
```

#### Ejemplos

```elscript
/* Contar todas las filas de metadatos para un archivo adjunto */
Media.get(42).meta.count()
```

```elscript
/* Contar claves de metadatos ocultas/privadas */
Media.get(42).meta.where('meta_key', 'like', '_%').count()
```

---

### PostMeta.to_sql()

Compila y devuelve la cadena de consulta SQL para la consulta de metadatos del archivo adjunto sin ejecutarla. Aplica la delimitación `post_id = Attachment.ID`. Reinicia el estado del constructor tras la compilación.

```elscriptsignature
PostMeta.to_sql(bool:is_count = false): string
```

#### Parámetros

* **`is_count`** (`bool`, _opcional_): Cuando es `true`, compila una consulta `SELECT COUNT(*)` en lugar de una proyección de columnas. El valor predeterminado es `false`.

#### Ejemplos

```elscript
/* Previsualizar SQL de consulta de metadatos */
Media.get(42).meta.where('meta_key', 'like', '_wp_%').order_by('meta_id', 'DESC').to_sql()
```

```elscript
/* Previsualizar SQL de conteo de metadatos */
Media.get(42).meta.where('meta_key', 'like', '_wp_%').to_sql(true)
```
