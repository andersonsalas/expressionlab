---
id: posts
title: Publicaciones
sidebar_position: 5
---

# Publicaciones

Permite consultar, inspeccionar y gestionar entradas, páginas y tipos de contenido personalizados (*custom post types / CPT*) en WordPress mediante constructores fluidos de consulta, análisis de metadatos e inspección taxonómica.

La arquitectura de gestión de publicaciones consta de tres componentes interconectados:

1. **Servicio Posts (`Posts`)**: El punto de entrada principal para búsquedas de publicaciones por ID numérico, slug o URL/enlace permanente, encadenamiento del constructor de consultas, descubrimiento de tipos y estados de publicaciones, inspección taxonómica y estadísticas visuales.
2. **Modelo Post (`Post`)**: Una entidad que representa una publicación, página o tipo de contenido personalizado individual de WordPress, exponiendo propiedades centrales normalizadas (`ID`, `title`, `content`, `excerpt`, `status`, `type`, `slug`, `date`, `modified`, `author_id`, `parent_id`, `comment_count`), resolución de enlaces permanentes y consultas de términos de taxonomía.
3. **Modelo PostMeta (`PostMeta`)**: Un repositorio de metadatos accesible mediante `post.meta` que proporciona operaciones CRUD de metadatos e integración del constructor de consultas vinculada a la publicación principal.

---

## Acceso y recuperación de publicaciones

### Posts.get()

Recupera una única instancia del modelo `Post` por identificador (ID numérico, slug de publicación o URL/enlace permanente), o ejecuta las condiciones acumuladas del constructor de consultas cuando se llama sin argumentos.

```elscriptsignature
Posts.get(
  int|string|null:identifier = null
): Post|array|null
```

#### Modos de resolución

* **Por ID numérico** (`int` o cadena numérica):
  Consulta a través de `get_post(absint($identifier))`. Devuelve una instancia del modelo `Post`, o `null` si la publicación no existe.
* **Por URL o enlace permanente** (`string` que coincida con formatos de URL, que comience con `http://` o `https://`, o que contenga separadores de ruta `/`):
  1. Resuelve mediante `url_to_postid($identifier)` o `url_to_postid(home_url($identifier))`.
  2. Si no se resuelve, extrae la ruta a través de `wp_parse_url($identifier, PHP_URL_PATH)` y consulta `get_page_by_path()` en todos los tipos de publicaciones registrados.
  3. Si aún no se resuelve, realiza una consulta de respaldo mediante `get_posts()` buscando coincidencias con el nombre base de la ruta URL en todos los tipos y estados de publicaciones.
  4. Devuelve una instancia del modelo `Post`, o `null` si no se encuentra ninguna coincidencia.
* **Por slug / Nombre de publicación** (`string`):
  Consulta mediante `get_posts()` con `name = sanitize_title($identifier)` en todos los tipos de publicaciones (`'any'`) y estados (`'any'`). Devuelve una instancia del modelo `Post`, o `null` si ninguna publicación coincide con el slug.
* **Sin argumentos** (`null`):
  Ejecuta las condiciones acumuladas del `QueryBuilder` contra `wp_posts`, replica las filas coincidentes en una tabla SQLite en memoria, vacía el búfer y devuelve los resultados como un arreglo de objetos de fila.

#### Ejemplos

```elscript
/* Buscar publicación por ID numérico */
Posts.get(42)
```

```elscript
/* Buscar publicación por slug */
Posts.get('hello-world')
```

```elscript
/* Buscar publicación por URL completa */
Posts.get('https://example.com/2026/08/sample-post/')
```

```elscript
/* Buscar publicación por ruta relativa */
Posts.get('/sample-page/')
```

---

## Constructor de consultas de publicaciones (query builder)

El servicio `Posts` incorpora métodos encadenables de constructor de consultas. Las condiciones se acumulan en grupos y se compilan en SQL parametrizado cuando se ejecutan a través de `get()` o `query()`. Los datos replicados se colocan en una tabla SQLite en memoria y se descartan al vaciar el búfer.

### Posts.where()

Agrega una condición `WHERE` combinada con lógica `AND` dentro del grupo de condiciones actual.

