---
id: sites
title: Network Sites
sidebar_position: 3
---

# Network Sites

Discover, configure, and inspect sites across a WordPress Multisite network without writing manual `switch_to_blog()` or raw SQL queries.

:::note Multisite Installations Only
The **NetworkSites** service is exclusively functional within WordPress Multisite environments. In single-site installations, `NetworkSites.current` evaluates to `null`, and attempting to call methods such as `get()`, `list()`, or `build()` will throw an exception indicating that network features require multisite mode.
:::

Managing multisite networks involves three main components:

1. **NetworkSites Service (`NetworkSites`)**: The main entrypoint for discovering, querying, listing, and building site instances.
2. **Site Model (`Site`)**: An entity representing an individual site in the network with properties, configuration setters, user membership management, and persistence methods (`save()`, `delete()`).
3. **SiteOptions Model (`SiteOptions`)**: Accessible via `site.options`, providing options CRUD operations with format compatibility checks and autoload distribution charts scoped to that specific subsite.

---

## Accessing and Listing Sites

### NetworkSites.current

A property that returns the `Site` model instance corresponding to the active site context where the Expression Lab console is executing.

```elscriptsignature
NetworkSites.current: ?Site
```

#### Example

```elscript
/* Inspect current site */
NetworkSites.current
```

---

### NetworkSites.get()

Resolves and returns a `Site` model instance by its numeric blog ID or by its domain and path combination.

```elscriptsignature
NetworkSites.get(
  int|string:id_or_path
): Site
```

#### Resolution Modes

* **By Numeric ID** (Integer or Numeric String):
  Pass a numeric value (e.g., `1`, `2`, or `'2'`) to look up the site by its `blog_id`.
* **By Domain & Path** (String):
  Pass a domain or relative path (e.g., `'sub.example.com'`, `'sub.example.com/site/'`, or `'subsite'`). The service automatically normalizes protocols (`http://`, `https://`) and path slashes to find matching records.

If no matching site is found in the network, an exception is thrown.

#### Examples

```elscript
/* Look up site by numeric ID */
NetworkSites.get(2)
```

```elscript
/* Look up site by domain and sub-path */
NetworkSites.get('example.com/client-a/')
```

```elscript
/* Look up subdomain site */
NetworkSites.get('shop.example.com')
```

---

### NetworkSites.list()

Queries and returns a paginated list of sites registered across the network, rendering an interactive visualization table in the Expression Lab console.

```elscriptsignature
NetworkSites.list(
  ?int:limit = 100,
  int:offset = 0
): array
```

#### Parameters

* **`limit`** (`?int`, _optional_): The maximum number of site records to retrieve. Capped at a safety threshold of `1000` to prevent memory exhaustion on massive networks. Defaults to `100`.
* **`offset`** (`int`, _optional_): Number of site records to skip for pagination. Defaults to `0`.

#### Returned Fields

Each site item in the returned array contains:

| Field | Type | Description |
| :--- | :--- | :--- |
| `blog_id` | `int` | Unique blog ID. |
| `site_id` | `int` | Network ID (typically `1`). |
| `domain` | `string` | Domain name without protocol. |
| `path` | `string` | Subsite path with leading and trailing slashes (e.g., `/subsite/`). |
| `registered` | `string` | Timestamp when the site was created. |
| `last_updated` | `string` | Timestamp of the last site record update. |
| `public` | `int` | Search engine indexing flag (`1` for public, `0` for private). |
| `archived` | `int` | Archive status flag (`1` if archived). |
| `mature` | `int` | Mature content flag (`1` if marked mature). |
| `spam` | `int` | Spam flag (`1` if flagged as spam). |
| `deleted` | `int` | Deletion flag (`1` if marked deleted). |
| `lang_id` | `int` | Language identifier associated with the site. |

#### Example

```elscript
/* List first 20 sites in the network */
NetworkSites.list(20, 0)
```

---

### NetworkSites.build()

Instantiates a new, unsaved `Site` model instance pre-configured for the current network (`site_id`).

```elscriptsignature
NetworkSites.build(): Site
```

This factory method allows fluent configuration before saving the new site into the database.

