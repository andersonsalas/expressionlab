---
id: graph
title: Graph
sidebar_position: 10
---

# Graph

Compiles and renders interactive [Vega-Lite](https://vega.github.io/vega-lite/) visualizations in the console, combining high-level builders for rapid data exploration with custom declarative chart definitions.

---

:::info
The **Graph** service is available starting from version `v0.0.3-alpha`.
:::

The `Graph` service bridges Expression Lab with the client-side Vega-Lite runtime. It transforms JSON payloads and structured language arrays into reactive charts, offering both turnkey builders for common visualizations and direct access to the raw Vega-Lite compiler.

* **Vega-Lite compiler access**: Compiles custom declarative specifications through `Graph.render()`.
* **Turnkey chart builders**: Generates complete visualizations in a single call, including bar, line, area, scatter, heatmap, histogram, and timeline charts.
* **Offline geographic mapping**: Produces choropleths using bundled World and US TopoJSON datasets without external network requests or CSP issues, with built-in resolution for ISO country codes and US state abbreviations.
* **Responsive container sizing**: Scales chart dimensions to fit the console panel (`width: "container"`).

---

## How Graphs Work

Every `Graph` method includes validation, sensible defaults, and managed rendering:

* **Flexible inputs**: Accepts object literals, associative arrays, or raw JSON strings.
* **Built-in defaults**: Injects the Vega-Lite `$schema` and responsive width (`width: "container"`) if omitted.
* **Execution limits**: Each chart generation counts toward expression execution limits and timeouts.
* **Interactive & batch execution**: Renders live visualizations in the interactive console, or pools them during batch routines until revealed via `show[...]`.

### Scripting with Visualizations

Charts can be generated alongside database queries or data processing routines inside `prog[...]` blocks:

```elscript
prog[
  set['details', Database.table_details('posts')],
  show[
      Graph.bars(var['details']['indexes'], {
        x: 'Key_name',
        y: 'Cardinality',
        horizontal: true,
        title: 'Posts Table Index Cardinality',
        color_value: Graph.COLOR_BLUE
      })
  ]
]
```

<VegaLite spec={{
  "$schema": "https://vega.github.io/schema/vega-lite/v6.json",
  "title": "Posts Table Index Cardinality",
  "width": "container",
  "height": 260,
  "data": {
    "values": [
      { "Key_name": "PRIMARY", "Column_name": "ID", "Cardinality": 8 },
      { "Key_name": "post_name", "Column_name": "post_name", "Cardinality": 8 },
      { "Key_name": "type_status_date", "Column_name": "post_type", "Cardinality": 4 },
      { "Key_name": "type_status_date", "Column_name": "post_status", "Cardinality": 6 },
      { "Key_name": "type_status_date", "Column_name": "post_date", "Cardinality": 8 },
      { "Key_name": "type_status_date", "Column_name": "ID", "Cardinality": 8 },
      { "Key_name": "post_parent", "Column_name": "post_parent", "Cardinality": 1 },
      { "Key_name": "post_author", "Column_name": "post_author", "Cardinality": 2 },
      { "Key_name": "type_status_author", "Column_name": "post_type", "Cardinality": 4 },
      { "Key_name": "type_status_author", "Column_name": "post_status", "Cardinality": 6 },
      { "Key_name": "type_status_author", "Column_name": "post_author", "Cardinality": 6 }
    ]
  },
  "mark": { "type": "bar", "cornerRadiusEnd": 4, "tooltip": true, "color": "#3858e9" },
  "encoding": {
    "x": { "field": "Cardinality", "type": "quantitative", "axis": { "title": "Cardinality" } },
    "y": { "field": "Key_name", "type": "nominal", "axis": { "title": "Key Name" } }
  }
}} />


---

## Constants & Visual Tokens

The `Graph` class provides visual constants for colors, palettes, and TopoJSON geographic layers. When accessed using the [scripting syntax](./../getting-started/scripting), class constants resolve as properties on the `Graph` object instance (such as `Graph.COLOR_BLUE` or `Graph.SCHEME_VIRIDIS`).

### Schema & Container Layout

| Constant | Value | Description |
| :--- | :--- | :--- |
| `DEFAULT_SCHEMA` | `'https://vega.github.io/schema/vega-lite/v6.json'` | Default schema definition injected when `$schema` is omitted. |
| `DEFAULT_WIDTH` | `'container'` | Instructs the visualization container to expand across available viewport width. |

### Geographic TopoJSON Assets

| Constant | Value | Description |
| :--- | :--- | :--- |
| `COUNTRIES` | `'countries-110m.json'` | Bundled TopoJSON dataset for world countries (1:110m resolution). |
| `LAND` | `'land-110m.json'` | Bundled TopoJSON dataset for continental landmass boundaries (1:110m resolution). |
| `USA_STATES` | `'states-10m.json'` | Bundled TopoJSON dataset for United States features (1:10m resolution). |

### Color Palette

| Constant | Hex Value | Semantic Usage |
| :--- | :--- | :--- |
| `COLOR_BLUE` | `'#3858e9'` <ColorSwatch color="#3858e9" /> | Primary accent for default marks across single-series visualizations. |
| `COLOR_RED` | `'#e53935'` <ColorSwatch color="#e53935" /> | Error thresholds, warning boundaries, and critical indicators. |
| `COLOR_GREEN` | `'#43a047'` <ColorSwatch color="#43a047" /> | Positive metrics, success states, and growth indicators. |
| `COLOR_PURPLE` | `'#8e24aa'` <ColorSwatch color="#8e24aa" /> | Categorical accents and secondary distributions. |
| `COLOR_ORANGE` | `'#fb8c00'` <ColorSwatch color="#fb8c00" /> | Moderate warning thresholds and notice highlights. |
| `COLOR_CYAN` | `'#00acc1'` <ColorSwatch color="#00acc1" /> | Neutral continuous series and network metrics. |
| `COLOR_GRAY` | `'#78909c'` <ColorSwatch color="#78909c" /> | Baseline boundaries, background markers, and reference tracks. |
| `COLOR_DARK` | `'#263238'` <ColorSwatch color="#263238" /> | High-contrast markers and dark outline rules. |

### Continuous & Categorical Schemes

| Constant | Identifier | Color Type | Colors | Description |
| :--- | :--- | :--- | :---: | :--- |
| `SCHEME_BLUES` | `'blues'` | Sequential | <SchemeSwatch scheme="blues" /> | Single-hue gradient ranging from light to dark blue. |
| `SCHEME_REDS` | `'reds'` | Sequential | <SchemeSwatch scheme="reds" /> | Single-hue gradient ranging from light to deep red. |
| `SCHEME_GREENS` | `'greens'` | Sequential | <SchemeSwatch scheme="greens" /> | Single-hue gradient ranging from light to forest green. |
| `SCHEME_PURPLES` | `'purples'` | Sequential | <SchemeSwatch scheme="purples" /> | Single-hue gradient ranging from lavender to deep purple. |
| `SCHEME_ORANGES` | `'oranges'` | Sequential | <SchemeSwatch scheme="oranges" /> | Single-hue gradient ranging from peach to dark orange. |
| `SCHEME_VIRIDIS` | `'viridis'` | Perceptual | <SchemeSwatch scheme="viridis" /> | Multi-hue uniform gradient (purple to teal to yellow). |
| `SCHEME_INFERNO` | `'inferno'` | Perceptual | <SchemeSwatch scheme="inferno" /> | High-contrast heat gradient (black to red to yellow). |
| `SCHEME_MAGMA` | `'magma'` | Perceptual | <SchemeSwatch scheme="magma" /> | Multi-hue gradient (dark violet to pink to white). |
| `SCHEME_CATEGORY10` | `'category10'` | Discrete | <SchemeSwatch scheme="category10" /> | Ten distinct categorical hues for nominal dimensions. |
| `SCHEME_TABLEAU10` | `'tableau10'` | Discrete | <SchemeSwatch scheme="tableau10" /> | Ten balanced hues for multi-series lines, slices, and groups. |

---

## Low-Level Specification Bridge

### Graph.render()

Renders an arbitrary Vega-Lite v6 specification payload directly in the console visualization panel.

```elscriptsignature
Graph.render(
  array|object|string:payload,
  string:title = 'Graph'
): void
```

#### Parameters

* **`payload`** (`array|object|string`, _required_): An associative array, object literal, or valid JSON string containing the complete Vega-Lite specification.
* **`title`** (`string`, _optional_): Header label for the output tab. Defaults to `'Graph'`. If left as default, the title declared inside the specification (`payload.title` or `payload.description`) takes precedence.

#### Normalization & Error Behavior

1. **String Input**: Parsed via `json_decode()`. Throws `\InvalidArgumentException` if string is empty or contains malformed JSON syntax.
2. **Object Input**: Converted recursively to an associative array to isolate the engine from unexpected host references.
3. **Array Input**: Validated to ensure the root payload is an associative map rather than an indexed sequential list.
4. **Default Injection**: If missing, `$schema` receives `DEFAULT_SCHEMA` and `width` receives `DEFAULT_WIDTH`.

#### Example

```elscript
Graph.render({
  '$schema': 'https://vega.github.io/schema/vega-lite/v6.json',
  title: 'Storage by Asset Type',
  data: {
    values: [
      { asset: 'Images', megabytes: 340 },
      { asset: 'Videos', megabytes: 820 },
      { asset: 'Documents', megabytes: 110 }
    ]
  },
  mark: { type: 'bar', color: Graph.COLOR_BLUE },
  encoding: {
    x: { field: 'asset', type: 'nominal', axis: { title: 'Asset Category' } },
    y: { field: 'megabytes', type: 'quantitative', axis: { title: 'Size (MB)' } }
  }
}, 'Storage Breakdown')
```

<VegaLite spec={{
  "$schema": "https://vega.github.io/schema/vega-lite/v6.json",
  "title": "Storage by Asset Type",
  "width": "container",
  "height": 260,
  "data": {
    "values": [
      { "asset": "Images", "megabytes": 340 },
      { "asset": "Videos", "megabytes": 820 },
      { "asset": "Documents", "megabytes": 110 }
    ]
  },
  "mark": { "type": "bar", "color": "#3858e9", "cornerRadiusEnd": 4, "tooltip": true },
  "encoding": {
    "x": { "field": "asset", "type": "nominal", "axis": { "title": "Asset Category" } },
    "y": { "field": "megabytes", "type": "quantitative", "axis": { "title": "Size (MB)" } }
  }
}} />

---

## High-Level Chart Builders

### Graph.bars()

Compiles and renders a bar chart from tabular collections or associative key-value dictionaries.

```elscriptsignature
Graph.bars(
  mixed:data,
  array:options = []
): void
```

#### Parameters

* **`data`** (`mixed`, _required_): Array of associative records (such as `[{ role: 'Editor', count: 12 }]`) or a key-value dictionary (such as `{ Draft: 14, Published: 89 }`).
* **`options`** (`array`, _optional_): Configuration settings.

#### Options Dictionary

| Key | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `title` | `string` | `'Bar Chart'` | Visualization title and tab label. |
| `x` | `string` | _Auto-detected_ | Field mapped to horizontal coordinates. |
| `y` | `string` | _Auto-detected_ | Field mapped to vertical coordinates. |
| `color` | `string` | `null` | Record field used for categorical color partitioning. |
| `color_value` | `string` | `Graph.COLOR_BLUE` | Fixed hex color applied to all bars when `color` is omitted. |
| `horizontal` | `bool` | `false` | When `true`, inverts axes to render horizontal bars. |
| `sort` | `string\|array` | `null` | Custom order definition for categorical dimension. |
| `label_angle` | `int` | `-45` | Rotation angle in degrees for axis labels. |
| `height` | `int` | `360` | Pixel height of the visualization area. |

#### Examples

##### Vertical Category Breakdown

```elscript
Graph.bars([
  { role: 'Administrator', users: 4 },
  { role: 'Editor', users: 12 },
  { role: 'Author', users: 28 },
  { role: 'Contributor', users: 45 },
  { role: 'Subscriber', users: 190 }
], {
  title: 'Active Users by Role',
  color_value: Graph.COLOR_PURPLE
})
```

<VegaLite spec={{
  "$schema": "https://vega.github.io/schema/vega-lite/v6.json",
  "title": "Active Users by Role",
  "width": "container",
  "height": 260,
  "data": {
    "values": [
      { "role": "Administrator", "users": 4 },
      { "role": "Editor", "users": 12 },
      { "role": "Author", "users": 28 },
      { "role": "Contributor", "users": 45 },
      { "role": "Subscriber", "users": 190 }
    ]
  },
  "mark": { "type": "bar", "cornerRadiusEnd": 4, "tooltip": true, "color": "#8e24aa" },
  "encoding": {
    "x": { "field": "role", "type": "nominal", "axis": { "title": "Role", "labelAngle": -45 } },
    "y": { "field": "users", "type": "quantitative", "axis": { "title": "Users" } }
  }
}} />

##### Horizontal Layout from Key-Value Map

```elscript
Graph.bars({
  'Draft': 14,
  'Pending': 6,
  'Private': 3,
  'Published': 89
}, {
  title: 'Post Inventory by Status',
  horizontal: true,
  color_value: Graph.COLOR_BLUE
})
```

<VegaLite spec={{
  "$schema": "https://vega.github.io/schema/vega-lite/v6.json",
  "title": "Post Inventory by Status",
  "width": "container",
  "height": 220,
  "data": {
    "values": [
      { "category": "Draft", "value": 14 },
      { "category": "Pending", "value": 6 },
      { "category": "Private", "value": 3 },
      { "category": "Published", "value": 89 }
    ]
  },
  "mark": { "type": "bar", "cornerRadiusEnd": 4, "tooltip": true, "color": "#3858e9" },
  "encoding": {
    "x": { "field": "value", "type": "quantitative", "axis": { "title": "Count" } },
    "y": { "field": "category", "type": "nominal", "axis": { "title": "Status" } }
  }
}} />

---

### Graph.lines()

Renders continuous trend lines and multi-series time comparisons.

```elscriptsignature
Graph.lines(
  mixed:data,
  array:options = []
): void
```

#### Options Dictionary

| Key | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `title` | `string` | `'Line Chart'` | Visualization title. |
| `x` | `string` | _Auto-detected_ | Field mapped to horizontal coordinates. |
| `y` | `string` | _Auto-detected_ | Field mapped to vertical coordinates. |
| `color` | `string` | `null` | Field used for multi-series color grouping. |
| `color_value` | `string` | `Graph.COLOR_BLUE` | Fixed hex color for single series. |
| `points` | `bool` | `true` | Renders discrete circular vertex markers along paths. |
| `sort` | `string\|array` | `null` | Sorting specification for horizontal coordinate axis. |
| `label_angle` | `int` | `-45` | Rotation angle in degrees for axis labels. |
| `height` | `int` | `360` | Pixel height of the visualization area. |

#### Example

```elscript
Graph.lines([
  { month: '2026-01', volume: 1420 },
  { month: '2026-02', volume: 1890 },
  { month: '2026-03', volume: 2400 },
  { month: '2026-04', volume: 2180 },
  { month: '2026-05', volume: 3050 },
  { month: '2026-06', volume: 3820 }
], {
  title: 'Monthly Network Throughput',
  color_value: Graph.COLOR_GREEN
})
```

<VegaLite spec={{
  "$schema": "https://vega.github.io/schema/vega-lite/v6.json",
  "title": "Monthly Network Throughput",
  "width": "container",
  "height": 260,
  "data": {
    "values": [
      { "month": "2026-01", "volume": 1420 },
      { "month": "2026-02", "volume": 1890 },
      { "month": "2026-03", "volume": 2400 },
      { "month": "2026-04", "volume": 2180 },
      { "month": "2026-05", "volume": 3050 },
      { "month": "2026-06", "volume": 3820 }
    ]
  },
  "mark": { "type": "line", "point": true, "tooltip": true, "color": "#43a047" },
  "encoding": {
    "x": { "field": "month", "type": "nominal", "axis": { "title": "Month", "labelAngle": -45 } },
    "y": { "field": "volume", "type": "quantitative", "axis": { "title": "Volume (GB)" } },
    "color": { "value": "#43a047" }
  }
}} />

---

### Graph.pie()

Renders proportional arc segments for composition analysis, with optional inner cutout hole for donut charts.

```elscriptsignature
Graph.pie(
  mixed:data,
  array:options = []
): void
```

#### Options Dictionary

| Key | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `title` | `string` | `'Pie Chart'` / `'Donut Chart'` | Visualization title. |
| `category` | `string` | _Auto-detected_ | Field containing slice categorical labels. |
| `value` | `string` | _Auto-detected_ | Field containing slice numeric magnitudes. |
| `donut` | `bool` | `false` | Renders a central cutout hole when `true`. |
| `inner_radius` | `int` | `65` | Pixel radius of inner cutout hole when `donut` is active. |
| `scheme` | `string` | `Graph.SCHEME_TABLEAU10` | Vega-Lite color palette for categorical slices. |
| `height` | `int` | `360` | Pixel height of the visualization area. |

#### Example

```elscript
Graph.pie([
  { device: 'Mobile', share: 58 },
  { device: 'Desktop', share: 34 },
  { device: 'Tablet', share: 8 }
], {
  title: 'Client Traffic Share by Form Factor',
  donut: true,
  inner_radius: 75
})
```

<VegaLite spec={{
  "$schema": "https://vega.github.io/schema/vega-lite/v6.json",
  "title": "Client Traffic Share by Form Factor",
  "width": "container",
  "height": 280,
  "data": {
    "values": [
      { "device": "Mobile", "share": 58 },
      { "device": "Desktop", "share": 34 },
      { "device": "Tablet", "share": 8 }
    ]
  },
  "mark": { "type": "arc", "innerRadius": 75, "tooltip": true },
  "encoding": {
    "theta": { "field": "share", "type": "quantitative", "stack": true },
    "color": { "field": "device", "type": "nominal", "scale": { "scheme": "tableau10" }, "legend": { "title": "Device" } }
  }
}} />

---

### Graph.worldmap()

Renders a two-layer offline choropleth world map projected onto an [Equal Earth projection](https://en.wikipedia.org/wiki/Equal_Earth_projection)

```elscriptsignature
Graph.worldmap(
  mixed:data,
  array:options = []
): void
```

#### Automatic Country Code Resolution

The method resolves country identifiers into standard TopoJSON numeric geometry IDs (`id`):
* **ISO 3166-1 Alpha-2**: `'US'`, `'ES'`, `'DE'`, `'FR'`, `'JP'`. Output from [IPLookup.to_country()](./ip-lookup#iplookupto_country) binds directly into this method.
* **ISO 3166-1 Alpha-3**: `'USA'`, `'ESP'`, `'DEU'`, `'FRA'`, `'JPN'`.
* **Standard Country Names**: `'United States'`, `'Germany'`, `'Spain'`.
* **Numeric TopoJSON IDs**: `'840'`, `'724'`, `'276'`.

#### Options Dictionary

| Key | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `title` | `string` | `'World Map'` | Visualization title. |
| `key` | `string` | _Auto-detected_ | Field holding country codes or names. |
| `value` | `string` | _Auto-detected_ | Field holding quantitative heat values. |
| `label` | `string` | `null` | Optional metadata field included in hover tooltips. |
| `scheme` | `string` | `Graph.SCHEME_BLUES` | Sequential color gradient scheme. |
| `projection` | `string` | `'equalEarth'` | Cartographic projection algorithm. |
| `legend_title` | `string` | _Field Name_ | Custom title for the continuous color scale legend. |
| `height` | `int` | `480` | Pixel height of the map viewport. |

#### Example

```elscript
Graph.worldmap({
  'US': 1940,
  'ES': 820,
  'DE': 1150,
  'BR': 670,
  'JP': 930,
  'AU': 450,
  'CA': 610,
  'FR': 780
}, {
  title: 'Global Request Ingestion by Origin',
  scheme: Graph.SCHEME_BLUES
})
```

<VegaLite spec={{
  "$schema": "https://vega.github.io/schema/vega-lite/v6.json",
  "title": "Global Request Ingestion by Origin",
  "width": "container",
  "height": 340,
  "projection": { "type": "equalEarth" },
  "layer": [
    {
      "data": { "url": "countries-110m.json", "format": { "type": "topojson", "feature": "countries" } },
      "mark": { "type": "geoshape", "fill": "#eef0f3", "stroke": "#bebebe", "strokeWidth": 0.5 }
    },
    {
      "data": { "url": "countries-110m.json", "format": { "type": "topojson", "feature": "countries" } },
      "transform": [
        {
          "lookup": "id",
          "from": {
            "data": {
              "values": [
                { "id": "840", "country_name": "United States", "iso": "US", "requests": 1940 },
                { "id": "724", "country_name": "Spain", "iso": "ES", "requests": 820 },
                { "id": "276", "country_name": "Germany", "iso": "DE", "requests": 1150 },
                { "id": "076", "country_name": "Brazil", "iso": "BR", "requests": 670 },
                { "id": "392", "country_name": "Japan", "iso": "JP", "requests": 930 },
                { "id": "036", "country_name": "Australia", "iso": "AU", "requests": 450 },
                { "id": "124", "country_name": "Canada", "iso": "CA", "requests": 610 },
                { "id": "250", "country_name": "France", "iso": "FR", "requests": 780 }
              ]
            },
            "key": "id",
            "fields": ["requests", "country_name", "iso"]
          }
        },
        { "filter": "isValid(datum['requests'])" }
      ],
      "mark": { "type": "geoshape", "stroke": "#000000", "strokeWidth": 0.4 },
      "encoding": {
        "color": {
          "field": "requests",
          "type": "quantitative",
          "scale": { "scheme": "blues", "zero": false },
          "legend": { "title": "Requests", "orient": "bottom-left", "gradientLength": 160 }
        },
        "tooltip": [
          { "field": "country_name", "type": "nominal", "title": "Country" },
          { "field": "iso", "type": "nominal", "title": "Code" },
          { "field": "requests", "type": "quantitative", "title": "Requests" }
        ]
      }
    }
  ]
}} />

---

### Graph.usa()

Renders a two-layer offline choropleth map of the United States using an [Albers USA projection](https://en.wikipedia.org/wiki/Albers_projection).

```elscriptsignature
Graph.usa(
  mixed:data,
  array:options = []
): void
```

#### State Code Resolution

Normalizes state references to 2-digit FIPS strings:
* **Postal Codes**: `'CA'`, `'TX'`, `'NY'`, `'FL'`, `'WA'`.
* **State Names**: `'California'`, `'Texas'`, `'New York'`.
* **FIPS Identifiers**: `'06'`, `'48'`, `'36'`, `6`, `48`.

#### Options Dictionary

| Key | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `title` | `string` | `'US State Map'` | Visualization title. |
| `key` | `string` | _Auto-detected_ | Field holding state postal codes, names, or FIPS. |
| `value` | `string` | _Auto-detected_ | Field holding metric values. |
| `label` | `string` | `null` | Optional extra dimension displayed in tooltips. |
| `scheme` | `string` | `Graph.SCHEME_BLUES` | Sequential color scheme. |
| `projection` | `string` | `'albersUsa'` | Cartographic projection algorithm. |
| `legend_title` | `string` | _Field Name_ | Custom legend title. |
| `height` | `int` | `450` | Pixel height of the viewport. |

#### Example

```elscript
Graph.usa({
  'CA': 1420,
  'TX': 980,
  'NY': 1150,
  'FL': 870,
  'IL': 520,
  'WA': 610,
  'CO': 430,
  'MA': 560
}, {
  title: 'Traffic Density across United States',
  scheme: Graph.SCHEME_PURPLES
})
```

<VegaLite spec={{
  "$schema": "https://vega.github.io/schema/vega-lite/v6.json",
  "title": "Traffic Density across United States",
  "width": "container",
  "height": 340,
  "projection": { "type": "albersUsa" },
  "layer": [
    {
      "data": { "url": "states-10m.json", "format": { "type": "topojson", "feature": "states" } },
      "mark": { "type": "geoshape", "fill": "#eef0f3", "stroke": "#bebebe", "strokeWidth": 0.5 }
    },
    {
      "data": { "url": "states-10m.json", "format": { "type": "topojson", "feature": "states" } },
      "transform": [
        {
          "lookup": "id",
          "from": {
            "data": {
              "values": [
                { "id": "06", "state_name": "California", "state_code": "CA", "traffic": 1420 },
                { "id": "48", "state_name": "Texas", "state_code": "TX", "traffic": 980 },
                { "id": "36", "state_name": "New York", "state_code": "NY", "traffic": 1150 },
                { "id": "12", "state_name": "Florida", "state_code": "FL", "traffic": 870 },
                { "id": "17", "state_name": "Illinois", "state_code": "IL", "traffic": 520 },
                { "id": "53", "state_name": "Washington", "state_code": "WA", "traffic": 610 },
                { "id": "08", "state_name": "Colorado", "state_code": "CO", "traffic": 430 },
                { "id": "25", "state_name": "Massachusetts", "state_code": "MA", "traffic": 560 }
              ]
            },
            "key": "id",
            "fields": ["traffic", "state_name", "state_code"]
          }
        },
        { "filter": "isValid(datum['traffic'])" }
      ],
      "mark": { "type": "geoshape", "stroke": "#000000", "strokeWidth": 0.4 },
      "encoding": {
        "color": {
          "field": "traffic",
          "type": "quantitative",
          "scale": { "scheme": "purples", "zero": false },
          "legend": { "title": "Traffic", "orient": "bottom-left", "gradientLength": 160 }
        },
        "tooltip": [
          { "field": "state_name", "type": "nominal", "title": "State" },
          { "field": "state_code", "type": "nominal", "title": "Code" },
          { "field": "traffic", "type": "quantitative", "title": "Traffic" }
        ]
      }
    }
  ]
}} />

---

### Graph.boxplot()

Renders box-and-whisker distribution charts displaying medians, interquartile ranges (Q1–Q3), and min-max boundaries across categorical dimensions.

```elscriptsignature
Graph.boxplot(
  mixed:data,
  array:options = []
): void
```

#### Options Dictionary

| Key | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `title` | `string` | `'Boxplot'` | Visualization title. |
| `x` | `string` | _Auto-detected_ | Categorical dimension mapped to horizontal axis. |
| `y` | `string` | _Auto-detected_ | Quantitative metric mapped to vertical axis. |
| `color` | `string` | `null` | Dimension used for discrete group color encoding. |
| `color_value` | `string` | `Graph.COLOR_BLUE` | Fill color for single-series boxes. |
| `label_angle` | `int` | `-45` | Rotation angle in degrees for axis labels. |
| `height` | `int` | `360` | Pixel height of the visualization area. |

#### Example

```elscript
Graph.boxplot([
  { endpoint: '/api/posts', latency_ms: 42 },
  { endpoint: '/api/posts', latency_ms: 58 },
  { endpoint: '/api/posts', latency_ms: 120 },
  { endpoint: '/api/posts', latency_ms: 68 },
  { endpoint: '/api/posts', latency_ms: 95 },
  { endpoint: '/api/users', latency_ms: 85 },
  { endpoint: '/api/users', latency_ms: 92 },
  { endpoint: '/api/users', latency_ms: 240 },
  { endpoint: '/api/users', latency_ms: 110 },
  { endpoint: '/api/users', latency_ms: 135 }
], {
  title: 'Endpoint Latency Distribution',
  color_value: Graph.COLOR_BLUE
})
```

<VegaLite spec={{
  "$schema": "https://vega.github.io/schema/vega-lite/v6.json",
  "title": "Endpoint Latency Distribution",
  "width": "container",
  "height": 280,
  "data": {
    "values": [
      { "endpoint": "/api/posts", "latency_ms": 42 },
      { "endpoint": "/api/posts", "latency_ms": 58 },
      { "endpoint": "/api/posts", "latency_ms": 120 },
      { "endpoint": "/api/posts", "latency_ms": 68 },
      { "endpoint": "/api/posts", "latency_ms": 95 },
      { "endpoint": "/api/users", "latency_ms": 85 },
      { "endpoint": "/api/users", "latency_ms": 92 },
      { "endpoint": "/api/users", "latency_ms": 240 },
      { "endpoint": "/api/users", "latency_ms": 110 },
      { "endpoint": "/api/users", "latency_ms": 135 }
    ]
  },
  "mark": { "type": "boxplot", "extent": "min-max", "box": { "fill": "#3858e9" }, "rule": { "color": "black" }, "tooltip": true },
  "encoding": {
    "x": { "field": "endpoint", "type": "nominal", "axis": { "title": "Endpoint", "labelAngle": -45 } },
    "y": { "field": "latency_ms", "type": "quantitative", "axis": { "title": "Latency (ms)" } }
  }
}} />

---

### Graph.scatter()

Renders scatter and bubble plots for multi-dimensional correlation analysis between quantitative variables.

```elscriptsignature
Graph.scatter(
  mixed:data,
  array:options = []
): void
```

#### Options Dictionary

| Key | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `title` | `string` | `'Scatter Plot'` | Visualization title. |
| `x` | `string` | _Auto-detected_ | Quantitative field for horizontal coordinates. |
| `y` | `string` | _Auto-detected_ | Quantitative field for vertical coordinates. |
| `color` | `string` | `null` | Field for discrete color grouping or literal hex color code. |
| `size` | `string\|int\|float` | `null` | Field mapped to point area or fixed marker size in square pixels. |
| `opacity` | `float` | `0.7` | Marker fill opacity between `0.0` and `1.0`. |
| `zero` | `bool` | `false` | When `false`, scales axes to data extents rather than forcing zero baseline. |
| `label_angle` | `int` | `-45` | Rotation angle in degrees for axis labels. |
| `height` | `int` | `360` | Pixel height of the visualization area. |

#### Example

```elscript
Graph.scatter([
  { queries: 6,   duration_ms: 18,  rows: 85,    status: 'ok' },
  { queries: 12,  duration_ms: 28,  rows: 240,   status: 'ok' },
  { queries: 16,  duration_ms: 35,  rows: 410,   status: 'ok' },
  { queries: 22,  duration_ms: 52,  rows: 620,   status: 'ok' },
  { queries: 32,  duration_ms: 70,  rows: 880,   status: 'ok' },
  { queries: 45,  duration_ms: 135, rows: 2800,  status: 'warning' },
  { queries: 58,  duration_ms: 145, rows: 3100,  status: 'warning' },
  { queries: 72,  duration_ms: 230, rows: 6800,  status: 'warning' },
  { queries: 90,  duration_ms: 360, rows: 12500, status: 'critical' },
  { queries: 116, duration_ms: 560, rows: 31000, status: 'critical' }
], {
  title: 'Database Queries vs Execution Duration',
  x: 'queries',
  y: 'duration_ms',
  color: 'status',
  size: 'rows',
  opacity: 0.75,
  zero: false
})
```

<VegaLite spec={{
  "$schema": "https://vega.github.io/schema/vega-lite/v6.json",
  "title": "Database Queries vs Execution Duration",
  "width": "container",
  "height": 280,
  "data": {
    "values": [
      { "queries": 6,   "duration_ms": 18,  "rows": 85,    "status": "ok" },
      { "queries": 12,  "duration_ms": 28,  "rows": 240,   "status": "ok" },
      { "queries": 16,  "duration_ms": 35,  "rows": 410,   "status": "ok" },
      { "queries": 22,  "duration_ms": 52,  "rows": 620,   "status": "ok" },
      { "queries": 32,  "duration_ms": 70,  "rows": 880,   "status": "ok" },
      { "queries": 45,  "duration_ms": 135, "rows": 2800,  "status": "warning" },
      { "queries": 58,  "duration_ms": 145, "rows": 3100,  "status": "warning" },
      { "queries": 72,  "duration_ms": 230, "rows": 6800,  "status": "warning" },
      { "queries": 90,  "duration_ms": 360, "rows": 12500, "status": "critical" },
      { "queries": 116, "duration_ms": 560, "rows": 31000, "status": "critical" }
    ]
  },
  "mark": { "type": "point", "filled": true, "opacity": 0.75, "tooltip": true },
  "encoding": {
    "x": { "field": "queries", "type": "quantitative", "scale": { "zero": false }, "axis": { "title": "Queries" } },
    "y": { "field": "duration_ms", "type": "quantitative", "scale": { "zero": false }, "axis": { "title": "Duration (ms)" } },
    "color": { "field": "status", "type": "nominal", "legend": { "title": "Status" } },
    "size": { "field": "rows", "type": "quantitative", "legend": { "title": "Rows Examined" } }
  }
}} />

---

### Graph.heatmap()

Renders a two-dimensional grid heatmap mapping categorical or ordinal coordinates to color-intensity values.

```elscriptsignature
Graph.heatmap(
  mixed:data,
  array:options = []
): void
```

#### Options Dictionary

| Key | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `title` | `string` | `'Heatmap'` | Visualization title. |
| `x` | `string` | _Auto-detected_ | Field mapped to horizontal column cells. |
| `y` | `string` | _Auto-detected_ | Field mapped to vertical row cells. |
| `value` | `string` | _Auto-detected_ | Metric mapped to cell color intensity. |
| `scheme` | `string` | `Graph.SCHEME_BLUES` | Sequential color palette for cell heat. |
| `stroke` | `string` | `'#ffffff'` <ColorSwatch color="#ffffff" /> | Grid separation line color. |
| `legend_title` | `string` | _Field Name_ | Custom legend title. |
| `label_angle` | `int` | `-45` | Rotation angle in degrees for axis labels. |
| `height` | `int` | `360` | Pixel height of the visualization area. |

#### Example

```elscript
Graph.heatmap([
    { 'day': 'Mon', 'hour': 9,  'events': 50 },
    { 'day': 'Mon', 'hour': 10, 'events': 320 },
    { 'day': 'Mon', 'hour': 11, 'events': 25 },
    { 'day': 'Mon', 'hour': 12, 'events': 250 },
    { 'day': 'Mon', 'hour': 13, 'events': 195 },
    { 'day': 'Mon', 'hour': 14, 'events': 331 },
    { 'day': 'Mon', 'hour': 15, 'events': 80 },
    { 'day': 'Tue', 'hour': 9,  'events': 190 },
    { 'day': 'Tue', 'hour': 10, 'events': 78 },
    { 'day': 'Tue', 'hour': 11, 'events': 22 },
    { 'day': 'Tue', 'hour': 12, 'events': 25 },
    { 'day': 'Tue', 'hour': 13, 'events': 250 },
    { 'day': 'Tue', 'hour': 14, 'events': 410 },
    { 'day': 'Tue', 'hour': 15, 'events': 295 },
    { 'day': 'Wed', 'hour': 9,  'events': 190 },
    { 'day': 'Wed', 'hour': 10, 'events': 46 },
    { 'day': 'Wed', 'hour': 11, 'events': 101 },
    { 'day': 'Wed', 'hour': 12, 'events': 610 },
    { 'day': 'Wed', 'hour': 13, 'events': 710 },
    { 'day': 'Wed', 'hour': 14, 'events': 155 },
    { 'day': 'Wed', 'hour': 15, 'events': 25 }
], {
    'title': 'System Events by Day and Hour',
    'x': 'hour',
    'y': 'day',
    'value': 'events',
    'height' : 180,
    'scheme': Graph.SCHEME_PURPLES
})
```

<VegaLite spec={{
  "$schema": "https://vega.github.io/schema/vega-lite/v6.json",
  "title": "System Events by Day and Hour",
  "width": "container",
  "height": 180,
  "data": {
    "values": [
      { "day": "Mon", "hour": 9,  "events": 50 },
      { "day": "Mon", "hour": 10, "events": 320 },
      { "day": "Mon", "hour": 11, "events": 25 },
      { "day": "Mon", "hour": 12, "events": 250 },
      { "day": "Mon", "hour": 13, "events": 195 },
      { "day": "Mon", "hour": 14, "events": 331 },
      { "day": "Mon", "hour": 15, "events": 80 },
      { "day": "Tue", "hour": 9,  "events": 190 },
      { "day": "Tue", "hour": 10, "events": 78 },
      { "day": "Tue", "hour": 11, "events": 22 },
      { "day": "Tue", "hour": 12, "events": 25 },
      { "day": "Tue", "hour": 13, "events": 250 },
      { "day": "Tue", "hour": 14, "events": 410 },
      { "day": "Tue", "hour": 15, "events": 295 },
      { "day": "Wed", "hour": 9,  "events": 190 },
      { "day": "Wed", "hour": 10, "events": 46 },
      { "day": "Wed", "hour": 11, "events": 101 },
      { "day": "Wed", "hour": 12, "events": 610 },
      { "day": "Wed", "hour": 13, "events": 710 },
      { "day": "Wed", "hour": 14, "events": 155 },
      { "day": "Wed", "hour": 15, "events": 25 }
    ]
  },
  "mark": { "type": "rect", "stroke": "#ffffff", "strokeWidth": 1 },
  "encoding": {
    "x": { "field": "hour", "type": "ordinal", "axis": { "title": "Hour", "labelAngle": 0 } },
    "y": { "field": "day", "type": "nominal", "axis": { "title": "Day" } },
    "color": { "field": "events", "type": "quantitative", "scale": { "scheme": "purples" }, "legend": { "title": "Events" } },
    "tooltip": [
      { "field": "day", "type": "nominal", "title": "Day" },
      { "field": "hour", "type": "ordinal", "title": "Hour" },
      { "field": "events", "type": "quantitative", "title": "Events" }
    ]
  }
}} />

---

### Graph.histogram()

Partitions continuous quantitative measurements into discrete interval bins and calculates distribution counts.

```elscriptsignature
Graph.histogram(
  mixed:data,
  array:options = []
): void
```

#### Input Modes

* **Numeric Scalar Array**: List of raw floats or integers (such as query runtimes or latencies).
* **Tabular Records**: Objects containing a targeted numeric metric column.

#### Options Dictionary

| Key | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `title` | `string` | `'Histogram'` | Visualization title. |
| `field` | `string` | _Auto-detected_ | Numeric field to partition into bins. |
| `bins` | `int` | `20` | Target maximum bin intervals. |
| `maxbins` | `int` | `null` | Upper constraint on generated bin count. |
| `step` | `float` | `null` | Fixed interval width for bin steps. |
| `color` | `string` | `Graph.COLOR_BLUE` | Categorical color partition or fixed color code. |
| `label_angle` | `int` | `-45` | Rotation angle in degrees for axis labels. |
| `height` | `int` | `360` | Pixel height of the visualization area. |

#### Example

```elscript
Graph.histogram([
  12.4, 15.1, 18.3, 19.0, 21.2, 22.8, 23.1, 28.5, 34.2, 45.0,
  18.1, 21.9, 23.4, 25.0, 27.2, 31.8, 35.1, 38.5, 42.2, 49.0
], {
  title: 'Query Duration Distribution (ms)',
  bins: 8,
  color: Graph.COLOR_CYAN
})
```

<VegaLite spec={{
  "$schema": "https://vega.github.io/schema/vega-lite/v6.json",
  "title": "Query Duration Distribution (ms)",
  "width": "container",
  "height": 260,
  "data": {
    "values": [
      { "value": 12.4 }, { "value": 15.1 }, { "value": 18.3 }, { "value": 19.0 },
      { "value": 21.2 }, { "value": 22.8 }, { "value": 23.1 }, { "value": 28.5 },
      { "value": 34.2 }, { "value": 45.0 }, { "value": 18.1 }, { "value": 21.9 },
      { "value": 23.4 }, { "value": 25.0 }, { "value": 27.2 }, { "value": 31.8 },
      { "value": 35.1 }, { "value": 38.5 }, { "value": 42.2 }, { "value": 49.0 }
    ]
  },
  "mark": { "type": "bar", "color": "#00acc1" },
  "encoding": {
    "x": { "field": "value", "type": "quantitative", "bin": { "maxbins": 8 }, "axis": { "title": "Duration (ms)" } },
    "y": { "aggregate": "count", "type": "quantitative", "axis": { "title": "Count" } },
    "tooltip": [
      { "field": "value", "bin": true, "type": "quantitative", "title": "Duration Range" },
      { "aggregate": "count", "type": "quantitative", "title": "Count" }
    ]
  }
}} />

---

### Graph.area()

Renders filled area charts for cumulative volume tracking, stacked multi-series infrastructure metrics, streamgraphs, and 100% normalized proportions.

```elscriptsignature
Graph.area(
  mixed:data,
  array:options = []
): void
```

#### Options Dictionary

| Key | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `title` | `string` | `'Area Chart'` | Visualization title. |
| `x` | `string` | _Auto-detected_ | Field mapped to horizontal coordinates. |
| `y` | `string` | _Auto-detected_ | Field mapped to vertical coordinates. |
| `color` | `string` | `null` | Field used for multi-series color grouping. |
| `color_value` | `string` | `Graph.COLOR_BLUE` | Fixed hex color for single series. |
| `stream` | `bool` | `false` | Centers vertical stack around middle baseline to form a streamgraph. |
| `normalize` | `bool` | `false` | Scales stack to 100% relative proportions. |
| `curve` | `string` | `'monotone'` | Path interpolation mode (`'monotone'`, `'basis'`, `'linear'`). |
| `opacity` | `float` | `0.6` | Fill opacity between `0.0` and `1.0`. |
| `label_angle` | `int` | `-45` | Rotation angle in degrees for axis labels. |
| `height` | `int` | `360` | Pixel height of the visualization area. |

#### Example

```elscript
Graph.area([
  { date: '2026-09-01', traffic_gb: 320, tier: 'CDN Cache' },
  { date: '2026-09-01', traffic_gb: 180, tier: 'Application Server' },
  { date: '2026-09-02', traffic_gb: 410, tier: 'CDN Cache' },
  { date: '2026-09-02', traffic_gb: 240, tier: 'Application Server' },
  { date: '2026-09-03', traffic_gb: 490, tier: 'CDN Cache' },
  { date: '2026-09-03', traffic_gb: 290, tier: 'Application Server' },
  { date: '2026-09-04', traffic_gb: 380, tier: 'CDN Cache' },
  { date: '2026-09-04', traffic_gb: 220, tier: 'Application Server' }
], {
  title: 'Network Egress Volume by Tier',
  x: 'date',
  y: 'traffic_gb',
  color: 'tier'
})
```

<VegaLite spec={{
  "$schema": "https://vega.github.io/schema/vega-lite/v6.json",
  "title": "Network Egress Volume by Tier",
  "width": "container",
  "height": 280,
  "data": {
    "values": [
      { "date": "2026-09-01", "traffic_gb": 320, "tier": "CDN Cache" },
      { "date": "2026-09-01", "traffic_gb": 180, "tier": "Application Server" },
      { "date": "2026-09-02", "traffic_gb": 410, "tier": "CDN Cache" },
      { "date": "2026-09-02", "traffic_gb": 240, "tier": "Application Server" },
      { "date": "2026-09-03", "traffic_gb": 490, "tier": "CDN Cache" },
      { "date": "2026-09-03", "traffic_gb": 290, "tier": "Application Server" },
      { "date": "2026-09-04", "traffic_gb": 380, "tier": "CDN Cache" },
      { "date": "2026-09-04", "traffic_gb": 220, "tier": "Application Server" }
    ]
  },
  "mark": { "type": "area", "line": true, "interpolate": "monotone", "opacity": 0.6, "tooltip": true },
  "encoding": {
    "x": { "field": "date", "type": "nominal", "axis": { "title": "Date", "labelAngle": -45 } },
    "y": { "field": "traffic_gb", "type": "quantitative", "axis": { "title": "Traffic (GB)" } },
    "color": { "field": "tier", "type": "nominal", "legend": { "title": "Tier" } }
  }
}} />

---

### Graph.timeline()

Renders horizontal Gantt interval timeline bars for execution profiles, request tracing, and scheduled events.

```elscriptsignature
Graph.timeline(
  mixed:data,
  array:options = []
): void
```

#### Options Dictionary

| Key | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `title` | `string` | `'Timeline'` | Visualization title. |
| `task` | `string` | _Auto-detected_ | Field containing task name or lane identifier (vertical axis). |
| `start` | `string` | _Auto-detected_ | Field containing start timestamp or numeric millisecond offset. |
| `end` | `string` | _Auto-detected_ | Field containing end timestamp or completion offset. |
| `color` | `string` | `Graph.COLOR_BLUE` | Field for category color encoding, or fixed color code. |
| `label_angle` | `int` | `-45` | Rotation angle in degrees for axis labels. |
| `height` | `int` | _Auto-calculated_ | Pixel height of the visualization area. |

#### Example

```elscript
Graph.timeline([
  { task: 'Authentication & Session', start: 0,   end: 35 },
  { task: 'Database Query Pipeline',  start: 25,  end: 95 },
  { task: 'Object Cache Lookup',      start: 40,  end: 75 },
  { task: 'Template Rendering',       start: 90,  end: 180 },
  { task: 'Output Buffer Flush',      start: 175, end: 210 }
], {
  title: 'Request Lifecycle Execution Breakdown',
  color: Graph.COLOR_BLUE
})
```

<VegaLite spec={{
  "$schema": "https://vega.github.io/schema/vega-lite/v6.json",
  "title": "Request Lifecycle Execution Breakdown",
  "width": "container",
  "height": 220,
  "data": {
    "values": [
      { "task": "Authentication & Session", "start": 0,   "end": 35 },
      { "task": "Database Query Pipeline",  "start": 25,  "end": 95 },
      { "task": "Object Cache Lookup",      "start": 40,  "end": 75 },
      { "task": "Template Rendering",       "start": 90,  "end": 180 },
      { "task": "Output Buffer Flush",      "start": 175, "end": 210 }
    ]
  },
  "mark": { "type": "bar", "cornerRadius": 4, "height": 18, "color": "#3858e9", "tooltip": true },
  "encoding": {
    "y": { "field": "task", "type": "nominal", "axis": { "title": "Lifecycle Stage" } },
    "x": { "field": "start", "type": "quantitative", "axis": { "title": "Offset (ms)" } },
    "x2": { "field": "end" }
  }
}} />
