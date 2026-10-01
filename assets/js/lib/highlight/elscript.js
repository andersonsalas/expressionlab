/**
 * Expression Lab DSL syntax definition for highlight.js.
 *
 * Provides syntax highlighting for Expression Lab REPL input and outputs.
 *
 * @param {import('highlight.js').HLJSApi} hljs
 * @returns {import('highlight.js').Language}
 */
export default function elscript(hljs) {
  const NUMBER_MODE = {
    className: 'number',
    variants: [
      { match: /\b0[xX][0-9a-fA-F](_?[0-9a-fA-F])*\b/ },
      { match: /\b0[bB][01](_?[01])*\b/ },
      { match: /\b0[oO][0-7](_?[0-7])*\b/ },
      { match: /(?:\b\d[0-9_]*(?:\.[0-9_]+)?|\B\.[0-9_]+)(?:[eE][+-]?[0-9_]+)?\b/ },
    ],
    relevance: 0,
  };

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
      NUMBER_MODE,
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
