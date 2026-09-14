---
id: media
title: Media
sidebar_position: 6
---

# Media

Find media attachments, discover unattached or orphaned uploads, inspect MIME types, and resolve size-specific URLs and dimensions.

The media management architecture consists of three interconnected components:

1. **Media Service (`Media`)**: The primary entrypoint for media lookups by numeric ID, slug, title, or URL/path, orphan attachment discovery, MIME type inspection, deletion, and query builder chaining.
2. **Attachment Model (`Attachment`)**: An entity representing an individual WordPress media attachment, exposing normalized properties (`ID`, `title`, `caption`, `description`, `alt`, `mime_type`, `url`, `file_path`, `filesize`, `filesize_human`, `dimensions`, `sizes`, `date`, `modified`, `author_id`, `parent_id`), size-specific resolution methods (`get_url()`, `get_path()`, `get_dimensions()`), and lifecycle deletion (`delete()`).
3. **PostMeta Model (`PostMeta`)**: A metadata repository accessible via `attachment.meta` that provides metadata CRUD operations and query builder integration scoped to the parent attachment record.

---

## Accessing and Retrieving Media

### Media.get()

Retrieves a single `Attachment` model instance by identifier (numeric ID, slug, title, URL, relative path, or filename), or executes accumulated query builder conditions when called without arguments.

```elscriptsignature
Media.get(
  int|string|null:identifier = null
): Attachment|array|null
```

#### Resolution Modes

* **By Numeric ID** (`int` or numeric string):
  Queries via `get_post(absint($identifier))` and verifies that `post_type === 'attachment'`. Returns an `Attachment` model instance, or `null` if the attachment does not exist.
* **By URL, Path, or Filename** (`string` matching URL formats, starting with `http://` or `https://`, containing `/` path separators, or ending with a file extension like `.jpg`, `.png`, `.pdf`):
  1. If an ID is resolved, queries `get_post()` and verifies `post_type === 'attachment'`.
  2. Returns an `Attachment` model instance, or `null` if no matching attachment record is found.
* **By Slug / Attachment Name** (`string`):
  Queries via `get_posts()` with `name = sanitize_title($identifier)` across `post_type = 'attachment'` and `post_status = 'any'`. Returns an `Attachment` model instance, or `null` if no match is found.
* **By Title** (`string`):
  Queries via `get_posts()` with `title = $identifier` across `post_type = 'attachment'` and `post_status = 'any'`. Returns an `Attachment` model instance, or `null` if no match is found.
* **Without Arguments** (`null`):
  Executes accumulated `QueryBuilder` conditions against `wp_posts`, mirrors matching rows into an in-memory SQLite table, flushes the buffer, and returns the results as an array of row objects.

#### Examples

```elscript
/* Look up media attachment by numeric ID */
Media.get(42)
```

```elscript
/* Look up attachment by original file URL */
Media.get('https://example.com/wp-content/uploads/2026/08/hero-banner.jpg')
```

```elscript
/* Look up attachment using a generated thumbnail dimension URL */
Media.get('https://example.com/wp-content/uploads/2026/08/hero-banner-300x200.jpg')
```

```elscript
/* Look up attachment using a WordPress scaled image URL */
Media.get('https://example.com/wp-content/uploads/2026/08/hero-banner-scaled.jpg')
```

```elscript
/* Look up attachment by relative upload path */
Media.get('2026/08/hero-banner.jpg')
```

```elscript
/* Look up attachment by slug */
Media.get('hero-banner')
```

---

### Media.unattached()

Retrieves unattached media attachments (orphan files in the media library where `post_parent = 0` and `post_status = 'inherit'`).

```elscriptsignature
Media.unattached(int:limit = 50): array
```

#### Parameters

* **`limit`** (`int`, _optional_): Maximum number of unattached attachments to return. Defaults to `50`.

#### Return Value

Returns an array of `Attachment` model instances representing unattached media items.

#### Examples

```elscript
/* Retrieve up to 50 unattached media items */
Media.unattached()
```

```elscript
/* Retrieve up to 10 unattached media items */
Media.unattached(10)
```

---

### Media.mime_types()

Retrieves an alphabetical list of all distinct MIME types currently stored in the WordPress media library.

```elscriptsignature
Media.mime_types(): array
```

#### Return Value

