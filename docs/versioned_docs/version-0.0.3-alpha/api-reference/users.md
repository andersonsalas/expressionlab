---
id: users
title: Users
sidebar_position: 4
---

# Users

Search, inspect, and manage WordPress user accounts, roles, capabilities, and user metadata across single sites and multisite networks.

The user management architecture consists of three interconnected components:

1. **Users Service (`Users`)**: The primary entrypoint for user lookups by ID, email, or username, query builder chaining, and instantiating new user models.
2. **User Model (`User`)**: An entity representing an individual WordPress user, exposing core properties (`ID`, `data`, `roles`, `caps`, `allcaps`), contextual capability evaluation (`can()`), configuration setters, and lifecycle persistence methods (`save()`, `delete()`).
3. **UserMeta Model (`UserMeta`)**: A metadata repository accessible via `user.meta` that provides metadata CRUD operations with format compatibility checks (PHP serialization vs. JSON) and query builder integration scoped to a specific user.

---

## Accessing and Retrieving Users

### Users.current

A property that returns the `User` model instance corresponding to the user context currently selected in the Expression Lab interface.

```elscriptsignature
Users.current: ?User
```

* **Type**: `?User` (Read-only)
* **Description**: Evaluates to the active user model instance based on the current execution context. Returns `null` if no user context is resolved.

#### Example

```elscript
/* Inspect the active user model */
Users.current
```

---

### Users.get()

Retrieves a single user instance by identifier (ID, email, or username), or executes accumulated query builder conditions when called without arguments.

```elscriptsignature
Users.get(
  int|string|null:identifier = null
): User|array|null
```

#### Resolution Modes

* **By Numeric ID** (`int` or numeric scalar):
  Queries via `get_userdata(absint($identifier))`. Returns a `User` instance or `null` if not found.
* **By Email Address** (`string` matching `is_email`):
  Queries via `get_user_by('email', $identifier)`. Returns a `User` instance or `null` if not found.
* **By Username / Login** (`string`):
  Queries via `get_user_by('login', $identifier)`. Returns a `User` instance or `null` if not found.
* **Without Arguments** (`null`):
  Executes the accumulated `QueryBuilder` conditions and returns an array of matching user objects from SQLite.

#### Examples

```elscript
/* Look up user by numeric ID */
Users.get(2)
```

```elscript
/* Look up user by email address */
Users.get('admin@example.com')
```

```elscript
/* Look up user by login handle */
Users.get('editor_user')
```

---

### Users.list()

Queries and mirrors user records from the `wp_users` table into SQLite using filter conditions and options, then flushes the buffer and returns matching rows as an array of objects.

```elscriptsignature
Users.list(
  array|object:where = [],
  array|object:options = []
): array
```

#### Parameters

* **`where`** (`array|object`, _optional_): Associative array or object defining filter conditions passed to the database mirroring layer. Default is `[]`.
* **`options`** (`array|object`, _optional_): Configuration options including `orderby`, `limit`, `offset`, and column selection. Default is `[]`.

#### Examples

```elscript
/* Retrieve all users */
Users.list()
```

```elscript
/* Retrieve active users sorted by registration date */
Users.list({ user_status: 0 }, { orderby: { user_registered: 'DESC' }, limit: 20 })
```

---

### Users.build()

Factory method that instantiates a new, unsaved `User` model instance.

```elscriptsignature
Users.build(): User
```

The returned `User` model is initialized with an unassigned `ID` (`null`) and an empty `data` object, ready for property assignment via setter methods before persistence with `save()`.

#### Example

```elscript
/* Instantiate and configure a new user model */
Users.build()
  .set_user_login('editor')
  .set_user_email('editor@example.com')
  .set_role('editor')
  .save()
```

---

## User query builder

The `Users` service incorporates chainable query builder methods. Conditions are accumulated into groups and compiled into parameterized SQL when executed via `get()` or `query()`. Mirrored data is placed into an in-memory SQLite table and discarded upon buffer flush.

### Users.where()

Appends a `WHERE` condition combined with `AND` logic within the current condition group.

```elscriptsignature
Users.where(
  string:column,
  mixed:operator_or_value,
  mixed:value = null
): Users
```

#### Call Signatures

* **Equality** (Two arguments): `Users.where('column', 'value')`
* **IN condition** (Two arguments with array): `Users.where('column', ['val1', 'val2'])`
* **Comparison Operator** (Three arguments): `Users.where('column', 'operator', 'value')`

