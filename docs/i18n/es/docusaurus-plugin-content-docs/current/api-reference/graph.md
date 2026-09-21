---
id: graph
title: Gráficos
sidebar_position: 10
---

# Gráficos

Compila y renderiza visualizaciones interactivas de [Vega-Lite](https://vega.github.io/vega-lite/) en la consola, combinando constructores de alto nivel para una rápida exploración de datos con definiciones de gráficos declarativas personalizadas.

El servicio `Graph` conecta Expression Lab con el runtime de Vega-Lite en el lado del cliente. Transforma payloads JSON y arrays estructurados del lenguaje en gráficos reactivos, ofreciendo tanto constructores listos para usar para visualizaciones comunes como acceso directo al compilador nativo de Vega-Lite.

* **Acceso al compilador de Vega-Lite**: Compila especificaciones declarativas personalizadas mediante `Graph.render()`.
* **Constructores de gráficos listos para usar**: Genera visualizaciones completas en una sola llamada, incluyendo gráficos de barras, líneas, áreas, dispersión, mapas de calor, histogramas y cronogramas.
* **Mapeo geográfico sin conexión**: Genera mapas coropléticos utilizando datasets TopoJSON integrados del mundo y de Estados Unidos sin peticiones de red externas ni problemas de CSP, con resolución integrada para códigos ISO de país y abreviaturas de estados de EE. UU.
* **Ajuste responsivo al contenedor**: Escala las dimensiones del gráfico para adaptarse al panel de la consola (`width: "container"`).

---

## Cómo funcionan los gráficos

Cada método de `Graph` incluye validación, valores por defecto bien calibrados y renderizado gestionado:

* **Entradas flexibles**: Admite objetos literales, arrays asociativos o cadenas JSON sin procesar.
* **Valores por defecto integrados**: Inyecta el `$schema` de Vega-Lite y el ancho responsivo (`width: "container"`) si se omiten.
* **Límites de ejecución**: Cada gráfico generado cuenta para los límites de ejecución y tiempos de espera (timeouts) de la expresión.
* **Ejecución interactiva y por lotes (batch)**: Renderiza visualizaciones en vivo en la consola interactiva, o las acumula durante rutinas por lotes hasta que se muestran mediante `show[...]`.

### Visualizaciones en scripts

Es posible generar gráficos junto a consultas de base de datos o rutinas de procesamiento de datos dentro de bloques `prog[...]`:

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

## Constantes y tokens visuales

La clase `Graph` proporciona constantes visuales para colores, paletas y capas geográficas TopoJSON. Al acceder a ellas mediante la [sintaxis de scripting](./../getting-started/scripting), las constantes de clase se resuelven como propiedades de la instancia del objeto `Graph` (como `Graph.COLOR_BLUE` o `Graph.SCHEME_VIRIDIS`).

### Esquema y dimensiones del contenedor

| Constante | Valor | Descripción |
| :--- | :--- | :--- |
| `DEFAULT_SCHEMA` | `'https://vega.github.io/schema/vega-lite/v6.json'` | Definición de esquema por defecto inyectada cuando se omite `$schema`. |
| `DEFAULT_WIDTH` | `'container'` | Indica al contenedor de la visualización que se expanda a lo largo del ancho disponible del viewport. |

### Recursos geográficos TopoJSON

| Constante | Valor | Descripción |
| :--- | :--- | :--- |
| `COUNTRIES` | `'countries-110m.json'` | Dataset TopoJSON integrado de países del mundo (resolución 1:110m). |
| `LAND` | `'land-110m.json'` | Dataset TopoJSON integrado para los límites de masas continentales (resolución 1:110m). |
| `USA_STATES` | `'states-10m.json'` | Dataset TopoJSON integrado para entidades de Estados Unidos (resolución 1:10m). |

### Paleta de colores

| Constante | Valor hexadecimal | Uso semántico |
| :--- | :--- | :--- |
| `COLOR_BLUE` | `'#3858e9'` <ColorSwatch color="#3858e9" /> | Acento primario para marcas por defecto en visualizaciones de una sola serie. |
| `COLOR_RED` | `'#e53935'` <ColorSwatch color="#e53935" /> | Umbrales de error, límites de advertencia e indicadores críticos. |
| `COLOR_GREEN` | `'#43a047'` <ColorSwatch color="#43a047" /> | Métricas positivas, estados de éxito e indicadores de crecimiento. |
| `COLOR_PURPLE` | `'#8e24aa'` <ColorSwatch color="#8e24aa" /> | Acentos categóricos y distribuciones secundarias. |
| `COLOR_ORANGE` | `'#fb8c00'` <ColorSwatch color="#fb8c00" /> | Umbrales de advertencia moderada y resaltado de avisos. |
| `COLOR_CYAN` | `'#00acc1'` <ColorSwatch color="#00acc1" /> | Series continuas neutrales y métricas de red. |
| `COLOR_GRAY` | `'#78909c'` <ColorSwatch color="#78909c" /> | Líneas base de referencia, marcadores de fondo y pistas inactivas. |
| `COLOR_DARK` | `'#263238'` <ColorSwatch color="#263238" /> | Marcadores de alto contraste y trazos de contorno oscuros. |

### Esquemas continuos y categóricos

| Constante | Identificador | Tipo de color | Colores | Descripción |
| :--- | :--- | :--- | :---: | :--- |
| `SCHEME_BLUES` | `'blues'` | Secuencial | <SchemeSwatch scheme="blues" /> | Gradiente de un solo tono que varía de azul claro a azul oscuro. |
| `SCHEME_REDS` | `'reds'` | Secuencial | <SchemeSwatch scheme="reds" /> | Gradiente de un solo tono que varía de rojo claro a rojo intenso. |
| `SCHEME_GREENS` | `'greens'` | Secuencial | <SchemeSwatch scheme="greens" /> | Gradiente de un solo tono que varía de verde claro a verde bosque. |
| `SCHEME_PURPLES` | `'purples'` | Secuencial | <SchemeSwatch scheme="purples" /> | Gradiente de un solo tono que varía de lavanda a morado intenso. |
| `SCHEME_ORANGES` | `'oranges'` | Secuencial | <SchemeSwatch scheme="oranges" /> | Gradiente de un solo tono que varía de melocotón a naranja oscuro. |
| `SCHEME_VIRIDIS` | `'viridis'` | Perceptual | <SchemeSwatch scheme="viridis" /> | Gradiente uniforme de múltiples tonos (morado a verde azulado y amarillo). |
| `SCHEME_INFERNO` | `'inferno'` | Perceptual | <SchemeSwatch scheme="inferno" /> | Gradiente térmico de alto contraste (negro a rojo y amarillo). |
| `SCHEME_MAGMA` | `'magma'` | Perceptual | <SchemeSwatch scheme="magma" /> | Gradiente térmico de múltiples tonos (violeta oscuro a rosa y blanco). |
| `SCHEME_CATEGORY10` | `'category10'` | Discreto | <SchemeSwatch scheme="category10" /> | Diez tonos categóricos distintivos para dimensiones nominales. |
| `SCHEME_TABLEAU10` | `'tableau10'` | Discreto | <SchemeSwatch scheme="tableau10" /> | Diez tonos equilibrados para líneas multiserie, sectores y grupos. |

---

## Puente de especificación de bajo nivel

### Graph.render()

Renderiza un payload con una especificación arbitraria de Vega-Lite v6 directamente en el panel de visualización de la consola.

```elscriptsignature
Graph.render(
  array|object|string:payload,
  string:title = 'Graph'
): void
```

#### Parámetros

* **`payload`** (`array|object|string`, _requerido_): Un array asociativo, objeto literal o cadena JSON válida que contiene la especificación completa de Vega-Lite.
* **`title`** (`string`, _opcional_): Etiqueta de cabecera para la pestaña de salida. Por defecto es `'Graph'`. Si se omite, prevalece el título declarado dentro de la especificación (`payload.title` o `payload.description`).

#### Normalización y comportamiento de errores

1. **Entrada en cadena (string)**: Se procesa mediante parsing con `json_decode()`. Lanza `\InvalidArgumentException` si la cadena está vacía o contiene una sintaxis JSON malformada.
2. **Entrada en objeto**: Se convierte recursivamente en un array asociativo para aislar el motor de referencias inesperadas del host.
3. **Entrada en array**: Se valida para garantizar que el payload raíz sea un mapa asociativo y no una lista secuencial indexada.
4. **Inyección por defecto**: Si se omiten, `$schema` recibe `DEFAULT_SCHEMA` y `width` recibe `DEFAULT_WIDTH`.

#### Ejemplo

```elscript
Graph.render({
  '$schema': 'https://vega.github.io/schema/vega-lite/v6.json',
  title: 'Almacenamiento por tipo de recurso',
  data: {
    values: [
      { asset: 'Imágenes', megabytes: 340 },
      { asset: 'Vídeos', megabytes: 820 },
      { asset: 'Documentos', megabytes: 110 }
    ]
  },
  mark: { type: 'bar', color: Graph.COLOR_BLUE },
  encoding: {
    x: { field: 'asset', type: 'nominal', axis: { title: 'Categoría de recurso' } },
    y: { field: 'megabytes', type: 'quantitative', axis: { title: 'Tamaño (MB)' } }
  }
}, 'Desglose de almacenamiento')
```

<VegaLite spec={{
  "$schema": "https://vega.github.io/schema/vega-lite/v6.json",
  "title": "Almacenamiento por tipo de recurso",
  "width": "container",
  "height": 260,
  "data": {
    "values": [
      { "asset": "Imágenes", "megabytes": 340 },
      { "asset": "Vídeos", "megabytes": 820 },
      { "asset": "Documentos", "megabytes": 110 }
    ]
  },
  "mark": { "type": "bar", "color": "#3858e9", "cornerRadiusEnd": 4, "tooltip": true },
  "encoding": {
    "x": { "field": "asset", "type": "nominal", "axis": { "title": "Categoría de recurso" } },
    "y": { "field": "megabytes", "type": "quantitative", "axis": { "title": "Tamaño (MB)" } }
  }
}} />

---

## Constructores de gráficos de alto nivel

### Graph.bars()

Compila y renderiza un gráfico de barras a partir de colecciones tabulares o diccionarios asociativos clave-valor.

```elscriptsignature
Graph.bars(
  mixed:data,
  array:options = []
): void
```

#### Parámetros

* **`data`** (`mixed`, _requerido_): Array de registros asociativos (como `[{ role: 'Editor', count: 12 }]`) o un diccionario clave-valor (como `{ Borrador: 14, Publicado: 89 }`).
* **`options`** (`array`, _opcional_): Ajustes de configuración.

#### Diccionario de opciones

| Clave | Tipo | Por defecto | Descripción |
| :--- | :--- | :--- | :--- |
| `title` | `string` | `'Bar Chart'` | Título de la visualización y etiqueta de la pestaña. |
| `x` | `string` | _Autodetectado_ | Campo asignado a las coordenadas horizontales. |
| `y` | `string` | _Autodetectado_ | Campo asignado a las coordenadas verticales. |
| `color` | `string` | `null` | Campo del registro utilizado para particionar colores por categoría. |
| `color_value` | `string` | `Graph.COLOR_BLUE` | Color hexadecimal fijo aplicado a todas las barras cuando se omite `color`. |
| `horizontal` | `bool` | `false` | Cuando es `true`, invierte los ejes para renderizar barras horizontales. |
| `sort` | `string\|array` | `null` | Definición de orden personalizado para la dimensión categórica. |
| `label_angle` | `int` | `-45` | Ángulo de rotación en grados para las etiquetas del eje. |
| `height` | `int` | `360` | Altura en píxeles del área de visualización. |

#### Ejemplos

##### Desglose vertical por categorías

```elscript
Graph.bars([
  { role: 'Administrador', users: 4 },
  { role: 'Editor', users: 12 },
  { role: 'Autor', users: 28 },
  { role: 'Colaborador', users: 45 },
  { role: 'Suscriptor', users: 190 }
], {
  title: 'Usuarios activos por rol',
  color_value: Graph.COLOR_PURPLE
})
```

<VegaLite spec={{
  "$schema": "https://vega.github.io/schema/vega-lite/v6.json",
  "title": "Usuarios activos por rol",
  "width": "container",
  "height": 260,
  "data": {
    "values": [
      { "role": "Administrador", "users": 4 },
      { "role": "Editor", "users": 12 },
      { "role": "Autor", "users": 28 },
      { "role": "Colaborador", "users": 45 },
      { "role": "Suscriptor", "users": 190 }
    ]
  },
  "mark": { "type": "bar", "cornerRadiusEnd": 4, "tooltip": true, "color": "#8e24aa" },
  "encoding": {
    "x": { "field": "role", "type": "nominal", "axis": { "title": "Rol", "labelAngle": -45 } },
    "y": { "field": "users", "type": "quantitative", "axis": { "title": "Usuarios" } }
  }
}} />

##### Disposición horizontal desde un mapa clave-valor

```elscript
Graph.bars({
  'Borrador': 14,
  'Pendiente': 6,
  'Privado': 3,
  'Publicado': 89
}, {
  title: 'Inventario de entradas por estado',
  horizontal: true,
  color_value: Graph.COLOR_BLUE
})
```

<VegaLite spec={{
  "$schema": "https://vega.github.io/schema/vega-lite/v6.json",
  "title": "Inventario de entradas por estado",
  "width": "container",
  "height": 220,
  "data": {
    "values": [
      { "category": "Borrador", "value": 14 },
      { "category": "Pendiente", "value": 6 },
      { "category": "Privado", "value": 3 },
      { "category": "Publicado", "value": 89 }
    ]
  },
  "mark": { "type": "bar", "cornerRadiusEnd": 4, "tooltip": true, "color": "#3858e9" },
  "encoding": {
    "x": { "field": "value", "type": "quantitative", "axis": { "title": "Cantidad" } },
    "y": { "field": "category", "type": "nominal", "axis": { "title": "Estado" } }
  }
}} />

---

### Graph.lines()

Renderiza líneas continuas de tendencia y comparaciones temporales multiserie.

```elscriptsignature
Graph.lines(
  mixed:data,
  array:options = []
): void
```

#### Diccionario de opciones

| Clave | Tipo | Por defecto | Descripción |
| :--- | :--- | :--- | :--- |
| `title` | `string` | `'Line Chart'` | Título de la visualización. |
| `x` | `string` | _Autodetectado_ | Campo asignado a las coordenadas horizontales. |
| `y` | `string` | _Autodetectado_ | Campo asignado a las coordenadas verticales. |
| `color` | `string` | `null` | Campo utilizado para la agrupación por color multiserie. |
| `color_value` | `string` | `Graph.COLOR_BLUE` | Color hexadecimal fijo para series individuales. |
| `points` | `bool` | `true` | Renderiza marcadores circulares discretos en los vértices a lo largo de las líneas. |
| `sort` | `string\|array` | `null` | Especificación de ordenación para el eje de coordenadas horizontales. |
| `label_angle` | `int` | `-45` | Ángulo de rotación en grados para las etiquetas del eje. |
| `height` | `int` | `360` | Altura en píxeles del área de visualización. |

#### Ejemplo

```elscript
Graph.lines([
  { month: '2026-01', volume: 1420 },
  { month: '2026-02', volume: 1890 },
  { month: '2026-03', volume: 2400 },
  { month: '2026-04', volume: 2180 },
  { month: '2026-05', volume: 3050 },
  { month: '2026-06', volume: 3820 }
], {
  title: 'Rendimiento mensual de la red',
  color_value: Graph.COLOR_GREEN
})
```

<VegaLite spec={{
  "$schema": "https://vega.github.io/schema/vega-lite/v6.json",
  "title": "Rendimiento mensual de la red",
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
    "x": { "field": "month", "type": "nominal", "axis": { "title": "Mes", "labelAngle": -45 } },
    "y": { "field": "volume", "type": "quantitative", "axis": { "title": "Volumen (GB)" } },
    "color": { "value": "#43a047" }
  }
}} />

