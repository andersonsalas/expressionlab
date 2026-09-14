# Contributing to Expression Lab

First off, thank you for considering contributing to Expression Lab! 

Whether you are fixing a typo in the documentation, reporting a bug, or proposing a new helper function, your help is truly appreciated. We aim to keep the contribution process simple, welcoming, and free of unnecessary bureaucracy.

---

## How Can I Contribute?

### 1. Reporting Bugs

If you find a bug or unexpected behavior:

1. Check existing [GitHub Issues](https://github.com/andersonsalas/expressionlab/issues) to make sure it hasn't already been reported.
2. If not, open a new issue with:
   - A clear description of the problem.
   - Steps to reproduce it (including sample DSL code if applicable).
   - Your environment details (PHP version, WordPress version, browser).

### 2. Suggesting Enhancements or New Features

Got an idea to make the DSL more useful or improve the console experience?
- Open an issue describing what you'd like to see, why it would be helpful, and any thoughts on how it might work.
- **Integration Scope**: Expression Lab focuses strictly on WordPress Core and official plugins (such as WooCommerce and Gutenberg). Support for third-party plugins is intentionally out of scope to keep project maintenance sustainable.

### 3. Submitting Code or Documentation Changes

We welcome Pull Requests! Here is how our workflow is structured:

#### Branching Strategy

* **`master`** is the central integration branch and always reflects the latest tested code.
* Working branches should originate from and target `master` via Pull Requests using standard prefixes:
  - `fix/<description>` for bug fixes.
  - `improve/<description>` for performance, documentation, or tooling improvements.
  - `feature/<description>` for new capabilities or helpers.
* Releases are cut directly from `master` using semantic version tags (`v*`).

#### Local Setup & Verification

1. Clone your fork and install dependencies:
   ```bash
   composer install
   pnpm install
   ```
2. Build frontend assets:
   ```bash
   pnpm run build
   ```
3. Before submitting your Pull Request, verify that tests and code style checks pass:
   ```bash
   # Run linters (PHPCS, ESLint, Stylelint)
   pnpm run lint

   # Run automated test suites
   pnpm run test
   ```

#### Commit Messages & Pull Requests

* **No rigid commit dogma**: You don't need to follow strict semantic or conventional commit formatting rules. Just write a clear, human summary of what you changed and why.
* When opening a Pull Request, briefly explain what problem the change solves and how to test it.

---

## Questions or Help?

If you're unsure about anything or need guidance on how the codebase works, don't hesitate to open an issue or reach out. Everyone is learning, and questions are always welcome.
