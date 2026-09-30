import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import ConsoleSession from '../../../components/console/ConsoleSession.vue';
import { useTabsStore } from '../../../stores/tabs.js';
import { handleFetch } from '../../../lib/api/client.js';

jest.mock('../../../lib/api/client.js', () => ({
  handleFetch: jest.fn(() =>
    Promise.resolve({
      json: () => Promise.resolve({ success: true, data: { result: '42' } }),
    })
  ),
}));

const mockEditorMethods = {
  formatCode: jest.fn(() => true),
  insertSnippet: jest.fn(),
  focus: jest.fn(),
  setEditorValue: jest.fn(),
  getEditorValue: jest.fn(() => 'test code'),
  clearEditor: jest.fn(),
  moveCursorToEnd: jest.fn(),
};

jest.mock('../../../components/console/ConsoleEditor.vue', () => ({
  name: 'ConsoleEditor',
  template: '<div class="mock-console-editor" />',
  methods: {
    formatCode(...args) {
      return mockEditorMethods.formatCode(...args);
    },
    insertSnippet(...args) {
      return mockEditorMethods.insertSnippet(...args);
    },
    focus(...args) {
      return mockEditorMethods.focus(...args);
    },
    setEditorValue(...args) {
      return mockEditorMethods.setEditorValue(...args);
    },
    getEditorValue(...args) {
      return mockEditorMethods.getEditorValue(...args);
    },
    clearEditor(...args) {
      return mockEditorMethods.clearEditor(...args);
    },
    moveCursorToEnd(...args) {
      return mockEditorMethods.moveCursorToEnd(...args);
    },
  },
}));

jest.mock('../../../components/console/ConsoleLog.vue', () => ({
  name: 'ConsoleLog',
  template: '<div class="mock-console-log" />',
}));

jest.mock('../../../components/console/ScratchpadOutput.vue', () => ({
  name: 'ScratchpadOutput',
  template: '<div class="mock-scratchpad-output" />',
}));

