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
        setState: jest.fn((newState) => {
          currentDoc = newState?.doc?.toString() || '';
        }),
        dispatch: jest.fn(),
        destroy: jest.fn(),
        focus: jest.fn(),
        requestMeasure: jest.fn(),
        scrollDOM: { scrollTop: 0 },
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

  it('automatically scrolls the snippet list when adding a new snippet', async () => {
    const scrollIntoViewMock = jest.fn();
    window.HTMLElement.prototype.scrollIntoView = scrollIntoViewMock;

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
    await nextTick();

    const createBtn = wrapper.find('.btn-create');
    expect(createBtn.exists()).toBe(true);

    await createBtn.trigger('click');
    await flushPromises();
    await nextTick();

    expect(scrollIntoViewMock).toHaveBeenCalledWith(expect.objectContaining({
      block: 'nearest'
    }));
  });

  it('falls back to scrolling container scrollTop if scrollIntoView is not available', async () => {
    const originalScrollIntoView = window.HTMLElement.prototype.scrollIntoView;
    delete window.HTMLElement.prototype.scrollIntoView;

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
    await nextTick();

    const libraryList = wrapper.find('.library-list').element;
    Object.defineProperty(libraryList, 'scrollHeight', { value: 500, configurable: true });

    const createBtn = wrapper.find('.btn-create');
    await createBtn.trigger('click');
    await flushPromises();
    await nextTick();

    expect(libraryList.scrollTop).toBe(500);

    window.HTMLElement.prototype.scrollIntoView = originalScrollIntoView;
  });

  it('does not scroll to the bottom of the code editor when selecting a snippet', async () => {
    jest.useFakeTimers();
    const { handleLocalStorage } = require('../../../lib/api/client.js');
    handleLocalStorage.mockImplementation((method, key) => {
      if (method === 'getItem' && key === 'el_snippets') {
        return Promise.resolve(JSON.stringify([
          { id: 'snippet-1', name: 'Snippet 1', code: 'line 1\nline 2\nline 3' },
          { id: 'snippet-2', name: 'Snippet 2', code: 'prog[\n  long snippet code here\n]' }
        ]));
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
    await nextTick();
    jest.advanceTimersByTime(150);

    const { EditorView } = require('@codemirror/view');
    const editorInstance = EditorView.mock.results[0]?.value;
    expect(editorInstance).toBeDefined();

    editorInstance.dispatch.mockClear();

    const snippetItems = wrapper.findAll('.library-list-item');
    const snippet2Item = snippetItems.filter(w => w.text().includes('Snippet 2'))[0];
    await snippet2Item.trigger('click');
    await flushPromises();
    await nextTick();
    jest.advanceTimersByTime(150);

    const dispatchCalls = editorInstance.dispatch.mock.calls;
    for (const call of dispatchCalls) {
      const arg = call[0];
      if (arg && typeof arg === 'object') {
        expect(arg.scrollIntoView).toBeFalsy();
        if (arg.selection) {
          expect(arg.selection.anchor).toBe(0);
          expect(arg.selection.head).toBe(0);
        }
      }
    }

    expect(editorInstance.scrollDOM.scrollTop).toBe(0);
    jest.useRealTimers();
  });

  it('prevents saving and displays a warning dialog when renaming to a duplicate snippet name', async () => {
    const { handleLocalStorage } = require('../../../lib/api/client.js');
    handleLocalStorage.mockImplementation((method, key) => {
      if (method === 'getItem' && key === 'el_snippets') {
        return Promise.resolve(JSON.stringify([
          { id: 'First Snippet', name: 'First Snippet', code: 'code1()' },
          { id: 'Second Snippet', name: 'Second Snippet', code: 'code2()' }
        ]));
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
    await nextTick();

    const items = wrapper.findAll('.library-list-item');
    const secondItem = items.filter(w => w.text().includes('Second Snippet'))[0];
    await secondItem.trigger('click');
    await flushPromises();
    await nextTick();

    const titleInput = wrapper.find('.snippet-title-input');
    await titleInput.setValue('First Snippet');

    const uiStore = useUiStore();
    uiStore.showDialog.mockClear();

    const saveBtn = wrapper.find('.library-main-actions button[title="Save"]');
    await saveBtn.trigger('click');
    await flushPromises();

    expect(uiStore.showDialog).toHaveBeenCalledWith(expect.objectContaining({
      type: 'warning',
      title: 'Duplicate Snippet Name',
      message: expect.stringContaining('First Snippet')
    }));

    const setItemCalls = handleLocalStorage.mock.calls.filter(c => c[0] === 'setItem' && c[1] === 'el_snippets');
    if (setItemCalls.length > 0) {
      const lastPayload = JSON.parse(setItemCalls[setItemCalls.length - 1][2]);
      const ids = lastPayload.map(s => s.id);
      expect(new Set(ids).size).toBe(ids.length);
    }
  });

  it('deduplicates duplicate snippet names on load automatically', async () => {
    const { handleLocalStorage } = require('../../../lib/api/client.js');
    handleLocalStorage.mockImplementation((method, key) => {
      if (method === 'getItem' && key === 'el_snippets') {
        return Promise.resolve(JSON.stringify([
          { id: 'New Snippet', name: 'New Snippet', code: '1' },
          { id: 'New Snippet', name: 'New Snippet', code: '2' },
          { id: 'New Snippet', name: 'New Snippet', code: '3' }
        ]));
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
    await nextTick();

    expect(wrapper.text()).toContain('New Snippet');
    expect(wrapper.text()).toContain('New Snippet (1)');
    expect(wrapper.text()).toContain('New Snippet (2)');
  });

  it('places use local file system toggle in modal-footer and import/export in sidebar footer', async () => {
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

    expect(wrapper.find('.modal-footer .library-fs-container').exists()).toBe(true);
    expect(wrapper.find('.library-sidebar-footer .library-fs-container').exists()).toBe(false);
    expect(wrapper.find('.library-sidebar-footer .library-import-export-wrapper').exists()).toBe(true);
    expect(wrapper.find('.modal-footer .modal-footer-actions').exists()).toBe(true);
  });

  it('renders TagInput in library-main-tags with active snippet tags', async () => {
    const { handleLocalStorage } = require('../../../lib/api/client.js');
    handleLocalStorage.mockImplementation((method, key) => {
      if (method === 'getItem' && key === 'el_snippets') {
        return Promise.resolve(JSON.stringify([
          { id: 'Math Snippet', name: 'Math Snippet', code: '1+1', tags: ['math', 'calc'] }
        ]));
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
    await nextTick();

    const mainTags = wrapper.find('.library-main-tags');
    expect(mainTags.exists()).toBe(true);
    expect(mainTags.find('.codicon-tag').exists()).toBe(true);

    const tagChips = mainTags.findAll('.tag-chip');
    expect(tagChips.length).toBe(2);
    expect(tagChips[0].text()).toContain('math');
    expect(tagChips[1].text()).toContain('calc');
  });

  it('filters snippets by tag:[name] and tag:name syntax', async () => {
    const { handleLocalStorage } = require('../../../lib/api/client.js');
    handleLocalStorage.mockImplementation((method, key) => {
      if (method === 'getItem' && key === 'el_snippets') {
        return Promise.resolve(JSON.stringify([
          { id: 'Calc', name: 'Calc', code: '', tags: ['math', 'finance'] },
          { id: 'Array Map', name: 'Array Map', code: '', tags: ['array', 'utils'] },
          { id: 'String Trim', name: 'String Trim', code: '', tags: ['string', 'utils'] }
        ]));
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
    await nextTick();

    const searchInput = wrapper.find('.library-search-bar input');

    // Filter using tag:[math]
    await searchInput.setValue('tag:[math]');
    await nextTick();
    let visibleItems = wrapper.findAll('.library-list .library-list-item');
    expect(visibleItems.length).toBe(1);
    expect(visibleItems[0].text()).toContain('Calc');

    // Filter using tag:utils
    await searchInput.setValue('tag:utils');
    await nextTick();
    visibleItems = wrapper.findAll('.library-list .library-list-item');
    expect(visibleItems.length).toBe(2);
    expect(visibleItems.some(i => i.text().includes('Array Map'))).toBe(true);
    expect(visibleItems.some(i => i.text().includes('String Trim'))).toBe(true);

    // Filter using tag:[utils] Array
    await searchInput.setValue('tag:[utils] Array');
    await nextTick();
    visibleItems = wrapper.findAll('.library-list .library-list-item');
    expect(visibleItems.length).toBe(1);
    expect(visibleItems[0].text()).toContain('Array Map');

    // Filter using nonexistent tag
    await searchInput.setValue('tag:[nonexistent]');
    await nextTick();
    expect(wrapper.find('.no-results').exists()).toBe(true);
  });

  it('marks snippet as unsaved on tag change and saves updated tags to storage', async () => {
    const { handleLocalStorage } = require('../../../lib/api/client.js');
    handleLocalStorage.mockImplementation((method, key) => {
      if (method === 'getItem' && key === 'el_snippets') {
        return Promise.resolve(JSON.stringify([
          { id: 'Snippet 1', name: 'Snippet 1', code: 'code()', tags: ['init'] }
        ]));
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
    await nextTick();

    expect(wrapper.find('.unsaved-dot').exists()).toBe(false);

    // Add a new tag via the TagInput input
    const tagInputField = wrapper.find('.tag-inline-input');
    expect(tagInputField.exists()).toBe(true);

    await tagInputField.setValue('newtag');
    await tagInputField.trigger('keydown', { key: 'Enter' });
    await nextTick();

    // Verify unsaved dot is shown
    expect(wrapper.find('.unsaved-dot').exists()).toBe(true);

    // Click save
    const saveBtn = wrapper.find('.library-main-actions button[title="Save"]');
    await saveBtn.trigger('click');
    await flushPromises();

    // Verify unsaved dot is cleared
    expect(wrapper.find('.unsaved-dot').exists()).toBe(false);

    // Verify persistSnippets wrote the updated tags
    const setItemCalls = handleLocalStorage.mock.calls.filter(c => c[0] === 'setItem' && c[1] === 'el_snippets');
    expect(setItemCalls.length).toBeGreaterThan(0);
    const lastSavedData = JSON.parse(setItemCalls[setItemCalls.length - 1][2]);
    expect(lastSavedData[0].tags).toEqual(['init', 'newtag']);
  });

  it('renders Format button in header actions and triggers format on click', async () => {
    const { handleLocalStorage } = require('../../../lib/api/client.js');
    handleLocalStorage.mockImplementation((method, key) => {
      if (method === 'getItem' && key === 'el_snippets') {
        return Promise.resolve(JSON.stringify([
          { id: '1', name: 'Snippet 1', code: 'prog[set[\'x\', 1], var[\'x\']]' }
        ]));
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

    const formatBtn = wrapper.find('.library-main-actions button[title*="Format"]');
    expect(formatBtn.exists()).toBe(true);
    expect(formatBtn.find('.codicon-wand').exists()).toBe(true);

    await formatBtn.trigger('click');
    await flushPromises();
  });

  it('does not mutate activeSnippet.code on format and clears unsaved state when undoing to original code', async () => {
    const { handleLocalStorage } = require('../../../lib/api/client.js');
    const { EditorView } = require('@codemirror/view');

    const originalCode = 'prog[set[\'x\', 1], var[\'x\']]';
    handleLocalStorage.mockImplementation((method, key) => {
      if (method === 'getItem' && key === 'el_snippets') {
        return Promise.resolve(JSON.stringify([
          { id: '1', name: 'Snippet 1', code: originalCode }
        ]));
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
    await nextTick();

    expect(wrapper.find('.unsaved-dot').exists()).toBe(false);

    // Click format button
    const formatBtn = wrapper.find('.library-main-actions button[title*="Format"]');
    await formatBtn.trigger('click');
    await flushPromises();

    // Retrieve the updateListener registered with CodeMirror
    const listenerCalls = EditorView.updateListener.of.mock.calls;
    const updateListener = listenerCalls[listenerCalls.length - 1][0];

    // Simulate formatting changes dispatched in editor
    const formattedCode = 'prog[\n    set[\'x\', 1],\n    var[\'x\']\n]';
    updateListener({
      docChanged: true,
      state: {
        doc: {
          toString: () => formattedCode
        }
      }
    });
    await nextTick();

    // Snippet should now be marked as unsaved
    expect(wrapper.find('.unsaved-dot').exists()).toBe(true);

    // Simulate undo (Ctrl+Z) in editor back to original code
    updateListener({
      docChanged: true,
      state: {
        doc: {
          toString: () => originalCode
        }
      }
    });
    await nextTick();

    // Snippet should return to clean saved state (no unsaved dot) because baseline code was not corrupted
    expect(wrapper.find('.unsaved-dot').exists()).toBe(false);
  });

  it('resets EditorState with view.setState when switching snippets to isolate undo/redo history', async () => {
    const { handleLocalStorage } = require('../../../lib/api/client.js');
    const { EditorView } = require('@codemirror/view');
    const { EditorState } = require('@codemirror/state');

    handleLocalStorage.mockImplementation((method, key) => {
      if (method === 'getItem' && key === 'el_snippets') {
        return Promise.resolve(JSON.stringify([
          { id: '1', name: 'Snippet 1', code: 'code1()' },
          { id: '2', name: 'Snippet 2', code: 'code2()' }
        ]));
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
    await nextTick();

    const editorInstance = EditorView.mock.results[EditorView.mock.results.length - 1]?.value;
    expect(editorInstance).toBeDefined();
    expect(editorInstance.state.doc.toString()).toBe('code1()');

    // Switch to Snippet 2
    const items = wrapper.findAll('.library-list-item');
    const snippet2Item = items.filter(w => w.text().includes('Snippet 2'))[0];
    await snippet2Item.trigger('click');
    await flushPromises();
    await nextTick();

    // Verify view.setState was called with a newly created EditorState for Snippet 2
    expect(editorInstance.setState).toHaveBeenCalled();
    const lastSetStateCall = editorInstance.setState.mock.calls[editorInstance.setState.mock.calls.length - 1];
    expect(lastSetStateCall[0].doc.toString()).toBe('code2()');
    expect(editorInstance.state.doc.toString()).toBe('code2()');

    // Verify EditorState.create was invoked for code2()
    const stateCreateCalls = EditorState.create.mock.calls;
    const hasCode2Call = stateCreateCalls.some(call => call[0]?.doc === 'code2()');
    expect(hasCode2Call).toBe(true);

    // Switch back to Snippet 1
    const snippet1Item = items.filter(w => w.text().includes('Snippet 1'))[0];
    await snippet1Item.trigger('click');
    await flushPromises();
    await nextTick();

    // Verify view.setState was called with a fresh state for Snippet 1, isolating its history
    const finalSetStateCall = editorInstance.setState.mock.calls[editorInstance.setState.mock.calls.length - 1];
    expect(finalSetStateCall[0].doc.toString()).toBe('code1()');
    expect(editorInstance.state.doc.toString()).toBe('code1()');
  });
});




