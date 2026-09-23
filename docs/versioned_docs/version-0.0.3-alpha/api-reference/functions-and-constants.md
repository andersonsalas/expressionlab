---
id: functions-and-constants
title: Functions and Constants
sidebar_position: 10
---

# Functions and Constants

Reference built-in runtime constants, state management stores, visualization controls, and transparent bridges to standard PHP and WordPress utility functions.

:::tip In-App Real-Time Documentation
Rather than maintaining static reference pages for hundreds of standard PHP and WordPress functions, Expression Lab features live runtime introspection. You can browse, search, and inspect complete parameter signatures, return types, and documentation in the **Sidebar Outline Explorer** and inline tooltips inside the REPL console.
:::

---

## Runtime Constants

Constants in Expression Lab are immutable values registered in the expression execution context. They can be referenced by name without any dollar sign (`$`) or special prefix.

### Top-Level Engine Constants

Top-level constants are globally accessible within any expression:

| Constant | Type | Description | Example |
| :--- | :--- | :--- | :--- |
| `EXPRESSION_LAB_VERSION` | `string` | Returns the currently active Expression Lab plugin version string. | `EXPRESSION_LAB_VERSION` |

```elscript
/* Check current engine version */
'Running Expression Lab v' ~ EXPRESSION_LAB_VERSION
```

### Service & Object Scoped Constants

Services and domain models expose specific operational constants through object dot-notation:

```elscript
/* Example of an Options service constant */
Options.FORMAT_JSON
```

```elscript
/* Example of an Http service constant */
Http.ALLOWED_OPTIONS
```

:::note Looking for Server/wp-config Constants?
Constants used to configure the host plugin environment, such as `EXPRESSION_LAB_MAX_EXECUTION_LIMIT` or `EXPRESSION_LAB_DATABASE_READONLY`, are native PHP constants configured in `wp-config.php`. See the [Configuration](../getting-started/configuration) guide for complete documentation.
:::

---

## State Management & Special Forms

Variable storage, multi-step sequencing, anonymous lambda closures, and higher-order stream transformations are powered by native engine **Special Forms** (`set[...]`, `var[...]`, `isset[...]`, `unset[...]`, `show[...]`, `prog[...]`, `fn[...]`, `map[...]`, `filter[...]`, `reduce[...]`).

Special forms are built into the Abstract Syntax Tree parser using bracket syntax (`keyword[...]`) rather than parenthesized function calls (`function(...)`).

:::info Complete Scripting Reference
For comprehensive documentation on declaring variables, creating closures, and executing multi-step workflows, see the **[Scripting Syntax Guide](../getting-started/scripting)**.
:::

---

## PHP & WordPress Function Bridges

Expression Lab registers curated sets of native PHP and WordPress helper functions into the expression language namespace. These functions can be called natively like any standard expression function:

```elscript
/* Native PHP string and array functions */
strtoupper(trim('  hello world  '))
json_encode({ status: 'ok', count: 42 })

/* Native WordPress helper functions */
wp_strip_all_tags('<p>Sample Text <a href="#">Link</a></p>')
```

To explore all registered PHP and WordPress functions, open the **Sidebar Outline Explorer** in the REPL console, where every function is listed with interactive documentation, parameter types, default values, and links to official documentation.
