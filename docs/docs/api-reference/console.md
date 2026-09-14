---
id: console
title: Console
sidebar_position: 9
---

# Console

Output styled diagnostic messages (`log`, `warning`, `error`, `success`) and render rich data visualizations in the console output panel.

---

## Data Encoding and Type Handling

When data is passed to any logging method (`log`, `warning`, `error`, `success`), the value undergoes internal type normalization before being queued into the engine's message buffer:

| Data Type | Normalization Behavior | Output Format Example |
| :--- | :--- | :--- |
| **Scalar Primitives** (`string`, `int`, `float`, `bool`) | Retained in native primitive format without modification. | `'Operation completed'`, `120`, `true` |
| **Array** (`array`) | Serialized to JSON format using `wp_json_encode()`. | `'{"status":"ok","items":[1,2,3]}'` |
| **Standard Class** (`stdClass`) | Serialized to JSON format using `wp_json_encode()`. | `'{"id":101,"active":true}'` |
| **Stringable Object** (implements `__toString()`) | Converted to string via the object's `__toString()` method. | `'String representation of object'` |
| **Custom Class Object** | Formatted as a type identifier containing the class name. | `'<WP_Post>'`, `'<WP_User>'` or `'<Resource>'` |

Messages are recorded as associative records containing `type` (`'info'`, `'warning'`, `'error'`, or `'success'`) and `text` (the normalized output), and are returned in the expression execution response.

---

## Message Logging Methods

### Console.log()

Logs an informational message (`info` level) to the Expression Lab output panel.

```elscriptsignature
Console.log(
  mixed:message
): void
```

#### Parameters

* **`message`** (`mixed`, _required_): The message payload to output. Can be a string, number, boolean, array, or object.

#### Return Value

Returns `null` (`void`).

#### Examples

```elscript
/* Log an informational text string */
Console.log('Initialization sequence completed')
```

```elscript
/* Log an integer value */
Console.log(1024)
```

```elscript
/* Log a structured associative object */
Console.log({ status: 'healthy', memory_usage_mb: 42.5 })
```

```elscript
/* Log an interpolated expression using string concatenation */
Console.log('Active theme: ' ~ Options.get('stylesheet'))
```

---

### Console.warning()

Logs a warning message (`warning` level) to the Expression Lab output panel.

```elscriptsignature
Console.warning(
  mixed:message
): void
```

#### Parameters

* **`message`** (`mixed`, _required_): The warning message payload to output.

#### Return Value

Returns `null` (`void`).

#### Examples

```elscript
/* Log a configuration warning */
Console.warning('Autoload option size is approaching the 800 KB threshold')
```

```elscript
/* Log diagnostic warning payload */
Console.warning({ warning: 'High memory usage', allocated_mb: 210, limit_mb: 256 })
```

---

### Console.error()

Logs an error message (`error` level) to the Expression Lab output panel.

```elscriptsignature
Console.error(
  mixed:message
): void
```

#### Parameters

* **`message`** (`mixed`, _required_): The error message payload to output.

#### Return Value

Returns `null` (`void`).

#### Examples

```elscript
/* Log an execution error message */
Console.error('Target endpoint returned non-200 status code')
```

```elscript
/* Log structured error details */
Console.error({ error_code: 'REST_INVALID_ARGUMENT', field: 'email' })
```

---

### Console.success()

Logs a success message (`success` level) to the Expression Lab output panel.

```elscriptsignature
Console.success(
  mixed:message
): void
```

#### Parameters

* **`message`** (`mixed`, _required_): The success message payload to output.

#### Return Value

Returns `null` (`void`).

#### Examples

```elscript
/* Log successful operation confirmation */
Console.success('Database table mirrored successfully')
```

```elscript
/* Log structured success metadata */
Console.success({ updated: true, timestamp: 1725055200 })
```

---

## Data Visualizations

### Console.table()

Renders an interactive data table visualization in the Expression Lab console output.

`Console.table()` supports two input formats: **Associative (Record) Format** and **Positional (Matrix) Format**.

```elscriptsignature
Console.table(
  array:arg1,
  array|string:arg2 = [],
  string:title = 'Table'
): void
```

#### Formats

##### 1. Associative (Record) Format

Pass an array of associative arrays or objects as `$arg1`, and optionally provide the table title as `$arg2`.

* **Column Detection**: Table headers are dynamically extracted from the keys of the first item in the array.
* **Row Normalization**: Each subsequent row is mapped against the detected headers. If a subsequent row is missing a key present in the first item, that column is set to `null`.
* **Title Argument**: The title is passed as the second argument (`$arg2`). If omitted or empty, the title defaults to `'Table'`.

```elscriptsignature
Console.table(
  array:data,
  string:title = 'Table'
): void
```

##### 2. Positional (Matrix) Format

Pass an indexed array of header strings as `$arg1`, a two-dimensional array of row values as `$arg2`, and an optional table title as `$title` (`$arg3`).

* **Column Mapping**: Values in each inner array are mapped by numerical index to the header names defined in `$arg1`.
* **Row Normalization**: If an inner row array has fewer values than the headers array, unassigned columns are set to `null`.
* **Title Argument**: The title is passed as the third argument (`$title`). If omitted, defaults to `'Table'`.

```elscriptsignature
Console.table(
  array:headers,
  array:values,
  string:title = 'Table'
): void
```

#### Parameters

* **`arg1`** (`array`, _required_): Either an array of associative records / objects (associative format) or an indexed array of column header names (positional format).
* **`arg2`** (`array|string`, _optional_): Either the table title string (associative format) or a two-dimensional array of row values (positional format). Defaults to `[]`.
* **`title`** (`string`, _optional_): Table title used when calling in positional format. Defaults to `'Table'`.

#### Return Value

Returns `null` (`void`). Registers a visualization payload of type `table` with the provided title and normalized row dataset in the `LanguageEngine` runtime.

#### Error Handling

* Throws `\InvalidArgumentException` (`"Data cannot be empty."`) if `$arg1` is an empty array.
* Throws `\InvalidArgumentException` (`"Values cannot be empty when using positional headers."`) if `$arg1` contains positional headers but `$arg2` is empty or not an array.

#### Examples

```elscript
/* Associative format with custom title */
Console.table([
  { id: 1, name: 'Alice', role: 'Administrator' },
  { id: 2, name: 'Bob', role: 'Editor' }
], 'Team Members')
```

```elscript
/* Associative format with default title */
Console.table([
  { option: 'blogname', value: 'Expression Lab' },
  { option: 'admin_email', value: 'admin@example.com' }
])
```

```elscript
/* Positional format with custom title */
Console.table(
  ['ID', 'Username', 'Email'],
  [
    [1, 'admin', 'admin@example.com'],
    [2, 'editor', 'editor@example.com']
  ],
  'User Directory'
)
```

```elscript
/* Positional format with metric comparisons */
Console.table(
  ['Metric', 'Current', 'Threshold'],
  [
    ['Autoload Size', '450 KB', '800 KB'],
    ['Total Tables', 12, 50]
  ],
  'Performance Metrics'
)
```

```elscript
/* Tabulate filesystem diagnostic results */
Console.table([
  { path: 'wp-config.php', exists: Files.exists('wp-config.php') },
  { path: 'wp-content/debug.log', exists: Files.exists('wp-content/debug.log') }
], 'Filesystem Checks')
```
