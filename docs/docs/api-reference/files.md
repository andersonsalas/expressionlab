---
id: files
title: Files
sidebar_position: 7
---

# Files

Inspect files, tail debug logs, and analyze directory disk usage within the WordPress root directory (`ABSPATH`) using read-only inspection methods.

---

## Path Resolution and Access Constraints

The `Files` service enforces the following filesystem access rules:

* **WordPress Root Confinement (`ABSPATH`)**: All paths (whether provided as relative or absolute) are canonicalized using `realpath()` and normalized. If a target path resolves outside `ABSPATH`, an `\InvalidArgumentException` is thrown.
* **Relative Path Resolution**: Any path that does not begin with a leading `/` or Windows drive letter is automatically resolved relative to `ABSPATH`.
* **Read-Only Confinement**: The service provides read-only inspection operations (`exists`, `is_file`, `is_dir`, `size`, `modified`, `read`, `tail`, `list`, `stats`). It does not expose write, edit, or delete primitives.
* **Operation Counter**: Methods performing disk I/O trigger execution ticks (`LanguageEngine::tick()`) to enforce configured evaluation time limits.

---

## File and Directory Inspection

### Files.exists()

Checks whether a file or directory exists at the specified path within the WordPress root directory.

```elscriptsignature
Files.exists(
  string:path
): bool
```

#### Parameters

* **`path`** (`string`, _required_): File or directory path relative to `ABSPATH` or absolute within `ABSPATH`.

#### Return Value

Returns `true` if the target exists within `ABSPATH`, or `false` if it does not exist or if path traversal outside `ABSPATH` is attempted.

#### Examples

```elscript
/* Check if debug.log exists in the wp-content directory */
Files.exists('wp-content/debug.log')
```

```elscript
/* Check if wp-config.php exists */
Files.exists('wp-config.php')
```

```elscript
/* Check if an uploads subfolder exists */
Files.exists('wp-content/uploads/2026/08')
```

---

### Files.is_file()

Checks whether the specified path exists and is a regular file.

```elscriptsignature
Files.is_file(
  string:path
): bool
```

#### Parameters

* **`path`** (`string`, _required_): Path to inspect.

#### Return Value

Returns `true` if the path exists and is a regular file, or `false` if it is a directory, does not exist, or resolves outside `ABSPATH`.

#### Examples

```elscript
/* Verify if wp-config.php is a regular file */
Files.is_file('wp-config.php')
```

```elscript
/* Check if a directory path is a file (evaluates to false) */
Files.is_file('wp-content/plugins')
```

---

### Files.is_dir()

Checks whether the specified path exists and is a directory.

```elscriptsignature
Files.is_dir(
  string:path
): bool
```

#### Parameters

* **`path`** (`string`, _required_): Path to inspect.

#### Return Value

Returns `true` if the path exists and is a directory, or `false` if it is a regular file, does not exist, or resolves outside `ABSPATH`.

#### Examples

```elscript
/* Verify if wp-content/plugins is a directory */
Files.is_dir('wp-content/plugins')
```

```elscript
/* Check if a file path is a directory (evaluates to false) */
Files.is_dir('wp-load.php')
```

---

### Files.size()

Retrieves the size of a file or directory in bytes and as a formatted human-readable string.

```elscriptsignature
Files.size(
  string:path
): array
```

#### Parameters

* **`path`** (`string`, _required_): Path to the file or directory.

#### Return Value

Returns an associative array with the following keys:

| Field | Type | Description |
| :--- | :--- | :--- |
| `bytes` | `int` | Exact file or directory size in bytes. |
| `human` | `string` | Formatted size string with binary units (`B`, `KB`, `MB`, `GB`, `TB`) rounded to two decimal places. |

#### Error Handling

Throws `\InvalidArgumentException` if the path does not exist or resolves outside `ABSPATH`.

#### Examples

```elscript
/* Retrieve size metrics for wp-config.php */
Files.size('wp-config.php')
```

```elscript
/* Retrieve size metrics for a log file */
Files.size('wp-content/debug.log')
```

```elscript
/* Access only the raw byte count */
Files.size('wp-load.php')['bytes']
```

```elscript
/* Access the human-readable formatted string */
Files.size('wp-load.php')['human']
```

---

### Files.modified()

Retrieves the last modification timestamp and UTC ISO-formatted datetime string for a file or directory.

```elscriptsignature
Files.modified(
  string:path
): array
```

#### Parameters

* **`path`** (`string`, _required_): Path to the file or directory.

#### Return Value

Returns an associative array with the following keys:

| Field | Type | Description |
| :--- | :--- | :--- |
| `timestamp` | `int` | Unix epoch timestamp of the last modification. |
| `formatted` | `string` | UTC formatted datetime string (`YYYY-MM-DD HH:MM:SS UTC`). |

