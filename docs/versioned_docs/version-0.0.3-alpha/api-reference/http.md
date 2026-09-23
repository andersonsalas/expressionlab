---
id: http
title: HTTP
sidebar_position: 8
---

# HTTP

Perform HTTP requests (`GET`, `POST`, `HEAD`) and test network latency from the console.

---

## Operational Limits & Defaults

Outgoing HTTP operations adhere to the following defaults:

* **Network Access**: Disabled by default. Set `define('EXPRESSION_LAB_NETWORK_READONLY', false);` in `wp-config.php` to enable network requests.
* **Supported Protocols**: Standard `http://` and `https://` schemes only.
* **Payload Limits**: Response bodies are capped at 1 MB (`Http.MAX_BODY_BYTES`). Automatic JSON parsing is limited to a nesting depth of 64 levels (`Http.MAX_JSON_DEPTH`).
* **Request Defaults**: 10-second timeout (`Http.DEFAULT_TIMEOUT`, 5 seconds for `ping()`), up to 5 redirects, and SSL verification enabled.

For security policies, address filtering, and network boundaries, refer to the [Security](../security/security-and-environment.md) guide.

---

## Response Structure

All request methods (`get`, `post`, `head`) return an associative array with the following normalized structure:

| Field | Type | Description |
| :--- | :--- | :--- |
| `status` | `int` | HTTP response status code (e.g., `200`, `201`, `400`, `404`, `500`). |
| `status_text` | `string` | HTTP status reason phrase (e.g., `'OK'`, `'Created'`, `'Not Found'`). |
| `headers` | `array` | Associative array of response headers returned by the remote server. |
| `body` | `string` | Raw response body string, truncated to `Http.MAX_BODY_BYTES` (1 MB) if the remote payload exceeds that size. |
| `json` | `mixed\|null` | Decoded JSON data structure (associative array, indexed array, or scalar) if the response body contains valid JSON; `null` if the body is empty, invalid, or exceeds `Http.MAX_JSON_DEPTH` (64 levels). |
| `duration_ms` | `float` | Roundtrip request duration in milliseconds, rounded to two decimal places. |
| `content_type` | `string` | The value of the response `Content-Type` header (empty string if omitted by the server). |

---

## HTTP Request Methods

### Http.get()

Performs an HTTP GET request to the specified URL.

```elscriptsignature
Http.get(
  string:url,
  array:headers = [],
  array:options = []
): array
```

#### Parameters

* **`url`** (`string`, _required_): Target HTTP or HTTPS URL.
* **`headers`** (`array`, _optional_): Associative array of custom request headers. Defaults to `[]`.
* **`options`** (`array`, _optional_): Additional arguments passed to the WordPress HTTP transport. Only keys defined in `Http.ALLOWED_OPTIONS` are forwarded; other keys are ignored. Defaults to `[]`.

#### Return Value

Returns the normalized response associative array containing `status`, `status_text`, `headers`, `body`, `json`, `duration_ms`, and `content_type`.

#### Error Handling

* Throws `\RuntimeException` if network operations are disabled via `EXPRESSION_LAB_NETWORK_READONLY`.
* Throws `\InvalidArgumentException` if the URL is invalid or not allowed.
* Throws `\RuntimeException` if a network transport failure occurs (e.g., DNS resolution failure, connection timeout, or SSL certificate mismatch).

#### Examples

```elscript
/* Execute a basic GET request */
Http.get('https://api.github.com/zen')
```

```elscript
/* Inspect the HTTP status code of the response */
Http.get('https://api.github.com/zen')['status']
```

```elscript
/* Retrieve and automatically decode a JSON payload */
Http.get('https://api.github.com/users/octocat')['json']
```

```elscript
/* Access a specific property from the parsed JSON response */
Http.get('https://api.github.com/users/octocat')['json']['public_repos']
```

