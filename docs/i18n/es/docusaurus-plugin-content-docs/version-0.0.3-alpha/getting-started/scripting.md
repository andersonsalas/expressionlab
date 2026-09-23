---
id: scripting
title: Sintaxis de scripting
sidebar_position: 5
---

# Sintaxis de scripting

Expression Lab introduce un dialecto de scripting funcional y declarativo integrado en su analizador de Árbol de Sintaxis Abstracta (AST). Orientada al paradigma de expresiones, esta capa permite componer flujos de trabajo de múltiples pasos, declarar *closures* lambda reutilizables, gestionar variables de expresión y realizar transformaciones de orden superior sobre colecciones de datos dentro de los límites de las formas registradas en el lenguaje.

---

## Paradigma del lenguaje y principios de diseño

El dialecto de scripting de Expression Lab está diseñado bajo tres principios fundamentales:

### 1. Naturaleza orientada a expresiones
Todo en Expression Lab es una expresión que evalúa a un valor. No existen sentencias imperativas ni palabras clave de control de flujo tradicionales (como `while`, `for`, `goto` o asignaciones tipo `$x = 10`). La lógica secuencial se modela mediante composición declarativa y funcional.

### 2. Dicotomía sintáctica: [...] frente a (...)
Para garantizar una gramática sin ambigüedades, Expression Lab establece una separación clara entre las construcciones del motor y las llamadas a funciones de PHP o WordPress:

| Estructura sintáctica | Destino | Descripción | Ejemplo |
| :--- | :--- | :--- | :--- |
| **Formas especiales** | `<PalabraClave>[ ... ]` | Sintaxis a nivel de motor para secuenciación, gestión de estado, *closures* y transformaciones de flujos. | `prog[...]`, `set[...]`, `fn[...]`, `map[...]` |
| **Funciones del host** | `<Identificador>( ... )` | Funciones estándar de PHP y WordPress expuestas con evaluación inmediata (*eager evaluation*) de argumentos. | `count(...)`, `strtoupper(...)`, `Database.mirror(...)` |

### 3. Controles de ejecución y límites operativos
El motor de scripting opera bajo restricciones de evaluación definidas:

* **Evaluación diferida (*lazy evaluation*)**: Las funciones anónimas creadas con `fn[...]` compilan su cuerpo en un subárbol del AST que se evalúa solo al invocar la función.
* **Contador de operaciones (límite de gas)**: El motor incrementa un contador de operaciones durante iteraciones y recorridos del AST para cumplir con el tiempo límite configurado (`EXPRESSION_LAB_MAX_EXECUTION_LIMIT`).
* **Validación de tipo en *closures***: Las operaciones de orden superior (`map`, `filter`, `reduce`) requieren instancias de `\Closure` creadas mediante `fn[...]`, impidiendo la evaluación de cadenas con nombres de funciones arbitrarias.

---

## Registro de formas especiales

Expression Lab reserva 11 palabras clave para sus formas especiales:

| Forma especial | Categoría | Firma | Descripción |
| :--- | :--- | :--- | :--- |
| **`prog`** | Secuenciación | `prog[<expr1>, ..., <exprN>] : mixed` | Evalúa expresiones en orden de izquierda a derecha y devuelve el resultado de `<exprN>`. |
| **`set`** | Asignación de estado | `set[<name:string>, <value:mixed>] : mixed` | Almacena `<value>` en el almacén de variables de la expresión bajo la clave `<name>` y devuelve el valor asignado. |
| **`var`** | Lectura de estado | `var[<name:string>] : mixed` | Obtiene el valor almacenado en una variable de expresión. |
| **`isset`** | Inspección de estado | `isset[<name:string>] : bool` | Devuelve `true` si la variable `<name>` existe en el ámbito de la expresión y no es `null`. |
| **`unset`** | Eliminación de estado | `unset[<name:string>] : bool` | Elimina `<name>` del almacén de variables de la expresión y devuelve `true`. |
| **`fn`** | *Closures* | `fn[[<param1>, ...], <body:expr>] : \Closure` | Define una función anónima (*closure*) de primera clase con evaluación diferida. |
| **`args`** | Ámbito léxico | `args[<name:string>] : mixed` | Accede al valor de un parámetro dentro del cuerpo de una *closure* `fn`. |
| **`map`** | Transformación de flujos | `map[<iterable>, <fn>] : array` | Transforma cada elemento de `<iterable>` aplicando la *closure* `<fn>`. |
| **`filter`** | Filtrado de flujos | `filter[<iterable>, <fn>, <mode:int>?] : array` | Filtra los elementos de `<iterable>` que cumplan con la condición predicado `<fn>`. |
| **`reduce`** | Agregación de flujos | `reduce[<iterable>, <fn>, <initial:mixed>?] : mixed` | Reduce `<iterable>` a un único valor acumulado mediante `<fn>`. |
| **`show`** | Visualización | `show[<expr>] : mixed` | Fuerza la renderización gráfica de un resultado intermedio dentro de un bloque `prog`. |

