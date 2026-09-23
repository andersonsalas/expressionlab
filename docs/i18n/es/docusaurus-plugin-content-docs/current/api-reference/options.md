---
id: options
title: Opciones
sidebar_position: 2
---

# Opciones

Inspecciona, actualiza y analiza opciones de WordPress con deserialización controlada, comprobación de compatibilidad de formato y gráficos de distribución de *autoload*.

El servicio `Options` proporciona utilidades de diagnóstico e inspección para las opciones de WordPress:

* **Deserialización controlada**: Restringe la deserialización a arrays, escalares e instancias de `stdClass` (`allowed_classes => false`), previniendo la instanciación de clases arbitrarias.
* **Comprobación de compatibilidad de formato**: Detecta el formato existente (serialización PHP frente a JSON) durante las actualizaciones para evitar conversiones accidentales.
* **Invalidación de caché**: Limpia tanto la caché de claves individuales como el grupo global `alloptions` al modificar opciones.
* **Análisis de distribución de autoload**: Genera gráficos en Vega-Lite y tablas de desglose para las opciones configuradas con carga automática.
* **Compatibilidad con Multisite**: Permite gestionar opciones a nivel de red mediante `NetworkOptions`.

---

## Lectura de opciones

### Options.get()

Recupera el valor de una opción y decodifica cadenas JSON o deserializa datos estructurados.

```elscriptsignature
Options.get(
  string:key,
  mixed:default = null
): mixed
```

#### Comportamiento y detección de tipos

1. **Datos serializados**: Si la cadena de la opción está serializada (por ejemplo, arrays u objetos), se procesa mediante un procedimiento riguroso de deserialización. Solo se permiten objetos `stdClass` y tipos escalares/arrays estándar. Si se detecta un objeto de una clase no autorizada, se lanza una excepción.
2. **Datos JSON**: Si la cadena de la opción es un JSON válido (por ejemplo, `{"enabled": true}` o `[1, 2, 3]`), se analiza y convierte en un arreglo asociativo o en un objeto.
3. **Valores escalares**: Cadenas, enteros, booleanos y nulos se devuelven sin alteración.
4. **Claves inexistentes**: Devuelve `default` (o `null` si se omite).

#### Ejemplo

```elscript
/* Recuperar arreglo de configuración de plugin o valor predeterminado */
Options.get('my_plugin_settings', { enabled: false, timeout: 30 })
```

```elscript
/* Acceso a propiedades anidadas */
Options.get('active_plugins')
```

---

### Options.get_raw()

Recupera la cadena exacta y sin modificar almacenada en la columna `option_value` de la tabla `wp_options` sin ejecutar ninguna deserialización ni decodificación JSON.

```elscriptsignature
Options.get_raw(
  string:key,
  mixed:default = null
): ?string
```

#### Ejemplo

```elscript
/* Inspeccionar contenido sin procesar en la base de datos para detectar serialización corrupta */
Options.get_raw('cron')
```

:::tip Casos de uso para `get_raw()`
La función `get_raw()` se utiliza al realizar auditorías forenses en opciones corruptas, comprobar la longitud de bytes de cadenas sin procesar o analizar bloques serializados sin deserializar.
:::

---

## Escritura y actualización de opciones

:::warning Protección contra escritura habilitada por defecto
Todos los métodos de modificación (`update`, `update_raw`, `delete`) están bloqueados cuando la constante `EXPRESSION_LAB_DATABASE_READONLY` está configurada como `true` (valor predeterminado en producción). Para permitir modificaciones, define `define( 'EXPRESSION_LAB_DATABASE_READONLY', false );` en tu `wp-config.php`.
:::

### Options.update()

Actualiza una opción existente o crea una nueva, aplicando estrictas comprobaciones de seguridad de serialización previas.

```elscriptsignature
Options.update(
  string:key,
  mixed:value,
  string:serialization_type = Options.FORMAT_SERIALIZED
): bool
```

#### Constantes de formato de serialización

