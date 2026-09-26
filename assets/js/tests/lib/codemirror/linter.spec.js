import { lintExpressionLab, expressionLabLinter } from '../../../lib/codemirror/elscript-linter.js';
import { elscript } from '../../../lib/codemirror/elscript.js';
import { EditorState } from '@codemirror/state';

describe('Expression Lab DSL Linter', () => {
  describe('Valid Expressions', () => {
    it('returns empty diagnostics for empty or whitespace-only code', () => {
      expect(lintExpressionLab('')).toEqual([]);
      expect(lintExpressionLab('   \n  \t ')).toEqual([]);
      expect(lintExpressionLab(null)).toEqual([]);
      expect(lintExpressionLab(undefined)).toEqual([]);
    });

    it('returns empty diagnostics for valid prog blocks', () => {
      expect(lintExpressionLab('prog[]')).toEqual([]);
      expect(lintExpressionLab('prog[1]')).toEqual([]);
      expect(lintExpressionLab('prog[set[\'x\', 1], var[\'x\'] + 2]')).toEqual([]);
    });

    it('returns empty diagnostics for valid object and array literals', () => {
      expect(lintExpressionLab('{ name: \'test\', count: 42 }')).toEqual([]);
      expect(lintExpressionLab('[1, 2, 3, \'hello\']')).toEqual([]);
    });

    it('returns empty diagnostics for valid pipeline closures (map, filter, reduce)', () => {
      expect(lintExpressionLab('map[items, fn[[\'x\'], var[\'x\'] * 2]]')).toEqual([]);
      expect(lintExpressionLab('filter[items, fn[[\'x\'], var[\'x\'] > 0]]')).toEqual([]);
      expect(lintExpressionLab('filter[items, fn[[\'x\'], var[\'x\'] > 0], \'ARRAY_FILTER_USE_BOTH\']')).toEqual([]);
      expect(lintExpressionLab('reduce[items, fn[[\'acc\', \'x\'], var[\'acc\'] + var[\'x\']], 0]')).toEqual([]);
    });

    it('returns empty diagnostics for valid comments and multi-line strings', () => {
      expect(lintExpressionLab('/* comment */ 42')).toEqual([]);
      expect(lintExpressionLab('Database.query(\'SELECT * FROM posts\')')).toEqual([]);
    });

    it('returns empty diagnostics for valid range expressions like 0..23', () => {
      expect(lintExpressionLab('0..23')).toEqual([]);
      expect(lintExpressionLab('set[\'hours\', 0..23]')).toEqual([]);
      expect(lintExpressionLab('prog[set[\'hours\', 0..23]]')).toEqual([]);
    });
  });

  describe('Syntax Errors', () => {
    it('detects unclosed brackets in prog[] and special forms', () => {
      const code = 'prog[set[\'x\', 1]';
      const diags = lintExpressionLab(code);
      expect(diags.length).toBeGreaterThan(0);
      expect(diags[0].severity).toBe('error');
      expect(diags[0].message).toContain('Syntax error');
    });

    it('detects missing commas between expressions in prog[]', () => {
      const code = 'prog[set[\'x\', 1] var[\'x\']]';
      const diags = lintExpressionLab(code);
      expect(diags.length).toBeGreaterThan(0);
      expect(diags[0].severity).toBe('error');
      expect(diags[0].message).toContain('Syntax error');
    });

    it('detects missing commas in object literals', () => {
      const code = '{ a: 1 b: 2 }';
      const diags = lintExpressionLab(code);
      expect(diags.length).toBeGreaterThan(0);
      expect(diags[0].severity).toBe('error');
    });

    it('detects trailing binary operators', () => {
      const code = 'var[\'x\'] +';
      const diags = lintExpressionLab(code);
      expect(diags.length).toBeGreaterThan(0);
      expect(diags[0].severity).toBe('error');
    });

    it('detects unclosed strings', () => {
      const code = '\'hello unclosed';
      const diags = lintExpressionLab(code);
      expect(diags.length).toBeGreaterThan(0);
      expect(diags[0].severity).toBe('error');
    });

    it('detects unclosed block comments', () => {
      const code = '/* unclosed comment';
      const diags = lintExpressionLab(code);
      expect(diags.length).toBeGreaterThan(0);
      expect(diags[0].severity).toBe('error');
    });
  });

  describe('Special Form Signatures and Arity Validation', () => {
    it('enforces set[] arity (requires exactly 2 arguments)', () => {
      const missingArg = lintExpressionLab('set[\'x\']');
      expect(missingArg.length).toBe(1);
      expect(missingArg[0].message).toBe('set[] requires exactly 2 arguments: set[name, value]');

      const emptyArg = lintExpressionLab('set[]');
      expect(emptyArg.length).toBe(1);
      expect(emptyArg[0].message).toBe('set[] requires exactly 2 arguments: set[name, value]');

      const tooManyArgs = lintExpressionLab('set[\'x\', 1, 2]');
      expect(tooManyArgs.length).toBe(1);
      expect(tooManyArgs[0].message).toBe('set[] requires exactly 2 arguments: set[name, value]');

      const valid = lintExpressionLab('set[\'x\', 1]');
      expect(valid.length).toBe(0);
    });

    it('enforces var[] arity (requires exactly 1 argument)', () => {
      const emptyVar = lintExpressionLab('var[]');
      expect(emptyVar.length).toBe(1);
      expect(emptyVar[0].message).toBe('var[] requires exactly 1 argument: var[name]');

      const tooMany = lintExpressionLab('var[\'x\', \'y\']');
      expect(tooMany.length).toBe(1);
      expect(tooMany[0].message).toBe('var[] requires exactly 1 argument: var[name]');

      const valid = lintExpressionLab('var[\'x\']');
      expect(valid.length).toBe(0);
    });

    it('enforces unset[] and isset[] arity', () => {
      const unsetEmpty = lintExpressionLab('unset[]');
      expect(unsetEmpty.length).toBe(1);
      expect(unsetEmpty[0].message).toBe('unset[] requires exactly 1 argument: unset[name]');

      const issetTooMany = lintExpressionLab('isset[\'a\', \'b\']');
      expect(issetTooMany.length).toBe(1);
      expect(issetTooMany[0].message).toBe('isset[] requires exactly 1 argument: isset[name]');
    });

    it('enforces args[] and show[] arity', () => {
      const argsEmpty = lintExpressionLab('args[]');
      expect(argsEmpty.length).toBe(1);
      expect(argsEmpty[0].message).toBe('args[] requires exactly 1 argument: args[name]');

      const showEmpty = lintExpressionLab('show[]');
      expect(showEmpty.length).toBe(1);
      expect(showEmpty[0].message).toBe('show[] requires exactly 1 argument: show[expression]');
    });

    it('enforces fn[] arity (requires exactly 2 arguments)', () => {
      const fnMissing = lintExpressionLab('fn[[\'x\']]');
      expect(fnMissing.length).toBe(1);
      expect(fnMissing[0].message).toBe('fn[] requires exactly 2 arguments: fn[params, body]');

      const fnValid = lintExpressionLab('fn[[\'x\'], var[\'x\'] * 2]');
      expect(fnValid.length).toBe(0);
    });

    it('enforces map[] arity (requires exactly 2 arguments)', () => {
      const mapMissing = lintExpressionLab('map[items]');
      expect(mapMissing.length).toBe(1);
      expect(mapMissing[0].message).toBe('map[] requires exactly 2 arguments: map[iterable, fn]');

      const mapValid = lintExpressionLab('map[items, fn[[\'x\'], var[\'x\']]]');
      expect(mapValid.length).toBe(0);
    });

    it('enforces filter[] and reduce[] arity (2 or 3 arguments)', () => {
      const filterMissing = lintExpressionLab('filter[items]');
      expect(filterMissing.length).toBe(1);
      expect(filterMissing[0].message).toContain('filter[] requires between 2 and 3 arguments');

      const filterTooMany = lintExpressionLab('filter[items, myFunc, 1, 2]');
      expect(filterTooMany.length).toBe(1);
      expect(filterTooMany[0].message).toContain('filter[] requires between 2 and 3 arguments');

      const filterValid2 = lintExpressionLab('filter[items, myFunc]');
      expect(filterValid2.length).toBe(0);

      const filterValid3 = lintExpressionLab('filter[items, myFunc, \'mode\']');
      expect(filterValid3.length).toBe(0);

      const reduceValid3 = lintExpressionLab('reduce[items, myFunc, 0]');
      expect(reduceValid3.length).toBe(0);
    });
  });

  describe('EditorState Integration', () => {
    it('evaluates diagnostics from EditorState with elscript() extension', () => {
      const state = EditorState.create({
        doc: 'prog[set[\'x\', 1]',
        extensions: [elscript()],
      });

      const diags = lintExpressionLab(state);
      expect(diags.length).toBeGreaterThan(0);
      expect(diags[0].severity).toBe('error');
    });

    it('creates a CodeMirror 6 extension via expressionLabLinter()', () => {
      const ext = expressionLabLinter();
      expect(ext).toBeDefined();

      const state = EditorState.create({
        doc: 'prog[]',
        extensions: [elscript(), ext],
      });
      expect(state).toBeDefined();
    });
  });
});