Returns an indexed array of strings containing unique MIME types found in `wp_posts` where `post_type = 'attachment'` (e.g., `['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'video/mp4']`).

#### Example

```elscript
/* Retrieve all distinct MIME types in the media library */
Media.mime_types()
```

---

### Media.delete()

Deletes a media attachment record from the database and permanently removes all associated physical files (original file and generated thumbnail sub-sizes) from the server filesystem.

```elscriptsignature
Media.delete(
  int|string:identifier,
  bool:force = false
): bool
```

#### Parameters

* **`identifier`** (`int|string`, _required_): Attachment ID, slug, title, URL, or path.
* **`force`** (`bool`, _optional_): When `true`, bypasses the trash and permanently deletes the record and files immediately. Defaults to `false`.

#### Return Value

Returns `true` if the attachment was resolved and deleted, or `false` on failure.

:::warning Write Protection Guard
Deleting attachments mutates both the database and the server filesystem.

Throws an exception if `EXPRESSION_LAB_DATABASE_READONLY` or `EXPRESSION_LAB_FILESYSTEM_READONLY` is set to `true`. Both constants must be set to `false` in `wp-config.php`.
:::

#### Examples

```elscript
/* Send attachment to trash by numeric ID */
Media.delete(42)
```

```elscript
/* Permanently delete attachment and physical files bypassing trash */
Media.delete(42, true)
```

```elscript
/* Delete attachment identified by thumbnail URL */
Media.delete('https://example.com/wp-content/uploads/2026/08/obsolete-image-300x200.jpg', true)
```

---

## Media Query Builder

The `Media` service incorporates chainable query builder methods targeting the `wp_posts` table. All query builder operations chained on `Media` are automatically scoped to attachments via an internal base condition (`post_type = 'attachment'`). Conditions are accumulated into groups and compiled into parameterized SQL when executed via `get()` or `query()`. Mirrored data is placed into an in-memory SQLite table and discarded upon buffer flush.

### Media.where()

Appends a `WHERE` condition combined with `AND` logic within the current condition group.

```elscriptsignature
Media.where(
  string:column,
  mixed:operator_or_value,
  mixed:value = null
): Media
```

#### Call Signatures

* **Equality** (Two arguments): `Media.where('column', 'value')`
* **IN condition** (Two arguments with array): `Media.where('column', ['val1', 'val2'])`
* **Comparison Operator** (Three arguments): `Media.where('column', 'operator', 'value')`

#### Supported Comparison Operators

The three-argument form `Media.where(column, operator, value)` accepts standard SQL comparison operators as strings:

| Operator | Description | Example |
| :--- | :--- | :--- |
| `'='` | Exact equality (default) | `Media.where('post_mime_type', '=', 'image/png').get()`, or `Media.where('post_mime_type', 'image/png').get()` |
| `'>'` | Greater than | `Media.where('ID', '>', 100).get()` |
| `'>='` | Greater than or equal | `Media.where('ID', '>=', 100).get()` |
| `'<'` | Less than | `Media.where('ID', '<', 50).get()` |
| `'<='` | Less than or equal | `Media.where('ID', '<=', 50).get()` |
| `'!='`, `'<>'` | Not equal | `Media.where('post_mime_type', '!=', 'image/jpeg').get()` |
| `'like'` | SQL LIKE pattern matching | `Media.where('post_title', 'like', '%logo%').get()` |
| `'not like'` | Negated SQL LIKE pattern matching | `Media.where('post_title', 'not like', '%draft%').get()` |
| `'in'` | Set membership (value must be an array) | `Media.where('post_mime_type', 'in', ['image/jpeg', 'image/png']).get()` |
| `'not in'` | Negated set membership (value must be an array) | `Media.where('post_mime_type', 'not in', ['image/gif', 'image/bmp']).get()` |
| `'between'` | Range check (value must be a two-element array `[min, max]`) | `Media.where('post_date', 'between', ['2026-01-01', '2026-12-31']).get()` |

:::note QueryBuilder vs Database.mirror Syntax
The `QueryBuilder` methods (`where()`, `or_where()`) accept standard SQL operator strings (`'>'`, `'like'`, `'between'`) and compile them internally into the `$`-prefixed recursive condition objects expected by `Database.mirror()`.
:::

#### Examples

```elscript
/* Query JPEG images */
Media.where('post_mime_type', 'image/jpeg').get()
```