---

### Graph.pie()

Renderiza sectores de arco proporcionales para análisis de composición, con opción de orificio central para gráficos de dona.

```elscriptsignature
Graph.pie(
  mixed:data,
  array:options = []
): void
```

#### Diccionario de opciones

| Clave | Tipo | Por defecto | Descripción |
| :--- | :--- | :--- | :--- |
| `title` | `string` | `'Pie Chart'` / `'Donut Chart'` | Título de la visualización. |
| `category` | `string` | _Autodetectado_ | Campo que contiene las etiquetas categóricas de los sectores. |
| `value` | `string` | _Autodetectado_ | Campo que contiene las magnitudes numéricas de los sectores. |
| `donut` | `bool` | `false` | Renderiza un orificio central para gráficos de dona cuando es `true`. |
| `inner_radius` | `int` | `65` | Radio en píxeles del orificio central cuando `donut` está activo. |
| `scheme` | `string` | `Graph.SCHEME_TABLEAU10` | Paleta de colores de Vega-Lite para los sectores categóricos. |
| `height` | `int` | `360` | Altura en píxeles del área de visualización. |

#### Ejemplo

```elscript
Graph.pie([
  { device: 'Móvil', share: 58 },
  { device: 'Escritorio', share: 34 },
  { device: 'Tablet', share: 8 }
], {
  title: 'Distribución de tráfico por tipo de dispositivo',
  donut: true,
  inner_radius: 75
})
```

