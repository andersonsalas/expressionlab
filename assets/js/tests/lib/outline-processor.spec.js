import { processOutlineData } from '../../lib/outline-processor.js';

describe('outline-processor.js', () => {
  const mockOutlineData = {
    objects: {
      Database: {
        methods: {
          query: {
            insertText: 'query(${1:sql})',
            doc: { summary: 'Execute query' },
          },
          connect: {
            doc: { summary: 'Connect' }, // no insertText, tests fallback
          },
        },
        constants: {
          DRIVER: {
            insertText: 'Database.DRIVER',
            doc: { summary: 'Driver constant' },
          },
          DEFAULT_PORT: {
            doc: { summary: 'Default port' }, // tests fallback
          },
        },
        properties: {
          connection: {
            insertText: 'Database.connection',
            doc: { summary: 'Current connection' },
          },
          status: {
            doc: { summary: 'Status' }, // tests fallback
          },
        },
      },
      EmptyObject: {},
    },
    functions: {
      rand: {
        insertText: 'rand(${1:min}, ${2:max})',
        doc: { summary: 'Random number generator' },
      },
      time: {
        doc: { summary: 'Current timestamp' }, // tests fallback
      },
    },
    constants: {
      PHP_VERSION: {
        insertText: 'PHP_VERSION',
        doc: { summary: 'PHP Version' },
      },
      MAX_INT: {
        doc: { summary: 'Max Integer' }, // tests fallback
      },
    },
    typeRegistry: {
      Database: {
        query: { type: 'method', returnType: 'QueryResult' },
      },
    },
  };

  it('processes objects with methods, constants, and properties correctly', () => {
    const { outlineFlat, outlineTree, outlineChained } = processOutlineData(mockOutlineData);

    expect(outlineChained.rootObjects.has('Database')).toBe(true);
    expect(outlineChained.rootObjects.has('EmptyObject')).toBe(true);
    expect(outlineChained.typeRegistry).toBe(mockOutlineData.typeRegistry);

    const dbNode = outlineTree.find((n) => n.label === 'Database');
    expect(dbNode).toBeDefined();
    expect(dbNode.kind).toBe('Object');
    expect(dbNode.icon).toBe('codicon-symbol-class');

    // Methods in tree and flat
    const queryMethod = dbNode.methods.find((m) => m.label === 'query');
    expect(queryMethod).toBeDefined();
    expect(queryMethod.kind).toBe('Method');
    expect(queryMethod.insertText).toBe('query(${1:sql})');

    const connectMethod = dbNode.methods.find((m) => m.label === 'connect');
    expect(connectMethod.insertText).toBe('Database.connect()');

    // Constants in tree and flat
    const driverConst = dbNode.methods.find((m) => m.label === 'DRIVER');
    expect(driverConst).toBeDefined();
    expect(driverConst.kind).toBe('Constant');
    expect(driverConst.insertText).toBe('Database.DRIVER');

    const portConst = dbNode.methods.find((m) => m.label === 'DEFAULT_PORT');
    expect(portConst.insertText).toBe('Database.DEFAULT_PORT');

    // Properties in tree and flat
    const connProp = dbNode.methods.find((m) => m.label === 'connection');
    expect(connProp).toBeDefined();
    expect(connProp.kind).toBe('Property');
    expect(connProp.insertText).toBe('Database.connection');

    const statusProp = dbNode.methods.find((m) => m.label === 'status');
    expect(statusProp.insertText).toBe('Database.status');

    // Flat items include prefix
    expect(outlineFlat.some((i) => i.label === 'Database.query')).toBe(true);
    expect(outlineFlat.some((i) => i.label === 'Database.DRIVER')).toBe(true);
    expect(outlineFlat.some((i) => i.label === 'Database.connection')).toBe(true);
  });

  it('creates Functions category and places it at the start of outlineTree', () => {
    const { outlineFlat, outlineTree } = processOutlineData(mockOutlineData);

    const funcNode = outlineTree[0];
    expect(funcNode.label).toBe('Functions');
    expect(funcNode.kind).toBe('Category');
    expect(funcNode.icon).toBe('codicon-library');
    expect(funcNode.expanded).toBe(true);

    const randFunc = funcNode.methods.find((m) => m.label === 'rand');
    expect(randFunc.insertText).toBe('rand(${1:min}, ${2:max})');

    const timeFunc = funcNode.methods.find((m) => m.label === 'time');
    expect(timeFunc.insertText).toBe('time()');

    expect(outlineFlat.some((i) => i.label === 'rand' && i.kind === 'Function')).toBe(true);
    expect(outlineFlat.some((i) => i.label === 'time' && i.kind === 'Function')).toBe(true);
  });

  it('creates Constants category and appends it to outlineTree', () => {
    const { outlineFlat, outlineTree } = processOutlineData(mockOutlineData);

    const constNode = outlineTree.find((n) => n.label === 'Constants');
    expect(constNode).toBeDefined();
    expect(constNode.kind).toBe('Category');
    expect(constNode.icon).toBe('codicon-symbol-constant');
    expect(constNode.expanded).toBe(true);

    const phpVer = constNode.methods.find((m) => m.label === 'PHP_VERSION');
    expect(phpVer.insertText).toBe('PHP_VERSION');

    const maxInt = constNode.methods.find((m) => m.label === 'MAX_INT');
    expect(maxInt.insertText).toBe('MAX_INT');

    expect(outlineFlat.some((i) => i.label === 'PHP_VERSION' && i.kind === 'Constant')).toBe(true);
  });

  it('handles empty outlineData gracefully', () => {
    const result = processOutlineData({});
    expect(result.outlineFlat).toEqual([]);
    expect(result.outlineTree).toEqual([]);
    expect(result.outlineChained.rootObjects.size).toBe(0);
  });
});
