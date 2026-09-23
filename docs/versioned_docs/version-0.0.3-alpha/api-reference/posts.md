---
id: posts
title: Posts
sidebar_position: 5
---

# Posts

Query, inspect, and manage WordPress posts, pages, and custom post types (CPTs) with fluent filtering, metadata traversal, and taxonomy inspection.

The post management architecture consists of three interconnected components:

1. **Posts Service (`Posts`)**: The primary entrypoint for post lookups by numeric ID, slug, or URL/permalink, query builder chaining, post type and status discovery, taxonomy inspection, and visual statistics.
2. **Post Model (`Post`)**: An entity representing an individual WordPress post, page, or custom post type, exposing normalized core properties (`ID`, `title`, `content`, `excerpt`, `status`, `type`, `slug`, `date`, `modified`, `author_id`, `parent_id`, `comment_count`), permalink resolution, and taxonomy term queries.
3. **PostMeta Model (`PostMeta`)**: A metadata repository accessible via `post.meta` that provides metadata CRUD operations and query builder integration scoped to the parent post.

---

## Accessing and Retrieving Posts

### Posts.get()

Retrieves a single `Post` model instance by identifier (numeric ID, post slug, or URL/permalink), or executes accumulated query builder conditions when called without arguments.

```elscriptsignature
Posts.get(
  int|string|null:identifier = null
): Post|array|null
```

#### Resolution Modes

* **By Numeric ID** (`int` or numeric string):
  Queries via `get_post(absint($identifier))`. Returns a `Post` model instance, or `null` if the post does not exist.
* **By URL or Permalink** (`string` matching URL formats, starting with `http://` or `https://`, or containing `/` path separators):
  1. Resolves via `url_to_postid($identifier)` or `url_to_postid(home_url($identifier))`.
  2. If unresolved, extracts the path via `wp_parse_url($identifier, PHP_URL_PATH)` and queries `get_page_by_path()` across all registered post types.
  3. If still unresolved, performs a fallback query via `get_posts()` matching the basename of the URL path across all post types and statuses.
  4. Returns a `Post` model instance, or `null` if no match is found.
* **By Slug / Post Name** (`string`):
  Queries via `get_posts()` with `name = sanitize_title($identifier)` across all post types (`'any'`) and statuses (`'any'`). Returns a `Post` model instance, or `null` if no post matches the slug.
* **Without Arguments** (`null`):
  Executes the accumulated `QueryBuilder` conditions against `wp_posts`, mirrors matching rows into an in-memory SQLite table, flushes the buffer, and returns the results as an array of row objects.

#### Examples

```elscript
/* Look up post by numeric ID */
Posts.get(42)
```

```elscript
/* Look up post by slug */
Posts.get('hello-world')
```

```elscript
/* Look up post by full URL */
Posts.get('https://example.com/2026/08/sample-post/')
```

```elscript
/* Look up post by relative path */
Posts.get('/sample-page/')
```

---

## Post Query Builder

The `Posts` service incorporates chainable query builder methods. Conditions are accumulated into groups and compiled into parameterized SQL when executed via `get()` or `query()`. Mirrored data is placed into an in-memory SQLite table and discarded upon buffer flush.

### Posts.where()

Appends a `WHERE` condition combined with `AND` logic within the current condition group.

```elscriptsignature
Posts.where(
  string:column,
  mixed:operator_or_value,
  mixed:value = null
): Posts
```

#### Call Signatures

* **Equality** (Two arguments): `Posts.where('column', 'value')`
* **IN condition** (Two arguments with array): `Posts.where('column', ['val1', 'val2'])`
* **Comparison Operator** (Three arguments): `Posts.where('column', 'operator', 'value')`

#### Supported Comparison Operators

The three-argument form `Posts.where(column, operator, value)` accepts standard SQL comparison operators as strings:

