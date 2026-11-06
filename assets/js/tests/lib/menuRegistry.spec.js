import { MenuRegistry, matchesShortcut } from '../../lib/menuRegistry.js';

describe('menuRegistry', () => {
  let registry;

  beforeEach(() => {
    registry = new MenuRegistry();
  });

  it('returns baseGroups unchanged when no transformers are registered', () => {
    const baseGroups = [
      { id: 'group1', items: [{ id: 'item1', label: 'Item 1' }] },
    ];
    const result = registry.apply('file', baseGroups, {});
    expect(result).toEqual(baseGroups);
  });

  it('applies registered transformers in sequence', () => {
    registry.registerTransformer('file', (groups) => {
      groups[0].items.push({ id: 'item2', label: 'Item 2' });
      return groups;
    });

    registry.registerTransformer('file', (groups) => {
      groups.push({ id: 'group2', items: [{ id: 'item3', label: 'Item 3' }] });
      return groups;
    });

    const baseGroups = [
      { id: 'group1', items: [{ id: 'item1', label: 'Item 1' }] },
    ];

    const result = registry.apply('file', baseGroups, {});
    expect(result.length).toBe(2);
    expect(result[0].items.length).toBe(2);
    expect(result[1].id).toBe('group2');
    expect(result[1].items[0].id).toBe('item3');
  });

  it('passes context to transformer', () => {
    let capturedContext = null;
    registry.registerTransformer('file', (groups, context) => {
      capturedContext = context;
      return groups;
    });

    registry.apply('file', [], { isNotebook: true, foo: 'bar' });
    expect(capturedContext).toEqual({ isNotebook: true, foo: 'bar' });
  });

  it('catches and isolates errors thrown in transformers', () => {
    const spy = jest.spyOn(console, 'error').mockImplementation(() => {});

    registry.registerTransformer('file', () => {
      throw new Error('Explosion');
    });

    const baseGroups = [
      { id: 'group1', items: [{ id: 'item1', label: 'Item 1' }] },
    ];

    const result = registry.apply('file', baseGroups, {});
    expect(result).toBeDefined();
    expect(result[0].id).toBe('group1');
    expect(spy).toHaveBeenCalled();
    spy.mockRestore();
  });

  it('resets transformers correctly', () => {
    registry.registerTransformer('file', (groups) => {
      groups[0].items.push({ id: 'added' });
      return groups;
    });
    registry.reset('file');

    const result = registry.apply('file', [{ id: 'g', items: [] }]);
    expect(result[0].items.length).toBe(0);
  });

  it('shallow-clones individual menu items so in-place transformer mutations do not mutate baseGroups', () => {
    const baseItem = { id: 'clear-outputs', label: 'Clear outputs' };
    const baseGroups = [{ id: 'edit', items: [baseItem] }];

    registry.registerTransformer('file', (groups) => {
      const item = groups[0].items.find((i) => i.id === 'clear-outputs');
      if (item) {
        item.label = 'Clear all cell outputs';
      }
      return groups;
    });

    const result = registry.apply('file', baseGroups, {});
    expect(result[0].items[0].label).toBe('Clear all cell outputs');
    expect(baseItem.label).toBe('Clear outputs');
  });
});

describe('matchesShortcut', () => {
  it('correctly matches Ctrl+S and Cmd+S', () => {
    expect(matchesShortcut({ ctrlKey: true, key: 's' }, 'Ctrl+S')).toBe(true);
    expect(matchesShortcut({ metaKey: true, key: 's' }, 'Ctrl+S')).toBe(true);
    expect(matchesShortcut({ ctrlKey: true, key: 'S' }, 'Ctrl+S')).toBe(true);
    expect(matchesShortcut({ ctrlKey: false, key: 's' }, 'Ctrl+S')).toBe(false);
  });

  it('correctly matches macOS symbol shortcuts (⌘+S, ⌘+Shift+S, ⌥+N)', () => {
    expect(matchesShortcut({ metaKey: true, key: 's' }, '⌘+S')).toBe(true);
    expect(matchesShortcut({ metaKey: true, shiftKey: true, key: 's' }, '⌘+Shift+S')).toBe(true);
    expect(matchesShortcut({ metaKey: true, shiftKey: true, key: 'z' }, '⌘+⇧+Z')).toBe(true);
    expect(matchesShortcut({ altKey: true, key: 'n' }, '⌥+N')).toBe(true);
    expect(matchesShortcut({ metaKey: false, key: 's' }, '⌘+S')).toBe(false);
  });

  it('correctly matches Ctrl+Shift+S', () => {
    expect(matchesShortcut({ ctrlKey: true, shiftKey: true, key: 's' }, 'Ctrl+Shift+S')).toBe(true);
    expect(matchesShortcut({ ctrlKey: true, shiftKey: false, key: 's' }, 'Ctrl+Shift+S')).toBe(false);
  });

  it('correctly rejects when extra modifiers are pressed', () => {
    expect(matchesShortcut({ ctrlKey: true, altKey: true, key: 's' }, 'Ctrl+S')).toBe(false);
  });
});