#### Example

```elscript
/* Build a new site instance */
NetworkSites.build()
  .set_domain('example.com')
  .set_path('/portal/')
  .set_public(1)
```

:::warning Unsaved Site Options
Attempting to access `site.options` on an unsaved site instance will throw an exception: `Cannot access site options on an unsaved site instance. Call save() first.`. You must call `save()` to provision the site before interacting with its options table.
:::

---

## Site Properties

Instances of the `Site` model expose properties describing the subsite's state, configuration, and options:

### Site.blog_id

Unique numeric identifier (`blog_id`) for the subsite. Mapped from `WP_Site::$blog_id`.

```elscriptsignature
Site.blog_id: ?int
```

* **Type**: `?int` (Read-only)
* **Description**: Returns the numeric blog ID if the site exists in the network database. Evaluates to `null` if the instance was newly created via `NetworkSites.build()` and has not yet been saved.

#### Examples

Current site:

```elscript
NetworkSites.current.blog_id
```

Specific site:

```elscript
NetworkSites.get(2).blog_id
```

---

### Site.site_id

The network (multisite installation) identifier where this site resides.

```elscriptsignature
Site.site_id: int
```

* **Type**: `int` (Read-only)
* **Description**: Mapped from `WP_Site::$site_id`. Typically `1` on standard WordPress multisite installations.

#### Examples

Current site:

```elscript
NetworkSites.current.site_id
```

Specific site:

```elscript
NetworkSites.get(2).site_id
```

---

### Site.domain

The domain name of the subsite.

```elscriptsignature
Site.domain: string
```

* **Type**: `string`
* **Description**: Domain name without protocol (e.g. `'example.com'` or `'sub.example.com'`).

#### Examples

Current site:

```elscript
NetworkSites.current.domain
```

Specific site:

```elscript
NetworkSites.get(2).domain
```

---

### Site.path

The relative path and directory structure of the subsite.

```elscriptsignature
Site.path: string
```

* **Type**: `string`
* **Description**: Subsite path with leading and trailing slashes (e.g. `'/'`, `'/portal/'`, `'/client-a/'`).

#### Examples

Current site:

```elscript
NetworkSites.current.path
```

Specific site:

```elscript
NetworkSites.get(2).path
```

---

### Site.registered

The date and time when the subsite was created.

```elscriptsignature
Site.registered: string
```

* **Type**: `string` (Read-only)
* **Description**: MySQL timestamp string formatted as `YYYY-MM-DD HH:MM:SS`.

#### Examples

Current site:

```elscript
NetworkSites.current.registered
```

Specific site:

```elscript
NetworkSites.get(2).registered
```

---

### Site.last_updated

The date and time when the subsite record was last modified.

```elscriptsignature
Site.last_updated: string
```

* **Type**: `string` (Read-only)
* **Description**: MySQL timestamp string formatted as `YYYY-MM-DD HH:MM:SS`.

#### Examples

Current site:

```elscript
NetworkSites.current.last_updated
```

Specific site:

```elscript
NetworkSites.get(2).last_updated
```

---

### Site.public

Search engine indexing visibility setting for the subsite.

```elscriptsignature
Site.public: int
```

* **Type**: `int`
* **Description**: Flag indicating public visibility. `1` indicates public indexing is enabled; `0` discourages search engines from indexing the site.

#### Examples

Current site:

```elscript
NetworkSites.current.public
```

Specific site:

```elscript
NetworkSites.get(2).public
```

---

### Site.archived

Archive status of the subsite.

```elscriptsignature
Site.archived: int
```

* **Type**: `int`
* **Description**: `1` if the subsite is marked as archived; `0` if active.

#### Examples

Current site:

```elscript
NetworkSites.current.archived
```

Specific site:

```elscript
NetworkSites.get(2).archived
```

---

### Site.mature

Flag indicating whether the subsite is marked as mature content.

```elscriptsignature
Site.mature: int
```

* **Type**: `int`
* **Description**: `1` if flagged as mature content; `0` if standard.

#### Examples

Current site:

```elscript
NetworkSites.current.mature
```

Specific site:

```elscript
NetworkSites.get(2).mature
```

