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
  "$schema": "https://vega.github.io/schema/vega-lite/v6.json",
  "title": "Option Prefix Distribution",
  "width": "container",
  "height": 320,
  "data": {
    "values": [
      { "prefix": "(Transients)", "count": 29 },
      { "prefix": "widget", "count": 18 },
      { "prefix": "default", "count": 9 },
      { "prefix": "(No prefix)", "count": 7 },
      { "prefix": "auto", "count": 5 },
      { "prefix": "mailserver", "count": 4 },
      { "prefix": "show", "count": 3 },
      { "prefix": "admin", "count": 2 },
      { "prefix": "recently", "count": 2 },
      { "prefix": "avatar", "count": 2 },
      { "prefix": "rewrite", "count": 1 },
      { "prefix": "wp_user", "count": 1 },
      { "prefix": "sidebars", "count": 1 },
      { "prefix": "active", "count": 1 },
      { "prefix": "theme", "count": 1 },
      { "prefix": "permalink", "count": 1 },
      { "prefix": "ping", "count": 1 },
      { "prefix": "wp_force", "count": 1 },
      { "prefix": "html", "count": 1 },
      { "prefix": "recovery", "count": 1 }
    ]
  },
  "mark": { "type": "bar", "color": "#3858e9", "cornerRadiusEnd": 4, "tooltip": true },
  "encoding": {
    "x": { "field": "prefix", "type": "nominal", "sort": "-y", "axis": { "title": "Prefix", "labelAngle": -45 } },
    "y": { "field": "count", "type": "quantitative", "axis": { "title": "Count" } }
  }
}} />

<VegaLite spec={{
  "$schema": "https://vega.github.io/schema/vega-lite/v6.json",
  "title": "Autoload Distribution",
  "width": "container",
  "height": 280,
  "data": {
    "values": [
      { "autoload": "on", "count": 102 },
      { "autoload": "off", "count": 36 },
      { "autoload": "auto", "count": 18 }
    ]
  },
  "mark": { "type": "arc", "tooltip": true },
  "encoding": {
    "theta": { "field": "count", "type": "quantitative", "stack": true },
    "color": { "field": "autoload", "type": "nominal", "scale": { "scheme": "tableau10" }, "legend": { "title": "Autoload" } }
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
