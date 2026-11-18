---
id: configuration
title: Configuración
sidebar_position: 3
---

# Configuración

El comportamiento en tiempo de ejecución de Expression Lab se controla mediante constantes nativas de PHP definidas en el archivo `wp-config.php` de la instalación de WordPress.

Dado que Expression Lab opera como un entorno REPL aislado, estas constantes permiten ajustar las restricciones de seguridad, tiempos máximos de ejecución y límites de recursos según las características del servidor y los requisitos operativos.

---

## Constantes de autenticación y seguridad

Estas constantes se configuran durante el asistente inicial y permiten autenticar las sesiones cifradas de la consola mediante criptografía asimétrica.

| Constante | Tipo | Valor predeterminado | Descripción |
| :--- | :--- | :--- | :--- |
| `EXPRESSION_LAB_ADMIN_USER_ID` | `int` | *Ninguno* | **Requerido.** ID del usuario de WordPress autorizado para acceder a la consola de Expression Lab. |
| `EXPRESSION_LAB_ADMIN_PUBLIC_KEY` | `string` | *Ninguno* | **Requerido.** Clave pública Ed25519 en Base64, derivada en el cliente a partir de la contraseña de administrador. |
| `EXPRESSION_LAB_ADMIN_SALT` | `string` | *Ninguno* | **Requerido.** *Salt* en Base64 utilizado para la derivación de claves Argon2id durante la configuración inicial y el desbloqueo de sesiones. |

:::important Restablecimiento de contraseña y claves
En caso de pérdida de la contraseña (*passphrase*), es necesario eliminar estas tres constantes (`EXPRESSION_LAB_ADMIN_USER_ID`, `EXPRESSION_LAB_ADMIN_PUBLIC_KEY` y `EXPRESSION_LAB_ADMIN_SALT`) del archivo `wp-config.php`. Al recargar el panel de administración, el asistente inicial vuelve a mostrarse para generar un nuevo par de credenciales.
:::

---

## Constantes de sandbox y aislamiento

| Constante | Tipo | Valor predeterminado | Descripción |
| :--- | :--- | :--- | :--- |
| `EXPRESSION_LAB_SIGNATURE_DURATION` | `int` | `120` | Tiempo de validez (en segundos) de las firmas criptográficas de cada petición antes de expirar. |
| `EXPRESSION_LAB_SANDBOX_ENABLED` | `bool` | `true` | Si está habilitado, renderiza la consola en un `iframe` aislado y exige verificación criptográfica de firmas de extremo a extremo en cada petición. |
| `EXPRESSION_LAB_HOOKS_ENABLED` | `bool` | `false` | Si está habilitado, permite que plugins y temas extiendan Expression Lab con objetos, funciones y constantes mediante acciones y filtros de WordPress. |

:::warning Consideraciones de seguridad sobre el sandbox
- **`EXPRESSION_LAB_SANDBOX_ENABLED`**: Desactivar este ajuste elimina el aislamiento por `iframe` y las verificaciones criptográficas de firma. **No se debe desactivar esta constante en entornos de producción.**
- **`EXPRESSION_LAB_HOOKS_ENABLED`**: Los *hooks* personalizados se ejecutan dentro del contexto de PHP del servidor y pueden sobrepasar los límites de aislamiento de las expresiones. Se recomienda mantener esta opción activa solo durante tareas de desarrollo o pruebas controladas.
:::

---

## Límites de ejecución y recursos

Expression Lab incorpora límites operativos para prevenir bucles de ejecución prolongados, consumo excesivo de CPU y modificaciones accidentales en la base de datos.

| Constante | Tipo | Valor predeterminado | Descripción |
| :--- | :--- | :--- | :--- |
| `EXPRESSION_LAB_DATABASE_READONLY` | `bool` | `true` | Restringe el acceso a la base de datos a modo de solo lectura durante la evaluación de expresiones. Bloquea sentencias de escritura (`INSERT`, `UPDATE`, `DELETE`, `DROP`, `ALTER`) contra la base de datos activa. |
| `EXPRESSION_LAB_FILESYSTEM_READONLY` | `bool` | `true` | Restringe el acceso al sistema de archivos a operaciones de solo lectura durante las evaluaciones, impidiendo crear, modificar o eliminar archivos. |
| `EXPRESSION_LAB_NETWORK_READONLY` | `bool` | `true` | Restringe las comunicaciones de red bloqueando cualquier petición HTTP/HTTPS saliente iniciada desde una expresión. |
| `EXPRESSION_LAB_MAX_EXECUTION_LIMIT` | `int` | `2` | Tiempo máximo de CPU en segundos permitido para una expresión antes de que su ejecución sea interrumpida. |

---

## Constantes del sistema y diagnóstico

| Constante | Tipo | Valor predeterminado | Descripción |
| :--- | :--- | :--- | :--- |
| `EXPRESSION_LAB_DEBUG_MODE` | `bool` | `false` | Activa la depuración interna del plugin y omite el aislamiento por `iframe` para desarrollo del núcleo. |
| `EXPRESSION_LAB_BROWSER_LOG` | `bool` | `false` | Envía los registros de diagnóstico internos del motor a la consola de herramientas de desarrollo del navegador. |
| `EXPRESSION_LAB_CACHE` | `bool` | `true` | Controla el almacenamiento en caché del explorador lateral (`el_outline_cache` en `localStorage`), reservada además para futuras operaciones de caché tanto en el frontend como en el backend. |
| `EXPRESSION_LAB_DISABLE_SQLITE` | `bool` | `false` | Desactiva el motor de replicación en memoria con SQLite3 cuando la extensión no esté disponible o no se desee utilizar en el servidor. |

:::danger No habilitar EXPRESSION_LAB_DEBUG_MODE en producción
`EXPRESSION_LAB_DEBUG_MODE` se reserva solo para el **desarrollo local y la depuración interna del plugin**. Habilitar esta constante desactiva controles de seguridad esenciales:

* **Desactiva la firma criptográfica de peticiones**: Omite el firmado Ed25519 en el cliente y la validación de firmas en el servidor.
* **Desactiva la rotación de desafíos (*nonces*)**: Omite la rotación dinámica de desafíos, eliminando la mitigación contra ataques de repetición (*replay attacks*).
* **Elimina el aislamiento por iframe**: Renderiza la consola en el DOM del panel de administración de WordPress sin aislamiento.

Aunque `EXPRESSION_LAB_DEBUG_MODE` mantiene la comprobación del ID de usuario autorizado en WordPress, las comunicaciones se realizan mediante peticiones AJAX estándar sin firma criptográfica. **No se debe activar esta constante en entornos de producción o sitios con acceso público.**
:::
