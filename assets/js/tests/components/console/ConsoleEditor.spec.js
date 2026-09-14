let mockCapturedEnterKeymap = [];
let mockCapturedUpDownKeymap = [];

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
        dispatch: jest.fn((tr) => {}),
        destroy: jest.fn(),
        focus: jest.fn(),
        dom: { addEventListener: jest.fn() },
      };
    }),
    {
      lineWrapping: {},
      theme: jest.fn(),
      domEventHandlers: jest.fn(),
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
});
