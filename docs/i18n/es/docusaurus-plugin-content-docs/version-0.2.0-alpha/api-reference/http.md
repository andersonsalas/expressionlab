---
id: http
title: HTTP
sidebar_position: 8
---

# HTTP

Realiza peticiones HTTP (`GET`, `POST`, `HEAD`) y mide la latencia de red desde la consola.

---

## Límites operativos y valores predeterminados

Las operaciones HTTP salientes se rigen por los siguientes valores predeterminados:

* **Acceso a la red**: Desactivado de forma predeterminada. Define `define('EXPRESSION_LAB_NETWORK_READONLY', false);` en `wp-config.php` para habilitar las peticiones de red.
* **Protocolos admitidos**: Solo esquemas estándar `http://` o `https://`.
* **Límites de respuesta**: Los cuerpos de respuesta están limitados a 1 MB (`Http.MAX_BODY_BYTES`). La decodificación de JSON admite una profundidad máxima de anidamiento de 64 niveles (`Http.MAX_JSON_DEPTH`).
* **Valores predeterminados**: Tiempo de espera de 10 segundos (`Http.DEFAULT_TIMEOUT`, 5 segundos en `ping()`), hasta 5 redirecciones y verificación SSL activa.

Para consultar los mecanismos de validación de direcciones y las restricciones de seguridad, véase la guía de [Seguridad](../security/security-and-environment.md).

---

## Estructura de la respuesta

Todos los métodos de solicitud (`get`, `post`, `head`) devuelven un arreglo asociativo con la siguiente estructura normalizada:

| Campo | Tipo | Descripción |
| :--- | :--- | :--- |
| `status` | `int` | Código de estado HTTP de la respuesta (por ejemplo, `200`, `201`, `400`, `404`, `500`). |
| `status_text` | `string` | Frase de motivo del estado HTTP (por ejemplo, `'OK'`, `'Created'`, `'Not Found'`). |
| `headers` | `array` | Arreglo asociativo de encabezados devueltos por el servidor remoto. |
| `body` | `string` | Cadena del cuerpo de respuesta sin procesar, truncada a `Http.MAX_BODY_BYTES` (1 MB) si la carga remota excede ese tamaño. |
| `json` | `mixed\|null` | Estructura de datos JSON decodificada (arreglo asociativo, arreglo indexado o escalar) si el cuerpo contiene un JSON válido; `null` si está vacío, es inválido o excede `Http.MAX_JSON_DEPTH` (64 niveles). |
| `duration_ms` | `float` | Tiempo de ida y vuelta (*round-trip time / RTT*) de la petición en milisegundos, redondeado a dos decimales. |
| `content_type` | `string` | El valor del encabezado `Content-Type` de la respuesta (cadena vacía si es omitido por el servidor). |

---

## Métodos de solicitud HTTP

### Http.get()

Realiza una solicitud HTTP GET a la URL especificada.

```elscriptsignature
Http.get(
  string:url,
  array:headers = [],
  array:options = []
): array
```

#### Parámetros

* **`url`** (`string`, _requerido_): URL de destino HTTP o HTTPS.
* **`headers`** (`array`, _opcional_): Arreglo asociativo de encabezados de solicitud personalizados. El valor predeterminado es `[]`.
* **`options`** (`array`, _opcional_): Argumentos adicionales pasados al transporte HTTP de WordPress. Solo se admiten las claves definidas en `Http.ALLOWED_OPTIONS`; el resto se ignora. El valor predeterminado es `[]`.

#### Valor de retorno

Devuelve el arreglo asociativo de respuesta normalizada que contiene `status`, `status_text`, `headers`, `body`, `json`, `duration_ms` y `content_type`.

#### Manejo de errores

* Lanza `\RuntimeException` si las operaciones de red están deshabilitadas mediante `EXPRESSION_LAB_NETWORK_READONLY`.
* Lanza `\InvalidArgumentException` si la URL no es válida o no está permitida.
* Lanza `\RuntimeException` si ocurre un error en el transporte de red (por ejemplo, fallo de resolución DNS, tiempo de espera agotado o error de validación del certificado SSL).

#### Ejemplos

```elscript
/* Ejecutar una solicitud GET básica */
Http.get('https://api.github.com/zen')
```

```elscript
/* Inspeccionar el código de estado HTTP de la respuesta */
Http.get('https://api.github.com/zen')['status']
```

```elscript
/* Recuperar y decodificar una carga JSON */
Http.get('https://api.github.com/users/octocat')['json']
```

```elscript
/* Acceder a una propiedad específica de la respuesta JSON analizada */
Http.get('https://api.github.com/users/octocat')['json']['public_repos']
```