```elscriptsignature
Posts.where(
  string:column,
  mixed:operator_or_value,
  mixed:value = null
): Posts
```

#### Firmas de llamada

* **Igualdad** (Dos argumentos): `Posts.where('column', 'value')`
* **Condición IN** (Dos argumentos con arreglo): `Posts.where('column', ['val1', 'val2'])`
* **Operador de comparación** (Tres argumentos): `Posts.where('column', 'operator', 'value')`

#### Operadores de comparación compatibles

La forma de tres argumentos `Posts.where(column, operator, value)` acepta operadores de comparación estándar de SQL como cadenas:

| Operador | Descripción | Ejemplo |
| :--- | :--- | :--- |
| `'='` | Igualdad exacta (predeterminado) | `Posts.where('post_status', '=', 'publish').get()`, o `Posts.where('post_status', 'publish').get()` |
| `'>'` | Mayor que | `Posts.where('ID', '>', 100).get()` |
| `'>='` | Mayor o igual que | `Posts.where('ID', '>=', 100).get()` |
| `'<'` | Menor que | `Posts.where('ID', '<', 50).get()` |
| `'<='` | Menor o igual que | `Posts.where('ID', '<=', 50).get()` |
| `'!='`, `'<>'` | No igual | `Posts.where('post_status', '!=', 'trash').get()` |
| `'like'` | Coincidencia de patrones SQL LIKE | `Posts.where('post_title', 'like', '%announcement%').get()` |
| `'not like'` | Coincidencia de patrones SQL LIKE negada | `Posts.where('post_title', 'not like', '%draft%').get()` |
| `'in'` | Pertenencia a conjuntos (el valor debe ser un arreglo) | `Posts.where('post_type', 'in', ['post', 'page']).get()` |
| `'not in'` | Pertenencia a conjuntos negada (el valor debe ser un arreglo) | `Posts.where('post_status', 'not in', ['trash', 'auto-draft']).get()` |
| `'between'` | Comprobación de rango (el valor debe ser un arreglo de dos elementos `[min, max]`) | `Posts.where('post_date', 'between', ['2026-01-01', '2026-12-31']).get()` |

:::note Sintaxis de QueryBuilder vs Database.mirror
Los métodos del `QueryBuilder` (`where()`, `or_where()`) aceptan cadenas de operadores SQL estándar (`'>'`, `'like'`, `'between'`) y las compilan en los objetos de condición recursivos con prefijo `$` requeridos por `Database.mirror()`.

Al invocar `Database.mirror(table, where, options)`, se debe emplear la sintaxis de objetos declarativos documentada en [Base de Datos](./database) (por ejemplo, `{ ID: { '$gt': 100 } }`).
:::

#### Ejemplos

```elscript
/* Consultar páginas publicadas */
Posts.where('post_type', 'page').where('post_status', 'publish').get()
```

```elscript
/* Consultar publicaciones creadas por un usuario con comparación de ID */
Posts.where('post_author', 1).where('ID', '>', 50).get()
```

```elscript
/* Consultar publicaciones que coincidan con un patrón de título */
Posts.where('post_title', 'like', '%Release%').get()
```

---

### Posts.or_where()

Agrega una condición `WHERE` que inicia un nuevo grupo de condiciones combinado con los grupos anteriores mediante lógica `OR`. Las condiciones añadidas dentro de cada grupo usando `where()` se combinan mediante `AND`.

```elscriptsignature
Posts.or_where(
  string:column,
  mixed:operator_or_value,
  mixed:value = null
): Posts
```

#### Ejemplo

```elscript
/* Recuperar publicaciones que sean entradas publicadas o páginas pendientes de revisión */
Posts.where('post_type', 'post')
  .where('post_status', 'publish')
  .or_where('post_type', 'page')
  .where('post_status', 'pending')
  .get()
```

---

### Posts.order_by()

Especifica el ordenamiento de columnas para los resultados de la consulta. Se puede encadenar varias veces para aplicar ordenamiento multi-columna.

```elscriptsignature
Posts.order_by(
  string:column,
  string:direction = 'ASC'
): Posts
```

#### Parámetros

