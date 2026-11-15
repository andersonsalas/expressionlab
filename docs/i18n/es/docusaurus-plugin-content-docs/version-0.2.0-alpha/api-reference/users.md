---
id: users
title: Usuarios
sidebar_position: 4
---

# Usuarios

Permite buscar, inspeccionar y gestionar cuentas de usuario de WordPress, roles, capacidades (*capabilities*) y metadatos tanto en sitios individuales como en redes multisite.

La arquitectura de gestión de usuarios consta de tres componentes interconectados:

1. **Servicio Users (`Users`)**: Punto de entrada principal para búsquedas por ID, correo electrónico o nombre de usuario, encadenamiento de constructores de consultas e instanciación de nuevos usuarios.
2. **Modelo User (`User`)**: Entidad que representa a un usuario individual de WordPress, exponiendo sus propiedades centrales (`ID`, `data`, `roles`, `caps`, `allcaps`), evaluación contextual de capacidades (`can()`), métodos modificadores (*setters*) y persistencia del ciclo de vida (`save()`, `delete()`).
3. **Modelo UserMeta (`UserMeta`)**: Repositorio accesible mediante `user.meta` que proporciona operaciones CRUD sobre metadatos con comprobaciones de compatibilidad de formato (PHP serializado frente a JSON) e integración con el constructor de consultas.

---

## Acceso y recuperación de usuarios

### Users.current

Una propiedad que devuelve la instancia del modelo `User` correspondiente al contexto de usuario seleccionado en la interfaz de Expression Lab.

```elscriptsignature
Users.current: ?User
```

* **Tipo**: `?User` (Solo lectura)
* **Descripción**: Evalúa a la instancia del modelo de usuario activo según el contexto de ejecución actual. Devuelve `null` si no se resuelve ningún contexto de usuario.

#### Ejemplo

```elscript
/* Inspeccionar el modelo de usuario activo */
Users.current
```

---

### Users.get()

Recupera una única instancia de usuario por su identificador (ID, correo electrónico o nombre de usuario), o ejecuta las condiciones acumuladas del constructor de consultas cuando se llama sin argumentos.

```elscriptsignature
Users.get(
  int|string|null:identifier = null
): User|array|null
```

#### Modos de resolución

* **Por ID numérico** (`int` o escalar numérico):
  Consulta a través de `get_userdata(absint($identifier))`. Devuelve una instancia de `User` o `null` si no se encuentra.
* **Por dirección de correo electrónico** (`string` que cumpla con `is_email`):
  Consulta a través de `get_user_by('email', $identifier)`. Devuelve una instancia de `User` o `null` si no se encuentra.
* **Por nombre de usuario / login** (`string`):
  Consulta a través de `get_user_by('login', $identifier)`. Devuelve una instancia de `User` o `null` si no se encuentra.
* **Sin argumentos** (`null`):
  Ejecuta las condiciones acumuladas del `QueryBuilder` y devuelve un arreglo de objetos de usuario coincidentes desde SQLite.

#### Ejemplos

```elscript
/* Buscar usuario por ID numérico */
Users.get(2)
```

```elscript
/* Buscar usuario por dirección de correo electrónico */
Users.get('admin@example.com')
```

```elscript
/* Buscar usuario por nombre de usuario */
Users.get('editor_user')
```

---

### Users.list()

Consulta y replica registros de usuarios de la tabla `wp_users` en SQLite utilizando condiciones de filtro y opciones, luego vacía el búfer y devuelve las filas coincidentes como un arreglo de objetos.

```elscriptsignature
Users.list(
  array|object:where = [],
  array|object:options = []
): array
```

#### Parámetros

* **`where`** (`array|object`, _opcional_): Arreglo asociativo u objeto que define las condiciones de filtro pasadas a la capa de replicación de base de datos. El valor predeterminado es `[]`.
* **`options`** (`array|object`, _opcional_): Opciones de configuración que incluyen `orderby`, `limit`, `offset` y selección de columnas. El valor predeterminado es `[]`.

#### Ejemplos

```elscript
/* Recuperar todos los usuarios */
Users.list()
```

```elscript
/* Recuperar usuarios activos ordenados por fecha de registro */
Users.list({ user_status: 0 }, { orderby: { user_registered: 'DESC' }, limit: 20 })
```

---

### Users.build()

Método de fábrica que instancia una nueva instancia no guardada del modelo `User`.

```elscriptsignature
Users.build(): User
```

El modelo `User` devuelto se inicializa con un `ID` no asignado (`null`) y un objeto `data` vacío, listo para la asignación de propiedades mediante métodos setter antes de la persistencia con `save()`.

#### Ejemplo

```elscript
/* Instanciar y configurar un nuevo modelo de usuario */
Users.build()
  .set_user_login('editor')
  .set_user_email('editor@example.com')
  .set_role('editor')
  .save()
```

---

## Constructor de consultas de usuarios (query builder)

El servicio `Users` incorpora métodos encadenables de constructor de consultas. Las condiciones se acumulan en grupos y se compilan en SQL parametrizado cuando se ejecutan a través de `get()` o `query()`. Los datos replicados se colocan en una tabla SQLite en memoria y se descartan al vaciar el búfer.

### Users.where()

Agrega una condición `WHERE` combinada con lógica `AND` dentro del grupo de condiciones actual.

