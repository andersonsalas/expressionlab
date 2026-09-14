jest.mock('@codemirror/state', () => ({
  EditorState: {
    create: jest.fn((config = {}) => {
      const docStr = config.doc || '';
      return {
        doc: {
          toString: () => docStr,
          length: docStr.length
        }
      };
    }),
    tabSize: { of: jest.fn() }
  }
}));

jest.mock('@codemirror/view', () => ({
  EditorView: Object.assign(
    jest.fn().mockImplementation((config = {}) => {
      let currentDoc = config.state?.doc?.toString() || '';
      return {
        state: {
          get doc() {
            return {
              toString: () => currentDoc,
              length: currentDoc.length
            };
          },
          update: jest.fn((u) => {
            if (u.changes && typeof u.changes.insert === 'string') {
              currentDoc = u.changes.insert;
            }
            return {};
          }),
        },
        dispatch: jest.fn(),
        destroy: jest.fn(),
        focus: jest.fn(),
        requestMeasure: jest.fn(),
        dom: { addEventListener: jest.fn() }
      };
    }),
    {
      lineWrapping: {},
      theme: jest.fn(),
      updateListener: { of: jest.fn() }
    }
  ),
  keymap: { of: jest.fn() }
}));

jest.mock('@codemirror/language', () => ({
  indentUnit: { of: jest.fn() },
  bracketMatching: jest.fn()
}));

jest.mock('../../../lib/codemirror/elscript.js', () => ({
  elscript: jest.fn(() => []),
  elscriptLanguage: {}
}));

jest.mock('@codemirror/commands', () => ({
  history: jest.fn(),
  historyKeymap: [],
  indentWithTab: {},
  insertNewlineAndIndent: jest.fn()
}));

jest.mock('@fsegurai/codemirror-theme-bundle', () => ({
  vsCodeLight: {}
}));

import { nextTick } from 'vue';
import { mount, flushPromises } from '@vue/test-utils';
import { createTestingPinia } from '@pinia/testing';
import LibraryModal from '../../../components/modals/LibraryModal.vue';
import { useUiStore } from '../../../stores/ui';

const createElSettings = () => ({
  version: '0.0.1',
  ajax_url: 'http://example.com/wp-admin/admin-ajax.php',
  nonce: 'testnonce',
  settings: {
    enable_cache: false,
  },
  user: {
    display_name: 'Test Administrator',
    gravatar: 'https://example.com/avatar.png'
  }
});

jest.mock('../../../lib/api/client.js', () => ({
  handleFetch: jest.fn(() => Promise.resolve({ json: () => Promise.resolve({ success: false }) })),
  handleLocalStorage: jest.fn(() => Promise.resolve(null)),
  handleDownload: jest.fn(),
  handleSelectDirectory: jest.fn(() => Promise.resolve({ success: true, snippets: [] })),
  handleReadLocalSnippets: jest.fn(() => Promise.resolve({ success: true, snippets: [] })),
  handleWriteLocalSnippets: jest.fn(() => Promise.resolve({ success: true })),
  handleOpenFilePicker: jest.fn(() => Promise.resolve({ success: true, content: '[]' })),
  handleGetLocalFsStatus: jest.fn(() => Promise.resolve({ connected: false, hasStoredHandle: false })),
  handleDisconnectLocalFs: jest.fn(() => Promise.resolve({ success: true })),
  handleRequestFsPermission: jest.fn(() => Promise.resolve({ success: true, snippets: [] })),
}));