#### Supported Comparison Operators

The three-argument form `Users.where(column, operator, value)` accepts standard SQL-like comparison operators as strings:

| Operator | Description | Example |
| :--- | :--- | :--- |
| `'='` | Exact equality (default) | `Users.where('user_status', '=', 0).get()`, or simply `Users.where('user_status', 0).get()`  |
| `'>'` | Greater than | `Users.where('ID', '>', 10).get()` |
| `'>='` | Greater than or equal | `Users.where('ID', '>=', 10).get()` |
| `'<'` | Less than | `Users.where('ID', '<', 50).get()` |
| `'<='` | Less than or equal | `Users.where('ID', '<=', 50).get()` |
| `'!='`, `'<>'` | Not equal | `Users.where('user_status', '!=', 1).get()` |
| `'like'` | SQL LIKE pattern matching | `Users.where('user_email', 'like', '%@example.com').get()` |
| `'not like'` | Negated SQL LIKE pattern matching | `Users.where('user_login', 'not like', 'test_%').get()` |
| `'in'` | Set membership (value must be an array) | `Users.where('user_login', 'in', ['admin', 'editor']).get()` |
| `'not in'` | Negated set membership (value must be an array) | `Users.where('ID', 'not in', [1, 2, 3]).get()` |
| `'between'` | Range check (value must be a two-element array `[min, max]`) | `Users.where('ID', 'between', [10, 50]).get()` |

:::note QueryBuilder vs Database.mirror Syntax
The `QueryBuilder` methods (`where()`, `or_where()`) accept standard SQL operator strings (`'>'`, `'like'`, `'between'`) and compile them internally into the `$`-prefixed recursive condition objects expected by `Database.mirror()`.

When using `Users.list(where, options)` or `Database.mirror(table, where, options)`, use the declarative object syntax documented in [Database](./database) (e.g., `{ ID: { '$gt': 10 } }`).
:::

#### Examples

```elscript
/* Simple equality filter */
Users.where('user_login', 'admin').get()
```

```elscript
/* Numeric comparison filter */
Users.where('ID', '>', 5).get()
```

```elscript
/* Pattern matching filter */
Users.where('user_email', 'like', '%@company.org').get()
```

---

### Users.or_where()

Appends a `WHERE` condition that initiates a new condition group combined with preceding groups via `OR` logic. Conditions added within each group using `where()` are combined via `AND`.

```elscriptsignature
Users.or_where(
  string:column,
  mixed:operator_or_value,
  mixed:value = null
): Users
```

#### Example

```elscript
/* Combined AND and OR condition groups */
Users.where('user_status', 0)
  .or_where('user_login', 'admin')
  .get()
```

---

### Users.order_by()

Specifies column sorting for query results. Can be chained multiple times to apply multi-column ordering.

```elscriptsignature
Users.order_by(
  string:column,
  string:direction = 'ASC'
): Users
```

#### Parameters

* **`column`** (`string`, _required_): Column name to sort by (e.g., `'ID'`, `'user_registered'`, `'user_login'`).
* **`direction`** (`string`, _optional_): Sorting direction (`'ASC'` or `'DESC'`). Defaults to `'ASC'`.

#### Example

```elscript
/* Sort by registration date descending */
Users.where('user_status', 0)
  .order_by('user_registered', 'DESC')
  .get()
```

---

### Users.limit()

Sets the maximum number of user records to return.

```elscriptsignature
Users.limit(int:limit): Users
```

#### Example

```elscript
/* Retrieve first 10 matching users */
Users.where('user_status', 0)
  .order_by('ID', 'ASC')
  .limit(10)
  .get()
```

---

### Users.offset()

Sets the number of rows to skip for pagination.

```elscriptsignature
Users.offset(int:offset): Users
```

#### Example

```elscript
/* Paginate results: skip 20 rows and take 10 */
Users.where('user_status', 0)
  .order_by('ID', 'ASC')
  .offset(20)
  .limit(10)
  .get()
```

---

### Users.query()

Mirrors the `wp_users` table into SQLite using accumulated conditions and options, then executes an arbitrary SQL `SELECT` query against the in-memory SQLite database. Resets builder state after execution.

```elscriptsignature
Users.query(string:sql): array
```

#### Example

```elscript
/* Execute arbitrary SQL on mirrored SQLite table */
Users.where('user_status', 0)
  .query('SELECT ID, user_login, user_email FROM wp_users ORDER BY ID DESC')
```