---

## Ejecución secuencial: prog[]

La forma especial `prog[...]` (inspirada en la función `progn` de Lisp) ejecuta múltiples expresiones en secuencia y devuelve el valor de la última expresión evaluada:

```elscript
prog[
  set['post_type', 'page'],
  set['pages', Posts.where('post_type', var['post_type']).get()],
  count(var['pages'])
]
```

### Reglas y comportamiento

* Las expresiones se evalúan en orden de izquierda a derecha.
* Un bloque `prog[]` vacío devuelve `null`.
* Se admiten comas finales sin producir error: `prog[ set['a', 1], set['b', 2], ]`.
* Se admite anidamiento arbitrario: un bloque `prog` puede ubicarse dentro de condicionales ternarios, argumentos de funciones o cuerpos de *closures*.

---

## Variables y gestión de estado de la expresión

Las variables de expresión permiten almacenar cálculos intermedios, consultas y modelos durante la evaluación de un script o bloque `prog[...]`.

:::tip Ámbito en memoria frente a sesiones PHP
Las variables declaradas con `set[...]` residen solo en la memoria volátil del motor (`VariableStore`) durante el ciclo de vida de la evaluación. **No utilizan ni modifican la superglobal `$_SESSION` de PHP**, no manipulan cookies ni persisten en el servidor o navegador tras finalizar la ejecución.
:::

### Asignar variables: set[]

Almacena un valor en el almacén de variables de la expresión y devuelve el valor asignado:

```elscript
set['threshold', 100]
```

### Leer variables: var[]

Recupera una variable almacenada:

```elscript
var['threshold'] * 1.25
```

:::tip Acceso mediante notación de punto
También es posible leer variables de expresión utilizando la propiedad de punto sobre el objeto `var` (por ejemplo, `var.threshold`). Ambas sintaxis (`var['threshold']` y `var.threshold`) operan sobre el mismo almacén de variables de la expresión.
:::

### Claves anidadas con notación de punto

`set` y `var` admiten rutas delimitadas por puntos para organizar estructuras complejas:

```elscript
prog[
  set['app.settings.theme', 'dark'],
  set['app.settings.per_page', 25],
  var['app.settings.per_page']
]
```

### Comprobar existencia: isset[]

Determina si una variable o ruta anidada está definida y no es `null`:

```elscript
isset['app.settings.theme'] ? var['app.settings.theme'] : 'light'
```

### Eliminar variables: unset[]

Elimina una variable del almacén de la expresión liberando los recursos asociados. Devuelve `true`:

```elscript
unset['pages']
```

---

## *Closures* de primera clase y ámbito léxico

Las funciones anónimas (lambdas) se declaran mediante la forma especial `fn`.

### Creación de closures: fn[]

Genera una instancia de `\Closure` de PHP con evaluación diferida (*lazy*). El cuerpo se compila como un subárbol del AST y solo se evalúa al invocar la función:

```elscript
/* Lambda que recibe un único parámetro */
fn[['x'], args['x'] * 2]
```

```elscript
/* Lambda que recibe múltiples parámetros */
fn[['first', 'last'], args['first'] ~ ' ' ~ args['last']]
```

### Acceso a parámetros: args[]

Dentro del cuerpo de una *closure*, los parámetros recibidos se leen a través de `args['<nombre_parametro>']`. Si se invoca fuera del contexto de una *closure*, `args[...]` devuelve `null`.

:::info Ámbito de variables: estado global de la expresión frente a ámbito léxico local
* **`set[...]` y `var[...]` operan a nivel global de la expresión**: Cualquier variable modificada con `set[...]` (incluso dentro de un `prog[...]` anidado o dentro de una *closure* `fn[...]`) altera el estado compartido en `VariableStore` a lo largo de toda la evaluación de la expresión.
* **`args[...]` tiene ámbito léxico y local**: Los parámetros definidos en `args[...]` existen solo durante la ejecución de esa llamada específica y no se propagan a otras funciones ni al almacén global de la expresión.
:::

```elscript
/* Devuelve: ["Hello, ALICE!", "Hello, BOB!"] */
prog[
  set['greet', fn[['name'], 'Hello, ' ~ strtoupper(args['name']) ~ '!']],
  /* Las closures almacenadas en variables pueden pasarse a operaciones de orden superior */
  map[
    ['alice', 'bob'],
    var['greet']
  ]
]
```