| Constante | Valor | Descripción |
| :--- | :--- | :--- |
| `Options.FORMAT_SERIALIZED` | `'serialized'` | Predeterminado. Codifica arreglos y escalares utilizando la serialización estándar de PHP (`serialize`). |
| `Options.FORMAT_SERIALIZED_OBJECT` | `'serialized_object'` | Convierte el arreglo raíz en un objeto `stdClass` antes de serializar. |
| `Options.FORMAT_JSON` | `'json'` | Codifica el valor como una cadena JSON mediante `wp_json_encode`. |

#### Protecciones contra conflictos de formato

Para evitar la corrupción de datos:
* Si la opción en la base de datos se encuentra serializada con PHP, `Options.update()` **rechaza** actualizarla con `Options.FORMAT_JSON`.
* Si la opción en la base de datos se encuentra en formato JSON, `Options.update()` **rechaza** actualizarla con `Options.FORMAT_SERIALIZED`.
* Para forzar el reemplazo de formato, se debe utilizar `Options.update_raw()`.

#### Ejemplos

```elscript
/* Almacenar ajustes estructurados de un plugin mediante serialización de PHP */
Options.update('my_plugin_settings', {
  api_key: 'sk_live_12345',
  debug_mode: true,
  retries: 3
})
```

```elscript
/* Almacenar ajustes formateados como JSON */
Options.update('my_app_state', { theme: 'dark', notifications: true }, Options.FORMAT_JSON)
```

---

### Options.update_raw()

Realiza una inserción o actualización directa y de bajo nivel en la tabla `wp_options`. No serializa ni codifica en JSON `$value`.

```elscriptsignature
Options.update_raw(
  string:key,
  string:value,
  string|bool|null:autoload = null
): bool
```

#### Parámetros

* **`key`** (`string`, _requerido_): El nombre de la opción.
* **`value`** (`string`, _requerido_): La carga útil en cadena de texto sin procesar a almacenar.
* **`autoload`** (`string|bool|null`, _opcional_): Controla si la opción se carga en memoria en cada solicitud de WordPress:
  * `'yes'` o `true`: La opción se autocarga en el conjunto global `alloptions`.
  * `'no'` o `false`: La opción se carga bajo demanda al ser solicitada.
  * `null`: Si la opción ya existe, conserva su valor actual de `autoload`. Si se inserta un nuevo registro, se establece de forma predeterminada en `'no'`.

#### Comportamiento de invalidación de caché

Cuando se ejecuta `update_raw()`:
1. Purga la clave de la caché de objetos de WordPress a través de `wp_cache_delete($key, 'options')`.
2. Comprueba si la clave existe en la caché global `alloptions`. Si está presente, purga `'alloptions'` para evitar datos obsoletos en solicitudes PHP posteriores.

#### Ejemplo

```elscript
/* Reparar o escribir una cadena serializada directa y establecer autoload en 'no' */
Options.update_raw('heavy_cache_entry', 'a:1:{s:4:"data";s:6:"sample";}', false)
```

---

## Eliminación de opciones

### Options.delete()

Elimina una clave de opción de la base de datos y purga todas las entradas de caché de objetos relacionadas.

```elscriptsignature
Options.delete(string:key): bool
```

#### Ejemplo

```elscript
/* Limpiar una opción obsoleta de un plugin */
Options.delete('obsolete_plugin_config')
```

---

## Análisis forense y diagnóstico de autocarga (autoload)

### Options.stats()

Ejecuta un análisis heurístico exhaustivo y optimizado en memoria de la tabla `wp_options`, calculando agrupaciones de prefijos, huellas de memoria (KB) y proporciones de autocarga.

```elscriptsignature
Options.stats(
  ?int:sample_limit = 100000,
  int:graph_limit = 20
): array
```

#### Qué genera `stats()`

Al ejecutarse en Expression Lab, `stats()` renderiza:

1. **Gráfico de Barras Interactivo (Vega-Lite)**: Principales prefijos de opciones ordenados por tamaño acumulado en kilobytes.
2. **Tabla: Distribución de Prefijos**: Desglose de métricas tabulares por grupo de opciones.
3. **Gráfico Circular (Vega-Lite)**: Proporción de datos autocargados (`yes`/`on`/`auto`) frente a no autocargados (`no`/`off`).
4. **Tabla: Distribución de Autoload**: Resumen de recuentos y tamaño total en kilobytes.

