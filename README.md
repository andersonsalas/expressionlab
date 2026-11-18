<p align="center">
  <a href="https://expressionlab.io" target="_blank" rel="noopener noreferrer">
    <img src="assets/img/expressionlab-logo.svg" alt="Expression Lab Logo" width="108" height="108" style="display: block; margin: 0 auto;">
  </a>
</p>

<h1 align="center">Expression Lab</h1>

<p align="center">
  <strong>A Jupyter-like notebook interface and declarative DSL for WordPress.</strong>
</p>

<p align="center">
  <a href="https://github.com/andersonsalas/expressionlab/actions/workflows/lint.yml"><img src="https://github.com/andersonsalas/expressionlab/actions/workflows/lint.yml/badge.svg" alt="Code Style (WPCS)"></a>
  <a href="https://github.com/andersonsalas/expressionlab/actions/workflows/test-php.yml"><img src="https://github.com/andersonsalas/expressionlab/actions/workflows/test-php.yml/badge.svg" alt="PHPUnit Test Matrix"></a>
  <a href="https://github.com/andersonsalas/expressionlab/actions/workflows/test-js.yml"><img src="https://github.com/andersonsalas/expressionlab/actions/workflows/test-js.yml/badge.svg" alt="Jest Tests"></a>
  <img src="https://img.shields.io/badge/PHP-8.4%20--%208.5-777BB4.svg?logo=php&logoColor=white" alt="PHP 8.4 to 8.5">
  <img src="https://img.shields.io/badge/WordPress-6.4%2B-21759B.svg?logo=wordpress&logoColor=white" alt="WordPress 6.4+">
  <img src="https://img.shields.io/badge/Multisite-Supported-success.svg" alt="Multisite Supported">
  <a href="#license"><img src="https://img.shields.io/badge/License-EULA%20%2F%20GPL--2.0--or--later-blue.svg" alt="License: EULA / GPL-2.0-or-later"></a>
</p>

---

**Expression Lab** is an in-browser, **Jupyter-like notebook interface** and diagnostics plugin for WordPress. Powered by a declarative Domain-Specific Language (DSL), it lets you inspect posts, options, database tables, caches, HTTP requests, and files-and visualize results with Vega-Lite charts-without writing raw PHP snippets or using `eval()`.

Key capabilities include multi-cell interactive notebooks and a scratchpad mode, visual query and HTTP request builders, in-memory SQLite analytics (`Database.mirror()`), a filesystem inspector, and encrypted `.elab` notebook storage (Professional Edition).

---

## Security Pillars

Expression Lab is built around four core security pillars to protect your WordPress environment:

