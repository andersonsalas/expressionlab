---
id: sites
title: Sitios de red
sidebar_position: 3
---

# Sitios de red

Permite descubrir, configurar e inspeccionar sitios en una red WordPress Multisite sin escribir llamadas a `switch_to_blog()` ni consultas SQL directas.

:::note Solo para Instalaciones Multisite
El servicio **NetworkSites** solo funciona en entornos de WordPress Multisite. En instalaciones de sitio único, `NetworkSites.current` se evalúa como `null`, y llamar a métodos como `get()`, `list()` o `build()` lanzará una excepción indicando que las funciones de red requieren el modo multisite.
:::

La gestión de redes multisite involucra tres componentes principales:

1. **Servicio NetworkSites (`NetworkSites`)**: El punto de entrada principal para descubrir, consultar, listar y construir instancias de sitios.
2. **Modelo Site (`Site`)**: Una entidad que representa un sitio individual en la red con propiedades, métodos de configuración (setters), gestión de membresía de usuarios y métodos de persistencia (`save()`, `delete()`).
3. **Modelo SiteOptions (`SiteOptions`)**: Accesible a través de `site.options`, proporciona operaciones CRUD de opciones con comprobación de compatibilidad de formato y gráficos de distribución de autocarga para ese subsitio específico.

---

## Acceso y listado de sitios

### NetworkSites.current

Una propiedad que devuelve la instancia del modelo `Site` correspondiente al contexto del sitio activo donde se está ejecutando la consola de Expression Lab.

```elscriptsignature
NetworkSites.current: ?Site
```

#### Ejemplo

```elscript
/* Inspeccionar el sitio actual */
NetworkSites.current
```

---

### NetworkSites.get()

Resuelve y devuelve una instancia del modelo `Site` por su ID numérico de blog o por su combinación de dominio y ruta.

```elscriptsignature
NetworkSites.get(
  int|string:id_or_path
): Site
```

#### Modos de resolución

* **Por ID Numérico** (Entero o cadena numérica):
  Se especifica un valor numérico (por ejemplo, `1`, `2` o `'2'`) para buscar el sitio por su `blog_id`.
* **Por dominio o ruta**:
  Se especifica un dominio o ruta relativa (por ejemplo, `'sub.example.com'`, `'sub.example.com/site/'` o `'subsite'`). El servicio normaliza los protocolos (`http://`, `https://`) y las barras de ruta para encontrar registros coincidentes.

Si no se encuentra ningún sitio coincidente en la red, se lanza una excepción.

#### Ejemplos

```elscript
/* Buscar sitio por ID numérico */
NetworkSites.get(2)
```

```elscript
/* Buscar sitio por dominio y subruta */
NetworkSites.get('example.com/client-a/')
```

```elscript
/* Buscar sitio de subdominio */
NetworkSites.get('shop.example.com')
```

---

### NetworkSites.list()

Consulta y devuelve una lista paginada de los sitios registrados en la red, generando una tabla de visualización interactiva en la consola de Expression Lab.

```elscriptsignature
NetworkSites.list(
  ?int:limit = 100,
  int:offset = 0
): array
```

#### Parámetros

* **`limit`** (`?int`, _opcional_): El número máximo de registros de sitios a recuperar. Limitado a un umbral de seguridad de `1000` para evitar el agotamiento de memoria en redes masivas. El valor predeterminado es `100`.
* **`offset`** (`int`, _opcional_): Número de registros de sitios a omitir para paginación. El valor predeterminado es `0`.

#### Campos devueltos

Cada elemento de sitio en el arreglo devuelto contiene:

