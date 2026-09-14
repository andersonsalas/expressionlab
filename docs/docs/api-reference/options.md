---
id: options
title: Options
sidebar_position: 2
---

# Options

Inspect, update, and profile WordPress options with structured deserialization, format compatibility checks, and autoload distribution charts.

The `Options` service provides diagnostic and inspection utilities for WordPress options:

* **Controlled deserialization**: Restricts unserialization to arrays, scalars, and `stdClass` instances (`allowed_classes => false`), preventing arbitrary class instantiation.
* **Format compatibility checks**: Detects existing data format (PHP serialization vs. JSON) during updates to prevent accidental format conversion.
* **Cache invalidation**: Clears both specific option caches and the `alloptions` cache when options are modified.
* **Autoload distribution analysis**: Generates Vega-Lite charts and breakdown tables for options configured with autoloading.
* **Multisite support**: Provides access to network-level site options via `NetworkOptions`.

---

## Reading Options

### Options.get()

Retrieves an option value and automatically decodes or safely unserializes structured data.

```elscriptsignature
Options.get(
  string:key,
  mixed:default = null
): mixed
```

#### Behavior & Type Detection

1. **Serialized Data**: If the option string is serialized (e.g., arrays or objects), it is processed through a strict unserialization process. Only `stdClass` objects and standard scalar types/arrays are allowed. If a non-`stdClass` object or unauthorized class is detected, an exception is thrown.
2. **JSON Data**: If the option string is valid JSON (e.g. `{"enabled": true}` or `[1, 2, 3]`), it is automatically parsed into an associative array/object.
3. **Scalar Values**: Strings, integers, booleans, and nulls are returned without modification.
4. **Non-Existent Keys**: Returns `default` (or `null` if omitted).

#### Example

```elscript
/* Retrieve site configuration array or default */
Options.get('my_plugin_settings', { enabled: false, timeout: 30 })
```

```elscript
/* Access nested properties */
Options.get('active_plugins')
```

---

### Options.get_raw()

Retrieves the exact, unmodified raw string stored in the `option_value` column of the `wp_options` table without executing any deserialization or JSON decoding.

```elscriptsignature
Options.get_raw(
  string:key,
  mixed:default = null
): ?string
```

#### Example

```elscript
/* Inspect raw database payload for corrupted serialization */
Options.get_raw('cron')
```

:::tip Use Cases for `get_raw()`
Use `get_raw()` when performing forensic audits on potentially corrupted options, checking raw string byte lengths, or analyzing unparsed serialized blobs.
:::

---

## Writing & Updating Options

:::warning Write Protection Enabled by Default
All mutation methods (`update`, `update_raw`, `delete`) enforce write protection when the `EXPRESSION_LAB_DATABASE_READONLY` constant is set to `true` (default in production environments). To allow modifications, set `define( 'EXPRESSION_LAB_DATABASE_READONLY', false );` in your `wp-config.php`.
:::

### Options.update()

Updates an existing option or creates a new one, applying strict pre-flight serialization safety checks.

```elscriptsignature
Options.update(
  string:key,
  mixed:value,
  string:serialization_type = Options.FORMAT_SERIALIZED
): bool
```

#### Serialization Format Constants

| Constant | Value | Description |
| :--- | :--- | :--- |
| `Options.FORMAT_SERIALIZED` | `'serialized'` | Default. Encodes arrays and scalars using standard PHP serialization (`serialize`). |
| `Options.FORMAT_SERIALIZED_OBJECT` | `'serialized_object'` | Converts the root array into a `stdClass` object before serializing. |
| `Options.FORMAT_JSON` | `'json'` | Encodes the value as a JSON string using `wp_json_encode`. |

#### Format Conflict Protections

To prevent silent data corruption:
* If the option in the database is currently PHP serialized, `Options.update()` **rejects** updating it with `Options.FORMAT_JSON`.
* If the option in the database is currently JSON, `Options.update()` **rejects** updating it with `Options.FORMAT_SERIALIZED`.
* If you intentionally need to replace the format, use `Options.update_raw()`.

#### Examples

```elscript
/* Store structured plugin settings using PHP serialization */
Options.update('my_plugin_settings', {
  api_key: 'sk_live_12345',
  debug_mode: true,
  retries: 3
})
```

```elscript
/* Store settings formatted as JSON */
Options.update('my_app_state', { theme: 'dark', notifications: true }, Options.FORMAT_JSON)
```

---

### Options.update_raw()

Performs a direct, low-level insert or update to the `wp_options` table. It does not serialize or JSON-encode `$value`.

```elscriptsignature
Options.update_raw(
  string:key,
  string:value,
  string|bool|null:autoload = null
): bool
```

#### Parameters

* **`key`** (`string`, _required_): The option name.
* **`value`** (`string`, _required_): The raw string payload to store.
* **`autoload`** (`string|bool|null`, _optional_): Controls whether the option is automatically loaded into memory on every WordPress request:
  * `'yes'` or `true`: Option is autoloaded into the global `alloptions` pool.
  * `'no'` or `false`: Option is loaded on-demand when explicitly requested.
  * `null`: If the option already exists, preserves its current `autoload` value. If inserting a new record, defaults to `'no'`.

