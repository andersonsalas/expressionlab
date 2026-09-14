import { createCompletionSource } from '../../lib/autocomplete.js';

describe('Autocomplete Engine', () => {
  const mockFlatItems = [
    { label: 'Users', kind: 'object', doc: { summary: 'Users object' } },
    { label: 'Database', kind: 'object', doc: { summary: 'Database helper' } },
    { label: 'rand', kind: 'function', insertText: 'rand(${1:min}, ${2:max})', doc: { summary: 'Rand function' } },
    { label: 'PHP_VERSION', kind: 'constant', doc: { summary: 'PHP version' } }
  ];

  const mockTypeRegistry = {
    Users: {
      find: { type: 'method', returnType: 'User', insertText: 'find(${1:id})', doc: { summary: 'Find user by ID' } },
      all: { type: 'method', returnType: 'UserList', doc: { summary: 'Get all users' } }
    },
    User: {
      meta: { type: 'property', returnType: 'UserMeta', doc: { summary: 'User meta property' } },
      roles: { type: 'property', returnType: 'Array', doc: { summary: 'User roles array' } },
      delete: { type: 'method', returnType: 'bool', insertText: 'delete(${1:reassign})', doc: { summary: 'Delete user' } }
    },
    UserMeta: {
      get: { type: 'method', returnType: 'string', insertText: 'get(${1:key})', doc: { summary: 'Get meta value' } },
      set: { type: 'method', returnType: 'bool', insertText: 'set(${1:key}, ${2:value})', doc: { summary: 'Set meta value' } }
    },
    Database: {
      query: { type: 'method', returnType: 'QueryResult', insertText: 'query(${1:sql})', doc: { summary: 'Execute query' } }
    }
  };

  const mockChainData = {
    typeRegistry: mockTypeRegistry,
    rootObjects: new Set(['Users', 'Database'])
  };

  const getCompletionItems = () => mockFlatItems;
  const getChainData = () => mockChainData;

  function createMockContext(docText, pos = docText.length, explicit = false) {
    return {
      pos,
      explicit,
      state: {
        doc: {
          sliceString: (from, to) => docText.slice(from, to),
          length: docText.length
        }
      },
      matchBefore(regex) {
        const textBefore = docText.slice(0, pos);
        const match = textBefore.match(new RegExp(regex.source + '$'));
        if (!match) return null;
        return {
          from: pos - match[0].length,
          to: pos,
          text: match[0]
        };
      }
    };
  }

  const completionSource = createCompletionSource(getCompletionItems, getChainData);

  describe('String literal suppression', () => {
    test('suppresses autocomplete when cursor is inside a single-quoted string', () => {
      const context = createMockContext('\'Users.fi');
      expect(completionSource(context)).toBeNull();
    });

    test('suppresses autocomplete when cursor is inside a double-quoted string', () => {
      const context = createMockContext('"Database.query(');
      expect(completionSource(context)).toBeNull();
    });

    test('suppresses autocomplete when cursor is inside a template backtick string', () => {
      const context = createMockContext('`Hello Users.find(`');
      expect(completionSource(context)).toBeNull();
    });

    test('handles escaped quotes properly inside strings', () => {
      const context = createMockContext('\'It\\\'s a test with Users.fi');
      expect(completionSource(context)).toBeNull();
    });

    test('allows autocomplete after a string literal is closed', () => {
      const text = 'prog(\'hello\', Users.find(1).)';
      const pos = 'prog(\'hello\', Users.find(1).'.length;
      const context = createMockContext(text, pos);
      const result = completionSource(context);
      expect(result).not.toBeNull();
      expect(result.options.some(opt => opt.label === 'meta')).toBe(true);
    });
  });

  describe('Chained autocomplete via type-graph walk', () => {
    test('resolves level-1 chained members on root object method return', () => {
      const context = createMockContext('Users.find(1).');
      const result = completionSource(context);
      expect(result).not.toBeNull();

      const labels = result.options.map(o => o.label);
      expect(labels).toContain('meta');
      expect(labels).toContain('roles');
      expect(labels).toContain('delete');
    });

    test('resolves deep level-2 chained members across multiple types', () => {
      const context = createMockContext('Users.find(10).meta.');
      const result = completionSource(context);
      expect(result).not.toBeNull();

      const labels = result.options.map(o => o.label);
      expect(labels).toContain('get');
      expect(labels).toContain('set');
    });

    test('handles methods with multiple arguments and complex expressions', () => {
      const context = createMockContext('Users.find(123, \'extra\', rand(1, 10)).meta.');
      const result = completionSource(context);
      expect(result).not.toBeNull();

      const labels = result.options.map(o => o.label);
      expect(labels).toContain('get');
      expect(labels).toContain('set');
    });

    test('supports optional chaining (?.) in member access', () => {
      const context = createMockContext('Users?.find(1)?.meta.');
      const result = completionSource(context);
      expect(result).not.toBeNull();

      const labels = result.options.map(o => o.label);
      expect(labels).toContain('get');
      expect(labels).toContain('set');
    });

    test('is resilient to whitespace around operators and parentheses', () => {
      const context = createMockContext('Users  .  find( 42 )  .  meta  .');
      const result = completionSource(context);
      expect(result).not.toBeNull();

      const labels = result.options.map(o => o.label);
      expect(labels).toContain('get');
      expect(labels).toContain('set');
    });

    test('returns null for unknown member in chain path', () => {
      const context = createMockContext('Users.unknownMethod().');
      const result = completionSource(context);
      expect(result === null || result.options.length === 0 || !result.options.some(o => o.label === 'get')).toBe(true);
    });

    test('calculates correct replacement range (from position) for partial chained input', () => {
      const text = 'Users.find(1).me';
      const context = createMockContext(text);
      const result = completionSource(context);
      expect(result).not.toBeNull();
      expect(result.from).toBe(14);
    });
  });

  describe('Innermost expression detection with nested calls', () => {
    test('isolates expression inside function arguments', () => {
      const pos = 'prog( set(x, 10), Users.find(1).meta.'.length;
      const result = completionSource(createMockContext('prog( set(x, 10), Users.find(1).meta. )', pos));
      expect(result).not.toBeNull();

      const labels = result.options.map(o => o.label);
      expect(labels).toContain('get');
      expect(labels).toContain('set');
    });

    test('handles deeply nested multi-argument calls', () => {
      const text = 'fn1( fn2( 1, fn3( Users.find(1). ) ) )';
      const pos = 'fn1( fn2( 1, fn3( Users.find(1).'.length;
      const result = completionSource(createMockContext(text, pos));
      expect(result).not.toBeNull();

      const labels = result.options.map(o => o.label);
      expect(labels).toContain('meta');
      expect(labels).toContain('roles');
    });
  });

  describe('Default fallback completions', () => {
    test('suggests root objects, functions, and constants on word match', () => {
      const context = createMockContext('Dat');
      const result = completionSource(context);
      expect(result).not.toBeNull();

      const labels = result.options.map(o => o.label);
      expect(labels).toContain('Database');
      expect(labels).toContain('Users');
      expect(labels).toContain('rand');
      expect(labels).toContain('PHP_VERSION');
    });

    test('returns null for empty non-explicit trigger', () => {
      const context = createMockContext('', 0, false);
      const result = completionSource(context);
      expect(result).toBeNull();
    });

    test('returns completions when explicitly triggered on empty text', () => {
      const context = createMockContext('', 0, true);
      const result = completionSource(context);
      expect(result).not.toBeNull();
      expect(result.options.length).toBe(mockFlatItems.length + 11);
    });

    test('suggests DSL keywords with type keyword and documentation', () => {
      const context = createMockContext('pro');
      const result = completionSource(context);
      expect(result).not.toBeNull();

      const progOption = result.options.find(o => o.label === 'prog[]');
      expect(progOption).toBeDefined();
      expect(progOption.type).toBe('keyword');
      expect(typeof progOption.info).toBe('function');

      const infoNode = progOption.info();
      expect(infoNode).toBeDefined();
      expect(infoNode.classList.contains('doc-tooltip')).toBe(true);
      expect(infoNode.querySelector('.summary').textContent).toContain('Sequential');
    });

    test('does not suggest keywords after a member access dot', () => {
      const context = createMockContext('Database.');
      const result = completionSource(context);
      expect(result).not.toBeNull();
      const labels = result.options.map(o => o.label);
      expect(labels).not.toContain('prog[]');
      expect(labels).not.toContain('set[]');
    });
  });

  describe('Documentation and completion option metadata', () => {
    test('attaches info generator to completion options', () => {
      const context = createMockContext('Users.find(1).');
      const result = completionSource(context);
      const metaOption = result.options.find(o => o.label === 'meta');
      expect(metaOption).toBeDefined();
      expect(typeof metaOption.info).toBe('function');

      const infoNode = metaOption.info();
      expect(infoNode).toBeDefined();
      expect(infoNode.classList.contains('doc-tooltip')).toBe(true);
    });
  });
});
