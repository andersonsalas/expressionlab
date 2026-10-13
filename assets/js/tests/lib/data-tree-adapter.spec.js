import { convertToTreeAst, isPhpAstNode, MAX_TREE_DEPTH } from '../../lib/data-tree-adapter.js';
import { parsePhpSerialized } from '../../lib/php-serializer/parser.js';

describe('data-tree-adapter.js', () => {
  it('converts primitives properly', () => {
    expect(convertToTreeAst(null)).toEqual({ type: 'null', raw: 'null' });
    expect(convertToTreeAst(undefined)).toEqual({ type: 'null', raw: 'undefined' });
    expect(convertToTreeAst(true)).toEqual({ type: 'boolean', value: true });
    expect(convertToTreeAst(false)).toEqual({ type: 'boolean', value: false });
    expect(convertToTreeAst(42)).toEqual({ type: 'integer', value: 42 });
    expect(convertToTreeAst(3.14)).toEqual({ type: 'double', value: 3.14 });
    expect(convertToTreeAst('test string')).toEqual({ type: 'string', value: 'test string' });
  });

  it('converts arrays with indices as integer keys', () => {
    const ast = convertToTreeAst(['first', 'second']);
    expect(ast.type).toBe('array');
    expect(ast.count).toBe(2);
    expect(ast.entries).toHaveLength(2);
    expect(ast.entries[0].key).toEqual({ type: 'integer', value: 0 });
    expect(ast.entries[0].value).toEqual({ type: 'string', value: 'first' });
    expect(ast.entries[1].key).toEqual({ type: 'integer', value: 1 });
    expect(ast.entries[1].value).toEqual({ type: 'string', value: 'second' });
  });

  it('converts plain objects with properties', () => {
    const ast = convertToTreeAst({ username: 'admin', active: true });
    expect(ast.type).toBe('object');
    expect(ast.className).toBe('Object');
    expect(ast.propertyCount).toBe(2);
    expect(ast.properties).toHaveLength(2);
    expect(ast.properties[0].name).toBe('username');
    expect(ast.properties[0].visibility).toBeNull();
    expect(ast.properties[0].value).toEqual({ type: 'string', value: 'admin' });
    expect(ast.properties[1].name).toBe('active');
    expect(ast.properties[1].value).toEqual({ type: 'boolean', value: true });
  });

  it('preserves existing PHP AST nodes without double-wrapping', () => {
    const existingNode = parsePhpSerialized('O:7:"WP_Post":1:{s:2:"ID";i:10;}');

    expect(isPhpAstNode(existingNode)).toBe(true);
    expect(convertToTreeAst(existingNode)).toBe(existingNode);
  });

  it('passes through PHP AST nodes nested inside plain JS structures', () => {
    const nested = parsePhpSerialized('a:1:{i:0;s:1:"x";}');
    const ast = convertToTreeAst({ payload: nested });
    expect(ast.properties[0].value).toBe(nested);
  });

  it('does not mistake JSON objects that look like AST nodes for parser output', () => {
    const field = { type: 'text', value: 'foo', name: 'email' };
    expect(isPhpAstNode(field)).toBe(false);

    const ast = convertToTreeAst(field);
    expect(ast.type).toBe('object');
    expect(ast.properties.map((p) => p.name)).toEqual(['type', 'value', 'name']);

    // A look-alike with a non-array `entries` must not reach the renderer as an AST.
    const tricky = convertToTreeAst({ type: 'array', entries: 5 });
    expect(tricky.type).toBe('object');
    expect(tricky.properties[1].value).toEqual({ type: 'integer', value: 5 });
  });

  it('handles nested structures', () => {
    const nested = {
      items: [
        { id: 1, label: 'item-1' },
      ],
    };

    const ast = convertToTreeAst(nested);
    expect(ast.type).toBe('object');
    expect(ast.properties[0].name).toBe('items');
    expect(ast.properties[0].value.type).toBe('array');
    expect(ast.properties[0].value.entries[0].value.type).toBe('object');
  });

  it('guards against circular references', () => {
    const circular = { name: 'self' };
    circular.loop = circular;

    const ast = convertToTreeAst(circular);
    expect(ast.type).toBe('object');
    expect(ast.properties.find(p => p.name === 'loop').value).toEqual({ type: 'circular', value: '[Circular]' });
  });

  it('renders shared (non-cyclic) references twice instead of flagging them as circular', () => {
    const shared = { id: 1 };
    const ast = convertToTreeAst({ a: shared, b: shared, list: [shared, shared] });
    expect(ast.properties[0].value.type).toBe('object');
    expect(ast.properties[1].value.type).toBe('object');
    expect(ast.properties[2].value.entries.every((e) => e.value.type === 'object')).toBe(true);
  });

  it('stops at the maximum depth with a dedicated sentinel', () => {
    let deep = { leaf: true };
    for (let i = 0; i < MAX_TREE_DEPTH + 5; i++) {
      deep = { child: deep };
    }
    let node = convertToTreeAst(deep);
    while (node.type === 'object') {
      node = node.properties[0].value;
    }
    expect(node).toEqual({ type: 'max-depth', value: '[Max depth reached]' });
  });

  it('ignores payload-controlled constructor keys when resolving class names', () => {
    const spoofed = JSON.parse('{"constructor": {"name": "Evil"}, "__proto__": {"polluted": true}}');
    const ast = convertToTreeAst(spoofed);
    expect(ast.className).toBe('Object');
    expect(ast.properties.map((p) => p.name)).toEqual(['constructor', '__proto__']);
    expect({}.polluted).toBeUndefined();

    class Point { constructor() { this.x = 1; } }
    expect(convertToTreeAst(new Point()).className).toBe('Point');
  });
});