```elscriptsignature
Users.where(
  string:column,
  mixed:operator_or_value,
  mixed:value = null
): Users
```

#### Firmas de llamada

* **Igualdad** (Dos argumentos): `Users.where('column', 'value')`
* **Condición IN** (Dos argumentos con arreglo): `Users.where('column', ['val1', 'val2'])`
* **Operador de comparación** (Tres argumentos): `Users.where('column', 'operator', 'value')`

#### Operadores de comparación compatibles

La forma de tres argumentos `Users.where(column, operator, value)` acepta operadores de comparación estándar de tipo SQL como cadenas:

| Operador | Descripción | Ejemplo |
| :--- | :--- | :--- |
| `'='` | Igualdad exacta (predeterminado) | `Users.where('user_status', '=', 0).get()`, o bien `Users.where('user_status', 0).get()`  |
| `'>'` | Mayor que | `Users.where('ID', '>', 10).get()` |
| `'>='` | Mayor o igual que | `Users.where('ID', '>=', 10).get()` |
| `'<'` | Menor que | `Users.where('ID', '<', 50).get()` |
| `'<='` | Menor o igual que | `Users.where('ID', '<=', 50).get()` |
| `'!='`, `'<>'` | No igual | `Users.where('user_status', '!=', 1).get()` |
| `'like'` | Coincidencia de patrones SQL LIKE | `Users.where('user_email', 'like', '%@example.com').get()` |
| `'not like'` | Coincidencia de patrones SQL LIKE negada | `Users.where('user_login', 'not like', 'test_%').get()` |
| `'in'` | Pertenencia a conjuntos (el valor debe ser un arreglo) | `Users.where('user_login', 'in', ['admin', 'editor']).get()` |
| `'not in'` | Pertenencia a conjuntos negada (el valor debe ser un arreglo) | `Users.where('ID', 'not in', [1, 2, 3]).get()` |
| `'between'` | Comprobación de rango (el valor debe ser un arreglo de dos elementos `[min, max]`) | `Users.where('ID', 'between', [10, 50]).get()` |

:::note Sintaxis de QueryBuilder vs Database.mirror
Los métodos del `QueryBuilder` (`where()`, `or_where()`) aceptan cadenas de operadores SQL estándar (`'>'`, `'like'`, `'between'`) y las compilan en los objetos de condición recursivos con prefijo `$` requeridos por `Database.mirror()`.

Al invocar `Users.list(where, options)` o `Database.mirror(table, where, options)`, se debe emplear la sintaxis de objetos declarativos documentada en [Base de Datos](./database) (por ejemplo, `{ ID: { '$gt': 10 } }`).
:::

#### Ejemplos

```elscript
/* Filtro de igualdad simple */
Users.where('user_login', 'admin').get()
```

```elscript
/* Filtro de comparación numérica */
Users.where('ID', '>', 5).get()
```

```elscript
/* Filtro de coincidencia de patrones */
Users.where('user_email', 'like', '%@company.org').get()
```

---

### Users.or_where()

Agrega una condición `WHERE` que inicia un nuevo grupo de condiciones combinado con los grupos anteriores mediante lógica `OR`. Las condiciones añadidas dentro de cada grupo usando `where()` se combinan mediante `AND`.

```elscriptsignature
Users.or_where(
  string:column,
  mixed:operator_or_value,
  mixed:value = null
): Users
```

#### Ejemplo

```elscript
/* Grupos de condiciones combinadas AND y OR */
Users.where('user_status', 0)
  .or_where('user_login', 'admin')
  .get()
```

---

### Users.order_by()

Especifica el ordenamiento de columnas para los resultados de la consulta. Se puede encadenar varias veces para aplicar ordenamiento multi-columna.

```elscriptsignature
Users.order_by(
  string:column,
  string:direction = 'ASC'
): Users
```

#### Parámetros

* **`column`** (`string`, _requerido_): Nombre de la columna por la cual ordenar (por ejemplo, `'ID'`, `'user_registered'`, `'user_login'`).
* **`direction`** (`string`, _opcional_): Dirección de ordenamiento (`'ASC'` o `'DESC'`). El valor predeterminado es `'ASC'`.

#### Ejemplo

```elscript
/* Ordenar por fecha de registro descendente */
Users.where('user_status', 0)
  .order_by('user_registered', 'DESC')
  .get()
```

---

### Users.limit()

Establece el número máximo de registros de usuarios a devolver.

```elscriptsignature
Users.limit(int:limit): Users
```

#### Ejemplo

```elscript
/* Recuperar los primeros 10 usuarios coincidentes */
Users.where('user_status', 0)
  .order_by('ID', 'ASC')
  .limit(10)
  .get()
```

---

### Users.offset()

Establece el número de filas a omitir para la paginación.

```elscriptsignature
Users.offset(int:offset): Users
```

#### Ejemplo

```elscript
/* Paginar resultados: omitir 20 filas y tomar 10 */
Users.where('user_status', 0)
  .order_by('ID', 'ASC')
  .offset(20)
  .limit(10)
  .get()
```

---

### Users.query()

Replica la tabla `wp_users` en SQLite utilizando las condiciones y opciones acumuladas, luego ejecuta una consulta SQL `SELECT` arbitraria contra la base de datos SQLite en memoria. Reinicia el estado del constructor tras la ejecución.

```elscriptsignature
Users.query(string:sql): array
```

#### Ejemplo

