let mockCapturedEnterKeymap = [];
let mockCapturedUpDownKeymap = [];
let mockCapturedFormatKeymap = [];
let mockCapturedUpdateListeners = [];

jest.mock('@codemirror/state', () => ({
  EditorState: {
    create: jest.fn((config = {}) => {
      const docStr = config.doc || '';
      return {
        doc: {
          toString: () => docStr,
          length: docStr.length,
        },
      };
    }),
    tabSize: { of: jest.fn() },
  },
}));

jest.mock('@codemirror/view', () => {
  const MockEditorView = Object.assign(
    jest.fn().mockImplementation((config = {}) => {
      let currentDoc = config.state?.doc?.toString() || '';
      return {
        state: {
          get doc() {
            return {
              toString: () => currentDoc,
              length: currentDoc.length,
            };
          },
          update: jest.fn((u) => {
            if (u.changes && typeof u.changes.insert === 'string') {
              currentDoc = u.changes.insert;
            }
            return {};
          }),
          selection: { main: { from: 0, to: 0 } },
          replaceSelection: jest.fn((text) => {
            currentDoc = text;
            return {};
          }),
        },
        dispatch: jest.fn((tr) => {
          mockCapturedUpdateListeners.forEach((listener) => {
            listener({
              docChanged: true,
              state: {
                doc: {
                  toString: () => currentDoc,
                  length: currentDoc.length,
                },
              },
            });
          });
        }),
        destroy: jest.fn(),
        focus: jest.fn(),
        dom: { addEventListener: jest.fn() },
      };
    }),
    {
      lineWrapping: {},
      theme: jest.fn(),
      domEventHandlers: jest.fn(),
      updateListener: {
        of: jest.fn((fn) => {
          mockCapturedUpdateListeners.push(fn);
          return {};
        }),
      },
    }
  );

  return {
    EditorView: MockEditorView,
    keymap: {
      of: jest.fn((keymaps) => {
        const list = Array.isArray(keymaps) ? keymaps.flat(Infinity) : [keymaps];
        list.forEach((k) => {
          if (k && k.key === 'Enter') mockCapturedEnterKeymap.push(k);
          if (k && (k.key === 'Alt-ArrowUp' || k.key === 'Alt-ArrowDown')) mockCapturedUpDownKeymap.push(k);
          if (k && k.key === 'Shift-Alt-f') mockCapturedFormatKeymap.push(k);
        });
        return {};
      }),
    },
  };
});

jest.mock('@codemirror/language', () => ({
  indentUnit: { of: jest.fn() },
  bracketMatching: jest.fn(),
}));

jest.mock('../../../lib/codemirror/elscript.js', () => ({
  elscript: jest.fn(() => []),
  elscriptLanguage: {},
}));

jest.mock('../../../lib/codemirror/elscript-linter.js', () => ({
  expressionLabLinter: jest.fn(() => []),
  lintExpressionLab: jest.fn(() => []),
}));

jest.mock('@codemirror/commands', () => ({
  history: jest.fn(),
  historyKeymap: [],
  indentWithTab: {},
  insertNewlineAndIndent: jest.fn(),
}));

jest.mock('@fsegurai/codemirror-theme-bundle', () => ({
  vsCodeLight: {},
}));

import { mount } from '@vue/test-utils';
import { createTestingPinia } from '@pinia/testing';
import ConsoleEditor from '../../../components/console/ConsoleEditor.vue';