---

### Site.spam

Flag indicating whether the subsite is marked as spam.

```elscriptsignature
Site.spam: int
```

* **Type**: `int`
* **Description**: `1` if the subsite is flagged as spam; `0` if not spam.

#### Examples

Current site:

```elscript
NetworkSites.current.spam
```

Specific site:

```elscript
NetworkSites.get(2).spam
```

---

### Site.deleted

Deletion and trash status of the subsite.

```elscriptsignature
Site.deleted: int
```

* **Type**: `int`
* **Description**: `1` if the subsite is marked as deleted/trashed; `0` if active.

#### Examples

Current site:

```elscript
NetworkSites.current.deleted
```

Specific site:

```elscript
NetworkSites.get(2).deleted
```

---

### Site.lang_id

Language identifier associated with the subsite record.

```elscriptsignature
Site.lang_id: int
```

* **Type**: `int`
* **Description**: Language ID number stored in the WordPress network database.

#### Examples

Current site:

```elscript
NetworkSites.current.lang_id
```

Specific site:

```elscript
NetworkSites.get(2).lang_id
```

---

### Site.options

Isolated `SiteOptions` repository instance scoped to this subsite.

```elscriptsignature
Site.options: SiteOptions
```

* **Type**: `SiteOptions` (Read-only)
* **Description**: Provides access to options CRUD operations (`get`, `get_raw`, `update`, `update_raw`, `delete`) and autoload distribution analysis (`stats`) scoped to the subsite's `wp_{blog_id}_options` table without requiring `switch_to_blog()`.

:::warning Unsaved Site Instances
Attempting to access `site.options` on an unsaved site instance (where `blog_id` is `null`) will throw an exception: `Cannot access site options on an unsaved site instance. Call save() first.`. You must call `save()` to provision the site before interacting with its options repository.
:::

#### Examples

Current site:

```elscript
NetworkSites.current.options.get('blogname')
```

Specific site:

```elscript
NetworkSites.get(2).options.get('blogname')
```

---

## Site Configuration & Setters

The `Site` model provides fluent, chainable setter methods for configuring site attributes before calling `save()` or updating an existing site record. Each setter validates and normalizes its input, returning `$this` (`Site`):

### Site.set_domain()

Sets and normalizes the domain for the site.

```elscriptsignature
Site.set_domain(
  string:domain
): Site
```

#### Parameters

* **`domain`** (`string`, _required_): The domain to assign. Protocols (`http://`, `https://`) and trailing slashes are automatically stripped and sanitized.

#### Return Value

Returns the current `Site` instance for method chaining.

#### Examples

Current site:

```elscript
NetworkSites.current.set_domain('portal.example.com')
```

Specific site:

```elscript
NetworkSites.get(2).set_domain('portal.example.com')
```

---

### Site.set_path()

Sets and normalizes the sub-path for the site.

```elscriptsignature
Site.set_path(
  string:path
): Site
```

#### Parameters

* **`path`** (`string`, _required_): The sub-path to assign. Automatically normalizes leading and trailing slashes (e.g., `'portal'` becomes `'/portal/'`).

#### Return Value

Returns the current `Site` instance for method chaining.

#### Examples

Current site:

```elscript
NetworkSites.current.set_path('/portal/')
```

Specific site:

```elscript
NetworkSites.get(2).set_path('/portal/')
```

---

### Site.set_public()

Sets the search engine visibility and public indexing flag.

```elscriptsignature
Site.set_public(
  int:public
): Site
```

#### Parameters

* **`public`** (`int`, _required_): `1` for public indexing allowed, `0` for private.

#### Return Value

Returns the current `Site` instance for method chaining.

#### Examples

Current site:

```elscript
NetworkSites.current.set_public(1)
```

Specific site:

```elscript
NetworkSites.get(2).set_public(1)
```

---

### Site.set_archived()

Sets the archive status flag for the site.

```elscriptsignature
Site.set_archived(
  int:archived
): Site
```

#### Parameters

* **`archived`** (`int`, _required_): `1` to mark the site as archived, `0` for active.

#### Return Value

Returns the current `Site` instance for method chaining.

