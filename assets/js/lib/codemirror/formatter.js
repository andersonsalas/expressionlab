/**
 * Expression Lab DSL Code Formatter.
 *
 * Formats Expression Lab DSL scripts with canonical indentation, spacing,
 * and structural layout for prog[...] blocks, nested pipelines (map, filter, reduce),
 * objects, arrays, and operators
 */

const KEYWORDS = new Set([
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
]);

const WORD_OPERATORS = new Set([
  'and',
  'or',
  'not',
  'in',
  'matches',
  'startsWith',
  'endsWith',
  'contains',
]);

/**
 * Token types.
 */
const TT = {
  BLOCK_COMMENT: 'BLOCK_COMMENT',
  STRING: 'STRING',
  NUMBER: 'NUMBER',
  IDENTIFIER: 'IDENTIFIER',
  OPERATOR: 'OPERATOR',
  OPEN_BRACKET: 'OPEN_BRACKET',   // (, [, {
  CLOSE_BRACKET: 'CLOSE_BRACKET', // ), ], }
  COMMA: 'COMMA',
  COLON: 'COLON',
  DOT: 'DOT',
  QUESTION: 'QUESTION',
  NEWLINE: 'NEWLINE',
  UNKNOWN: 'UNKNOWN',
};

/**
 * Tokenize Expression Lab DSL code into raw tokens.
 *
 * @param {string} input Source code.
 * @returns {Array<object>} List of tokens.
 */