| Operator | Description | Example |
| :--- | :--- | :--- |
| `'='` | Exact equality (default) | `Posts.where('post_status', '=', 'publish').get()`, or `Posts.where('post_status', 'publish').get()` |
| `'>'` | Greater than | `Posts.where('ID', '>', 100).get()` |
| `'>='` | Greater than or equal | `Posts.where('ID', '>=', 100).get()` |
| `'<'` | Less than | `Posts.where('ID', '<', 50).get()` |
| `'<='` | Less than or equal | `Posts.where('ID', '<=', 50).get()` |
| `'!='`, `'<>'` | Not equal | `Posts.where('post_status', '!=', 'trash').get()` |
| `'like'` | SQL LIKE pattern matching | `Posts.where('post_title', 'like', '%announcement%').get()` |
| `'not like'` | Negated SQL LIKE pattern matching | `Posts.where('post_title', 'not like', '%draft%').get()` |
| `'in'` | Set membership (value must be an array) | `Posts.where('post_type', 'in', ['post', 'page']).get()` |
| `'not in'` | Negated set membership (value must be an array) | `Posts.where('post_status', 'not in', ['trash', 'auto-draft']).get()` |
| `'between'` | Range check (value must be a two-element array `[min, max]`) | `Posts.where('post_date', 'between', ['2026-01-01', '2026-12-31']).get()` |

:::note QueryBuilder vs Database.mirror Syntax
The `QueryBuilder` methods (`where()`, `or_where()`) accept standard SQL operator strings (`'>'`, `'like'`, `'between'`) and compile them internally into the `$`-prefixed recursive condition objects expected by `Database.mirror()`.

When using `Database.mirror(table, where, options)`, use the declarative object syntax documented in [Database](./database) (e.g., `{ ID: { '$gt': 100 } }`).
:::

#### Examples

```elscript
/* Query published pages */
Posts.where('post_type', 'page').where('post_status', 'publish').get()
```

```elscript
/* Query posts authored by a specific user with ID comparison */
Posts.where('post_author', 1).where('ID', '>', 50).get()
```

```elscript
/* Query posts matching a title pattern */
Posts.where('post_title', 'like', '%Release%').get()
```

---

### Posts.or_where()

Appends a `WHERE` condition that initiates a new condition group combined with preceding groups via `OR` logic. Conditions added within each group using `where()` are combined via `AND`.

```elscriptsignature
Posts.or_where(
  string:column,
  mixed:operator_or_value,
  mixed:value = null
): Posts
```

#### Example

```elscript
/* Retrieve posts that are either published standard posts or pending review pages */
Posts.where('post_type', 'post')
  .where('post_status', 'publish')
  .or_where('post_type', 'page')
  .where('post_status', 'pending')
  .get()
```

---

### Posts.order_by()

Specifies column sorting for query results. Can be chained multiple times to apply multi-column ordering.

```elscriptsignature
Posts.order_by(
  string:column,
  string:direction = 'ASC'
): Posts
```

#### Parameters

* **`column`** (`string`, _required_): Column name to sort by (e.g., `'ID'`, `'post_date'`, `'post_title'`, `'comment_count'`).
* **`direction`** (`string`, _optional_): Sorting direction (`'ASC'` or `'DESC'`). Defaults to `'ASC'`.

#### Example

```elscript
/* Sort published posts by creation date descending */
Posts.where('post_status', 'publish')
  .order_by('post_date', 'DESC')
  .get()
```

```elscript
/* Multi-column sorting: sort by post type ascending, then date descending */
Posts.where('post_status', 'publish')
  .order_by('post_type', 'ASC')
  .order_by('post_date', 'DESC')
  .get()
```

---

### Posts.limit()

Sets the maximum number of post records to return.

```elscriptsignature
Posts.limit(int:limit): Posts
```

#### Example

```elscript
/* Retrieve the 5 most recent published posts */
Posts.where('post_status', 'publish')
  .order_by('post_date', 'DESC')
  .limit(5)
  .get()
```

---

### Posts.offset()

Sets the number of rows to skip for pagination.

```elscriptsignature
Posts.offset(int:offset): Posts
```

#### Example

```elscript
/* Paginate query results: skip 20 rows and take 10 */
Posts.where('post_status', 'publish')
  .order_by('ID', 'DESC')
  .offset(20)
  .limit(10)
  .get()
```

---

### Posts.query()