```elscript
/* Query media uploaded by a specific user */
Media.where('post_author', 1).get()
```

```elscript
/* Query PDF documents matching a title pattern */
Media.where('post_mime_type', 'application/pdf').where('post_title', 'like', '%Report%').get()
```

---

### Media.or_where()

Appends a `WHERE` condition that initiates a new condition group combined with preceding groups via `OR` logic. Conditions added within each group using `where()` are combined via `AND`. Scoping to `post_type = 'attachment'` is preserved across all groups.

```elscriptsignature
Media.or_where(
  string:column,
  mixed:operator_or_value,
  mixed:value = null
): Media
```

#### Example

```elscript
/* Retrieve attachments that are either PNG images or PDF documents */
Media.where('post_mime_type', 'image/png')
  .or_where('post_mime_type', 'application/pdf')
  .get()
```

---

### Media.order_by()

Specifies column sorting for query results. Can be chained multiple times to apply multi-column ordering.

```elscriptsignature
Media.order_by(
  string:column,
  string:direction = 'ASC'
): Media
```

#### Parameters

* **`column`** (`string`, _required_): Column name to sort by (e.g., `'ID'`, `'post_date'`, `'post_title'`, `'post_mime_type'`).
* **`direction`** (`string`, _optional_): Sorting direction (`'ASC'` or `'DESC'`). Defaults to `'ASC'`.

#### Examples

```elscript
/* Sort media attachments by creation date descending */
Media.order_by('post_date', 'DESC').get()
```

```elscript
/* Multi-column sorting: sort by MIME type ascending, then ID descending */
Media.order_by('post_mime_type', 'ASC')
  .order_by('ID', 'DESC')
  .get()
```

---

### Media.limit()

Sets the maximum number of media attachment records to return.

```elscriptsignature
Media.limit(int:limit): Media
```

#### Example

```elscript
/* Retrieve the 10 most recent media attachments */
Media.order_by('post_date', 'DESC')
  .limit(10)
  .get()
```

---

### Media.offset()

Sets the number of rows to skip for pagination.

```elscriptsignature
Media.offset(int:offset): Media
```

#### Example

```elscript
/* Skip 20 records and retrieve the next 10 */
Media.order_by('ID', 'DESC')
  .offset(20)
  .limit(10)
  .get()
```

---

### Media.query()

Mirrors the `wp_posts` table into SQLite using accumulated conditions (including the `post_type = 'attachment'` constraint) and options, then executes an arbitrary SQL `SELECT` query against the in-memory SQLite database. Resets builder state after execution.

```elscriptsignature
Media.query(string:sql): array
```

#### Example

```elscript
/* Execute arbitrary SQL aggregation on mirrored media records */
Media.query('SELECT post_mime_type, COUNT(*) AS total FROM wp_posts GROUP BY post_mime_type ORDER BY total DESC')
```

:::info SQLite3 Extension Required
The SQLite3 PHP extension **must** be installed and enabled on your server for `Media.query()` to work.
:::

---

### Media.count()

Counts matching records in the MySQL database without buffering rows into memory or initializing an SQLite table. Automatically enforces `post_type = 'attachment'`. Resets builder state after execution.

```elscriptsignature
Media.count(): int
```

#### Examples

```elscript
/* Count total attachments in the media library */
Media.count()
```

```elscript
/* Count PNG images uploaded by user 1 */
Media.where('post_mime_type', 'image/png').where('post_author', 1).count()
```

---

### Media.to_sql()

Compiles and returns the SQL query string for the accumulated conditions without executing it. Automatically enforces `post_type = 'attachment'` scoping. Resets builder state after compilation.

```elscriptsignature
Media.to_sql(bool:is_count = false): string
```

#### Parameters

* **`is_count`** (`bool`, _optional_): When `true`, compiles a `SELECT COUNT(*)` query instead of a column projection. Defaults to `false`.

#### Examples

```elscript
/* Preview compiled SELECT SQL query */
Media.where('post_mime_type', 'image/webp')
  .order_by('ID', 'DESC')
  .limit(5)
  .to_sql()
```

```elscript
/* Preview compiled COUNT SQL query */
Media.where('post_mime_type', 'image/webp').to_sql(true)
```

---

## Forensic Statistics and Distribution

### Media.stats()

