---
id: intro
title: Introducción
slug: /
sidebar_position: 1
---

# Introducción a Expression Lab

**Expression Lab** es un entorno de diagnóstico e inspección de datos para WordPress. Proporciona un Lenguaje de Dominio Específico (DSL) declarativo basado en el componente Symfony Expression Language para consultar estructuras internas de WordPress, inspeccionar datos y automatizar flujos administrativos sin necesidad de escribir scripts PHP directos ni modificar archivos del sistema.

Expression Lab es software libre distribuido bajo la licencia [GNU General Public License v2 (GPLv2)](https://www.gnu.org/licenses/gpl-2.0.html).

---

## Principios fundamentales

* **DSL funcional declarativo**: Impulsado por un motor extendido de [Symfony Expression Language](https://symfony.com/doc/current/expression_language.html). Las expresiones se compilan en un AST con límites de ejecución configurables, validación de *closures* y operaciones de flujo (*streams*) deterministas (`prog[...]`, `map[...]`, `filter[...]`).
* **Autenticación criptográfica en el cliente**: Las sesiones de la consola derivan un par de claves **Ed25519** en la memoria del navegador mediante **Argon2id**. Las peticiones se validan en el servidor frente a la clave pública configurada en `wp-config.php`, sin almacenar contraseñas ni claves privadas en la base de datos.
* **Exploración de solo lectura por defecto**: Permite inspeccionar bases de datos, árboles de taxonomías, metadatos de archivos y opciones con replicación en memoria mediante SQLite y restricciones de solo lectura para base de datos, sistema de archivos y red.
* **Experiencia de desarrollo moderna**: Interfaz reactiva construida con Vue 3, equipada con resaltado de sintaxis, biblioteca de *snippets* (fragmentos de código) y visualización en árbol de estructuras de datos complejas.

---

## Un vistazo rápido

Expression Lab combina consultas fluidas basadas en objetos con la composición funcional de pipelines:

```elscript
/* Obtener la lista completa de cron jobs activos */
Options.get('cron')
```

```elscript
/* Obtener los detalles del usuario 'anderson' */
Users.get('anderson')
```

```elscript
/* Obtener los últimos 5 posts publicados ordenados por fecha */
Posts.where('post_type', 'post')
  .where('post_status', 'publish')
  .order_by('post_date','DESC')
  .limit(5)
  .get()
```

```elscript
/* Construye una lista personalizada de posts y sus numero de comentarios */
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

## Siguientes pasos

Recorrido por la documentación según los requerimientos del entorno:

<div className="row" style={{ marginTop: '1.5rem', marginBottom: '1.5rem' }}>
  <div className="col col--6" style={{ marginBottom: '1rem' }}>
    <div className="card" style={{ height: '100%', padding: '1rem' }}>
      <h3>Primeros pasos</h3>
      <p>Instalación del plugin, asistente de configuración inicial y definición de constantes requeridas.</p>
      <a href="./getting-started/installation">Instalación</a> · <a href="./getting-started/quick-start">Inicio rápido</a>
    </div>
  </div>
  <div className="col col--6" style={{ marginBottom: '1rem' }}>
    <div className="card" style={{ height: '100%', padding: '1rem' }}>
      <h3>Lenguaje y scripting</h3>
      <p>Evaluación de expresiones, formas especiales del AST, variables de expresión, <em>closures</em> y transformaciones de colecciones.</p>
      <a href="./getting-started/basic-syntax">Sintaxis básica</a> · <a href="./getting-started/scripting">Sintaxis de scripting</a>
    </div>
  </div>
  <div className="col col--6" style={{ marginBottom: '1rem' }}>
    <div className="card" style={{ height: '100%', padding: '1rem' }}>
      <h3>Referencia de la API</h3>
      <p>Constructores de consultas para publicaciones, usuarios, base de datos, medios, HTTP, opciones y archivos.</p>
      <a href="./api-reference/posts">API de publicaciones</a> · <a href="./api-reference/database">API de base de datos</a> · <a href="./api-reference/users">API de usuarios</a>
    </div>
  </div>
  <div className="col col--6" style={{ marginBottom: '1rem' }}>
    <div className="card" style={{ height: '100%', padding: '1rem' }}>
      <h3>Arquitectura de seguridad</h3>
      <p>Derivación de claves Argon2id, firmas Ed25519 y restricciones del <em>sandbox</em> de ejecución.</p>
      <a href="./security/security-and-environment">Seguridad</a>
    </div>
  </div>
</div>