```elscript
/* Ejecutar SQL arbitrario en la tabla SQLite replicada */
Users.where('user_status', 0)
  .query('SELECT ID, user_login, user_email FROM wp_users ORDER BY ID DESC')
```

:::info Extensión SQLite3 Requerida
La extensión de PHP SQLite3 **debe** estar instalada y habilitada en tu servidor para que `Users.query()` funcione.
:::

---

### Users.count()

Cuenta los registros de usuarios coincidentes en la base de datos MySQL sin almacenar filas en memoria ni inicializar una tabla SQLite. Reinicia el estado del constructor tras la ejecución.

```elscriptsignature
Users.count(): int
```

#### Ejemplos

```elscript
/* Contar usuarios activos */
Users.where('user_status', 0).count()
```

```elscript
/* Contar usuarios que coincidan con un dominio de correo electrónico */
Users.where('user_email', 'like', '%@example.com').count()
```

---

### Users.to_sql()

Compila y devuelve la cadena de consulta SQL para las condiciones acumuladas sin ejecutarla. Reinicia el estado del constructor tras la compilación.

```elscriptsignature
Users.to_sql(bool:is_count = false): string
```

#### Parámetros

* **`is_count`** (`bool`, _opcional_): Cuando es `true`, compila una consulta `SELECT COUNT(*)` en lugar de una proyección de columnas. El valor predeterminado es `false`.

#### Ejemplos

```elscript
/* Previsualizar consulta SQL compilada */
Users.where('user_status', 0)
  .order_by('ID', 'DESC')
  .limit(25)
  .to_sql()
```

```elscript
/* Previsualizar consulta de conteo compilada */
Users.where('user_email', 'like', '%@company.org').to_sql(true)
```

---

## Propiedades del modelo User

El modelo `User` representa un registro individual de usuario de WordPress.

### User.ID

El identificador de clave primaria numérica del usuario en la base de datos.

```elscriptsignature
User.ID: ?int
```

* **Tipo**: `?int` (Solo lectura)
* **Descripción**: Devuelve el ID de base de datos del usuario. Evalúa a `null` si la instancia proviene de `Users.build()` y aún no se ha guardado en la base de datos.

#### Ejemplos

Usuario actual:

```elscript
Users.current.ID
```

Usuario específico:

```elscript
Users.get(2).ID
```

---

### User.data

Un objeto que contiene las columnas estándar de usuario de WordPress mapeadas desde `WP_User::to_array()`.

```elscriptsignature
User.data: object
```

* **Tipo**: `object` (`stdClass`)
* **Descripción**: Contiene las columnas principales de usuario con tipos escalares normalizados.

#### Referencia de campos

| Campo | Tipo | Descripción |
| :--- | :--- | :--- |
| `ID` | `int` | ID de usuario en la base de datos. |
| `user_login` | `string` | Nombre de usuario para iniciar sesión. |
| `user_pass` | `string` | Hash de la contraseña almacenada en la base de datos. |
| `user_nicename` | `string` | Slug sanitizado del usuario para URLs. |
| `user_email` | `string` | Dirección de correo electrónico del usuario. |
| `user_url` | `string` | URL del sitio web del usuario. |
| `user_registered` | `string` | Marca de tiempo de registro (`YYYY-MM-DD HH:MM:SS`). |
| `user_activation_key` | `string` | Clave hash para restablecimiento de contraseña o activación. |
| `user_status` | `int` | Entero indicador del estado del usuario. |
| `display_name` | `string` | Nombre visible del usuario. |
| `spam` | `int` | Indicador de spam en Multisite (`1` si está marcado como spam, `0` en caso contrario). |
| `deleted` | `int` | Indicador de eliminación en Multisite (`1` si está marcado como eliminado, `0` en caso contrario). |

#### Ejemplos

Usuario actual:

```elscript
Users.current.data.user_login
```

Usuario específico:

```elscript
Users.get(2).data.user_login
```

---

### User.roles

Un arreglo indexado de identificadores de roles asignados al usuario.

```elscriptsignature
User.roles: ?array
```

* **Tipo**: `?array` (Solo lectura)
* **Descripción**: Lista de roles asignados al usuario (por ejemplo, `['administrator']`, `['editor']`). Mapeado desde `WP_User::$roles`.

#### Ejemplos

Usuario actual:

```elscript
Users.current.roles
```

Usuario específico:

```elscript
Users.get(2).roles
```

---

### User.caps

Un arreglo asociativo de indicadores de capacidad asignados al registro del usuario.

```elscriptsignature
User.caps: ?array
```

* **Tipo**: `?array` (Solo lectura)
* **Descripción**: Mapa clave-valor que representa asignaciones individuales de capacidades. Mapeado desde `WP_User::$caps`.

#### Ejemplos

Usuario actual:

```elscript
Users.current.caps
```

Usuario específico:

```elscript
Users.get(2).caps
```

---

### User.cap_key

La clave de metadatos de base de datos donde se almacenan los registros de capacidades para este usuario en `wp_usermeta`.

```elscriptsignature
User.cap_key: ?string
```

* **Tipo**: `?string` (Solo lectura)
* **Descripción**: Resuelve al prefijo de capacidad del sitio (por ejemplo, `'wp_capabilities'` o `'wp_2_capabilities'` en subsitios multisite). Mapeado desde `WP_User::$cap_key`.