Calculates a statistical breakdown of all attachments in the `wp_posts` table grouped by MIME type, automatically generating interactive visualizations in the Expression Lab console.

```elscriptsignature
Media.stats(): array
```

#### What stats() Generates

When executed in Expression Lab, `Media.stats()` queries MySQL and renders:

1. **Table Visualization (`Table: Media distribution by MIME type`)**: Tabular distribution displaying `mime_type` and record `count`.
2. **Vega-Lite Radial Chart (`Graph: Media distribution by MIME type`)**: Interactive radial (pie) chart grouping counts by `mime_type`.

#### Return Value

Returns an array of associative arrays with the following structure:

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

#### Example

```elscript
/* Generate media MIME type distribution statistics and visualizations */
Media.stats()
```

---

## Attachment Model Properties

Instances of the `Attachment` model represent an individual WordPress media attachment record and its associated physical file metadata.

### Attachment.ID

The numeric primary key identifier of the attachment in the `wp_posts` table.

```elscriptsignature
Attachment.ID: ?int
```

* **Type**: `?int` (Read-only)
* **Description**: Database post ID mapped from `WP_Post::$ID`.

#### Example

```elscript
Media.get(42).ID
```

---

### Attachment.title

The attachment title.

```elscriptsignature
Attachment.title: string
```

* **Type**: `string` (Read-only)
* **Description**: String value mapped from `WP_Post::$post_title`.

#### Example

```elscript
Media.get(42).title
```

---

### Attachment.caption

The attachment caption.

```elscriptsignature
Attachment.caption: string
```

* **Type**: `string` (Read-only)
* **Description**: String value mapped from `WP_Post::$post_excerpt`.

#### Example

```elscript
Media.get(42).caption
```

---

### Attachment.description

The attachment description body.

```elscriptsignature
Attachment.description: string
```

* **Type**: `string` (Read-only)
* **Description**: String value mapped from `WP_Post::$post_content`.

#### Example

```elscript
Media.get(42).description
```

---

### Attachment.alt

The alternative text for images.

```elscriptsignature
Attachment.alt: string
```

* **Type**: `string` (Read-only)
* **Description**: Value retrieved from `_wp_attachment_image_alt` post metadata.

#### Example

```elscript
Media.get(42).alt
```

---

### Attachment.mime_type

The MIME type of the uploaded media file.

```elscriptsignature
Attachment.mime_type: string
```

* **Type**: `string` (Read-only)
* **Description**: String value (e.g., `'image/jpeg'`, `'image/png'`, `'application/pdf'`) mapped from `WP_Post::$post_mime_type`.

#### Example

```elscript
Media.get(42).mime_type
```

---

### Attachment.url

The full public URL to the original media file.

```elscriptsignature
Attachment.url: string
```

* **Type**: `string` (Read-only)
* **Description**: URL string resolved via `wp_get_attachment_url()`.

#### Example

```elscript
Media.get(42).url
```

---

### Attachment.file_path

The absolute filesystem path to the original media file on the server.

```elscriptsignature
Attachment.file_path: string
```

* **Type**: `string` (Read-only)
* **Description**: Absolute path string resolved via `get_attached_file()`.

#### Example

```elscript
Media.get(42).file_path
```

---

### Attachment.filesize

The physical file size in bytes of the original media file on disk.

```elscriptsignature
Attachment.filesize: int
```

* **Type**: `int` (Read-only)
* **Description**: Integer byte count computed from `filesize($file_path)`. Returns `0` if the file does not exist on disk.

#### Example

```elscript
Media.get(42).filesize
```

---

### Attachment.filesize_human

The formatted, human-readable file size of the original media file.

```elscriptsignature
Attachment.filesize_human: string
```

* **Type**: `string` (Read-only)
* **Description**: Formatted string with appropriate binary unit suffix (e.g., `'1.24 MB'`, `'450.5 KB'`, `'0 B'`).

#### Example

```elscript
Media.get(42).filesize_human
```

---

### Attachment.dimensions

The width and height dimensions of the original media file.

```elscriptsignature
Attachment.dimensions: array
```

* **Type**: `array` (Read-only)
* **Description**: Associative array with integer keys `width` and `height` extracted from `_wp_attachment_metadata`. Empty array `[]` if unavailable or non-image.

#### Example

```elscript
Media.get(42).dimensions
```

---

### Attachment.sizes

