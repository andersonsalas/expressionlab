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

<VegaLite spec={{
  "$schema": "https://vega.github.io/schema/vega/v6.json",
  "description": "Prefix distribution graph",
  "autosize": {
    "type": "fit-x",
    "contains": "padding"
  },
  "background": "white",
  "padding": 5,
  "height": 400,
  "title": {
    "anchor": "start",
    "text": "Option Prefix Distribution"
  },
  "style": "cell",
  "data": [
    {
      "name": "source_0",
      "values": [
        {
          "prefix": "(Transients)",
          "count": 29,
          "size_kb": 719.01,
          "autoload_size_kb": 34.33,
          "autoload_percentage": 5
        },
        {
          "prefix": "rewrite",
          "count": 1,
          "size_kb": 8.85,
          "autoload_size_kb": 8.85,
          "autoload_percentage": 100
        },
        {
          "prefix": "wp_user",
          "count": 1,
          "size_kb": 3.06,
          "autoload_size_kb": 3.06,
          "autoload_percentage": 100
        },
        {
          "prefix": "(No prefix)",
          "count": 7,
          "size_kb": 2.13,
          "autoload_size_kb": 2.13,
          "autoload_percentage": 100
        },
        {
          "prefix": "widget",
          "count": 18,
          "size_kb": 1.3,
          "autoload_size_kb": 1.3,
          "autoload_percentage": 100
        },
        {
          "prefix": "sidebars",
          "count": 1,
          "size_kb": 0.19,
          "autoload_size_kb": 0.19,
          "autoload_percentage": 100
        },
        {
          "prefix": "auto",
          "count": 5,
          "size_kb": 0.15,
          "autoload_size_kb": 0.02,
          "autoload_percentage": 13
        },
        {
          "prefix": "active",
          "count": 1,
          "size_kb": 0.05,
          "autoload_size_kb": 0.05,
          "autoload_percentage": 100
        },
        {
          "prefix": "mailserver",
          "count": 4,
          "size_kb": 0.04,
          "autoload_size_kb": 0.04,
          "autoload_percentage": 100
        },
        {
          "prefix": "theme",
          "count": 1,
          "size_kb": 0.04,
          "autoload_size_kb": 0.04,
          "autoload_percentage": 100
        },
        {
          "prefix": "permalink",
          "count": 1,
          "size_kb": 0.04,
          "autoload_size_kb": 0.04,
          "autoload_percentage": 100
        },
        {
          "prefix": "ping",
          "count": 1,
          "size_kb": 0.03,
          "autoload_size_kb": 0.03,
          "autoload_percentage": 100
        },
        {
          "prefix": "default",
          "count": 9,
          "size_kb": 0.03,
          "autoload_size_kb": 0.03,
          "autoload_percentage": 100
        },
        {
          "prefix": "admin",
          "count": 2,
          "size_kb": 0.03,
          "autoload_size_kb": 0.03,
          "autoload_percentage": 100
        },
        {
          "prefix": "wp_force",
          "count": 1,
          "size_kb": 0.01,
          "autoload_size_kb": 0.01,
          "autoload_percentage": 100
        },
        {
          "prefix": "recently",
          "count": 2,
          "size_kb": 0.01,
          "autoload_size_kb": 0,
          "autoload_percentage": 0
        },
        {
          "prefix": "html",
          "count": 1,
          "size_kb": 0.01,
          "autoload_size_kb": 0.01,
          "autoload_percentage": 100
        },
        {
          "prefix": "recovery",
          "count": 1,
          "size_kb": 0.01,
          "autoload_size_kb": 0,
          "autoload_percentage": 0
        },
        {
          "prefix": "show",
          "count": 3,
          "size_kb": 0.01,
          "autoload_size_kb": 0.01,
          "autoload_percentage": 100
        },
        {
          "prefix": "avatar",
          "count": 2,
          "size_kb": 0.01,
          "autoload_size_kb": 0.01,
          "autoload_percentage": 100
        }
      ]
    },
    {
      "name": "data_0",
      "source": "source_0",
      "transform": [
        {
          "type": "stack",
          "groupby": [
            "prefix"
          ],
          "field": "count",
          "sort": {
            "field": [],
            "order": []
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
      "type": "rect",
      "style": [
        "bar"
      ],
      "from": {
        "data": "data_0"
      },
      "encode": {
        "update": {
          "tooltip": {
            "signal": "{\"prefix\": isValid(datum[\"prefix\"]) ? isArray(datum[\"prefix\"]) ? join(datum[\"prefix\"], '\\n') : datum[\"prefix\"] : \"\"+datum[\"prefix\"], \"count\": format(datum[\"count\"], \"\")}"
          },
          "fill": {
            "value": "#3858e9"
          },
          "ariaRoleDescription": {
            "value": "bar"
          },
          "description": {
            "signal": "\"prefix: \" + (isValid(datum[\"prefix\"]) ? isArray(datum[\"prefix\"]) ? join(datum[\"prefix\"], ' ') : datum[\"prefix\"] : \"\"+datum[\"prefix\"]) + \"; count: \" + (format(datum[\"count\"], \"\"))"
          },
          "x": {
            "scale": "x",
            "field": "prefix"
          },
          "width": {
            "signal": "max(0.25, bandwidth('x'))"
          },
          "y": {
            "scale": "y",
            "field": "count_end"
          },
          "y2": {
            "scale": "y",
            "field": "count_start"
          }
        }
      }
    }
  ],
  "scales": [
    {
      "name": "x",
      "type": "band",
      "domain": {
        "data": "source_0",
        "field": "prefix",
        "sort": {
          "op": "sum",
          "field": "count",
          "order": "descending"
        }
      },
      "range": [
        0,
        {
          "signal": "width"
        }
      ],
      "paddingInner": 0.1,
      "paddingOuter": 0.05
    },
    {
      "name": "y",
      "type": "linear",
      "domain": {
        "data": "data_0",
        "fields": [
          "count_start",
          "count_end"
        ]
      },
      "range": [
        {
          "signal": "height"
        },
        0
      ],
      "nice": true,
      "zero": true
    }
  ],
  "axes": [
    {
      "scale": "y",
      "orient": "left",
      "gridScale": "x",
      "grid": true,
      "tickCount": {
        "signal": "ceil(height/40)"
      },
      "domain": false,
      "labels": false,
      "aria": false,
      "maxExtent": 0,
      "minExtent": 0,
      "ticks": false,
      "zindex": 0
    },
    {
      "scale": "x",
      "orient": "bottom",
      "grid": false,
      "title": "Prefix",
      "labelAngle": 315,
      "labelAlign": "right",
      "labelBaseline": "top",
      "zindex": 0
    },
    {
      "scale": "y",
      "orient": "left",
      "grid": false,
      "title": "Count",
      "labelOverlap": true,
      "tickCount": {
        "signal": "ceil(height/40)"
      },
      "zindex": 0
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
  "description": "Autoload distribution graph",
  "autosize": {
    "type": "fit-x",
    "contains": "padding"
  },
  "background": "white",
  "padding": 20,
  "height": 300,
  "title": {
    "anchor": "start",
    "text": "Autoload Distribution"
  },
  "style": "view",
  "data": [
    {
      "name": "source_0",
      "values": [
        {
          "autoload": "on",
          "count": 102,
          "size_kb": 48.77
        },
        {
          "autoload": "off",
          "count": 36,
          "size_kb": 684.83
        },
        {
          "autoload": "auto",
          "count": 18,
          "size_kb": 1.51
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
              "autoload"
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
            "signal": "{\"count\": format(datum[\"count\"], \"\"), \"autoload\": isValid(datum[\"autoload\"]) ? isArray(datum[\"autoload\"]) ? join(datum[\"autoload\"], '\\n') : datum[\"autoload\"] : \"\"+datum[\"autoload\"]}"
          },
          "fill": {
            "scale": "color",
            "field": "autoload"
          },
          "description": {
            "signal": "\"count: \" + (format(datum[\"count\"], \"\")) + \"; autoload: \" + (isValid(datum[\"autoload\"]) ? isArray(datum[\"autoload\"]) ? join(datum[\"autoload\"], ' ') : datum[\"autoload\"] : \"\"+datum[\"autoload\"])"
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
        "field": "autoload",
        "sort": true
      },
      "range": "category"
    }
  ],
  "legends": [
    {
      "title": "Autoload distribution",
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
