import { elscript, elscriptLanguage, elscriptHighlightStyle, elscriptMode } from '../../../lib/codemirror/elscript.js';
import { EditorState } from '@codemirror/state';

describe('elscript CodeMirror 6 language support', () => {
  it('creates an extension via elscript()', () => {
    const ext = elscript();
    expect(ext).toBeDefined();
    expect(ext.language).toBe(elscriptLanguage);

    const state = EditorState.create({
      doc: 'prog[]',
      extensions: [ext],
    });
    expect(state.doc.toString()).toBe('prog[]');
  });

  it('correctly parses special keywords and structures into Lezer syntax tree', () => {
    const doc = 'prog[set[\'x\', show[1]], var[\'x\'], fn[], args[\'p\'], map[], filter[], isset[\'x\'], unset[\'x\'], reduce[]]';
    const tree = elscriptLanguage.parser.parse(doc);
    const treeStr = tree.toString();

    expect(treeStr).toContain('ProgExpression');
    expect(treeStr).toContain('SetExpression');
    expect(treeStr).toContain('ShowExpression');
    expect(treeStr).toContain('VarExpression');
    expect(treeStr).toContain('BracketList');
    expect(treeStr).toContain('String');
    expect(treeStr).toContain('Number');
  });

  it('correctly parses built-in root objects and methods', () => {
    const doc = 'Users.find(1) Database.query() Posts.all()';
    const tree = elscriptLanguage.parser.parse(doc);
    const treeStr = tree.toString();

    expect(treeStr).toContain('BuiltinObject');
    expect(treeStr).toContain('PropertyName');
    expect(treeStr).toContain('ArgumentList');
  });

  it('correctly parses string concatenation operator ~ and comparison operators', () => {
    const doc = 'var[\'a\'] ~ \' hello\' == true != false <= 100';
    const tree = elscriptLanguage.parser.parse(doc);
    const treeStr = tree.toString();

    expect(treeStr).toContain('BinaryExpression');
    expect(treeStr).toContain('VarExpression');
    expect(treeStr).toContain('Boolean');
    expect(treeStr).toContain('Number');
  });

  it('correctly parses and highlights range operator 0..23', () => {
    const { highlightTree } = require('@lezer/highlight');
    const doc = 'set[\'hours\', 0..23]';
    const tree = elscriptLanguage.parser.parse(doc);
    const treeStr = tree.toString();

    expect(treeStr).toContain('BinaryExpression');
    expect(treeStr).toContain('Number');

    const tokens = [];
    highlightTree(tree, elscriptHighlightStyle, (from, to, classes) => {
      tokens.push({ text: doc.slice(from, to), classes });
    });

    const rangeToken = tokens.find(t => t.text === '..');
    expect(rangeToken).toBeDefined();

    const numberTokens = tokens.filter(t => t.text === '0' || t.text === '23');
    expect(numberTokens.length).toBe(2);
  });

  it('preserves keyword and string highlighting inside binary expressions and lambda functions', () => {
    const { highlightTree } = require('@lezer/highlight');
    const doc = 'set[\'suma\', fn[ [\'a\',\'b\'], args[\'a\'] + args[\'b\'] ] ]';
    const tree = elscriptLanguage.parser.parse(doc);

    const tokens = [];
    highlightTree(tree, elscriptHighlightStyle, (from, to, classes) => {
      tokens.push({ text: doc.slice(from, to), classes });
    });

    const argsTokens = tokens.filter(t => t.text === 'args');
    expect(argsTokens.length).toBe(2);
    // Both args tokens must have keyword styling
    argsTokens.forEach(t => expect(t.classes).toBeTruthy());

    const stringTokens = tokens.filter(t => t.text === '\'a\'' || t.text === '\'b\'');
    expect(stringTokens.length).toBeGreaterThanOrEqual(2);
    stringTokens.forEach(t => expect(t.classes).toBeTruthy());

    const plusToken = tokens.find(t => t.text === '+');
    expect(plusToken).toBeDefined();
  });

  it('correctly parses block comments and null', () => {
    const doc = '/* multi-line\n comment */ null';
    const tree = elscriptLanguage.parser.parse(doc);
    const treeStr = tree.toString();

    expect(treeStr).toContain('BlockComment');
    expect(treeStr).toContain('Null');
  });

  it('correctly parses multi-line strings across line breaks', () => {
    const doc = 'Database.query(\'\nSELECT "post" as tipo\nUNION ALL\nSELECT "page"\n\')';
    const tree = elscriptLanguage.parser.parse(doc);
    const treeStr = tree.toString();

    expect(treeStr).toContain('BuiltinObject');
    expect(treeStr).toContain('String');
    expect(treeStr).toContain('ArgumentList');
  });

  it('correctly parses strings with escaped quotes without premature termination', () => {
    const doc = 'wp_slash(\'It\\\'s a test\')';
    const tree = elscriptLanguage.parser.parse(doc);
    const treeStr = tree.toString();

    expect(treeStr).toBe('Program(PostfixExpression(AtomicExpression(Identifier),ArgumentList("(",AtomicExpression(String),")")))');
  });

  it('has elscriptHighlightStyle defined with proper tag styles and canonical colors', () => {
    expect(elscriptHighlightStyle).toBeDefined();
    expect(elscriptHighlightStyle.specs.length).toBeGreaterThan(0);

    const keywordSpec = elscriptHighlightStyle.specs.find(s => s.color === '#af00db');
    expect(keywordSpec).toBeDefined();

    const literalSpec = elscriptHighlightStyle.specs.find(s => s.color === '#0000ff');
    expect(literalSpec).toBeDefined();
  });

  it('handles token stream advance on unknown characters in compatibility mode', () => {
    const state = elscriptMode.startState();
    let advanced = false;
    const mockStream = {
      eatSpace: () => false,
      match: () => false,
      next: () => { advanced = true; return '@'; },
      skipToEnd: () => {},
      skipTo: () => false,
      eol: () => false,
    };
    const result = elscriptMode.token(mockStream, state);
    expect(result).toBeNull();
    expect(advanced).toBe(true);
  });
});