Mirrors the `wp_posts` table into SQLite using accumulated conditions and options, then executes an arbitrary SQL `SELECT` query against the in-memory SQLite database. Resets builder state after execution.

```elscriptsignature
Posts.query(string:sql): array
```

#### Example

```elscript
/* Execute arbitrary aggregation query on mirrored posts table */
Posts.where('post_status', 'publish')
  .query('SELECT post_type, COUNT(*) AS total FROM wp_posts GROUP BY post_type ORDER BY total DESC')
```

:::info SQLite3 Extension Required
The SQLite3 PHP extension **must** be installed and enabled on your server for `Posts.query()` to work.
:::

---

### Posts.count()

Counts matching records in the MySQL database without buffering rows into memory or initializing an SQLite table. Resets builder state after execution.

```elscriptsignature
Posts.count(): int
```

#### Examples

```elscript
/* Count published posts */
Posts.where('post_type', 'post').where('post_status', 'publish').count()
```

```elscript
/* Count published pages authored by user 1 */
Posts.where('post_type', 'page').where('post_author', 1).count()
```

---

### Posts.to_sql()

Compiles and returns the SQL query string for the accumulated conditions without executing it. Resets builder state after compilation.

```elscriptsignature
Posts.to_sql(bool:is_count = false): string
```

#### Parameters

* **`is_count`** (`bool`, _optional_): When `true`, compiles a `SELECT COUNT(*)` query instead of a column projection. Defaults to `false`.

#### Examples

```elscript
/* Preview compiled SQL query with ordering and limits */
Posts.where('post_type', 'post')
  .where('post_status', 'publish')
  .order_by('post_date', 'DESC')
  .limit(10)
  .to_sql()
```

```elscript
/* Preview compiled count SQL query */
Posts.where('post_type', 'page').where('post_status', 'publish').to_sql(true)
```

---

## Reflection and Metadata Discovery

The `Posts` service provides helper utilities for inspecting registered post types, statuses, taxonomies, and taxonomy terms.

### Posts.types()

Retrieves a list of all registered WordPress post type names.

```elscriptsignature
Posts.types(): array
```

#### Return Value

Returns an indexed array of strings containing all registered post type slugs. Example:

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

#### Example

```elscript
/* List all registered post types */
Posts.types()
```

---

### Posts.statuses()

Retrieves a list of all registered WordPress post status names.

```elscriptsignature
Posts.statuses(): array
```

#### Return Value

Returns an indexed array of strings containing all registered post status slugs. Example:

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

#### Example

```elscript
/* List all registered post statuses */
Posts.statuses()
```

---

### Posts.taxonomies()

Retrieves all registered taxonomies in the WordPress installation, or taxonomies associated with a specific post type.

```elscriptsignature
Posts.taxonomies(
  string:post_type = ''
): array
```

#### Parameters

* **`post_type`** (`string`, _optional_): When provided, returns only the taxonomy slugs associated with that post type (via `get_object_taxonomies`). When omitted or empty, returns all registered taxonomies in the installation (via `get_taxonomies`). Defaults to `''`.

#### Return Value

Returns an indexed array of taxonomy slugs (e.g., `['category', 'post_tag', 'nav_menu', 'link_category', 'post_format']`).

#### Examples

```elscript
/* Retrieve all registered taxonomies */
Posts.taxonomies()
```

```elscript
/* Retrieve taxonomies registered for the 'post' post type */
Posts.taxonomies('post')
```

```elscript
/* Retrieve taxonomies registered for a custom post type */
Posts.taxonomies('product')
```

---

### Posts.terms()

Retrieves terms for a given taxonomy and formats them into an array of objects.

```elscriptsignature
Posts.terms(
  string:taxonomy,
  array:args = []
): array
```

#### Parameters

* **`taxonomy`** (`string`, _required_): The taxonomy slug (e.g., `'category'`, `'post_tag'`).
* **`args`** (`array`, _optional_): Arguments passed to WordPress `get_terms()`. Defaults to `[]`. The `hide_empty` argument defaults to `false` if not explicitly specified.

#### Return Value

Returns an indexed array of objects with the following fields:

