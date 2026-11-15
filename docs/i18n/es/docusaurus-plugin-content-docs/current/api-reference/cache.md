---
id: cache
title: Caché
sidebar_position: 3
---

# Caché

Permite inspeccionar, diagnosticar y gestionar entradas en la caché de objetos de WordPress.

El servicio `Cache` ofrece métodos para comprobar si existe una caché persistente, consultar métricas de aciertos y fallos, y leer, guardar o eliminar claves dentro de un grupo.

---

## Diagnóstico y estado

### Cache.is_persistent()

Comprueba si el sitio utiliza una caché de objetos persistente externa (como Redis o Memcached) en lugar de la caché en memoria por solicitud.

```elscriptsignature
Cache.is_persistent(): bool
```

#### Ejemplo

```elscript
/* Comprobar si hay una caché de objetos externa activa */
Cache.is_persistent()
```

---

### Cache.status()

Devuelve la configuración y el estado en tiempo de ejecución del sistema de caché de objetos activo.

```elscriptsignature
Cache.status(): array
```

#### Campos devueltos

| Campo | Tipo | Descripción |
| :--- | :--- | :--- |
| `is_persistent` | `bool` | Indica si hay una caché persistente externa activa. |
| `backend` | `string` | Nombre de la clase del controlador de caché activo, o `'none'`. |
| `dropin_exists` | `bool` | Indica si existe el archivo `wp-content/object-cache.php` en disco. |
| `dropin_path` | `?string` | Ruta completa de `object-cache.php`, o `null` si no existe. |
| `global_groups` | `array` | Lista de grupos globales registrados en la caché. |
| `non_persistent_groups` | `array` | Lista de grupos excluidos del almacenamiento persistente. |
| `multisite_blog_id` | `?int` | ID del sitio actual en Multisite, o `null` en instalaciones individuales. |

#### Ejemplo

```elscript
/* Inspeccionar el backend de caché activo y sus grupos */
Cache.status()
```

---

### Cache.stats()

Recopila los contadores de aciertos y fallos de la solicitud actual y genera una tabla resumen junto con un gráfico de distribución.

```elscriptsignature
Cache.stats(): array
```

#### Campos devueltos

| Campo | Tipo | Descripción |
| :--- | :--- | :--- |
| `hits` | `int` | Número de aciertos en caché durante la solicitud. |
| `misses` | `int` | Número de fallos en caché durante la solicitud. |
| `total` | `int` | Suma total de aciertos y fallos. |
| `hit_ratio` | `string` | Porcentaje de aciertos con dos decimales (por ejemplo, `'94.50%'`). |
| `is_persistent` | `bool` | Indica si hay una caché persistente externa activa. |

#### Ejemplo

```elscript
/* Mostrar métricas y gráfico de aciertos frente a fallos */
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

## Lectura de entradas en caché

### Cache.get()

Obtiene el valor almacenado para una clave, o recupera múltiples claves a la vez al pasar un arreglo.

```elscriptsignature
Cache.get(
  string|int|array:key,
  string:group = '',
  bool:force = false,
  mixed:default_value = null
): mixed
```

#### Parámetros

* **`key`** (`string|int|array`, _requerido_): Clave individual o arreglo de claves a consultar.
* **`group`** (`string`, _opcional_): Nombre del grupo de caché. Por defecto `''`.
* **`force`** (`bool`, _opcional_): Fuerza la lectura desde la caché persistente cuando el backend lo admite. Por defecto `false`.
* **`default_value`** (`mixed`, _opcional_): Valor devuelto cuando la clave no existe en caché. Por defecto `null`.

#### Ejemplos

```elscript
/* Leer una entrada individual de caché */
Cache.get('active_plugins', 'options')
```

```elscript
/* Leer múltiples claves del grupo 'posts' */
Cache.get(['post_1', 'post_2'], 'posts', false, null)
```

---

### Cache.has()

Comprueba si una clave existe dentro de un grupo de caché. A diferencia de `Cache.get()`, permite distinguir entre una clave inexistente y una clave cuyo valor guardado es `false`.

```elscriptsignature
Cache.has(
  string|int:key,
  string:group = ''
): bool
```

#### Ejemplo

```elscript
/* Verificar si una clave existe en caché */
Cache.has('user_meta_cache', 'users')
```

---

## Escritura y eliminación en caché

:::warning Protección contra escritura habilitada por defecto
Los métodos `Cache.set()` y `Cache.delete()` quedan bloqueados cuando `EXPRESSION_LAB_DATABASE_READONLY` está en `true` (valor predeterminado en producción). Para permitir escrituras en caché, se debe definir `define( 'EXPRESSION_LAB_DATABASE_READONLY', false );` en `wp-config.php`.
:::

### Cache.set()

Guarda uno o más valores en la caché de objetos con grupo y tiempo de expiración opcionales.

#### Firma para una clave

```elscriptsignature
Cache.set(
  string|int:key,
  mixed:data,
  string:group = '',
  int:expire = 0
): bool
```

#### Firma por lotes

```elscriptsignature
Cache.set(
  array:items,
  string:group = '',
  int:expire = 0
): bool
```

#### Parámetros

* **`key` / `items`** (`string|int|array`, _requerido_): Clave individual (junto con `data`) o mapa asociativo de pares `clave: valor`.
* **`data`** (`mixed`, _requerido para clave individual_): Valor a guardar. Al pasar un mapa por lotes como primer argumento, el segundo parámetro pasa a ser `group`.
* **`group`** (`string`, _opcional_): Nombre del grupo de caché. Por defecto `''`.
* **`expire`** (`int`, _opcional_): Tiempo de expiración en segundos (debe ser `0` o mayor). `0` indica que no expira. Por defecto `0`.

#### Ejemplos

```elscript
/* Guardar un valor durante 5 minutos (300 segundos) */
Cache.set('sample_token', 'xyz-123', 'tokens', 300)
```

```elscript
/* Guardar múltiples entradas en una sola llamada durante 10 minutos */
Cache.set({ 'token_1': 'abc', 'token_2': 'def' }, 'tokens', 600)
```

---

### Cache.delete()

Elimina una o más claves de un grupo de caché.

```elscriptsignature
Cache.delete(
  string|int|array:key,
  string:group = ''
): bool
```

#### Parámetros

* **`key`** (`string|int|array`, _requerido_): Clave individual o arreglo de claves a eliminar.
* **`group`** (`string`, _opcional_): Nombre del grupo de caché. Por defecto `''`.

#### Ejemplos

```elscript
/* Eliminar una entrada individual de caché */
Cache.delete('sample_token', 'tokens')
```

```elscript
/* Eliminar múltiples entradas en una sola llamada */
Cache.delete(['token_1', 'token_2'], 'tokens')
```
