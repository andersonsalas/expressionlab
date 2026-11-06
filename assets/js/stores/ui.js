import { defineStore } from 'pinia';
import { useTabsStore } from './tabs';

export const useUiStore = defineStore('ui', {
  state: () => ({
    consoleMode: 'evaluate',
    currentChallenge: null,
    privateKey: null,
    isRequestingChallenge: false,
    challengeFetchedAt: 0,
    activeModal: null, // 'import', 'settings', etc., or null
    activeModalData: null, // optional data passed to the active modal
    activeDialog: null, // separate layer for dialog modals (confirm, info, warning, error, success)
    activeDialogData: null,
    snippetToInsert: null,
    librarySnippetToInsert: null,
    isLocked: window.el_settings?.settings?.enable_sandbox || window.el_settings?.settings?.debug_mode || false,
    activeDropdown: null,
  }),

  actions: {
    openDropdown(dropdownId) {
      this.activeDropdown = dropdownId;
    },
    closeDropdown(dropdownId = null) {
      if (!dropdownId || this.activeDropdown === dropdownId) {
        this.activeDropdown = null;
      }
    },
    closeDropdowns() {
      this.activeDropdown = null;
    },
    toggleDropdown(dropdownId) {
      if (this.activeDropdown === dropdownId) {
        this.activeDropdown = null;
      } else {
        this.activeDropdown = dropdownId;
      }
    },
    triggerSnippetInsert(snippet) {
      if (snippet !== null && snippet !== undefined) {
        try {
          const tabsStore = useTabsStore();
          if (tabsStore?.isCurrentTabReadOnly) {
            return false;
          }
        } catch {
          // Proceed if Pinia context is unavailable
        }
      }
      this.snippetToInsert = snippet;
      return true;
    },
    triggerLibrarySnippetInsert(snippet) {
      if (snippet !== null && snippet !== undefined) {
        try {
          const tabsStore = useTabsStore();
          if (tabsStore?.isCurrentTabReadOnly) {
            return false;
          }
        } catch {
          // Proceed if Pinia context is unavailable
        }
      }
      this.librarySnippetToInsert = snippet;
      return true;
    },
    openModal(modalName, modalData = null) {
      this.closeDropdowns();
      this.activeModal = modalName;
      this.activeModalData = modalData;
    },
    closeModal() {
      this.activeModal = null;
      this.activeModalData = null;
    },
    showDialog(options) {
      this.closeDropdowns();
      const hasDeny = Boolean(options?.denyText || options?.denyLabel);
      return new Promise((resolve) => {
        this.activeDialog = 'dialog';
        this.activeDialogData = {
          ...options,
          onConfirm: (payload) => {
            this.activeDialog = null;
            this.activeDialogData = null;
            if (options.type === 'prompt') {
              resolve(payload !== undefined ? payload : '');
            } else {
              resolve(hasDeny ? 'confirm' : true);
            }
          },
          onDeny: () => {
            this.activeDialog = null;
            this.activeDialogData = null;
            resolve('deny');
          },
          onCancel: () => {
            this.activeDialog = null;
            this.activeDialogData = null;
            if (options.type === 'prompt') {
              resolve(null);
            } else {
              resolve(hasDeny ? 'cancel' : false);
            }
          }
        };
      });
    },
    showBusyDialog(options = {}) {
      this.closeDropdowns();
      this.activeDialog = 'dialog';
      this.activeDialogData = {
        icon: 'codicon-clockface',
        ...options,
        type: 'busy',
        closable: false,
      };
    },
    closeDialog() {
      this.activeDialog = null;
      this.activeDialogData = null;
    },
    setConsoleMode(mode) {
      this.consoleMode = mode;
    },
    setChallenge(challenge) {
      this.currentChallenge = challenge;
      this.challengeFetchedAt = Date.now();
    },
    setPrivateKey(key) {
      this.privateKey = key;
    },
    setRequestingChallenge(state) {
      this.isRequestingChallenge = state;
    },
    lock() {
      this.privateKey = null;
      this.isLocked = true;
    },
    unlock(seed) {
      this.privateKey = seed;
      this.isLocked = false;
    },
  },
});
