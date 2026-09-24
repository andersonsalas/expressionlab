/**
 * Expression Lab DSL language support for CodeMirror 6.
 *
 * Implements a StreamLanguage streaming parser and HighlightStyle for Expression Lab DSL.
 */

import { StreamLanguage, HighlightStyle, syntaxHighlighting, LanguageSupport } from '@codemirror/language';
import { tags } from '@codemirror/highlight';

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

const BUILTIN_OBJECTS = new Set([
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
]);

const LITERALS = new Set([
  'true',
  'false',
  'null',
]);

/**
 * StreamParser definition for Expression Lab DSL.
 */
export const elscriptMode = {
  name: 'elscript',
  startState() {
    return {
      inBlockComment: false,
      stringQuote: null,
      bracketDepth: 0,
    };
  },
  indent(state, textAfter, cx) {
    const unit = cx?.unit || 4;
    const closing = /^[\]})]/.test(textAfter);
    const depth = closing ? Math.max(0, state.bracketDepth - 1) : state.bracketDepth;
    return depth * unit;
  },
  token(stream, state) {
    // 1. Whitespace (outside of string literals)
    if (!state.stringQuote && stream.eatSpace()) {
      return null;
    }

    // 2. Multi-line string continuation
    if (state.stringQuote) {
      let escaped = false;
      while (!stream.eol()) {
        const ch = stream.next();
        if (ch === state.stringQuote && !escaped) {
          state.stringQuote = null;
          break;
        }
        escaped = !escaped && ch === '\\';
      }
      return 'string';
    }

    // 3. Block comment continuation
    if (state.inBlockComment) {
      if (stream.skipTo('*/')) {
        stream.pos += 2;
        state.inBlockComment = false;
      } else {
        stream.skipToEnd();
      }
      return 'comment';
    }

    // 4. Block comment start
    if (stream.match('/*')) {
      state.inBlockComment = true;
      if (stream.skipTo('*/')) {
        stream.pos += 2;
        state.inBlockComment = false;
      } else {
        stream.skipToEnd();
      }
      return 'comment';
    }

    // 5. Strings start: single and double quoted with backslash escapes
    if (stream.match('\'') || stream.match('"')) {
      const quote = stream.current();
      state.stringQuote = quote;
      let escaped = false;
      while (!stream.eol()) {
        const ch = stream.next();
        if (ch === quote && !escaped) {
          state.stringQuote = null;
          break;
        }
        escaped = !escaped && ch === '\\';
      }
      return 'string';
    }

    // 6. Numbers: integers, floats, scientific notation, leading dot decimals
    if (stream.match(/^[0-9]+(\.[0-9]+)?([eE][+-]?[0-9]+)?/) || stream.match(/^\.[0-9]+([eE][+-]?[0-9]+)?/)) {
      return 'number';
    }

    // 7. Operators: ~ (string concatenation), arithmetic, comparison, logical
    if (stream.match(/^([~+\-*%!=]=?|<=|>=|<|>|&&|\|\||\?\?|\?|\/)/)) {
      return 'operator';
    }

    // 8. Delimiters, brackets and punctuation
    if (stream.match(/^[[\](){}]/)) {
      const b = stream.current();
      if (b === '[' || b === '{' || b === '(') {
        state.bracketDepth++;
      } else if (b === ']' || b === '}' || b === ')') {
        state.bracketDepth = Math.max(0, state.bracketDepth - 1);
      }
      return 'bracket';
    }
    if (stream.match(/^[,;.:]/)) {
      return 'punctuation';
    }

    // 9. Identifiers, keywords, built-ins, and literals
    if (stream.match(/^[a-zA-Z_$][a-zA-Z0-9_$]*/)) {
      const word = stream.current();
      if (KEYWORDS.has(word)) {
        return 'keyword';
      }
      if (BUILTIN_OBJECTS.has(word)) {
        return 'className';
      }
      if (LITERALS.has(word)) {
        return word === 'null' ? 'null' : 'bool';
      }
      return 'variableName';
    }

    // Consume unknown char to advance stream
    stream.next();
    return null;
  },
};

/**
 * StreamLanguage instance for Expression Lab DSL.
 */
export const elscriptLanguage = StreamLanguage.define(elscriptMode);

/**
 * HighlightStyle matching Expression Lab palette:
 * - Keywords: #af00db
 * - Literals (true, false, null): #0000ff
 * - Strings: #a31515
 * - Numbers: #098658
 * - Comments: #888888
 * - Classes / Operators / Punctuation: #000000
 */
export const elscriptHighlightStyle = HighlightStyle.define([
  { tag: tags.keyword, color: '#af00db', fontWeight: 'normal' },
  { tag: tags.className, color: '#000000', fontWeight: 'normal' },
  { tag: tags.string, color: '#a31515' },
  { tag: tags.number, color: '#098658' },
  { tag: [tags.bool, tags.null], color: '#0000ff', fontWeight: 'normal' },
  { tag: tags.comment, color: '#888888', fontStyle: 'normal' },
  { tag: tags.operator, color: '#000000' },
  { tag: tags.bracket, color: '#000000' },
  { tag: tags.punctuation, color: '#000000' },
  { tag: tags.variableName, color: '#000000' },
]);

/**
 * Extension factory for CodeMirror 6.
 *
 * @returns {LanguageSupport}
 */
export function elscript() {
  return new LanguageSupport(elscriptLanguage, [
    syntaxHighlighting(elscriptHighlightStyle),
  ]);
}
