---
id: functions-and-constants
title: Funciones y constantes
sidebar_position: 10
---

# Funciones y constantes

Referencia de las constantes globales del motor, almacén de estado de sesión, utilidades de visualización y puentes nativos a funciones estándar de PHP y WordPress.

:::tip Documentación contextual interactiva
En lugar de mantener páginas estáticas con cientos de funciones estándar de PHP y WordPress, Expression Lab incorpora introspección dinámica en tiempo de ejecución. El **Explorador Lateral (Outline)** y los paneles flotantes de la consola REPL permiten buscar, explorar e inspeccionar firmas completas de parámetros, tipos de retorno y documentación.
:::

---

## Constantes en tiempo de ejecución

Las constantes en Expression Lab son valores inmutables registrados en el contexto de ejecución del motor. Se invocan por su nombre, sin necesidad del signo de dólar (`$`) ni prefijos especiales.

### Constantes globales del motor

Disponibles en cualquier expresión:

| Constante | Tipo | Descripción | Ejemplo |
| :--- | :--- | :--- | :--- |
| `EXPRESSION_LAB_VERSION` | `string` | Devuelve la versión activa del plugin Expression Lab. | `EXPRESSION_LAB_VERSION` |

```elscript
/* Comprobar la versión activa del motor */
'Ejecutando Expression Lab v' ~ EXPRESSION_LAB_VERSION
```

### Constantes con espacio de nombres por servicio

Determinados modelos y servicios exponen constantes operativas accesibles mediante la notación de punto:

```elscript
/* Constante de formato del servicio Options */
Options.FORMAT_JSON
```

```elscript
/* Opciones de configuración del servicio Http */
Http.ALLOWED_OPTIONS
```

:::note ¿Buscas constantes de configuración del servidor?
Las constantes para configurar el entorno del plugin (como `EXPRESSION_LAB_MAX_EXECUTION_LIMIT` o `EXPRESSION_LAB_DATABASE_READONLY`) se definen en el archivo `wp-config.php`. Consulta la guía de [Configuración](../getting-started/configuration) para ver la lista completa.
:::

---

## Gestión de estado y formas especiales

El almacenamiento de variables, la secuenciación de pasos, las *closures* y el procesamiento funcional de colecciones operan mediante las **Formas Especiales** del motor (`set[...]`, `var[...]`, `isset[...]`, `unset[...]`, `show[...]`, `prog[...]`, `fn[...]`, `map[...]`, `filter[...]`, `reduce[...]`).

Estas formas especiales se procesan en el analizador del AST mediante la sintaxis de corchetes (`palabra_clave[...]`), a diferencia de las funciones convencionales que utilizan paréntesis (`funcion(...)`).

:::info Guía completa de scripting
Para una explicación exhaustiva sobre variables de expresión, creación de *closures* y composición de flujos secuenciales, consulta la **[guía de sintaxis de scripting](../getting-started/scripting)**.
:::

---

## Puentes a funciones de PHP y WordPress

Expression Lab expone un conjunto seleccionado de funciones de utilidad de PHP y WordPress dentro del contexto de ejecución, con invocación directa:

```elscript
/* Funciones estándar de PHP para cadenas y arrays */
strtoupper(trim('  hola mundo  '))
json_encode({ status: 'ok', count: 42 })

/* Funciones auxiliares de WordPress */
wp_strip_all_tags('<p>Texto de Muestra <a href="#">Enlace</a></p>')
```

Para explorar el catálogo completo de funciones habilitadas de PHP y WordPress, el **Explorador Lateral** en la consola ofrece documentación interactiva de cada función con sus tipos de parámetros, valores predeterminados y enlaces a la documentación oficial.