#### Ejemplos

Usuario actual:

```elscript
Users.current.cap_key
```

Usuario específico:

```elscript
Users.get(2).cap_key
```

---

### User.allcaps

Un arreglo asociativo de todas las capacidades efectivas otorgadas al usuario.

```elscriptsignature
User.allcaps: ?array
```

* **Tipo**: `?array` (Solo lectura)
* **Descripción**: Mapa de capacidades combinado, que fusiona los permisos heredados del rol con las capacidades explícitas por usuario. Mapeado desde `WP_User::$allcaps`.

#### Ejemplos

Usuario actual:

```elscript
Users.current.allcaps
```

Usuario específico:

```elscript
Users.get(2).allcaps
```

---

### User.filter

Filtro de sanitización de contexto aplicado al objeto de usuario.

```elscriptsignature
User.filter: mixed
```

* **Tipo**: `mixed` (Solo lectura)
* **Descripción**: Contexto de filtro de sanitización mapeado desde `WP_User::$filter` (por lo general `null` o `'raw'`).

#### Ejemplos

Usuario actual:

```elscript
Users.current.filter
```

Usuario específico:

```elscript
Users.get(2).filter
```

---

### User.meta

Una instancia aislada del repositorio `UserMeta` vinculada a este usuario.

```elscriptsignature
User.meta: UserMeta
```

* **Tipo**: `UserMeta` (Solo lectura)
* **Descripción**: Expone operaciones de metadatos (`get`, `get_raw`, `update`, `update_raw`, `delete`, `has`, `list`) y filtrado con constructor de consultas dirigido a `wp_usermeta` para este usuario específico.

#### Ejemplos

Usuario actual:

```elscript
Users.current.meta.get('first_name')
```

Usuario específico:

```elscript
Users.get(2).meta.get('first_name')
```

---

## Gestión de capacidades del usuario

### User.can()

Comprueba si el usuario posee una capacidad de WordPress o un permiso primitivo específico.

```elscriptsignature
User.can(
  string:capability,
  array:args = []
): bool
```

#### Parámetros

* **`capability`** (`string`, _requerido_): El nombre de la capacidad a comprobar (por ejemplo, `'manage_options'`, `'edit_posts'`, `'publish_pages'`).
* **`args`** (`array`, _opcional_): Argumentos contextuales adicionales pasados a `WP_User::has_cap()` (por ejemplo, un ID de post específico `[42]`). El valor predeterminado es `[]`.

:::note Detección de Contexto Multisite
Al evaluar expresiones dentro del contexto de un subsitio específico en una instalación multisite, `User.can()` cambia el contexto del blog mediante `switch_to_blog()` para verificar roles y permisos específicos del sitio, restaurando el contexto inicial mediante `restore_current_blog()` una vez evaluado.
:::

#### Valor de retorno

Devuelve `true` si el usuario posee la capacidad, `false` en caso contrario. Devuelve `false` si se llama en una instancia de usuario no inicializada.

#### Ejemplos

Usuario actual:

```elscript
Users.current.can('manage_options')
```

Usuario específico:

```elscript
Users.get(2).can('edit_post', [128])
```

---

### User.add_cap()

Asigna una capacidad individual nativa o personalizada al usuario en `wp_usermeta` y actualiza los permisos efectivos.

```elscriptsignature
User.add_cap(
  string:capability,
  bool:grant = true
): User
```

#### Parámetros

* **`capability`** (`string`, _requerido_): El slug de la capacidad a otorgar (por ejemplo, `'publish_posts'`, `'manage_custom_reports'`).
* **`grant`** (`bool`, _opcional_): Si la capacidad se concede (`true`) o se deniega (`false`). El valor predeterminado es `true`.

#### Valor de retorno

Devuelve la instancia actual de `User` para encadenamiento de métodos.

#### Ejemplos

Usuario actual:

```elscript
Users.current.add_cap('export_reports')
```

Usuario específico:

```elscript
Users.get(2).add_cap('edit_custom_posts')
```

---

### User.remove_cap()

Elimina una capacidad individual del usuario en `wp_usermeta` y actualiza los permisos efectivos.

```elscriptsignature
User.remove_cap(string:capability): User
```

#### Parámetros

* **`capability`** (`string`, _requerido_): El slug de la capacidad a eliminar del usuario.

#### Valor de retorno

Devuelve la instancia actual de `User` para encadenamiento de métodos.

#### Ejemplos

Usuario actual:

```elscript
Users.current.remove_cap('export_reports')
```

Usuario específico:

```elscript
Users.get(2).remove_cap('edit_custom_posts')
```

---

## Configuración y setters del usuario

El modelo `User` expone métodos setter fluidos para configurar propiedades antes de su persistencia. Cada setter devuelve la instancia actual (`User`) para encadenamiento de métodos.

### User.set_user_login()

Establece el nombre de usuario de inicio de sesión para una nueva instancia de usuario.

```elscriptsignature
User.set_user_login(string:user_login): User
```

#### Parámetros

* **`user_login`** (`string`, _requerido_): El nombre de usuario a asignar.

:::note Inmutable en Usuarios Existentes
En WordPress, `user_login` es inmutable una vez que se ha creado una cuenta de usuario. `set_user_login()` solo funciona al aprovisionar un nuevo usuario mediante `Users.build()`. Llamarlo en un usuario existente (`Users.get(ID)` o `Users.current`) no actualizará el nombre de usuario en la base de datos.
:::