| Field | Type | Description |
| :--- | :--- | :--- |
| `term_id` | `int` | Unique term identifier. |
| `name` | `string` | Public name of the term. |
| `slug` | `string` | URL-sanitized term slug. |
| `count` | `int` | Number of objects associated with the term. |

If the taxonomy does not exist or an error occurs, returns an empty array `[]`.

#### Examples

```elscript
/* Retrieve all categories including empty ones */
Posts.terms('category')
```

```elscript
/* Retrieve tags ordered by count descending */
Posts.terms('post_tag', { orderby: 'count', order: 'DESC', number: 10 })
```

---

## Forensic Statistics and Distribution

### Posts.stats()

Calculates a statistical breakdown of all records in the `wp_posts` table grouped by post type and status, automatically generating interactive visualizations in the Expression Lab console.

```elscriptsignature
Posts.stats(): array
```

#### What `stats()` Generates

When executed in Expression Lab, `Posts.stats()` queries MySQL and renders:

1. **Table Visualization**: Tabular distribution displaying `post_type`, `post_status`, and record `count`.
2. **Vega-Lite Bar Chart (`Graph: Posts distribution by type`)**: Interactive bar chart grouping counts by `post_type` and color-coding by `post_status`.

#### Return Value

Returns an array of associative arrays with the following structure:

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

#### Example