```elscript
/* Enviar encabezados de solicitud personalizados */
Http.get('https://httpbin.org/headers', { 'Accept': 'application/json', 'Authorization': 'Bearer token-de-prueba-abc' })
```

```elscript
/* Pasar opciones de transporte personalizadas como tiempo de espera y agente de usuario */
Http.get('https://httpbin.org/delay/2', {}, { timeout: 20, 'user-agent': 'MiAppPersonalizada/2.0' })
```

---

### Http.post()

Realiza una solicitud HTTP POST con una carga útil opcional a la URL especificada.

```elscriptsignature
Http.post(
  string:url,
  mixed:body = null,
  array:headers = [],
  array:options = []
): array
```

#### Parámetros

* **`url`** (`string`, _requerido_): URL de destino HTTP o HTTPS.
* **`body`** (`mixed`, _opcional_): Carga útil de la solicitud. Se maneja de la siguiente manera:
  * **Arreglo con Content-Type JSON**: Si se pasa como un arreglo y los `headers` incluyen un `Content-Type` que contenga `'json'` (sin distinción de mayúsculas/minúsculas), la carga se serializa mediante `wp_json_encode()`.
  * **Arreglo sin Content-Type JSON**: Formateado como datos de formulario (`application/x-www-form-urlencoded` o multipart) por el cliente HTTP subyacente.
  * **Escalar / Cadena**: Se convierte a cadena y se envía como el cuerpo de la solicitud.
  * **Null**: No se transmite cuerpo de solicitud. El valor predeterminado es `null`.
* **`headers`** (`array`, _opcional_): Arreglo asociativo de encabezados de solicitud personalizados. El valor predeterminado es `[]`.
* **`options`** (`array`, _opcional_): Argumentos adicionales pasados al transporte HTTP de WordPress. Solo se admiten las claves definidas en `Http.ALLOWED_OPTIONS`; el resto se ignora. El valor predeterminado es `[]`.

#### Valor de retorno

Devuelve el arreglo asociativo de respuesta normalizada que contiene `status`, `status_text`, `headers`, `body`, `json`, `duration_ms` y `content_type`.

#### Manejo de errores

* Lanza `\RuntimeException` si las operaciones de red están deshabilitadas mediante `EXPRESSION_LAB_NETWORK_READONLY`.
* Lanza `\InvalidArgumentException` si la URL no es válida o no está permitida.
* Lanza `\RuntimeException` si ocurre un error en el transporte de red.

#### Ejemplos

```elscript
/* Enviar una solicitud POST con carga JSON automática */
Http.post('https://httpbin.org/post', { action: 'sync', site_id: 1 }, { 'Content-Type': 'application/json' })
```

```elscript
/* Enviar una solicitud POST con datos de formulario URL-encoded */
Http.post('https://httpbin.org/post', { username: 'administrator', grant_type: 'password' })
```

```elscript
/* Enviar una carga de texto sin formato */
Http.post('https://httpbin.org/post', 'cadena-de-prueba', { 'Content-Type': 'text/plain' })
```

```elscript
/* Inspeccionar el cuerpo de respuesta JSON analizado de una solicitud POST */
Http.post('https://httpbin.org/post', { event: 'ping' }, { 'Content-Type': 'application/json' })['json']
```

---

### Http.head()

Realiza una solicitud HTTP HEAD para recuperar metadatos y encabezados de respuesta de un servidor remoto sin transferir el cuerpo de la respuesta.

```elscriptsignature
Http.head(
  string:url,
  array:headers = [],
  array:options = []
): array
```

#### Parámetros

* **`url`** (`string`, _requerido_): URL de destino HTTP o HTTPS.
* **`headers`** (`array`, _opcional_): Arreglo asociativo de encabezados de solicitud personalizados. El valor predeterminado es `[]`.
* **`options`** (`array`, _opcional_): Argumentos adicionales pasados al transporte HTTP de WordPress. Solo se admiten las claves definidas en `Http.ALLOWED_OPTIONS`; el resto se ignora. El valor predeterminado es `[]`.

#### Valor de retorno

Devuelve el arreglo asociativo de respuesta normalizada donde `body` está vacío, `json` es `null` y `headers` contiene los encabezados proporcionados por el servidor remoto.

#### Manejo de errores

* Lanza `\RuntimeException` si las operaciones de red están deshabilitadas mediante `EXPRESSION_LAB_NETWORK_READONLY`.
* Lanza `\InvalidArgumentException` si la URL no es válida o no está permitida.
* Lanza `\RuntimeException` si ocurre un error en el transporte de red.

#### Ejemplos

```elscript
/* Realizar una solicitud HEAD para inspeccionar los encabezados del servidor */
Http.head('https://wordpress.org')
```