#### Ejemplo

```elscript
Users.build()
  .set_user_login('author_account')
  .set_user_email('author@example.com')
  .set_role('author')
  .save()
```

---

### User.set_user_pass()

Establece la contraseña en texto plano para el usuario y marca el estado de la contraseña como modificado.

```elscriptsignature
User.set_user_pass(string:user_pass): User
```

#### Parámetros

* **`user_pass`** (`string`, _requerido_): La contraseña en texto plano. WordPress genera el hash de este valor durante `save()`.

#### Ejemplos

Usuario actual:

```elscript
Users.current
  .set_user_pass('nueva_contraseña_secreta')
  .save()
```

Usuario específico:

```elscript
Users.get(2)
  .set_user_pass('nueva_contraseña_secreta')
  .save()
```

---

### User.set_user_nicename()

Establece el slug de URL (nicename) para el usuario.

```elscriptsignature
User.set_user_nicename(string:user_nicename): User
```

#### Parámetros

* **`user_nicename`** (`string`, _requerido_): El slug sanitizado del usuario.

#### Ejemplos

Usuario actual:

```elscript
Users.current
  .set_user_nicename('john-doe')
  .save()
```

Usuario específico:

```elscript
Users.get(2)
  .set_user_nicename('john-doe')
  .save()
```

---

### User.set_user_email()

Establece la dirección de correo electrónico para el usuario.

```elscriptsignature
User.set_user_email(string:user_email): User
```

#### Parámetros

* **`user_email`** (`string`, _requerido_): La dirección de correo electrónico del usuario.

#### Ejemplos

Usuario actual:

```elscript
Users.current
  .set_user_email('contacto@example.com')
  .save()
```

Usuario específico:

```elscript
Users.get(2)
  .set_user_email('contacto@example.com')
  .save()
```

---

### User.set_user_url()

Establece la URL del sitio web para el usuario.

```elscriptsignature
User.set_user_url(string:user_url): User
```

#### Parámetros

* **`user_url`** (`string`, _requerido_): La cadena URL del sitio web.

#### Ejemplos

Usuario actual:

```elscript
Users.current
  .set_user_url('https://example.com')
  .save()
```

Usuario específico:

```elscript
Users.get(2)
  .set_user_url('https://example.com')
  .save()
```

---

### User.set_display_name()

Establece el nombre público a mostrar para el usuario.

```elscriptsignature
User.set_display_name(string:display_name): User
```

#### Parámetros

* **`display_name`** (`string`, _requerido_): El nombre público a mostrar.

#### Ejemplos

Usuario actual:

```elscript
Users.current
  .set_display_name('John Doe')
  .save()
```

Usuario específico:

```elscript
Users.get(2)
  .set_display_name('John Doe')
  .save()
```

---

### User.set_role()

Establece el rol a asignar al usuario al momento de guardar.

```elscriptsignature
User.set_role(string:role): User
```

#### Parámetros

* **`role`** (`string`, _requerido_): El slug del rol de WordPress a asignar (por ejemplo, `'administrator'`, `'editor'`, `'author'`, `'contributor'`, `'subscriber'`).

#### Ejemplos

Usuario actual:

```elscript
Users.current
  .set_role('editor')
  .save()
```

Usuario específico:

```elscript
Users.get(2)
  .set_role('editor')
  .save()
```

---

## Persistencia y ciclo de vida del usuario

### User.save()

Persiste las modificaciones del modelo de usuario o crea un nuevo usuario en la base de datos de WordPress.

```elscriptsignature
User.save(): User|false
```

#### Comportamiento

* **Creación de nuevos usuarios (`ID` es `null`)**:
  Prepara la carga útil de inserción con `user_login`, `user_pass` (genera una contraseña aleatoria mediante `wp_generate_password()` si se omite), `user_nicename`, `user_email`, `user_url`, `display_name` y el `role` asignado. Llama a `wp_insert_user()`. Devuelve una nueva instancia de `User` poblada con el ID insertado y los datos en caso de éxito, o `false` en caso de error.
* **Actualización de usuarios existentes (`ID` está poblado)**:
  Actualiza las columnas existentes mediante `wp_update_user()`. Solo actualiza la contraseña si se llamó a `set_user_pass()`. Actualiza el rol si se especificó mediante `set_role()`. Devuelve la instancia actual de `User` en caso de éxito, o `false` en caso de error.

#### Ejemplos

Usuario actual:

```elscript
Users.current
  .set_display_name('Jane Doe')
  .set_user_email('jane.doe@example.com')
  .save()
```

Usuario específico:

```elscript
Users.get(2)
  .set_display_name('Jane Doe')
  .set_user_email('jane.doe@example.com')
  .save()
```

---

### User.delete()

Elimina el usuario de la base de datos de WordPress.

```elscriptsignature
User.delete(
  ?int:reassign = null
): bool
```

#### Parámetros

* **`reassign`** (`?int`, _opcional_): El ID de usuario al que se reasignarán las publicaciones tras la eliminación (solo en instalaciones de sitio único). El valor predeterminado es `null` (elimina las publicaciones del usuario).

#### Mecanismos de ejecución

* **Sitio único**: Ejecuta `wp_delete_user($this->ID, $reassign)`.
* **Multisitio**: Ejecuta `wpmu_delete_user($this->ID)`.