export function tokenize(input) {
  const tokens = [];
  let pos = 0;
  const len = input.length;

  while (pos < len) {
    const ch = input[pos];

    // 1. Whitespace
    if (ch === ' ' || ch === '\t' || ch === '\r') {
      pos++;
      continue;
    }

    // 2. Newline
    if (ch === '\n') {
      tokens.push({ type: TT.NEWLINE, value: '\n', pos });
      pos++;
      continue;
    }

    // 3. Block comment /* ... */ (No line comments // in Expression Lab DSL)
    if (ch === '/' && input[pos + 1] === '*') {
      const start = pos;
      pos += 2;
      const end = input.indexOf('*/', pos);
      if (end === -1) {
        tokens.push({ type: TT.BLOCK_COMMENT, value: input.slice(start), pos: start });
        pos = len;
      } else {
        tokens.push({ type: TT.BLOCK_COMMENT, value: input.slice(start, end + 2), pos: start });
        pos = end + 2;
      }
      continue;
    }

    // 4. Strings: single and double quotes with backslash escape
    if (ch === '\'' || ch === '"') {
      const start = pos;
      const quote = ch;
      pos++;
      let escaped = false;
      while (pos < len) {
        const c = input[pos];
        if (c === quote && !escaped) {
          pos++;
          break;
        }
        escaped = !escaped && c === '\\';
        pos++;
      }
      tokens.push({ type: TT.STRING, value: input.slice(start, pos), pos: start });
      continue;
    }

    // 5. Numbers: float, scientific, int, leading dot
    if (/\d/.test(ch) || (ch === '.' && /\d/.test(input[pos + 1] || ''))) {
      const start = pos;
      const match = input.slice(pos).match(/^(\d+(\.\d+)?([eE][+-]?\d+)?|\.\d+([eE][+-]?\d+)?)/);
      if (match) {
        tokens.push({ type: TT.NUMBER, value: match[0], pos: start });
        pos += match[0].length;
        continue;
      }
    }

    // 6. Range operator .. and Dot / Null-safe call
    if (ch === '.' && input[pos + 1] === '.') {
      tokens.push({ type: TT.OPERATOR, value: '..', pos });
      pos += 2;
      continue;
    }
    if (ch === '?' && input[pos + 1] === '.') {
      tokens.push({ type: TT.DOT, value: '?.', pos });
      pos += 2;
      continue;
    }
    if (ch === '.') {
      tokens.push({ type: TT.DOT, value: '.', pos });
      pos++;
      continue;
    }

    // 7. Brackets
    if (ch === '(' || ch === '[' || ch === '{') {
      tokens.push({ type: TT.OPEN_BRACKET, value: ch, pos });
      pos++;
      continue;
    }
    if (ch === ')' || ch === ']' || ch === '}') {
      tokens.push({ type: TT.CLOSE_BRACKET, value: ch, pos });
      pos++;
      continue;
    }

    // 8. Delimiters
    if (ch === ',') {
      tokens.push({ type: TT.COMMA, value: ',', pos });
      pos++;
      continue;
    }
    if (ch === ':') {
      tokens.push({ type: TT.COLON, value: ':', pos });
      pos++;
      continue;
    }
    if (ch === '?') {
      if (input[pos + 1] === '?') {
        tokens.push({ type: TT.OPERATOR, value: '??', pos });
        pos += 2;
      } else {
        tokens.push({ type: TT.QUESTION, value: '?', pos });
        pos++;
      }
      continue;
    }

    // 9. Multi-char and single-char operators:
    // ===, !==, ==, !=, <=, >=, &&, ||, **, ~, +, -, *, /, %, <, >, !
    const op3 = input.slice(pos, pos + 3);
    if (op3 === '===' || op3 === '!==') {
      tokens.push({ type: TT.OPERATOR, value: op3, pos });
      pos += 3;
      continue;
    }
    const op2 = input.slice(pos, pos + 2);
    if (op2 === '==' || op2 === '!=' || op2 === '<=' || op2 === '>=' || op2 === '&&' || op2 === '||' || op2 === '**') {
      tokens.push({ type: TT.OPERATOR, value: op2, pos });
      pos += 2;
      continue;
    }
    if (ch === '~' || ch === '+' || ch === '-' || ch === '*' || ch === '/' || ch === '%' || ch === '<' || ch === '>' || ch === '!') {
      tokens.push({ type: TT.OPERATOR, value: ch, pos });
      pos++;
      continue;
    }

    // 10. Identifiers, keywords, word operators
    if (/[a-zA-Z_$]/.test(ch)) {
      const start = pos;
      while (pos < len && /[a-zA-Z0-9_$]/.test(input[pos])) {
        pos++;
      }
      const val = input.slice(start, pos);

      // Check for two-word operator "not in"
      if (val === 'not') {
        let look = pos;
        while (look < len && (input[look] === ' ' || input[look] === '\t')) {
          look++;
        }
        if (input.slice(look, look + 2) === 'in' && !/[a-zA-Z0-9_$]/.test(input[look + 2] || '')) {
          tokens.push({ type: TT.OPERATOR, value: 'not in', pos: start });
          pos = look + 2;
          continue;
        }
      }

      if (WORD_OPERATORS.has(val)) {
        tokens.push({ type: TT.OPERATOR, value: val, pos: start });
      } else {
        tokens.push({ type: TT.IDENTIFIER, value: val, pos: start });
      }
      continue;
    }

    // 11. Other / Unknown characters
    tokens.push({ type: TT.UNKNOWN, value: ch, pos });
    pos++;
  }

  return tokens;
}

/**
 * Builds a bracketed AST node tree from raw tokens.
 *
 * @param {Array<object>} tokens
 * @returns {object} Root node containing items and children.
 */