A dictionary mapping each registered image sub-size name to its full metadata and paths.

```elscriptsignature
Attachment.sizes: array
```

* **Type**: `array` (Read-only)
* **Description**: Associative array where each key is a size slug (e.g., `'thumbnail'`, `'medium'`, `'medium_large'`, `'large'`) mapping to an object with the following fields:

| Field | Type | Description |
| :--- | :--- | :--- |
| `file` | `string` | Filename of the generated sub-size (e.g., `'banner-300x200.jpg'`). |
| `width` | `int` | Width of the sub-size in pixels. |
| `height` | `int` | Height of the sub-size in pixels. |
| `mime_type` | `string` | MIME type of the sub-size file. |
| `url` | `string` | Full public URL to the sub-size image. |
| `path` | `string` | Absolute server filesystem path to the sub-size file. |
| `filesize` | `int` | File size in bytes (if provided by WordPress metadata). |

#### Example

```elscript
Media.get(42).sizes
```

---

### Attachment.date

The attachment creation timestamp.

```elscriptsignature
Attachment.date: string
```

* **Type**: `string` (Read-only)
* **Description**: MySQL timestamp string formatted as `YYYY-MM-DD HH:MM:SS`, mapped from `WP_Post::$post_date`.

#### Example

```elscript
Media.get(42).date
```

---

### Attachment.modified

The attachment last modified timestamp.

```elscriptsignature
Attachment.modified: string
```

* **Type**: `string` (Read-only)
* **Description**: MySQL timestamp string formatted as `YYYY-MM-DD HH:MM:SS`, mapped from `WP_Post::$post_modified`.

#### Example

```elscript
Media.get(42).modified
```

---

### Attachment.author_id

The database ID of the user who uploaded the media attachment.

```elscriptsignature
Attachment.author_id: int
```

* **Type**: `int` (Read-only)
* **Description**: Numeric author user ID mapped from `WP_Post::$post_author`.

#### Example

```elscript
Media.get(42).author_id
```

---

### Attachment.parent_id

The database ID of the parent post the attachment is attached to.

```elscriptsignature
Attachment.parent_id: int
```

* **Type**: `int` (Read-only)
* **Description**: Numeric parent post ID mapped from `WP_Post::$post_parent`. Returns `0` if the attachment is unattached/orphan.

#### Example

```elscript
Media.get(42).parent_id
```

---

### Attachment.meta

The scoped `PostMeta` metadata repository instance associated with this attachment record.

```elscriptsignature
Attachment.meta: ?PostMeta
```

* **Type**: `?PostMeta` (Read-only)
* **Description**: Provides metadata CRUD operations (`get`, `set`, `delete`, `all`) and query builder filtering scoped to this attachment's database ID.

#### Examples

```elscript
/* Retrieve attached file path metadata */
Media.get(42).meta.get('_wp_attached_file')
```

```elscript
/* Retrieve all metadata registered for the attachment */
Media.get(42).meta.all()
```

---

## Attachment Model Methods

### Attachment.get_url()

Retrieves the full public URL for a specific registered image size or the original media file.

```elscriptsignature
Attachment.get_url(string:size = 'full'): string
```

#### Parameters

* **`size`** (`string`, _optional_): Registered image size slug (e.g., `'thumbnail'`, `'medium'`, `'large'`, `'full'`). Defaults to `'full'`.

#### Return Value

Returns the resolved URL string via `wp_get_attachment_image_src()` or the model's internal sizes registry. Returns the original file URL if `'full'` or empty.

#### Examples

```elscript
/* Get full/original file URL */
Media.get(42).get_url()
```

```elscript
/* Get medium thumbnail URL */
Media.get(42).get_url('medium')
```

```elscript
/* Get custom registered size URL */
Media.get(42).get_url('thumbnail')
```

---

### Attachment.get_path()

Retrieves the absolute filesystem path on the server for a specific registered image size or the original media file.

```elscriptsignature
Attachment.get_path(string:size = 'full'): string
```

#### Parameters

* **`size`** (`string`, _optional_): Registered image size slug (e.g., `'thumbnail'`, `'medium'`, `'large'`, `'full'`). Defaults to `'full'`.

#### Return Value

Returns the absolute filesystem path string to the specified size file. Returns the original `file_path` if `'full'` or empty.

#### Examples