```elscript
/* Generate post distribution statistics and charts */
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

## Post Model Properties

Instances of the `Post` model represent an individual WordPress post, page, or custom post type record.

### Post.ID

The numeric primary key identifier of the post in the `wp_posts` table.

```elscriptsignature
Post.ID: ?int
```

* **Type**: `?int` (Read-only)
* **Description**: Database post ID mapped from `WP_Post::$ID`.

#### Example

```elscript
Posts.get(42).ID
```

---

### Post.title

The post title.

```elscriptsignature
Post.title: string
```

* **Type**: `string` (Read-only)
* **Description**: String value mapped from `WP_Post::$post_title`.

#### Example

```elscript
Posts.get(42).title
```

---

### Post.content

The main post content body.

```elscriptsignature
Post.content: string
```

* **Type**: `string` (Read-only)
* **Description**: String value mapped from `WP_Post::$post_content`.

#### Example

```elscript
Posts.get(42).content
```

---

### Post.excerpt

The post excerpt.

```elscriptsignature
Post.excerpt: string
```

* **Type**: `string` (Read-only)
* **Description**: String value mapped from `WP_Post::$post_excerpt`.

#### Example

```elscript
Posts.get(42).excerpt
```

---

### Post.status

The post status slug.

```elscriptsignature
Post.status: string
```

* **Type**: `string` (Read-only)
* **Description**: Status string (e.g., `'publish'`, `'draft'`, `'pending'`, `'private'`, `'trash'`) mapped from `WP_Post::$post_status`.

#### Example

```elscript
Posts.get(42).status
```

---

### Post.type

The post type slug.

```elscriptsignature
Post.type: string
```

* **Type**: `string` (Read-only)
* **Description**: Post type string (e.g., `'post'`, `'page'`, `'attachment'`, or a custom post type slug) mapped from `WP_Post::$post_type`.

#### Example

```elscript
Posts.get(42).type
```

---

### Post.slug

The URL-sanitized post slug.

```elscriptsignature
Post.slug: string
```

* **Type**: `string` (Read-only)
* **Description**: Post slug string mapped from `WP_Post::$post_name`.

#### Example

```elscript
Posts.get(42).slug
```

---

### Post.date

The post creation timestamp.

```elscriptsignature
Post.date: string
```

* **Type**: `string` (Read-only)
* **Description**: MySQL timestamp string formatted as `YYYY-MM-DD HH:MM:SS`, mapped from `WP_Post::$post_date`.

#### Example

```elscript
Posts.get(42).date
```

---

### Post.modified

The post last modified timestamp.

```elscriptsignature
Post.modified: string
```

* **Type**: `string` (Read-only)
* **Description**: MySQL timestamp string formatted as `YYYY-MM-DD HH:MM:SS`, mapped from `WP_Post::$post_modified`.

#### Example

```elscript
Posts.get(42).modified
```

---

### Post.author_id

The database ID of the user who authored the post.

```elscriptsignature
Post.author_id: int
```

* **Type**: `int` (Read-only)
* **Description**: Numeric author user ID mapped from `WP_Post::$post_author`.

#### Example

```elscript
Posts.get(42).author_id
```

---

### Post.parent_id

The database ID of the parent post, if hierarchical.

```elscriptsignature
Post.parent_id: int
```

* **Type**: `int` (Read-only)
* **Description**: Numeric parent post ID mapped from `WP_Post::$post_parent`. Returns `0` if the post has no parent.

#### Example

```elscript
Posts.get(42).parent_id
```

---

### Post.comment_count

The number of comments approved on the post.

```elscriptsignature
Post.comment_count: int
```

* **Type**: `int` (Read-only)
* **Description**: Integer count mapped from `WP_Post::$comment_count`.

#### Example

```elscript
Posts.get(42).comment_count
```

---

### Post.meta

The scoped `PostMeta` metadata repository instance associated with this post.

```elscriptsignature
Post.meta: ?PostMeta
```

* **Type**: `?PostMeta` (Read-only)
* **Description**: Provides metadata CRUD operations (`get`, `set`, `delete`, `all`) and query builder filtering scoped to this post's database ID.

#### Examples

```elscript
/* Retrieve specific post meta */
Posts.get(42).meta.get('_thumbnail_id')
```

```elscript
/* Retrieve all post metadata */
Posts.get(42).meta.all()
```

---

## Post Model Methods

### Post.get_permalink()

Retrieves the full permalink URL for the post.

```elscriptsignature
Post.get_permalink(): string|false
```

#### Return Value

Returns the permalink URL string via `get_permalink()`, or `false` if resolution fails.

#### Example

```elscript
/* Get the permalink URL of a post */
Posts.get(42).get_permalink()
```

---

### Post.get_terms()

Retrieves taxonomy terms associated with the post.

```elscriptsignature
Post.get_terms(string:taxonomy): array
```

#### Parameters

* **`taxonomy`** (`string`, _required_): The taxonomy slug (e.g., `'category'`, `'post_tag'`).

#### Return Value

Returns an array of objects representing assigned terms via `get_the_terms()`. Each term object contains:

| Field | Type | Description |
| :--- | :--- | :--- |
| `term_id` | `int` | Unique term identifier. |
| `name` | `string` | Term name. |
| `slug` | `string` | Term slug. |

If no terms are assigned or an error occurs, returns an empty array `[]`.

#### Examples

```elscript
/* Retrieve categories assigned to the post */
Posts.get(42).get_terms('category')
```

```elscript
/* Retrieve tags assigned to the post */
Posts.get(42).get_terms('post_tag')
```

---

## Post Metadata Management

Post metadata operations are accessed through the `meta` property on a `Post` model instance (e.g., `Posts.get(42).meta`).

### PostMeta.get()

Retrieves a metadata value for the post using `get_post_meta()`, or executes accumulated query builder conditions on `wp_postmeta` when called without arguments.

```elscriptsignature
PostMeta.get(
  ?string:key = null,
  bool:single = true
): mixed
```

#### Parameters

* **`key`** (`?string`, _optional_): The metadata key name. If omitted or `null`, executes accumulated query builder conditions on `wp_postmeta` for this post. Defaults to `null`.
* **`single`** (`bool`, _optional_): Whether to return a single value (`true`) or an array of values (`false`). Defaults to `true`.

#### Return Value

Returns the stored metadata value, or an array of query builder results when `$key` is `null`.

#### Examples

```elscript
/* Retrieve a single metadata value */
Posts.get(42).meta.get('_edit_lock')
```

```elscript
/* Retrieve all values for a non-unique meta key */
Posts.get(42).meta.get('custom_attachment_ids', false)
```

---

### PostMeta.all()

Retrieves all metadata entries for the post from `wp_postmeta`. Values are automatically processed with `maybe_unserialize()`.

```elscriptsignature
PostMeta.all(): array
```

#### Return Value

Returns an associative array mapping each meta key to its value (or array of values for multi-entry keys).

#### Example

```elscript
/* Inspect all metadata registered for a post */
Posts.get(42).meta.all()
```

---

### PostMeta.set()

Sets or updates a metadata entry for the post.

```elscriptsignature
PostMeta.set(
  string:key,
  mixed:value
): bool
```

#### Parameters

* **`key`** (`string`, _required_): The metadata key name.
* **`value`** (`mixed`, _required_): The value to store.

#### Return Value

Returns `true` if the database was updated, `false` otherwise.

:::warning Write Protection Guard
Throws an exception if `EXPRESSION_LAB_DATABASE_READONLY` is set to `true`.
:::

#### Example

```elscript
/* Update custom metadata */
Posts.get(42).meta.set('reading_time_minutes', 4)
```

---

### PostMeta.delete()

Deletes a metadata entry for the post from `wp_postmeta`.

```elscriptsignature
PostMeta.delete(string:key): bool
```

#### Parameters

* **`key`** (`string`, _required_): The metadata key name to delete.

#### Return Value

Returns `true` if successfully deleted, `false` otherwise.

:::warning Write Protection Guard
Throws an exception if `EXPRESSION_LAB_DATABASE_READONLY` is set to `true`.
:::

#### Example

```elscript
/* Delete metadata entry */
Posts.get(42).meta.delete('cache_hash')
```

---

## Query Builder on Post Metadata

The `PostMeta` model implements the `QueryBuilder` trait. All query builder operations chained on `Post.meta` are automatically scoped to the parent post via `post_id = Post.ID`.

Mirrored metadata records are placed into an in-memory SQLite table and discarded upon buffer flush.

### PostMeta.where()

Appends a `WHERE` condition combined with `AND` logic within the current condition group on `wp_postmeta`.

```elscriptsignature
PostMeta.where(
  string:column,
  mixed:operator_or_value,
  mixed:value = null
): PostMeta
```

#### Call Signatures

* **Equality** (Two arguments): `post.meta.where('column', 'value')`
* **IN condition** (Two arguments with array): `post.meta.where('column', ['val1', 'val2'])`
* **Comparison Operator** (Three arguments): `post.meta.where('column', 'operator', 'value')`

#### Supported Comparison Operators

| Operator | Description | Example |
| :--- | :--- | :--- |
| `'='` | Exact equality (default) | `Posts.get(42).meta.where('meta_key', '=', '_thumbnail_id').get()` |
| `'>'` | Greater than | `Posts.get(42).meta.where('meta_id', '>', 100).get()` |
| `'>='` | Greater than or equal | `Posts.get(42).meta.where('meta_id', '>=', 100).get()` |
| `'<'` | Less than | `Posts.get(42).meta.where('meta_id', '<', 500).get()` |
| `'<='` | Less than or equal | `Posts.get(42).meta.where('meta_id', '<=', 500).get()` |
| `'!='`, `'<>'` | Not equal | `Posts.get(42).meta.where('meta_key', '!=', '_edit_lock').get()` |
| `'like'` | SQL LIKE pattern matching | `Posts.get(42).meta.where('meta_key', 'like', '_oembed_%').get()` |
| `'not like'` | Negated SQL LIKE pattern matching | `Posts.get(42).meta.where('meta_key', 'not like', '_wp_%').get()` |
| `'in'` | Set membership (value must be an array) | `Posts.get(42).meta.where('meta_key', 'in', ['_thumbnail_id', '_edit_last']).get()` |
| `'not in'` | Negated set membership (value must be an array) | `Posts.get(42).meta.where('meta_key', 'not in', ['_edit_lock', '_edit_last']).get()` |
| `'between'` | Range check (value must be a two-element array `[min, max]`) | `Posts.get(42).meta.where('meta_id', 'between', [100, 200]).get()` |

#### Examples

```elscript
/* Query metadata matching a pattern for a specific post */
Posts.get(42).meta.where('meta_key', 'like', '_wp_%').get()
```

```elscript
/* Query specific metadata keys */
Posts.get(42).meta.where('meta_key', 'in', ['_thumbnail_id', '_wp_page_template']).get()
```

---

### PostMeta.or_where()

Appends a `WHERE` condition that initiates a new condition group combined with preceding groups via `OR` logic. Conditions added within each group using `where()` are combined via `AND`. Scoping to `post_id = Post.ID` is preserved across all groups.

```elscriptsignature
PostMeta.or_where(
  string:column,
  mixed:operator_or_value,
  mixed:value = null
): PostMeta
```

#### Example

```elscript
/* Query metadata keys matching either of two keys */
Posts.get(42).meta.where('meta_key', '_thumbnail_id')
  .or_where('meta_key', '_wp_page_template')
  .get()