#### Valor de retorno

Devuelve `true` tras una eliminación exitosa, o `false` si `ID` es `null` o la operación de eliminación falla.

:::note Protección de Administrador y Usuario Autenticado
Para evitar bloqueos accidentales de administración y corrupción de sesión, `User.delete()` rechaza la eliminación del administrador designado de Expression Lab (`EXPRESSION_LAB_ADMIN_USER_ID`) y del usuario autenticado actual de WordPress.
:::

#### Ejemplos

```elscript
/* Eliminar usuario y reasignar publicaciones al administrador (ID de usuario 1) */
Users.get(15).delete(1)
```

```elscript
/* Eliminar usuario y remover su contenido de forma definitiva */
Users.get(15).delete()
```

---

## Gestión de metadatos de usuario

Las operaciones de metadatos de usuario se acceden a través de la propiedad `meta` en una instancia del modelo `User` (por ejemplo, `Users.current.meta` o `Users.get(2).meta`).

### UserMeta.get()

Recupera un valor de metadatos para el usuario, deserializando cargas estructuradas de PHP o decodificando cadenas JSON. Cuando se llama sin argumentos, ejecuta el constructor de consultas contra `wp_usermeta`.

```elscriptsignature
UserMeta.get(
  ?string:key = null,
  mixed:default_value = null
): mixed
```

#### Parámetros

* **`key`** (`?string`, _opcional_): La clave de metadatos a recuperar. Si es `null`, ejecuta las condiciones acumuladas del constructor de consultas en `wp_usermeta` para este usuario.
* **`default_value`** (`mixed`, _opcional_): Valor devuelto si la clave no existe. El valor predeterminado es `null`.

#### Deserialización y detección de formatos

1. **PHP serializado**: Se procesa mediante validación estricta (`Helper::strict_unserialize`). Rechaza clases de objetos no autorizadas.
2. **Cargas JSON**: Analizadas a través de `json_decode` en estructuras asociativas.
3. **Cadenas escalares**: Se devuelven sin modificar.
4. **Claves inexistentes**: Devuelve `default_value`.

#### Ejemplos

Usuario actual:

```elscript
Users.current
  .meta
  .get('first_name')
```

Usuario específico:

```elscript
Users.get(2)
  .meta
  .get('first_name')
```

---

### UserMeta.get_raw()

Recupera la cadena exacta almacenada en la columna `meta_value` de `wp_usermeta` sin ejecutar deserialización de PHP ni análisis JSON.

```elscriptsignature
UserMeta.get_raw(
  string:key,
  mixed:default_value = null
): mixed
```

#### Parámetros

* **`key`** (`string`, _requerido_): El nombre de la clave de metadatos.
* **`default_value`** (`mixed`, _opcional_): Valor de respaldo si no se encuentra la clave. El valor predeterminado es `null`.

#### Ejemplos

Usuario actual:

```elscript
Users.current
  .meta
  .get_raw('session_tokens')
```

Usuario específico:

```elscript
Users.get(2)
  .meta
  .get_raw('session_tokens')
```

---

### UserMeta.has()

Comprueba si existe una clave de metadatos específica para el usuario en la tabla `wp_usermeta`.

```elscriptsignature
UserMeta.has(string:key): bool
```

#### Parámetros

* **`key`** (`string`, _requerido_): La clave de metadatos a verificar.

#### Valor de retorno

Devuelve `true` si existe al menos una fila para este usuario y clave, `false` en caso contrario.

#### Ejemplos

Usuario actual:

```elscript
Users.current
  .meta
  .has('billing_address_1')
```

Usuario específico:

```elscript
Users.get(2)
  .meta
  .has('billing_address_1')
```

---

### UserMeta.list()

Replica y devuelve todos los registros de metadatos asociados con el usuario (`user_id = User.ID`) de `wp_usermeta`.

```elscriptsignature
UserMeta.list(): array
```

#### Valor de retorno

Devuelve un arreglo de objetos que representan todas las filas de metadatos de este usuario.

#### Ejemplos

Usuario actual:

```elscript
Users.current
  .meta
  .list()
```

Usuario específico:

```elscript
Users.get(2)
  .meta
  .list()
```

---

### UserMeta.update()

Actualiza una entrada de metadatos existente o crea una nueva para el usuario, aplicando comprobaciones de conflicto de formatos y validación estricta de serialización.

```elscriptsignature
UserMeta.update(
  string:key,
  mixed:value,
  string:serialization_type = Options.FORMAT_SERIALIZED
): bool
```

#### Constantes de formato de serialización

| Constante | Valor | Descripción |
| :--- | :--- | :--- |
| `Options.FORMAT_SERIALIZED` | `'serialized'` | Predeterminado. Codifica arreglos y estructuras escalares utilizando la serialización estándar de PHP (`serialize`). |
| `Options.FORMAT_SERIALIZED_OBJECT` | `'serialized_object'` | Convierte el arreglo raíz a `stdClass` antes de serializar. |
| `Options.FORMAT_JSON` | `'json'` | Codifica el valor como JSON a través de `wp_json_encode`. |

#### Protecciones contra conflictos de formato