#### Examples

Current site:

```elscript
NetworkSites.current.set_archived(0)
```

Specific site:

```elscript
NetworkSites.get(2).set_archived(0)
```

---

### Site.set_mature()

Sets the mature content flag for the site.

```elscriptsignature
Site.set_mature(
  int:mature
): Site
```

#### Parameters

* **`mature`** (`int`, _required_): `1` to flag as mature content, `0` for standard.

#### Return Value

Returns the current `Site` instance for method chaining.

#### Examples

Current site:

```elscript
NetworkSites.current.set_mature(0)
```

Specific site:

```elscript
NetworkSites.get(2).set_mature(0)
```

---

### Site.set_spam()

Sets the spam flag for the site.

```elscriptsignature
Site.set_spam(
  int:spam
): Site
```

#### Parameters

* **`spam`** (`int`, _required_): `1` to flag as spam, `0` for not spam.

#### Return Value

Returns the current `Site` instance for method chaining.

#### Examples

Current site:

```elscript
NetworkSites.current.set_spam(0)
```

Specific site:

```elscript
NetworkSites.get(2).set_spam(0)
```

---

### Site.set_deleted()

Sets the deleted/trash flag for the site.

```elscriptsignature
Site.set_deleted(
  int:deleted
): Site
```

#### Parameters

* **`deleted`** (`int`, _required_): `1` to mark the site as deleted, `0` for active.

#### Return Value

Returns the current `Site` instance for method chaining.

#### Examples

Current site:

```elscript
NetworkSites.current.set_deleted(0)
```

Specific site:

```elscript
NetworkSites.get(2).set_deleted(0)
```

---

### Site.set_lang_id()

Sets the language identifier for the site.

```elscriptsignature
Site.set_lang_id(
  int:lang_id
): Site
```

#### Parameters

* **`lang_id`** (`int`, _required_): The language identifier number.

#### Return Value

Returns the current `Site` instance for method chaining.

#### Examples

Current site:

```elscript
NetworkSites.current.set_lang_id(1)
```

Specific site:

```elscript
NetworkSites.get(2).set_lang_id(1)
```

---

## Site Lifecycle & User Management

### Site.save()

Persists the site to the WordPress database.

```elscriptsignature
Site.save(): Site
```

* **Creating a New Site**: If `blog_id` is null, `save()` executes `wp_insert_site()`, generates all core database tables for the subsite, populates `blog_id`, initializes the `site.options` repository, and returns the hydrated `Site` instance.
* **Updating an Existing Site**: If `blog_id` is present, `save()` updates the site record in `wp_blogs` / `wp_site` via `wp_update_site()`.

#### Examples

Persist updates on current site:

```elscript
NetworkSites.current
  .set_public(1)
  .save()
```

Provision a new subsite:

```elscript
NetworkSites.build()
  /* Create and provision a new subsite */
  .set_domain('example.com')
  .set_path('/staging/')
  .set_public(0)
  .save()
  /* Immediately configure initial options */
  .options.update('blogname', 'Staging Portal')
```

---

### Site.delete()

Deletes the site from the network using WordPress Core's `wp_delete_site()`.

```elscriptsignature
Site.delete(): bool
```

:::warning Irreversible Operation
Deleting a site removes its records and tables permanently. Always double-check the targeted `blog_id`!
:::

#### Examples

Specific site by ID:

```elscript
/* Delete site with ID 5 and drop all its database tables */
NetworkSites.get(5).delete()
```

Specific site by domain & path:

```elscript
NetworkSites.get('example.com/demo/').delete()
```

---

### Site.list_users()

Returns an array of `User` model instances representing the user accounts assigned to this specific subsite.

```elscriptsignature
Site.list_users(): User[]
```

If called on an unsaved `Site` instance (`blog_id` is `null`), it returns an empty array `[]`.

#### Examples

Current site:

```elscript
NetworkSites.current.list_users()
```

Specific site:

```elscript
NetworkSites.get(2).list_users()
```

---

### Site.add_user()

Assigns a WordPress user account to this site with the specified role.

```elscriptsignature
Site.add_user(
  User:user,
  string:role = 'subscriber'
): bool
```