:::info SQLite3 Extension Required
The SQLite3 PHP extension **must** be installed and enabled on your server for `Users.query()` to work.
:::

---

### Users.count()

Counts matching user records in the MySQL database without buffering rows into memory or initializing an SQLite table. Resets builder state after execution.

```elscriptsignature
Users.count(): int
```

#### Examples

```elscript
/* Count active users */
Users.where('user_status', 0).count()
```

```elscript
/* Count users matching an email domain */
Users.where('user_email', 'like', '%@example.com').count()
```

---

### Users.to_sql()

Compiles and returns the SQL query string for the accumulated conditions without executing it. Resets builder state after compilation.

```elscriptsignature
Users.to_sql(bool:is_count = false): string
```

#### Parameters

* **`is_count`** (`bool`, _optional_): When `true`, compiles a `SELECT COUNT(*)` query instead of a column projection. Defaults to `false`.

#### Examples

```elscript
/* Preview compiled SQL query */
Users.where('user_status', 0)
  .order_by('ID', 'DESC')
  .limit(25)
  .to_sql()
```

```elscript
/* Preview compiled count query */
Users.where('user_email', 'like', '%@company.org').to_sql(true)
```

---

## User Model Properties

The `User` model represents an individual WordPress user record.

### User.ID

The numeric primary key identifier of the user in the database.

```elscriptsignature
User.ID: ?int
```

* **Type**: `?int` (Read-only)
* **Description**: Returns the user database ID. Evaluates to `null` if the instance was newly created via `Users.build()` and has not yet been persisted to the database.

#### Examples

Current user:

```elscript
Users.current.ID
```

Specific user:

```elscript
Users.get(2).ID
```

---

### User.data

An object containing standard WordPress user columns mapped from `WP_User::to_array()`.

```elscriptsignature
User.data: object
```

* **Type**: `object` (`stdClass`)
* **Description**: Holds core user columns with normalized scalar types.

#### Field Reference

| Field | Type | Description |
| :--- | :--- | :--- |
| `ID` | `int` | Database user ID. |
| `user_login` | `string` | User login username. |
| `user_pass` | `string` | Password hash stored in the database. |
| `user_nicename` | `string` | URL-sanitized user slug. |
| `user_email` | `string` | User email address. |
| `user_url` | `string` | User website URL. |
| `user_registered` | `string` | Registration timestamp (`YYYY-MM-DD HH:MM:SS`). |
| `user_activation_key` | `string` | Password reset or activation key hash. |
| `user_status` | `int` | User status flag integer. |
| `display_name` | `string` | Publicly displayed name. |
| `spam` | `int` | Multisite spam flag (`1` if flagged as spam, `0` otherwise). |
| `deleted` | `int` | Multisite deletion flag (`1` if marked deleted, `0` otherwise). |

#### Examples

Current user:

```elscript
Users.current.data.user_login
```

Specific user:

```elscript
Users.get(2).data.user_login
```

---

### User.roles

An indexed array of role identifiers assigned to the user.

```elscriptsignature
User.roles: ?array
```

* **Type**: `?array` (Read-only)
* **Description**: List of roles assigned to the user (e.g., `['administrator']`, `['editor']`). Mapped from `WP_User::$roles`.

#### Examples

Current user:

```elscript
Users.current.roles
```

Specific user:

```elscript
Users.get(2).roles
```

---

### User.caps

An associative array of capability flags assigned to the user record.

```elscriptsignature
User.caps: ?array
```

* **Type**: `?array` (Read-only)
* **Description**: Key-value map representing individual capability assignments. Mapped from `WP_User::$caps`.

#### Examples

Current user:

```elscript
Users.current.caps
```

Specific user:

```elscript
Users.get(2).caps
```

---

### User.cap_key

The database meta key where capability records for this user are stored in `wp_usermeta`.

```elscriptsignature
User.cap_key: ?string
```

* **Type**: `?string` (Read-only)
* **Description**: Resolves to the site capability prefix (e.g., `'wp_capabilities'` or `'wp_2_capabilities'` on multisite sub-sites). Mapped from `WP_User::$cap_key`.

#### Examples

Current user:

```elscript
Users.current.cap_key
```

Specific user:

```elscript
Users.get(2).cap_key
```

---

### User.allcaps

An associative array of all effective capabilities granted to the user.

```elscriptsignature
User.allcaps: ?array
```

