import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import ConsoleSession from '../../../components/console/ConsoleSession.vue';
import { useTabsStore } from '../../../stores/tabs.js';

jest.mock('../../../lib/api/client.js', () => ({
  handleFetch: jest.fn(() =>
    Promise.resolve({
      json: () => Promise.resolve({ success: true, data: { result: '42' } }),
    })
  ),
}));

jest.mock('../../../components/console/ConsoleEditor.vue', () => ({
  name: 'ConsoleEditor',
  template: '<div class="mock-console-editor" />',
  methods: {
    formatCode: jest.fn(),
    insertSnippet: jest.fn(),
    focus: jest.fn(),
    setEditorValue: jest.fn(),
    getEditorValue: jest.fn(() => 'test code'),
    clearEditor: jest.fn(),
    moveCursorToEnd: jest.fn(),
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

  it('renders REPL view when tab.mode is repl', () => {
    const tab = tabsStore.createTab({ mode: 'repl' });
    const wrapper = mount(ConsoleSession, {
      props: {
        tabId: tab.id,
        isActive: true,
      },
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    expect(wrapper.find('ul.entries').isVisible()).toBe(true);
    expect(wrapper.find('.scratchpad-workspace').isVisible()).toBe(false);
  });

  it('renders Scratchpad view when tab.mode is scratchpad', () => {
    const tab = tabsStore.createTab({ mode: 'scratchpad' });
    const wrapper = mount(ConsoleSession, {
      props: {
        tabId: tab.id,
        isActive: true,
      },
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    expect(wrapper.find('ul.entries').isVisible()).toBe(false);
    expect(wrapper.find('.scratchpad-workspace').isVisible()).toBe(true);
  });

  it('clears REPL history when clearSession is called in repl mode', () => {
    const tab = tabsStore.createTab({ mode: 'repl' });
    tabsStore.updateTabRepl(tab.id, {
      history: [{ input: '1 + 1', output: '2' }],
    });

    const wrapper = mount(ConsoleSession, {
      props: {
        tabId: tab.id,
        isActive: true,
      },
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

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

    const wrapper = mount(ConsoleSession, {
      props: {
        tabId: tab.id,
        isActive: true,
      },
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    wrapper.vm.clearSession();
    expect(tab.scratchpadCode).toBe('');
    expect(tab.scratchpadResult).toBeNull();
  });
});