* **`column`** (`string`, _requerido_): Nombre de la columna por la cual ordenar (por ejemplo, `'ID'`, `'post_date'`, `'post_title'`, `'comment_count'`).
* **`direction`** (`string`, _opcional_): Dirección de ordenamiento (`'ASC'` o `'DESC'`). El valor predeterminado es `'ASC'`.

#### Ejemplo

```elscript
/* Ordenar publicaciones publicadas por fecha de creación descendente */
Posts.where('post_status', 'publish')
  .order_by('post_date', 'DESC')
  .get()
```

```elscript
/* Ordenamiento multi-columna: ordenar por tipo de post ascendente, luego por fecha descendente */
Posts.where('post_status', 'publish')
  .order_by('post_type', 'ASC')
  .order_by('post_date', 'DESC')
  .get()
```

---

### Posts.limit()

Establece el número máximo de registros de publicaciones a devolver.

```elscriptsignature
Posts.limit(int:limit): Posts
```

#### Ejemplo

```elscript
/* Recuperar las 5 publicaciones publicadas más recientes */
Posts.where('post_status', 'publish')
  .order_by('post_date', 'DESC')
  .limit(5)
  .get()
```

---

### Posts.offset()

Establece el número de filas a omitir para la paginación.

```elscriptsignature
Posts.offset(int:offset): Posts
```

#### Ejemplo

```elscript
/* Paginar resultados: omitir 20 filas y tomar 10 */
Posts.where('post_status', 'publish')
  .order_by('ID', 'DESC')
  .offset(20)
  .limit(10)
  .get()
```

---

### Posts.query()

Replica la tabla `wp_posts` en SQLite utilizando las condiciones y opciones acumuladas, luego ejecuta una consulta SQL `SELECT` arbitraria contra la base de datos SQLite en memoria. Reinicia el estado del constructor tras la ejecución.

```elscriptsignature
Posts.query(string:sql): array
```

#### Ejemplo

```elscript
/* Ejecutar consulta de agregación arbitraria en la tabla de publicaciones replicada */
Posts.where('post_status', 'publish')
  .query('SELECT post_type, COUNT(*) AS total FROM wp_posts GROUP BY post_type ORDER BY total DESC')
```

:::info Extensión SQLite3 Requerida
La extensión de PHP SQLite3 **debe** estar instalada y habilitada en tu servidor para que `Posts.query()` funcione.
:::

---

### Posts.count()

Cuenta registros coincidentes en la base de datos MySQL sin almacenar filas en memoria ni inicializar una tabla SQLite. Reinicia el estado del constructor tras la ejecución.

```elscriptsignature
Posts.count(): int
```

#### Ejemplos

```elscript
/* Contar publicaciones publicadas */
Posts.where('post_type', 'post').where('post_status', 'publish').count()
```

```elscript
/* Contar páginas publicadas escritas por el usuario 1 */
Posts.where('post_type', 'page').where('post_author', 1).count()
```

---

### Posts.to_sql()

Compila y devuelve la cadena de consulta SQL para las condiciones acumuladas sin ejecutarla. Reinicia el estado del constructor tras la compilación.

```elscriptsignature
Posts.to_sql(bool:is_count = false): string
```

#### Parámetros

* **`is_count`** (`bool`, _opcional_): Cuando es `true`, compila una consulta `SELECT COUNT(*)` en lugar de una proyección de columnas. El valor predeterminado es `false`.

#### Ejemplos

```elscript
/* Previsualizar consulta SQL compilada con ordenamiento y límites */
Posts.where('post_type', 'post')
  .where('post_status', 'publish')
  .order_by('post_date', 'DESC')
  .limit(10)
  .to_sql()
```

```elscript
/* Previsualizar consulta SQL de conteo compilada */
Posts.where('post_type', 'page').where('post_status', 'publish').to_sql(true)
```

---

## Introspección y descubrimiento de metadatos

El servicio `Posts` proporciona utilidades de ayuda para inspeccionar tipos de publicaciones registrados, estados, taxonomías y términos taxonómicos.

### Posts.types()

Recupera una lista de todos los nombres de tipos de publicación registrados en WordPress.

```elscriptsignature
Posts.types(): array
```

