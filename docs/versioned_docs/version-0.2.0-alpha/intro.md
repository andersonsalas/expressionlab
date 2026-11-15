---
id: intro
title: Introduction
slug: /
sidebar_position: 1
---

# Welcome to Expression Lab

**Expression Lab** is sandboxed diagnostics and data inspection environment for WordPress. It provides a declarative Domain-Specific Language (DSL) based on the **Symfony Expression Language** component to query WordPress internals, inspect data structures, and automate administrative workflows without writing raw PHP scripts or modifying code files.

Expression Lab is free software distributed under the [GNU General Public License v2 (GPLv2)](https://www.gnu.org/licenses/gpl-2.0.html).

---

## Core Principles

* **Declarative Functional DSL**: Powered by an extended [Symfony Expression Language](https://symfony.com/doc/current/expression_language.html) engine. Expressions are compiled into an AST with configurable execution limits, closure validation, and stream operations (`prog[...]`, `map[...]`, `filter[...]`).
* **Client-Side Cryptographic Authentication**: Console sessions derive an **Ed25519** key pair client-side in browser memory using **Argon2id**. Requests are verified on the server against public keys configured in `wp-config.php`, without storing private keys or passwords in the database.
* **Read-Only Exploration by Default**: Inspect databases, taxonomy trees, media metadata, and option stores with optional in-memory SQLite mirroring and read-only defaults for database, filesystem, and network operations.
* **Modern Developer Experience**: A reactive interface built with Vue 3, featuring syntax highlighting, a snippet library, and deep data tree visualization.

---

## A Quick Glimpse

Expression Lab combines familiar object-like queries with functional pipeline composition:

```elscript
/* Get all active cron jobs */
Options.get('cron')
```

```elscript
/* Get details for user 'anderson' */
Users.get('anderson')
```

```elscript
/* Get the last 5 published posts ordered by date */
Posts.where('post_type', 'post')
  .where('post_status', 'publish')
  .order_by('post_date','DESC')
  .limit(5)
  .get()
```

```elscript
/* Build a custom list of posts and their comments count */
prog[ 
    set[
        'comments', 
        Database.mirror( 'posts', { 'post_status' : 'publish' }, { 'limit' : 5 } )
            .query('SELECT ID post_id, comment_count FROM wp_posts') 
    ],
    map[ 
        var['comments'],
        fn[ 
            ['post'],
            'The post ID #' ~ args['post']['post_id'] 
                ~ ' has ' 
                ~ args['post']['comment_count'] 
                ~ ' comment' 
                ~ ( 1 !== intval( args['post']['comment_count'] ) ? 's' : '' )
        ]
    ]
]
```

---

## Where to Go Next

Explore the documentation based on your workflow:

<div className="row" style={{ marginTop: '1.5rem', marginBottom: '1.5rem' }}>
  <div className="col col--6" style={{ marginBottom: '1rem' }}>
    <div className="card" style={{ height: '100%', padding: '1rem' }}>
      <h3>Getting Started</h3>
      <p>Install the plugin, complete the onboarding security wizard, and configure your constants.</p>
      <a href="./getting-started/installation">Installation</a> · <a href="./getting-started/quick-start">Quick Start Guide</a>
    </div>
  </div>
  <div className="col col--6" style={{ marginBottom: '1rem' }}>
    <div className="card" style={{ height: '100%', padding: '1rem' }}>
      <h3>Language &amp; Scripting</h3>
      <p>Master expressions, AST special forms, state bindings, closures, and collection transformations.</p>
      <a href="./getting-started/basic-syntax">Basic Syntax</a> · <a href="./getting-started/scripting">Scripting Syntax</a>
    </div>
  </div>
  <div className="col col--6" style={{ marginBottom: '1rem' }}>
    <div className="card" style={{ height: '100%', padding: '1rem' }}>
      <h3>API Reference</h3>
      <p>Explore fluent query builders for Posts, Users, Database, Media, HTTP, Options, and Files.</p>
      <a href="./api-reference/posts">Posts API</a> · <a href="./api-reference/database">Database API</a> · <a href="./api-reference/users">Users API</a>
    </div>
  </div>
  <div className="col col--6" style={{ marginBottom: '1rem' }}>
    <div className="card" style={{ height: '100%', padding: '1rem' }}>
      <h3>Security Architecture</h3>
      <p>Learn how Argon2id key derivation, Ed25519 signing, and sandbox boundaries safeguard your site.</p>
      <a href="./security/security-and-environment">Security Model</a>
    </div>
  </div>
</div>