describe('ConsoleSession.vue', () => {
  let pinia;
  let tabsStore;

  const createWrapper = (tabId, props = {}) => {
    return mount(ConsoleSession, {
      props: {
        tabId,
        isActive: true,
        ...props,
      },
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });
  };

  beforeEach(() => {
    window.el_settings = {
      nonce: 'dummy-nonce',
      ajax_url: '/wp-admin/admin-ajax.php',
    };
    pinia = createPinia();
    setActivePinia(pinia);
    tabsStore = useTabsStore();
    jest.clearAllMocks();
  });

  afterEach(() => {
    delete window.el_settings;
  });

  describe('View Rendering & Session Clearing', () => {
    it('renders REPL view when tab.mode is repl', () => {
      const tab = tabsStore.createTab({ mode: 'repl' });
      const wrapper = createWrapper(tab.id);

      expect(wrapper.find('ul.entries').isVisible()).toBe(true);
      expect(wrapper.find('.scratchpad-workspace').isVisible()).toBe(false);
    });

    it('renders Scratchpad view when tab.mode is scratchpad', () => {
      const tab = tabsStore.createTab({ mode: 'scratchpad' });
      const wrapper = createWrapper(tab.id);

      expect(wrapper.find('ul.entries').isVisible()).toBe(false);
      expect(wrapper.find('.scratchpad-workspace').isVisible()).toBe(true);
    });

    it('clears REPL history when clearSession is called in repl mode', () => {
      const tab = tabsStore.createTab({ mode: 'repl' });
      tabsStore.updateTabRepl(tab.id, {
        history: [{ input: '1 + 1', output: '2' }],
      });

      const wrapper = createWrapper(tab.id);
      expect(tab.history.length).toBe(1);

      wrapper.vm.clearSession();
      expect(tab.history.length).toBe(0);
      expect(tab.historyIndex).toBe(-1);
    });

    it('clears Scratchpad code and result when clearSession is called in scratchpad mode', () => {
      const tab = tabsStore.createTab({ mode: 'scratchpad' });
      tabsStore.updateTabScratchpad(tab.id, {
        scratchpadCode: 'echo 1;',
        scratchpadResult: { result: '1' },
      });

      const wrapper = createWrapper(tab.id);
      wrapper.vm.clearSession();

      expect(tab.scratchpadCode).toBe('');
      expect(tab.scratchpadResult).toBeNull();
    });
  });

  describe('handleExecute() — REPL Execution Lifecycle', () => {
    it('dispatches AJAX request with parameters and updates history on success', async () => {
      handleFetch.mockImplementationOnce(() =>
        Promise.resolve({
          json: () =>
            Promise.resolve({
              success: true,
              data: {
                result: '84',
                messages: [{ text: 'done' }],
                visualizations: [],
                object_type: 'integer',
              },
            }),
        })
      );

      const tab = tabsStore.createTab({
        mode: 'repl',
        selectedUser: 10,
        selectedSite: 2,
        withBanner: false,
      });

      const wrapper = createWrapper(tab.id);

      wrapper.vm.handleExecute('42 * 2');

      expect(tab.isEvaluating).toBe(true);
      expect(tab.history.length).toBe(1);
      expect(tab.history[0].pending).toBe(true);
      expect(tab.history[0].input).toBe('42 * 2');

      expect(handleFetch).toHaveBeenCalledTimes(1);
      const [url, options] = handleFetch.mock.calls[0];
      expect(url).toBe('/wp-admin/admin-ajax.php');
      expect(options.method).toBe('POST');

      const body = options.body;
      expect(body.get('action')).toBe('expressionlab_evaluate_expression');
      expect(body.get('expression')).toBe('42 * 2');
      expect(body.get('nonce')).toBe('dummy-nonce');
      expect(body.get('user_id')).toBe('10');
      expect(body.get('site_id')).toBe('2');

      // Wait for promise tick
      await new Promise((resolve) => setTimeout(resolve, 0));

      expect(tab.isEvaluating).toBe(false);
      expect(tab.history[0].pending).toBe(false);
      expect(tab.history[0].output).toBe('84');
      expect(tab.history[0].object_type).toBe('integer');
      expect(tab.history[0].messages).toEqual([{ text: 'done' }]);
    });

    it('handles server failure response (success: false)', async () => {
      handleFetch.mockImplementationOnce(() =>
        Promise.resolve({
          json: () =>
            Promise.resolve({
              success: false,
              data: { message: 'Syntax error in expression' },
            }),
        })
      );

      const tab = tabsStore.createTab({ mode: 'repl', withBanner: false });
      const wrapper = createWrapper(tab.id);

      wrapper.vm.handleExecute('invalid code');
      await new Promise((resolve) => setTimeout(resolve, 0));

      expect(tab.isEvaluating).toBe(false);
      expect(tab.history[0].pending).toBe(false);
      expect(tab.history[0].type).toBe('error');
      expect(tab.history[0].output).toBe('Syntax error in expression');
    });

    it('handles unexpected network error gracefully', async () => {
      handleFetch.mockImplementationOnce(() => Promise.reject(new Error('Network disconnected')));

      const tab = tabsStore.createTab({ mode: 'repl', withBanner: false });
      const wrapper = createWrapper(tab.id);

      wrapper.vm.handleExecute('test_network()');
      await new Promise((resolve) => setTimeout(resolve, 0));

      expect(tab.isEvaluating).toBe(false);
      expect(tab.history[0].pending).toBe(false);
      expect(tab.history[0].type).toBe('error');
      expect(tab.history[0].output).toBe('Error evaluating expression.');
    });
  });

  describe('executeScratchpad() — Scratchpad Execution Lifecycle', () => {
    it('executes scratchpad code, toggles output visibility, and saves result', async () => {
      handleFetch.mockImplementationOnce(() =>
        Promise.resolve({
          json: () =>
            Promise.resolve({
              success: true,
              data: {
                result: '[1, 2, 3]',
                messages: [],
                visualizations: [{ type: 'table' }],
                object_type: 'array',
              },
            }),
        })
      );

      const tab = tabsStore.createTab({ mode: 'scratchpad' });
      const wrapper = createWrapper(tab.id);

      wrapper.vm.executeScratchpad('range(1, 3)');

      expect(tab.scratchpadCode).toBe('range(1, 3)');
      expect(tab.isEvaluating).toBe(true);

      const [, options] = handleFetch.mock.calls[0];
      expect(options.body.get('expression')).toBe('range(1, 3)');

      await new Promise((resolve) => setTimeout(resolve, 0));

      expect(tab.isEvaluating).toBe(false);
      expect(tab.isScratchpadOutputVisible).toBe(true);
      expect(tab.scratchpadResult).toEqual({
        result: '[1, 2, 3]',
        messages: [],
        visualizations: [{ type: 'table' }],
        object_type: 'array',
        type: null,
      });
    });

    it('guards against execution when already evaluating', () => {
      const tab = tabsStore.createTab({ mode: 'scratchpad' });
      tabsStore.setTabEvaluating(tab.id, true);

      const wrapper = createWrapper(tab.id);
      wrapper.vm.executeScratchpad('echo 123;');

      expect(handleFetch).not.toHaveBeenCalled();
    });

    it('guards against execution when code is empty or whitespace', () => {
      const tab = tabsStore.createTab({ mode: 'scratchpad' });
      const wrapper = createWrapper(tab.id);

      wrapper.vm.executeScratchpad('   \n  ');
      expect(handleFetch).not.toHaveBeenCalled();
    });

    it('clears scratchpad output with clearScratchpadOutput', () => {
      const tab = tabsStore.createTab({ mode: 'scratchpad' });
      tabsStore.updateTabScratchpad(tab.id, {
        scratchpadResult: { result: 'test' },
      });

      const wrapper = createWrapper(tab.id);
      expect(tab.scratchpadResult).not.toBeNull();

      const outputComponent = wrapper.findComponent({ name: 'ScratchpadOutput' });
      outputComponent.vm.$emit('clear-output');

      expect(tab.scratchpadResult).toBeNull();
    });
  });

  describe('Concurrency & AbortController Lifecycle', () => {
    it('aborts previous in-flight request when a new evaluation starts', () => {
      const tab = tabsStore.createTab({ mode: 'repl', withBanner: false });
      const wrapper = createWrapper(tab.id);

      wrapper.vm.handleExecute('query_1');
      const firstSignal = handleFetch.mock.calls[0][1].signal;
      expect(firstSignal.aborted).toBe(false);

      wrapper.vm.handleExecute('query_2');
      expect(firstSignal.aborted).toBe(true);

      const secondSignal = handleFetch.mock.calls[1][1].signal;
      expect(secondSignal.aborted).toBe(false);
    });

    it('aborts active evaluation when component is unmounted', () => {
      const tab = tabsStore.createTab({ mode: 'repl', withBanner: false });
      const wrapper = createWrapper(tab.id);

      wrapper.vm.handleExecute('query_running');
      const signal = handleFetch.mock.calls[0][1].signal;
      expect(signal.aborted).toBe(false);

      wrapper.unmount();
      expect(signal.aborted).toBe(true);
    });

    it('silently ignores AbortError on cancelled evaluation without corrupting history', async () => {
      const abortError = new Error('The user aborted a request.');
      abortError.name = 'AbortError';

      handleFetch.mockImplementationOnce(() => Promise.reject(abortError));

      const tab = tabsStore.createTab({ mode: 'repl', withBanner: false });
      const wrapper = createWrapper(tab.id);

      wrapper.vm.handleExecute('aborted_query');
      await new Promise((resolve) => setTimeout(resolve, 0));

      // In ConsoleSession.vue: if (err.name === 'AbortError') return;
      // It must not mutate history to error or crash
      expect(tab.history[0].type).toBeNull();
      expect(tab.history[0].output).toBe('"Loading..."');
    });
  });

  describe('History Navigation (handleHistoryNav)', () => {
    it('navigates up and down through REPL history', async () => {
      const tab = tabsStore.createTab({ mode: 'repl', withBanner: false });
      tabsStore.updateTabRepl(tab.id, {
        history: [
          { input: 'first_command', output: '1' },
          { input: 'second_command', output: '2' },
          { input: 'third_command', output: '3' },
        ],
        historyIndex: 3,
      });

      const wrapper = createWrapper(tab.id);
      const editorComponent = wrapper.findComponent({ name: 'ConsoleEditor' });

      // Trigger history-up (from index 3 -> 2: 'third_command')
      editorComponent.vm.$emit('history-up');
      expect(tab.historyIndex).toBe(2);
      expect(mockEditorMethods.setEditorValue).toHaveBeenCalledWith('third_command');

      // Trigger history-up again (from index 2 -> 1: 'second_command')
      editorComponent.vm.$emit('history-up');
      expect(tab.historyIndex).toBe(1);
      expect(mockEditorMethods.setEditorValue).toHaveBeenCalledWith('second_command');

      // Trigger history-down (from index 1 -> 2: 'third_command')
      editorComponent.vm.$emit('history-down');
      expect(tab.historyIndex).toBe(2);
      expect(mockEditorMethods.setEditorValue).toHaveBeenCalledWith('third_command');
    });

    it('clamps history navigation at lower (0) and upper (length - 1) boundaries', () => {
      const tab = tabsStore.createTab({ mode: 'repl', withBanner: false });
      tabsStore.updateTabRepl(tab.id, {
        history: [{ input: 'sole_command', output: 'ok' }],
        historyIndex: 0,
      });

      const wrapper = createWrapper(tab.id);
      const editorComponent = wrapper.findComponent({ name: 'ConsoleEditor' });

      // Cannot go below 0
      editorComponent.vm.$emit('history-up');
      expect(tab.historyIndex).toBe(0);

      // Cannot go above length - 1
      editorComponent.vm.$emit('history-down');
      expect(tab.historyIndex).toBe(0);
    });
  });

  describe('ConsoleLog Event Delegation (handleEditEntry & handleRunCode)', () => {
    it('handles edit-entry event from ConsoleLog', () => {
      const tab = tabsStore.createTab({ mode: 'repl', withBanner: false });
      tabsStore.updateTabRepl(tab.id, {
        history: [{ input: 'let $x = 10;', output: 10 }],
      });

      const wrapper = createWrapper(tab.id);
      const logComponent = wrapper.findComponent({ name: 'ConsoleLog' });

      // Edit input of history item 0
      logComponent.vm.$emit('edit-entry', 0, 'input');
      expect(mockEditorMethods.setEditorValue).toHaveBeenCalledWith('let $x = 10;');

      // Edit with custom text
      logComponent.vm.$emit('edit-entry', 0, 'input', 'custom text override');
      expect(mockEditorMethods.setEditorValue).toHaveBeenCalledWith('custom text override');
    });

    it('delegates run-code event to REPL execute or Scratchpad execute', () => {
      // 1. REPL mode
      const replTab = tabsStore.createTab({ mode: 'repl', withBanner: false });
      const replWrapper = createWrapper(replTab.id);
      const replLog = replWrapper.findComponent({ name: 'ConsoleLog' });

      replLog.vm.$emit('run-code', 'quick_repl()');
      expect(handleFetch).toHaveBeenCalledTimes(1);
      expect(handleFetch.mock.calls[0][1].body.get('expression')).toBe('quick_repl()');

      // 2. Scratchpad mode
      const scratchTab = tabsStore.createTab({ mode: 'scratchpad' });
      const scratchWrapper = createWrapper(scratchTab.id);

      scratchWrapper.vm.handleRunCode('quick_scratchpad()');
      expect(handleFetch).toHaveBeenCalledTimes(2);
      expect(handleFetch.mock.calls[1][1].body.get('expression')).toBe('quick_scratchpad()');
    });
  });

  describe('Active Tab Watcher & Layout Refresh', () => {
    it('resets needsLayoutRefresh when isActive transitions to true', async () => {
      const tab = tabsStore.createTab({ mode: 'scratchpad' });
      tabsStore.updateTabScratchpad(tab.id, { needsLayoutRefresh: true });

      const wrapper = createWrapper(tab.id, { isActive: false });
      expect(tab.needsLayoutRefresh).toBe(true);

      await wrapper.setProps({ isActive: true });
      expect(tab.needsLayoutRefresh).toBe(false);
    });
  });

  describe('Public API & Exposed Delegators', () => {
    it('delegates insertSnippet, formatCode, and focus to active editor', () => {
      const tab = tabsStore.createTab({ mode: 'repl' });
      const wrapper = createWrapper(tab.id);

      wrapper.vm.insertSnippet('test_snippet()', { replaceWord: true });
      expect(mockEditorMethods.insertSnippet).toHaveBeenCalledWith('test_snippet()', { replaceWord: true });

      const formatResult = wrapper.vm.formatCode();
      expect(mockEditorMethods.formatCode).toHaveBeenCalled();
      expect(formatResult).toBe(true);

      wrapper.vm.focus();
      expect(mockEditorMethods.focus).toHaveBeenCalled();
    });

    it('emits save-snippet event when handleSaveScratchpadSnippet is called', () => {
      const tab = tabsStore.createTab({ mode: 'scratchpad' });
      const wrapper = createWrapper(tab.id);

      wrapper.vm.handleSaveScratchpadSnippet();
      expect(wrapper.emitted('save-snippet')).toBeTruthy();
      expect(wrapper.emitted('save-snippet')[0]).toEqual(['test code']);
    });
  });
});
