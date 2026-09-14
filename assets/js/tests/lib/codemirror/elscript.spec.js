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

  it('correctly tokenizes special keywords and structures into syntax tree', () => {
    const doc = 'prog[set[\'x\', show[1]], var[\'x\'], fn[], args[\'p\'], map[], filter[], isset[\'x\'], unset[\'x\'], reduce[]]';
    const tree = elscriptLanguage.parser.parse(doc);
    const treeStr = tree.toString();

    expect(treeStr).toContain('keyword');
    expect(treeStr).toContain('bracket');
    expect(treeStr).toContain('string');
    expect(treeStr).toContain('number');
  });

  it('correctly tokenizes built-in root objects and methods', () => {
    const doc = 'Users.find(1) Database.query() Posts.all()';
    const tree = elscriptLanguage.parser.parse(doc);
    const treeStr = tree.toString();

    expect(treeStr).toContain('className');
    expect(treeStr).toContain('variableName');
  });

  it('correctly tokenizes string concatenation operator ~ and comparison operators', () => {
    const doc = 'var[\'a\'] ~ \' hello\' == true != false <= 100';
    const tree = elscriptLanguage.parser.parse(doc);
    const treeStr = tree.toString();

    expect(treeStr).toContain('operator');
    expect(treeStr).toContain('bool');
    expect(treeStr).toContain('number');
  });

  it('correctly tokenizes multi-line and single-line comments', () => {
    const doc = '/* multi-line\n comment */ // single-line comment\nnull';
    const tree = elscriptLanguage.parser.parse(doc);
    const treeStr = tree.toString();

    expect(treeStr).toContain('comment');
    expect(treeStr).toContain('null');
  });

  it('correctly tokenizes multi-line strings across line breaks', () => {
    const doc = 'Database.query(\'\nSELECT "post" as tipo\nUNION ALL\nSELECT "page"\n\')';
    const tree = elscriptLanguage.parser.parse(doc);
    const treeStr = tree.toString();

    expect(treeStr).toContain('className');
    expect(treeStr).toContain('string');
    // Inside the string, lines should be treated as string rather than outside tokens
    expect(treeStr.startsWith('Document(className,punctuation,variableName,bracket,string')).toBe(true);
    expect(treeStr.endsWith('string,bracket)')).toBe(true);
  });

  it('correctly tokenizes strings with escaped quotes without premature termination', () => {
    const doc = 'wp_slash(\'It\\\'s a test\')';
    const tree = elscriptLanguage.parser.parse(doc);
    const treeStr = tree.toString();

    expect(treeStr).toBe('Document(variableName,bracket,string,bracket)');
  });

  it('has elscriptHighlightStyle defined with proper tag styles and canonical colors', () => {
    expect(elscriptHighlightStyle).toBeDefined();
    expect(elscriptHighlightStyle.specs.length).toBeGreaterThan(0);

    const keywordSpec = elscriptHighlightStyle.specs.find(s => s.color === '#af00db');
    expect(keywordSpec).toBeDefined();

    const literalSpec = elscriptHighlightStyle.specs.find(s => s.color === '#0000ff');
    expect(literalSpec).toBeDefined();
  });

  it('handles token stream advance on unknown characters', () => {
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