```

---

### PostMeta.order_by()

Specifies column sorting for mirrored metadata query results.

```elscriptsignature
PostMeta.order_by(
  string:column,
  string:direction = 'ASC'
): PostMeta
```

#### Parameters

* **`column`** (`string`, _required_): Column name to sort by (`'meta_id'`, `'meta_key'`, `'meta_value'`).
* **`direction`** (`string`, _optional_): Sorting direction (`'ASC'` or `'DESC'`). Defaults to `'ASC'`.

#### Example

```elscript
/* Order metadata rows by meta_id descending */
Posts.get(42).meta.where('meta_key', 'like', 'custom_%')
  .order_by('meta_id', 'DESC')
  .get()
```

---

### PostMeta.limit()

Sets the maximum number of metadata rows to return.

```elscriptsignature
PostMeta.limit(int:limit): PostMeta
```

#### Example

```elscript
/* Retrieve first 5 metadata rows */
Posts.get(42).meta.order_by('meta_id', 'ASC')
  .limit(5)
  .get()
```

---

### PostMeta.offset()

Sets the number of rows to skip for pagination across metadata rows.

```elscriptsignature
PostMeta.offset(int:offset): PostMeta
```

#### Example

```elscript
/* Paginate metadata rows */
Posts.get(42).meta.order_by('meta_id', 'ASC')
  .offset(5)
  .limit(5)
  .get()
