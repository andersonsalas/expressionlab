---
id: basic-syntax
title: Basic Syntax
sidebar_position: 4
---

# Basic Syntax

Expression Lab uses the [Symfony Expression Language component](https://symfony.com/doc/current/expression_language.html) as the foundational engine for evaluating expressions. It provides a declarative Domain-Specific Language designed for querying, transforming data, and interacting with WordPress services without writing raw PHP scripts.

:::success Want to learn more?

See the Symfony's [The Expression Syntax](https://symfony.com/doc/current/reference/formats/expression_language.html) official documentation for further reference.
:::

---

## Language Engine Overview

The Symfony Expression Language component compiles expressions into Abstract Syntax Tree (AST) structures. While expression-oriented (evaluating to a single result without imperative loops like `while` or `for`), Expression Lab extends the base language with a declarative functional scripting layer (`prog[...]`, `set[...]`, `fn[...]`, `map[...]`).

This enables multi-step execution pipelines, expression state management, and lambda transformations while restricting execution to registered operators and functions.

---

## Literals & Primitive Types

Expression Lab supports standard data primitives:

| Type | Syntax Examples | Description |
| :--- | :--- | :--- |
| **Numbers** | `42`, `3.14`, `-10`, `1e3` | Integers and floating-point values. |
| **Strings** | `'hello'`, `"WordPress"`, `"Value with \"quotes\""` | Single or double-quoted strings. |
| **Booleans** | `true`, `false` | Case-insensitive boolean literals. |
| **Null** | `null` | Represents an empty or null value. |

### String Escaping

Strings can be enclosed in single quotes (`'...'`) or double quotes (`"..."`). When you need to include quote marks or backslashes within a string, follow standard escape rules:

- **Single quotes (`'...'`)**: Escape internal single quotes with a backslash (`\'`), e.g., `'It\'s a test'`.
- **Double quotes (`"..."`)**: Escape internal double quotes with a backslash (`\"`), e.g., `"He said \"Hello\""`.
- **Literal backslashes**: Escape backslashes with a double backslash (`\\`), e.g., `'C:\\Windows'` or `"path\\to\\file"`.
- **Pro Tip**: You can alternate quote delimiters to avoid escaping entirely:
  - Use double quotes to enclose single quotes: `"It's a test"`
  - Use single quotes to enclose double quotes: `'He said "Hello"'`

---

## Collections & Data Structures

### Arrays (Lists)

Arrays are comma-separated values enclosed in square brackets `[...]`:

```elscript
[1, 2, 3, "four", true]
```

Elements are accessed via zero-based numeric indexing:

```elscript
users[0]
```

### Maps (Key-Value Objects)

Key-value maps are enclosed in curly braces `{...}`:

```elscript
{ id: 1, title: 'Sample Post', published: true }
```

Property access can be performed using either dot notation or bracket notation:

```elscript
post.title
post['title']
```

---

## Operators

Expression Lab provides a comprehensive set of operators for arithmetic, comparison, logical evaluation, and string manipulation.

### Arithmetic Operators

| Operator | Syntax | Description |
| :--- | :--- | :--- |
| `+` | `10 + 5` | Addition (returns `15`) |
| `-` | `10 - 5` | Subtraction (returns `5`) |
| `*` | `10 * 5` | Multiplication (returns `50`) |
| `/` | `10 / 5` | Division (returns `2`) |
| `%` | `10 % 3` | Modulo (returns `1`) |
| `**` | `2 ** 3` | Exponentiation (returns `8`) |

### String Concatenation

Use the `~` (tilde) operator to concatenate strings:

```elscript
'Hello, ' ~ Users.current.data.display_name ~ '!'
```

### Comparison Operators

| Operator | Syntax | Description |
| :--- | :--- | :--- |
| `==` | `status == 'publish'` | Loose equality |
| `===` | `user_id === 1` | Strict equality |
| `!=` | `post_type != 'revision'` | Loose inequality |
| `!==` | `role !== 'subscriber'` | Strict inequality |
| `<`, `>` | `count > 10` | Less than, greater than |
| `<=`, `>=` | `memory <= 50` | Less than or equal, greater than or equal |

### Logical Operators

| Operator | Syntax | Description |
| :--- | :--- | :--- |
| `and` / `&&` | `is_admin and has_access` | Logical AND |
| `or` / `\|\|` | `is_editor or is_admin` | Logical OR |
| `not` / `!` | `not is_draft` | Logical NOT |

---

## Advanced Operators

#### Ternary Operator (`condition ? true_val : false_val`)

```elscript
user.is_admin ? 'Administrator' : 'Standard User'
```

#### Null Coalescing Operator (`??`)

Returns the first operand if it exists and is not `null`, otherwise returns the second:

```elscript
post.meta['subtitle'] ?? 'No subtitle available'
```

#### Membership Operators (`in`, `not in`)

Check whether an element is present in an array or string:

```elscript
'administrator' in Users.current.roles
```

```elscript
'draft' not in ['publish', 'future', 'private']
```

#### Regular Expression Matching (`matches`)

Test strings against regular expressions:

```elscript
Users.current.data.user_email matches '/^[a-zA-Z0-9._%+-]+@example\\.com$/'
```

---

## Method Calls & Object Access

You can invoke methods on any exposed service or model object:

```elscript
Options.get('active_plugins')
```

Methods can be chained fluidly:

```elscript
Posts.where('post_status','publish').get()
```

---

## Next Steps

- Learn multi-step execution, expression variables, closures, and stream transformations in [Scripting Syntax](./scripting).
- Explore runtime helpers and constants in [Functions and Constants](../api-reference/functions-and-constants).
- Learn how to query and mirror data in [Database](../api-reference/database).
- Configure environment safety brakes and keys in [Configuration](./configuration).