<VegaLite spec={{
  "$schema": "https://vega.github.io/schema/vega-lite/v6.json",
  "title": "Distribución de tráfico por tipo de dispositivo",
  "width": "container",
  "height": 280,
  "data": {
    "values": [
      { "device": "Móvil", "share": 58 },
      { "device": "Escritorio", "share": 34 },
      { "device": "Tablet", "share": 8 }
    ]
  },
  "mark": { "type": "arc", "innerRadius": 75, "tooltip": true },
  "encoding": {
    "theta": { "field": "share", "type": "quantitative", "stack": true },
    "color": { "field": "device", "type": "nominal", "scale": { "scheme": "tableau10" }, "legend": { "title": "Dispositivo" } }
  }
}} />

---

### Graph.worldmap()

Renderiza un mapa coroplético mundial de dos capas sin conexión proyectado sobre una [proyección Equal Earth](https://es.wikipedia.org/wiki/Proyecci%C3%B3n_Equal_Earth).

```elscriptsignature
Graph.worldmap(
  mixed:data,
  array:options = []
): void
```

#### Resolución automática de códigos de país

El método normaliza identificadores de países a identificadores numéricos de geometría TopoJSON estándar (`id`):
* **ISO 3166-1 Alfa-2**: `'US'`, `'ES'`, `'DE'`, `'FR'`, `'JP'`. La salida de [IPLookup.to_country()](./ip-lookup.md#iplookupto_country) enlaza directamente con este método.
* **ISO 3166-1 Alfa-3**: `'USA'`, `'ESP'`, `'DEU'`, `'FRA'`, `'JPN'`.
* **Nombres estándar de países**: `'United States'`, `'Germany'`, `'Spain'`.
* **Identificadores numéricos TopoJSON**: `'840'`, `'724'`, `'276'`.

#### Diccionario de opciones

| Clave | Tipo | Por defecto | Descripción |
| :--- | :--- | :--- | :--- |
| `title` | `string` | `'World Map'` | Título de la visualización. |
| `key` | `string` | _Autodetectado_ | Campo que contiene los códigos o nombres de país. |
| `value` | `string` | _Autodetectado_ | Campo que contiene los valores cuantitativos de calor. |
| `label` | `string` | `null` | Campo de metadatos opcional incluido en los tooltips al pasar el cursor. |
| `scheme` | `string` | `Graph.SCHEME_BLUES` | Esquema de color de gradiente secuencial. |
| `projection` | `string` | `'equalEarth'` | Algoritmo de proyección cartográfica. |
| `legend_title` | `string` | _Nombre del campo_ | Título personalizado para la leyenda de la escala de color continua. |
| `height` | `int` | `480` | Altura en píxeles del viewport del mapa. |

#### Ejemplo

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
  title: 'Ingesta global de peticiones por origen',
  scheme: Graph.SCHEME_BLUES
})
```

<VegaLite spec={{
  "$schema": "https://vega.github.io/schema/vega-lite/v6.json",
  "title": "Ingesta global de peticiones por origen",
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
                { "id": "840", "country_name": "Estados Unidos", "iso": "US", "requests": 1940 },
                { "id": "724", "country_name": "España", "iso": "ES", "requests": 820 },
                { "id": "276", "country_name": "Alemania", "iso": "DE", "requests": 1150 },
                { "id": "076", "country_name": "Brasil", "iso": "BR", "requests": 670 },
                { "id": "392", "country_name": "Japón", "iso": "JP", "requests": 930 },
                { "id": "036", "country_name": "Australia", "iso": "AU", "requests": 450 },
                { "id": "124", "country_name": "Canadá", "iso": "CA", "requests": 610 },
                { "id": "250", "country_name": "Francia", "iso": "FR", "requests": 780 }
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
          "legend": { "title": "Peticiones", "orient": "bottom-left", "gradientLength": 160 }
        },
        "tooltip": [
          { "field": "country_name", "type": "nominal", "title": "País" },
          { "field": "iso", "type": "nominal", "title": "Código" },
          { "field": "requests", "type": "quantitative", "title": "Peticiones" }
        ]
      }
    }
  ]
}} />

---

### Graph.usa()

Renderiza un mapa coroplético de dos capas sin conexión de los Estados Unidos utilizando una [proyección Albers USA](https://es.wikipedia.org/wiki/Proyecci%C3%B3n_de_Albers).

```elscriptsignature
Graph.usa(
  mixed:data,
  array:options = []
): void
```

#### Resolución de códigos de estado

Normaliza referencias de estados a cadenas FIPS numéricas de 2 dígitos:
* **Códigos postales**: `'CA'`, `'TX'`, `'NY'`, `'FL'`, `'WA'`.
* **Nombres de estados**: `'California'`, `'Texas'`, `'New York'`.
* **Identificadores FIPS**: `'06'`, `'48'`, `'36'`, `6`, `48`.

#### Diccionario de opciones

| Clave | Tipo | Por defecto | Descripción |
| :--- | :--- | :--- | :--- |
| `title` | `string` | `'US State Map'` | Título de la visualización. |
| `key` | `string` | _Autodetectado_ | Campo que contiene códigos postales, nombres o identificadores FIPS de estados. |
| `value` | `string` | _Autodetectado_ | Campo que contiene los valores métricos. |
| `label` | `string` | `null` | Dimensión adicional opcional mostrada en los tooltips. |
| `scheme` | `string` | `Graph.SCHEME_BLUES` | Esquema de color secuencial. |
| `projection` | `string` | `'albersUsa'` | Algoritmo de proyección cartográfica. |
| `legend_title` | `string` | _Nombre del campo_ | Título personalizado de la leyenda. |
| `height` | `int` | `450` | Altura en píxeles del viewport. |

#### Ejemplo

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
  title: 'Densidad de tráfico en Estados Unidos',
  scheme: Graph.SCHEME_PURPLES
})
```

