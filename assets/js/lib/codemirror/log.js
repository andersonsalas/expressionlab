/*
 * Semi-agnostic Log Language - CodeMirror Mode
 *
 * Supports:
 * - General log entries (generic keywords, operators, etc.)
 * - PHP-specific highlighting (warnings, notices, errors, stack traces, WP-CLI)
 */
import { StreamLanguage } from '@codemirror/language';

const PHP_KEYWORDS_INSIDE_PARENS = new Set([
  // Literals / Constants
  'true', 'false', 'null',
  // Visibility & Declarations
  'class', 'interface', 'trait', 'enum', 'public', 'private', 'protected',
  'readonly', 'static', 'final', 'abstract', 'const', 'function', 'fn',
  'new', 'clone', 'extends', 'implements', 'instanceof', 'self', 'parent',
  // Types
  'array', 'callable', 'iterable', 'object', 'string', 'int', 'float', 'bool', 'void', 'mixed', 'never',
  // Control & Language Constructs
  'return', 'throw', 'yield', 'match', 'list', 'echo', 'print', 'die', 'exit',
  'empty', 'isset', 'unset', 'eval', 'include', 'include_once', 'require', 'require_once',
  'use', 'as', 'global', 'var',
]);

export const logLanguage = () => StreamLanguage.define({
  name: 'log',
  startState() {
    return {
      inString: null,
      parenDepth: 0,
    };
  },
  token(stream, state) {
    if (stream.eatSpace()) return null;

    // Reset paren depth at start of new log entry (timestamp at sol)
    if (stream.sol()) {
      if (stream.match(/^\[[^\]\r\n]+\]/)) {
        state.parenDepth = 0;
        return 'meta';
      }
      if (stream.match(/^\d{4}-\d{2}-\d{2}[T\s]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})?/)) {
        state.parenDepth = 0;
        return 'meta';
      }
    }

    // 1. Strings (single or double quotes, with escape support and multi-line continuation)
    if (state.inString) {
      while (!stream.eol()) {
        const ch = stream.next();
        if (ch === '\\') {
          stream.next();
        } else if (ch === state.inString) {
          state.inString = null;
          break;
        }
      }
      return 'string';
    }

    const quote = stream.peek();
    if (quote === '"' || quote === '\'') {
      stream.next();
      state.inString = quote;
      while (!stream.eol()) {
        const ch = stream.next();
        if (ch === '\\') {
          stream.next();
        } else if (ch === quote) {
          state.inString = null;
          break;
        }
      }
      return 'string';
    }

    // Critical & Fatal Errors
    if (stream.match(/^PHP\s+(?:Fatal\s+error|Parse\s+error|Compile\s+Error|Core\s+Error):?/i) ||
        stream.match(/^(?:FATAL|CRITICAL|EMERGENCY|ERROR|EXCEPTION):?/i)) {
      return 'error';
    }

    // Warnings
    if (stream.match(/^PHP\s+Warning:?/i)) {
      return 'keyword';
    }

    // Notices, Deprecations & Info
    if (stream.match(/^PHP\s+(?:Notice|Deprecated):?/i)) {
      return 'atom';
    }

    // Stack Traces header
    if (stream.match(/^PHP\s+Stack\s+trace:?/i)) {
      return 'def';
    }

    // Stack trace numerators: "  1. ", " 10. ", "#0 " -> ignore without highlighting as number
    if (stream.match(/^\d+\.\s/) || stream.match(/^#\d+\s/)) {
      return null;
    }

    // Track parentheses for PHP expression detection
    const ch = stream.peek();
    if (ch === '(') {
      state.parenDepth++;
      stream.next();
      return null;
    }
    if (ch === ')') {
      if (state.parenDepth > 0) {
        state.parenDepth--;
      }
      stream.next();
      return null;
    }

    // 3. PHP Variables: $var, $state, $this, etc.
    if (stream.match(/^\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/)) {
      return 'variable';
    }

    // 2. Standalone numbers (not part of stack trace numerators or identifiers)
    if (stream.match(/^(?:0x[0-9a-fA-F]+|\d+(?:\.\d+)?)\b/)) {
      return 'number';
    }

    // 4. Words / Identifiers (PHP keywords only highlighted when inside parentheses)
    const wordMatch = stream.match(/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/);
    if (wordMatch) {
      const lower = wordMatch[0].toLowerCase();
      if (state.parenDepth > 0 && PHP_KEYWORDS_INSIDE_PARENS.has(lower)) {
        if (lower === 'true' || lower === 'false' || lower === 'null') {
          return 'atom';
        }
        return 'keyword';
      }
      return null;
    }

    stream.next();
    return null;
  },
});