#### Desglose de métricas

| Columna de Métrica | Descripción |
| :--- | :--- |
| `prefix` | El prefijo detectado de plugin/tema o del núcleo (por ejemplo, `woocommerce_`, `elementor_`, `(Transients)`). |
| `count` | Número total de filas de opciones que coinciden con el prefijo. |
| `size_kb` | Tamaño total de todos los valores de este grupo de prefijos en kilobytes. |
| `autoload_size_kb` | Tamaño total en kilobytes de los registros cargados en **cada** solicitud de página. |
| `autoload_percentage` | Porcentaje del peso de datos del prefijo que se autocarga (`0% - 100%`). |

#### Ejemplos

```elscript
/* Realizar un escaneo de diagnóstico de toda la tabla de opciones */
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
/* Muestrear las primeras 50.000 filas con un límite de visualización de los 10 principales */
Options.stats(50000, 10)
```

---

## Opciones de red en multisite

En instalaciones de WordPress Multisite, la configuración de toda la red se almacena en la tabla `wp_sitemeta`. Expression Lab proporciona el servicio **NetworkOptions** para administrar opciones de red en toda la instalación multisite.

:::note Solo para Multisite y Alcance de Red
`NetworkOptions` funciona solo en entornos de WordPress Multisite y apunta a la red principal (`wp_sitemeta` / `site_id`). Al evaluar expresiones en subsitios individuales, debe utilizarse `Options` (o `Site.options`) para operar en las tablas de opciones específicas `wp_{blog_id}_options`.
:::

### NetworkOptions.get()

Recupera el valor de una opción de red de la tabla `wp_sitemeta`, decodificando JSON o deserializando cargas estructuradas de PHP con protecciones contra la inyección de objetos PHP (POI).

```elscriptsignature
NetworkOptions.get(
  string:key,
  mixed:default = null
): mixed
```

#### Ejemplo

```elscript
/* Leer una opción de red con deserialización estricta */
NetworkOptions.get('active_sitewide_plugins')
```

---

### NetworkOptions.get_raw()

Recupera la cadena exacta y sin modificar almacenada en la columna `meta_value` de la tabla `wp_sitemeta` sin deserialización ni decodificación JSON.

```elscriptsignature
NetworkOptions.get_raw(
  string:key,
  mixed:default = null
): ?string
```

#### Ejemplo

```elscript
/* Leer el valor sin procesar de sitemeta */
NetworkOptions.get_raw('site_admins')
```

---

### NetworkOptions.update()

Actualiza o crea de forma segura una opción de red en `wp_sitemeta`, aplicando comprobaciones estrictas de seguridad de serialización y protecciones contra conflictos de formato (evitando la conversión accidental entre datos serializados de PHP y JSON).

```elscriptsignature
NetworkOptions.update(
  string:key,
  mixed:value,
  string:serialization_type = Options.FORMAT_SERIALIZED
): bool
```

#### Ejemplo

```elscript
/* Actualizar una opción de red de forma segura */
NetworkOptions.update('network_security_policy', { max_login_attempts: 5 })
```

---

### NetworkOptions.update_raw()

Escribe una carga útil en cadena de texto sin procesar en la tabla `wp_sitemeta` sin serialización ni codificación JSON, e invalida la caché de opciones de red correspondiente.

```elscriptsignature
NetworkOptions.update_raw(
  string:key,
  string:value
): bool
```

#### Ejemplo

```elscript
/* Actualización directa sin procesar en sitemeta */
NetworkOptions.update_raw('custom_network_meta', 'raw_value')
```

---

### NetworkOptions.delete()

Elimina una opción de red de la tabla `wp_sitemeta` y purga las entradas de caché relacionadas.

```elscriptsignature
NetworkOptions.delete(
  string:key
): bool
```

#### Ejemplo

```elscript
/* Eliminar una opción de red */
NetworkOptions.delete('obsolete_network_key')
```