describe('ConsoleEditor.vue', () => {
  beforeEach(() => {
    mockCapturedEnterKeymap = [];
    mockCapturedUpDownKeymap = [];
    mockCapturedFormatKeymap = [];
    mockCapturedUpdateListeners = [];
  });

  it('renders correctly and mounts CodeMirror container', () => {
    const wrapper = mount(ConsoleEditor, {
      global: {
        plugins: [createTestingPinia()],
      },
      props: {
        loading: false,
      },
    });

    expect(wrapper.find('.code-editor-container').exists()).toBe(true);
    expect(wrapper.find('.metro-spinner').isVisible()).toBe(false);
  });

  it('shows loading spinner when loading prop is true', () => {
    const wrapper = mount(ConsoleEditor, {
      global: {
        plugins: [createTestingPinia()],
      },
      props: {
        loading: true,
      },
    });

    expect(wrapper.find('.loading').exists()).toBe(true);
    expect(wrapper.find('.metro-spinner').isVisible()).toBe(true);
  });

  it('exposes setEditorValue and insertSnippet methods', async () => {
    const wrapper = mount(ConsoleEditor, {
      global: {
        plugins: [createTestingPinia()],
      },
    });

    expect(typeof wrapper.vm.setEditorValue).toBe('function');
    expect(typeof wrapper.vm.insertSnippet).toBe('function');
    expect(typeof wrapper.vm.moveCursorToEnd).toBe('function');
    expect(typeof wrapper.vm.focus).toBe('function');

    wrapper.vm.setEditorValue('Users.all()');
    wrapper.vm.insertSnippet('Database.query()');
  });

  it('emits execute when Enter key handler runs with valid code', async () => {
    const wrapper = mount(ConsoleEditor, {
      global: {
        plugins: [createTestingPinia()],
      },
    });

    wrapper.vm.setEditorValue('Users.all()');

    const enterBinding = mockCapturedEnterKeymap.find((k) => k.key === 'Enter');
    expect(enterBinding).toBeDefined();

    enterBinding.run();

    expect(wrapper.emitted('execute')).toBeTruthy();
    expect(wrapper.emitted('execute')[0]).toEqual(['Users.all()']);
  });

  it('emits clear when input is "clear"', async () => {
    const wrapper = mount(ConsoleEditor, {
      global: {
        plugins: [createTestingPinia()],
      },
    });

    wrapper.vm.setEditorValue('  CLEAR  ');

    const enterBinding = mockCapturedEnterKeymap.find((k) => k.key === 'Enter');
    enterBinding.run();

    expect(wrapper.emitted('clear')).toBeTruthy();
    expect(wrapper.emitted('execute')).toBeFalsy();
  });

  it('emits history-up and history-down on Alt-ArrowUp/Down keymaps', async () => {
    const wrapper = mount(ConsoleEditor, {
      global: {
        plugins: [createTestingPinia()],
      },
    });

    const upBinding = mockCapturedUpDownKeymap.find((k) => k.key === 'Alt-ArrowUp');
    const downBinding = mockCapturedUpDownKeymap.find((k) => k.key === 'Alt-ArrowDown');

    expect(upBinding).toBeDefined();
    expect(downBinding).toBeDefined();

    upBinding.run();
    expect(wrapper.emitted('history-up')).toBeTruthy();

    downBinding.run();
    expect(wrapper.emitted('history-down')).toBeTruthy();
  });

  it('exposes formatCode and registers Shift-Alt-f format keymap', async () => {
    const wrapper = mount(ConsoleEditor, {
      global: {
        plugins: [createTestingPinia()],
      },
    });

    expect(typeof wrapper.vm.formatCode).toBe('function');
    const formatBinding = mockCapturedFormatKeymap.find((k) => k.key === 'Shift-Alt-f');
    expect(formatBinding).toBeDefined();
    expect(typeof formatBinding.run).toBe('function');
  });

  it('updates hasSyntaxErrors and applies class when syntax errors are detected', async () => {
    jest.useFakeTimers();
    const { lintExpressionLab } = require('../../../lib/codemirror/elscript-linter.js');
    lintExpressionLab.mockReturnValueOnce([
      { from: 0, to: 5, severity: 'error', message: 'Syntax error: unexpected token' },
    ]);

    const wrapper = mount(ConsoleEditor, {
      global: {
        plugins: [createTestingPinia()],
      },
    });

    expect(wrapper.vm.hasSyntaxErrors).toBe(false);
    expect(wrapper.classes()).not.toContain('has-syntax-errors');

    wrapper.vm.setEditorValue('prog[');
    jest.advanceTimersByTime(200);
    await wrapper.vm.$nextTick();

    expect(wrapper.vm.hasSyntaxErrors).toBe(true);
    expect(wrapper.classes()).toContain('has-syntax-errors');

    // Setting empty resets immediately
    lintExpressionLab.mockReturnValue([]);
    wrapper.vm.setEditorValue('');
    await wrapper.vm.$nextTick();

    expect(wrapper.vm.hasSyntaxErrors).toBe(false);
    expect(wrapper.classes()).not.toContain('has-syntax-errors');

    jest.useRealTimers();
  });

  it('resets hasSyntaxErrors when enter executes code', async () => {
    jest.useFakeTimers();
    const { lintExpressionLab } = require('../../../lib/codemirror/elscript-linter.js');
    lintExpressionLab.mockReturnValueOnce([
      { from: 0, to: 5, severity: 'error', message: 'Syntax error' },
    ]);

    const wrapper = mount(ConsoleEditor, {
      global: {
        plugins: [createTestingPinia()],
      },
    });

    wrapper.vm.setEditorValue('prog[');
    jest.advanceTimersByTime(200);
    await wrapper.vm.$nextTick();

    expect(wrapper.vm.hasSyntaxErrors).toBe(true);

    const enterBinding = mockCapturedEnterKeymap.find((k) => k.key === 'Enter');
    enterBinding.run();
    await wrapper.vm.$nextTick();

    expect(wrapper.vm.hasSyntaxErrors).toBe(false);
    expect(wrapper.classes()).not.toContain('has-syntax-errors');

    jest.useRealTimers();
  });
});
