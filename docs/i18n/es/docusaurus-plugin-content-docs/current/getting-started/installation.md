---
id: installation
title: Instalación
sidebar_position: 1
---

import DownloadButton from '@site/src/components/DownloadButton';

# Instalación

Expression Lab se puede instalar y activar como cualquier otro plugin estándar de WordPress.

:::warning Software experimental en fase alfa
Expression Lab es un **prototipo en fase alfa** no auditado, concebido para desarrollo local, pruebas e inspección en staging. **No es apto para entornos de producción activos**, plataformas de comercio electrónico que procesen datos confidenciales ni infraestructuras críticas. Para conocer en detalle las limitaciones operativas y los casos de uso prohibidos, se debe consultar la sección [Seguridad](../security/security-and-environment.md).
:::

## Requisitos

Antes de instalar Expression Lab, es necesario verificar que el entorno cumpla con los siguientes requisitos mínimos:

### Requisitos del servidor

- **WordPress**: 6.4 o superior (probado hasta 7.1)
- **PHP**: 8.2 o superior
- **Extensiones de PHP**:
  - **[Sodium](https://www.php.net/manual/es/book.sodium.php)** (*Requerida*): Necesaria para la verificación de firmas Ed25519 en las peticiones entrantes de la consola.
  - **[SQLite3](https://www.php.net/manual/es/book.sqlite3.php)** (*Recomendada / Opcional*): Permite la replicación de tablas en memoria para consultas analíticas sin ejecutar consultas sobre la base de datos MySQL en producción.

### Requisitos del navegador

Expression Lab ejecuta operaciones criptográficas en el navegador durante la configuración inicial y el desbloqueo de la consola:

- **[Web Crypto API](https://developer.mozilla.org/es/docs/Web/API/Web_Crypto_API)**: Necesaria en el navegador para la derivación de claves mediante Argon2id y la firma de peticiones con Ed25519.
- **Contexto seguro**: Los navegadores modernos requieren contextos seguros (`https://` o `http://localhost` / `127.0.0.1`) para habilitar la Web Crypto API.

---

## Métodos de instalación

### Método 1: Descargar la versión estable

La última versión empaquetada está disponible en el [repositorio de Expression Lab en GitHub](https://github.com/andersonsalas/expressionlab):

<DownloadButton />

También es posible instalarlo a través de [Composer](https://packagist.org/packages/andersonsalas/expressionlab).

### Método 2: Instalación para desarrollo (desde el código fuente)

Para contribuir o depurar Expression Lab en un entorno local:

1. Clonar el repositorio dentro del directorio `wp-content/plugins/` de la instalación de WordPress:
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/andersonsalas/expressionlab.git
   cd expressionlab
   ```

2. Instalar las dependencias de PHP y JavaScript:
   ```bash
   composer install
   pnpm install
   ```

3. Compilar los recursos estáticos (*assets*):
   ```bash
   pnpm build
   ```

4. Activar el plugin mediante [WP-CLI](https://wordpress.org/cli/) o desde el panel de administración de WordPress:
   ```bash
   wp plugin activate expressionlab
   ```