#### Parameters

* **`user`** (`User`): A `User` model instance to add to the site (retrieved via `Users.get()`).
* **`role`** (`string`, _optional_): The role to assign to the user on this site (e.g. `'subscriber'`, `'author'`, `'editor'`, `'administrator'`). Defaults to `'subscriber'`.

#### Return Value

Returns `true` on success, or `false` on failure.

#### Examples

Current site:

```elscript
NetworkSites.current.add_user( Users.get('anderson'), 'editor' )
```

Specific site:

```elscript
NetworkSites.get(2).add_user( Users.get('anderson'), 'editor' )
```

---

### Site.remove_user()

Removes a user's association and roles from this specific subsite. The user account itself remains intact globally across the network.

```elscriptsignature
Site.remove_user(
  User:user
): bool
```

#### Parameters

* **`user`** (`User`): The `User` model instance to remove from the site.

#### Return Value

Returns `true` on success, or `false` on failure.

#### Examples

Current site:

```elscript
NetworkSites.current.remove_user( Users.get('anderson') )
```

Specific site:

```elscript
NetworkSites.get(2).remove_user( Users.get('anderson') )
```

---

## Managing Site Options

Every `Site` model instance provides a dedicated `Site.options` repository (`SiteOptions`), allowing options CRUD operations and autoload analysis scoped to that subsite's `wp_{blog_id}_options` table without requiring manual `switch_to_blog()` calls.

### Site.options.get()

Reads and decodes or unserializes an option from the subsite's options table.

```elscriptsignature
Site.options.get(
  string:key,
  mixed:default = null
): mixed
```

#### Examples

Current site:

```elscript
NetworkSites.current.options.get('blogname')
```

Specific site:

```elscript
NetworkSites.get(2).options.get('blogname')
```

---

### Site.options.get_raw()

Retrieves the unmodified raw string payload stored in the subsite's `option_value` column.

```elscriptsignature
Site.options.get_raw(
  string:key,
  mixed:default = null
): ?string
```

#### Examples

Current site:

```elscript
NetworkSites.current.options.get_raw('rewrite_rules')
```

Specific site:

```elscript
NetworkSites.get(2).options.get_raw('rewrite_rules')
```

---

### Site.options.update()

Safely updates or creates an option on the subsite with format conflict protections (preventing accidental corruption between PHP serialized and JSON data).

```elscriptsignature
Site.options.update(
  string:key,
  mixed:value,
  string:serialization_type = Options.FORMAT_SERIALIZED
): bool
```

#### Examples

Current site:

```elscript
NetworkSites.current.options.update('custom_theme_mods', { dark_mode: true, brand_color: '#2271b1' })
```

Specific site:

```elscript
NetworkSites.get(2).options.update('custom_theme_mods', { dark_mode: true, brand_color: '#2271b1' })
```

---

### Site.options.update_raw()

Writes a raw string value to the subsite's options table and purges the object cache for that site.

```elscriptsignature
Site.options.update_raw(
  string:key,
  string:value,
  string|bool|null:autoload = null
): bool
```

#### Examples

Current site:

```elscript
NetworkSites.current.options.update_raw('custom_value', 'Custom!')
```

Specific site:

```elscript
NetworkSites.get(2).options.update_raw('custom_value', 'Custom!')
```

---

### Site.options.delete()

Deletes an option from the subsite and purges its cache.

```elscriptsignature
Site.options.delete(
  string:key
): bool
```

#### Examples

Current site:

```elscript
NetworkSites.current.options.delete('custom_value')
```

Specific site:

```elscript
NetworkSites.get(2).options.delete('custom_value')
```

---

### Site.options.stats()

Runs a memory-optimized forensic analysis on the subsite's `wp_{blog_id}_options` table, rendering interactive Vega-Lite charts and tabular breakdowns of option prefixes and autoload footprints.

```elscriptsignature
site.options.stats(
  ?int:sample_limit = 100000,
  int:graph_limit = 20
): array
```

#### Examples

Current site:

```elscript
NetworkSites.current.options.stats()
```

Specific site:

```elscript
NetworkSites.get(3).options.stats()
```