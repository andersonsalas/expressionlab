/**
 * Data Tree Filter & Path Search Engine.
 *
 * Supports:
 * 1. Free-text recursive search across keys and values.
 * 2. Path expression evaluation in Symfony Expression Language / Dot notation
 *    (e.g. `obj.prop`, `arr[0]`, `obj['key']['nested']`).
 *
 * The engine only walks AST arrays (`properties` / `entries`) with `Array#find`,
 * never indexes plain objects by user-supplied keys, so path segments such as
 * `__proto__` or `constructor` cannot reach or mutate prototypes.
 */

const WHITESPACE_REGEX = /\s/;
const IDENTIFIER_STOP_CHARS = new Set(['.', '[', ']', '\'', '"']);

/**
 * Reads an identifier (dot segment) starting at `index`.
 *
 * @param {string} str
 * @param {number} index
 * @returns {{ value: string, end: number }}
 */
function readIdentifier(str, index) {
  let end = index;
  while (end < str.length && !IDENTIFIER_STOP_CHARS.has(str[end]) && !WHITESPACE_REGEX.test(str[end])) {
    end++;
  }
  return { value: str.slice(index, end), end };
}

/**
 * Reads a bracket segment (`[0]`, `['key']`, `["key"]`) starting at the `[` located at `index`.
 *
 * @param {string} str
 * @param {number} index
 * @returns {{ value: string, end: number }|null} Null on syntax error.
 */
function readBracket(str, index) {
  let i = index + 1;
  while (i < str.length && WHITESPACE_REGEX.test(str[i])) i++;
  if (i >= str.length) return null;

  let value = '';
  const quote = str[i];
  if (quote === '\'' || quote === '"') {
    i++;
    let closed = false;
    while (i < str.length) {
      const ch = str[i];
      if (ch === '\\' && i + 1 < str.length) {
        value += str[i + 1];
        i += 2;
        continue;
      }
      if (ch === quote) {
        closed = true;
        i++;
        break;
      }
      value += ch;
      i++;
    }
    if (!closed) return null;
  } else {
    const start = i;
    while (i < str.length && str[i] !== ']' && str[i] !== '[' && str[i] !== '\'' && str[i] !== '"' && !WHITESPACE_REGEX.test(str[i])) {
      i++;
    }
    value = str.slice(start, i);
    if (!value) return null;
  }

  while (i < str.length && WHITESPACE_REGEX.test(str[i])) i++;
  if (str[i] !== ']') return null;
  return { value, end: i + 1 };
}

/**
 * Tokenizes a path expression into segment keys.
 * Handles:
 * - Dot syntax: foo.bar.baz
 * - Bracket string syntax: foo['bar']["baz"] (backslash escapes allowed inside quotes)
 * - Bracket index syntax: foo[0][1]
 * - Mixed syntax: foo['bar'].baz[0]
 * - Root bracket syntax: [0].foo
 * - Unicode identifiers: usuario.contraseña
 *
 * @param {string} pathStr - Path expression string.
 * @returns {string[]|null} Array of segment tokens, empty for blank input, or null on syntax error.
 */
export function parseExpressionPath(pathStr) {
  if (!pathStr || typeof pathStr !== 'string') return [];
  const str = pathStr.trim();
  if (!str) return [];

  const tokens = [];
  let i = 0;
  // A single leading dot is tolerated (e.g. `.last_checked`).
  let expectIdentifier = false;
  if (str[0] === '.') {
    i = 1;
    expectIdentifier = true;
  }

  while (i < str.length) {
    const ch = str[i];

    if (expectIdentifier || tokens.length === 0) {
      if (ch === '[' && !expectIdentifier) {
        const bracket = readBracket(str, i);
        if (!bracket) return null;
        tokens.push(bracket.value);
        i = bracket.end;
        continue;
      }
      const ident = readIdentifier(str, i);
      if (!ident.value) return null;
      tokens.push(ident.value);
      i = ident.end;
      expectIdentifier = false;
      continue;
    }

    if (ch === '.') {
      expectIdentifier = true;
      i++;
      continue;
    }

    if (ch === '[') {
      const bracket = readBracket(str, i);
      if (!bracket) return null;
      tokens.push(bracket.value);
      i = bracket.end;
      continue;
    }

    // Any other character after a complete segment is a syntax error.
    return null;
  }

  if (expectIdentifier) return null; // Trailing dot.
  return tokens;
}

/**
 * Checks if a string or primitive contains the search term case-insensitively.
 *
 * @param {any} val - Value to check.
 * @param {string} term - Lowercase search term.
 * @returns {boolean}
 */
function textMatches(val, term) {
  if (val === null || val === undefined) return false;
  return String(val).toLowerCase().includes(term);
}

/**
 * Returns the searchable texts of a node itself (excluding its children),
 * aligned with what PhpSerializedTreeNode renders.
 *
 * @param {object} node - AST Node.
 * @returns {Array<any>}
 */
function getNodeOwnTexts(node) {
  switch (node.type) {
    case 'object':
      return [node.className];
    case 'array':
      return [];
    case 'string':
      return [node.value];
    case 'integer':
    case 'double':
      return [node.raw ?? node.value];
    case 'boolean':
      return [node.value ? 'true' : 'false'];
    case 'null':
      return ['null'];
    case 'reference':
      return [`#${node.refIndex}`, node.targetClassName];
    case 'enum':
      return [`${node.className}::${node.caseName}`];
    case 'custom_object':
      return [node.className, node.rawData];
    default:
      return [node.value, node.raw];
  }
}