* **Type**: `?array` (Read-only)
* **Description**: Combined capability map, merging role-inherited permissions with explicit per-user capabilities. Mapped from `WP_User::$allcaps`.

#### Examples

Current user:

```elscript
Users.current.allcaps
```

Specific user:

```elscript
Users.get(2).allcaps
```

---

### User.filter

Context sanitization filter applied to the user object.

```elscriptsignature
User.filter: mixed
```

* **Type**: `mixed` (Read-only)
* **Description**: Sanitization filter context mapped from `WP_User::$filter` (typically `null` or `'raw'`).

#### Examples

Current user:

```elscript
Users.current.filter
```

Specific user:

```elscript
Users.get(2).filter
```

---

### User.meta

An isolated `UserMeta` repository instance scoped to this user.

```elscriptsignature
User.meta: UserMeta
```

* **Type**: `UserMeta` (Read-only)
* **Description**: Exposes metadata operations (`get`, `get_raw`, `update`, `update_raw`, `delete`, `has`, `list`) and query builder filtering targeting `wp_usermeta` for this specific user.

#### Examples

Current user:

```elscript
Users.current.meta.get('first_name')
```

Specific user:

```elscript
Users.get(2).meta.get('first_name')
```

---

## User Capability Management

### User.can()

Checks whether the user possesses a specific WordPress capability or primitive permission.

```elscriptsignature
User.can(
  string:capability,
  array:args = []
): bool
```

#### Parameters

* **`capability`** (`string`, _required_): The capability name to test (e.g., `'manage_options'`, `'edit_posts'`, `'publish_pages'`).
* **`args`** (`array`, _optional_): Additional contextual arguments passed to `WP_User::has_cap()` (e.g., a specific post ID `[42]`). Default is `[]`.

:::note Multisite Context Awareness
When evaluating expressions within a specific sub-site context on a multisite installation, `User.can()` switches blog context via `switch_to_blog()` to test site-specific roles and permissions, restoring the initial context via `restore_current_blog()` once evaluated.
:::

#### Return Value

Returns `true` if the user possesses the capability, `false` otherwise. Returns `false` if called on an uninitialized user instance.

#### Examples

Current user:

```elscript
Users.current.can('manage_options')
```

Specific user:

```elscript
Users.get(2).can('edit_post', [128])
```

---

### User.add_cap()

Assigns an individual native or custom capability to the user in `wp_usermeta` and updates effective permissions.

```elscriptsignature
User.add_cap(
  string:capability,
  bool:grant = true
): User
```

#### Parameters

* **`capability`** (`string`, _required_): The capability slug to grant (e.g., `'publish_posts'`, `'manage_custom_reports'`).
* **`grant`** (`bool`, _optional_): Whether the capability is granted (`true`) or denied (`false`). Defaults to `true`.

#### Return Value

Returns the current `User` instance for method chaining.

#### Examples

Current user:

```elscript
Users.current.add_cap('export_reports')
```

Specific user:

```elscript
Users.get(2).add_cap('edit_custom_posts')
```

---

### User.remove_cap()

Removes an individual capability from the user in `wp_usermeta` and updates effective permissions.

```elscriptsignature
User.remove_cap(string:capability): User
```

#### Parameters

* **`capability`** (`string`, _required_): The capability slug to remove from the user.

#### Return Value

Returns the current `User` instance for method chaining.

#### Examples

Current user:

```elscript
Users.current.remove_cap('export_reports')
```

Specific user:

```elscript
Users.get(2).remove_cap('edit_custom_posts')
```

---

## User Configuration and Setters

The `User` model exposes fluent setter methods to configure properties prior to persistence. Each setter returns the current instance (`User`) for method chaining.

### User.set_user_login()

Sets the login username for a new user instance.

```elscriptsignature
User.set_user_login(string:user_login): User
```

#### Parameters

* **`user_login`** (`string`, _required_): The login handle to assign.

:::note Immutable on Existing Users
In WordPress, `user_login` is immutable once a user account has been created. `set_user_login()` is exclusively functional when provisioning a new user via `Users.build()`. Calling it on an existing user (`Users.get(ID)` or `Users.current`) will not update the username in the database.
:::

#### Example

```elscript
Users.build()
  .set_user_login('author_account')
  .set_user_email('author@example.com')
  .set_role('author')
  .save()
```

---

### User.set_user_pass()

Sets the plaintext password for the user and marks the password state as changed.

