---
id: ip-lookup
title: Geolocalización por IP
sidebar_position: 9
---

# Geolocalización por IP

Resuelve direcciones IPv4 e IPv6 a códigos de país ISO 3166-1 alfa-2 mediante una base de datos binaria local MaxMind GeoLite2.

Requiere una base de datos GeoLite2 creada por MaxMind, disponible en [https://www.maxmind.com](https://www.maxmind.com). MaxMind y GeoLite2 son marcas registradas de MaxMind, Inc.

---

## Modelo operativo y almacenamiento

El servicio `IPLookup` ejecuta consultas binarias en local y sin conexión sobre un archivo MaxMind DB (`.mmdb`) almacenado en el sistema de archivos del servidor. No se emiten peticiones de red salientes durante la evaluación de expresiones.

* **Ubicación de la base de datos**: De forma predeterminada, la base de datos se almacena en `wp-content/expressionlab/{hash}-GeoLite2-Country.mmdb`, donde `{hash}` corresponde a un HMAC determinista de 16 caracteres derivado de las claves secretas de la instalación.
* **Ruta personalizada**: Es posible definir la constante `EXPRESSION_LAB_MAXMIND_PATH` en `wp-config.php` para indicar una ruta alternativa dentro de `WP_CONTENT_DIR`. El archivo debe tener la extensión `.mmdb`.
* **Política de actualización**: Las directivas de MaxMind exigen actualizar las bases de datos GeoLite2 dentro de los 30 días posteriores a su publicación.
* **Direcciones reservadas y privadas**: Las direcciones de loopback (`127.0.0.1`, `::1`), los rangos privados (RFC 1918) y las direcciones de enlace local no contienen asignación geográfica y devuelven `null`.

---

## Gestión de la base de datos mediante WP-CLI

La obtención, actualización y mantenimiento de la base de datos se gestionan mediante [WP-CLI](https://make.wordpress.org/cli/handbook/).

### Descarga o actualización de la base de datos

Para descargar o renovar la base de datos local GeoLite2 Country:

```bash
wp expressionlab iplookup update --license-key=MAXMIND_LICENSE_KEY
```

Para evitar pasar la clave de licencia como opción en cada ejecución, basta con definir la constante en `wp-config.php`:

```php
define( 'EXPRESSION_LAB_MAXMIND_API_KEY', 'maxmind_license_key' );
```

Con la constante definida, ejecutar:

```bash
wp expressionlab iplookup update
```

Las actualizaciones se omiten si la base de datos se actualizó en las últimas 24 horas. Para eludir esta comprobación, se puede indicar la opción `--force`:

```bash
wp expressionlab iplookup update --force
```

### Inspección del estado de la base de datos

Para consultar el estado actual de la base de datos, la ruta del archivo, el tamaño, la fecha de modificación y la época de compilación (build epoch):

```bash
wp expressionlab iplookup status
```

La salida incluye advertencias si el archivo de la base de datos no se encuentra o si su fecha de compilación supera el plazo de cumplimiento de 30 días.

### Eliminación de la base de datos

Para eliminar el archivo de la base de datos local:

```bash
wp expressionlab iplookup delete
```

Para omitir la confirmación interactiva, se puede añadir la opción `--yes`:

```bash
wp expressionlab iplookup delete --yes
```

---

## Métodos

### IPLookup.to_country()

Resuelve una dirección IPv4 o IPv6 a su código de país ISO 3166-1 alfa-2 de dos letras.

```elscriptsignature
IPLookup.to_country(
  string:ip
): string|null
```

#### Parámetros

* **`ip`** (`string`, _requerido_): Cadena con una dirección IPv4 o IPv6 válida.

#### Valor devuelto

Devuelve una cadena de dos letras en mayúsculas que representa el código de país ISO 3166-1 alfa-2 (como `'US'`, `'ES'`, `'DE'`), o `null` si la dirección IP se encuentra en un rango privado, reservado o sin asignar.

#### Gestión de errores

* Emite `\InvalidArgumentException` si la cadena de entrada no corresponde a una dirección IPv4 o IPv6 con sintaxis válida.
* Emite `\RuntimeException` si el archivo de base de datos GeoLite2 no se encuentra o no se puede leer. Ejecutar `wp expressionlab iplookup update` para instalar la base de datos.

#### Ejemplos

```elscript
/* Resolver una dirección IPv4 pública a su código de país */
IPLookup.to_country('8.8.8.8')
```

```elscript
/* Resolver una dirección IPv6 pública */
IPLookup.to_country('2001:4860:4860::8888')
```

```elscript
/* Las direcciones IP privadas o reservadas devuelven null */
IPLookup.to_country('192.168.1.1')
```

```elscript
/* La dirección de loopback local devuelve null */
IPLookup.to_country('127.0.0.1')
```
