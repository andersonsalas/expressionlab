import { setActivePinia, createPinia } from 'pinia';
import { useUiStore } from '../../stores/ui.js';

describe('stores/ui.js - Lock/Unlock Security State', () => {
  beforeEach(() => {
    window.el_settings = {
      settings: {
        enable_sandbox: true,
        debug_mode: false,
      },
    };
    setActivePinia(createPinia());
  });

  afterEach(() => {
    delete window.el_settings;
  });

  it('isLocked is true when enable_sandbox is true', () => {
    window.el_settings.settings.enable_sandbox = true;
    window.el_settings.settings.debug_mode = false;
    setActivePinia(createPinia());

    const store = useUiStore();
    expect(store.isLocked).toBe(true);
  });

  it('isLocked is true when debug_mode is true', () => {
    window.el_settings.settings.enable_sandbox = false;
    window.el_settings.settings.debug_mode = true;
    setActivePinia(createPinia());

    const store = useUiStore();
    expect(store.isLocked).toBe(true);
  });

  it('isLocked is true when both enable_sandbox and debug_mode are true', () => {
    window.el_settings.settings.enable_sandbox = true;
    window.el_settings.settings.debug_mode = true;
    setActivePinia(createPinia());

    const store = useUiStore();
    expect(store.isLocked).toBe(true);
  });

  it('isLocked is false when both enable_sandbox and debug_mode are false', () => {
    window.el_settings.settings.enable_sandbox = false;
    window.el_settings.settings.debug_mode = false;
    setActivePinia(createPinia());

    const store = useUiStore();
    expect(store.isLocked).toBe(false);
  });

  it('privateKey is null initially', () => {
    const store = useUiStore();
    expect(store.privateKey).toBeNull();
  });

  it('unlock() sets isLocked to false and stores the private key', () => {
    const store = useUiStore();
    const mockSeed = new Uint8Array(32).fill(42);

    expect(store.isLocked).toBe(true);
    store.unlock(mockSeed);

    expect(store.isLocked).toBe(false);
    expect(store.privateKey).toBe(mockSeed);
  });

  it('lock() sets isLocked to true and clears privateKey', () => {
    const store = useUiStore();
    const mockSeed = new Uint8Array(32).fill(42);

    store.unlock(mockSeed);
    expect(store.isLocked).toBe(false);
    expect(store.privateKey).toBe(mockSeed);

    store.lock();

    expect(store.isLocked).toBe(true);
    expect(store.privateKey).toBeNull();
  });

  it('setPrivateKey() sets the private key without changing isLocked', () => {
    const store = useUiStore();
    const mockSeed = new Uint8Array(32).fill(99);

    expect(store.isLocked).toBe(true);
    store.setPrivateKey(mockSeed);

    expect(store.privateKey).toBe(mockSeed);
    expect(store.isLocked).toBe(true);
  });

  it('setPrivateKey(null) clears the private key without changing isLocked', () => {
    const store = useUiStore();
    const mockSeed = new Uint8Array(32).fill(99);

    store.unlock(mockSeed);
    expect(store.isLocked).toBe(false);

    store.setPrivateKey(null);

    expect(store.privateKey).toBeNull();
    expect(store.isLocked).toBe(false);
  });

  it('setChallenge() stores the challenge and records fetch time', () => {
    const store = useUiStore();
    const challenge = { nonce: 'abc123', timestamp: Date.now() };
    const before = Date.now();

    store.setChallenge(challenge);

    expect(store.currentChallenge).toStrictEqual(challenge);
    expect(store.challengeFetchedAt).toBeGreaterThanOrEqual(before);
  });

  it('setRequestingChallenge() toggles isRequestingChallenge', () => {
    const store = useUiStore();

    expect(store.isRequestingChallenge).toBe(false);
    store.setRequestingChallenge(true);
    expect(store.isRequestingChallenge).toBe(true);
    store.setRequestingChallenge(false);
    expect(store.isRequestingChallenge).toBe(false);
  });

  it('manages activeDropdown correctly with open, close, closeDropdowns, and toggle', () => {
    const store = useUiStore();
    expect(store.activeDropdown).toBeNull();

    store.openDropdown('file-menu');
    expect(store.activeDropdown).toBe('file-menu');

    // Opening another replaces the previous one (only one dropdown active at a time)
    store.openDropdown('tools-menu');
    expect(store.activeDropdown).toBe('tools-menu');

    // Toggle same closes it
    store.toggleDropdown('tools-menu');
    expect(store.activeDropdown).toBeNull();

    // Toggle different opens it
    store.toggleDropdown('file-menu');
    expect(store.activeDropdown).toBe('file-menu');

    // Toggle to another replaces it
    store.toggleDropdown('tab-context-menu');
    expect(store.activeDropdown).toBe('tab-context-menu');

    // closeDropdown with matching id closes
    store.closeDropdown('tab-context-menu');
    expect(store.activeDropdown).toBeNull();

    // closeDropdown with non-matching id does not close
    store.openDropdown('tools-menu');
    store.closeDropdown('other-id');
    expect(store.activeDropdown).toBe('tools-menu');

    // closeDropdowns clears any active dropdown
    store.closeDropdowns();
    expect(store.activeDropdown).toBeNull();
  });

  it('closes activeDropdown when opening a modal or showing a dialog', () => {
    const store = useUiStore();
    store.openDropdown('file-menu');
    expect(store.activeDropdown).toBe('file-menu');

    store.openModal('settings');
    expect(store.activeDropdown).toBeNull();

    store.openDropdown('tools-menu');
    expect(store.activeDropdown).toBe('tools-menu');

    store.showDialog({ title: 'Test' });
    expect(store.activeDropdown).toBeNull();
  });

  it('showDialog prompt resolves entered value on confirm and null on cancel', async () => {
    const store = useUiStore();
    const promptPromise = store.showDialog({
      type: 'prompt',
      inputType: 'password',
      title: 'Encrypted Notebook',
    });

    expect(store.activeDialog).toBe('dialog');
    expect(store.activeDialogData.type).toBe('prompt');

    store.activeDialogData.onConfirm('mypassword');
    const result = await promptPromise;
    expect(result).toBe('mypassword');
    expect(store.activeDialog).toBeNull();

    const cancelPromise = store.showDialog({
      type: 'prompt',
      inputType: 'password',
      title: 'Encrypted Notebook',
    });
    store.activeDialogData.onCancel();
    const cancelResult = await cancelPromise;
    expect(cancelResult).toBeNull();
  });

  it('showDialog alert resolves true on confirm and resets activeDialog', async () => {
    const store = useUiStore();
    const alertPromise = store.showDialog({
      type: 'alert',
      title: 'Decryption Failed',
    });

    expect(store.activeDialog).toBe('dialog');
    store.activeDialogData.onConfirm();
    const result = await alertPromise;
    expect(result).toBe(true);
    expect(store.activeDialog).toBeNull();
  });

  it('centrally blocks triggerSnippetInsert and triggerLibrarySnippetInsert when active tab is read-only', () => {
    const store = useUiStore();
    const { useTabsStore } = require('../../stores/tabs');
    const tabsStore = useTabsStore();

    const nbTab = tabsStore.createTab({
      mode: 'notebook',
      notebookFileHandleId: 'locked-handle',
      notebookPersistedReadOnly: true,
    });
    tabsStore.updateTabNotebook(nbTab.id, {
      notebookMeta: { meta: { isReadOnly: true } },
      notebookUnlockedForEditing: false,
    });

    expect(tabsStore.isCurrentTabReadOnly).toBe(true);
    expect(store.triggerSnippetInsert({ code: '1 + 1', mode: 'insert' })).toBe(false);
    expect(store.snippetToInsert).toBeNull();

    expect(store.triggerLibrarySnippetInsert({ code: '2 + 2' })).toBe(false);
    expect(store.librarySnippetToInsert).toBeNull();

    // Unlocking the tab allows snippet insertion again
    tabsStore.updateTabNotebook(nbTab.id, { notebookUnlockedForEditing: true });
    expect(store.triggerSnippetInsert({ code: '1 + 1', mode: 'insert' })).toBe(true);
    expect(store.snippetToInsert).toEqual({ code: '1 + 1', mode: 'insert' });
  });

  it('showBusyDialog opens a non-closable busy dialog and closeDialog resets it', () => {
    const store = useUiStore();
    store.openDropdown('file-menu');

    store.showBusyDialog({
      title: 'Encrypted Notebook',
      message: 'Unlocking and opening notebook, please wait...',
    });

    expect(store.activeDropdown).toBeNull();
    expect(store.activeDialog).toBe('dialog');
    expect(store.activeDialogData).toMatchObject({
      type: 'busy',
      closable: false,
      icon: 'codicon-clockface',
      title: 'Encrypted Notebook',
      message: 'Unlocking and opening notebook, please wait...',
    });

    store.closeDialog();
    expect(store.activeDialog).toBeNull();
    expect(store.activeDialogData).toBeNull();
  });

  it('DialogModal renders non-dismissible busy dialog without footer and ignores Escape/Enter keys', async () => {
    const { mount } = require('@vue/test-utils');
    const DialogModal = require('../../components/modals/DialogModal.vue').default;
    const store = useUiStore();

    const wrapper = mount(DialogModal, {
      global: {
        stubs: {
          Teleport: true,
        },
      },
    });

    store.showBusyDialog({
      title: 'Encrypted Notebook',
      message: 'Unlocking and opening notebook, please wait...',
    });
    await wrapper.vm.$nextTick();

    const overlay = wrapper.find('#dialog-modal');
    expect(overlay.exists()).toBe(true);
    expect(overlay.classes()).toContain('is-busy');
    expect(overlay.attributes('aria-busy')).toBe('true');
    expect(wrapper.find('.modal-header h2').text()).toBe('Encrypted Notebook');
    expect(wrapper.find('.dialog-message').text()).toBe('Unlocking and opening notebook, please wait...');
    expect(wrapper.find('.codicon-clockface').exists()).toBe(true);
    expect(wrapper.find('.modal-footer').exists()).toBe(false);

    // Escape and Enter must not dismiss the busy dialog
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter' }));
    await wrapper.vm.$nextTick();
    expect(store.activeDialog).toBe('dialog');

    // Transitioning in-place to an alert restores the footer without closing the overlay
    store.showDialog({
      type: 'alert',
      title: 'Decryption Failed',
      message: 'Incorrect password or corrupted file. Please try again.',
    });
    await wrapper.vm.$nextTick();
    expect(wrapper.find('#dialog-modal').classes()).not.toContain('is-busy');
    expect(wrapper.find('.modal-footer').exists()).toBe(true);

    wrapper.unmount();
  });
});