| Campo | Tipo | Descripción |
| :--- | :--- | :--- |
| `blog_id` | `int` | ID único del blog. |
| `site_id` | `int` | ID de la red (por lo general `1`). |
| `domain` | `string` | Nombre de dominio sin protocolo. |
| `path` | `string` | Ruta del subsitio con barras inicial y final (por ejemplo, `/subsite/`). |
| `registered` | `string` | Marca de tiempo de creación del sitio. |
| `last_updated` | `string` | Marca de tiempo de la última actualización del registro del sitio. |
| `public` | `int` | Indicador de indexación en motores de búsqueda (`1` para público, `0` para privado). |
| `archived` | `int` | Indicador de estado archivado (`1` si está archivado). |
| `mature` | `int` | Indicador de contenido para adultos (`1` si está marcado como maduro). |
| `spam` | `int` | Indicador de spam (`1` si está marcado como spam). |
| `deleted` | `int` | Indicador de eliminación (`1` si está marcado como eliminado). |
| `lang_id` | `int` | Identificador de idioma asociado con el sitio. |

#### Ejemplo

```elscript
/* Listar los primeros 20 sitios de la red */
NetworkSites.list(20, 0)
```

---

### NetworkSites.build()

Instancia una nueva instancia no guardada del modelo `Site` preconfigurada para la red actual (`site_id`).

```elscriptsignature
NetworkSites.build(): Site
```

Este método de fábrica permite una configuración fluida antes de guardar el nuevo sitio en la base de datos.

#### Ejemplo

```elscript
/* Construir una nueva instancia de sitio */
NetworkSites.build()
  .set_domain('example.com')
  .set_path('/portal/')
  .set_public(1)
```

:::warning Opciones de Sitio No Guardado
Intentar acceder a `site.options` en una instancia de sitio no guardada lanzará una excepción: `Cannot access site options on an unsaved site instance. Call save() first.`. Debes llamar a `save()` para aprovisionar el sitio antes de interactuar con su tabla de opciones.
:::

---

## Propiedades del sitio

Las instancias del modelo `Site` exponen propiedades que describen el estado, la configuración y las opciones del subsitio:

### Site.blog_id

Identificador numérico único (`blog_id`) para el subsitio. Mapeado desde `WP_Site::$blog_id`.

```elscriptsignature
Site.blog_id: ?int
```

* **Tipo**: `?int` (Solo lectura)
* **Descripción**: Devuelve el ID numérico del blog si el sitio existe en la base de datos de la red. Evalúa a `null` si la instancia proviene de `NetworkSites.build()` y aún no ha sido guardada.

#### Ejemplos

Sitio actual:

```elscript
NetworkSites.current.blog_id
```

Sitio específico:

```elscript
NetworkSites.get(2).blog_id
```

---

### Site.site_id

El identificador de la red (instalación multisite) donde reside este sitio.

```elscriptsignature
Site.site_id: int
```

* **Tipo**: `int` (Solo lectura)
* **Descripción**: Mapeado desde `WP_Site::$site_id`. Por lo general `1` en instalaciones estándar de WordPress multisite.

#### Ejemplos

Sitio actual:

```elscript
NetworkSites.current.site_id
```

Sitio específico:

```elscript
NetworkSites.get(2).site_id
```

---

### Site.domain

El nombre de dominio del subsitio.

```elscriptsignature
Site.domain: string
```

* **Tipo**: `string`
* **Descripción**: Nombre de dominio sin protocolo (por ejemplo, `'example.com'` o `'sub.example.com'`).

#### Ejemplos

Sitio actual:

```elscript
NetworkSites.current.domain
```

Sitio específico:

```elscript
NetworkSites.get(2).domain
```

---

### Site.path

La ruta relativa y estructura de directorios del subsitio.

```elscriptsignature
Site.path: string
```

* **Tipo**: `string`
* **Descripción**: Ruta del subsitio con barras inicial y final (por ejemplo, `'/'`, `'/portal/'`, `'/client-a/'`).

#### Ejemplos

Sitio actual:

```elscript
NetworkSites.current.path
```

Sitio específico:

```elscript
NetworkSites.get(2).path
```

---

### Site.registered

La fecha y hora en que se creó el subsitio.

```elscriptsignature
Site.registered: string
```

* **Tipo**: `string` (Solo lectura)
* **Descripción**: Cadena de marca de tiempo MySQL formateada como `YYYY-MM-DD HH:MM:SS`.

#### Ejemplos

Sitio actual:

```elscript
NetworkSites.current.registered
```

Sitio específico:

```elscript
NetworkSites.get(2).registered
```