export function parseBracketTree(tokens) {
  const root = {
    type: 'ROOT',
    items: [[]],
    hasOriginalNewlines: false,
  };

  const stack = [root];

  const matchingBracket = {
    ')': '(',
    ']': '[',
    '}': '{',
  };

  for (let i = 0; i < tokens.length; i++) {
    const token = tokens[i];
    const current = stack[stack.length - 1];
    const currentItem = current.items[current.items.length - 1];

    if (token.type === TT.NEWLINE) {
      current.hasOriginalNewlines = true;
      if (currentItem.length === 0) {
        currentItem.leadingNewlines = (currentItem.leadingNewlines || 0) + 1;
        if (currentItem.leadingNewlines >= 2) {
          currentItem.hasLeadingBlankLine = true;
        }
      }
      continue;
    }

    if (token.type === TT.OPEN_BRACKET) {
      // Look back to see previous token before opening bracket
      let prevToken = null;
      for (let j = currentItem.length - 1; j >= 0; j--) {
        if (currentItem[j].type !== TT.NEWLINE && currentItem[j].type !== TT.BLOCK_COMMENT) {
          prevToken = currentItem[j];
          break;
        }
      }

      const node = {
        type: 'GROUP',
        bracket: token.value,
        prevToken: prevToken,
        items: [[]],
        hasOriginalNewlines: false,
        openToken: token,
      };

      currentItem.push(node);
      stack.push(node);
      continue;
    }

    if (token.type === TT.CLOSE_BRACKET) {
      if (stack.length > 1 && matchingBracket[token.value] === current.bracket) {
        current.closeToken = token;
        stack.pop();
      } else {
        // Unmatched bracket: treat as regular token
        currentItem.push(token);
      }
      continue;
    }

    if (token.type === TT.COMMA) {
      current.items.push([]);
      continue;
    }

    currentItem.push(token);
  }

  return root;
}

/**
 * Formats a node tree back to formatted string.
 */
export class FormatterPrinter {
  constructor(options = {}) {
    this.indentStr = options.indent || '    ';
    this.maxLineLength = options.maxLineLength || 80;
  }

  getIndent(level) {
    return this.indentStr.repeat(level);
  }

  isProgGroup(node) {
    return node.type === 'GROUP' && node.bracket === '[' && node.prevToken?.value === 'prog';
  }

  isSpecialForm(node) {
    return node.type === 'GROUP' && node.bracket === '[' && KEYWORDS.has(node.prevToken?.value);
  }

  formatTree(root) {
    return this.formatItems(root.items, 0, false).trim();
  }

  /**
   * Determine if a group should be formatted multiline.
   */
  shouldGroupBeMultiLine(node, level) {
    // 1. prog[...] is ALWAYS multiline if it has items
    if (this.isProgGroup(node)) {
      return node.items.length > 0 && !(node.items.length === 1 && node.items[0].length === 0);
    }

    // Special forms like set, var, isset, unset, args, fn keep their arguments on the same line
    // e.g. set['result', prog[...]]
    const isSpecialFormCompact = node.bracket === '[' && (
      node.prevToken?.value === 'set' ||
      node.prevToken?.value === 'var' ||
      node.prevToken?.value === 'isset' ||
      node.prevToken?.value === 'unset' ||
      node.prevToken?.value === 'args' ||
      node.prevToken?.value === 'fn'
    );
    if (isSpecialFormCompact) {
      return false;
    }

    // 2. If it contains a nested prog or multiline child, it must be multiline
    for (const item of node.items) {
      for (const tok of item) {
        if (tok.type === 'GROUP' && (this.isProgGroup(tok) || this.shouldGroupBeMultiLine(tok, level + 1))) {
          return true;
        }
      }
    }

    // 3. If it had original newlines and multiple items, keep multiline
    if (node.hasOriginalNewlines && node.items.length > 1) {
      return true;
    }

    // 4. If single line rendering exceeds maxLineLength
    const singleLine = this.renderGroupSingleLine(node);
    if (singleLine.length > this.maxLineLength && node.items.length > 1) {
      return true;
    }

    return false;
  }

  renderGroupSingleLine(node) {
    const parts = [];
    parts.push(node.bracket);
    const itemStrings = [];
    for (const item of node.items) {
      itemStrings.push(this.formatInlineItem(item));
    }
    if (node.bracket === '{' && itemStrings.length > 0) {
      parts.push(' ' + itemStrings.join(', ') + ' ');
    } else {
      parts.push(itemStrings.join(', '));
    }
    const closeBracket = node.bracket === '(' ? ')' : node.bracket === '[' ? ']' : '}';
    parts.push(closeBracket);
    return parts.join('');
  }