* Si el valor existente en la base de datos está serializado con PHP, `UserMeta.update()` **rechaza** actualizarlo con `Options.FORMAT_JSON`.
* Si el valor existente en la base de datos está en formato JSON, `UserMeta.update()` **rechaza** actualizarlo con `Options.FORMAT_SERIALIZED`.
* Para eludir las protecciones de conflicto de formato, se debe utilizar `UserMeta.update_raw()`.

#### Ejemplos

Usuario actual:

```elscript
Users.current
  .meta
  .update('user_preferences', { compact_view: true, per_page: 25 })
```

Usuario específico:

```elscript
Users.get(2)
  .meta
  .update('user_preferences', { compact_view: true, per_page: 25 })
```

---

### UserMeta.update_raw()

Escribe un valor de cadena sin procesar en la columna `meta_value` de `wp_usermeta` sin serialización ni codificación JSON.

```elscriptsignature
UserMeta.update_raw(
  string:key,
  string:value
): bool
```

#### Parámetros

* **`key`** (`string`, _requerido_): El nombre de la clave de metadatos.
* **`value`** (`string`, _requerido_): Carga útil en cadena sin procesar a almacenar.

#### Ejemplos

Usuario actual:

```elscript
Users.current
  .meta
  .update_raw('custom_status_flag', 'verified_active')
```

Usuario específico:

```elscript
Users.get(2)
  .meta
  .update_raw('custom_status_flag', 'verified_active')
```

---

### UserMeta.delete()

Elimina una clave de metadatos del usuario de `wp_usermeta`.

```elscriptsignature
UserMeta.delete(string:key): bool
```

#### Parámetros

* **`key`** (`string`, _requerido_): El nombre de la clave de metadatos a eliminar.

#### Valor de retorno

Devuelve `true` si fue eliminada, `false` en caso contrario.

#### Ejemplos

Usuario actual:

```elscript
Users.current
  .meta
  .delete('temporary_login_token')
```

Usuario específico:

```elscript
Users.get(2)
  .meta
  .delete('temporary_login_token')
```

---

## Constructor de consultas en metadatos de usuario

El modelo `UserMeta` implementa el trait `QueryBuilder`, permitiendo encadenar condiciones sobre el repositorio de metadatos de un usuario (`User.meta`) contra la tabla `wp_usermeta`.

Los registros de metadatos replicados se colocan en una tabla SQLite en memoria y se descartan al vaciar el búfer.

### UserMeta.where()

Agrega una condición `WHERE` combinada con lógica `AND` dentro del grupo de condiciones actual en `wp_usermeta`.

```elscriptsignature
UserMeta.where(
  string:column,
  mixed:operator_or_value,
  mixed:value = null
): UserMeta
```

#### Firmas de llamada

* **Igualdad** (Dos argumentos): `user.meta.where('column', 'value')`
* **Condición IN** (Dos argumentos con arreglo): `user.meta.where('column', ['val1', 'val2'])`
* **Operador de comparación** (Tres argumentos): `user.meta.where('column', 'operator', 'value')`

#### Operadores de comparación compatibles

La forma de tres argumentos `User.meta.where(column, operator, value)` acepta operadores de comparación estándar de SQL:

| Operador | Descripción | Ejemplo |
| :--- | :--- | :--- |
| `'='` | Igualdad exacta (predeterminado) | `Users.current.meta.where('meta_key', '=', 'first_name').get()`, o bien `Users.current.meta.where('meta_key', 'first_name').get()` |
| `'>'` | Mayor que | `Users.current.meta.where('umeta_id', '>', 50).get()` |
| `'>='` | Mayor o igual que | `Users.current.meta.where('umeta_id', '>=', 50).get()` |
| `'<'` | Menor que | `Users.current.meta.where('umeta_id', '<', 200).get()` |
| `'<='` | Menor o igual que | `Users.current.meta.where('umeta_id', '<=', 200).get()` |
| `'!='`, `'<>'` | No igual | `Users.current.meta.where('meta_key', '!=', 'session_tokens').get()` |
| `'like'` | Coincidencia de patrones SQL LIKE | `Users.current.meta.where('meta_key', 'like', 'wp_%').get()` |
| `'not like'` | Coincidencia de patrones SQL LIKE negada | `Users.current.meta.where('meta_key', 'not like', '_transient_%').get()` |
| `'in'` | Pertenencia a conjuntos (el valor debe ser un arreglo) | `Users.current.meta.where('meta_key', 'in', ['first_name', 'last_name', 'nickname']).get()` |
| `'not in'` | Pertenencia a conjuntos negada (el valor debe ser un arreglo) | `Users.current.meta.where('meta_key', 'not in', ['session_tokens', 'closedpostboxes_%']).get()` |
| `'between'` | Comprobación de rango (el valor debe ser un arreglo de dos elementos `[min, max]`) | `Users.current.meta.where('umeta_id', 'between', [100, 500]).get()` |

#### Ejemplos

Usuario actual:

```elscript
Users.current
  .meta
  .where('meta_key', 'like', 'wp_%')
  .get()
```

Usuario específico:

```elscript
Users.get(2)
  .meta
  .where('meta_key', 'in', ['first_name', 'last_name', 'nickname'])
  .get()
```

---

### UserMeta.or_where()

Agrega una condición `WHERE` que inicia un nuevo grupo de condiciones combinado con los grupos anteriores mediante lógica `OR`. Las condiciones añadidas dentro de cada grupo usando `where()` se combinan mediante `AND`.