```

---

### PostMeta.query()

Mirrors the `wp_postmeta` table into SQLite using accumulated conditions (including the parent `post_id` constraint) and options, then executes an arbitrary SQL `SELECT` query against the in-memory SQLite database. Resets builder state after execution.

```elscriptsignature
PostMeta.query(string:sql): array
```

#### Example

```elscript
/* Execute raw SQL query against mirrored post metadata */
Posts.get(42).meta.where('meta_key', 'like', '_%')
  .query('SELECT meta_id, meta_key, meta_value FROM wp_postmeta ORDER BY meta_id ASC')
```

:::info SQLite3 Extension Required
The SQLite3 PHP extension **must** be installed and enabled on your server for `PostMeta.query()` to work.
:::

---

### PostMeta.count()

Counts matching metadata records for the post in MySQL without buffering rows into memory or initializing an SQLite table. Automatically enforces `post_id` scoping. Resets builder state after execution.

```elscriptsignature
PostMeta.count(): int
```

#### Examples

```elscript
/* Count all metadata rows for a post */
Posts.get(42).meta.count()
```

```elscript
/* Count hidden/private metadata keys */
Posts.get(42).meta.where('meta_key', 'like', '_%').count()
```

---

### PostMeta.to_sql()

Compiles and returns the SQL query string for the post's metadata query without executing it. Automatically enforces `post_id = Post.ID` scoping. Resets builder state after compilation.

```elscriptsignature
PostMeta.to_sql(bool:is_count = false): string
```

#### Parameters

* **`is_count`** (`bool`, _optional_): When `true`, compiles a `SELECT COUNT(*)` query instead of a column projection. Defaults to `false`.

#### Examples

```elscript
/* Preview metadata query SQL */
Posts.get(42).meta.where('meta_key', 'like', 'billing_%').order_by('meta_id', 'DESC').to_sql()
```

```elscript
/* Preview metadata count SQL */
Posts.get(42).meta.where('meta_key', 'like', 'billing_%').to_sql(true)
```
