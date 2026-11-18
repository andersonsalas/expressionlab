---
id: configuration
title: Configuration
sidebar_position: 3
---

# Configuration

Expression Lab behavior and runtime parameters are controlled via native PHP constants defined in your WordPress `wp-config.php` file.

Because Expression Lab operates as a sandboxed REPL environment within your WordPress installation, configuring these constants allows you to adjust security boundaries, execution timeouts, and resource locks according to your hosting environment and operational needs.

---

## Authentication & Security Constants

These constants are established during the initial setup wizard and authenticate encrypted console sessions using public-key cryptography.

| Constant | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `EXPRESSION_LAB_ADMIN_USER_ID` | `int` | *None* | **Required.** The WordPress User ID authorized to initialize and access the Expression Lab console. |
| `EXPRESSION_LAB_ADMIN_PUBLIC_KEY` | `string` | *None* | **Required.** The Base64-encoded Ed25519 public key derived client-side from your master admin passphrase. |
| `EXPRESSION_LAB_ADMIN_SALT` | `string` | *None* | **Required.** The Base64-encoded salt used for Argon2id key derivation during onboarding and session unlock. |

:::important Passphrase & Key Reset
If you lose your master passphrase, you must delete these three authentication constants (`EXPRESSION_LAB_ADMIN_USER_ID`, `EXPRESSION_LAB_ADMIN_PUBLIC_KEY`, and `EXPRESSION_LAB_ADMIN_SALT`) from your `wp-config.php` file. Upon reloading the admin panel, the onboarding wizard will reappear to guide you through generating new credentials.
:::

---

## Sandbox & Isolation Constants

| Constant | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `EXPRESSION_LAB_SIGNATURE_DURATION` | `int` | `120` | Lifetime in seconds of cryptographic request signatures before they expire. |
| `EXPRESSION_LAB_SANDBOX_ENABLED` | `bool` | `true` | When enabled, renders the console UI within a sandboxed iframe and enforces end-to-end cryptographic signature verification on all incoming requests. |
| `EXPRESSION_LAB_HOOKS_ENABLED` | `bool` | `false` | When enabled, allows third-party WordPress plugins and themes to extend Expression Lab with custom objects, functions, and constants via WordPress action/filter hooks. |

:::warning Security Boundary Considerations
- **`EXPRESSION_LAB_SANDBOX_ENABLED`**: Disabling this setting removes the iframe isolation and signature checks. **Do not disable this in production environments.**
- **`EXPRESSION_LAB_HOOKS_ENABLED`**: Third-party hooks run within the host WordPress PHP context and can potentially bypass expression sandboxing. Enable this setting only when actively developing or integrating custom extensions.
:::

---

## Execution Timeout & Resource Limits

Expression Lab includes operational limits to prevent runaway execution loops, high server load, and accidental data mutation during expression evaluation.

| Constant | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `EXPRESSION_LAB_DATABASE_READONLY` | `bool` | `true` | Restricts database access to read-only mode during expression evaluation. Blocks direct write queries (`INSERT`, `UPDATE`, `DELETE`, `DROP`, `ALTER`) against the active database. |
| `EXPRESSION_LAB_FILESYSTEM_READONLY` | `bool` | `true` | Restricts filesystem access to read-only operations during expression evaluation, preventing file creation, modification, or deletion. |
| `EXPRESSION_LAB_NETWORK_READONLY` | `bool` | `true` | Restricts network activity by blocking outgoing HTTP/HTTPS requests initiated during evaluations. |
| `EXPRESSION_LAB_MAX_EXECUTION_LIMIT` | `int` | `2` | Maximum CPU execution time in seconds allowed for a single expression before execution is halted. |

---

## System & Diagnostics Constants

| Constant | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `EXPRESSION_LAB_DEBUG_MODE` | `bool` | `false` | Enables internal plugin debugging and bypasses iframe sandbox isolation for development. |
| `EXPRESSION_LAB_BROWSER_LOG` | `bool` | `false` | Directs internal engine diagnostic logs to the browser console. |
| `EXPRESSION_LAB_CACHE` | `bool` | `true` | Controls client-side caching for the sidebar outline (`el_outline_cache` in `localStorage`), and is reserved for future caching operations across both frontend and backend. |
| `EXPRESSION_LAB_DISABLE_SQLITE` | `bool` | `false` | Disables the in-memory SQLite3 mirroring engine fallback when SQLite3 is unavailable or undesirable on the host server. |

:::danger Do Not Enable EXPRESSION_LAB_DEBUG_MODE in Production
`EXPRESSION_LAB_DEBUG_MODE` is intended solely for **local development and debugging of the plugin itself**. Enabling this constant suppresses essential security controls:

* **Bypasses Public Key Signature Checks**: Eliminates Ed25519 signature verification on the REST API endpoint, allowing any logged-in user matching `EXPRESSION_LAB_ADMIN_USER_ID` to run expressions without cryptographic authentication.
* **Removes Sandbox Iframe Isolation**: Renders the console application into the WordPress admin page DOM instead of an isolated iframe.

While `EXPRESSION_LAB_DEBUG_MODE` retains the authorized WordPress user ID check, requests run as standard unverified AJAX calls without cryptographic signatures. **Do not enable this constant on production or publicly accessible sites.**
:::