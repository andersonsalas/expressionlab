/**
 * Expression Lab DSL keywords specification and autocompletion items.
 *
 * Provides static keyword definitions, signatures, documentation and snippet
 * templates for CodeMirror autocompletion.
 */

const documentation_url = 'https://expressionlab.io/docs/getting-started/scripting/#';

export const DSL_KEYWORDS = [
  {
    label: 'args[]',
    kind: 'keyword',
    insertText: 'args[\'${1:name}\']',
    doc: {
      summary: 'Access argument passed to the current lambda closure',
      signature: 'args[\'name\'] => mixed',
      description: 'Retrieves the value of a formal parameter passed into the currently executing anonymous lambda closure defined via `fn[]`.\n\nResolves strictly within the active closure\'s lexical execution frame. When evaluated outside of a closure frame, or if the parameter was not passed, returns `null` without error.\n\nExample:\n\n```elscript\nfn[[\'a\', \'b\'], args[\'a\'] + args[\'b\']]\n```',
      parameters: [
        {
          name: 'name',
          type: 'string',
          description: 'Parameter identifier declared in the formal parameter list of the enclosing `fn[]` closure.',
          required: true,
        },
      ],
      return: {
        type: 'mixed',
        description: 'Value bound to the argument in the active lexical frame, or `null` if evaluated outside of a closure context or if the parameter is undefined.',
      },
      see: [
        `${documentation_url}accessing-parameters-args`,
        `${documentation_url}creating-closures-fn`,
      ],
    },
  },
  {
    label: 'filter[]',
    kind: 'keyword',
    insertText: 'filter[${1:iterable}, fn[[\'${2:item}\'], ${3:expr}]]',
    doc: {
      summary: 'Filter iterable elements with a predicate function',
      signature: 'filter[iterable, fn[[\'item\'], body], mode?] => array',
      description: 'Evaluates a predicate callback for each element of an iterable collection, returning a new array containing only the elements for which the callback returned truthy.\n\nThe predicate callback must be a native `Closure` declared with `fn[]`. When filtering sequential lists under default mode (`0`), elements are re-indexed numerically (`0, 1, ...`); associative keys are preserved. The engine ticks execution gas during iteration.\n\nExample:\n\n```elscript\nfilter[[1, 2, 3, 4], fn[[\'n\'], args[\'n\'] % 2 == 0]]\n```',
      parameters: [
        {
          name: 'iterable',
          type: 'array|Traversable',
          description: 'The target array, list, or Traversable collection to filter.',
          required: true,
        },
        {
          name: 'callback',
          type: 'Closure',
          description: 'Predicate lambda created via `fn[]`. Must evaluate to a truthy value to retain an item.',
          required: true,
        },
        {
          name: 'mode',
          type: 'int',
          description: 'Filtering mode flag: `0` (default, filter by value; sequential lists re-indexed), `1` (`USE_BOTH`, passes item and key to `fn[[\'item\', \'key\'], ...]`; preserves keys), or `2` (`USE_KEY`, passes key to `fn[[\'key\'], ...]`; preserves keys).',
          required: false,
        },
      ],
      return: {
        type: 'array',
        description: 'Filtered array containing only the elements that satisfied the predicate callback.',
      },
      see: [
        `${documentation_url}filtering-filter`,
      ],
    },
  },
  {
    label: 'fn[]',
    kind: 'keyword',
    insertText: 'fn[[\'${1:arg}\'], ${2:expr}]',
    doc: {
      summary: 'Define an anonymous lambda closure',
      signature: 'fn[[\'arg1\', \'arg2\'], body_expression] => Closure',
      description: 'Declares a first-class anonymous closure with formal parameters and a lazily evaluated expression body.\n\nThe body expression is compiled into an Abstract Syntax Tree (AST) subtree at parse time and evaluated only upon closure invocation. Closures capture the execution context lexically, can be assigned to variables via `set[]`, and may be passed to higher-order stream operations (`map[]`, `filter[]`, `reduce[]`).\n\nExample:\n\n```elscript\nset[\'double\', fn[[\'x\'], args[\'x\'] * 2]]\n```',
      parameters: [
        {
          name: 'params',
          type: 'string[]',
          description: 'Array of string literals declaring formal parameter names accessible via `args[]` within the body (e.g. `[\'x\', \'y\']`).',
          required: true,
        },
        {
          name: 'body',
          type: 'expr',
          description: 'Evaluable expression body compiled into an AST subtree and evaluated lazily upon each invocation.',
          required: true,
        },
      ],
      return: {
        type: 'Closure',
        description: 'A callable PHP `Closure` instance with isolated lexical parameter scope.',
      },
      see: [
        `${documentation_url}first-class-closures--lexical-scope`,
      ],
    },
  },
  {
    label: 'isset[]',
    kind: 'keyword',
    insertText: 'isset[\'${1:name}\']',
    doc: {
      summary: 'Check whether a local variable is defined',
      signature: 'isset[\'name\'] => bool',
      description: 'Checks whether the specified variable exists within the in-memory variable store (`VariableStore`) and its value is not `null`.\n\nSupports dot-separated nested path notation (e.g. `isset[\'config.db.host\']`) to safely inspect nested structures without triggering undefined variable notices.\n\nExample:\n\n```elscript\nisset[\'my_var\']\n```',
      parameters: [
        {
          name: 'name',
          type: 'string',
          description: 'Variable identifier or dot-separated nested path (e.g. `\'app.theme\'`) to inspect in the execution store.',
          required: true,
        },
      ],
      return: {
        type: 'bool',
        description: '`true` if the variable exists and is not `null`; `false` otherwise.',
      },
      see: [
        `${documentation_url}checking-variable-existence-isset`,
      ],
    },
  },
  {
    label: 'map[]',
    kind: 'keyword',
    insertText: 'map[${1:iterable}, fn[[\'${2:item}\'], ${3:expr}]]',
    doc: {
      summary: 'Transform iterable elements with a mapper function',
      signature: 'map[iterable, fn[[\'item\'], body]] => array',
      description: 'Applies a transformation closure to each element of an iterable collection and returns a new array containing the transformed values.\n\nThe mapper callback must be a native `Closure` defined with `fn[]`. Each iteration ticks engine execution gas against `EXPRESSION_LAB_MAX_EXECUTION_LIMIT` to safeguard against resource exhaustion.\n\nExample:\n\n```elscript\nmap[[1, 2, 3], fn[[\'x\'], args[\'x\'] * 10]]\n```',
      parameters: [
        {
          name: 'iterable',
          type: 'array|Traversable',
          description: 'The target array, list, or Traversable collection whose elements will be transformed.',
          required: true,
        },
        {
          name: 'callback',
          type: 'Closure',
          description: 'Unary transformation lambda declared via `fn[]` that receives each element and returns the mapped value.',
          required: true,
        },
      ],
      return: {
        type: 'array',
        description: 'A new array containing the transformed values in corresponding order.',
      },
      see: [
        `${documentation_url}mapping-map`,
      ],
    },
  },
  {
    label: 'prog[]',
    kind: 'keyword',
    insertText: 'prog[\n\t${1}\n]',
    doc: {
      summary: 'Sequential evaluation block',
      signature: 'prog[expr1, expr2, ..., exprN] => mixed',
      description: 'Evaluates multiple expressions in strict sequential order from left to right and returns the evaluation result of the final expression.\n\nUseful for composing multi-step execution workflows involving variable state assignments, data transformations, and diagnostic outputs. Trailing commas are permitted. If called with no arguments, returns `null`.\n\nExample:\n\n```elscript\nprog[\n  set[\'x\', 10],\n  var[\'x\'] * 2\n]\n```',
      parameters: [
        {
          name: '...expressions',
          type: 'expr',
          description: 'Comma-separated sequence of expressions to evaluate sequentially. If empty, evaluates to `null`.',
          required: false,
        },
      ],
      return: {
        type: 'mixed',
        description: 'The evaluation result of the final expression in the sequence, or `null` if the block contains no expressions.',
      },
      see: [
        `${documentation_url}sequential-execution-prog`,
      ],
    },
  },
  {
    label: 'reduce[]',
    kind: 'keyword',
    insertText: 'reduce[${1:iterable}, fn[[\'${2:acc}\', \'${3:item}\'], ${4:expr}], ${5:initial}]',
    doc: {
      summary: 'Reduce an iterable to a single accumulated value',
      signature: 'reduce[iterable, fn[[\'acc\', \'item\'], body], initial?] => mixed',
      description: 'Applies an accumulator callback iteratively to all items in an iterable collection, reducing them to a single cumulative scalar or composite value.\n\nThe reducer callback must be a native `Closure` created via `fn[]` accepting `(accumulator, current_item)`. An optional initial seed value can be provided; if omitted, the accumulator starts as `null`.\n\nExample:\n\n```elscript\nreduce[[1, 2, 3], fn[[\'acc\', \'n\'], args[\'acc\'] + args[\'n\']], 0]\n```',
      parameters: [
        {
          name: 'iterable',
          type: 'array|Traversable',
          description: 'The target array, list, or Traversable collection to fold.',
          required: true,
        },
        {
          name: 'callback',
          type: 'Closure',
          description: 'Binary reduction lambda declared via `fn[]` receiving `(accumulator, current_item)` and returning the updated accumulator.',
          required: true,
        },
        {
          name: 'initial',
          type: 'mixed',
          description: 'Initial seed value for the accumulator before iteration begins. Defaults to `null` if omitted.',
          required: false,
        },
      ],
      return: {
        type: 'mixed',
        description: 'The final accumulated scalar or composite value after iterating across the collection.',
      },
      see: [
        `${documentation_url}reducing-reduce`,
      ],
    },
  },
  {
    label: 'set[]',
    kind: 'keyword',
    insertText: 'set[\'${1:name}\', ${2:value}]',
    doc: {
      summary: 'Declare or assign a local variable',
      signature: 'set[\'name\', value] => mixed',
      description: 'Binds a value under an identifier in the in-memory execution store (`VariableStore`) for the lifecycle of the active expression.\n\nSupports dot-separated path syntax (e.g. `set[\'app.settings.theme\', \'dark\']`) to initialize or update nested associative structures. Returns the assigned value, enabling chained or embedded assignments.\n\nExample:\n\n```elscript\nset[\'total\', 100]\n```',
      parameters: [
        {
          name: 'name',
          type: 'string',
          description: 'Variable identifier or dot-separated path (e.g. `\'app.settings.per_page\'`) to bind in the variable store.',
          required: true,
        },
        {
          name: 'value',
          type: 'mixed',
          description: 'The value to assign. Can be any scalar, array, object model, or lambda closure.',
          required: true,
        },
      ],
      return: {
        type: 'mixed',
        description: 'The evaluated value assigned to the variable.',
      },
      see: [
        `${documentation_url}declaring-variables-set`,
      ],
    },
  },
  {
    label: 'show[]',
    kind: 'keyword',
    insertText: 'show[ ${1:expr} ]',
    doc: {
      summary: 'Mark expression for rich visualization output',
      signature: 'show[expression] => mixed',
      description: 'Forces the immediate rendering of any rich diagnostic visualizations (such as database query tables, distribution charts, or inspection dumps) generated by the enclosed expression.\n\nIn multi-step `prog[...]` blocks, intermediate visualizers are silenced by default to prevent console clutter. Wrapping an expression in `show[...]` unsilences and renders its visualizers while returning the expression result unchanged.\n\nExample:\n\n```elscript\nshow[ Options.stats() ]\n```',
      parameters: [
        {
          name: 'expression',
          type: 'expr',
          description: 'Expression to evaluate whose generated diagnostic visualizations will be rendered in the console.',
          required: true,
        },
      ],
      return: {
        type: 'mixed',
        description: 'The evaluated result of the enclosed expression.',
      },
      see: [
        `${documentation_url}console-visualizations-show`,
      ],
    },
  },
  {
    label: 'unset[]',
    kind: 'keyword',
    insertText: 'unset[\'${1:name}\']',
    doc: {
      summary: 'Delete a local variable from the current scope',
      signature: 'unset[\'name\'] => bool',
      description: 'Removes a variable or nested dot-separated path previously bound via `set[]` from the in-memory execution store (`VariableStore`).\n\nFrees memory allocated to intermediate query results, datasets, or models during multi-step script execution. Always returns `true`.\n\nExample:\n\n```elscript\nunset[\'temp_data\']\n```',
      parameters: [
        {
          name: 'name',
          type: 'string',
          description: 'Identifier or dot-separated path of the variable to remove from the variable store.',
          required: true,
        },
      ],
      return: {
        type: 'bool',
        description: '`true` upon successful removal of the variable from the store.',
      },
      see: [
        `${documentation_url}removing-variables-unset`,
      ],
    },
  },
  {
    label: 'var[]',
    kind: 'keyword',
    insertText: 'var[\'${1:name}\']',
    doc: {
      summary: 'Retrieve the value of a local variable',
      signature: 'var[\'name\'] => mixed',
      description: 'Retrieves the value of a variable or dot-separated path from the in-memory variable store (`VariableStore`).\n\nIf the variable is not defined, emits a non-fatal warning diagnostic and returns `null`. Can also be accessed via dot property notation (e.g. `var.total`).\n\nExample:\n\n```elscript\nvar[\'total\']\n```',
      parameters: [
        {
          name: 'name',
          type: 'string',
          description: 'Variable identifier or dot-separated path (e.g. `\'app.settings.per_page\'`) to read from the variable store.',
          required: true,
        },
      ],
      return: {
        type: 'mixed',
        description: 'The current value stored under the specified name or dot path, or `null` if the variable does not exist.',
      },
      see: [
        `${documentation_url}reading-variables-var`,
      ],
    },
  },
];