#### Valor de retorno

Devuelve un arreglo indexado de cadenas que contiene todos los slugs de tipos de publicación registrados. Por ejemplo:

```elscript
[
  'post',
  'page',
  'attachment',
  'revision',
  'nav_menu_item',
  'custom_css',
  'customize_changeset',
  'oembed_cache',
  'user_request',
  'wp_block',
  'wp_template',
  'wp_template_part',
  'wp_global_styles',
  'wp_navigation'
]
```

#### Ejemplo

```elscript
/* Listar todos los tipos de publicaciones registrados */
Posts.types()
```

---

### Posts.statuses()

Recupera una lista de todos los nombres de estados de publicación registrados en WordPress.

```elscriptsignature
Posts.statuses(): array
```

#### Valor de retorno

Devuelve un arreglo indexado de cadenas que contiene todos los slugs de estados de publicación registrados. Por ejemplo:

```elscript
[
  'publish',
  'future',
  'draft',
  'pending',
  'private',
  'trash',
  'auto-draft',
  'inherit'
]
```

#### Ejemplo

```elscript
/* Listar todos los estados de publicaciones registrados */
Posts.statuses()
```

---

### Posts.taxonomies()

Recupera todas las taxonomías registradas en la instalación de WordPress, o las taxonomías asociadas con un tipo de publicación específico.

```elscriptsignature
Posts.taxonomies(
  string:post_type = ''
): array
```

#### Parámetros

* **`post_type`** (`string`, _opcional_): Cuando se proporciona, devuelve solo los slugs de taxonomía asociados con ese tipo de publicación (a través de `get_object_taxonomies`). Cuando se omite o está vacío, devuelve todas las taxonomías registradas en la instalación (a través de `get_taxonomies`). El valor predeterminado es `''`.

#### Valor de retorno

Devuelve un arreglo indexado de slugs de taxonomía (por ejemplo, `['category', 'post_tag', 'nav_menu', 'link_category', 'post_format']`).

#### Ejemplos

```elscript
/* Recuperar todas las taxonomías registradas */
Posts.taxonomies()
```

```elscript
/* Recuperar taxonomías registradas para el tipo 'post' */
Posts.taxonomies('post')
```

```elscript
/* Recuperar taxonomías registradas para un tipo de contenido personalizado */
Posts.taxonomies('product')
```

---

### Posts.terms()

Recupera términos para una taxonomía determinada y los formatea en un arreglo de objetos.

```elscriptsignature
Posts.terms(
  string:taxonomy,
  array:args = []
): array
```

#### Parámetros

* **`taxonomy`** (`string`, _requerido_): El slug de la taxonomía (por ejemplo, `'category'`, `'post_tag'`).
* **`args`** (`array`, _opcional_): Argumentos pasados a `get_terms()` de WordPress. El valor predeterminado es `[]`. El argumento `hide_empty` se establece por defecto en `false` si no se especifica.

#### Valor de retorno

Devuelve un arreglo indexado de objetos con los siguientes campos:

| Campo | Tipo | Descripción |
| :--- | :--- | :--- |
| `term_id` | `int` | Identificador único del término. |
| `name` | `string` | Nombre público del término. |
| `slug` | `string` | Slug del término sanitizado para URL. |
| `count` | `int` | Número de objetos asociados con el término. |

Si la taxonomía no existe o se produce un error, devuelve un arreglo vacío `[]`.

#### Ejemplos

```elscript
/* Recuperar todas las categorías incluyendo las vacías */
Posts.terms('category')
```

```elscript
/* Recuperar etiquetas ordenadas por recuento descendente */
Posts.terms('post_tag', { orderby: 'count', order: 'DESC', number: 10 })
```

---

## Estadísticas y distribución

### Posts.stats()

Calcula un desglose estadístico de todos los registros en la tabla `wp_posts` agrupados por tipo de post y estado, generando visualizaciones interactivas en la consola de Expression Lab.

```elscriptsignature
Posts.stats(): array
```

#### Qué genera `stats()`

Al ejecutarse en Expression Lab, `Posts.stats()` consulta MySQL y renderiza:

1. **Tabla de visualización**: Distribución tabular que muestra `post_type`, `post_status` y `count` de registros.
2. **Gráfico de barras Vega-Lite (`Graph: Posts distribution by type`)**: Gráfico de barras interactivo que agrupa los recuentos por `post_type` y los codifica por color según `post_status`.

#### Valor de retorno

Devuelve un arreglo de arreglos asociativos con la siguiente estructura:

```elscript
[
  {
    post_type: 'post',
    post_status: 'publish',
    count: 142
  },
  {
    post_type: 'page',
    post_status: 'publish',
    count: 12
  },
  {
    post_type: 'post',
    post_status: 'draft',
    count: 5
  }
]
```

#### Ejemplo

```elscript
/* Generar estadísticas y gráficos de distribución de publicaciones */
Posts.stats()
```

<VegaLite spec={{
  "$schema": "https://vega.github.io/schema/vega-lite/v6.json",
  "title": "Posts Distribution by Type",
  "width": "container",
  "height": 300,
  "data": {
    "values": [
      { "post_type": "post", "post_status": "publish", "count": 142 },
      { "post_type": "post", "post_status": "draft", "count": 15 },
      { "post_type": "post", "post_status": "auto-draft", "count": 3 },
      { "post_type": "attachment", "post_status": "inherit", "count": 85 },
      { "post_type": "page", "post_status": "publish", "count": 22 },
      { "post_type": "page", "post_status": "draft", "count": 4 },
      { "post_type": "wp_navigation", "post_status": "publish", "count": 6 }
    ]
  },
  "mark": { "type": "bar", "cornerRadiusEnd": 4, "tooltip": true },
  "encoding": {
    "x": { "field": "post_type", "type": "nominal", "axis": { "title": "Post Type", "labelAngle": -45 } },
    "y": { "field": "count", "type": "quantitative", "axis": { "title": "Total Count" } },
    "color": { "field": "post_status", "type": "nominal", "scale": { "scheme": "tableau10" }, "legend": { "title": "Status" } }
  }
}} />

---

## Propiedades del modelo Post

Las instancias del modelo `Post` representan un registro individual de publicación, página o tipo de contenido personalizado de WordPress.

### Post.ID

El identificador numérico de clave primaria de la publicación en la tabla `wp_posts`.

```elscriptsignature
Post.ID: ?int
```

* **Tipo**: `?int` (Solo lectura)
* **Descripción**: ID de publicación en base de datos mapeado desde `WP_Post::$ID`.

#### Ejemplo

```elscript
Posts.get(42).ID
```

---

### Post.title

El título de la publicación.

```elscriptsignature
Post.title: string
```

* **Tipo**: `string` (Solo lectura)
* **Descripción**: Valor de cadena mapeado desde `WP_Post::$post_title`.

#### Ejemplo

```elscript
Posts.get(42).title
```

---

### Post.content

El cuerpo de contenido principal de la publicación.

```elscriptsignature
Post.content: string
```

* **Tipo**: `string` (Solo lectura)
* **Descripción**: Valor de cadena mapeado desde `WP_Post::$post_content`.

#### Ejemplo

```elscript
Posts.get(42).content
```

---

### Post.excerpt

El extracto de la publicación.

```elscriptsignature
Post.excerpt: string
```

* **Tipo**: `string` (Solo lectura)
* **Descripción**: Valor de cadena mapeado desde `WP_Post::$post_excerpt`.

#### Ejemplo

```elscript
Posts.get(42).excerpt
```

---

### Post.status

El slug del estado de la publicación.

```elscriptsignature
Post.status: string
```

* **Tipo**: `string` (Solo lectura)
* **Descripción**: Cadena de estado (por ejemplo, `'publish'`, `'draft'`, `'pending'`, `'private'`, `'trash'`) mapeada desde `WP_Post::$post_status`.

#### Ejemplo

```elscript
Posts.get(42).status
```

---

### Post.type

El slug del tipo de publicación.

```elscriptsignature
Post.type: string
```

* **Tipo**: `string` (Solo lectura)
* **Descripción**: Cadena de tipo de publicación (por ejemplo, `'post'`, `'page'`, `'attachment'` o el slug de un tipo de contenido personalizado) mapeada desde `WP_Post::$post_type`.