```elscriptsignature
User.set_user_pass(string:user_pass): User
```

#### Parameters

* **`user_pass`** (`string`, _required_): The plaintext password. WordPress hashes this value during `save()`.

#### Examples

Current user:

```elscript
Users.current
  .set_user_pass('new_secret_password_here')
  .save()
```

Specific user:

```elscript
Users.get(2)
  .set_user_pass('new_secret_password_here')
  .save()
```

---

### User.set_user_nicename()

Sets the URL slug (nicename) for the user.

```elscriptsignature
User.set_user_nicename(string:user_nicename): User
```

#### Parameters

* **`user_nicename`** (`string`, _required_): The sanitized user slug.

#### Examples

Current user:

```elscript
Users.current
  .set_user_nicename('john-doe')
  .save()
```

Specific user:

```elscript
Users.get(2)
  .set_user_nicename('john-doe')
  .save()
```

---

### User.set_user_email()

Sets the email address for the user.

```elscriptsignature
User.set_user_email(string:user_email): User
```

#### Parameters

* **`user_email`** (`string`, _required_): The user email address.

#### Examples

Current user:

```elscript
Users.current
  .set_user_email('contact@example.com')
  .save()
```

Specific user:

```elscript
Users.get(2)
  .set_user_email('contact@example.com')
  .save()
```

---

### User.set_user_url()

Sets the website URL for the user.

```elscriptsignature
User.set_user_url(string:user_url): User
```

#### Parameters

* **`user_url`** (`string`, _required_): The website URL string.

#### Examples

Current user:

```elscript
Users.current
  .set_user_url('https://example.com')
  .save()
```

Specific user:

```elscript
Users.get(2)
  .set_user_url('https://example.com')
  .save()
```

---

### User.set_display_name()

Sets the public display name for the user.

```elscriptsignature
User.set_display_name(string:display_name): User
```

#### Parameters

* **`display_name`** (`string`, _required_): The public display name.

#### Examples

Current user:

```elscript
Users.current
  .set_display_name('John Doe')
  .save()
```

Specific user:

```elscript
Users.get(2)
  .set_display_name('John Doe')
  .save()
```

---

### User.set_role()

Sets the role to assign to the user upon saving.

```elscriptsignature
User.set_role(string:role): User
```

#### Parameters

* **`role`** (`string`, _required_): The WordPress role slug to assign (e.g., `'administrator'`, `'editor'`, `'author'`, `'contributor'`, `'subscriber'`).

#### Examples

Current user:

```elscript
Users.current
  .set_role('editor')
  .save()
```

Specific user:

```elscript
Users.get(2)
  .set_role('editor')
  .save()
```

---

## User Persistence and Lifecycle

### User.save()

Persists user model modifications or creates a new user in the WordPress database.

```elscriptsignature
User.save(): User|false
```

#### Behavior

* **Creating New Users (`ID` is `null`)**:
  Prepares an insert payload with `user_login`, `user_pass` (automatically generates a random password via `wp_generate_password()` if omitted), `user_nicename`, `user_email`, `user_url`, `display_name`, and assigned `role`. Calls `wp_insert_user()`. Returns a new `User` instance populated with the inserted ID and data on success, or `false` on failure.
* **Updating Existing Users (`ID` is populated)**:
  Updates existing columns with `wp_update_user()`. Only updates the password if `set_user_pass()` was called. Updates role if specified via `set_role()`. Returns the current `User` instance on success, or `false` on failure.

#### Examples

Current user:

```elscript
Users.current
  .set_display_name('Jane Doe')
  .set_user_email('jane.doe@example.com')
  .save()
```

Specific user:

```elscript
Users.get(2)
  .set_display_name('Jane Doe')
  .set_user_email('jane.doe@example.com')
  .save()
```

---

### User.delete()

Deletes the user from the WordPress database.

```elscriptsignature
User.delete(
  ?int:reassign = null
): bool
```

#### Parameters

* **`reassign`** (`?int`, _optional_): The user ID to reassign posts to upon deletion (single-site installations only). Defaults to `null` (deletes user posts).

#### Execution Mechanics

* **Single-Site**: Executes `wp_delete_user($this->ID, $reassign)`.
* **Multisite**: Executes `wpmu_delete_user($this->ID)`.

#### Return Value

Returns `true` on successful deletion, `false` if `ID` is `null` or the deletion operation fails.

