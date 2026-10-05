---
title: "An Overview of Expression Lab"
description: "I'm Anderson Salas, creator of Expression Lab. In this post, I want to take a closer look at the project: its origins, development challenges, philosophy, use cases, and future vision."
slug: an-overview-of-expression-lab
authors: [andersonsalas]
tags: [writings]
date: 2026-10-05
---

import DownloadButton from '@site/src/components/DownloadButton';

I'm Anderson Salas, creator of Expression Lab. In this post, I want to take a closer look at the project: its origins, the challenges faced during development, its philosophy, its use cases, and its future vision.

New around here? Don't worry. This isn't a tutorial, nor is it dry technical documentation.

<!-- truncate -->

### The origins: trenches of Tier 2 support

One of the wonderful things about WordPress is the endless variety of things you can build with it: there are plugins and themes for virtually anything you can imagine.

I worked as a Tier 2 support engineer for nearly five years. During that time, I handled user-reported issues daily&mdash;some trivial, others far from it. This quickly led me to ask, if every WordPress site is *unique*, how do you troubleshoot an issue that could literally come from anywhere?

If you've been (or currently are) a support engineer like me, these practices will sound very familiar:

1. Uploading an MU plugin containing a `var_dump` or an `error_log`.
2. Installing the [Query Monitor](https://querymonitor.com/) plugin or opening a [WP-CLI](https://wordpress.org/cli/) session&mdash;absolute industry standards and personal favorites of many, myself included.
3. In certain cases, upload an [Adminer](https://www.adminer.org/) script to inspect or dump the database directly (with the user's permission).

All of these are legitimate strategies when used responsibly. However, each tackles the problem from a different angle, almost always demanding an exhausting, multidisciplinary balancing act from the support engineer. It was precisely within that friction where the core idea behind Expression Lab emerged: *What if there was an alternative that could complement all of the above?*

### The challenge: finding a safe alternative to eval()

When we envision an interactive diagnostics environment, our thoughts inevitably gravitate toward running arbitrary PHP code on the server.

PHP already has a built-in construct for this: the controversial and dreaded `eval()`. However, [its own documentation](https://www.php.net/manual/en/function.eval.php) explicitly warns about its severe security implications:

> **Caution**: The **eval()** language construct is *very dangerous* because it allows execution of arbitrary PHP code. *Its use thus is discouraged*. If you have carefully verified that there is no other option than to use this construct, pay special attention *not to pass any user provided data* into it without properly validating it beforehand.

Therefore, doing something like this was *completely* out of the question:

```php
$result = eval($_POST['code']);
```

After extensive research, the answer arrived from an unexpected place: Symfony's [Expression Language](https://symfony.com/doc/current/expression_language.html) component.

As its name suggests, this component evaluates expressions that return a value. It is primarily used as a lightweight engine for embedding dynamic logic into configuration files:

```js
user.getGroup() in ['good_customers', 'collaborator']
```

```js
article.commentCount > 100 and article.category not in ["misc"]
```

Naturally, evaluating static rules in YAML is very different from exploring a relational CMS. The challenge was evolving that lightweight evaluator into a full DSL with fluent entities, sandboxed execution limits, and in-memory SQLite mirroring&mdash;all while ensuring no raw PHP or private keys ever touch the WordPress database. Subsequent development focused on writing a standard library of services to observe the internal state of a live WordPress instance deterministically, and crafting an intuitive, ergonomic UI capable of managing multiple independent execution workflows.

### The philosophy: safe diagnostics should be accessible, not an afterthought

Expression Lab is distributed under the GPLv2 license. This is far more than a legal formality; it is a personal conviction:

1. **Diagnosing an issue shouldn't require risking site stability:** Everyone deserves access to safe, non-destructive tools to understand runtime state without dreading a fatal error.
2. **Diagnostics and development go hand in hand:** Expression Lab is not just a tool for support teams&mdash;it provides high-fidelity telemetry for developers. Both roles can contribute ideas or code to a tool built for the community's benefit.

I firmly believe in the power of great tooling to improve people's workflows and elevate an entire ecosystem.

### Use cases: Where does Expression Lab shine?

Today, Expression Lab is tailored specifically for **local development and staging environments**:

* **Debugging complex queries, transients, or scheduled cron events** without littering your codebase with temporary `var_dump()` calls or leaving cleanup behind.
* **Exploratory analysis and ad-hoc aggregations** through in-memory SQLite, allowing you to slice and inspect data safely without mutating test database tables.
* **Auditing internal WordPress state** in real time during plugin development, giving you immediate feedback through an interactive, visual console.

That said, allow me to be completely candid: **Expression Lab is an experimental prototype in an alpha stage.**

Even though the core was architected around strict security premises, **it has not undergone independent, external security audits**.

For this reason, **it must not be installed under any circumstances on production websites**, nor in environments subject to compliance frameworks (SOC 2, PCI-DSS, HIPAA), critical infrastructure, government systems, military environments, or any application processing credit card data or personally identifiable information (PII).

### The road ahead

Expression Lab doesn't aim to replace established tools; it seeks to offer a complementary plane: a visual, declarative, and deterministic console for understanding how WordPress works from the inside out.

The project is completely open source, and during this alpha phase, what it needs most is critical eyes, testing in local labs, and technical feedback:

* **GitHub Repository:** [andersonsalas/expressionlab](https://github.com/andersonsalas/expressionlab)
* **Official Documentation:** [expressionlab.io](https://expressionlab.io)

If you're passionate about WordPress architecture, install it locally, take it for a spin, and let me know your thoughts in the repository issues and discussions.

<DownloadButton />