---

### Site.last_updated

La fecha y hora en que se modificó por última vez el registro del subsitio.

```elscriptsignature
Site.last_updated: string
```

* **Tipo**: `string` (Solo lectura)
* **Descripción**: Cadena de marca de tiempo MySQL formateada como `YYYY-MM-DD HH:MM:SS`.

#### Ejemplos

Sitio actual:

```elscript
NetworkSites.current.last_updated
```

Sitio específico:

```elscript
NetworkSites.get(2).last_updated
```

---

### Site.public

Ajuste de visibilidad e indexación en motores de búsqueda para el subsitio.

```elscriptsignature
Site.public: int
```

* **Tipo**: `int`
* **Descripción**: Indicador de visibilidad pública. `1` indica que la indexación pública está permitida; `0` disuade a los motores de búsqueda de indexar el sitio.

#### Ejemplos

Sitio actual:

```elscript
NetworkSites.current.public
```

Sitio específico:

```elscript
NetworkSites.get(2).public
```

---

### Site.archived

Estado de archivo del subsitio.

```elscriptsignature
Site.archived: int
```

* **Tipo**: `int`
* **Descripción**: `1` si el subsitio está marcado como archivado; `0` si está activo.

#### Ejemplos

Sitio actual:

```elscript
NetworkSites.current.archived
```

Sitio específico:

```elscript
NetworkSites.get(2).archived
```

---

### Site.mature

Indicador de si el subsitio está marcado como contenido para adultos.

```elscriptsignature
Site.mature: int
```

* **Tipo**: `int`
* **Descripción**: `1` si está marcado como contenido para adultos; `0` si es estándar.

#### Ejemplos

Sitio actual:

```elscript
NetworkSites.current.mature
```

Sitio específico:

```elscript
NetworkSites.get(2).mature
```

---

### Site.spam

Indicador de si el subsitio está marcado como spam.

```elscriptsignature
Site.spam: int
```

* **Tipo**: `int`
* **Descripción**: `1` si el subsitio está marcado como spam; `0` si no es spam.

#### Ejemplos

Sitio actual:

```elscript
NetworkSites.current.spam
```

Sitio específico:

```elscript
NetworkSites.get(2).spam
```

---

### Site.deleted

Estado de eliminación y papelera del subsitio.

```elscriptsignature
Site.deleted: int
```

* **Tipo**: `int`
* **Descripción**: `1` si el subsitio está marcado como eliminado/en papelera; `0` si está activo.

#### Ejemplos

Sitio actual:

```elscript
NetworkSites.current.deleted
```

Sitio específico:

```elscript
NetworkSites.get(2).deleted
```

---

### Site.lang_id

Identificador de idioma asociado con el registro del subsitio.

```elscriptsignature
Site.lang_id: int
```

* **Tipo**: `int`
* **Descripción**: Número de ID de idioma almacenado en la base de datos de red de WordPress.

#### Ejemplos

Sitio actual:

```elscript
NetworkSites.current.lang_id
```

Sitio específico:

```elscript
NetworkSites.get(2).lang_id
```

---

### Site.options

Instancia aislada del repositorio `SiteOptions` vinculada a este subsitio.

```elscriptsignature
Site.options: SiteOptions
```

* **Tipo**: `SiteOptions` (Solo lectura)
* **Descripción**: Proporciona acceso a operaciones CRUD de opciones (`get`, `get_raw`, `update`, `update_raw`, `delete`) y análisis de distribución de autocarga (`stats`) vinculadas a la tabla `wp_{blog_id}_options` del subsitio sin necesidad de `switch_to_blog()`.

:::warning Instancias de Sitio No Guardadas
Intentar acceder a `site.options` en una instancia no guardada (donde `blog_id` es `null`) lanzará una excepción: `Cannot access site options on an unsaved site instance. Call save() first.`. Debes llamar a `save()` para aprovisionar el sitio antes de interactuar con su repositorio de opciones.
:::

#### Ejemplos

Sitio actual:

```elscript
NetworkSites.current.options.get('blogname')
```

Sitio específico:

```elscript
NetworkSites.get(2).options.get('blogname')
```