#### Ejemplo

```elscript
Posts.get(42).type
```

---

### Post.slug

El slug de la publicación sanitizado para URL.

```elscriptsignature
Post.slug: string
```

* **Tipo**: `string` (Solo lectura)
* **Descripción**: Cadena de slug mapeada desde `WP_Post::$post_name`.

#### Ejemplo

```elscript
Posts.get(42).slug
```

---

### Post.date

La marca de tiempo de creación de la publicación.

```elscriptsignature
Post.date: string
```

* **Tipo**: `string` (Solo lectura)
* **Descripción**: Cadena de marca de tiempo MySQL formateada como `YYYY-MM-DD HH:MM:SS`, mapeada desde `WP_Post::$post_date`.

#### Ejemplo

```elscript
Posts.get(42).date
```

---

### Post.modified

La marca de tiempo de última modificación de la publicación.

```elscriptsignature
Post.modified: string
```

* **Tipo**: `string` (Solo lectura)
* **Descripción**: Cadena de marca de tiempo MySQL formateada como `YYYY-MM-DD HH:MM:SS`, mapeada desde `WP_Post::$post_modified`.

#### Ejemplo

```elscript
Posts.get(42).modified
```

---

### Post.author_id

El ID de base de datos del usuario que escribió la publicación.

```elscriptsignature
Post.author_id: int
```

* **Tipo**: `int` (Solo lectura)
* **Descripción**: ID numérico del autor mapeado desde `WP_Post::$post_author`.

#### Ejemplo

```elscript
Posts.get(42).author_id
```

---

### Post.parent_id

El ID de base de datos de la publicación superior, si es jerárquica.

```elscriptsignature
Post.parent_id: int
```

* **Tipo**: `int` (Solo lectura)
* **Descripción**: ID numérico de la publicación superior mapeado desde `WP_Post::$post_parent`. Devuelve `0` si no tiene superior.

#### Ejemplo

```elscript
Posts.get(42).parent_id
```

---

### Post.comment_count

El número de comentarios aprobados en la publicación.

```elscriptsignature
Post.comment_count: int
```

* **Tipo**: `int` (Solo lectura)
* **Descripción**: Recuento entero mapeado desde `WP_Post::$comment_count`.

#### Ejemplo

```elscript
Posts.get(42).comment_count
```

---

### Post.meta

La instancia delimitada del repositorio de metadatos `PostMeta` asociada con esta publicación.

```elscriptsignature
Post.meta: ?PostMeta
```

* **Tipo**: `?PostMeta` (Solo lectura)
* **Descripción**: Proporciona operaciones CRUD de metadatos (`get`, `set`, `delete`, `all`) y filtrado con constructor de consultas delimitado al ID de base de datos de esta publicación.

#### Ejemplos

```elscript
/* Recuperar metadato específico de la publicación */
Posts.get(42).meta.get('_thumbnail_id')
```

```elscript
/* Recuperar todos los metadatos de la publicación */
Posts.get(42).meta.all()
```

---

## Métodos del modelo Post

### Post.get_permalink()

Recupera la URL del enlace permanente completo para la publicación.

```elscriptsignature
Post.get_permalink(): string|false
```

#### Valor de retorno

Devuelve la cadena URL del enlace permanente a través de `get_permalink()`, o `false` si la resolución falla.

#### Ejemplo

```elscript
/* Obtener la URL del enlace permanente de una publicación */
Posts.get(42).get_permalink()
```

---

### Post.get_terms()

Recupera los términos de taxonomía asociados con la publicación.

```elscriptsignature
Post.get_terms(string:taxonomy): array
```

#### Parámetros

* **`taxonomy`** (`string`, _requerido_): El slug de la taxonomía (por ejemplo, `'category'`, `'post_tag'`).

#### Valor de retorno

Devuelve un arreglo de objetos que representan los términos asignados a través de `get_the_terms()`. Cada objeto de término contiene:

| Campo | Tipo | Descripción |
| :--- | :--- | :--- |
| `term_id` | `int` | Identificador único del término. |
| `name` | `string` | Nombre del término. |
| `slug` | `string` | Slug del término. |