:::note Administrator and Authenticated User Protection Guard
To prevent accidental admin lockouts and session corruption, `User.delete()` automatically rejects deleting the designated Expression Lab administrator (`EXPRESSION_LAB_ADMIN_USER_ID`) and the currently authenticated WordPress user.
:::

#### Examples

```elscript
/* Delete user and reassign posts to administrator (user ID 1) */
Users.get(15).delete(1)
```

```elscript
/* Delete user and permanently remove their content */
Users.get(15).delete()
```

---

## User Metadata Management

User metadata operations are accessed through the `meta` property on a `User` model instance (e.g., `Users.current.meta` or `Users.get(2).meta`).

### UserMeta.get()

Retrieves a metadata value for the user, automatically deserializing structured PHP payloads or decoding JSON strings. When called without arguments, executes the query builder against `wp_usermeta`.

```elscriptsignature
UserMeta.get(
  ?string:key = null,
  mixed:default_value = null
): mixed
```

#### Parameters

* **`key`** (`?string`, _optional_): The metadata key to retrieve. If `null`, executes accumulated query builder conditions on `wp_usermeta` for this user.
* **`default_value`** (`mixed`, _optional_): Value returned if the key does not exist. Defaults to `null`.

#### Deserialization & Format Detection

1. **Serialized PHP**: Processed through strict validation (`Helper::strict_unserialize`). Rejects unauthorized object classes.
2. **JSON Payloads**: Parsed via `json_decode` into associative structures.
3. **Scalar Strings**: Returned unmodified.
4. **Missing Keys**: Returns `default_value`.

#### Examples

Current user:

```elscript
Users.current
  .meta
  .get('first_name')
```

Specific user:

```elscript
Users.get(2)
  .meta
  .get('first_name')
```

---

### UserMeta.get_raw()

Retrieves the exact string stored in the `meta_value` column of `wp_usermeta` without executing PHP deserialization or JSON parsing.

```elscriptsignature
UserMeta.get_raw(
  string:key,
  mixed:default_value = null
): mixed
```

#### Parameters

* **`key`** (`string`, _required_): The metadata key name.
* **`default_value`** (`mixed`, _optional_): Fallback value if key is not found. Defaults to `null`.

#### Examples

Current user:

```elscript
Users.current
  .meta
  .get_raw('session_tokens')
```

Specific user:

```elscript
Users.get(2)
  .meta
  .get_raw('session_tokens')
```

---

### UserMeta.has()

Checks whether a specific metadata key exists for the user in the `wp_usermeta` table.

```elscriptsignature
UserMeta.has(string:key): bool
```

#### Parameters

* **`key`** (`string`, _required_): The metadata key to check.

#### Return Value

Returns `true` if at least one row exists for this user and key, `false` otherwise.

#### Examples

Current user:

```elscript
Users.current
  .meta
  .has('billing_address_1')
```

Specific user:

```elscript
Users.get(2)
  .meta
  .has('billing_address_1')
```

---

### UserMeta.list()

Mirrors and returns all metadata records associated with the user (`user_id = User.ID`) from `wp_usermeta`.

```elscriptsignature
UserMeta.list(): array
```

#### Return Value

Returns an array of objects representing all metadata rows for this user.

#### Examples

Current user:

```elscript
Users.current
  .meta
  .list()
```

Specific user:

```elscript
Users.get(2)
  .meta
  .list()
```

---

### UserMeta.update()

Updates an existing metadata entry or creates a new one for the user, enforcing format conflict checks and strict serialization validation.

```elscriptsignature
UserMeta.update(
  string:key,
  mixed:value,
  string:serialization_type = Options.FORMAT_SERIALIZED
): bool
```

#### Serialization Format Constants

| Constant | Value | Description |
| :--- | :--- | :--- |
| `Options.FORMAT_SERIALIZED` | `'serialized'` | Default. Encodes arrays and scalar structures using standard PHP serialization (`serialize`). |
| `Options.FORMAT_SERIALIZED_OBJECT` | `'serialized_object'` | Casts root array to `stdClass` before serializing. |
| `Options.FORMAT_JSON` | `'json'` | Encodes the value as JSON via `wp_json_encode`. |

#### Format Conflict Protections

* If the existing database value is PHP serialized, `UserMeta.update()` **rejects** updating it with `Options.FORMAT_JSON`.
* If the existing database value is JSON, `UserMeta.update()` **rejects** updating it with `Options.FORMAT_SERIALIZED`.
* To bypass format conflict protections intentionally, use `UserMeta.update_raw()`.

