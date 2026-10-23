/**
 * SQLite Query Linter for Expression Lab
 *
 * Lightweight, zero-dependency syntactic validator for SQLite queries in CodeMirror 6.
 * Validates quotes, brackets, comments, parenthesis nesting, clause sequencing,
 * and incomplete statement tails with precise character, line, and column coordinates.
 */

/**
 * Calculates 1-indexed line and column numbers from a character index in a string.
 *
 * @param {string} text
 * @param {number} index
 * @returns {{ line: number, column: number }}
 */
export function getLineAndColumn(text, index) {
  const safeIndex = Math.max(0, Math.min(index, text.length));
  const lines = text.slice(0, safeIndex).split('\n');
  const line = lines.length;
  const column = lines[lines.length - 1].length + 1;
  return { line, column };
}

/**
 * Validates a SQL query string against SQLite syntax rules.
 *
 * @param {string} sql
 * @returns {{
 *   isValid: boolean,
 *   error: string|null,
 *   from?: number,
 *   to?: number,
 *   line?: number,
 *   column?: number
 * }}
 */
export function validateSql(sql) {
  if (typeof sql !== 'string') {
    return { isValid: true, error: null };
  }

  const trimmed = sql.trim();
  if (!trimmed) {
    return { isValid: true, error: null };
  }

  const len = sql.length;
  let i = 0;

  // Track parenthetical nesting: array of { index, line, column }
  const parenStack = [];

  // Tokens collected for clause checking: array of { text, upper, from, to, line, column }
  const tokens = [];

  while (i < len) {
    const ch = sql[i];

    // 1. Whitespace
    if (/\s/.test(ch)) {
      i++;
      continue;
    }

    // 2. Line comment: --
    if (ch === '-' && i + 1 < len && sql[i + 1] === '-') {
      i += 2;
      while (i < len && sql[i] !== '\n') {
        i++;
      }
      continue;
    }

    // 3. Block comment: /* ... */
    if (ch === '/' && i + 1 < len && sql[i + 1] === '*') {
      const commentStart = i;
      const { line, column } = getLineAndColumn(sql, commentStart);
      i += 2;
      let closed = false;
      while (i < len - 1) {
        if (sql[i] === '*' && sql[i + 1] === '/') {
          closed = true;
          i += 2;
          break;
        }
        i++;
      }
      if (!closed) {
        return {
          isValid: false,
          error: `Unterminated block comment '/*' (line ${line}, column ${column})`,
          from: commentStart,
          to: len,
          line,
          column
        };
      }
      continue;
    }

    // 4. String literal: '...' (escaped with '')
    if (ch === '\'') {
      const strStart = i;
      const { line, column } = getLineAndColumn(sql, strStart);
      i++;
      let closed = false;
      while (i < len) {
        if (sql[i] === '\'') {
          if (i + 1 < len && sql[i + 1] === '\'') {
            i += 2; // escaped quote
            continue;
          }
          closed = true;
          i++;
          break;
        }
        i++;
      }
      if (!closed) {
        return {
          isValid: false,
          error: `Unterminated string literal (line ${line}, column ${column})`,
          from: strStart,
          to: len,
          line,
          column
        };
      }
      tokens.push({
        text: sql.slice(strStart, i),
        upper: '<STRING>',
        from: strStart,
        to: i,
        line,
        column
      });
      continue;
    }

    // 5. Quoted Identifiers: "...", `...`, [...]
    if (ch === '"' || ch === '`' || ch === '[') {
      const closingChar = ch === '[' ? ']' : ch;
      const identStart = i;
      const { line, column } = getLineAndColumn(sql, identStart);
      i++;
      let closed = false;
      while (i < len) {
        if (sql[i] === closingChar) {
          closed = true;
          i++;
          break;
        }
        i++;
      }
      if (!closed) {
        return {
          isValid: false,
          error: `Unterminated quoted identifier '${ch}' (line ${line}, column ${column})`,
          from: identStart,
          to: len,
          line,
          column
        };
      }
      tokens.push({
        text: sql.slice(identStart, i),
        upper: '<IDENT>',
        from: identStart,
        to: i,
        line,
        column
      });
      continue;
    }

    // 6. Parentheses
    if (ch === '(') {
      const { line, column } = getLineAndColumn(sql, i);
      parenStack.push({ index: i, line, column });
      tokens.push({ text: '(', upper: '(', from: i, to: i + 1, line, column });
      i++;
      continue;
    }

    if (ch === ')') {
      const { line, column } = getLineAndColumn(sql, i);
      if (parenStack.length === 0) {
        return {
          isValid: false,
          error: `Unexpected closing parenthesis ')' (line ${line}, column ${column})`,
          from: i,
          to: i + 1,
          line,
          column
        };
      }
      parenStack.pop();
      tokens.push({ text: ')', upper: ')', from: i, to: i + 1, line, column });
      i++;
      continue;
    }

    // 7. Parameter placeholders: ? or ?NNN, :name, @name, $name
    if (ch === '?') {
      const paramStart = i;
      const { line, column } = getLineAndColumn(sql, paramStart);
      i++;
      while (i < len && /[0-9]/.test(sql[i])) {
        i++;
      }
      tokens.push({
        text: sql.slice(paramStart, i),
        upper: '<PARAM>',
        from: paramStart,
        to: i,
        line,
        column
      });
      continue;
    }

    if (ch === ':' || ch === '@' || (ch === '$' && i + 1 < len && /[a-zA-Z0-9_]/.test(sql[i + 1]))) {
      const paramStart = i;
      const { line, column } = getLineAndColumn(sql, paramStart);
      i++;
      while (i < len && /[a-zA-Z0-9_$]/.test(sql[i])) {
        i++;
      }
      tokens.push({
        text: sql.slice(paramStart, i),
        upper: '<PARAM>',
        from: paramStart,
        to: i,
        line,
        column
      });
      continue;
    }

    // 8. Punctuation / Operators
    if (',;.=<>!+-*/%|&~^'.includes(ch)) {
      const opStart = i;
      const { line, column } = getLineAndColumn(sql, opStart);
      let opText = ch;
      i++;
      if (i < len) {
        const next = sql[i];
        if (
          (ch === '<' && (next === '=' || next === '>' || next === '<')) ||
          (ch === '>' && (next === '=' || next === '>')) ||
          (ch === '!' && next === '=') ||
          (ch === '=' && next === '=') ||
          (ch === '|' && next === '|')
        ) {
          opText += next;
          i++;
        }
      }
      tokens.push({
        text: opText,
        upper: opText,
        from: opStart,
        to: i,
        line,
        column
      });
      continue;
    }

    // 9. Word tokens (Keywords, identifiers, numbers)
    if (/[a-zA-Z0-9_]/.test(ch)) {
      const wordStart = i;
      const { line, column } = getLineAndColumn(sql, wordStart);
      while (i < len && /[a-zA-Z0-9_$]/.test(sql[i])) {
        i++;
      }
      const rawWord = sql.slice(wordStart, i);
      tokens.push({
        text: rawWord,
        upper: rawWord.toUpperCase(),
        from: wordStart,
        to: i,
        line,
        column
      });
      continue;
    }

    // 10. Fallback for any other character (guarantees advancement to prevent infinite loop)
    const fallbackStart = i;
    const { line, column } = getLineAndColumn(sql, fallbackStart);
    i++;
    tokens.push({
      text: ch,
      upper: ch,
      from: fallbackStart,
      to: i,
      line,
      column
    });
  }

  // Check unclosed parentheses
  if (parenStack.length > 0) {
    const lastUnclosed = parenStack[parenStack.length - 1];
    return {
      isValid: false,
      error: `Unclosed parenthesis '(' (line ${lastUnclosed.line}, column ${lastUnclosed.column})`,
      from: lastUnclosed.index,
      to: lastUnclosed.index + 1,
      line: lastUnclosed.line,
      column: lastUnclosed.column
    };
  }

  // Filter out semicolons at end
  while (tokens.length > 0 && tokens[tokens.length - 1].upper === ';') {
    tokens.pop();
  }

  if (tokens.length === 0) {
    return { isValid: true, error: null };
  }

  // 9. Root keyword check
  const firstToken = tokens[0];
  const ALLOWED_ROOTS = ['SELECT', 'WITH', 'EXPLAIN', 'PRAGMA'];
  if (!ALLOWED_ROOTS.includes(firstToken.upper)) {
    return {
      isValid: false,
      error: `Expected SELECT statement, found '${firstToken.text}' (line ${firstToken.line}, column ${firstToken.column})`,
      from: firstToken.from,
      to: firstToken.to,
      line: firstToken.line,
      column: firstToken.column
    };
  }

  // 10. Check comma errors
  for (let t = 0; t < tokens.length; t++) {
    const cur = tokens[t];
    if (cur.upper === ',') {
      // Duplicate comma: ,,
      if (t + 1 < tokens.length && tokens[t + 1].upper === ',') {
        const next = tokens[t + 1];
        return {
          isValid: false,
          error: `Unexpected duplicate comma (line ${next.line}, column ${next.column})`,
          from: next.from,
          to: next.to,
          line: next.line,
          column: next.column
        };
      }
      // Comma before clause: , FROM / , WHERE / , ORDER
      if (t + 1 < tokens.length) {
        const nextUpper = tokens[t + 1].upper;
        if (['FROM', 'WHERE', 'ORDER', 'GROUP', 'HAVING', 'LIMIT', 'OFFSET', 'UNION'].includes(nextUpper)) {
          const next = tokens[t + 1];
          return {
            isValid: false,
            error: `Unexpected token '${next.text}' after comma (line ${next.line}, column ${next.column})`,
            from: next.from,
            to: next.to,
            line: next.line,
            column: next.column
          };
        }
      }
    }
  }

  // 11. Check clauses sequence in top-level query (outside nested parentheses)
  let parenDepth = 0;
  let hasSelect = false;
  let hasFrom = false;
  let hasWhere = false;
  let hasGroupBy = false;
  let hasHaving = false;
  let hasOrderBy = false;
  let hasLimit = false;

  for (let t = 0; t < tokens.length; t++) {
    const cur = tokens[t];

    if (cur.upper === '(') {
      parenDepth++;
      continue;
    }
    if (cur.upper === ')') {
      parenDepth--;
      continue;
    }

    if (parenDepth === 0) {
      if (cur.upper === 'SELECT') {
        hasSelect = true;
        // Check if immediately followed by FROM: SELECT FROM
        if (t + 1 < tokens.length && tokens[t + 1].upper === 'FROM') {
          const next = tokens[t + 1];
          return {
            isValid: false,
            error: `Unexpected token 'FROM'. Expected column expressions after SELECT (line ${next.line}, column ${next.column})`,
            from: next.from,
            to: next.to,
            line: next.line,
            column: next.column
          };
        }
      } else if (cur.upper === 'FROM') {
        hasFrom = true;
      } else if (cur.upper === 'WHERE') {
        if (hasOrderBy) {
          return {
            isValid: false,
            error: `WHERE clause cannot follow ORDER BY (line ${cur.line}, column ${cur.column})`,
            from: cur.from,
            to: cur.to,
            line: cur.line,
            column: cur.column
          };
        }
        if (hasLimit) {
          return {
            isValid: false,
            error: `WHERE clause cannot follow LIMIT (line ${cur.line}, column ${cur.column})`,
            from: cur.from,
            to: cur.to,
            line: cur.line,
            column: cur.column
          };
        }
        hasWhere = true;
      } else if (cur.upper === 'GROUP') {
        if (hasOrderBy) {
          return {
            isValid: false,
            error: `GROUP BY clause cannot follow ORDER BY (line ${cur.line}, column ${cur.column})`,
            from: cur.from,
            to: cur.to,
            line: cur.line,
            column: cur.column
          };
        }
        if (hasLimit) {
          return {
            isValid: false,
            error: `GROUP BY clause cannot follow LIMIT (line ${cur.line}, column ${cur.column})`,
            from: cur.from,
            to: cur.to,
            line: cur.line,
            column: cur.column
          };
        }
        hasGroupBy = true;
      } else if (cur.upper === 'ORDER') {
        if (hasLimit) {
          return {
            isValid: false,
            error: `ORDER BY clause cannot follow LIMIT (line ${cur.line}, column ${cur.column})`,
            from: cur.from,
            to: cur.to,
            line: cur.line,
            column: cur.column
          };
        }
        hasOrderBy = true;
      } else if (cur.upper === 'LIMIT') {
        hasLimit = true;
      }
    }
  }

  // 12. Check incomplete statement tail at EOF
  const lastToken = tokens[tokens.length - 1];
  const INCOMPLETE_KEYWORDS = [
    'SELECT', 'FROM', 'WHERE', 'ORDER', 'GROUP', 'BY', 'HAVING', 'LIMIT',
    'OFFSET', 'JOIN', 'LEFT', 'RIGHT', 'INNER', 'CROSS', 'ON', 'USING',
    'IN', 'BETWEEN', 'AND', 'OR', 'NOT', 'LIKE', 'GLOB', 'MATCH', 'REGEXP',
    'IS', 'AS', 'CASE', 'WHEN', 'THEN', 'ELSE',
    ',', '=', '<', '>', '<=', '>=', '!=', '<>', '==', '+', '-', '*', '/', '%', '||', '<<', '>>', '&', '|', '^'
  ];

  if (INCOMPLETE_KEYWORDS.includes(lastToken.upper)) {
    return {
      isValid: false,
      error: `Incomplete statement: expected expression after '${lastToken.text}' (line ${lastToken.line}, column ${lastToken.column})`,
      from: lastToken.from,
      to: lastToken.to,
      line: lastToken.line,
      column: lastToken.column
    };
  }

  return { isValid: true, error: null };
}