  formatInlineItem(item) {
    const parts = [];
    let ternaryDepth = 0;

    for (let i = 0; i < item.length; i++) {
      const tok = item[i];
      if (tok.type === TT.NEWLINE) continue;

      if (tok.type === 'GROUP') {
        parts.push(this.renderGroupSingleLine(tok));
        continue;
      }

      if (tok.type === TT.QUESTION) {
        ternaryDepth++;
        if (parts.length > 0 && !parts[parts.length - 1].endsWith(' ')) {
          parts.push(' ');
        }
        parts.push('? ');
        continue;
      }

      if (tok.type === TT.COLON) {
        if (ternaryDepth > 0) {
          ternaryDepth--;
          if (parts.length > 0 && !parts[parts.length - 1].endsWith(' ')) {
            parts.push(' ');
          }
          parts.push(': ');
        } else {
          while (parts.length > 0 && parts[parts.length - 1] === ' ') {
            parts.pop();
          }
          if (parts.length > 0 && parts[parts.length - 1].endsWith(' ')) {
            parts[parts.length - 1] = parts[parts.length - 1].trimEnd();
          }
          parts.push(': ');
        }
        continue;
      }

      this.appendTokenInline(parts, tok, item[i - 1], item[i + 1]);
    }
    return parts.join('');
  }

  appendTokenInline(parts, token, prev, next) {
    if (token.type === TT.BLOCK_COMMENT) {
      if (parts.length > 0 && !parts[parts.length - 1].endsWith(' ')) {
        parts.push(' ');
      }
      parts.push(token.value);
      if (next && next.type !== TT.COMMA && next.type !== TT.CLOSE_BRACKET) {
        parts.push(' ');
      }
      return;
    }

    if (token.type === TT.OPERATOR) {
      if (token.value === '..') {
        if (parts.length > 0 && parts[parts.length - 1] === ' ') {
          parts.pop();
        } else if (parts.length > 0 && parts[parts.length - 1].endsWith(' ')) {
          parts[parts.length - 1] = parts[parts.length - 1].trimEnd();
        }
        parts.push('..');
        return;
      }

      // Unary operators: ! and unary -
      if (token.value === '!') {
        parts.push('!');
        return;
      }
      if (token.value === 'not') {
        parts.push('not ');
        return;
      }
      if (token.value === '-' && (!prev || prev.type === TT.OPERATOR || prev.type === TT.OPEN_BRACKET || prev.type === TT.COMMA)) {
        parts.push('-');
        return;
      }

      // Binary operator: space around
      if (parts.length > 0 && !parts[parts.length - 1].endsWith(' ')) {
        parts.push(' ');
      }
      parts.push(token.value);
      parts.push(' ');
      return;
    }

    if (token.type === TT.COLON) {
      parts.push(': ');
      return;
    }

    if (token.type === TT.QUESTION) {
      if (parts.length > 0 && !parts[parts.length - 1].endsWith(' ')) {
        parts.push(' ');
      }
      parts.push('? ');
      return;
    }

    if (token.type === TT.DOT) {
      parts.push(token.value);
      return;
    }

    // Default identifier, string, number
    if (prev && (prev.type === TT.IDENTIFIER || prev.type === TT.STRING || prev.type === TT.NUMBER || prev.type === TT.CLOSE_BRACKET)) {
      if (prev.value !== '..' && !parts[parts.length - 1]?.endsWith(' ') && token.type !== TT.DOT) {
        parts.push(' ');
      }
    }

    parts.push(token.value);
  }

  renderCompactGroup(node, level) {
    const parts = [];
    parts.push(node.bracket);
    const itemStrings = [];
    for (const item of node.items) {
      itemStrings.push(this.formatSingleItemMultiLine(item, level));
    }
    if (node.bracket === '{' && itemStrings.length > 0) {
      parts.push(' ' + itemStrings.join(', ') + ' ');
    } else {
      parts.push(itemStrings.join(', '));
    }
    const closeBracket = node.bracket === '(' ? ')' : node.bracket === '[' ? ']' : '}';
    parts.push(closeBracket);
    return parts.join('');
  }

