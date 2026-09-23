---
id: basic-syntax
title: Sintaxis básica
sidebar_position: 4
---

# Sintaxis básica

Expression Lab utiliza el componente [Symfony Expression Language](https://symfony.com/doc/current/expression_language.html) como base para la evaluación de expresiones. Proporciona un Lenguaje de Dominio Específico (DSL) declarativo diseñado para consultar datos, aplicar transformaciones e interactuar con los servicios internos de WordPress sin necesidad de escribir scripts PHP directos.

:::success ¿Quieres profundizar más?
Consulta la documentación oficial de Symfony sobre [The Expression Syntax](https://symfony.com/doc/current/reference/formats/expression_language.html) para explorar detalles adicionales de la gramática base.
:::

---

## Descripción general del motor de expresiones

El componente Symfony Expression Language compila cada expresión en un Árbol de Sintaxis Abstracta (AST). Aunque su diseño original se orienta solo a expresiones (evaluando un único valor de retorno sin estructuras iterativas convencionales como `while` o `for`), Expression Lab amplía el lenguaje base con una capa de scripting funcional y declarativo (`prog[...]`, `set[...]`, `fn[...]`, `map[...]`).

Esto permite componer flujos de trabajo de múltiples pasos, gestionar variables de expresión y transformar colecciones restringiendo la evaluación a operadores y funciones registrados en el contexto.

---

## Literales y tipos primitivos

Expression Lab admite los tipos de datos primitivos estándar:

| Tipo | Sintaxis | Descripción |
| :--- | :--- | :--- |
| **Números** | `42`, `3.14`, `-10`, `1e3` | Enteros y valores de punto flotante. |
| **Cadenas** | `'hola'`, `"WordPress"`, `"Texto con \"comillas\""` | Cadenas delimitadas con comillas simples o dobles. |
| **Booleanos** | `true`, `false` | Literales booleanos (insensibles a mayúsculas/minúsculas). |
| **Null** | `null` | Representa la ausencia de valor o valor nulo. |

### Escape de caracteres en cadenas

Las cadenas de texto pueden delimitarse con comillas simples (`'...'`) o dobles (`"..."`). Cuando necesites incluir comillas o barras invertidas dentro de una cadena, aplica las reglas estándar de escape:

- **Comillas simples (`'...'`)**: Escapa las comillas simples internas con una barra invertida (`\'`), por ejemplo: `'It\'s a test'`.
- **Comillas dobles (`"..."`)**: Escapa las comillas dobles internas con una barra invertida (`\"`), por ejemplo: `"Dijo \"Hola\""`.
- **Barras invertidas literales**: Escapa las barras invertidas con doble barra (`\\`), por ejemplo: `'C:\\Windows'` o `"ruta\\al\\archivo"`.
- **Consejo**: Es posible alternar el tipo de comillas para evitar tener que escapar caracteres:
  - Comillas dobles para contener comillas simples: `"It's a test"`
  - Comillas simples para contener comillas dobles: `'Dijo "Hola"'`

---

## Colecciones y estructuras de datos

### Arrays (listas indexadas)

Los arrays se declaran delimitando elementos separados por comas entre corchetes `[...]`:

```elscript
[1, 2, 3, "cuatro", true]
```

Se accede a sus elementos mediante índices numéricos basados en cero:

```elscript
users[0]
```

### Objetos y mapas clave-valor

Los mapas clave-valor se encierran entre llaves `{...}`:

```elscript
{ id: 1, title: 'Entrada de Muestra', published: true }
```

El acceso a sus propiedades puede realizarse mediante notación de punto o corchetes:

```elscript
post.title
post['title']
```

---

## Operadores

Expression Lab proporciona un conjunto completo de operadores aritméticos, lógicos, de comparación y manipulación de cadenas.

### Operadores aritméticos

| Operador | Sintaxis | Descripción |
| :--- | :--- | :--- |
| `+` | `10 + 5` | Suma (evalúa a `15`) |
| `-` | `10 - 5` | Resta (evalúa a `5`) |
| `*` | `10 * 5` | Multiplicación (evalúa a `50`) |
| `/` | `10 / 5` | División (evalúa a `2`) |
| `%` | `10 % 3` | Módulo / Resto (evalúa a `1`) |
| `**` | `2 ** 3` | Exponenciación (evalúa a `8`) |

### Concatenación de cadenas

El operador `~` (tilde) permite concatenar cadenas:

```elscript
'Hola, ' ~ Users.current.data.display_name ~ '!'
```

### Operadores de comparación

| Operador | Sintaxis | Descripción |
| :--- | :--- | :--- |
| `==` | `status == 'publish'` | Igualdad débil |
| `===` | `user_id === 1` | Igualdad estricta (tipo y valor) |
| `!=` | `post_type != 'revision'` | Desigualdad débil |
| `!==` | `role !== 'subscriber'` | Desigualdad estricta |
| `<`, `>` | `count > 10` | Menor que, mayor que |
| `<=`, `>=` | `memory <= 50` | Menor o igual que, mayor o igual que |

### Operadores lógicos

| Operador | Sintaxis | Descripción |
| :--- | :--- | :--- |
| `and` / `&&` | `is_admin and has_access` | Conjunción lógica (Y) |
| `or` / `\|\|` | `is_editor or is_admin` | Disyunción lógica (O) |
| `not` / `!` | `not is_draft` | Negación lógica (NO) |

---

## Operadores avanzados

#### Operador ternario (`condicion ? valor_si_verdadero : valor_si_falso`)

```elscript
user.is_admin ? 'Administrador' : 'Usuario Estándar'
```

#### Operador de coalescencia nula (`??`)

Devuelve el primer operando si está definido y no es `null`; de lo contrario, evalúa y devuelve el segundo operando:

```elscript
post.meta['subtitle'] ?? 'Sin subtítulo disponible'
```

#### Operadores de pertenencia (`in`, `not in`)

Comprueban si un valor está contenido dentro de un array o cadena:

```elscript
'administrator' in Users.current.roles
```

```elscript
'draft' not in ['publish', 'future', 'private']
```

#### Coincidencia con expresiones regulares (`matches`)

Evalúa si una cadena coincide con un patrón regex estándar:

```elscript
Users.current.data.user_email matches '/^[a-zA-Z0-9._%+-]+@example\\.com$/'
```

---

## Invocación de métodos y encadenamiento

Es posible invocar métodos en cualquier servicio, modelo o utilidad expuesta:

```elscript
Options.get('active_plugins')
```

Los constructores de consultas admiten encadenamiento fluido:

```elscript
Posts.where('post_status', 'publish').get()
```

---

## Siguientes pasos

- Consulta sobre cómo componer secuencias de pasos, variables de expresión, *closures* y transformaciones en la guía de [Sintaxis de scripting](./scripting).
- Consulta de funciones auxiliares y constantes en la sección de [Funciones y constantes](../api-reference/functions-and-constants).
- Consulta y replicación de datos en [Base de datos](../api-reference/database).
- Ajuste de salvaguardas y variables de entorno en [Configuración](./configuration).