---

## Procesamiento funcional de colecciones y flujos (*streams*)

Expression Lab provee formas especiales integradas para manipular arrays, resultados de consultas e iterables.

### Transformación: map[]

Aplica una *closure* a cada elemento de un iterable y devuelve un array con los resultados transformados:

```elscript
/* Devuelve: ["post_id_10", "post_id_25", "post_id_42"] */
prog[
  set['raw_ids', [10, 25, 42]],
  map[
    var['raw_ids'],
    fn[['id'], 'post_id_' ~ args['id']]
  ]
]
```

### Filtrado: filter[]

Filtra elementos de una colección, conservando solo aquellos donde el predicado evalúe a verdadero (*truthy*):

```elscript
/* Devuelve: [25, 42] */
prog[
  set['raw_ids', [10, 25, 42]],
  filter[
    var['raw_ids'],
    fn[['id'], args['id'] > 20]
  ]
]
```

#### Modos de filtrado

`filter` acepta un tercer parámetro opcional para especificar si se evalúa el valor, la clave o ambos:

| Valor del modo | Comportamiento | Firma de la closure | Tratamiento de claves |
| :--- | :--- | :--- | :--- |
| `0` (Predeterminado) | Filtrar por valor | `fn[['item'], ...]` | Listas indexadas se reindexan (`[0, 1, ...]`). Claves asociativas se conservan. |
| `1` (`USE_BOTH`) | Filtrar por valor y clave | `fn[['item', 'key'], ...]` | Conserva las claves originales. |
| `2` (`USE_KEY`) | Filtrar solo por clave | `fn[['key'], ...]` | Conserva las claves originales. |

```elscript
/* Devuelve: { "wp_core": 1, "plugin_a": 2 } */
filter[
  { 'wp_core': 1, 'plugin_a': 2, 'temp_cache': 3 },
  fn[['k'], not (args['k'] matches '/^temp_/')],
  2
]
```

### Agregación / plegado: reduce[]

Reduce un iterable a un único valor acumulado. La *closure* reductora recibe `(acumulador, elemento_actual)`:

```elscript
/* Devuelve: 100 */
prog[
  set['values', [10, 20, 30, 40]],
  reduce[
    var['values'],
    fn[['acc', 'n'], args['acc'] + args['n']],
    0
  ]
]
```

---

## Visualizaciones en la consola: show[]

Al evaluar bloques compuestos con `prog[...]`, las representaciones intermedias (como tablas de base de datos o gráficos) se ocultan para mostrar solo el resultado de la última expresión.

La forma `show[<expr>]` permite forzar la visualización gráfica de representaciones intermedias sin interrumpir el flujo de ejecución:

```elscript
prog[
  /* Muestra la tabla interactiva de resultados en la consola */
  show[Database.mirror('posts', { post_status: 'publish' }).fetch()],
  
  /* Continúa calculando métricas adicionales */
  set['total', Database.count('posts', { post_status: 'publish' })],
  'Auditoría completada. Total de publicaciones activas: ' ~ var['total']
]
```

---

## Ejemplos prácticos de scripting

### Ejemplo 1: Auditoría de distribución de estados de publicaciones
Cálculo del recuento de publicaciones agrupadas por estado y resumen consolidado:

```elscript
prog[
  set['posts', Posts.limit(50).get()],
  set['published', filter[var['posts'], fn[['p'], args['p'].post_status == 'publish']]],
  set['drafts', filter[var['posts'], fn[['p'], args['p'].post_status == 'draft']]],
  {
    total_audited: count(var['posts']),
    published_count: count(var['published']),
    draft_count: count(var['drafts'])
  }
]
```

### Ejemplo 2: Pipeline de transformación y extracción de contenido
Recuperación de entradas recientes, construcción de un array asociativo con métricas calculadas y filtrado por longitud:

```elscript
prog[
  set['posts', Posts.where('post_status', 'publish').limit(10).get()],
  set['summaries', map[
    var['posts'],
    fn[['p'], {
      id: args['p'].ID,
      title: args['p'].post_title,
      slug: args['p'].post_name,
      word_count: count(explode(' ', args['p'].post_content)),
      has_comments: intval(args['p'].comment_count) > 0
    }]
  ]],
  filter[var['summaries'], fn[['item'], args['item']['word_count'] > 0]]
]
```

---

## Siguientes pasos

* Consulta de herramientas de consulta y replicación en la [API de base de datos](../api-reference/database).
* Consulta de funciones del sistema en [Funciones y constantes](../api-reference/functions-and-constants).
* Revisión de límites de ejecución y restricciones en [Configuración](./configuration).