```elscriptsignature
UserMeta.or_where(
  string:column,
  mixed:operator_or_value,
  mixed:value = null
): UserMeta
```

#### Ejemplos

Usuario actual:

```elscript
Users.current
  .meta
  .where('meta_key', 'first_name')
  .or_where('meta_key', 'last_name')
  .get()
```

Usuario específico:

```elscript
Users.get(2)
  .meta
  .where('meta_key', 'first_name')
  .or_where('meta_key', 'last_name')
  .get()
```

---

### UserMeta.order_by()

Especifica el ordenamiento de columnas para los resultados de consultas de metadatos replicados. Se puede encadenar varias veces para aplicar ordenamiento multi-columna.

```elscriptsignature
UserMeta.order_by(
  string:column,
  string:direction = 'ASC'
): UserMeta
```

#### Parámetros

* **`column`** (`string`, _requerido_): Nombre de la columna por la cual ordenar (`'umeta_id'`, `'meta_key'`, `'meta_value'`).
* **`direction`** (`string`, _opcional_): Dirección de ordenamiento (`'ASC'` o `'DESC'`). El valor predeterminado es `'ASC'`.

#### Ejemplos

Usuario actual:

```elscript
Users.current
  .meta
  .where('meta_key', 'like', 'billing_%')
  .order_by('umeta_id', 'DESC')
  .get()
```

Usuario específico:

```elscript
Users.get(2)
  .meta
  .where('meta_key', 'like', 'billing_%')
  .order_by('umeta_id', 'DESC')
  .get()
```

---

### UserMeta.limit()

Establece el número máximo de filas de metadatos a devolver.

```elscriptsignature
UserMeta.limit(int:limit): UserMeta
```

#### Ejemplos

Usuario actual:

```elscript
Users.current
  .meta
  .where('meta_key', 'like', 'wp_%')
  .order_by('umeta_id', 'ASC')
  .limit(5)
  .get()
```

Usuario específico:

```elscript
Users.get(2)
  .meta
  .where('meta_key', 'like', 'wp_%')
  .order_by('umeta_id', 'ASC')
  .limit(5)
  .get()
```

---

### UserMeta.offset()

Establece el número de filas a omitir para paginación a través de las filas de metadatos.

```elscriptsignature
UserMeta.offset(int:offset): UserMeta
```

#### Ejemplos

Usuario actual:

```elscript
Users.current
  .meta
  .where('meta_key', 'like', 'wp_%')
  .order_by('umeta_id', 'ASC')
  .offset(10)
  .limit(5)
  .get()
```

Usuario específico:

```elscript
Users.get(2)
  .meta
  .where('meta_key', 'like', 'wp_%')
  .order_by('umeta_id', 'ASC')
  .offset(10)
  .limit(5)
  .get()
```

---

### UserMeta.query()

Replica la tabla `wp_usermeta` en SQLite utilizando las condiciones y opciones acumuladas, luego ejecuta una consulta SQL `SELECT` arbitraria contra la base de datos SQLite en memoria. Reinicia el estado del constructor tras la ejecución.

```elscriptsignature
UserMeta.query(string:sql): array
```

#### Ejemplos

Usuario actual:

```elscript
Users.current
  .meta
  .where('meta_key', 'like', 'billing_%')
  .query('SELECT umeta_id, meta_key, meta_value FROM wp_usermeta ORDER BY umeta_id ASC')
```

Usuario específico:

```elscript
Users.get(2)
  .meta
  .where('meta_key', 'like', 'billing_%')
  .query('SELECT umeta_id, meta_key, meta_value FROM wp_usermeta ORDER BY umeta_id ASC')
```

:::info Extensión SQLite3 Requerida
La extensión de PHP SQLite3 **debe** estar instalada y habilitada en tu servidor para que `UserMeta.query()` funcione.
:::

---

### UserMeta.count()

Cuenta los registros de metadatos coincidentes para el usuario en MySQL sin almacenar filas en memoria ni inicializar una tabla SQLite. Aplica la delimitación de `user_id`. Reinicia el estado del constructor tras la ejecución.

```elscriptsignature
UserMeta.count(): int
```

#### Ejemplos

Usuario actual:

```elscript
/* Contar todas las entradas de metadatos para el usuario activo */
Users.current.meta.count()
```

Usuario específico:

```elscript
/* Contar entradas de metadatos personalizados que coincidan con un prefijo */
Users.get(2).meta.where('meta_key', 'like', 'billing_%').count()
```

---

### UserMeta.to_sql()

Compila y devuelve la cadena de consulta SQL para la consulta de metadatos del usuario sin ejecutarla. Aplica la delimitación `user_id = User.ID`. Reinicia el estado del constructor tras la compilación.

```elscriptsignature
UserMeta.to_sql(bool:is_count = false): string
```

#### Parámetros

* **`is_count`** (`bool`, _opcional_): Cuando es `true`, compila una consulta `SELECT COUNT(*)` en lugar de una proyección de columnas. El valor predeterminado es `false`.

#### Ejemplos

Usuario actual:

```elscript
/* Previsualizar SQL de consulta de metadatos */
Users.current.meta.where('meta_key', 'like', 'billing_%').order_by('umeta_id', 'DESC').to_sql()
```

Usuario específico:

```elscript
/* Previsualizar SQL de conteo de metadatos */
Users.get(2).meta.where('meta_key', 'like', 'billing_%').to_sql(true)
```
