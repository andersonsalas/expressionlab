/**
 * Expression Lab DSL syntax definition for highlight.js.
 *
 * Provides syntax highlighting for Expression Lab REPL input and outputs.
 *
 * @param {import('highlight.js').HLJSApi} hljs
 * @returns {import('highlight.js').Language}
 */
export default function elscript(hljs) {
  return {
    name: 'elscript',
    aliases: ['expressionlab', 'el'],
    keywords: {
      keyword: [
        'prog',
        'set',
        'var',
        'fn',
        'args',
        'map',
        'filter',
        'show',
        'isset',
        'unset',
        'reduce',
      ],
      built_in: [
        'Posts',
        'Users',
        'Database',
        'Options',
        'NetworkOptions',
        'Media',
        'Files',
        'Http',
        'Console',
        'NetworkSites',
      ],
      literal: [
        'true',
        'false',
        'null',
      ],
    },
    contains: [
      hljs.C_BLOCK_COMMENT_MODE,
      hljs.APOS_STRING_MODE,
      hljs.QUOTE_STRING_MODE,
      hljs.C_NUMBER_MODE,
      {
        className: 'operator',
        match: /~|==|!=|<=|>=|<|>|&&|\|\||[+\-*%!=]|\//,
      },
      {
        className: 'punctuation',
        match: /[[\](),;]/,
      },
    ],
  };
}