#### Error Handling

Throws `\InvalidArgumentException` if the path does not exist or resolves outside `ABSPATH`.

#### Examples

```elscript
/* Retrieve modification metadata for wp-config.php */
Files.modified('wp-config.php')
```

```elscript
/* Inspect formatted modification datetime for a plugin directory */
Files.modified('wp-content/plugins')['formatted']
```

---

## Reading and Content Retrieval

### Files.read()

Reads the content of a file up to a specified maximum byte limit.

```elscriptsignature
Files.read(
  string:path,
  int:max_bytes = Files.MAX_READ_BYTES
): string
```

#### Parameters

* **`path`** (`string`, _required_): Path to the file.
* **`max_bytes`** (`int`, _optional_): Maximum number of bytes to read. Defaults to `Files.MAX_READ_BYTES` (`262144` bytes = 256 KB). Clamped between `1` and `Files.MAX_READ_BYTES`.

#### Return Value

Returns a `string` containing the raw file contents up to `max_bytes`.

#### Error Handling

* Throws `\InvalidArgumentException` if the path does not exist or resolves outside `ABSPATH`.
* Throws `\RuntimeException` if the target path is a directory (`'Cannot read a directory as a file.'`).
* Throws `\RuntimeException` if the file content cannot be read (`'Failed to read file contents.'`).

#### Examples

```elscript
/* Read up to 256 KB of a log file (default limit) */
Files.read('wp-content/debug.log')
```

```elscript
/* Read explicitly the first 512 bytes of a configuration file */
Files.read('wp-config.php', 512)
```

---

### Files.tail()

Reads the last *N* lines of a file by seeking backwards from the end of the file in binary mode (`fseek`), without loading the entire file into memory.

```elscriptsignature
Files.tail(
  string:path,
  int:lines = 50
): array
```

#### Parameters

* **`path`** (`string`, _required_): Path to the file.
* **`lines`** (`int`, _optional_): Number of lines to retrieve from the end of the file. Defaults to `50`. Clamped between `1` and `500`.

#### Return Value

Returns an array of strings (`string[]`), where each element is a line from the end of the file.

#### Error Handling

* Throws `\InvalidArgumentException` if the path does not exist or resolves outside `ABSPATH`.
* Throws `\RuntimeException` if the target path is a directory (`'Cannot tail a directory.'`).
* Throws `\RuntimeException` if the file cannot be opened for reading (`'Failed to open file for tailing.'`).

#### Examples

```elscript
/* Retrieve the last 50 lines from debug.log (default limit) */
Files.tail('wp-content/debug.log')
```

```elscript
/* Retrieve the last 15 lines from the debug log */
Files.tail('wp-content/debug.log', 15)
```

```elscript
/* Retrieve the maximum allowed 500 lines from a log file */
Files.tail('wp-content/debug.log', 500)
```

---

## Directory Traversal and Diagnostics

### Files.list()

Lists the immediate files and directories contained within a specified directory path. Dot entries (`.` and `..`) are omitted. Results are automatically sorted: directories appear first (alphabetically, case-insensitive), followed by files (alphabetically, case-insensitive).

```elscriptsignature
Files.list(
  string:path = '.'
): array
```

#### Parameters

* **`path`** (`string`, _optional_): Directory path relative to `ABSPATH` or absolute within `ABSPATH`. Defaults to `'.'` (the WordPress root directory `ABSPATH`).

#### Return Value

Returns an indexed array of associative arrays, each representing a filesystem item:

| Field | Type | Description |
| :--- | :--- | :--- |
| `name` | `string` | Filename or directory name. |
| `type` | `string` | Entry type (`'dir'` or `'file'`). |
| `size` | `?string` | Formatted human-readable size for files, or `null` for directories. |
| `modified` | `string` | UTC modification datetime string formatted as `YYYY-MM-DD HH:MM:SS`. |

#### Error Handling

* Throws `\InvalidArgumentException` if the path does not exist or resolves outside `ABSPATH`.
* Throws `\RuntimeException` if the target path is not a directory.

#### Examples

```elscript
/* List contents of the WordPress root directory */
Files.list()
```

```elscript
/* List contents of the wp-content directory */
Files.list('wp-content')
```

```elscript
/* List items inside the plugins directory */
Files.list('wp-content/plugins')
```

```elscript
/* List items inside the active themes directory */
Files.list('wp-content/themes')
```

---

### Files.stats()

Calculates disk space usage statistics for a directory, aggregating file counts and byte sizes grouped by file extension. Automatically generates interactive visualizations in the Expression Lab console.