Si no hay términos asignados o se produce un error, devuelve un arreglo vacío `[]`.

#### Ejemplos

```elscript
/* Recuperar categorías asignadas a la publicación */
Posts.get(42).get_terms('category')
```

```elscript
/* Recuperar etiquetas asignadas a la publicación */
Posts.get(42).get_terms('post_tag')
```

---

## Gestión de metadatos de publicación

Las operaciones de metadatos de publicación se acceden a través de la propiedad `meta` en una instancia del modelo `Post` (por ejemplo, `Posts.get(42).meta`).

### PostMeta.get()

Recupera un valor de metadatos para la publicación utilizando `get_post_meta()`, o ejecuta las condiciones acumuladas del constructor de consultas en `wp_postmeta` cuando se llama sin argumentos.

```elscriptsignature
PostMeta.get(
  ?string:key = null,
  bool:single = true
): mixed
```

#### Parámetros

* **`key`** (`?string`, _opcional_): El nombre de la clave de metadatos. Si se omite o es `null`, ejecuta las condiciones acumuladas del constructor de consultas en `wp_postmeta` para esta publicación. El valor predeterminado es `null`.
* **`single`** (`bool`, _opcional_): Si se devuelve un valor único (`true`) o un arreglo de valores (`false`). El valor predeterminado es `true`.

#### Valor de retorno

Devuelve el valor de metadatos almacenado, o un arreglo de resultados del constructor de consultas cuando `$key` es `null`.

#### Ejemplos

```elscript
/* Recuperar un valor único de metadatos */
Posts.get(42).meta.get('_edit_lock')
```

```elscript
/* Recuperar todos los valores para una clave meta no única */
Posts.get(42).meta.get('custom_attachment_ids', false)
```

---

### PostMeta.all()

Recupera todas las entradas de metadatos para la publicación desde `wp_postmeta`. Los valores se procesan mediante `maybe_unserialize()`.

```elscriptsignature
PostMeta.all(): array
```

#### Valor de retorno

Devuelve un arreglo asociativo que asigna cada clave meta a su valor (o arreglo de valores para claves con múltiples entradas).

#### Ejemplo

```elscript
/* Inspeccionar todos los metadatos registrados para una publicación */
Posts.get(42).meta.all()
```

---

### PostMeta.set()

Establece o actualiza una entrada de metadatos para la publicación.

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
Posts.get(42).meta.set('reading_time_minutes', 4)
```

---

### PostMeta.delete()

Elimina una entrada de metadatos para la publicación de `wp_postmeta`.

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
Posts.get(42).meta.delete('cache_hash')
```

---

## Constructor de consultas en metadatos de publicación

El modelo `PostMeta` implementa el trait `QueryBuilder`. Todas las operaciones del constructor de consultas encadenadas en `Post.meta` se delimitan a la publicación principal a través de `post_id = Post.ID`.

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

* **Igualdad** (Dos argumentos): `post.meta.where('column', 'value')`
* **Condición IN** (Dos argumentos con arreglo): `post.meta.where('column', ['val1', 'val2'])`
* **Operador de comparación** (Tres argumentos): `post.meta.where('column', 'operator', 'value')`

#### Operadores de comparación compatibles

| Operador | Descripción | Ejemplo |
| :--- | :--- | :--- |
| `'='` | Igualdad exacta (predeterminado) | `Posts.get(42).meta.where('meta_key', '=', '_thumbnail_id').get()` |
| `'>'` | Mayor que | `Posts.get(42).meta.where('meta_id', '>', 100).get()` |
| `'>='` | Mayor o igual que | `Posts.get(42).meta.where('meta_id', '>=', 100).get()` |
| `'<'` | Menor que | `Posts.get(42).meta.where('meta_id', '<', 500).get()` |
| `'<='` | Menor o igual que | `Posts.get(42).meta.where('meta_id', '<=', 500).get()` |
| `'!='`, `'<>'` | No igual | `Posts.get(42).meta.where('meta_key', '!=', '_edit_lock').get()` |
| `'like'` | Coincidencia de patrones SQL LIKE | `Posts.get(42).meta.where('meta_key', 'like', '_oembed_%').get()` |
| `'not like'` | Coincidencia de patrones SQL LIKE negada | `Posts.get(42).meta.where('meta_key', 'not like', '_wp_%').get()` |
| `'in'` | Pertenencia a conjuntos (el valor debe ser un arreglo) | `Posts.get(42).meta.where('meta_key', 'in', ['_thumbnail_id', '_edit_last']).get()` |
| `'not in'` | Pertenencia a conjuntos negada (el valor debe ser un arreglo) | `Posts.get(42).meta.where('meta_key', 'not in', ['_edit_lock', '_edit_last']).get()` |
| `'between'` | Comprobación de rango (el valor debe ser un arreglo de dos elementos `[min, max]`) | `Posts.get(42).meta.where('meta_id', 'between', [100, 200]).get()` |