```elscript
/* Recuperar el diccionario de encabezados de respuesta */
Http.head('https://wordpress.org')['headers']
```

```elscript
/* Inspeccionar el encabezado remoto Content-Type */
Http.head('https://wordpress.org')['content_type']
```

```elscript
/* Inspeccionar un encabezado de respuesta específico */
Http.head('https://wordpress.org')['headers']['server']
```

---

## Diagnósticos y benchmarking

### Http.ping()

Realiza una solicitud GET ligera con un tiempo de espera de 5 segundos para comprobar la disponibilidad del endpoint y medir la latencia. Al ejecutarse en la consola, renderiza una tabla de diagnóstico.

```elscriptsignature
Http.ping(
  string:url
): array
```

#### Parámetros

* **`url`** (`string`, _requerido_): URL HTTP o HTTPS de destino a evaluar.

#### Visualizaciones generadas

Al ejecutarse en Expression Lab, `Http.ping()` renderiza una Tabla de visualización (`Table: HTTP Ping Diagnostics`) con las siguientes métricas de diagnóstico:

* **`Target URL`**: La URL evaluada durante la prueba.
* **`HTTP Status`**: El código de estado combinado y el texto de estado (por ejemplo, `200 OK`).
* **`Latency (ms)`**: Duración total de la solicitud formateada en milisegundos.
* **`Content Type`**: Encabezado Content-Type reportado por el servidor.
* **`Payload Size`**: Recuento de bytes del cuerpo de respuesta.

#### Valor de retorno

Devuelve un arreglo asociativo que contiene:

| Campo | Tipo | Descripción |
| :--- | :--- | :--- |
| `url` | `string` | URL evaluada. |
| `status` | `int` | Código de estado HTTP devuelto por el endpoint. |
| `latency_ms` | `float` | Latencia de ida y vuelta en milisegundos. |
| `is_healthy` | `bool` | `true` si el código de estado está entre `200` y `399` (`2xx` o `3xx`), `false` en caso contrario (`4xx`, `5xx`). |

#### Manejo de errores

* Lanza `\RuntimeException` si las operaciones de red están deshabilitadas mediante `EXPRESSION_LAB_NETWORK_READONLY`.
* Lanza `\InvalidArgumentException` si la URL no es válida o no está permitida.
* Lanza `\RuntimeException` si ocurre un error de transporte de red o tiempo de espera agotado.

#### Ejemplos

```elscript
/* Ejecutar prueba de diagnóstico de latencia y disponibilidad de red */
Http.ping('https://wordpress.org')
```

```elscript
/* Inspeccionar el indicador booleano de salud del endpoint */
Http.ping('https://wordpress.org')['is_healthy']
```

```elscript
/* Inspeccionar la latencia en milisegundos */
Http.ping('https://api.github.com')['latency_ms']
```

---

## Propiedades y constantes

### Http.DEFAULT_TIMEOUT

Tiempo de espera predeterminado para solicitudes, en segundos (`10`).

```elscriptsignature
Http.DEFAULT_TIMEOUT: int
```

* **Tipo**: `int` (Solo lectura)
* **Valor**: `10`

#### Ejemplo

```elscript
/* Inspeccionar el tiempo de espera predeterminado de solicitudes HTTP */
Http.DEFAULT_TIMEOUT
```

---

### Http.MAX_BODY_BYTES

Tamaño máximo del cuerpo de respuesta en bytes (`1048576`, equivalente a 1 MB).

```elscriptsignature
Http.MAX_BODY_BYTES: int
```

* **Tipo**: `int` (Solo lectura)
* **Valor**: `1048576`

#### Ejemplo

```elscript
/* Inspeccionar el tamaño máximo de captura de cuerpo de respuesta */
Http.MAX_BODY_BYTES
```

---

### Http.MAX_JSON_DEPTH

Profundidad máxima de anidamiento permitida durante la decodificación automática de JSON (`64` niveles).

```elscriptsignature
Http.MAX_JSON_DEPTH: int
```

* **Tipo**: `int` (Solo lectura)
* **Valor**: `64`

#### Ejemplo

```elscript
/* Inspeccionar la profundidad máxima de anidamiento de decodificación JSON */
Http.MAX_JSON_DEPTH
```

---

### Http.ALLOWED_OPTIONS

Lista de claves permitidas en el parámetro `$options` para los métodos de solicitud.

```elscriptsignature
Http.ALLOWED_OPTIONS: array
```

* **Tipo**: `array` (Solo lectura)
* **Valor**: `['timeout', 'httpversion', 'user-agent', 'headers', 'body', 'cookies', 'compress', 'decompress', 'blocking']`

#### Ejemplo

```elscript
/* Inspeccionar las claves de opciones de transporte permitidas */
Http.ALLOWED_OPTIONS
```