```elscript
/* Get absolute filesystem path to original file */
Media.get(42).get_path()
```

```elscript
/* Get absolute filesystem path to medium thumbnail */
Media.get(42).get_path('medium')
```

---

### Attachment.get_dimensions()

Retrieves the pixel dimensions (`width` and `height`) for a specific registered image size or the original image.

```elscriptsignature
Attachment.get_dimensions(string:size = 'full'): array
```

#### Parameters

* **`size`** (`string`, _optional_): Registered image size slug (e.g., `'thumbnail'`, `'medium'`, `'large'`, `'full'`). Defaults to `'full'`.

#### Return Value

Returns an associative array with integer keys `width` and `height` (e.g., `{ width: 300, height: 200 }`).

#### Examples

```elscript
/* Get dimensions of original image */
Media.get(42).get_dimensions()
```

```elscript
/* Get dimensions of medium thumbnail */
Media.get(42).get_dimensions('medium')
```

---

### Attachment.delete()

Deletes this attachment record from the database and permanently removes all associated physical files from the filesystem.

```elscriptsignature
Attachment.delete(bool:force = false): bool
```

#### Parameters

* **`force`** (`bool`, _optional_): When `true`, bypasses the trash and permanently deletes the record and files immediately. Defaults to `false`.

#### Return Value

Returns `true` on successful deletion, or `false` on failure.

:::warning Write Protection Guard
Deleting an attachment mutates both the database and the server filesystem.

Throws an exception if `EXPRESSION_LAB_DATABASE_READONLY` or `EXPRESSION_LAB_FILESYSTEM_READONLY` is set to `true`. Both constants must be set to `false` in `wp-config.php` to enable deletion.
:::

#### Examples

```elscript
/* Send attachment to trash */
Media.get(42).delete()
```

```elscript
/* Permanently delete attachment and physical files */
Media.get(42).delete(true)
```

---

## Attachment Metadata Management

Attachment metadata operations are accessed through the `meta` property on an `Attachment` model instance (e.g., `Media.get(42).meta`).

### PostMeta.get()

Retrieves a metadata value for the attachment using `get_post_meta()`, or executes accumulated query builder conditions on `wp_postmeta` when called without arguments.

```elscriptsignature
PostMeta.get(
  ?string:key = null,
  bool:single = true
): mixed
```

#### Parameters

* **`key`** (`?string`, _optional_): The metadata key name. If omitted or `null`, executes accumulated query builder conditions on `wp_postmeta` for this attachment. Defaults to `null`.
* **`single`** (`bool`, _optional_): Whether to return a single value (`true`) or an array of values (`false`). Defaults to `true`.

#### Return Value

Returns the stored metadata value, or an array of query builder results when `$key` is `null`.

#### Examples

```elscript
/* Retrieve a single metadata value */
Media.get(42).meta.get('_wp_attachment_image_alt')
```

```elscript
/* Retrieve all values for a non-unique meta key */
Media.get(42).meta.get('custom_tags', false)
```

---

### PostMeta.all()

Retrieves all metadata entries for the attachment from `wp_postmeta`. Values are automatically processed with `maybe_unserialize()`.

```elscriptsignature
PostMeta.all(): array
```

#### Return Value

Returns an associative array mapping each meta key to its value (or array of values for multi-entry keys).

#### Example

```elscript
/* Inspect all metadata registered for an attachment */
Media.get(42).meta.all()
```

---

### PostMeta.set()

Sets or updates a metadata entry for the attachment.

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
Media.get(42).meta.set('license_type', 'CC-BY-4.0')
```

---

### PostMeta.delete()

Deletes a metadata entry for the attachment from `wp_postmeta`.

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
Media.get(42).meta.delete('temp_import_hash')
```

---

## Query Builder on Attachment Metadata

The `PostMeta` model implements the `QueryBuilder` trait. All query builder operations chained on `Attachment.meta` are automatically scoped to the parent attachment via `post_id = Attachment.ID`.

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

* **Equality** (Two arguments): `attachment.meta.where('column', 'value')`
* **IN condition** (Two arguments with array): `attachment.meta.where('column', ['val1', 'val2'])`
* **Comparison Operator** (Three arguments): `attachment.meta.where('column', 'operator', 'value')`

#### Supported Comparison Operators

