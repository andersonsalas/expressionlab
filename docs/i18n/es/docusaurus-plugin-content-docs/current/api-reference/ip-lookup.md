---
id: ip-lookup
title: Geolocalización por IP
sidebar_position: 9
---

# Geolocalización por IP

Resolución de direcciones IPv4 e IPv6 a códigos de país ISO 3166-1 alfa-2 mediante una base de datos binaria local MaxMind GeoLite2.

---

:::info
Este servicio de Geolocalización por IP está disponible desde la versión `v0.0.3-alpha`.
:::

Requiere una base de datos de GeoLite2 creada por MaxMind, disponible en [https://www.maxmind.com](https://www.maxmind.com). MaxMind y GeoLite2 son marcas registradas de MaxMind, Inc.

---

## Modelo Operativo y Almacenamiento

El servicio `IPLookup` realiza lecturas binarias locales sobre un archivo con formato MaxMind DB (`.mmdb`) alojado en el sistema de archivos del servidor. Durante la evaluación de expresiones no se realizan peticiones de red hacia el exterior.

* **Ubicación por defecto**: El archivo se almacena en `wp-content/expressionlab/{hash}-GeoLite2-Country.mmdb`, donde `{hash}` corresponde a un HMAC determinista de 16 caracteres derivado de las claves secretas de la instalación.
* **Ruta personalizada**: Es posible definir la constante `EXPRESSION_LAB_MAXMIND_PATH` en `wp-config.php` para indicar una ruta alternativa dentro de los límites de `WP_CONTENT_DIR`. El archivo debe terminar con la extensión `.mmdb`.
* **Política de actualización**: Las directivas de uso de MaxMind requieren actualizar las bases de datos GeoLite2 en un plazo no mayor a 30 días tras su publicación.
* **Direcciones privadas y reservadas**: Las direcciones de loopback (`127.0.0.1`, `::1`), los rangos privados (RFC 1918) y las direcciones de enlace local no tienen asignación geográfica y devuelven `null`.

---

## Gestión de la Base de Datos con WP-CLI

La descarga, actualización y desinstalación de la base de datos se realiza mediante [WP-CLI](https://make.wordpress.org/cli/handbook/).

### Descarga o Actualización

Para descargar o renovar la base de datos local GeoLite2 Country:

```bash
wp expressionlab iplookup update --license-key=CLAVE_DE_MAXMIND
```

Para evitar introducir la clave en cada ejecución, defina la constante en `wp-config.php`:

```php
define( 'EXPRESSION_LAB_MAXMIND_API_KEY', 'clave_de_maxmind' );
```

Con la constante configurada, ejecute:

```bash
wp expressionlab iplookup update
```

El proceso omite la descarga si el archivo fue actualizado en las últimas 24 horas. Para forzar la actualización inmediata, use `--force`:

```bash
wp expressionlab iplookup update --force
```

### Inspección del Estado

Para visualizar el estado del archivo, ruta, tamaño, fecha de modificación y versión de compilación:

```bash
wp expressionlab iplookup status
```

La salida informa si el archivo no existe o si su fecha de compilación supera los 30 días reglamentarios.

### Eliminación del Archivo

Para eliminar la base de datos local del servidor:

```bash
wp expressionlab iplookup delete
```

Use el flag `--yes` para omitir la confirmación interactiva:

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

#### Valor de Retorno

Retorna una cadena de dos letras en mayúsculas correspondiente al código ISO 3166-1 alfa-2 (como `'US'`, `'ES'`, `'MX'`), o `null` si la dirección pertenece a un rango privado, reservado o no asignado.

#### Manejo de Errores

* Lanza `\InvalidArgumentException` si el parámetro no tiene un formato de dirección IP sintácticamente válido.
* Lanza `\RuntimeException` si el archivo de base de datos GeoLite2 no existe o no se puede leer. Ejecute `wp expressionlab iplookup update` para aprovisionarlo.

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
/* Las direcciones IP privadas devuelven null */
IPLookup.to_country('192.168.1.1')
```

```elscript
/* La dirección localhost loopback devuelve null */
IPLookup.to_country('127.0.0.1')
```
