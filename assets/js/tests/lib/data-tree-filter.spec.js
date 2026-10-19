import { parseExpressionPath, searchTreeAst, countAstNodesExceeds } from '../../lib/data-tree-filter.js';
import { convertToTreeAst } from '../../lib/data-tree-adapter.js';
import { parsePhpSerialized } from '../../lib/php-serializer/parser.js';

describe('data-tree-filter.js', () => {
  describe('parseExpressionPath', () => {
    it('parses dot syntax', () => {
      expect(parseExpressionPath('the.object.values.syntax')).toEqual([
        'the',
        'object',
        'values',
        'syntax',
      ]);
    });

    it('parses bracket string syntax', () => {
      expect(parseExpressionPath('the.object["with.dots"]["nested"]')).toEqual([
        'the',
        'object',
        'with.dots',
        'nested',
      ]);
    });

    it('parses numeric bracket indices and mixed paths', () => {
      expect(parseExpressionPath('cron[0][\'wp_version_check\']')).toEqual([
        'cron',
        '0',
        'wp_version_check',
      ]);
      expect(parseExpressionPath('[0].option_name')).toEqual([
        '0',
        'option_name',
      ]);
      expect(parseExpressionPath('.last_checked')).toEqual(['last_checked']);
    });

    it('handles empty or whitespace strings gracefully', () => {
      expect(parseExpressionPath('')).toEqual([]);
      expect(parseExpressionPath('   ')).toEqual([]);
      expect(parseExpressionPath(null)).toEqual([]);
    });
  });

  describe('searchTreeAst (Path Mode)', () => {
    const rawData = {
      response: {
        status: 200,
        headers: ['application/json'],
      },
      checked: {
        no_update: {
          key: 'secret',
        },
      },
    };
    const ast = convertToTreeAst(rawData);

    it('resolves dot syntax in objects', () => {
      const result = searchTreeAst(ast, 'checked.no_update.key', 'path');
      expect(result.matchCount).toBe(1);
      expect(result.filteredAst).not.toBeNull();
      // Follow the slice
      const checkedProp = result.filteredAst.properties.find((p) => p.name === 'checked');
      expect(checkedProp).toBeDefined();
      const noUpdateProp = checkedProp.value.properties.find((p) => p.name === 'no_update');
      expect(noUpdateProp).toBeDefined();
      const keyProp = noUpdateProp.value.properties.find((p) => p.name === 'key');
      expect(keyProp.value.value).toBe('secret');
    });

    it('resolves bracket syntax and array indices', () => {
      const result = searchTreeAst(ast, 'response[\'headers\'][0]', 'path');
      expect(result.matchCount).toBe(1);
      const respProp = result.filteredAst.properties.find((p) => p.name === 'response');
      const headersProp = respProp.value.properties.find((p) => p.name === 'headers');
      expect(headersProp.value.entries[0].value.value).toBe('application/json');
    });

    it('returns matchCount 0 when path does not exist', () => {
      const result = searchTreeAst(ast, 'checked.non_existent', 'path');
      expect(result.matchCount).toBe(0);
      expect(result.filteredAst).toBeNull();
    });

    it('tolerates explicit root alias like data or root', () => {
      const result = searchTreeAst(ast, 'data.response.status', 'path');
      expect(result.matchCount).toBe(1);
    });
  });

  describe('searchTreeAst (Text Mode)', () => {
    const rawData = {
      title: 'Hello World',
      tags: ['php', 'wordpress', 'expressionlab'],
      meta: {
        author: 'Anderson',
        secret_token: 1791834872,
      },
    };
    const ast = convertToTreeAst(rawData);

    it('matches scalar string values case-insensitively', () => {
      const result = searchTreeAst(ast, 'hello', 'text');
      expect(result.matchCount).toBeGreaterThanOrEqual(1);
      expect(result.filteredAst).not.toBeNull();
      expect(result.filteredAst.properties.some((p) => p.name === 'title')).toBe(true);
      expect(result.filteredAst.properties.some((p) => p.name === 'meta')).toBe(false);
    });

    it('matches property names', () => {
      const result = searchTreeAst(ast, 'secret_token', 'text');
      expect(result.matchCount).toBe(1);
      const metaProp = result.filteredAst.properties.find((p) => p.name === 'meta');
      expect(metaProp).toBeDefined();
      expect(metaProp.value.properties.some((p) => p.name === 'secret_token')).toBe(true);
    });

    it('matches numeric values', () => {
      const result = searchTreeAst(ast, '1791834872', 'text');
      expect(result.matchCount).toBe(1);
    });

    it('returns matchCount 0 when text does not match anything', () => {
      const result = searchTreeAst(ast, 'nonexistenttermxyz', 'text');
      expect(result.matchCount).toBe(0);
      expect(result.filteredAst).toBeNull();
    });

    it('returns original ast when query is empty', () => {
      const result = searchTreeAst(ast, '', 'text');
      expect(result.matchCount).toBe(0);
      expect(result.filteredAst).toBe(ast);
    });

    it('returns original ast for whitespace-only queries in both modes', () => {
      expect(searchTreeAst(ast, '   ', 'text')).toEqual({ filteredAst: ast, matchCount: 0 });
      expect(searchTreeAst(ast, ' \t ', 'path')).toEqual({ filteredAst: ast, matchCount: 0 });
    });
  });

  describe('audit 019: strict path tokenizer', () => {
    it('rejects invalid syntax instead of silently skipping characters', () => {
      expect(parseExpressionPath('foo bar')).toBeNull();
      expect(parseExpressionPath('foo..bar')).toBeNull();
      expect(parseExpressionPath('foo.')).toBeNull();
      expect(parseExpressionPath('foo[0')).toBeNull();
      expect(parseExpressionPath('foo[]')).toBeNull();
      expect(parseExpressionPath('foo[\'bar]')).toBeNull();
      expect(parseExpressionPath('foo.[0]')).toBeNull();
    });

    it('supports Unicode identifiers, quoted keys with dots/brackets and escaped quotes', () => {
      expect(parseExpressionPath('usuario.contraseña')).toEqual(['usuario', 'contraseña']);
      expect(parseExpressionPath('settings[\'site.url\']')).toEqual(['settings', 'site.url']);
      expect(parseExpressionPath('map["a[0]"]')).toEqual(['map', 'a[0]']);
      expect(parseExpressionPath('o[\'it\\\'s\']')).toEqual(['o', 'it\'s']);
      expect(parseExpressionPath('[\'\']')).toEqual(['']);
      expect(parseExpressionPath('matrix[ 1 ][2]')).toEqual(['matrix', '1', '2']);
    });

    it('parses mixed Symfony expressions', () => {
      expect(parseExpressionPath('response.args[0][\'user\'].profile.emails[0]')).toEqual([
        'response', 'args', '0', 'user', 'profile', 'emails', '0',
      ]);
    });
  });

  describe('audit 019: path evaluation', () => {
    const ast = convertToTreeAst({
      response: { args: [{ user: { profile: { emails: ['a@b.c'] } } }] },
      settings: { 'site.url': 'https://example.com' },
    });

    it('resolves mixed expressions and quoted keys containing dots', () => {
      const mixed = searchTreeAst(ast, 'response.args[0][\'user\'].profile.emails[0]', 'path');
      expect(mixed.matchCount).toBe(1);
      const quoted = searchTreeAst(ast, 'settings[\'site.url\']', 'path');
      expect(quoted.matchCount).toBe(1);
      expect(quoted.filteredAst.properties[0].value.properties[0].value.value).toBe('https://example.com');
    });

    it('returns not found for invalid syntax and missing intermediate segments', () => {
      expect(searchTreeAst(ast, 'response args', 'path')).toEqual({ filteredAst: null, matchCount: 0 });
      expect(searchTreeAst(ast, 'response.missing.deep[0]', 'path')).toEqual({ filteredAst: null, matchCount: 0 });
      expect(searchTreeAst(ast, 'response.args[0].user.profile.emails[0].x', 'path').matchCount).toBe(0);
    });

    it('treats prototype-related segments as plain, missing keys', () => {
      expect(searchTreeAst(ast, '__proto__.polluted', 'path').matchCount).toBe(0);
      expect(searchTreeAst(ast, 'constructor.prototype', 'path').matchCount).toBe(0);
      expect({}.polluted).toBeUndefined();
    });

    it('matches PHP integer and string array keys', () => {
      const phpAst = parsePhpSerialized('a:2:{i:5;s:3:"int";s:3:"key";a:1:{i:0;s:4:"deep";}}');
      expect(searchTreeAst(phpAst, '[5]', 'path').matchCount).toBe(1);
      expect(searchTreeAst(phpAst, '[\'5\']', 'path').matchCount).toBe(1);
      expect(searchTreeAst(phpAst, 'key[0]', 'path').matchCount).toBe(1);
      expect(searchTreeAst(phpAst, '[6]', 'path').matchCount).toBe(0);
    });
  });

  describe('audit 019: text search counting and rendering parity', () => {
    it('counts matches nested under an already matching key', () => {
      const ast = convertToTreeAst({ name: { name: 'x', other: 'name' } });
      // Rows: outer "name" key, inner "name" key, "other" value "name".
      const result = searchTreeAst(ast, 'name', 'text');
      expect(result.matchCount).toBe(3);
    });

    it('counts a row once when both key and value match', () => {
      const ast = convertToTreeAst({ token: 'token-123' });
      expect(searchTreeAst(ast, 'token', 'text').matchCount).toBe(1);
    });

    it('matches values as rendered (PHP null, booleans, enums, references)', () => {
      const phpAst = parsePhpSerialized('O:8:"stdClass":4:{s:1:"a";N;s:1:"b";b:1;s:1:"c";E:11:"Suit:Hearts";s:1:"d";r:1;}');
      expect(searchTreeAst(phpAst, 'null', 'text').matchCount).toBe(1);
      expect(searchTreeAst(phpAst, 'true', 'text').matchCount).toBe(1);
      expect(searchTreeAst(phpAst, 'Suit::Hearts', 'text').matchCount).toBe(1);
      // Class header of the root object + the reference to it.
      expect(searchTreeAst(phpAst, 'stdclass', 'text').matchCount).toBe(2);
    });

    it('keeps the full subtree when a container header matches', () => {
      const phpAst = parsePhpSerialized('O:7:"WP_User":2:{s:2:"ID";i:1;s:5:"login";s:5:"admin";}');
      const result = searchTreeAst(phpAst, 'wp_user', 'text');
      expect(result.filteredAst.properties).toHaveLength(2);
    });
  });

  describe('countAstNodesExceeds', () => {
    it('returns false for small trees below the threshold', () => {
      const ast = convertToTreeAst({ a: 1, b: 2 });
      expect(countAstNodesExceeds(ast, 20)).toBe(false);
    });

    it('returns true when total nodes exceed the threshold', () => {
      const items = Array.from({ length: 25 }, (_, i) => ({ id: i, value: `val_${i}` }));
      const ast = convertToTreeAst(items);
      expect(countAstNodesExceeds(ast, 20)).toBe(true);
    });

    it('handles nested structures and aborts early', () => {
      const nested = {
        level1: {
          level2: {
            items: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
          },
        },
      };
      const ast = convertToTreeAst(nested);
      expect(countAstNodesExceeds(ast, 5)).toBe(true);
    });

    it('returns false for null or non-object values', () => {
      expect(countAstNodesExceeds(null, 20)).toBe(false);
      expect(countAstNodesExceeds(undefined, 20)).toBe(false);
      expect(countAstNodesExceeds('string', 20)).toBe(false);
    });
  });
});