<VegaLite spec={{
  "$schema": "https://vega.github.io/schema/vega-lite/v6.json",
  "title": "Densidad de tráfico en Estados Unidos",
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
          "legend": { "title": "Tráfico", "orient": "bottom-left", "gradientLength": 160 }
        },
        "tooltip": [
          { "field": "state_name", "type": "nominal", "title": "Estado" },
          { "field": "state_code", "type": "nominal", "title": "Código" },
          { "field": "traffic", "type": "quantitative", "title": "Tráfico" }
        ]
      }
    }
  ]
}} />

---

### Graph.boxplot()

Renderiza gráficos de distribución boxplot mostrando medianas, rangos intercuartílicos (Q1–Q3) y límites mín-máx a través de dimensiones categóricas.

```elscriptsignature
Graph.boxplot(
  mixed:data,
  array:options = []
): void
```

#### Diccionario de opciones

| Clave | Tipo | Por defecto | Descripción |
| :--- | :--- | :--- | :--- |
| `title` | `string` | `'Boxplot'` | Título de la visualización. |
| `x` | `string` | _Autodetectado_ | Dimensión categórica asignada al eje horizontal. |
| `y` | `string` | _Autodetectado_ | Métrica cuantitativa asignada al eje vertical. |
| `color` | `string` | `null` | Dimensión utilizada para codificar colores por subgrupo. |
| `color_value` | `string` | `Graph.COLOR_BLUE` | Color de relleno para cajas en series individuales. |
| `label_angle` | `int` | `-45` | Ángulo de rotación en grados para las etiquetas del eje. |
| `height` | `int` | `360` | Altura en píxeles del área de visualización. |

