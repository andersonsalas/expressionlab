import { defineStore } from 'pinia';

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
    isLocked: window.el_settings.settings.enable_sandbox || window.el_settings.settings.debug_mode || false,
  }),

  actions: {
    triggerSnippetInsert(snippet) {
      this.snippetToInsert = snippet;
    },
    triggerLibrarySnippetInsert(snippet) {
      this.librarySnippetToInsert = snippet;
    },
    openModal(modalName, modalData = null) {
      this.activeModal = modalName;
      this.activeModalData = modalData;
    },
    closeModal() {
      this.activeModal = null;
      this.activeModalData = null;
    },
    showDialog(options) {
      return new Promise((resolve) => {
        this.activeDialog = 'dialog';
        this.activeDialogData = {
          ...options,
          onConfirm: () => {
            this.activeDialog = null;
            this.activeDialogData = null;
            resolve(options.denyText ? 'confirm' : true);
          },
          onDeny: () => {
            this.activeDialog = null;
            this.activeDialogData = null;
            resolve('deny');
          },
          onCancel: () => {
            this.activeDialog = null;
            this.activeDialogData = null;
            resolve(options.denyText ? 'cancel' : false);
          }
        };
      });
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