#### Examples

Current user:

```elscript
Users.current
  .meta
  .update('user_preferences', { compact_view: true, per_page: 25 })
```

Specific user:

```elscript
Users.get(2)
  .meta
  .update('user_preferences', { compact_view: true, per_page: 25 })
```

---

### UserMeta.update_raw()

Writes a raw string value to the `meta_value` column in `wp_usermeta` without serialization or JSON encoding.

```elscriptsignature
UserMeta.update_raw(
  string:key,
  string:value
): bool
```

#### Parameters

* **`key`** (`string`, _required_): The metadata key name.
* **`value`** (`string`, _required_): Raw string payload to store.

#### Examples

Current user:

```elscript
Users.current
  .meta
  .update_raw('custom_status_flag', 'verified_active')
```

Specific user:

```elscript
Users.get(2)
  .meta
  .update_raw('custom_status_flag', 'verified_active')
```

---

### UserMeta.delete()

Deletes a metadata key for the user from `wp_usermeta`.

```elscriptsignature
UserMeta.delete(string:key): bool
```

#### Parameters

* **`key`** (`string`, _required_): The metadata key name to delete.

#### Return Value

Returns `true` if deleted, `false` otherwise.

#### Examples

Current user:

```elscript
Users.current
  .meta
  .delete('temporary_login_token')
```

Specific user:

```elscript
Users.get(2)
  .meta
  .delete('temporary_login_token')
```

---

## Query Builder on User Metadata

The `UserMeta` model implements the `QueryBuilder` trait, allowing conditions to be chained on a user's metadata repository (`User.meta`) against the `wp_usermeta` table.

Mirrored metadata records are placed into an in-memory SQLite table and discarded upon buffer flush.

### UserMeta.where()

Appends a `WHERE` condition combined with `AND` logic within the current condition group on `wp_usermeta`.

```elscriptsignature
UserMeta.where(
  string:column,
  mixed:operator_or_value,
  mixed:value = null
): UserMeta
```

#### Call Signatures

* **Equality** (Two arguments): `user.meta.where('column', 'value')`
* **IN condition** (Two arguments with array): `user.meta.where('column', ['val1', 'val2'])`
* **Comparison Operator** (Three arguments): `user.meta.where('column', 'operator', 'value')`

#### Supported Comparison Operators

The three-argument form `User.meta.where(column, operator, value)` accepts standard SQL comparison operators:

| Operator | Description | Example |
| :--- | :--- | :--- |
| `'='` | Exact equality (default) | `Users.current.meta.where('meta_key', '=', 'first_name').get()`, or simply `Users.current.meta.where('meta_key', 'first_name').get()` |
| `'>'` | Greater than | `Users.current.meta.where('umeta_id', '>', 50).get()` |
| `'>='` | Greater than or equal | `Users.current.meta.where('umeta_id', '>=', 50).get()` |
| `'<'` | Less than | `Users.current.meta.where('umeta_id', '<', 200).get()` |
| `'<='` | Less than or equal | `Users.current.meta.where('umeta_id', '<=', 200).get()` |
| `'!='`, `'<>'` | Not equal | `Users.current.meta.where('meta_key', '!=', 'session_tokens').get()` |
| `'like'` | SQL LIKE pattern matching | `Users.current.meta.where('meta_key', 'like', 'wp_%').get()` |
| `'not like'` | Negated SQL LIKE pattern matching | `Users.current.meta.where('meta_key', 'not like', '_transient_%').get()` |
| `'in'` | Set membership (value must be an array) | `Users.current.meta.where('meta_key', 'in', ['first_name', 'last_name', 'nickname']).get()` |
| `'not in'` | Negated set membership (value must be an array) | `Users.current.meta.where('meta_key', 'not in', ['session_tokens', 'closedpostboxes_%']).get()` |
| `'between'` | Range check (value must be a two-element array `[min, max]`) | `Users.current.meta.where('umeta_id', 'between', [100, 500]).get()` |

#### Examples

Current user:

```elscript
Users.current
  .meta
  .where('meta_key', 'like', 'wp_%')
  .get()
```

Specific user:

```elscript
Users.get(2)
  .meta
  .where('meta_key', 'in', ['first_name', 'last_name', 'nickname'])
  .get()
```

---

### UserMeta.or_where()