---

## Configuración y setters del sitio

El modelo `Site` proporciona métodos setter fluidos y encadenables para configurar los atributos del sitio antes de llamar a `save()` o actualizar un registro existente. Cada setter valida y normaliza su entrada, devolviendo `$this` (`Site`):

### Site.set_domain()

Establece y normaliza el dominio del sitio.

```elscriptsignature
Site.set_domain(
  string:domain
): Site
```

#### Parámetros

* **`domain`** (`string`, _requerido_): El dominio a asignar. Los protocolos (`http://`, `https://`) y las barras finales se eliminan y sanitizan.

#### Valor de retorno

Devuelve la instancia actual de `Site` para encadenamiento de métodos.

#### Ejemplos

Sitio actual:

```elscript
NetworkSites.current.set_domain('portal.example.com')
```

Sitio específico:

```elscript
NetworkSites.get(2).set_domain('portal.example.com')
```

---

### Site.set_path()

Establece y normaliza la subruta del sitio.

```elscriptsignature
Site.set_path(
  string:path
): Site
```

#### Parámetros

* **`path`** (`string`, _requerido_): La subruta a asignar. Normaliza las barras iniciales y finales (por ejemplo, `'portal'` se convierte en `'/portal/'`).

#### Valor de retorno

Devuelve la instancia actual de `Site` para encadenamiento de métodos.

#### Ejemplos

Sitio actual:

```elscript
NetworkSites.current.set_path('/portal/')
```

Sitio específico:

```elscript
NetworkSites.get(2).set_path('/portal/')
```

---

### Site.set_public()

Establece la visibilidad en motores de búsqueda y el indicador de indexación pública.

```elscriptsignature
Site.set_public(
  int:public
): Site
```

#### Parámetros

* **`public`** (`int`, _requerido_): `1` para permitir la indexación pública, `0` para privado.

#### Valor de retorno

Devuelve la instancia actual de `Site` para encadenamiento de métodos.

#### Ejemplos

Sitio actual:

```elscript
NetworkSites.current.set_public(1)
```

Sitio específico:

```elscript
NetworkSites.get(2).set_public(1)
```

---

### Site.set_archived()

Establece el indicador de estado de archivo del sitio.

```elscriptsignature
Site.set_archived(
  int:archived
): Site
```

#### Parámetros

* **`archived`** (`int`, _requerido_): `1` para marcar el sitio como archivado, `0` para activo.

#### Valor de retorno

Devuelve la instancia actual de `Site` para encadenamiento de métodos.

#### Ejemplos

Sitio actual:

```elscript
NetworkSites.current.set_archived(0)
```

Sitio específico:

```elscript
NetworkSites.get(2).set_archived(0)
```

---

### Site.set_mature()

Establece el indicador de contenido para adultos del sitio.

```elscriptsignature
Site.set_mature(
  int:mature
): Site
```

#### Parámetros

* **`mature`** (`int`, _requerido_): `1` para marcar como contenido maduro, `0` para estándar.

#### Valor de retorno

Devuelve la instancia actual de `Site` para encadenamiento de métodos.

#### Ejemplos

Sitio actual:

```elscript
NetworkSites.current.set_mature(0)
```

Sitio específico:

```elscript
NetworkSites.get(2).set_mature(0)
```

---

### Site.set_spam()

Establece el indicador de spam para el sitio.

```elscriptsignature
Site.set_spam(
  int:spam
): Site
```

#### Parámetros

* **`spam`** (`int`, _requerido_): `1` para marcar como spam, `0` para no spam.

#### Valor de retorno

Devuelve la instancia actual de `Site` para encadenamiento de métodos.

#### Ejemplos

Sitio actual:

```elscript
NetworkSites.current.set_spam(0)
```

Sitio específico:

```elscript
NetworkSites.get(2).set_spam(0)
```

---

### Site.set_deleted()

Establece el indicador de eliminación/papelera para el sitio.

```elscriptsignature
Site.set_deleted(
  int:deleted
): Site
```

#### Parámetros

* **`deleted`** (`int`, _requerido_): `1` para marcar el sitio como eliminado, `0` para activo.

