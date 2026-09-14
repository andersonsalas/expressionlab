/**
 * Expression Lab DSL Prism language loader for Docusaurus.
 *
 * Wraps @theme-original/prism-include-languages to retain all default
 * additionalLanguages (php, yaml, etc.) and register elscript & elscriptsignature.
 */

import originalPrismIncludeLanguages from '@theme-original/prism-include-languages';

export default function prismIncludeLanguages(PrismObject) {
  originalPrismIncludeLanguages(PrismObject);

  // Expression Lab DSL grammar definition
  PrismObject.languages.elscript = {
    comment: [
      {
        pattern: /(^|[^\\])\/\*[\s\S]*?(?:\*\/|$)/,
        lookbehind: true,
        greedy: true,
      },
      {
        pattern: /(^|[^\\:])\/\/.*/,
        lookbehind: true,
        greedy: true,
      },
    ],
    string: {
      pattern: /(["'])(?:\\(?:\r\n|[\s\S])|(?!\1)[\s\S])*?\1/,
      greedy: true,
    },
    keyword: /\b(?:prog|set|var|fn|args|map|filter|show|isset|unset|reduce)\b/,
    'class-name': /\b(?:Posts|Users|Database|Options|NetworkOptions|Media|Files|Http|Console|NetworkSites)\b/,
    boolean: /\b(?:true|false)\b/,
    null: {
      pattern: /\bnull\b/,
      alias: 'boolean',
    },
    number: /\b\d+(?:\.\d+)?\b/,
    operator: /~|==|!=|<=|>=|&&|\|\||[+\-*%!=<>]/,
    punctuation: /[[\](),;]/,
  };

  PrismObject.languages.expressionlab = PrismObject.languages.elscript;
  PrismObject.languages.el = PrismObject.languages.elscript;

  // Expression Lab Method & Signature grammar definition
  PrismObject.languages.elscriptsignature = {
    comment: [
      {
        pattern: /(^|[^\\])\/\*[\s\S]*?(?:\*\/|$)/,
        lookbehind: true,
        greedy: true,
      },
      {
        pattern: /(^|[^\\:])\/\/.*/,
        lookbehind: true,
        greedy: true,
      },
    ],
    string: {
      pattern: /(["'])(?:\\(?:\r\n|[\s\S])|(?!\1)[\s\S])*?\1/,
      greedy: true,
    },
    number: /\b\d+(?:\.\d+)?\b/,
    boolean: /\b(?:true|false)\b/,
    null: {
      pattern: /\bnull\b/,
      alias: 'boolean',
    },
    'class-name': {
      pattern: /\b[A-Z]\w*(?=\s*(?:\.|::))/,
    },
    function: /\b[a-zA-Z_]\w*(?=\s*\()/,
    constant: {
      pattern: /(::)[a-zA-Z_]\w*/,
      lookbehind: true,
    },
    variable: {
      pattern: /((?:^|[^:]):)(\.{3})?[a-zA-Z_]\w*/,
      lookbehind: true,
    },
    type: [
      {
        pattern: /\b(?:string|int|integer|float|bool|boolean|array|object|mixed|callable|iterable|void|resource|never|number)\b/,
        alias: 'keyword',
      },
      {
        pattern: /\b[A-Z]\w*\b/,
        alias: 'class-name',
      },
    ],
    property: {
      pattern: /(\.)[a-zA-Z_]\w*/,
      lookbehind: true,
    },
    operator: /=>|->|::|[=:\?]|\|/,
    punctuation: /[[\](),;]/,
  };

  PrismObject.languages['elscript-signature'] = PrismObject.languages.elscriptsignature;
}