describe('LibraryModal.vue', () => {
  beforeEach(() => {
    Object.defineProperty(window, 'el_settings', {
      configurable: true,
      writable: true,
      value: createElSettings()
    });
  });

  afterEach(() => {
    delete window.el_settings;
  });

  it('renders correctly when open', () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'library' }
      }
    });

    const wrapper = mount(LibraryModal, {
      global: {
        plugins: [pinia]
      }
    });

    expect(wrapper.find('#library-modal').exists()).toBe(true);
    expect(wrapper.find('h2').text()).toBe('Library');
    expect(wrapper.find('.btn-create').text()).toContain('Add Snippet');
    expect(wrapper.find('.library-fs-text').text()).toBe('Use local file system');
    expect(wrapper.find('.library-import-export-btn').text()).toContain('Import/Export');
  });

  it('does not render when closed', () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: null }
      }
    });

    const wrapper = mount(LibraryModal, {
      global: {
        plugins: [pinia]
      }
    });

    expect(wrapper.find('#library-modal').exists()).toBe(false);
  });

  it('toggles import/export dropdown menu on button click', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'library' }
      }
    });

    const wrapper = mount(LibraryModal, {
      global: {
        plugins: [pinia]
      }
    });

    expect(wrapper.find('.library-dropdown-menu').exists()).toBe(false);

    await wrapper.find('.library-import-export-btn').trigger('click');
    expect(wrapper.find('.library-dropdown-menu').exists()).toBe(true);
    expect(wrapper.text()).toContain('Import JSON');
    expect(wrapper.text()).toContain('Export JSON');

    await wrapper.find('.library-import-export-btn').trigger('click');
    expect(wrapper.find('.library-dropdown-menu').exists()).toBe(false);
  });

  it('calls closeModal on close button click', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'library' }
      }
    });

    const wrapper = mount(LibraryModal, {
      global: {
        plugins: [pinia]
      }
    });

    const uiStore = useUiStore();
    await wrapper.find('.modal-close').trigger('click');
    expect(uiStore.closeModal).toHaveBeenCalled();
  });

  it('triggers handleDownload on export click', async () => {
    const { handleDownload } = require('../../../lib/api/client.js');
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'library' }
      }
    });

    const wrapper = mount(LibraryModal, {
      global: {
        plugins: [pinia]
      }
    });

    await wrapper.find('.library-import-export-btn').trigger('click');
    const exportBtn = wrapper.findAll('.dropdown-item').filter(w => w.text().includes('Export JSON'))[0];
    await exportBtn.trigger('click');

    expect(handleDownload).toHaveBeenCalled();
    const args = handleDownload.mock.calls[0];
    expect(args[1]).toBe('snippets.json');
    expect(args[2]).toBe('application/json');
    const parsed = JSON.parse(args[0]);
    expect(Array.isArray(parsed)).toBe(true);
  });

  it('renders empty state illustration, title, subtitle and pro-tip when no snippets exist', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'library' }
      }
    });

    const wrapper = mount(LibraryModal, {
      global: {
        plugins: [pinia]
      }
    });

    await flushPromises();

    expect(wrapper.find('.library-empty-state').exists()).toBe(true);
    expect(wrapper.find('.codicon-library').exists()).toBe(true);
    expect(wrapper.find('.library-empty-title').text()).toBe('Snippet library');
    expect(wrapper.find('.library-empty-desc').text()).toBe('Save your frequent snippets here to reuse them easily.');
    expect(wrapper.find('.library-empty-tip').text()).toContain('Pro tip:');
    expect(wrapper.find('.library-empty-tip').text()).toContain('select Use local file system to save snippets to your hard drive.');
  });

  it('imports snippets with duplicate resolution and assigns trimmed name as id', async () => {
    const { handleOpenFilePicker } = require('../../../lib/api/client.js');
    handleOpenFilePicker.mockResolvedValueOnce({
      success: true,
      content: JSON.stringify([
        { name: '  My custom snippet  ', code: 'testCode()' },
        { name: 'My custom snippet', code: 'duplicateCode()' },
        { name: 'New Custom', code: 'customCode()' }
      ])
    });

    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'library' }
      }
    });

    const wrapper = mount(LibraryModal, {
      global: {
        plugins: [pinia]
      }
    });

    await wrapper.find('.library-import-export-btn').trigger('click');
    const importBtn = wrapper.findAll('.dropdown-item').filter(w => w.text().includes('Import JSON'))[0];
    await importBtn.trigger('click');
    await flushPromises();

    const uiStore = useUiStore();
    expect(uiStore.showDialog).toHaveBeenCalledWith(expect.objectContaining({
      type: 'success',
      title: 'Import Complete'
    }));

    expect(wrapper.text()).toContain('My custom snippet');
    expect(wrapper.text()).toContain('My custom snippet (1)');
    expect(wrapper.text()).toContain('New Custom');
  });

  it('handles local file system checkbox toggle', async () => {
    const { handleSelectDirectory, handleDisconnectLocalFs } = require('../../../lib/api/client.js');
    handleSelectDirectory.mockResolvedValue({
      success: true,
      snippets: [{ name: 'FS Snippet', code: 'fsCode()' }]
    });

    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'library' }
      }
    });

    const wrapper = mount(LibraryModal, {
      global: {
        plugins: [pinia]
      }
    });

    await flushPromises();

    const checkbox = wrapper.find('.library-fs-checkbox');
    await checkbox.setValue(true);
    await flushPromises();
    expect(handleSelectDirectory).toHaveBeenCalled();
    expect(wrapper.text()).toContain('FS Snippet');

    await checkbox.setValue(false);
    await flushPromises();
    expect(handleDisconnectLocalFs).toHaveBeenCalled();
  });

  it('restores local file system state and snippets automatically if previously enabled', async () => {
    const { handleLocalStorage, handleReadLocalSnippets } = require('../../../lib/api/client.js');
    handleLocalStorage.mockImplementation((method, key) => {
      if (method === 'getItem' && key === 'el_use_local_fs') {
        return Promise.resolve('true');
      }
      return Promise.resolve(null);
    });

    handleReadLocalSnippets.mockResolvedValueOnce({
      success: true,
      snippets: [{ name: 'Restored FS Snippet', code: 'restored()' }]
    });

    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'library' }
      }
    });

    const wrapper = mount(LibraryModal, {
      global: {
        plugins: [pinia]
      }
    });

    await flushPromises();

    const checkbox = wrapper.find('.library-fs-checkbox');
    expect(checkbox.element.checked).toBe(true);
    expect(wrapper.text()).toContain('Restored FS Snippet');
  });

  it('restores localStorage snippets on load and preserves empty list without injecting defaults', async () => {
    const { handleLocalStorage } = require('../../../lib/api/client.js');
    handleLocalStorage.mockImplementation((method, key) => {
      if (method === 'getItem' && key === 'el_snippets') {
        return Promise.resolve('[]');
      }
      return Promise.resolve(null);
    });

    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'library' }
      }
    });

    const wrapper = mount(LibraryModal, {
      global: {
        plugins: [pinia]
      }
    });

    await flushPromises();

    expect(wrapper.find('.library-empty-state').exists()).toBe(true);
    expect(wrapper.text()).not.toContain('My custom snippet');
  });

  it('inserts active snippet and closes the modal automatically', async () => {
    const { handleLocalStorage } = require('../../../lib/api/client.js');
    handleLocalStorage.mockImplementation((method, key) => {
      if (method === 'getItem' && key === 'el_snippets') {
        return Promise.resolve(JSON.stringify([{ id: 'test', name: 'Test Snippet', code: 'testCode()' }]));
      }
      return Promise.resolve(null);
    });

    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'library' }
      },
      stubActions: false
    });

    const wrapper = mount(LibraryModal, {
      global: {
        plugins: [pinia]
      }
    });

    await flushPromises();
    await nextTick();

    const insertBtn = wrapper.find('.btn-insert');
    expect(insertBtn.exists()).toBe(true);

    const uiStore = useUiStore();
    await insertBtn.trigger('click');

    expect(uiStore.snippetToInsert).toBe('testCode()');
    expect(uiStore.activeModal).toBeNull();
  });
});
