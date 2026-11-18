---
id: cache
title: Cache
sidebar_position: 3
---

# Cache

Inspect, diagnose, and manage entries in the WordPress object cache.

The `Cache` service provides methods to check cache persistence, review hit and miss metrics, and read, store, or remove cached keys within a group.

---

## Diagnostics & Status

### Cache.is_persistent()

Checks whether the site uses an external persistent object cache (such as Redis or Memcached) instead of the default per-request memory cache.

```elscriptsignature
Cache.is_persistent(): bool
```

#### Example

```elscript
/* Check if an external object cache is active */
Cache.is_persistent()
```

---

### Cache.status()

Returns configuration details and runtime metadata for the active object cache backend.

```elscriptsignature
Cache.status(): array
```

#### Returned Fields

| Field | Type | Description |
| :--- | :--- | :--- |
| `is_persistent` | `bool` | Whether an external persistent cache is active. |
| `backend` | `string` | Class name of the active object cache instance, or `'none'`. |
| `dropin_exists` | `bool` | Whether `wp-content/object-cache.php` exists on disk. |
| `dropin_path` | `?string` | Full path to `object-cache.php`, or `null` when absent. |
| `global_groups` | `array` | List of registered global cache groups. |
| `non_persistent_groups` | `array` | List of groups excluded from persistent storage. |
| `multisite_blog_id` | `?int` | Current site ID in Multisite, or `null` on single-site installations. |

#### Example

```elscript
/* Inspect the active cache backend and groups */
Cache.status()
```

---

### Cache.stats()

Collects hit and miss counters for the current request and renders a summary table along with a distribution chart.

```elscriptsignature
Cache.stats(): array
```

#### Returned Fields

| Field | Type | Description |
| :--- | :--- | :--- |
| `hits` | `int` | Number of cache hits during the request. |
| `misses` | `int` | Number of cache misses during the request. |
| `total` | `int` | Combined total of hits and misses. |
| `hit_ratio` | `string` | Hit percentage formatted with two decimals (for example, `'94.50%'`). |
| `is_persistent` | `bool` | Whether an external persistent cache is active. |

#### Example

```elscript
/* Display cache hit/miss metrics and chart */
Cache.stats()
```

<VegaLite spec={{
  "$schema": "https://vega.github.io/schema/vega-lite/v6.json",
  "title": "Cache Hit vs Miss Distribution",
  "width": "container",
  "height": 300,
  "data": {
    "values": [
      { "metric": "Hits", "count": 185 },
      { "metric": "Misses", "count": 15 }
    ]
  },
  "mark": { "type": "arc", "tooltip": true },
  "encoding": {
    "theta": { "field": "count", "type": "quantitative" },
    "color": { "field": "metric", "type": "nominal", "legend": { "title": "Metric" } }
  }
}} />

---

## Reading Cache Entries

### Cache.get()

Retrieves a cached value by key, or fetches multiple keys at once when an array of keys is passed.

```elscriptsignature
Cache.get(
  string|int|array:key,
  string:group = '',
  bool:force = false,
  mixed:default_value = null
): mixed
```

#### Parameters

* **`key`** (`string|int|array`, _required_): A single cache key or an array of keys.
* **`group`** (`string`, _optional_): Cache group name. Default `''`.
* **`force`** (`bool`, _optional_): Forces a refresh from the persistent cache backend when supported. Default `false`.
* **`default_value`** (`mixed`, _optional_): Value returned for missing keys. Default `null`.

#### Examples

```elscript
/* Read a single cached option */
Cache.get('active_plugins', 'options')
```

```elscript
/* Read multiple keys from the 'posts' group */
Cache.get(['post_1', 'post_2'], 'posts', false, null)
```

---

### Cache.has()

Checks whether a key exists in a cache group. Unlike `Cache.get()`, this method distinguishes between a missing key and a key whose stored value is `false`.

```elscriptsignature
Cache.has(
  string|int:key,
  string:group = ''
): bool
```

#### Example

```elscript
/* Verify whether a key is present in cache */
Cache.has('user_meta_cache', 'users')
```

---

## Writing & Removing Cache Entries

:::warning Write Protection Enabled by Default
Both `Cache.set()` and `Cache.delete()` are blocked when `EXPRESSION_LAB_DATABASE_READONLY` is set to `true` (default in production). To allow cache writes, define `define( 'EXPRESSION_LAB_DATABASE_READONLY', false );` in `wp-config.php`.
:::

### Cache.set()

Stores one or more values in the object cache with an optional group and expiration time.

#### Single-Key Signature

```elscriptsignature
Cache.set(
  string|int:key,
  mixed:data,
  string:group = '',
  int:expire = 0
): bool
```

#### Batch Signature

```elscriptsignature
Cache.set(
  array:items,
  string:group = '',
  int:expire = 0
): bool
```

#### Parameters

* **`key` / `items`** (`string|int|array`, _required_): Either a single key (paired with `data`) or an associative object/map of `key: value` pairs.
* **`data`** (`mixed`, _required for single key_): Value to store. When passing a batch map as the first argument, this parameter becomes `group`.
* **`group`** (`string`, _optional_): Cache group name. Default `''`.
* **`expire`** (`int`, _optional_): Expiration time in seconds (must be `0` or greater). `0` means no expiration. Default `0`.

#### Examples

```elscript
/* Store a single value for 5 minutes (300 seconds) */
Cache.set('sample_token', 'xyz-123', 'tokens', 300)
```

```elscript
/* Store multiple entries in one call for 10 minutes */
Cache.set({ 'token_1': 'abc', 'token_2': 'def' }, 'tokens', 600)
```

---

### Cache.delete()

Removes one or more keys from a cache group.

```elscriptsignature
Cache.delete(
  string|int|array:key,
  string:group = ''
): bool
```

#### Parameters

* **`key`** (`string|int|array`, _required_): Single key or array of keys to delete.
* **`group`** (`string`, _optional_): Cache group name. Default `''`.

#### Examples

```elscript
/* Delete a single cache entry */
Cache.delete('sample_token', 'tokens')
```

```elscript
/* Delete multiple entries in one call */
Cache.delete(['token_1', 'token_2'], 'tokens')
```