/**
 * Recursively filters an AST node by free-text search term.
 *
 * A "match" is a rendered row whose label (key / property name) or own value
 * (scalar, class name, enum case...) contains the term. Every matching row in
 * the tree is counted once, including rows nested under an already matching
 * key. When a container label or header matches, its full subtree is kept.
 *
 * @param {object} node - AST Node.
 * @param {string} term - Lowercase search term.
 * @returns {{ node: object|null, count: number, selfMatch: boolean }}
 */
function filterNodeByText(node, term) {
  if (!node || typeof node !== 'object') return { node: null, count: 0, selfMatch: false };

  const selfMatch = getNodeOwnTexts(node).some((text) => textMatches(text, term));

  const isObject = node.type === 'object' && Array.isArray(node.properties);
  const isArray = node.type === 'array' && Array.isArray(node.entries);

  if (!isObject && !isArray) {
    return selfMatch
      ? { node, count: 1, selfMatch: true }
      : { node: null, count: 0, selfMatch: false };
  }

  const children = isObject ? node.properties : node.entries;
  const kept = [];
  let count = selfMatch ? 1 : 0;

  for (const child of children) {
    const label = isObject ? child.name : child.key?.value;
    const labelMatches = textMatches(label, term);
    const childResult = filterNodeByText(child.value, term);

    count += childResult.count;
    if (labelMatches && !childResult.selfMatch) {
      // The row matches through its label only; its value was not counted yet.
      count++;
    }

    if (labelMatches) {
      kept.push(child);
    } else if (childResult.node !== null) {
      kept.push({ ...child, value: childResult.node });
    }
  }

  if (selfMatch) {
    return { node, count, selfMatch: true };
  }

  if (kept.length === 0) {
    return { node: null, count: 0, selfMatch: false };
  }

  const filtered = isObject
    ? { ...node, properties: kept, propertyCount: kept.length }
    : { ...node, entries: kept, count: kept.length };

  return { node: filtered, count, selfMatch: false };
}

/**
 * Resolves a sequence of path tokens starting from an AST node,
 * returning a sliced sub-tree leading to the target node.
 *
 * @param {object} node - AST Node.
 * @param {string[]} tokens - Path segment tokens.
 * @param {number} [index=0] - Current token index.
 * @returns {object|null} Sliced AST Node or null.
 */
function resolveNodeByTokens(node, tokens, index = 0) {
  if (!node) return null;
  if (index >= tokens.length) {
    return node;
  }

  const token = tokens[index];

  if (node.type === 'object' && Array.isArray(node.properties)) {
    const prop = node.properties.find((p) => String(p.name) === token);
    if (prop) {
      const isTarget = index + 1 === tokens.length;
      const child = isTarget ? prop.value : resolveNodeByTokens(prop.value, tokens, index + 1);
      if (child !== null) {
        return {
          ...node,
          properties: [
            {
              ...prop,
              value: child,
            },
          ],
          propertyCount: 1,
        };
      }
    }
    // Check if token 0 is a redundant root alias like "data", "root", "this", or class name
    if (index === 0 && ['data', 'root', 'this', node.className?.toLowerCase()].includes(token.toLowerCase())) {
      return resolveNodeByTokens(node, tokens, 1);
    }
    return null;
  }

  if (node.type === 'array' && Array.isArray(node.entries)) {
    const entry = node.entries.find((e) => String(e.key?.value) === token);
    if (entry) {
      const isTarget = index + 1 === tokens.length;
      const child = isTarget ? entry.value : resolveNodeByTokens(entry.value, tokens, index + 1);
      if (child !== null) {
        return {
          ...node,
          entries: [
            {
              ...entry,
              value: child,
            },
          ],
          count: 1,
        };
      }
    }
    if (index === 0 && ['data', 'root', 'this'].includes(token.toLowerCase())) {
      return resolveNodeByTokens(node, tokens, 1);
    }
    return null;
  }

  return null;
}

/**
 * Searches and filters a Tree AST according to query and mode.
 *
 * @param {object} ast - Root AST node.
 * @param {string} query - Search query string.
 * @param {'text'|'path'} [mode='text'] - Search mode.
 * @returns {{ filteredAst: object|null, matchCount: number }}
 */
export function searchTreeAst(ast, query, mode = 'text') {
  if (!ast) {
    return { filteredAst: null, matchCount: 0 };
  }

  const trimmed = (query || '').trim();
  if (!trimmed) {
    return { filteredAst: ast, matchCount: 0 };
  }

  if (mode === 'path') {
    const tokens = parseExpressionPath(trimmed);
    if (tokens === null) {
      // Invalid path syntax: nothing can match.
      return { filteredAst: null, matchCount: 0 };
    }
    if (tokens.length === 0) {
      return { filteredAst: ast, matchCount: 0 };
    }
    const resolved = resolveNodeByTokens(ast, tokens, 0);
    return {
      filteredAst: resolved,
      matchCount: resolved !== null ? 1 : 0,
    };
  }

  // Free text mode
  const term = trimmed.toLowerCase();
  const { node, count } = filterNodeByText(ast, term);
  return {
    filteredAst: node,
    matchCount: count,
  };
}