```elscript
/* Send custom request headers */
Http.get('https://httpbin.org/headers', { 'Accept': 'application/json', 'Authorization': 'Bearer sample-token-abc' })
```

```elscript
/* Pass custom transport options such as a custom timeout and user agent */
Http.get('https://httpbin.org/delay/2', {}, { timeout: 20, 'user-agent': 'MyCustomApp/2.0' })
```

---

### Http.post()

Performs an HTTP POST request with an optional payload to the specified URL.

```elscriptsignature
Http.post(
  string:url,
  mixed:body = null,
  array:headers = [],
  array:options = []
): array
```

#### Parameters

* **`url`** (`string`, _required_): Target HTTP or HTTPS URL.
* **`body`** (`mixed`, _optional_): Request payload. Handled as follows:
  * **Array with JSON Content-Type**: If passed as an array and the `headers` include a `Content-Type` containing `'json'` (case-insensitive), the payload is automatically serialized using `wp_json_encode()`.
  * **Array without JSON Content-Type**: Formatted as form data (`application/x-www-form-urlencoded` or multipart) by the underlying HTTP client.
  * **Scalar / String**: Cast to string and sent directly as the request body.
  * **Null**: No request body is transmitted. Defaults to `null`.
* **`headers`** (`array`, _optional_): Associative array of custom request headers. Defaults to `[]`.
* **`options`** (`array`, _optional_): Additional arguments passed to the WordPress HTTP transport. Only keys defined in `Http.ALLOWED_OPTIONS` are forwarded; other keys are ignored. Defaults to `[]`.

#### Return Value

Returns the normalized response associative array containing `status`, `status_text`, `headers`, `body`, `json`, `duration_ms`, and `content_type`.

#### Error Handling

* Throws `\RuntimeException` if network operations are disabled via `EXPRESSION_LAB_NETWORK_READONLY`.
* Throws `\InvalidArgumentException` if the URL is invalid or not allowed.
* Throws `\RuntimeException` if a network transport failure occurs.

#### Examples

```elscript
/* Send a POST request with an automated JSON payload */
Http.post('https://httpbin.org/post', { action: 'sync', site_id: 1 }, { 'Content-Type': 'application/json' })
```

```elscript
/* Send a POST request with form URL-encoded body data */
Http.post('https://httpbin.org/post', { username: 'administrator', grant_type: 'password' })
```

```elscript
/* Send a raw text string payload */
Http.post('https://httpbin.org/post', 'raw-payload-string', { 'Content-Type': 'text/plain' })
```

```elscript
/* Inspect the parsed JSON response body from a POST request */
Http.post('https://httpbin.org/post', { event: 'ping' }, { 'Content-Type': 'application/json' })['json']
```

---

### Http.head()

Performs an HTTP HEAD request to retrieve response metadata and headers from a remote server without transferring the response body.

```elscriptsignature
Http.head(
  string:url,
  array:headers = [],
  array:options = []
): array
```

#### Parameters

* **`url`** (`string`, _required_): Target HTTP or HTTPS URL.
* **`headers`** (`array`, _optional_): Associative array of custom request headers. Defaults to `[]`.
* **`options`** (`array`, _optional_): Additional arguments passed to the WordPress HTTP transport. Only keys defined in `Http.ALLOWED_OPTIONS` are forwarded; other keys are ignored. Defaults to `[]`.

#### Return Value

Returns the normalized response associative array where `body` is empty, `json` is `null`, and `headers` contains the headers provided by the remote server.

#### Error Handling

* Throws `\RuntimeException` if network operations are disabled via `EXPRESSION_LAB_NETWORK_READONLY`.
* Throws `\InvalidArgumentException` if the URL is invalid or not allowed.
* Throws `\RuntimeException` if a network transport failure occurs.

#### Examples

```elscript
/* Perform a HEAD request to inspect server headers */
Http.head('https://wordpress.org')
```