#### Ejemplos

```elscript
/* Consultar metadatos que coincidan con un patrón para una publicación específica */
Posts.get(42).meta.where('meta_key', 'like', '_wp_%').get()
```

```elscript
/* Consultar claves de metadatos específicas */
Posts.get(42).meta.where('meta_key', 'in', ['_thumbnail_id', '_wp_page_template']).get()
```

---

### PostMeta.or_where()

Agrega una condición `WHERE` que inicia un nuevo grupo de condiciones combinado con los grupos anteriores mediante lógica `OR`. Las condiciones añadidas dentro de cada grupo usando `where()` se combinan mediante `AND`. La delimitación a `post_id = Post.ID` se conserva en todos los grupos.

```elscriptsignature
PostMeta.or_where(
  string:column,
  mixed:operator_or_value,
  mixed:value = null
): PostMeta
```

#### Ejemplo

```elscript
/* Consultar claves de metadatos que coincidan con cualquiera de dos claves */
Posts.get(42).meta.where('meta_key', '_thumbnail_id')
  .or_where('meta_key', '_wp_page_template')
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
Posts.get(42).meta.where('meta_key', 'like', 'custom_%')
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
Posts.get(42).meta.order_by('meta_id', 'ASC')
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
Posts.get(42).meta.order_by('meta_id', 'ASC')
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
/* Ejecutar consulta SQL directa contra metadatos de post replicados */
Posts.get(42).meta.where('meta_key', 'like', '_%')
  .query('SELECT meta_id, meta_key, meta_value FROM wp_postmeta ORDER BY meta_id ASC')
```

:::info Extensión SQLite3 Requerida
La extensión de PHP SQLite3 **debe** estar instalada y habilitada en tu servidor para que `PostMeta.query()` funcione.
:::

---

### PostMeta.count()

Cuenta registros de metadatos coincidentes para la publicación en MySQL sin almacenar filas en memoria ni inicializar una tabla SQLite. Aplica la delimitación de `post_id`. Reinicia el estado del constructor tras la ejecución.

```elscriptsignature
PostMeta.count(): int
```

#### Ejemplos

```elscript
/* Contar todas las filas de metadatos para una publicación */
Posts.get(42).meta.count()
```

```elscript
/* Contar claves de metadatos ocultas/privadas */
Posts.get(42).meta.where('meta_key', 'like', '_%').count()
```

---

### PostMeta.to_sql()

Compila y devuelve la cadena de consulta SQL para la consulta de metadatos de la publicación sin ejecutarla. Aplica la delimitación `post_id = Post.ID`. Reinicia el estado del constructor tras la compilación.

```elscriptsignature
PostMeta.to_sql(bool:is_count = false): string
```

#### Parámetros

* **`is_count`** (`bool`, _opcional_): Cuando es `true`, compila una consulta `SELECT COUNT(*)` en lugar de una proyección de columnas. El valor predeterminado es `false`.

#### Ejemplos

```elscript
/* Previsualizar SQL de consulta de metadatos */
Posts.get(42).meta.where('meta_key', 'like', 'billing_%').order_by('meta_id', 'DESC').to_sql()
```

```elscript
/* Previsualizar SQL de conteo de metadatos */
Posts.get(42).meta.where('meta_key', 'like', 'billing_%').to_sql(true)
```
