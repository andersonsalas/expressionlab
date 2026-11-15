---
id: snippet-library
title: Biblioteca de snippets
sidebar_position: 1
---

# Biblioteca de snippets

Permite guardar, organizar, exportar y reejecutar expresiones habituales, rutinas de diagnóstico y consultas de base de datos desde la consola.

<div className="desktop-window">
  <img 
    src="../../img/0.2.0/snippetlibrary.png" 
    alt="Modal de la biblioteca de snippets de Expression Lab" 
  />
</div>

El modal de la Biblioteca de Snippets se compone de las siguientes áreas:

### Lista de snippets y búsqueda
* **Barra de búsqueda**: Filtra los fragmentos guardados en tiempo real por título o por etiquetas asociadas. Permite búsquedas de texto libre así como filtros explícitos mediante la sintaxis `tag:nombre` (por ejemplo, `tag:database` o `tag:[mi etiqueta]`).
* **Botón `+ Agregar Fragmento`**: Crea un nuevo fragmento en blanco para edición.
* **Navegación de snippets**: Al pulsar cualquier elemento de la lista se carga su título, código y etiquetas en el panel de edición.

### Editor de snippets
* **Campo de título**: Asigna un nombre descriptivo al fragmento (por ejemplo, `Usuarios (Database sin procesar)` o `Distribución de opciones`).
* **Editor de código**: Edición de expresiones multilínea con resaltado de sintaxis completo.
* **Campo de etiquetas (*Tags*)**: Asigna etiquetas personalizadas para categorizar fragmentos por temática (por ejemplo, `posts`, `database`, `diagnostics`). Muestra sugerencias de etiquetas existentes, permite añadir nuevas pulsando <kbd>Enter</kbd> o coma, y eliminarlas mediante el icono de borrado en sus chips.
* **Formatear código (`{}`)**: Aplica formato y sangrado limpio a la expresión en el editor.
* **Guardar (`Icono de disquete`)**: Guarda los cambios en el fragmento seleccionado.
* **Eliminar (`Icono de Papelera`)**: Elimina el fragmento del almacenamiento.

### Botones de acción
* **Insertar**: Inserta el código del fragmento en el editor activo de la consola o del scratchpad.
* **Cerrar**: Cierra el modal sin alterar el contenido actual del editor.

---

## Modos de almacenamiento

Expression Lab ofrece dos modalidades para la persistencia de los fragmentos:

### Almacenamiento en el navegador (predeterminado)

De forma predeterminada, los fragmentos se guardan en el almacenamiento local del navegador (IndexedDB / LocalStorage). Esto proporciona acceso inmediato sin requerir permisos adicionales ni configuración de rutas.

### Sincronización con el sistema de archivos local

La opción **Usar sistema de archivos local** permite sincronizar fragmentos con una carpeta local mediante la [File System Access API](https://developer.mozilla.org/es/docs/Web/API/File_System_Access_API) nativa del navegador:

* **Control de versiones**: Permite versionar los fragmentos dentro de un repositorio Git local.
* **Uso compartido entre navegadores**: Reutilización de colecciones en múltiples navegadores o entornos de desarrollo.
* **Persistencia en disco**: Mantiene los fragmentos fuera del almacenamiento temporal o de la caché del navegador.

:::note Compatibilidad de la File System Access API
La File System Access API está disponible en navegadores modernos basados en Chromium (Chrome, Edge, Brave, Opera) bajo contextos seguros (`HTTPS` o `localhost`). En navegadores no compatibles, Expression Lab recurre al almacenamiento local del navegador.
:::

---

## Importación y exportación de snippets

El botón **Importar/Exportar** situado en la esquina inferior izquierda del modal permite gestionar copias de seguridad:

* **Exportar**: Descarga el catálogo completo de fragmentos como un archivo empaquetado en formato `.json`.
* **Importar**: Carga colecciones de fragmentos en formato JSON, integrándolas o restaurándolas en el espacio de trabajo.

Esto simplifica compartir diagnósticos estándar entre miembros de un equipo, distribuir consultas de inducción o mantener scripts de auditoría sincronizados entre diferentes entornos de WordPress.
