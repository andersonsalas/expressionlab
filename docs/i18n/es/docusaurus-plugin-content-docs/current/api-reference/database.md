---
id: database
title: Base de datos
sidebar_position: 1
---

# Base de datos

Permite ejecutar consultas SQL, uniones (*joins*) y agregaciones sobre las tablas de WordPress mediante replicación en una **base de datos efímera SQLite3 en memoria**, separando las consultas analíticas de la base de datos MySQL activa.

:::info Extensión SQLite3 Requerida
La extensión de PHP SQLite3 **debe** estar instalada y habilitada en el servidor para que el servicio `Database` funcione con todas sus capacidades. Si SQLite3 no está disponible, `Database.mirror()` conserva los resultados en el búfer interno y `fetch()` los recupera del historial, pero `Database.query()` lanzará una excepción.
:::

## Replicación de datos (*mirroring*)

### Database.mirror()

Copia los registros de una tabla de WordPress en una base de datos SQLite en memoria con ámbito limitado a la petición activa (*request scope*). Esta base de datos se destruye en cuanto finaliza la evaluación de la expresión actual.

```elscriptsignature
Database.mirror(
  string:table,
  array|object|null:where = [],
  array|object|null:options = null
): Database
```

#### Parámetros

* **`table`** (`string`, _requerido_): El nombre de la tabla a replicar sin prefijo (por ejemplo, `'posts'`, `'users'`, `'postmeta'`).
* **`where`** (`array|object|null`, _opcional_): Condiciones de filtrado mediante estructuras recursivas, operadores de comparación o joins en el sitio. El valor predeterminado es `[]` (replica toda la tabla hasta los límites del búfer).
* **`options`** (`array|object|null`, _opcional_): Opciones de configuración para selección de columnas, ordenamiento, límites de paginación y prefijos. Consulta [Opciones de Configuración de Replicación](#opciones-de-configuración-de-replicación).

#### Uso básico

Supongamos que deseas obtener _todas_ las entradas de la tabla `wp_posts`. Puedes utilizar cualquiera de las siguientes expresiones:

```elscript
Database.mirror('posts').fetch()
```

```elscript
Database.mirror('posts').flush()
```

Los métodos encadenados `fetch()` y `flush()` recuperan los datos replicados y los devuelven como un arreglo de objetos. La diferencia es que **`flush()` libera el búfer de SQLite** (elimina y reinicializa la base de datos en memoria, limpiando el historial de consultas) mientras que **`fetch()` conserva los datos en memoria** para operaciones posteriores.

:::note Resolución Automática de Prefijos
Los prefijos de tabla (como `wp_`) se resuelven según el entorno. En instalaciones de WordPress Multisite, `Database.mirror` aplica el **prefijo del blog actual** (por ejemplo, `wp_2_`) para tablas específicas del sitio y el **prefijo base** (por ejemplo, `wp_`) para tablas globales (`users`, `usermeta` y cualquier `ms_global_tables` registrada).
:::

:::caution Límite de Búfer
Al replicar una tabla grande sin especificar un `limit` explícito (por ejemplo, `Database.mirror('posts').fetch()`), Expression Lab limita la consulta al límite de búfer disponible (`1.000` filas de forma predeterminada) y muestra una notificación de advertencia en la Consola.

Esto permite un muestreo exploratorio instantáneo en tablas grandes de producción (por ejemplo, más de 50.000 publicaciones) sin desencadenar excepciones de seguridad de memoria. Para recuperar un conjunto de datos más grande, aumenta el búfer con `Database.buffer(50000)` o proporciona una opción `limit` explícita.
:::

:::warning La Replicación no es UNION / UNION ALL
Llamar a `Database.mirror()` varias veces en la **misma tabla** (por ejemplo, `'posts'`) **reemplaza** el esquema y las filas de la tabla SQLite en memoria con la última consulta. **No** agrega filas ni se comporta como un `UNION ALL` de SQL.

* **Para consultar múltiples tipos/estados en una sola tabla:** Utiliza una sola llamada `mirror()` con un operador `$in` o un arreglo `OR` (por ejemplo, `{ post_type: { '$in': ['post', 'page'] } }`).
* **Para mantener múltiples instantáneas independientes de la misma tabla:** Utiliza la **opción `as`** para asignar nombres de tabla personalizados en SQLite (por ejemplo, `{ as: 'my_posts' }` y `{ as: 'my_pages' }`).
* **Para combinar diferentes tablas:** Replica tablas distintas (por ejemplo, `posts`, `users`, `postmeta`) para realizar operaciones relacionales `JOIN` entre ellas en `Database.query()`.
:::

---

## Sintaxis del parámetro WHERE

El parámetro `where` en `Database.mirror()` admite una sintaxis de filtrado recursiva y expresiva basada en las convenciones de Symfony Expression Language. Las condiciones se compilan en consultas MySQL parametrizadas (`$wpdb->prepare`) antes de replicar los datos en SQLite.

### Agrupación lógica (AND vs OR)

La estructura de datos subyacente determina si las condiciones se combinan con `AND` u `OR`:

#### 1. Los objetos `{}` representan grupos `AND`

Al pasar un mapa asociativo (objeto), todos los pares clave-valor se concatenan con `AND`.

```elscript
Database.mirror('posts', {
  post_status: 'publish',
  post_type: 'post'
}).fetch()
```

**SQL compilado:**
```sql
SELECT * FROM wp_posts WHERE post_status = 'publish' AND post_type = 'post'
```

#### 2. Los arreglos `[]` representan grupos `OR`

Al pasar una lista indexada (arreglo) de objetos de condición, cada rama se envuelve entre paréntesis y se combina con `OR`.

```elscript
Database.mirror('posts', [
  { post_status: 'draft' },
  { post_status: 'pending' }
]).fetch()
```

**SQL compilado:**
```sql
SELECT * FROM wp_posts WHERE (post_status = 'draft') OR (post_status = 'pending')
```

#### 3. Agrupación anidada y recursiva

Puedes anidar objetos dentro de arreglos (OR de ANDs) o arreglos dentro de objetos para construir árboles lógicos booleanos complejos:

```elscript
Database.mirror('posts', [
  { post_type: 'post', post_status: 'publish' },
  { post_type: 'page', post_status: 'draft' }
]).fetch()
```

**SQL compilado:**
```sql
SELECT * FROM wp_posts
WHERE (post_type = 'post' AND post_status = 'publish')
   OR (post_type = 'page' AND post_status = 'draft')
```

#### 4. Ejemplo de anidamiento profundo

Se admiten árboles booleanos de cualquier nivel de complejidad. El siguiente ejemplo combina grupos OR anidados dentro de un grupo AND:

```elscript
Database.mirror('posts', {
  post_type: 'post',
  post_status: { '$in': ['publish', 'draft'] }
}).fetch()
```

**SQL compilado:**
```sql
SELECT * FROM wp_posts
WHERE post_type = 'post' AND post_status IN ('publish', 'draft')
```

### Operadores de filtro y comparación

El parámetro `where` admite un amplio conjunto de operadores pasados como objetos anidados dentro de las definiciones de columna:

| Operador | Tipo | Descripción | Ejemplo | SQL compilado |
| :--- | :--- | :--- | :--- | :--- |
| *Valor directo* | Escalar | Comparación de igualdad (`=`) | `{ post_status: 'publish' }` | `post_status = 'publish'` |
| `$gt` | Número / Cadena | Mayor que (`>`) | `{ ID: { '$gt': 100 } }` | `ID > 100` |
| `$gte` | Número / Cadena | Mayor o igual que (`>=`) | `{ ID: { '$gte': 100 } }` | `ID >= 100` |
| `$lt` | Número / Cadena | Menor que (`<`) | `{ ID: { '$lt': 50 } }` | `ID < 50` |
| `$lte` | Número / Cadena | Menor o igual que (`<=`) | `{ ID: { '$lte': 50 } }` | `ID <= 50` |
| `$ne` | Mixto | No igual (`!=`) | `{ post_status: { '$ne': 'trash' } }` | `post_status != 'trash'` |
| `$isNull` | Booleano | Comprueba si la columna `IS NULL` | `{ meta_value: { '$isNull': true } }` | `meta_value IS NULL` |
| `$isNotNull` | Booleano | Comprueba si la columna `IS NOT NULL` | `{ meta_value: { '$isNotNull': true } }` | `meta_value IS NOT NULL` |
| `$between` | Arreglo `[min, max]` | Comprobación de rango (`BETWEEN ... AND ...`) | `{ post_date: { '$between': ['2025-01-01', '2025-12-31'] } }` | `post_date BETWEEN '2025-01-01' AND '2025-12-31'` |
| `$like` | Cadena / Arreglo | Coincidencia de patrones (`LIKE`) | `{ post_title: { '$like': '%Report%' } }` | `post_title LIKE '%Report%'` |
| `$notLike` | Cadena / Arreglo | Coincidencia de patrones negada (`NOT LIKE`) | `{ post_title: { '$notLike': '%Draft%' } }` | `post_title NOT LIKE '%Draft%'` |
| `$in` | Arreglo | Pertenencia a conjunto (`IN (...)`) | `{ ID: { '$in': [1, 5, 10] } }` | `ID IN (1, 5, 10)` |
| *Arreglo implícito* | Arreglo | Pertenencia a conjunto implícita (`IN (...)`) | `{ ID: [1, 5, 10] }` | `ID IN (1, 5, 10)` |
| `$notIn` | Arreglo | Pertenencia a conjunto negada (`NOT IN (...)`) | `{ ID: { '$notIn': [1, 2, 3] } }` | `ID NOT IN (1, 2, 3)` |

:::caution Limitación de Operadores de Comparación
Los operadores de comparación (`$gt`, `$gte`, `$lt`, `$lte`, `$ne`) son **excluyentes entre sí por columna**. Cuando se especifican múltiples operadores de comparación en la misma columna, **solo se aplica el primer operador coincidente** (en el orden `$gt` → `$gte` → `$lt` → `$lte` → `$ne`). Los operadores restantes se omiten.
:::

Para expresar una **condición de rango**, utiliza en su lugar el operador `$between`.

**Correcto**:

```elscript
Database.mirror('posts', {
  post_date: { '$between': ['2025-01-01', '2025-12-31'] }
}).fetch()
```

**Incorrecto** (solo se aplica `$gte`; `$lte` se descarta sin generar error):

```elscript
Database.mirror('posts', {
  post_date: { '$gte': '2025-01-01', '$lte': '2025-12-31' }
}).fetch()
```

### Ejemplos de operadores

#### Filtrado de rango con `$between`

```elscript
Database.mirror('posts', {
  post_date: { '$between': ['2025-01-01 00:00:00', '2026-06-30 23:59:59'] },
  post_status: 'publish'
}).fetch()
```

**SQL compilado:**
```sql
SELECT * FROM wp_posts
WHERE post_date BETWEEN '2025-01-01 00:00:00' AND '2026-06-30 23:59:59'
  AND post_status = 'publish'
```

:::tip Sintaxis de $between
El operador `$between` requiere un arreglo con **exactamente 2 elementos**: `[min, max]`. Si el arreglo contiene menos o más de 2 elementos, la condición pasa a manejarse como `IN`, lo que produce una semántica diferente.
:::

#### Mayor que / menor que (operador único)

```elscript
Database.mirror('posts', {
  ID: { '$gt': 100 },
  post_status: 'publish'
}).fetch()
```

**SQL compilado:**
```sql
SELECT * FROM wp_posts WHERE ID > 100 AND post_status = 'publish'
```

#### No igual

```elscript
Database.mirror('posts', {
  post_status: { '$ne': 'trash' },
  post_type: 'post'
}).fetch()
```

**SQL compilado:**
```sql
SELECT * FROM wp_posts WHERE post_status != 'trash' AND post_type = 'post'
```

#### Múltiples patrones de búsqueda con `$like` y `$notLike`

Pasar un arreglo de patrones a `$like` o `$notLike` dentro de un único objeto de columna aplica múltiples patrones combinados con **`AND`** (coincidencia acumulativa):

```elscript
Database.mirror('posts', {
  post_status: { '$notLike': ['%draft%', '%archived%'] }
}).fetch()
```

**SQL compilado:**

```sql
SELECT * FROM wp_posts WHERE post_status NOT LIKE '%draft%' AND post_status NOT LIKE '%archived%'
```

```elscript
Database.mirror('posts', {
  post_title: { '$like': ['%report%', '%quarterly%'] }
}).fetch()
```

**SQL compilado:**
```sql
SELECT * FROM wp_posts WHERE post_title LIKE '%report%' AND post_title LIKE '%quarterly%'
```

#### Patrones alternativos con `OR` (búsqueda disyuntiva)

Para buscar filas que coincidan con **cualquier** patrón (`OR`), utiliza un arreglo indexado de objetos de condición en `Database.mirror()`:

```elscript
Database.mirror('posts', [
  { post_title: { '$like': '%report%' } },
  { post_title: { '$like': '%quarterly%' } }
]).fetch()
```

**SQL compilado:**

```sql
SELECT * FROM wp_posts WHERE (post_title LIKE '%report%') OR (post_title LIKE '%quarterly%')
```

:::caution Combinación de `$like` y `$notLike` en la Misma Columna
Especificar tanto `$like` como `$notLike` dentro del **mismo objeto de columna** no está admitido (el motor evalúa primero `$like` e ignora cualquier `$notLike` posterior en esa misma columna).
:::

Para combinar filtros de patrones positivos y negativos, apunta a columnas distintas en el objeto de consulta:

```elscript
Database.mirror('posts', {
  post_title: { '$like': '%report%' },
  post_status: { '$notLike': '%trash%' }
}).fetch()
```

#### Pertenencia a conjuntos con `$in` y `$notIn`

```elscript
Database.mirror('posts', {
  post_status: { '$in': ['publish', 'draft', 'pending'] }
}).fetch()
```

**SQL compilado:**
```sql
SELECT * FROM wp_posts WHERE post_status IN ('publish', 'draft', 'pending')
```

```elscript
Database.mirror('posts', {
  ID: { '$notIn': [1, 2, 3] }
}).fetch()
```

**SQL compilado:**
```sql
SELECT * FROM wp_posts WHERE ID NOT IN (1, 2, 3)
```

#### Abreviatura de arreglo implícito para `IN`

Cuando el valor de una columna es un arreglo simple (no un objeto operador), se trata como una cláusula `IN` implícita:

```elscript
Database.mirror('posts', {
  ID: [1, 5, 10, 25]
}).fetch()
```

**SQL compilado:**
```sql
SELECT * FROM wp_posts WHERE ID IN (1, 5, 10, 25)
```

#### Comprobaciones de nulidad

```elscript
Database.mirror('postmeta', {
  meta_key: '_thumbnail_id',
  meta_value: { '$isNotNull': true }
}).fetch()
```

**SQL compilado:**
```sql
SELECT * FROM wp_postmeta WHERE meta_key = '_thumbnail_id' AND meta_value IS NOT NULL
```

```elscript
Database.mirror('postmeta', {
  meta_key: '_thumbnail_id',
  meta_value: { '$isNull': true }
}).fetch()
```

**SQL compilado:**
```sql
SELECT * FROM wp_postmeta WHERE meta_key = '_thumbnail_id' AND meta_value IS NULL
```

---

## In-site joins y referencias entre tablas

Los in-site joins te permiten crear **pipelines de datos relacionales** a través de llamadas consecutivas a `.mirror()` durante la fase de extracción de MySQL, sin necesidad de cargar conjuntos de datos masivos sin filtrar en memoria.

En lugar de replicar una tabla completa y filtrarla después, una llamada a `.mirror()` posterior puede hacer referencia a los valores de columna de una tabla replicada antes utilizando la sintaxis de referencia `$`.

* **`'$prev.NOMBRE_COLUMNA'`**: Hace referencia a los valores de la tabla replicada **previa inmediata**.
* **`'$NOMBRE_TABLA.NOMBRE_COLUMNA'`**: Hace referencia a los valores de **cualquier tabla replicada con anterioridad** en la cadena de ejecución actual (por ejemplo, `'$users.ID'` o `'$posts.ID'`).

### Funcionamiento interno de los in-site joins

Cuando Expression Lab encuentra una referencia como `'$prev.ID'` o `'$posts.ID'`:

1. **Búsqueda en el historial**: El motor busca en su historial de consultas interno (`query_history`). Si se especifica `$prev`, toma los resultados de la **última operación de replicación** (el último elemento en el historial). Si se especifica una tabla con nombre (por ejemplo, `$posts`), recorre el historial en **orden inverso** (de más reciente a más antiguo) y busca coincidencias con el nombre sin prefijo (`posts`), el nombre con prefijo (`wp_posts`) o el patrón de sufijo (`_posts`).
2. **Extracción y deduplicación de columnas**: El motor extrae todos los valores de la columna especificada del conjunto de resultados de origen y elimina los duplicados mediante `array_unique(array_column($source_data, $col))`.
3. **Generación de cláusula `IN (...)` preparada**: Los valores extraídos y deduplicados se convierten en una cláusula `IN (%s, %s, ...)` con valores parametrizados a través de `$wpdb->prepare()`.
4. **Respaldo para cero resultados**: Si la tabla de origen devolvió **0 filas** (o todos los valores de columna extraídos estaban vacíos), el motor inyecta `[-1]` como conjunto de valores (por ejemplo, `WHERE post_author IN (-1)`). Esto evita errores de sintaxis en cláusulas vacías `IN ()` y asegura que las consultas posteriores devuelvan 0 filas sin interrumpir la ejecución.

:::note Resolución de referencias con nombre
Al utilizar `$NOMBRE_TABLA.columna`, el motor prueba tres estrategias de coincidencia en orden:
1. Coincidencia exacta en `raw_table` (por ejemplo, `posts`)
2. Coincidencia exacta en `table` (por ejemplo, `wp_posts` o `wp_2_posts`)
3. Coincidencia de sufijo: ¿el nombre con prefijo almacenado termina en `_NOMBRE_TABLA`?

La búsqueda siempre recorre el historial de **más reciente a más antiguo** y se detiene en la primera coincidencia.
:::

### Ejemplo de pipeline multi-tabla

El siguiente ejemplo demuestra una cadena relacional de tres etapas:

```elscript
Database
  /*
   * Paso 1: Replicar usuarios objetivo 
   */
  .mirror('users', {
    user_email: { '$like': '%@example.com' }
  }, {
    select: ['ID', 'user_login', 'user_email']
  })

  /* 
   * Paso 2: In-site join con el paso anterior ($prev = users) 
   * Consulta: SELECT * FROM wp_posts WHERE post_author IN (IDs de usuario extraídos) AND post_status = 'publish' 
   */
  .mirror('posts', {
    post_author: '$prev.ID',
    post_status: 'publish'
  }, {
    select: ['ID', 'post_title', 'post_author', 'post_date']
  })

  /*
   * Paso 3: In-site join referenciando la tabla por nombre ($posts)
   * Consulta: SELECT * FROM wp_postmeta WHERE post_id IN (IDs de post extraídos) AND meta_key = '_thumbnail_id'
   */
  .mirror('postmeta', {
    post_id: '$posts.ID',
    meta_key: '_thumbnail_id'
  })
  .fetch()
```

### Ejemplo de pipeline de dos pasos

```elscript
Database
  .mirror('posts', {
    post_type: 'product',
    post_status: 'publish'
  }, {
    select: ['ID', 'post_title']
  })
  .mirror('postmeta', {
    post_id: '$prev.ID',
    meta_key: '_price'
  })
  .fetch()
```

**SQL del paso 1:**
```sql
SELECT ID, post_title FROM wp_posts WHERE post_type = 'product' AND post_status = 'publish'
```

**SQL del paso 2 (asumiendo que el paso 1 devolvió los IDs 10, 20, 30):**
```sql
SELECT * FROM wp_postmeta WHERE post_id IN (10, 20, 30) AND meta_key = '_price'
```

---

## Opciones de configuración de replicación

El tercer parámetro opcional de `Database.mirror()` acepta un objeto/arreglo para controlar la selección de columnas, el ordenamiento y la paginación:

```elscript
Database.mirror(table, where, options)
```

| Opción | Tipo | Valor predeterminado | Descripción |
| :--- | :--- | :--- | :--- |
| `select` | `string` \| `array` | `'*'` | Columnas a recuperar de MySQL. Los nombres de columna se sanitizan a caracteres alfanuméricos y guiones bajos. Si todos los nombres de columna son inválidos tras la sanitización, vuelve a `'*'`. |
| `orderby` | `object` \| `array` | `null` | Directivas de ordenamiento. Puede ser un mapa único `{ col: 'ASC'\|'DESC' }` o una lista de mapas `[{ col1: 'DESC' }, { col2: 'ASC' }]`. La dirección se valida; cualquier valor diferente de `'DESC'` se establece por defecto en `'ASC'`. |
| `limit` | `int` | `buffer_max_rows` (por defecto `1000`) | Filas máximas a recuperar de MySQL. Cuando se especifica, solo las filas solicitadas se evalúan contra el límite de búfer de memoria, permitiendo un muestreo seguro de tablas grandes. |
| `offset` | `int` | `0` | Número de filas a omitir (desplazamiento de paginación). El valor se convierte a un entero positivo. |
| `prefix` | `string` | *Auto-resuelto* | Anulación personalizada del prefijo de tabla. Sanitizado a caracteres alfanuméricos y guiones bajos. Si no se especifica, el motor resuelve el prefijo según la configuración multisite. |
| `as` | `string` | `null` | Alias personalizado para el nombre de la tabla en SQLite en memoria (por ejemplo, `'my_posts'`). Cuando se especifica, anula la denominación predeterminada `wp_{table}`, permitiendo que múltiples instantáneas de la misma tabla MySQL coexistan al mismo tiempo en memoria. Sanitizado a caracteres alfanuméricos y guiones bajos. |

### Ejemplos de opciones

#### Proyección de columnas y ordenamiento

```elscript
Database.mirror('posts', { post_status: 'publish' }, {
  select: ['ID', 'post_title', 'post_date'],
  orderby: { post_date: 'DESC' },
  limit: 10,
  offset: 0
}).fetch()
```

#### Alias de tabla personalizado con `as` (multi-instantánea / UNION)

Utiliza la opción `as` para crear tablas distintas con nombres personalizados en SQLite. Esto permite replicar la misma tabla MySQL varias veces con criterios diferentes y consultarlas juntas (como realizar `UNION ALL` o comparaciones entre conjuntos):

```elscript
Database
  .mirror('posts', { post_type: 'post' }, { as: 'active_posts', limit: 10 })
  .mirror('posts', { post_type: 'page' }, { as: 'active_pages', limit: 10 })
  .query('
    SELECT "post" AS type, ID, post_title FROM active_posts
    UNION ALL
    SELECT "page" AS type, ID, post_title FROM active_pages
  ')
```

#### Ordenamiento multi-columna

```elscript
Database.mirror('posts', null, {
  select: 'ID, post_title, post_type, post_status',
  orderby: [
    { post_type: 'ASC' },
    { post_date: 'DESC' }
  ],
  limit: 25
}).fetch()
```

#### Anulación de prefijo personalizado

```elscript
Database.mirror('posts', { post_status: 'publish' }, {
  prefix: 'wp_3_'
}).fetch()
```

#### Paginación

```elscript
/* Página 3 de resultados (25 elementos por página) */
Database.mirror('posts', { post_type: 'post' }, {
  select: ['ID', 'post_title', 'post_date'],
  orderby: { post_date: 'DESC' },
  limit: 25,
  offset: 50
}).fetch()
```

---

## Ejecución de consultas SQL directas

### Database.query()

Ejecuta una consulta SQL arbitraria contra la base de datos SQLite3 en memoria.

```elscriptsignature
Database.query(string:sql): array
```

Este método ofrece total flexibilidad de consulta en SQL (incluyendo `JOIN`, agregaciones, subconsultas, `GROUP BY`, CTEs con `WITH` y funciones de ventana) sobre las tablas replicadas con anterioridad.

* **Las tablas deben replicarse primero:** Debes replicar las tablas de destino mediante `Database.mirror()` antes de ejecutar `Database.query()`. Si no se ha replicado ninguna tabla, se lanzará una excepción.
* **Nombres de tabla en SQL directo:** Las tablas replicadas sin un alias tienen el prefijo `wp_` en SQLite (por ejemplo, `wp_posts`, `wp_users`). Las tablas replicadas con un alias personalizado `{ as: 'my_table' }` utilizan ese nombre de alias exacto sin ningún prefijo.
* **Cero impacto en producción:** La base de datos SQLite existe solo en RAM durante la ejecución de la expresión. Nunca modifica, bloquea ni afecta la base de datos MySQL activa.
* **Ejecución segura de consultas:** Admite `SELECT`, Expresiones de Tabla Comunes (`WITH ... SELECT ...`), consultas envueltas `(SELECT ...)` y sentencias `EXPLAIN`.

:::warning Regla de nombres de tabla en SQLite
A menos que asignes un alias personalizado utilizando la opción `as`, todas las tablas replicadas se almacenan en SQLite con el prefijo normalizado `wp_`. Utiliza siempre `wp_nombretabla` para réplicas estándar, o `alias_personalizado` para réplicas con alias en tus sentencias `Database.query()`.
:::

:::warning Advertencia sobre Tipos SQLite
Todas las columnas en las tablas replicadas de SQLite se almacenan como `TEXT`. El tipado dinámico de SQLite significa que las comparaciones numéricas y la aritmética suelen operar sin problemas (SQLite detecta el contenido numérico); no obstante, cabe considerar los siguientes casos:

- **Ordenamiento:** `ORDER BY numeric_column ASC` puede producir un orden alfabético (`1, 10, 2`) en lugar de numérico (`1, 2, 10`). Utiliza `CAST(column AS INTEGER)` para garantizar el orden numérico.
- **Agregaciones:** `SUM()`, `AVG()`, `MIN()`, `MAX()` operan sin problemas porque SQLite convierte el texto a valores numéricos.
- **Operaciones de fecha:** Las funciones `date()`, `datetime()` y `strftime()` de SQLite funcionan con el formato de fecha ISO-8601 de WordPress (`YYYY-MM-DD HH:MM:SS`) almacenado como TEXT.
:::

### Ejemplo de join en SQLite

```elscript
Database
  .mirror('users', { user_email: { '$like' : '%@example.com' } })
  .mirror('posts', { post_author: '$prev.ID', post_status: 'publish' })
  .query('
    SELECT
      u.user_login,
      u.user_email,
      p.ID AS post_id,
      p.post_title,
      p.post_date
    FROM wp_posts p
    INNER JOIN wp_users u ON p.post_author = u.ID
    ORDER BY p.post_date DESC
    LIMIT 20
  ')
```

### Ejemplo de agregación

```elscript
Database
  .mirror('posts', { post_status: 'publish' })
  .query('
    SELECT
      post_type,
      COUNT(*) AS total,
      MIN(post_date) AS earliest,
      MAX(post_date) AS latest
    FROM wp_posts
    GROUP BY post_type
    ORDER BY total DESC
  ')
```

### Ejemplo de subconsulta

```elscript
Database
  .mirror('posts', { post_status: 'publish' })
  .mirror('postmeta')
  .query('
    SELECT
      p.ID,
      p.post_title,
      (SELECT pm.meta_value FROM wp_postmeta pm
       WHERE pm.post_id = p.ID AND pm.meta_key = "_thumbnail_id"
       LIMIT 1) AS thumbnail_id
    FROM wp_posts p
    ORDER BY p.post_date DESC
    LIMIT 10
  ')
```

### Ejemplo de función de ventana

```elscript
Database
  .mirror('posts', { post_type: 'post', post_status: 'publish' })
  .query('
    SELECT
      ID,
      post_title,
      post_author,
      post_date,
      ROW_NUMBER() OVER (PARTITION BY post_author ORDER BY post_date DESC) AS author_post_rank
    FROM wp_posts
  ')
```

---

## Recuperación de resultados y gestión del búfer

### Database.fetch()

Recupera los registros replicados de SQLite sin limpiar la base de datos en memoria.

```elscriptsignature
Database.fetch(?string:table = null, bool:as_object = true): array
```

* **`table`** (`string|null`, opcional): Nombre de tabla específico (sin prefijo) a recuperar. Si se omite, devuelve los registros de la tabla replicada más reciente.
* **`as_object`** (`bool`, opcional): Cuando es `true` (predeterminado), las filas se devuelven como objetos. Cuando es `false`, se devuelven como arreglos asociativos.

#### Valor de retorno

Arreglo de registros devueltos de la tabla SQLite. Cada fila es un arreglo asociativo mapeado a pares columna/valor.

#### Ejemplos

```elscript
/* Replicar posts y recuperar los registros */
Database.mirror('posts').fetch()
```

---

### Database.flush()

Recupera registros de SQLite y reinicia la base de datos SQLite en memoria y el búfer de consultas para liberar memoria del servidor.

```elscriptsignature
Database.flush(?string:table = null, bool:as_object = true): array
```

* **`table`** (`string|null`, opcional): Nombre de tabla específico (sin prefijo) a recuperar antes de vaciar. Si se omite, devuelve los registros de la tabla replicada más reciente.
* **`as_object`** (`bool`, opcional): Cuando es `true` (predeterminado), las filas se devuelven como objetos. Cuando es `false`, se devuelven como arreglos asociativos.

Después de llamar a `flush()`, se reinicia el siguiente estado:
- La base de datos SQLite en memoria se destruye y se crea una nueva instancia `:memory:`.
- `query_history` se limpia.
- `last_table` y `last_results` se reinician.

```elscript
/*
 * Replicar, extraer datos y liberar la memoria RAM
 */
Database.mirror('options', { autoload: 'yes' }).flush()
```

:::tip Cuándo usar `flush()` vs `fetch()`
Se recomienda emplear **`fetch()`** cuando se planee ejecutar operaciones adicionales sobre los datos replicados (por ejemplo, continuar con `Database.query()` o replicar más tablas para in-site joins).

Se recomienda emplear **`flush()`** al realizar la recuperación final de datos para liberar memoria de inmediato, en especial con conjuntos de datos extensos.
:::

---

### Database.buffer()

Configura los límites de seguridad y las protecciones heurísticas de memoria para las operaciones de replicación de datos.

```elscriptsignature
Database.buffer(
  ?int:rows = 1000,
  ?int:memory = 50,
  ?float:php_overhead_factor = 3.5
): Database
```

* **`rows`** (`int`, por defecto `1000`): Máximo total de filas permitidas en el búfer en memoria en todas las tablas replicadas. Cada llamada subsiguiente a `mirror()` comprueba que el recuento acumulado de filas (filas en búfer existentes + nuevo recuento de resultados de consulta) no supere este límite.
* **`memory`** (`int`, por defecto `50`): Uso máximo estimado de memoria RAM en megabytes (MB).
* **`php_overhead_factor`** (`float`, por defecto `3.5`): Multiplicador utilizado para estimar la sobrecarga de estructuras de datos en memoria de PHP en relación con el tamaño de tabla SQL sin procesar. La fórmula de estimación es: `estimated_MB = (row_count × avg_row_bytes × php_overhead_factor) / 1024²`.

:::info Seguridad del Búfer y Mecanismo de Límite Automático
Los límites de búfer se aplican **antes de que los datos se recuperen de MySQL**:

1. **`limit` No Especificado (Límite Automático)**: Si no se proporciona una opción explícita de `limit` y la tabla supera la capacidad restante del búfer, la consulta se limita a las filas restantes del búfer y se emite una advertencia para notificar al desarrollador.
2. **`limit` Explícito**: Si se especifica un `limit` explícito que supera la capacidad del búfer, se lanza la excepción `Memory safety triggered`.
3. **Estimación de Memoria**: Consulta `SHOW TABLE STATUS` para obtener `Avg_row_length` y calcula `estimated_MB = (rows × Avg_row_length × php_overhead_factor) / 1024²`. Si supera el límite de `memory` (predeterminado: `50` MB), se lanza una excepción.

Estas son estimaciones heurísticas, no mediciones exactas. El uso real de memoria de PHP puede variar según los tipos de datos, la longitud de las cadenas y las estructuras internas de PHP.
:::

La configuración del búfer persiste en las operaciones `mirror()` posteriores hasta que se llama a `flush()` (que reinicia el historial de consultas pero conserva la configuración del búfer) o se establecen nuevos valores mediante otra llamada a `buffer()`.

```elscript
/* 
 * Aumentar el búfer para una consulta de diagnóstico grande
 */
Database
  .buffer(5000, 100)
  .mirror('posts', { post_type: 'product' })
  .fetch()
```

```elscript
/* 
 * Reducir el factor de sobrecarga para tablas con filas pequeñas y predecibles
 */
Database
  .buffer(2000, 75, 2.0)
  .mirror('options')
  .flush()
```

---

## Conteo directo y compilación de SQL

### Database.count()

Cuenta registros coincidentes en la base de datos MySQL sin almacenar filas en memoria ni inicializar una tabla SQLite.

```elscriptsignature
Database.count(
  string:table,
  array|object|null:where = [],
  array|object|null:options = null
): int
```

#### Parámetros

* **`table`** (`string`, _requerido_): Nombre de la tabla sin prefijo (por ejemplo, `'posts'`, `'users'`, `'postmeta'`).
* **`where`** (`array|object|null`, _opcional_): Condiciones de filtrado siguiendo la sintaxis estándar recursiva de WHERE. El valor predeterminado es `[]`.
* **`options`** (`array|object|null`, _opcional_): Objeto de opciones que admite la anulación de `'prefix'`. El valor predeterminado es `null`.

#### Ejemplos

```elscript
/* Contar publicaciones publicadas */
Database.count('posts', { post_status: 'publish', post_type: 'post' })
```

```elscript
/* Contar usuarios registrados en un intervalo específico */
Database.count('users', { user_registered: { '$gte': '2026-01-01 00:00:00' } })
```

---

### Database.get_queries()

Devuelve un arreglo de todas las consultas SQL ejecutadas contra MySQL mediante `$wpdb` en la sesión actual. Resulta de gran utilidad para depurar cadenas `mirror()` de múltiples tablas y verificar las consultas exactas enviadas al motor de base de datos.

```elscriptsignature
Database.get_queries(): array
```

#### Ejemplos

```elscript
/* Inspeccionar consultas ejecutadas a través de una cadena de replicación */
Database
  .mirror('users', { user_status: 0 })
  .mirror('posts', { post_author: '$prev.ID' })
  .get_queries()
```

```elscript
/* Recuperar todas las consultas ejecutadas después de operaciones de conteo o replicación */
Database.get_queries()
```

---

## Diagnóstico de esquemas y tablas

### Database.table_list()

Devuelve metadatos de todas las tablas en la base de datos activa de WordPress (nombre de tabla, motor de almacenamiento, recuento de filas y tamaño en MB).

```elscriptsignature
Database.table_list(): array
```

**Formato de retorno:** Un arreglo de objetos, cada uno conteniendo:

| Campo | Tipo | Descripción |
|:---|:---|:---|
| `name` | `string` | Nombre completo de la tabla incluyendo el prefijo |
| `engine` | `string` | Motor de almacenamiento (por ejemplo, `InnoDB`, `MyISAM`) |
| `rows` | `int` | Recuento aproximado de filas |
| `size` | `string` | Tamaño aproximado en MB (por ejemplo, `"1.52 MB"`) |

```elscript
Database.table_list()
```

<div className="desktop-window">
  <img 
    src="../../img/database-table-list.png" 
    alt="Lista de tablas en Expression Lab" 
  />
</div>

<VegaLite spec={{
  "$schema": "https://vega.github.io/schema/vega/v6.json",
  "description": "Tables distribution by rows",
  "autosize": {
    "type": "fit-x",
    "contains": "padding"
  },
  "background": "white",
  "padding": 20,
  "height": 300,
  "title": {
    "anchor": "start",
    "text": "Tables by Row Count"
  },
  "style": "view",
  "data": [
    {
      "name": "source_0",
      "values": [
        {
          "name": "wp_options",
          "engine": "InnoDB",
          "rows": 143,
          "size": "1.09 MB"
        },
        {
          "name": "wp_usermeta",
          "engine": "InnoDB",
          "rows": 20,
          "size": "0.05 MB"
        },
        {
          "name": "wp_postmeta",
          "engine": "InnoDB",
          "rows": 8,
          "size": "0.05 MB"
        },
        {
          "name": "wp_posts",
          "engine": "InnoDB",
          "rows": 8,
          "size": "0.09 MB"
        },
        {
          "name": "wp_users",
          "engine": "InnoDB",
          "rows": 1,
          "size": "0.06 MB"
        },
        {
          "name": "wp_commentmeta",
          "engine": "InnoDB",
          "rows": 0,
          "size": "0.05 MB"
        },
        {
          "name": "wp_comments",
          "engine": "InnoDB",
          "rows": 0,
          "size": "0.09 MB"
        },
        {
          "name": "wp_links",
          "engine": "InnoDB",
          "rows": 0,
          "size": "0.03 MB"
        },
        {
          "name": "wp_term_relationships",
          "engine": "InnoDB",
          "rows": 0,
          "size": "0.03 MB"
        },
        {
          "name": "wp_term_taxonomy",
          "engine": "InnoDB",
          "rows": 0,
          "size": "0.05 MB"
        },
        {
          "name": "wp_termmeta",
          "engine": "InnoDB",
          "rows": 0,
          "size": "0.05 MB"
        },
        {
          "name": "wp_terms",
          "engine": "InnoDB",
          "rows": 0,
          "size": "0.05 MB"
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
          "field": "rows",
          "sort": {
            "field": [
              "name"
            ],
            "order": [
              "ascending"
            ]
          },
          "as": [
            "rows_start",
            "rows_end"
          ],
          "offset": "zero"
        },
        {
          "type": "filter",
          "expr": "isValid(datum[\"rows\"]) && isFinite(+datum[\"rows\"])"
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
            "signal": "{\"rows\": format(datum[\"rows\"], \"\"), \"name\": isValid(datum[\"name\"]) ? isArray(datum[\"name\"]) ? join(datum[\"name\"], '\\n') : datum[\"name\"] : \"\"+datum[\"name\"]}"
          },
          "fill": {
            "scale": "color",
            "field": "name"
          },
          "description": {
            "signal": "\"rows: \" + (format(datum[\"rows\"], \"\")) + \"; name: \" + (isValid(datum[\"name\"]) ? isArray(datum[\"name\"]) ? join(datum[\"name\"], ' ') : datum[\"name\"] : \"\"+datum[\"name\"])"
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
            "field": "rows_end"
          },
          "endAngle": {
            "scale": "theta",
            "field": "rows_start"
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
          "rows_start",
          "rows_end"
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
        "field": "name",
        "sort": true
      },
      "range": "category"
    }
  ],
  "legends": [
    {
      "title": "Table Name",
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

<VegaLite spec={{
  "$schema": "https://vega.github.io/schema/vega/v6.json",
  "description": "Tables distribution by size",
  "autosize": {
    "type": "fit-x",
    "contains": "padding"
  },
  "background": "white",
  "padding": 20,
  "height": 300,
  "title": {
    "anchor": "start",
    "text": "Tables by Size (MB)"
  },
  "style": "view",
  "data": [
    {
      "name": "source_0",
      "values": [
        {
          "name": "wp_options",
          "engine": "InnoDB",
          "rows": 143,
          "size": 1.09
        },
        {
          "name": "wp_comments",
          "engine": "InnoDB",
          "rows": 0,
          "size": 0.09
        },
        {
          "name": "wp_posts",
          "engine": "InnoDB",
          "rows": 8,
          "size": 0.09
        },
        {
          "name": "wp_users",
          "engine": "InnoDB",
          "rows": 1,
          "size": 0.06
        },
        {
          "name": "wp_commentmeta",
          "engine": "InnoDB",
          "rows": 0,
          "size": 0.05
        },
        {
          "name": "wp_postmeta",
          "engine": "InnoDB",
          "rows": 8,
          "size": 0.05
        },
        {
          "name": "wp_term_taxonomy",
          "engine": "InnoDB",
          "rows": 0,
          "size": 0.05
        },
        {
          "name": "wp_termmeta",
          "engine": "InnoDB",
          "rows": 0,
          "size": 0.05
        },
        {
          "name": "wp_terms",
          "engine": "InnoDB",
          "rows": 0,
          "size": 0.05
        },
        {
          "name": "wp_usermeta",
          "engine": "InnoDB",
          "rows": 20,
          "size": 0.05
        },
        {
          "name": "wp_links",
          "engine": "InnoDB",
          "rows": 0,
          "size": 0.03
        },
        {
          "name": "wp_term_relationships",
          "engine": "InnoDB",
          "rows": 0,
          "size": 0.03
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
          "field": "size",
          "sort": {
            "field": [
              "name"
            ],
            "order": [
              "ascending"
            ]
          },
          "as": [
            "size_start",
            "size_end"
          ],
          "offset": "zero"
        },
        {
          "type": "filter",
          "expr": "isValid(datum[\"size\"]) && isFinite(+datum[\"size\"])"
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
            "signal": "{\"size\": format(datum[\"size\"], \"\"), \"name\": isValid(datum[\"name\"]) ? isArray(datum[\"name\"]) ? join(datum[\"name\"], '\\n') : datum[\"name\"] : \"\"+datum[\"name\"]}"
          },
          "fill": {
            "scale": "color",
            "field": "name"
          },
          "description": {
            "signal": "\"size: \" + (format(datum[\"size\"], \"\")) + \"; name: \" + (isValid(datum[\"name\"]) ? isArray(datum[\"name\"]) ? join(datum[\"name\"], ' ') : datum[\"name\"] : \"\"+datum[\"name\"])"
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
            "field": "size_end"
          },
          "endAngle": {
            "scale": "theta",
            "field": "size_start"
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
          "size_start",
          "size_end"
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
        "field": "name",
        "sort": true
      },
      "range": "category"
    }
  ],
  "legends": [
    {
      "title": "Table Name",
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

### Database.table_details()

Devuelve las definiciones de columnas, tipos de datos, nulabilidad, valores predeterminados y definiciones de índices para una tabla específica de WordPress.

```elscriptsignature
Database.table_details(string:tableName): array
```

* **`tableName`** (`string`, _requerido_): Nombre de la tabla sin prefijo (por ejemplo, `'posts'`, `'users'`).

**Formato de retorno:** Un objeto que contiene dos arreglos:

| Campo | Descripción |
|:---|:---|
| `columns` | Resultado de `SHOW FULL COLUMNS FROM tableName`: incluye `Field`, `Type`, `Null`, `Key`, `Default`, `Extra`, `Collation`, `Privileges` y `Comment` |
| `indexes` | Resultado de `SHOW INDEXES FROM tableName`: incluye `Key_name`, `Column_name`, `Non_unique`, `Seq_in_index`, `Cardinality` y más |

```elscript
Database.table_details('posts')
```

```elscript
Database.table_details('usermeta')
```
