/*
 * Log Language - CodeMirror Mode
 *
 * Implements clean, professional log syntax highlighting:
 * - Timestamps and dates (muted gray)
 * - Severity levels: Error, Warning, Notice/Info, Debug
 * - Stack trace headers and frame numerators
 * - File paths and line numbers
 * - Keeps message body text neutral to avoid inconsistent code parsing.
 */
import { StreamLanguage, HighlightStyle, syntaxHighlighting, LanguageSupport } from '@codemirror/language';
import { Tag } from '@lezer/highlight';

export const logTags = {
  timestamp: Tag.define(),
  error: Tag.define(),
  warning: Tag.define(),
  notice: Tag.define(),
  debug: Tag.define(),
  trace: Tag.define(),
  path: Tag.define(),
  prefix: Tag.define(),
  comment: Tag.define(),
};

export const logHighlightStyle = HighlightStyle.define([
  { tag: logTags.timestamp, color: '#6e7781' },
  { tag: logTags.error, color: '#cf222e', fontWeight: 'bold' },
  { tag: logTags.warning, color: '#9a6700', fontWeight: 'bold' },
  { tag: logTags.notice, color: '#0969da' },
  { tag: logTags.debug, color: '#8250df' },
  { tag: logTags.trace, color: '#57606a', fontWeight: 'bold' },
  { tag: logTags.path, color: '#0550ae' },
  { tag: logTags.prefix, color: '#6e7781' },
  { tag: logTags.comment, color: '#6e7781', fontStyle: 'italic' },
]);

export const logMode = {
  name: 'log',
  startState() {
    return {
      atLineStart: true,
    };
  },
  tokenTable: {
    timestamp: logTags.timestamp,
    error: logTags.error,
    warning: logTags.warning,
    notice: logTags.notice,
    debug: logTags.debug,
    trace: logTags.trace,
    path: logTags.path,
    prefix: logTags.prefix,
    comment: logTags.comment,
  },
  token(stream, state) {
    if (stream.sol()) {
      state.atLineStart = true;
    }

    if (stream.eatSpace()) return null;

    // 1. Timestamps / Dates (only at start of line)
    if (state.atLineStart) {
      // Bracketed timestamps: [23-Oct-2026 03:08:23 UTC], [2026-10-23 03:08:23], [Mon Oct 23 ...], [12:34:56]
      if (stream.match(/^\[(?:\d{1,4}[-/][A-Za-z0-9]+|[A-Za-z]{3}\s+[A-Za-z]{3}|\d{2}:\d{2}:\d{2})[^\]\r\n]*\]/)) {
        state.atLineStart = false;
        return 'timestamp';
      }
      // ISO timestamps: 2026-10-23 03:08:23 or 2026-10-23T03:08:23.123Z
      if (stream.match(/^\d{4}-\d{2}-\d{2}[T\s]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})?/)) {
        state.atLineStart = false;
        return 'timestamp';
      }
      // Syslog timestamps: Oct 23 03:08:23
      if (stream.match(/^(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+\d{1,2}\s+\d{2}:\d{2}:\d{2}/i)) {
        state.atLineStart = false;
        return 'timestamp';
      }
      // Config comments starting with # or ;
      if (stream.match(/^[#;].*/)) {
        state.atLineStart = false;
        return 'comment';
      }
      state.atLineStart = false;
    }

    // 2. Critical & Fatal Errors
    if (
      stream.match(/^PHP\s+(?:Fatal\s+error|Parse\s+error|Compile\s+Error|Core\s+Error|Error):?/i) ||
      stream.match(/^(?:Fatal\s+error|Parse\s+error):?/i) ||
      stream.match(/^Uncaught\s+[A-Za-z0-9_\\]*(?:Exception|Error):?/i) ||
      stream.match(/^\[(?:FATAL|CRITICAL|EMERGENCY|ERROR|ALERT|SEVERE)\]/i) ||
      stream.match(/^\[[a-z0-9_.-]*:(?:error|crit|emerg|alert)\]/i) ||
      stream.match(/^[a-zA-Z0-9_.-]+\.(?:FATAL|CRITICAL|EMERGENCY|ERROR|ALERT|SEVERE):/) ||
      stream.match(/^(?:FATAL|CRITICAL|EMERGENCY|ERROR|SEVERE)\b:?/)
    ) {
      return 'error';
    }

    // 3. Warnings
    if (
      stream.match(/^PHP\s+Warning:?/i) ||
      stream.match(/^Warning:?/i) ||
      stream.match(/^\[(?:WARN|WARNING)\]/i) ||
      stream.match(/^\[[a-z0-9_.-]*:(?:warn|warning)\]/i) ||
      stream.match(/^[a-zA-Z0-9_.-]+\.(?:WARN|WARNING):/) ||
      stream.match(/^(?:WARNING|WARN)\b:?/)
    ) {
      return 'warning';
    }

    // 4. Notices, Deprecations & Info
    if (
      stream.match(/^PHP\s+(?:Notice|Deprecated|Strict\s+Standards):?/i) ||
      stream.match(/^(?:Notice|Deprecated):?/i) ||
      stream.match(/^\[(?:INFO|NOTICE|DEPRECATED)\]/i) ||
      stream.match(/^\[[a-z0-9_.-]*:(?:info|notice)\]/i) ||
      stream.match(/^[a-zA-Z0-9_.-]+\.(?:INFO|NOTICE|DEPRECATED):/) ||
      stream.match(/^(?:INFO|NOTICE|DEPRECATED)\b:?/)
    ) {
      return 'notice';
    }

    // 5. Debug & Verbose
    if (
      stream.match(/^\[(?:DEBUG|TRACE|VERBOSE)\]/i) ||
      stream.match(/^\[[a-z0-9_.-]*:(?:debug|trace)\]/i) ||
      stream.match(/^[a-zA-Z0-9_.-]+\.(?:DEBUG|TRACE|VERBOSE):/) ||
      stream.match(/^(?:DEBUG|VERBOSE)\b:?/)
    ) {
      return 'debug';
    }

    // 6. Stack Traces header
    if (
      stream.match(/^PHP\s+Stack\s+trace:?/i) ||
      stream.match(/^Stack\s+trace:?/i)
    ) {
      return 'trace';
    }

    // 7. Stack trace numerators: "#0", "  1.", " 10."
    if (stream.match(/^#\d+\b/) || stream.match(/^\d+\.(?=\s)/)) {
      return 'trace';
    }

    // 8. Isolated PHP prefix
    if (stream.match(/^PHP\b/i)) {
      return 'prefix';
    }

    // 9. File paths and line numbers
    if (
      stream.match(/^(?:phar:\/\/)?\/(?:[~@a-zA-Z0-9_.-]+\/)+[~@a-zA-Z0-9_.-]+\.[a-zA-Z0-9]+(?::\d+|\(\d+\))?/) ||
      stream.match(/^[a-zA-Z]:\\(?:[~@a-zA-Z0-9_.-]+\\)+[~@a-zA-Z0-9_.-]+\.[a-zA-Z0-9]+(?::\d+|\(\d+\))?/) ||
      stream.match(/^on\s+line\s+\d+/i)
    ) {
      return 'path';
    }

    // Advance through unhighlighted text chunks up to whitespace or potential token delimiters
    if (!stream.eatWhile(/[^\s/\\#[\]]/)) {
      stream.next();
    }
    return null;
  },
};

export const logStreamLanguage = StreamLanguage.define(logMode);

export const logLanguage = () => new LanguageSupport(logStreamLanguage, [
  syntaxHighlighting(logHighlightStyle),
]);