  formatGroup(node, level) {
    const isMulti = this.shouldGroupBeMultiLine(node, level);
    const closeBracket = node.bracket === '(' ? ')' : node.bracket === '[' ? ']' : '}';

    // Check empty group
    const isEmpty = node.items.length === 0 || (node.items.length === 1 && node.items[0].length === 0);
    if (isEmpty) {
      return node.bracket + closeBracket;
    }

    if (!isMulti) {
      return this.renderCompactGroup(node, level);
    }

    // Multiline formatting
    const lines = [];
    lines.push(node.bracket);

    for (let idx = 0; idx < node.items.length; idx++) {
      const item = node.items[idx];
      // Skip empty trailing item
      if (idx === node.items.length - 1 && item.length === 0) continue;

      if (item.hasLeadingBlankLine && idx > 0) {
        lines.push('');
      }

      const itemStr = this.formatSingleItemMultiLine(item, level + 1);
      const isLast = idx === node.items.length - 1;
      const comma = isLast ? '' : ',';
      lines.push(this.getIndent(level + 1) + itemStr + comma);
    }

    lines.push(this.getIndent(level) + closeBracket);
    return lines.join('\n');
  }

  formatSingleItemMultiLine(item, level) {
    const parts = [];
    let ternaryDepth = 0;

    for (let i = 0; i < item.length; i++) {
      const tok = item[i];
      if (tok.type === TT.NEWLINE) continue;

      if (tok.type === 'GROUP') {
        const grpStr = this.formatGroup(tok, level);
        parts.push(grpStr);
        continue;
      }

      if (tok.type === TT.BLOCK_COMMENT) {
        const hasNextCode = item.slice(i + 1).some(t => t.type !== TT.NEWLINE && t.type !== TT.BLOCK_COMMENT);
        if (i === 0 && hasNextCode) {
          parts.push(tok.value + '\n' + this.getIndent(level));
        } else if (i > 0) {
          parts.push(' ' + tok.value);
        } else {
          parts.push(tok.value);
        }
        continue;
      }

      if (tok.type === TT.QUESTION) {
        ternaryDepth++;
        if (parts.length > 0 && !parts[parts.length - 1].endsWith(' ')) {
          parts.push(' ');
        }
        parts.push('? ');
        continue;
      }

      if (tok.type === TT.COLON) {
        if (ternaryDepth > 0) {
          ternaryDepth--;
          if (parts.length > 0 && !parts[parts.length - 1].endsWith(' ')) {
            parts.push(' ');
          }
          parts.push(': ');
        } else {
          while (parts.length > 0 && parts[parts.length - 1] === ' ') {
            parts.pop();
          }
          if (parts.length > 0 && parts[parts.length - 1].endsWith(' ')) {
            parts[parts.length - 1] = parts[parts.length - 1].trimEnd();
          }
          parts.push(': ');
        }
        continue;
      }

      this.appendTokenInline(parts, tok, item[i - 1], item[i + 1]);
    }
    return parts.join('').trim();
  }

  formatItems(items, level, isRoot = true) {
    const result = [];
    for (let i = 0; i < items.length; i++) {
      const item = items[i];
      if (item.length === 0) continue;
      const formatted = this.formatSingleItemMultiLine(item, level);
      result.push((isRoot ? '' : this.getIndent(level)) + formatted);
    }
    return result.join('\n');
  }
}

/**
 * Format Expression Lab DSL script.
 *
 * @param {string} code Source code.
 * @param {object} [options] Formatter options (indent: '    ', maxLineLength: 80).
 * @returns {string} Formatted code.
 */
export function formatExpressionLab(code, options = {}) {
  if (typeof code !== 'string') {
    return '';
  }
  const trimmed = code.trim();
  if (!trimmed) {
    return '';
  }

  try {
    const tokens = tokenize(code);
    if (!tokens.length) {
      return code;
    }

    const tree = parseBracketTree(tokens);
    const printer = new FormatterPrinter(options);
    const formatted = printer.formatTree(tree);

    return formatted || code;
  } catch (e) {
    // Gracefully fallback to original code on parsing error
    return code;
  }
}