/**
 * Creates a CodeMirror 6 linter extension for SQLite queries.
 *
 * @param {import('@codemirror/lint').linter} cmLinter - The linter function from @codemirror/lint
 * @returns {import('@codemirror/state').Extension}
 */
export function createSqlLinter(cmLinter) {
  return cmLinter((view) => {
    const doc = view.state.doc.toString();
    const result = validateSql(doc);

    if (!result.isValid && result.error) {
      const docLen = view.state.doc.length;
      const from = Math.min(result.from ?? 0, docLen);
      const to = Math.min(Math.max(from + 1, result.to ?? (from + 1)), docLen);

      return [
        {
          from,
          to,
          severity: 'error',
          message: result.error
        }
      ];
    }

    return [];
  });
}

/**
 * Formats and indents an SQLite SQL query with canonical keyword capitalization and line breaking.
 *
 * @param {string} sql - Raw SQL string
 * @returns {string} Formatted SQL query
 */
export function formatSql(sql) {
  if (!sql || typeof sql !== 'string' || !sql.trim()) return '';
  const trimmed = sql.trim().replace(/;+$/, '');

  const len = trimmed.length;
  let i = 0;
  const rawTokens = [];

  while (i < len) {
    const ch = trimmed[i];
    if (/\s/.test(ch)) {
      i++;
      continue;
    }
    // line comment
    if (ch === '-' && i + 1 < len && trimmed[i + 1] === '-') {
      const start = i;
      i += 2;
      while (i < len && trimmed[i] !== '\n') i++;
      rawTokens.push({ type: 'COMMENT', text: trimmed.slice(start, i) });
      continue;
    }
    // block comment
    if (ch === '/' && i + 1 < len && trimmed[i + 1] === '*') {
      const start = i;
      i += 2;
      while (i < len - 1 && !(trimmed[i] === '*' && trimmed[i + 1] === '/')) i++;
      i += 2;
      rawTokens.push({ type: 'COMMENT', text: trimmed.slice(start, i) });
      continue;
    }
    // string literal
    if (ch === '\'') {
      const start = i;
      i++;
      while (i < len) {
        if (trimmed[i] === '\'') {
          if (i + 1 < len && trimmed[i + 1] === '\'') {
            i += 2;
            continue;
          }
          i++;
          break;
        }
        i++;
      }
      rawTokens.push({ type: 'STRING', text: trimmed.slice(start, i) });
      continue;
    }
    // quoted identifier
    if (ch === '"' || ch === '`' || ch === '[') {
      const close = ch === '[' ? ']' : ch;
      const start = i;
      i++;
      while (i < len && trimmed[i] !== close) i++;
      if (i < len) i++;
      rawTokens.push({ type: 'IDENT', text: trimmed.slice(start, i) });
      continue;
    }
    // parens & commas & dot & semicolon
    if (ch === '(' || ch === ')' || ch === ',' || ch === ';') {
      rawTokens.push({ type: ch, text: ch });
      i++;
      continue;
    }
    if (ch === '.') {
      rawTokens.push({ type: '.', text: '.' });
      i++;
      continue;
    }
    // parameters: ? or ?NNN, :name, @name, $name
    if (ch === '?') {
      const start = i;
      i++;
      while (i < len && /[0-9]/.test(trimmed[i])) i++;
      rawTokens.push({ type: 'PARAM', text: trimmed.slice(start, i) });
      continue;
    }
    if (ch === ':' || ch === '@' || (ch === '$' && i + 1 < len && /[a-zA-Z0-9_]/.test(trimmed[i + 1]))) {
      const start = i;
      i++;
      while (i < len && /[a-zA-Z0-9_$]/.test(trimmed[i])) i++;
      rawTokens.push({ type: 'PARAM', text: trimmed.slice(start, i) });
      continue;
    }
    // operators
    if ('=<>!+-*/%|&~^'.includes(ch)) {
      let op = ch;
      i++;
      if (i < len) {
        const next = trimmed[i];
        if (
          (ch === '<' && (next === '=' || next === '>' || next === '<')) ||
          (ch === '>' && (next === '=' || next === '>')) ||
          (ch === '!' && next === '=') ||
          (ch === '=' && next === '=') ||
          (ch === '|' && next === '|')
        ) {
          op += next;
          i++;
        }
      }
      rawTokens.push({ type: 'OPERATOR', text: op });
      continue;
    }
    // word / number
    if (/[a-zA-Z0-9_]/.test(ch)) {
      const start = i;
      while (i < len && /[a-zA-Z0-9_$]/.test(trimmed[i])) i++;
      const word = trimmed.slice(start, i);
      rawTokens.push({ type: 'WORD', text: word });
      continue;
    }
    // Fallback: single character to guarantee progress
    rawTokens.push({ type: 'CHAR', text: ch });
    i++;
  }

  const KEYWORDS = new Set([
    'SELECT', 'DISTINCT', 'ALL', 'AS', 'FROM', 'WHERE', 'AND', 'OR', 'NOT',
    'IN', 'IS', 'NULL', 'LIKE', 'BETWEEN', 'JOIN', 'INNER', 'LEFT', 'RIGHT',
    'CROSS', 'OUTER', 'ON', 'USING', 'GROUP', 'BY', 'HAVING', 'ORDER',
    'ASC', 'DESC', 'LIMIT', 'OFFSET', 'UNION', 'EXISTS', 'CASE', 'WHEN',
    'THEN', 'ELSE', 'END', 'CAST', 'WITH', 'PRAGMA', 'EXPLAIN', 'COUNT', 'SUM', 'AVG', 'MIN', 'MAX'
  ]);

  // Combine compound keywords
  const tokens = [];
  for (let k = 0; k < rawTokens.length; k++) {
    const cur = rawTokens[k];
    const next = rawTokens[k + 1];
    const curUpper = cur.text.toUpperCase();
    const nextUpper = next?.text?.toUpperCase();

    if (cur.type === 'WORD' && next?.type === 'WORD') {
      if ((curUpper === 'ORDER' || curUpper === 'GROUP') && nextUpper === 'BY') {
        tokens.push({ type: 'CLAUSE', text: `${curUpper} BY` });
        k++;
        continue;
      }
      if (
        (curUpper === 'LEFT' || curUpper === 'RIGHT' || curUpper === 'INNER' || curUpper === 'CROSS') &&
        nextUpper === 'JOIN'
      ) {
        tokens.push({ type: 'JOIN', text: `${curUpper} JOIN` });
        k++;
        continue;
      }
      if (curUpper === 'UNION' && nextUpper === 'ALL') {
        tokens.push({ type: 'CLAUSE', text: 'UNION ALL' });
        k++;
        continue;
      }
    }

    if (cur.type === 'WORD') {
      if (KEYWORDS.has(curUpper)) {
        if (['SELECT', 'FROM', 'WHERE', 'HAVING', 'LIMIT', 'OFFSET'].includes(curUpper)) {
          tokens.push({ type: 'CLAUSE', text: curUpper });
        } else if (curUpper === 'JOIN') {
          tokens.push({ type: 'JOIN', text: 'JOIN' });
        } else if (curUpper === 'AND' || curUpper === 'OR') {
          tokens.push({ type: 'LOGIC', text: curUpper });
        } else {
          tokens.push({ type: 'KEYWORD', text: curUpper });
        }
      } else {
        tokens.push({ type: 'IDENT', text: cur.text });
      }
    } else {
      tokens.push(cur);
    }
  }

  // Format into lines
  let result = '';
  let parenDepth = 0;
  let inSelect = false;
  let selectItemCount = 0;

  for (let t = 0; t < tokens.length; t++) {
    const tok = tokens[t];
    const prev = tokens[t - 1];

    if (tok.type === '(') {
      parenDepth++;
    } else if (tok.type === ')') {
      parenDepth = Math.max(0, parenDepth - 1);
    }

    if (parenDepth === 0) {
      if (tok.type === 'CLAUSE') {
        if (tok.text === 'SELECT') {
          inSelect = true;
          // Count commas before FROM at parenDepth 0
          let commas = 0;
          let subParen = 0;
          for (let j = t + 1; j < tokens.length; j++) {
            if (tokens[j].type === '(') subParen++;
            else if (tokens[j].type === ')') subParen--;
            else if (subParen === 0 && tokens[j].type === 'CLAUSE' && tokens[j].text === 'FROM') break;
            else if (subParen === 0 && tokens[j].type === ',') commas++;
          }
          selectItemCount = commas + 1;
          result += (result ? '\n' : '') + 'SELECT';
          if (selectItemCount > 1) {
            result += '\n  ';
          } else {
            result += ' ';
          }
          continue;
        }

        inSelect = false;
        result += '\n' + tok.text + ' ';
        continue;
      }

      if (tok.type === 'JOIN') {
        inSelect = false;
        result += '\n' + tok.text + ' ';
        continue;
      }

      if (tok.type === 'LOGIC') {
        result += '\n  ' + tok.text + ' ';
        continue;
      }

      if (tok.type === ',') {
        if (inSelect && selectItemCount > 1) {
          result += ',\n  ';
        } else {
          result += ', ';
        }
        continue;
      }
    }

    // Normal token appending (or inside parens)
    if (tok.type === '.') {
      result += '.';
    } else if (prev?.type === '.') {
      result += tok.text;
    } else if (tok.type === '(') {
      if (prev?.text?.toUpperCase() === 'IN') {
        result += ' (';
      } else if (prev?.type === 'KEYWORD' || prev?.type === 'IDENT') {
        result += '(';
      } else {
        result += ' (';
      }
    } else if (tok.type === ')') {
      result += ')';
    } else if (tok.type === ',') {
      result += ', ';
    } else if (tok.type === 'OPERATOR') {
      result += ' ' + tok.text + ' ';
    } else if (tok.type === 'PARAM') {
      if (result.endsWith(' ') || result.endsWith('\n') || result.endsWith('(') || result.length === 0) {
        result += tok.text;
      } else {
        result += ' ' + tok.text;
      }
    } else {
      if (result.endsWith(' ') || result.endsWith('\n') || result.endsWith('(') || result.length === 0) {
        result += tok.text;
      } else {
        result += ' ' + tok.text;
      }
    }
  }

  // Clean trailing spaces and multiple spaces
  return result.replace(/[ \t]+\n/g, '\n').replace(/ +/g, ' ').replace(/\n /g, '\n  ').trim();
}