* **Declarative AST Sandbox**: Expressions are parsed and evaluated through an isolated Abstract Syntax Tree (AST) engine based on [Symfony Expression Language](https://symfony.com/doc/current/expression_language.html), preventing arbitrary PHP code execution.
* **Client-Side Cryptography**: Authentication uses browser-derived ephemeral Ed25519 keys to sign every request. Master passphrases, private keys, and credentials are never stored in the WordPress database.
* **Operational Boundaries**: Database operations default to read-only access with in-memory SQLite mirroring, filesystem access enforces path canonicalization, and a CPU watchdog enforces execution time limits.
* **Isolated UI & Auditability**: The notebook interface runs inside a sandboxed `iframe` with a `null` origin, and actions are recorded in a built-in audit log.

> [!NOTE]
> Expression Lab has not undergone a formal security audit by an independent third-party security firm. For details on the threat model and boundary limits, see the [Security Model documentation](https://docs.expressionlab.io/security/security-and-environment).

---

## The DSL at a Glance

### Fluent Entity Queries

```javascript
/* Query the latest 5 published posts */
Posts.where('post_type', 'post')
  .where('post_status', 'publish')
  .order_by('post_date', 'DESC')
  .limit(5)
  .get()
```

```javascript
/* Inspect user details and capabilities */
Users.get('anderson')
```

```javascript
/* Inspect registered cron schedules */
Options.get('cron')
```

### In-Memory SQLite Analytics

```javascript
Database.mirror('posts', { 'post_author': 1 }, { 'limit': 100 })
  .query('
    SELECT 
      (SELECT count(*) FROM wp_posts WHERE post_status = "publish") published,
      (SELECT count(*) FROM wp_posts WHERE post_status = "draft") drafts,
      (SELECT count(*) FROM wp_posts WHERE post_status = "trash") trashed
  ')
```

### Functional Pipeline Operations

```javascript
prog[
  set[
    'posts',
    Database.mirror('posts', { 'post_status': 'publish' }, { 'limit': 5 })
      .query('SELECT ID, post_title, comment_count FROM wp_posts')
  ],
  map[
    var['posts'],
    fn[
      ['item'],
      'Post #' ~ args['item']['ID'] 
        ~ ': "' ~ args['item']['post_title'] 
        ~ '" (' ~ args['item']['comment_count'] ~ ' comments)'
    ]
  ]
]
```

---

## System Requirements

| Component | Requirement | Note |
| :--- | :--- | :--- |
| **PHP** | `8.4` &ndash; `8.5` | Tested against PHP 8.4 and 8.5 |
| **WordPress** | `6.4+` | Tested on modern WordPress releases and Core `trunk` |
| **PHP Extensions** | `sodium`, `sqlite3` | `sodium` for Ed25519 signature verification; `sqlite3` for in-memory database mirroring |
| **Environment** | Single-site & Multisite | Automated tests run against both configurations |
| **Browser** | Modern Chromium, Firefox, or Safari | Requires native Web Crypto API and WebAssembly support |

---

## Installation & Setup

### 1. Installation

#### Pre-built ZIP Archive - Professional Edition (Recommended)

Pre-built release packages compiled by the repository's CI/CD pipeline are distributed as `.zip` archives via [GitHub Releases](https://github.com/andersonsalas/expressionlab/releases/latest). These releases ship **Expression Lab Professional Edition** (governed by the [EULA](https://expressionlab.io/eula.html)) with bundled frontend assets and scoped dependencies, installable via **Plugins &rarr; Add New Plugin &rarr; Upload Plugin** in WordPress.

#### From Source - Community Edition (Development)

To build the Community Edition from source:

1. **Clone the repository** into `wp-content/plugins`:
   ```bash
   cd wp-content/plugins
   git clone https://github.com/andersonsalas/expressionlab.git
   cd expressionlab
   ```
2. **Install PHP dependencies**:
   ```bash
   composer install
   ```
   *(For a build without development tooling, use `composer install --no-dev --optimize-autoloader`.)*
3. **Install JavaScript dependencies and compile frontend assets**:
   ```bash
   pnpm install
   pnpm run build
   ```

### 2. Configuration

1. Navigate to **Tools &rarr; Expression Lab** in the WordPress administration dashboard as an administrator.
2. Complete the onboarding wizard by defining a master passphrase.
3. Append the generated constants to `wp-config.php`:

```php
define( 'EXPRESSION_LAB_ADMIN_USER_ID', 1 );
define( 'EXPRESSION_LAB_ADMIN_PUBLIC_KEY', 'your_hex_encoded_public_key' );
define( 'EXPRESSION_LAB_ADMIN_SALT', 'your_unique_salt' );
```

4. Reload the administration interface.

---

## Development & Tooling

```bash
# Linting (PHPCS, ESLint, Stylelint)
pnpm run lint

# Automated Tests
pnpm run test:php       # PHPUnit (Single-site)
pnpm run test:php:ms    # PHPUnit (Multisite)
pnpm run test:php:all   # PHPUnit (Single-site + Multisite)
pnpm run test:js        # Jest (Frontend)

# Frontend Builds
pnpm run dev            # Development build
pnpm run watch          # Incremental watch mode
pnpm run dev-server     # Webpack Encore dev server (HMR)
pnpm run build          # Production minified bundle
```

> [!TIP]
> **Frontend Hot Reload**: Because the sandboxed `iframe` blocks `localStorage` access, disable the iframe sandbox in `wp-config.php` during local UI development (`define( 'EXPRESSION_LAB_SANDBOX_ENABLED', false );`) and keep it enabled otherwise.

### Documentation (Docusaurus)

```bash
pnpm run docs:dev       # Start local docs server (English)
pnpm run docs:dev:es    # Start local docs server (Spanish)
pnpm run docs:build     # Build static docs
pnpm run docs:serve     # Preview built docs
```

---

## Documentation

Full guides and API references are available at [docs.expressionlab.io](https://docs.expressionlab.io) ([Español](https://docs.expressionlab.io/es/)):

* **Getting Started**: [Installation & Configuration](https://docs.expressionlab.io/getting-started/installation) &middot; [Basic Syntax](https://docs.expressionlab.io/getting-started/basic-syntax) &middot; [Scripting](https://docs.expressionlab.io/getting-started/scripting)
* **API Reference**: [Database](https://docs.expressionlab.io/api-reference/database) &middot; [Options](https://docs.expressionlab.io/api-reference/options) &middot; [Cache](https://docs.expressionlab.io/api-reference/cache) &middot; [Network Sites](https://docs.expressionlab.io/api-reference/sites) &middot; [Users](https://docs.expressionlab.io/api-reference/users) &middot; [Posts](https://docs.expressionlab.io/api-reference/posts) &middot; [Media](https://docs.expressionlab.io/api-reference/media) &middot; [Files](https://docs.expressionlab.io/api-reference/files) &middot; [HTTP](https://docs.expressionlab.io/api-reference/http) &middot; [Console](https://docs.expressionlab.io/api-reference/console) &middot; [IP Lookup](https://docs.expressionlab.io/api-reference/ip-lookup) &middot; [Graph](https://docs.expressionlab.io/api-reference/graph)
* **Security & Environment**: [Security Model](https://docs.expressionlab.io/security/security-and-environment)

---

## Contributing & Security Inquiries

* **Contributing**: See [CONTRIBUTING.md](CONTRIBUTING.md) and [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md). Working branches (`fix/*`, `improve/*`, `feature/*`) target `master` via Pull Requests and must pass `pnpm run lint` and `pnpm run test`.
* **Security Inquiries**: Do not report vulnerabilities via public issues. See [SECURITY.md](SECURITY.md) or submit reports via GitHub [Security Advisories](https://github.com/andersonsalas/expressionlab/security) or `github@andersonsalas.com`.

---

## License

* **Professional Edition**: The version distributed via [GitHub Releases](https://github.com/andersonsalas/expressionlab/releases/latest) and compiled by this repository's CI/CD pipeline is **proprietary software** and is governed by the [End-User License Agreement (EULA)](https://expressionlab.io/eula.html).
* **Community Edition**: The standalone base source code hosted in this repository is licensed under the [GNU General Public License v2.0 or later (GPL-2.0-or-later)](LICENSE).