```elscript
/* Retrieve response headers dictionary */
Http.head('https://wordpress.org')['headers']
```

```elscript
/* Inspect remote Content-Type header */
Http.head('https://wordpress.org')['content_type']
```

```elscript
/* Inspect specific response header */
Http.head('https://wordpress.org')['headers']['server']
```

---

## Diagnostics and Benchmarking

### Http.ping()

Performs a lightweight GET request with a 5-second timeout to check endpoint availability and measure roundtrip latency. When run in the console, it renders a diagnostic table.

```elscriptsignature
Http.ping(
  string:url
): array
```

#### Parameters

* **`url`** (`string`, _required_): Target HTTP or HTTPS URL to benchmark.

#### Visualizations Generated

When executed in Expression Lab, `Http.ping()` renders a visualization table (`Table: HTTP Ping Diagnostics`) with the following diagnostic metrics:

* **`Target URL`**: The URL evaluated during the test.
* **`HTTP Status`**: The combined status code and status text (e.g., `200 OK`).
* **`Latency (ms)`**: Total request duration formatted in milliseconds.
* **`Content Type`**: Content-Type header reported by the server.
* **`Payload Size`**: Byte count of the response body.

#### Return Value

Returns an associative array containing:

| Field | Type | Description |
| :--- | :--- | :--- |
| `url` | `string` | Target URL tested. |
| `status` | `int` | HTTP status code returned by the endpoint. |
| `latency_ms` | `float` | Roundtrip latency in milliseconds. |
| `is_healthy` | `bool` | `true` if the status code is between `200` and `399` (`2xx` or `3xx`), `false` otherwise (`4xx`, `5xx`). |

#### Error Handling

* Throws `\RuntimeException` if network operations are disabled via `EXPRESSION_LAB_NETWORK_READONLY`.
* Throws `\InvalidArgumentException` if the URL is invalid or not allowed.
* Throws `\RuntimeException` if a network transport failure or timeout occurs.

#### Examples

```elscript
/* Run network latency and availability benchmark */
Http.ping('https://wordpress.org')
```

```elscript
/* Inspect health check boolean flag */
Http.ping('https://wordpress.org')['is_healthy']
```

```elscript
/* Inspect latency in milliseconds */
Http.ping('https://api.github.com')['latency_ms']
```

---

## Properties and Constants

### Http.DEFAULT_TIMEOUT

Default request timeout in seconds (`10`).

```elscriptsignature
Http.DEFAULT_TIMEOUT: int
```

* **Type**: `int` (Read-only)
* **Value**: `10`

#### Example

```elscript
/* Inspect default HTTP request timeout */
Http.DEFAULT_TIMEOUT
```

---

### Http.MAX_BODY_BYTES

Maximum response body size in bytes (`1048576`, or 1 MB).

```elscriptsignature
Http.MAX_BODY_BYTES: int
```

* **Type**: `int` (Read-only)
* **Value**: `1048576`

#### Example

```elscript
/* Inspect maximum response body capture size */
Http.MAX_BODY_BYTES
```

---

### Http.MAX_JSON_DEPTH

Maximum nesting depth permitted during automatic JSON parsing (`64` levels).

```elscriptsignature
Http.MAX_JSON_DEPTH: int
```

* **Type**: `int` (Read-only)
* **Value**: `64`

#### Example

```elscript
/* Inspect maximum JSON decode nesting depth */
Http.MAX_JSON_DEPTH
```

---

### Http.ALLOWED_OPTIONS

Allowlist of accepted keys for the `$options` parameter in request methods.

```elscriptsignature
Http.ALLOWED_OPTIONS: array
```

* **Type**: `array` (Read-only)
* **Value**: `['timeout', 'httpversion', 'user-agent', 'headers', 'body', 'cookies', 'compress', 'decompress', 'blocking']`

#### Example

```elscript
/* Inspect allowed transport option keys */
Http.ALLOWED_OPTIONS
```
