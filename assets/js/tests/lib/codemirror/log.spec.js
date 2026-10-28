import { StringStream } from '@codemirror/language';
import { EditorState } from '@codemirror/state';
import { logLanguage, logStreamLanguage, logHighlightStyle, logMode, logTags } from '../../../lib/codemirror/log.js';

describe('log CodeMirror 6 language support', () => {
  it('creates a valid LanguageSupport extension via logLanguage()', () => {
    const ext = logLanguage();
    expect(ext).toBeDefined();
    expect(ext.language).toBe(logStreamLanguage);

    const state = EditorState.create({
      doc: '[23-Oct-2026 03:08:23 UTC] PHP Notice: Test',
      extensions: [ext],
    });
    expect(state.doc.toString()).toBe('[23-Oct-2026 03:08:23 UTC] PHP Notice: Test');
  });

  it('has logHighlightStyle defined with proper colors and weights', () => {
    expect(logHighlightStyle).toBeDefined();
    expect(logTags.timestamp).toBeDefined();
    expect(logTags.error).toBeDefined();
    expect(logTags.warning).toBeDefined();
    expect(logTags.notice).toBeDefined();
    expect(logTags.debug).toBeDefined();
    expect(logTags.trace).toBeDefined();
    expect(logTags.path).toBeDefined();
  });

  describe('token stream parsing', () => {
    const tokenizeLine = (line) => {
      const stream = new StringStream(line, 4, 1000);
      const state = logMode.startState();
      const tokens = [];

      while (!stream.eol()) {
        const start = stream.pos;
        const tokenType = logMode.token(stream, state);
        const end = stream.pos;
        if (end > start) {
          tokens.push({
            text: line.slice(start, end),
            type: tokenType,
          });
        } else {
          // Prevent infinite loop if stream didn't advance
          stream.next();
        }
      }
      return tokens;
    };

    it('highlights bracketed timestamps at the start of a line', () => {
      const tokens = tokenizeLine('[23-Oct-2026 03:08:23 UTC] PHP Notice: Hello');
      expect(tokens[0]).toEqual({
        text: '[23-Oct-2026 03:08:23 UTC]',
        type: 'timestamp',
      });
    });

    it('highlights ISO timestamps at start of line', () => {
      const tokens = tokenizeLine('2026-10-23 03:08:23 [ERROR] Database failure');
      expect(tokens[0]).toEqual({
        text: '2026-10-23 03:08:23',
        type: 'timestamp',
      });
    });

    it('highlights syslog timestamps at start of line', () => {
      const tokens = tokenizeLine('Oct 23 03:08:23 server [CRITICAL] Out of memory');
      expect(tokens[0]).toEqual({
        text: 'Oct 23 03:08:23',
        type: 'timestamp',
      });
    });

    it('highlights PHP error levels correctly and keeps message body text neutral', () => {
      const tokens = tokenizeLine('PHP Fatal error: Class "Database" not found in /var/www/html/app.php:42');
      const errToken = tokens.find((t) => t.type === 'error');
      expect(errToken).toEqual({
        text: 'PHP Fatal error:',
        type: 'error',
      });

      // Strings like "Database" should NOT be highlighted as code strings
      const stringToken = tokens.find((t) => t.text === '"Database"');
      expect(stringToken.type).toBeNull();

      // Path should be highlighted as path
      const pathToken = tokens.find((t) => t.type === 'path');
      expect(pathToken).toEqual({
        text: '/var/www/html/app.php:42',
        type: 'path',
      });
    });

    it('highlights PHP Deprecated notices and phar paths', () => {
      const line = 'PHP Deprecated: Using null as offset in phar:///usr/local/bin/wp/Colors.php:161';
      const tokens = tokenizeLine(line);

      const noticeToken = tokens.find((t) => t.type === 'notice');
      expect(noticeToken).toEqual({
        text: 'PHP Deprecated:',
        type: 'notice',
      });

      const pathToken = tokens.find((t) => t.type === 'path');
      expect(pathToken).toEqual({
        text: 'phar:///usr/local/bin/wp/Colors.php:161',
        type: 'path',
      });
    });

    it('highlights PHP Warnings without coloring variables or function names', () => {
      const line = 'PHP Warning: Undefined variable $foo in /var/www/test.php on line 10';
      const tokens = tokenizeLine(line);

      const warnToken = tokens.find((t) => t.type === 'warning');
      expect(warnToken).toEqual({
        text: 'PHP Warning:',
        type: 'warning',
      });

      // $foo should NOT be highlighted as variable
      const varToken = tokens.find((t) => t.text === '$foo');
      expect(varToken.type).toBeNull();

      // on line 10 should be recognized
      const lineToken = tokens.find((t) => t.text === 'on line 10');
      expect(lineToken.type).toBe('path');
    });

    it('highlights stack trace headers and numerators', () => {
      const headerTokens = tokenizeLine('PHP Stack trace:');
      expect(headerTokens[0]).toEqual({
        text: 'PHP Stack trace:',
        type: 'trace',
      });

      const frameTokens = tokenizeLine('PHP   1. WP_Fatal_Error_Handler->handle() /var/www/test.php:0');
      const prefixToken = frameTokens.find((t) => t.text === 'PHP');
      expect(prefixToken.type).toBe('prefix');

      const numToken = frameTokens.find((t) => t.text === '1.');
      expect(numToken.type).toBe('trace');

      const callToken = frameTokens.find((t) => t.text === 'WP_Fatal_Error_Handler->handle()');
      expect(callToken.type).toBeNull();

      const pathToken = frameTokens.find((t) => t.type === 'path');
      expect(pathToken).toEqual({
        text: '/var/www/test.php:0',
        type: 'path',
      });
    });

    it('highlights brackets/standard log levels: [ERROR], [WARN], [INFO], [DEBUG]', () => {
      expect(tokenizeLine('[ERROR] Server stopped')[0]).toEqual({ text: '[ERROR]', type: 'error' });
      expect(tokenizeLine('[WARN] High latency')[0]).toEqual({ text: '[WARN]', type: 'warning' });
      expect(tokenizeLine('[INFO] Application started')[0]).toEqual({ text: '[INFO]', type: 'notice' });
      expect(tokenizeLine('[DEBUG] Cache hit')[0]).toEqual({ text: '[DEBUG]', type: 'debug' });
    });

    it('does not highlight lowercase ordinary words like "error" in prose', () => {
      const tokens = tokenizeLine('There has been a critical error on this website.');
      const highlighted = tokens.filter((t) => t.type !== null);
      expect(highlighted.length).toBe(0);
    });
  });
});
