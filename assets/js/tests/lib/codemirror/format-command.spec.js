import { formatEditorDocument, calculateMappedCursor } from '../../../lib/codemirror/format-command.js';

describe('formatEditorDocument command', () => {
  it('calculates mapped cursor position based on non-whitespace characters', () => {
    const orig = 'prog[set[\'x\', 1]]';
    const fmt = 'prog[\n    set[\'x\', 1]\n]';
    const mapped = calculateMappedCursor(orig, fmt, 5); // right after 'prog['
    expect(mapped).toBeGreaterThanOrEqual(5);
  });

  it('returns false when view is null or empty', () => {
    expect(formatEditorDocument(null)).toBe(false);

    const emptyView = {
      state: {
        doc: {
          toString: () => '   ',
          length: 3,
        },
      },
    };
    expect(formatEditorDocument(emptyView)).toBe(false);
  });

  it('dispatches formatted changes and updates selection when code is unformatted', () => {
    const raw = 'prog[set[\'a\', 1], var[\'a\']]';
    let dispatched = null;

    const mockView = {
      state: {
        doc: {
          toString: () => raw,
          length: raw.length,
        },
        selection: {
          main: { head: 10 },
        },
      },
      dispatch: jest.fn((tr) => {
        dispatched = tr;
      }),
    };

    const result = formatEditorDocument(mockView);
    expect(result).toBe(true);
    expect(mockView.dispatch).toHaveBeenCalledTimes(1);
    expect(dispatched.changes.insert).toContain('\n    set[\'a\', 1],');
    expect(dispatched.selection).toBeDefined();
  });

  it('returns false and does not dispatch if code is already formatted', () => {
    const alreadyFormatted = `prog[
    set['a', 1],
    var['a']
]`;

    const mockView = {
      state: {
        doc: {
          toString: () => alreadyFormatted,
          length: alreadyFormatted.length,
        },
        selection: {
          main: { head: 0 },
        },
      },
      dispatch: jest.fn(),
    };

    const result = formatEditorDocument(mockView);
    expect(result).toBe(false);
    expect(mockView.dispatch).not.toHaveBeenCalled();
  });
});