#### Valor de retorno

Devuelve la instancia actual de `Site` para encadenamiento de métodos.

#### Ejemplos

Sitio actual:

```elscript
NetworkSites.current.set_deleted(0)
```

Sitio específico:

```elscript
NetworkSites.get(2).set_deleted(0)
```

---

### Site.set_lang_id()

Establece el identificador de idioma para el sitio.

```elscriptsignature
Site.set_lang_id(
  int:lang_id
): Site
```

#### Parámetros

* **`lang_id`** (`int`, _requerido_): El número de identificador de idioma.

#### Valor de retorno

Devuelve la instancia actual de `Site` para encadenamiento de métodos.

#### Ejemplos

Sitio actual:

```elscript
NetworkSites.current.set_lang_id(1)
```

Sitio específico:

```elscript
NetworkSites.get(2).set_lang_id(1)
```

---

## Ciclo de vida del sitio y gestión de usuarios

### Site.save()

Persiste el sitio en la base de datos de WordPress.

```elscriptsignature
Site.save(): Site
```

* **Creación de un nuevo sitio**: Si `blog_id` es nulo, `save()` ejecuta `wp_insert_site()`, genera todas las tablas de base de datos centrales para el subsitio, asigna `blog_id`, inicializa el repositorio `site.options` y devuelve la instancia hidratada de `Site`.
* **Actualización de un sitio existente**: Si `blog_id` está presente, `save()` actualiza el registro del sitio en `wp_blogs` / `wp_site` a través de `wp_update_site()`.

#### Ejemplos

Persistir actualizaciones en el sitio actual:

```elscript
NetworkSites.current
  .set_public(1)
  .save()
```

Aprovisionar un nuevo subsitio:

```elscript
NetworkSites.build()
  /* Crear y aprovisionar un nuevo subsitio */
  .set_domain('example.com')
  .set_path('/staging/')
  .set_public(0)
  .save()
  /* Configurar opciones iniciales de inmediato */
  .options.update('blogname', 'Portal de Pruebas')
```

---

### Site.delete()

Elimina el sitio de la red utilizando `wp_delete_site()` del núcleo de WordPress.

```elscriptsignature
Site.delete(): bool
```

:::warning Operación Irreversible
Eliminar un sitio borra sus registros y tablas de forma definitiva. Se debe comprobar el `blog_id` de destino antes de ejecutar la acción.
:::

#### Ejemplos

Sitio específico por ID:

```elscript
/* Eliminar el sitio con ID 5 y borrar todas sus tablas de base de datos */
NetworkSites.get(5).delete()
```

Sitio específico por dominio y ruta:

```elscript
NetworkSites.get('example.com/demo/').delete()
```

---

### Site.list_users()

Devuelve un arreglo de instancias del modelo `User` que representan las cuentas de usuario asignadas a este subsitio específico.

```elscriptsignature
Site.list_users(): User[]
```

Si se llama en una instancia de `Site` no guardada (`blog_id` es `null`), devuelve un arreglo vacío `[]`.

#### Ejemplos

Sitio actual:

```elscript
NetworkSites.current.list_users()
```

Sitio específico:

```elscript
NetworkSites.get(2).list_users()
```

---

### Site.add_user()

Asigna una cuenta de usuario de WordPress a este sitio con el rol especificado.

```elscriptsignature
Site.add_user(
  User:user,
  string:role = 'subscriber'
): bool
```

#### Parámetros

* **`user`** (`User`): Una instancia del modelo `User` a agregar al sitio (recuperada mediante `Users.get()`).
* **`role`** (`string`, _opcional_): El rol a asignar al usuario en este sitio (por ejemplo, `'subscriber'`, `'author'`, `'editor'`, `'administrator'`). El valor predeterminado es `'subscriber'`.

#### Valor de retorno

Devuelve `true` en caso de éxito, o `false` en caso de error.

#### Ejemplos

Sitio actual:

```elscript
NetworkSites.current.add_user( Users.get('anderson'), 'editor' )
```

Sitio específico:

```elscript
NetworkSites.get(2).add_user( Users.get('anderson'), 'editor' )
```

---

### Site.remove_user()

Elimina la asociación y roles de un usuario de este subsitio específico. La cuenta de usuario en sí permanece intacta en toda la red.

```elscriptsignature
Site.remove_user(
  User:user
): bool
```

#### Parámetros

* **`user`** (`User`): La instancia del modelo `User` a remover del sitio.

#### Valor de retorno

Devuelve `true` en caso de éxito, o `false` en caso de error.

#### Ejemplos

Sitio actual:

```elscript
NetworkSites.current.remove_user( Users.get('anderson') )
```

Sitio específico:

```elscript
NetworkSites.get(2).remove_user( Users.get('anderson') )
```

---

## Gestión de opciones del sitio

Cada instancia del modelo `Site` proporciona un repositorio dedicado `Site.options` (`SiteOptions`), permitiendo operaciones CRUD y análisis de autocarga vinculados a la tabla `wp_{blog_id}_options` de ese subsitio sin requerir llamadas manuales a `switch_to_blog()`.

### Site.options.get()

Lee y decodifica o deserializa una opción de la tabla de opciones del subsitio.

```elscriptsignature
Site.options.get(
  string:key,
  mixed:default = null
): mixed
```

#### Ejemplos

Sitio actual:

```elscript
NetworkSites.current.options.get('blogname')
```

Sitio específico:

```elscript
NetworkSites.get(2).options.get('blogname')
```

---

### Site.options.get_raw()

Recupera la carga útil en cadena de texto sin procesar y sin modificar almacenada en la columna `option_value` del subsitio.

```elscriptsignature
Site.options.get_raw(
  string:key,
  mixed:default = null
): ?string
```

#### Ejemplos

Sitio actual:

```elscript
NetworkSites.current.options.get_raw('rewrite_rules')
```

Sitio específico:

```elscript
NetworkSites.get(2).options.get_raw('rewrite_rules')
```

---

### Site.options.update()

Actualiza o crea de forma segura una opción en el subsitio con protecciones contra conflictos de formato (evitando la corrupción accidental entre datos serializados de PHP y JSON).

```elscriptsignature
Site.options.update(
  string:key,
  mixed:value,
  string:serialization_type = Options.FORMAT_SERIALIZED
): bool
```

#### Ejemplos

Sitio actual:

```elscript
NetworkSites.current.options.update('custom_theme_mods', { dark_mode: true, brand_color: '#2271b1' })
```

Sitio específico:

```elscript
NetworkSites.get(2).options.update('custom_theme_mods', { dark_mode: true, brand_color: '#2271b1' })
```

---

### Site.options.update_raw()

Escribe un valor de cadena sin procesar en la tabla de opciones del subsitio y purga la caché de objetos de ese sitio.

```elscriptsignature
Site.options.update_raw(
  string:key,
  string:value,
  string|bool|null:autoload = null
): bool
```

#### Ejemplos

Sitio actual:

```elscript
NetworkSites.current.options.update_raw('custom_value', 'Personalizado!')
```

Sitio específico:

```elscript
NetworkSites.get(2).options.update_raw('custom_value', 'Personalizado!')
```

---

### Site.options.delete()

Elimina una opción del subsitio y purga su caché.

```elscriptsignature
Site.options.delete(
  string:key
): bool
```

#### Ejemplos

Sitio actual:

```elscript
NetworkSites.current.options.delete('custom_value')
```

Sitio específico:

```elscript
NetworkSites.get(2).options.delete('custom_value')
```

---

### Site.options.stats()

Ejecuta un análisis forense optimizado en memoria sobre la tabla `wp_{blog_id}_options` del subsitio, generando gráficos interactivos de Vega-Lite y desgloses tabulares de prefijos de opciones y huellas de autocarga.

```elscriptsignature
site.options.stats(
  ?int:sample_limit = 100000,
  int:graph_limit = 20
): array
```

#### Ejemplos

Sitio actual:

```elscript
NetworkSites.current.options.stats()
```

Sitio específico:

```elscript
NetworkSites.get(3).options.stats()
```