```elscriptsignature
Files.stats(
  string:path = '.'
): array
```

#### Parameters

* **`path`** (`string`, _optional_): Directory path to inspect relative to `ABSPATH` or absolute within `ABSPATH`. Defaults to `'.'` (the WordPress root directory `ABSPATH`).

#### Visualizations Generated

When executed in Expression Lab, `Files.stats()` renders:

1. **Table Visualization (`Table: Directory disk distribution by extension`)**: Tabular distribution displaying `extension`, `files`, `size`, and `bytes`.
2. **Vega-Lite Radial Chart (`Graph: Disk size distribution by extension`)**: Interactive radial (pie) chart displaying cumulative disk size per file extension.

#### Return Value

Returns an associative array with the following structure:

| Field | Type | Description |
| :--- | :--- | :--- |
| `path` | `string` | Normalized absolute path of the inspected directory. |
| `files` | `int` | Total count of regular files in the directory. |
| `directories` | `int` | Total count of immediate subdirectories. |
| `total_bytes` | `int` | Cumulative size of all files in bytes. |
| `total_size` | `string` | Formatted human-readable cumulative size string. |
| `by_ext` | `array` | List of extension summary records sorted descending by cumulative byte size. |

Each item in `by_ext` contains:

| Field | Type | Description |
| :--- | :--- | :--- |
| `extension` | `string` | File extension (e.g., `'.php'`, `'.js'`, `'.css'`, or `'(none)'`). |
| `files` | `int` | Number of files matching the extension. |
| `size` | `string` | Formatted human-readable cumulative size for this extension. |
| `bytes` | `int` | Cumulative byte count for this extension. |

#### Error Handling

* Throws `\InvalidArgumentException` if the path does not exist or resolves outside `ABSPATH`.
* Throws `\RuntimeException` if the target path is not a directory.

#### Examples

```elscript
/* Analyze disk distribution across the WordPress root directory */
Files.stats()
```

<VegaLite spec={{
  "$schema": "https://vega.github.io/schema/vega/v6.json",
  "description": "Files extension size distribution graph",
  "autosize": {
    "type": "fit-x",
    "contains": "padding"
  },
  "background": "white",
  "padding": 20,
  "height": 300,
  "title": {
    "anchor": "start",
    "text": "Disk Size Distribution by Extension"
  },
  "style": "view",
  "data": [
    {
      "name": "source_0",
      "values": [
        {
          "extension": ".php",
          "count": 15,
          "size": 168307
        },
        {
          "extension": ".txt",
          "count": 2,
          "size": 20013
        },
        {
          "extension": ".html",
          "count": 1,
          "size": 7407
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
              "extension"
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
            "signal": "{\"size\": format(datum[\"size\"], \"\"), \"extension\": isValid(datum[\"extension\"]) ? isArray(datum[\"extension\"]) ? join(datum[\"extension\"], '\\n') : datum[\"extension\"] : \"\"+datum[\"extension\"]}"
          },
          "fill": {
            "scale": "color",
            "field": "extension"
          },
          "description": {
            "signal": "\"size: \" + (format(datum[\"size\"], \"\")) + \"; extension: \" + (isValid(datum[\"extension\"]) ? isArray(datum[\"extension\"]) ? join(datum[\"extension\"], ' ') : datum[\"extension\"] : \"\"+datum[\"extension\"])"
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
        "field": "extension",
        "sort": true
      },
      "range": "category"
    }
  ],
  "legends": [
    {
      "title": "Extension",
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
/* Analyze disk distribution of the uploads directory */
Files.stats('wp-content/uploads')
```

```elscript
/* Analyze disk distribution of the plugins directory */
Files.stats('wp-content/plugins')
```

```elscript
/* Retrieve the total directory file count */
Files.stats('wp-content/themes')['files']
```

---

## Properties and Constants

### Files.MAX_READ_BYTES

A read-only property exposing the maximum byte limit enforced on single `read()` operations (`262144` bytes = 256 KB).

```elscriptsignature
Files.MAX_READ_BYTES: int
```

* **Type**: `int` (Read-only)
* **Value**: `262144`

#### Example

```elscript
/* Inspect the maximum byte read limit */
Files.MAX_READ_BYTES
```

---

### Files.MAX_TAIL_BYTES

A read-only property exposing the maximum accumulated byte limit enforced on `tail()` streaming operations (`1048576` bytes = 1 MB) to prevent unbounded memory allocation on single-line files.

```elscriptsignature
Files.MAX_TAIL_BYTES: int
```

* **Type**: `int` (Read-only)
* **Value**: `1048576`

#### Example

```elscript
/* Inspect the maximum tail buffer limit */
Files.MAX_TAIL_BYTES
```