| Operator | Description | Example |
| :--- | :--- | :--- |
| `'='` | Exact equality (default) | `Media.get(42).meta.where('meta_key', '=', '_wp_attached_file').get()` |
| `'>'` | Greater than | `Media.get(42).meta.where('meta_id', '>', 100).get()` |
| `'>='` | Greater than or equal | `Media.get(42).meta.where('meta_id', '>=', 100).get()` |
| `'<'` | Less than | `Media.get(42).meta.where('meta_id', '<', 500).get()` |
| `'<='` | Less than or equal | `Media.get(42).meta.where('meta_id', '<=', 500).get()` |
| `'!='`, `'<>'` | Not equal | `Media.get(42).meta.where('meta_key', '!=', '_wp_attached_file').get()` |
| `'like'` | SQL LIKE pattern matching | `Media.get(42).meta.where('meta_key', 'like', '_wp_%').get()` |
| `'not like'` | Negated SQL LIKE pattern matching | `Media.get(42).meta.where('meta_key', 'not like', '_wp_%').get()` |
| `'in'` | Set membership (value must be an array) | `Media.get(42).meta.where('meta_key', 'in', ['_wp_attached_file', '_wp_attachment_metadata']).get()` |
| `'not in'` | Negated set membership (value must be an array) | `Media.get(42).meta.where('meta_key', 'not in', ['_wp_attached_file', '_edit_lock']).get()` |
| `'between'` | Range check (value must be a two-element array `[min, max]`) | `Media.get(42).meta.where('meta_id', 'between', [100, 200]).get()` |

#### Examples

```elscript
/* Query metadata keys starting with _wp_ */
Media.get(42).meta.where('meta_key', 'like', '_wp_%').get()
```

```elscript
/* Query specific metadata keys */
Media.get(42).meta.where('meta_key', 'in', ['_wp_attached_file', '_wp_attachment_metadata']).get()
```

---

### PostMeta.or_where()

Appends a `WHERE` condition that initiates a new condition group combined with preceding groups via `OR` logic. Conditions added within each group using `where()` are combined via `AND`. Scoping to `post_id = Attachment.ID` is preserved across all groups.

```elscriptsignature
PostMeta.or_where(
  string:column,
  mixed:operator_or_value,
  mixed:value = null
): PostMeta
```

#### Example

```elscript
/* Query metadata keys matching either of two patterns */
Media.get(42).meta.where('meta_key', '_wp_attached_file')
  .or_where('meta_key', '_wp_attachment_metadata')
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
Media.get(42).meta.where('meta_key', 'like', '_wp_%')
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
Media.get(42).meta.order_by('meta_id', 'ASC')
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
Media.get(42).meta.order_by('meta_id', 'ASC')
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
/* Execute raw SQL query against mirrored attachment metadata */
Media.get(42).meta.where('meta_key', 'like', '_%')
  .query('SELECT meta_id, meta_key, meta_value FROM wp_postmeta ORDER BY meta_id ASC')
```

:::info SQLite3 Extension Required
The SQLite3 PHP extension **must** be installed and enabled on your server for `PostMeta.query()` to work.
:::

---

### PostMeta.count()

Counts matching metadata records for the attachment in MySQL without buffering rows into memory or initializing an SQLite table. Automatically enforces `post_id` scoping. Resets builder state after execution.

```elscriptsignature
PostMeta.count(): int
```

#### Examples

```elscript
/* Count all metadata rows for an attachment */
Media.get(42).meta.count()
```

```elscript
/* Count hidden/private metadata keys */
Media.get(42).meta.where('meta_key', 'like', '_%').count()
```

---

### PostMeta.to_sql()

Compiles and returns the SQL query string for the attachment's metadata query without executing it. Automatically enforces `post_id = Attachment.ID` scoping. Resets builder state after compilation.

```elscriptsignature
PostMeta.to_sql(bool:is_count = false): string
```

#### Parameters

* **`is_count`** (`bool`, _optional_): When `true`, compiles a `SELECT COUNT(*)` query instead of a column projection. Defaults to `false`.

#### Examples

```elscript
/* Preview metadata query SQL */
Media.get(42).meta.where('meta_key', 'like', '_wp_%').order_by('meta_id', 'DESC').to_sql()
```

```elscript
/* Preview metadata count SQL */
Media.get(42).meta.where('meta_key', 'like', '_wp_%').to_sql(true)
```
