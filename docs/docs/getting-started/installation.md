---
id: installation
title: Installation
sidebar_position: 1
---

import DownloadButton from '@site/src/components/DownloadButton';

# Installation

You can install and activate Expression Lab like any other WordPress plugin. 

:::warning Experimental Alpha Software
Expression Lab is currently an unaudited **alpha prototype** intended for local development, testing, and staging inspection. It is **not suitable for live production environments**, e-commerce stores handling sensitive customer data, or critical infrastructure. For detailed operational boundaries and prohibited use cases, review the [Security Model](../security/security-and-environment.md).
:::

## Requirements

Before installing Expression Lab, ensure your environment meets the following requirements:

### Server Requirements

- **WordPress**: 6.4 or higher (tested up to 7.1)
- **PHP**: 8.2 or higher
- **PHP Extensions**:
  - **[Sodium](https://www.php.net/manual/en/book.sodium.php)** (*Required*): Required for Ed25519 signature verification on incoming console evaluation requests.
  - **[SQLite3](https://www.php.net/manual/en/book.sqlite3.php)** (*Recommended / Optional*): Enables in-memory table mirroring for analytical queries without running ad-hoc analytical queries on the active MySQL database.

### Browser Requirements

Expression Lab relies on client-side cryptographic features during onboarding and console sessions:

- **[Web Crypto API](https://developer.mozilla.org/en-US/docs/Web/API/Web_Crypto_API)**: Required by the browser for client-side Argon2id key derivation and Ed25519 request signing.
- **Secure Context**: Modern browsers require secure contexts (`https://` or `http://localhost` / `127.0.0.1`) to access the Web Crypto API.

---

## Installation Methods

### Method 1: Download Stable Release

Download the latest stable release from the [Expression Lab repository](https://github.com/andersonsalas/expressionlab):

<DownloadButton />

Alternatively, you can install it via [Composer](https://packagist.org/packages/andersonsalas/expressionlab).

### Method 2: Development Installation (from Source)

If you are developing or contributing to Expression Lab:

1. Clone the repository into your WordPress plugins directory:
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/andersonsalas/expressionlab.git
   cd expressionlab
   ```

2. Install PHP and JavaScript dependencies:
   ```bash
   composer install
   pnpm install
   ```

3. Build the assets:
   ```bash
   pnpm build
   ```

4. Activate the plugin via [WP-CLI](https://wordpress.org/cli/) or the WordPress Admin:
   ```bash
   wp plugin activate expressionlab
   ```