#### Ejemplo

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
  title: 'Distribución de latencias por endpoint',
  color_value: Graph.COLOR_BLUE
})
```

<VegaLite spec={{
  "$schema": "https://vega.github.io/schema/vega-lite/v6.json",
  "title": "Distribución de latencias por endpoint",
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
    "y": { "field": "latency_ms", "type": "quantitative", "axis": { "title": "Latencia (ms)" } }
  }
}} />

---

### Graph.scatter()

Renderiza gráficos de dispersión y de burbujas para el análisis de correlación multidimensional entre variables cuantitativas.

```elscriptsignature
Graph.scatter(
  mixed:data,
  array:options = []
): void
```

#### Diccionario de opciones

| Clave | Tipo | Por defecto | Descripción |
| :--- | :--- | :--- | :--- |
| `title` | `string` | `'Scatter Plot'` | Título de la visualización. |
| `x` | `string` | _Autodetectado_ | Campo cuantitativo para coordenadas horizontales. |
| `y` | `string` | _Autodetectado_ | Campo cuantitativo para coordenadas verticales. |
| `color` | `string` | `null` | Campo para agrupación de color discreta o código de color hexadecimal fijo. |
| `size` | `string\|int\|float` | `null` | Campo mapeado al área del punto o tamaño fijo del marcador en píxeles cuadrados. |
| `opacity` | `float` | `0.7` | Opacidad de relleno del marcador entre `0.0` y `1.0`. |
| `zero` | `bool` | `false` | Cuando es `false`, escala los ejes a la extensión de los datos en lugar de forzar una línea base en cero. |
| `label_angle` | `int` | `-45` | Ángulo de rotación en grados para las etiquetas del eje. |
| `height` | `int` | `360` | Altura en píxeles del área de visualización. |

#### Ejemplo

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
  title: 'Consultas de base de datos frente a duración de ejecución',
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
  "title": "Consultas de base de datos frente a duración de ejecución",
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
    "x": { "field": "queries", "type": "quantitative", "scale": { "zero": false }, "axis": { "title": "Consultas" } },
    "y": { "field": "duration_ms", "type": "quantitative", "scale": { "zero": false }, "axis": { "title": "Duración (ms)" } },
    "color": { "field": "status", "type": "nominal", "legend": { "title": "Estado" } },
    "size": { "field": "rows", "type": "quantitative", "legend": { "title": "Filas examinadas" } }
  }
}} />

---

### Graph.heatmap()

Renderiza un mapa de calor en cuadrícula bidimensional mapeando coordenadas categóricas u ordinales a valores de intensidad de color.

```elscriptsignature
Graph.heatmap(
  mixed:data,
  array:options = []
): void
```

#### Diccionario de opciones

| Clave | Tipo | Por defecto | Descripción |
| :--- | :--- | :--- | :--- |
| `title` | `string` | `'Heatmap'` | Título de la visualización. |
| `x` | `string` | _Autodetectado_ | Campo asignado a las celdas de columnas horizontales. |
| `y` | `string` | _Autodetectado_ | Campo asignado a las celdas de filas verticales. |
| `value` | `string` | _Autodetectado_ | Métrica asignada a la intensidad del color de la celda. |
| `scheme` | `string` | `Graph.SCHEME_BLUES` | Paleta de colores secuencial para el mapa de calor. |
| `stroke` | `string` | `'#ffffff'` <ColorSwatch color="#ffffff" /> | Color de la línea de separación de la cuadrícula. |
| `legend_title` | `string` | _Nombre del campo_ | Título personalizado de la leyenda. |
| `label_angle` | `int` | `-45` | Ángulo de rotación en grados para las etiquetas del eje. |
| `height` | `int` | `360` | Altura en píxeles del área de visualización. |

#### Ejemplo

```elscript
Graph.heatmap([
    { 'day': 'Lun', 'hour': 9,  'events': 50 },
    { 'day': 'Lun', 'hour': 10, 'events': 320 },
    { 'day': 'Lun', 'hour': 11, 'events': 25 },
    { 'day': 'Lun', 'hour': 12, 'events': 250 },
    { 'day': 'Lun', 'hour': 13, 'events': 195 },
    { 'day': 'Lun', 'hour': 14, 'events': 331 },
    { 'day': 'Lun', 'hour': 15, 'events': 80 },
    { 'day': 'Mar', 'hour': 9,  'events': 190 },
    { 'day': 'Mar', 'hour': 10, 'events': 78 },
    { 'day': 'Mar', 'hour': 11, 'events': 22 },
    { 'day': 'Mar', 'hour': 12, 'events': 25 },
    { 'day': 'Mar', 'hour': 13, 'events': 250 },
    { 'day': 'Mar', 'hour': 14, 'events': 410 },
    { 'day': 'Mar', 'hour': 15, 'events': 295 },
    { 'day': 'Mié', 'hour': 9,  'events': 190 },
    { 'day': 'Mié', 'hour': 10, 'events': 46 },
    { 'day': 'Mié', 'hour': 11, 'events': 101 },
    { 'day': 'Mié', 'hour': 12, 'events': 610 },
    { 'day': 'Mié', 'hour': 13, 'events': 710 },
    { 'day': 'Mié', 'hour': 14, 'events': 155 },
    { 'day': 'Mié', 'hour': 15, 'events': 25 }
], {
    'title': 'Eventos del sistema por día y hora',
    'x': 'hour',
    'y': 'day',
    'value': 'events',
    'height': 180,
    'scheme': Graph.SCHEME_PURPLES
})
```

<VegaLite spec={{
  "$schema": "https://vega.github.io/schema/vega-lite/v6.json",
  "title": "Eventos del sistema por día y hora",
  "width": "container",
  "height": 180,
  "data": {
    "values": [
      { "day": "Lun", "hour": 9,  "events": 50 },
      { "day": "Lun", "hour": 10, "events": 320 },
      { "day": "Lun", "hour": 11, "events": 25 },
      { "day": "Lun", "hour": 12, "events": 250 },
      { "day": "Lun", "hour": 13, "events": 195 },
      { "day": "Lun", "hour": 14, "events": 331 },
      { "day": "Lun", "hour": 15, "events": 80 },
      { "day": "Mar", "hour": 9,  "events": 190 },
      { "day": "Mar", "hour": 10, "events": 78 },
      { "day": "Mar", "hour": 11, "events": 22 },
      { "day": "Mar", "hour": 12, "events": 25 },
      { "day": "Mar", "hour": 13, "events": 250 },
      { "day": "Mar", "hour": 14, "events": 410 },
      { "day": "Mar", "hour": 15, "events": 295 },
      { "day": "Mié", "hour": 9,  "events": 190 },
      { "day": "Mié", "hour": 10, "events": 46 },
      { "day": "Mié", "hour": 11, "events": 101 },
      { "day": "Mié", "hour": 12, "events": 610 },
      { "day": "Mié", "hour": 13, "events": 710 },
      { "day": "Mié", "hour": 14, "events": 155 },
      { "day": "Mié", "hour": 15, "events": 25 }
    ]
  },
  "mark": { "type": "rect", "stroke": "#ffffff", "strokeWidth": 1 },
  "encoding": {
    "x": { "field": "hour", "type": "ordinal", "axis": { "title": "Hora", "labelAngle": 0 } },
    "y": { "field": "day", "type": "nominal", "axis": { "title": "Día" } },
    "color": { "field": "events", "type": "quantitative", "scale": { "scheme": "purples" }, "legend": { "title": "Eventos" } },
    "tooltip": [
      { "field": "day", "type": "nominal", "title": "Día" },
      { "field": "hour", "type": "ordinal", "title": "Hora" },
      { "field": "events", "type": "quantitative", "title": "Eventos" }
    ]
  }
}} />

---

### Graph.histogram()

Particiona mediciones cuantitativas continuas en intervalos (bins) discretos y calcula recuentos de distribución.

```elscriptsignature
Graph.histogram(
  mixed:data,
  array:options = []
): void
```

#### Modos de entrada

* **Array escalar numérico**: Lista de números enteros o de coma flotante directos (como tiempos de ejecución o latencias de consultas).
* **Registros tabulares**: Objetos que contienen una columna numérica objetivo como métrica.

#### Diccionario de opciones

| Clave | Tipo | Por defecto | Descripción |
| :--- | :--- | :--- | :--- |
| `title` | `string` | `'Histogram'` | Título de la visualización. |
| `field` | `string` | _Autodetectado_ | Campo numérico para particionar en intervalos (bins). |
| `bins` | `int` | `20` | Cantidad máxima objetivo de intervalos (bins). |
| `maxbins` | `int` | `null` | Límite superior para el recuento de intervalos (bins) generados. |
| `step` | `float` | `null` | Ancho de intervalo fijo para los pasos de los intervalos (bins). |
| `color` | `string` | `Graph.COLOR_BLUE` | Partición de color categórica o código de color fijo. |
| `label_angle` | `int` | `-45` | Ángulo de rotación en grados para las etiquetas del eje. |
| `height` | `int` | `360` | Altura en píxeles del área de visualización. |

#### Ejemplo

```elscript
Graph.histogram([
  12.4, 15.1, 18.3, 19.0, 21.2, 22.8, 23.1, 28.5, 34.2, 45.0,
  18.1, 21.9, 23.4, 25.0, 27.2, 31.8, 35.1, 38.5, 42.2, 49.0
], {
  title: 'Distribución de duración de consultas (ms)',
  bins: 8,
  color: Graph.COLOR_CYAN
})
```

<VegaLite spec={{
  "$schema": "https://vega.github.io/schema/vega-lite/v6.json",
  "title": "Distribución de duración de consultas (ms)",
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
    "x": { "field": "value", "type": "quantitative", "bin": { "maxbins": 8 }, "axis": { "title": "Duración (ms)" } },
    "y": { "aggregate": "count", "type": "quantitative", "axis": { "title": "Recuento" } },
    "tooltip": [
      { "field": "value", "bin": true, "type": "quantitative", "title": "Rango de duración" },
      { "aggregate": "count", "type": "quantitative", "title": "Recuento" }
    ]
  }
}} />

---

### Graph.area()

Renderiza gráficos de áreas rellenas para el seguimiento de volumen acumulado, métricas de infraestructura apiladas multiserie, streamgraphs y proporciones normalizadas al 100%.

```elscriptsignature
Graph.area(
  mixed:data,
  array:options = []
): void
```

#### Diccionario de opciones

| Clave | Tipo | Por defecto | Descripción |
| :--- | :--- | :--- | :--- |
| `title` | `string` | `'Area Chart'` | Título de la visualización. |
| `x` | `string` | _Autodetectado_ | Campo asignado a las coordenadas horizontales. |
| `y` | `string` | _Autodetectado_ | Campo asignado a las coordenadas verticales. |
| `color` | `string` | `null` | Campo utilizado para la agrupación por color multiserie. |
| `color_value` | `string` | `Graph.COLOR_BLUE` | Color hexadecimal fijo para series individuales. |
| `stream` | `bool` | `false` | Centra el apilado vertical en torno a la línea base media para formar un streamgraph. |
| `normalize` | `bool` | `false` | Escala el apilado a proporciones relativas al 100%. |
| `curve` | `string` | `'monotone'` | Modo de interpolación de ruta (`'monotone'`, `'basis'`, `'linear'`). |
| `opacity` | `float` | `0.6` | Opacidad de relleno entre `0.0` y `1.0`. |
| `label_angle` | `int` | `-45` | Ángulo de rotación en grados para las etiquetas del eje. |
| `height` | `int` | `360` | Altura en píxeles del área de visualización. |

#### Ejemplo

```elscript
Graph.area([
  { date: '2026-09-01', traffic_gb: 320, tier: 'Caché CDN' },
  { date: '2026-09-01', traffic_gb: 180, tier: 'Servidor de aplicaciones' },
  { date: '2026-09-02', traffic_gb: 410, tier: 'Caché CDN' },
  { date: '2026-09-02', traffic_gb: 240, tier: 'Servidor de aplicaciones' },
  { date: '2026-09-03', traffic_gb: 490, tier: 'Caché CDN' },
  { date: '2026-09-03', traffic_gb: 290, tier: 'Servidor de aplicaciones' },
  { date: '2026-09-04', traffic_gb: 380, tier: 'Caché CDN' },
  { date: '2026-09-04', traffic_gb: 220, tier: 'Servidor de aplicaciones' }
], {
  title: 'Volumen de salida de red por capa',
  x: 'date',
  y: 'traffic_gb',
  color: 'tier'
})
```

<VegaLite spec={{
  "$schema": "https://vega.github.io/schema/vega-lite/v6.json",
  "title": "Volumen de salida de red por capa",
  "width": "container",
  "height": 280,
  "data": {
    "values": [
      { "date": "2026-09-01", "traffic_gb": 320, "tier": "Caché CDN" },
      { "date": "2026-09-01", "traffic_gb": 180, "tier": "Servidor de aplicaciones" },
      { "date": "2026-09-02", "traffic_gb": 410, "tier": "Caché CDN" },
      { "date": "2026-09-02", "traffic_gb": 240, "tier": "Servidor de aplicaciones" },
      { "date": "2026-09-03", "traffic_gb": 490, "tier": "Caché CDN" },
      { "date": "2026-09-03", "traffic_gb": 290, "tier": "Servidor de aplicaciones" },
      { "date": "2026-09-04", "traffic_gb": 380, "tier": "Caché CDN" },
      { "date": "2026-09-04", "traffic_gb": 220, "tier": "Servidor de aplicaciones" }
    ]
  },
  "mark": { "type": "area", "line": true, "interpolate": "monotone", "opacity": 0.6, "tooltip": true },
  "encoding": {
    "x": { "field": "date", "type": "nominal", "axis": { "title": "Fecha", "labelAngle": -45 } },
    "y": { "field": "traffic_gb", "type": "quantitative", "axis": { "title": "Tráfico (GB)" } },
    "color": { "field": "tier", "type": "nominal", "legend": { "title": "Capa" } }
  }
}} />

---

### Graph.timeline()

Renderiza barras de intervalo horizontales de línea de tiempo estilo Gantt para perfiles de ejecución, rastreo de peticiones y eventos programados.

```elscriptsignature
Graph.timeline(
  mixed:data,
  array:options = []
): void
```

#### Diccionario de opciones

| Clave | Tipo | Por defecto | Descripción |
| :--- | :--- | :--- | :--- |
| `title` | `string` | `'Timeline'` | Título de la visualización. |
| `task` | `string` | _Autodetectado_ | Campo que contiene el nombre de la tarea o identificador de carril (eje vertical). |
| `start` | `string` | _Autodetectado_ | Campo que contiene la marca de tiempo de inicio o el desplazamiento numérico en milisegundos. |
| `end` | `string` | _Autodetectado_ | Campo que contiene la marca de tiempo de finalización o el desplazamiento de término. |
| `color` | `string` | `Graph.COLOR_BLUE` | Campo para codificación de color por categoría, o código de color fijo. |
| `label_angle` | `int` | `-45` | Ángulo de rotación en grados para las etiquetas del eje. |
| `height` | `int` | _Autocalculado_ | Altura en píxeles del área de visualización. |

#### Ejemplo

```elscript
Graph.timeline([
  { task: 'Autenticación y sesión', start: 0,   end: 35 },
  { task: 'Pipeline de consultas a la base de datos',  start: 25,  end: 95 },
  { task: 'Búsqueda en caché de objetos',      start: 40,  end: 75 },
  { task: 'Renderizado de plantilla',       start: 90,  end: 180 },
  { task: 'Vaciado del buffer de salida',      start: 175, end: 210 }
], {
  title: 'Desglose de ejecución del ciclo de vida de la petición',
  color: Graph.COLOR_BLUE
})
```

<VegaLite spec={{
  "$schema": "https://vega.github.io/schema/vega-lite/v6.json",
  "title": "Desglose de ejecución del ciclo de vida de la petición",
  "width": "container",
  "height": 220,
  "data": {
    "values": [
      { "task": "Autenticación y sesión", "start": 0,   "end": 35 },
      { "task": "Pipeline de consultas a la base de datos",  "start": 25,  "end": 95 },
      { "task": "Búsqueda en caché de objetos",      "start": 40,  "end": 75 },
      { "task": "Renderizado de plantilla",       "start": 90,  "end": 180 },
      { "task": "Vaciado del buffer de salida",      "start": 175, "end": 210 }
    ]
  },
  "mark": { "type": "bar", "cornerRadius": 4, "height": 18, "color": "#3858e9", "tooltip": true },
  "encoding": {
    "y": { "field": "task", "type": "nominal", "axis": { "title": "Etapa del ciclo de vida" } },
    "x": { "field": "start", "type": "quantitative", "axis": { "title": "Desplazamiento (ms)" } },
    "x2": { "field": "end" }
  }
}} />