#### Cache Invalidation Behavior

When `update_raw()` executes:
1. It purges the key from WordPress object cache via `wp_cache_delete($key, 'options')`.
2. It checks whether the key exists in the global `alloptions` cache. If present, it purges `'alloptions'` to prevent stale cache hits across subsequent PHP requests.

#### Example

```elscript
/* Repair or write a raw serialized string and set autoload to 'no' */
Options.update_raw('heavy_cache_entry', 'a:1:{s:4:"data";s:6:"sample";}', false)
```

---

## Deleting Options

### Options.delete()

Removes an option key from the database and purges all related object cache entries.

```elscriptsignature
Options.delete(string:key): bool
```

#### Example

```elscript
/* Clean up an obsolete plugin option */
Options.delete('obsolete_plugin_config')
```

---

## Forensic Analysis & Autoload Diagnostics

### Options.stats()

Executes a comprehensive, memory-optimized heuristic analysis of the `wp_options` table, calculating prefix groupings, memory footprints (KB), and autoload ratios.

```elscriptsignature
Options.stats(
  ?int:sample_limit = 100000,
  int:graph_limit = 20
): array
```

#### What `stats()` Generates

When executed in Expression Lab, `stats()` automatically renders:

1. **Interactive Bar Chart (Vega-Lite)**: Top option prefixes sorted by cumulative size in kilobytes.
2. **Table: Prefix Distribution**: Tabular metrics breakdown per option group.
3. **Pie Chart (Vega-Lite)**: Ratio of autoloaded (`yes`/`on`/`auto`) vs non-autoloaded (`no`/`off`) data.
4. **Table: Autoload Distribution**: Summary of counts and total kilobyte footprint.

#### Metrics Breakdown

| Metric Column | Description |
| :--- | :--- |
| `prefix` | The detected plugin/theme or core prefix (e.g., `woocommerce_`, `elementor_`, `(Transients)`). |
| `count` | Total number of option rows matching the prefix. |
| `size_kb` | Total size of all values in this prefix group in kilobytes. |
| `autoload_size_kb` | Total kilobyte size of records loaded on **every** page request. |
| `autoload_percentage` | Percentage of the prefix's data weight that is autoloaded (`0% - 100%`). |

#### Examples

```elscript
/* Perform a diagnostic scan of the entire options table */
Options.stats()
```

```elscript
/* Sample the first 50,000 rows with top 10 visualization limit */
Options.stats(50000, 10)
```

---

## Multisite Network Options

On WordPress Multisite installations, network-wide configuration is stored in the `wp_sitemeta` table. Expression Lab provides the **NetworkOptions** service for managing network options across the entire multisite network.

:::note Multisite Only & Network Scoping
`NetworkOptions` is exclusively functional in WordPress Multisite environments and always targets the main network (`wp_sitemeta` / `site_id`). When evaluating expressions on individual subsites, use `Options` (or `Site.options`) to operate on site-specific `wp_{blog_id}_options` tables.
:::

### NetworkOptions.get()

Retrieves a network option value from the `wp_sitemeta` table, automatically decoding JSON or safely unserializing structured PHP payloads with strict Object Injection (POI) protections.

```elscriptsignature
NetworkOptions.get(
  string:key,
  mixed:default = null
): mixed
```

#### Example

```elscript
/* Read a network option with strict deserialization */
NetworkOptions.get('active_sitewide_plugins')
```

---

### NetworkOptions.get_raw()

Retrieves the exact, unmodified raw string stored in the `meta_value` column of the `wp_sitemeta` table without deserialization or JSON decoding.

```elscriptsignature
NetworkOptions.get_raw(
  string:key,
  mixed:default = null
): ?string
```

#### Example

```elscript
/* Read raw sitemeta value */
NetworkOptions.get_raw('site_admins')
```

---

### NetworkOptions.update()

Safely updates or creates a network option in `wp_sitemeta`, applying strict serialization safety checks and format conflict protections (preventing accidental conversion between PHP serialized data and JSON).

```elscriptsignature
NetworkOptions.update(
  string:key,
  mixed:value,
  string:serialization_type = Options.FORMAT_SERIALIZED
): bool
```

#### Example

```elscript
/* Update a network option safely */
NetworkOptions.update('network_security_policy', { max_login_attempts: 5 })
```

---

### NetworkOptions.update_raw()

Writes a raw string payload to the `wp_sitemeta` table without serialization or JSON encoding, and invalidates the corresponding network options cache.

```elscriptsignature
NetworkOptions.update_raw(
  string:key,
  string:value
): bool
```

#### Example

```elscript
/* Direct raw update on sitemeta */
NetworkOptions.update_raw('custom_network_meta', 'raw_value')
```

---

### NetworkOptions.delete()

Removes a network option from the `wp_sitemeta` table and purges related cache entries.

```elscriptsignature
NetworkOptions.delete(
  string:key
): bool
```

#### Example

```elscript
/* Delete a network option */
NetworkOptions.delete('obsolete_network_key')
```
