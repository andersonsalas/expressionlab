---
id: ip-lookup
title: IP Lookup
sidebar_position: 9
---

# IP Lookup

Resolve IPv4 and IPv6 addresses to ISO 3166-1 alpha-2 country codes using a local MaxMind GeoLite2 binary database.

---

:::info
The **IP Lookup** service is available starting from version `v0.0.3-alpha`.
:::

Requires a GeoLite2 database created by MaxMind, available from [https://www.maxmind.com](https://www.maxmind.com). MaxMind and GeoLite2 are registered trademarks of MaxMind, Inc.

---

## Operational Model & Data Storage

The `IPLookup` service performs local, offline binary lookups against a MaxMind DB (`.mmdb`) file stored on the server filesystem. No outbound network requests are dispatched during expression evaluation.

* **Database Location**: By default, the database is stored at `wp-content/expressionlab/{hash}-GeoLite2-Country.mmdb`, where `{hash}` is a deterministic 16-character HMAC derived from your installation secret keys.
* **Custom Path**: Define `EXPRESSION_LAB_MAXMIND_PATH` in `wp-config.php` to supply an alternative path confined within `WP_CONTENT_DIR`. The file must have a `.mmdb` extension.
* **Update Policy**: MaxMind policies require updating GeoLite2 databases within 30 days of release.
* **Reserved and Private Addresses**: Loopback (`127.0.0.1`, `::1`), private ranges (RFC 1918), and link-local addresses contain no geographic allocation and evaluate to `null`.

---

## Database Management via WP-CLI

Database acquisition, updates, and maintenance are handled through [WP-CLI](https://make.wordpress.org/cli/handbook/).

### Downloading or Updating the Database

Download or refresh the local GeoLite2 Country database:

```bash
wp expressionlab iplookup update --license-key=MAXMIND_LICENSE_KEY
```

To avoid passing the license key as a flag in each execution, define the constant in `wp-config.php`:

```php
define( 'EXPRESSION_LAB_MAXMIND_API_KEY', 'maxmind_license_key' );
```

With the constant defined, run:

```bash
wp expressionlab iplookup update
```

Updates are skipped if the database was refreshed within the last 24 hours. Pass `--force` to bypass this check:

```bash
wp expressionlab iplookup update --force
```

### Inspecting Database Status

Display current database status, file path, size, modification date, and build epoch:

```bash
wp expressionlab iplookup status
```

Output includes warnings if the database file is missing or if its build date exceeds the 30-day compliance window.

### Removing the Database

Delete the local database file:

```bash
wp expressionlab iplookup delete
```

Pass `--yes` to skip the interactive confirmation prompt:

```bash
wp expressionlab iplookup delete --yes
```

---

## Methods

### IPLookup.to_country()

Resolves an IPv4 or IPv6 address to its two-letter ISO 3166-1 alpha-2 country code.

```elscriptsignature
IPLookup.to_country(
  string:ip
): string|null
```

#### Parameters

* **`ip`** (`string`, _required_): Valid IPv4 or IPv6 address string.

#### Return Value

Returns an uppercase two-letter string representing the ISO 3166-1 alpha-2 country code (such as `'US'`, `'ES'`, `'DE'`), or `null` if the IP address resides within a private, reserved, or unallocated range.

#### Error Handling

* Throws `\InvalidArgumentException` if the input string is not a syntactically valid IPv4 or IPv6 address.
* Throws `\RuntimeException` if the GeoLite2 database file is missing or unreadable. Run `wp expressionlab iplookup update` to install the database.

#### Examples

```elscript
/* Resolve a public IPv4 address to its country code */
IPLookup.to_country('8.8.8.8')
```

```elscript
/* Resolve a public IPv6 address */
IPLookup.to_country('2001:4860:4860::8888')
```

```elscript
/* Private or reserved IP addresses return null */
IPLookup.to_country('192.168.1.1')
```

```elscript
/* Localhost loopback returns null */
IPLookup.to_country('127.0.0.1')
```