Appends a `WHERE` condition that initiates a new condition group combined with preceding groups via `OR` logic. Conditions added within each group using `where()` are combined via `AND`.

```elscriptsignature
UserMeta.or_where(
  string:column,
  mixed:operator_or_value,
  mixed:value = null
): UserMeta
```

#### Examples

Current user:

```elscript
Users.current
  .meta
  .where('meta_key', 'first_name')
  .or_where('meta_key', 'last_name')
  .get()
```

Specific user:

```elscript
Users.get(2)
  .meta
  .where('meta_key', 'first_name')
  .or_where('meta_key', 'last_name')
  .get()
```

---

### UserMeta.order_by()

Specifies column sorting for mirrored metadata query results. Can be chained multiple times to apply multi-column ordering.

```elscriptsignature
UserMeta.order_by(
  string:column,
  string:direction = 'ASC'
): UserMeta
```

#### Parameters

* **`column`** (`string`, _required_): Column name to sort by (`'umeta_id'`, `'meta_key'`, `'meta_value'`).
* **`direction`** (`string`, _optional_): Sorting direction (`'ASC'` or `'DESC'`). Defaults to `'ASC'`.

#### Examples

Current user:

```elscript
Users.current
  .meta
  .where('meta_key', 'like', 'billing_%')
  .order_by('umeta_id', 'DESC')
  .get()
```

Specific user:

```elscript
Users.get(2)
  .meta
  .where('meta_key', 'like', 'billing_%')
  .order_by('umeta_id', 'DESC')
  .get()
```

---

### UserMeta.limit()

Sets the maximum number of metadata rows to return.

```elscriptsignature
UserMeta.limit(int:limit): UserMeta
```

#### Examples

Current user:

```elscript
Users.current
  .meta
  .where('meta_key', 'like', 'wp_%')
  .order_by('umeta_id', 'ASC')
  .limit(5)
  .get()
```

Specific user:

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

Sets the number of rows to skip for pagination across metadata rows.

```elscriptsignature
UserMeta.offset(int:offset): UserMeta
```

#### Examples

Current user:

```elscript
Users.current
  .meta
  .where('meta_key', 'like', 'wp_%')
  .order_by('umeta_id', 'ASC')
  .offset(10)
  .limit(5)
  .get()
```

Specific user:

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

Mirrors the `wp_usermeta` table into SQLite using accumulated conditions and options, then executes an arbitrary SQL `SELECT` query against the in-memory SQLite database. Resets builder state after execution.

```elscriptsignature
UserMeta.query(string:sql): array
```

#### Examples

Current user:

```elscript
Users.current
  .meta
  .where('meta_key', 'like', 'billing_%')
  .query('SELECT umeta_id, meta_key, meta_value FROM wp_usermeta ORDER BY umeta_id ASC')
```

Specific user:

```elscript
Users.get(2)
  .meta
  .where('meta_key', 'like', 'billing_%')
  .query('SELECT umeta_id, meta_key, meta_value FROM wp_usermeta ORDER BY umeta_id ASC')
```

:::info SQLite3 Extension Required
The SQLite3 PHP extension **must** be installed and enabled on your server for `UserMeta.query()` to work.
:::

---

### UserMeta.count()

Counts matching metadata records for the user in MySQL without buffering rows into memory or initializing an SQLite table. Automatically enforces `user_id` scoping. Resets builder state after execution.

```elscriptsignature
UserMeta.count(): int
```

#### Examples

Current user:

```elscript
/* Count all metadata entries for active user */
Users.current.meta.count()
```

Specific user:

```elscript
/* Count custom metadata entries matching a prefix */
Users.get(2).meta.where('meta_key', 'like', 'billing_%').count()
```

---

### UserMeta.to_sql()

Compiles and returns the SQL query string for the user's metadata query without executing it. Automatically enforces `user_id = User.ID` scoping. Resets builder state after compilation.

```elscriptsignature
UserMeta.to_sql(bool:is_count = false): string
```

#### Parameters

* **`is_count`** (`bool`, _optional_): When `true`, compiles a `SELECT COUNT(*)` query instead of a column projection. Defaults to `false`.

#### Examples

Current user:

```elscript
/* Preview metadata query SQL */
Users.current.meta.where('meta_key', 'like', 'billing_%').order_by('umeta_id', 'DESC').to_sql()
```

Specific user:

```elscript
/* Preview metadata count SQL */
Users.get(2).meta.where('meta_key', 'like', 'billing_%').to_sql(true)
```
