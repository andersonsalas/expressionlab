/**
 * Expression Lab Signature syntax definition for highlight.js.
 *
 * Provides specialized syntax highlighting for method, function, constant,
 * and property signatures shown in autocomplete and outline documentation tooltips.
 *
 * Examples:
 *   - Database.mirror(string:table, [array|null:where = []]) => Database
 *   - format_date(string:format, [int|null:timestamp = null]) => string
 *   - Posts::STATUS_PUBLISHED
 *   - Posts.total
 *
 * @param {import('highlight.js').HLJSApi} hljs
 * @returns {import('highlight.js').Language}
 */
export default function elscriptSignature(hljs) {
  const TYPE_NAMES = [
    'string',
    'int',
    'integer',
    'float',
    'bool',
    'boolean',
    'array',
    'object',
    'mixed',
    'callable',
    'iterable',
    'void',
    'resource',
    'never',
    'null',
    'false',
    'true',
    'number',
  ];

  return {
    name: 'elscript-signature',
    keywords: {
      type: TYPE_NAMES,
      literal: ['null', 'true', 'false'],
    },
    contains: [
      hljs.C_BLOCK_COMMENT_MODE,
      hljs.APOS_STRING_MODE,
      hljs.QUOTE_STRING_MODE,
      hljs.C_NUMBER_MODE,

      // Target object/class before dot or :: (e.g., "Database." or "Posts::")
      {
        className: 'title.class',
        match: /\b[A-Z][a-zA-Z0-9_]*(?=(\.|::))/,
      },

      // Method or function name before ( (e.g., "mirror(" or "format_date(")
      {
        className: 'title.function',
        match: /[a-zA-Z_][a-zA-Z0-9_]*(?=\()/,
      },

      // Return arrow => or ->
      {
        className: 'operator',
        match: /=>|->/,
      },

      // Constant after :: (e.g., "STATUS_PUBLISHED" in "Posts::STATUS_PUBLISHED")
      {
        className: 'variable.constant',
        match: /(?<=::)[a-zA-Z_][a-zA-Z0-9_]*/,
      },

      // Parameter name after single : (e.g., ":table", ":where", ":...args")
      {
        className: 'variable',
        match: /(?<=(^|[^:]):)(\.{3})?[a-zA-Z_][a-zA-Z0-9_]*/,
      },

      // Types that are Class / Object identifiers (e.g., "Database", "User", "WP_Post")
      {
        className: 'type',
        match: /\b[A-Z][a-zA-Z0-9_]*\b/,
      },

      // Property after . (e.g., "total" in "Posts.total")
      {
        className: 'property',
        match: /(?<=\.)[a-zA-Z_][a-zA-Z0-9_]*/,
      },

      // Operators and separators: : | =
      {
        className: 'operator',
        match: /[=:]|\|/,
      },

      // Punctuation: [ ] ( ) ,
      {
        className: 'punctuation',
        match: /[[\](),;]/,
      },
    ],
  };
}
