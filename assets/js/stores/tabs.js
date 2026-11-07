import { defineStore } from 'pinia';
import { getStoredDefaultTabMode, useSettingsStore } from './settings.js';
import { useUiStore } from './ui.js';
import { hasAddons, __, sprintf } from '../lib/helpers.js';

export const MAX_TABS = 12;

const isDirtyNotebookTab = (tab) => Boolean(tab && tab.mode === 'notebook' && tab.notebookDirty);

/**
 * Determines whether a tab represents a locked, read-only notebook.
 *
 * @param {object|null} tab
 * @return {boolean}
 */
export const isTabLocked = (tab) => {
  if (!tab || tab.mode !== 'notebook') return false;
  const isPersisted = Boolean(tab.notebookFileHandleId || tab.notebookFileHandle);
  const isReadOnly = Boolean(tab.notebookMeta?.meta?.isReadOnly);
  const isUnlocked = Boolean(tab.notebookUnlockedForEditing);
  const isPersistedReadOnly = tab.notebookPersistedReadOnly !== undefined && tab.notebookPersistedReadOnly !== null
    ? Boolean(tab.notebookPersistedReadOnly)
    : isReadOnly;
  return isPersisted && isReadOnly && !isUnlocked && isPersistedReadOnly;
};

let tabCounter = 1;

/**
 * Resolves the next available incremental untitled notebook title ('Untitled.elab', 'Untitled-2.elab', ...).
 *
 * @param {Array<object>} [existingTabs]
 * @return {string}
 */
export const resolveNextUntitledNotebookTitle = (existingTabs = []) => {
  const existingTitles = new Set(
    (existingTabs || [])
      .filter((t) => t && t.mode === 'notebook' && t.title)
      .map((t) => String(t.title).trim().toLowerCase())
  );

  if (!existingTitles.has('untitled.elab')) {
    return 'Untitled.elab';
  }

  let counter = 2;
  while (existingTitles.has(`untitled-${counter}.elab`)) {
    counter++;
  }
  return `Untitled-${counter}.elab`;
};

/**
 * Resolves the default tab title for a given mode.
 *
 * @param {'repl'|'scratchpad'|'notebook'} mode
 * @param {Array<object>} [existingTabs]
 * @return {string}
 */
export const resolveDefaultTabTitle = (mode, existingTabs = []) => {
  if (mode === 'notebook') return resolveNextUntitledNotebookTitle(existingTabs);
  if (mode === 'scratchpad') return 'Scratchpad';
  return 'Console';
};

/**
 * Resolves initial tab mode based on user settings.
 *
 * @return {'repl'|'scratchpad'|'notebook'}
 */
export const getInitialTabMode = () => {
  try {
    const settingsStore = useSettingsStore();
    if (settingsStore) {
      if (settingsStore.defaultTabMode === 'notebook' && hasAddons()) {
        return 'notebook';
      }
      if (settingsStore.defaultTabMode === 'scratchpad' || settingsStore.defaultTabMode === 'console') {
        return settingsStore.defaultTabMode === 'scratchpad' ? 'scratchpad' : 'repl';
      }
    }
  } catch {
    // Pinia not active or store not yet available
  }
  const mode = getStoredDefaultTabMode();
  if (mode === 'notebook' && hasAddons()) {
    return 'notebook';
  }
  return mode === 'scratchpad' ? 'scratchpad' : 'repl';
};

/**
 * Creates the initial banner entry if provided by WordPress settings.
 *
 * @return {Array}
 */
export const getInitialBanner = () => {
  if (typeof window !== 'undefined' && window.el_settings?.banner) {
    return [
      {
        input: null,
        output: null,
        pending: false,
        type: null,
        mode: null,
        messages: Array.isArray(window.el_settings.banner)
          ? window.el_settings.banner
          : [window.el_settings.banner],
        visualizations: [],
        object_type: null,
      },
    ];
  }
  return [];
};

/**
 * Factory to generate a new tab instance.
 *
 * @param {object} options
 * @return {object}
 */
export const createTabInstance = ({
  id = null,
  title = null,
  mode = 'repl',
  selectedUser = undefined,
  selectedSite = undefined,
  scratchpadCode = '',
  withBanner = undefined,
  notebookDocHandle = null,
  notebookClonedFromDocHandle = null,
  notebookFileHandleId = null,
  notebookFilename = null,
  notebookDirty = false,
  notebookPayload = null,
  notebookOpenPassword = null,
  isEncrypted = false,
  notebookPersistedReadOnly = null,
  existingTabs = [],
} = {}) => {
  const tabId = id || `tab_${Date.now()}_${tabCounter++}`;
  const isCustomTitle = Boolean(title);
  const defaultTitle = resolveDefaultTabTitle(mode, existingTabs);

  const defaultUser = typeof window !== 'undefined' ? (window.el_settings?.user_id ?? null) : null;
  const defaultSite = typeof window !== 'undefined' && window.el_settings?.multisite
    ? (window.el_settings.site?.sites?.[0]?.id ?? null)
    : null;

  const showBanner = withBanner !== undefined ? Boolean(withBanner) : mode === 'repl';

  return {
    id: tabId,
    title: title || defaultTitle,
    isCustomTitle,
    mode,
    selectedUser: selectedUser !== undefined ? selectedUser : defaultUser,
    selectedSite: selectedSite !== undefined ? selectedSite : defaultSite,
    isEvaluating: false,
    history: showBanner ? getInitialBanner() : [],
    historyIndex: -1,
    replDraft: '',
    scratchpadCode: scratchpadCode || '',
    scratchpadResult: null,
    scratchpadActiveTab: 'raw',
    scratchpadEditorRatio: 50,
    isScratchpadOutputVisible: false,
    needsLayoutRefresh: false,
    notebookDocHandle,
    notebookClonedFromDocHandle,
    notebookMeta: null,
    notebookFileHandle: null,
    notebookFileName: notebookFilename,
    notebookFileHandleId,
    notebookFilename,
    notebookDirty: Boolean(notebookDirty),
    notebookPayload,
    isEncrypted: Boolean(isEncrypted || notebookOpenPassword),
    notebookUnlockedForEditing: false,
    notebookPersistedReadOnly:
      notebookPersistedReadOnly !== null && notebookPersistedReadOnly !== undefined
        ? Boolean(notebookPersistedReadOnly)
        : null,
  };
};

/**
 * Checks whether a tab is completely untouched/empty so an opened file can replace it in place.
 *
 * @param {object|null} tab
 * @return {boolean}
 */
export const isTabCleanAndEmpty = (tab) => {
  if (!tab || tab.isCustomTitle || tab.isEvaluating) return false;
  if (tab.mode === 'repl') {
    if (tab.replDraft && tab.replDraft.trim().length > 0) return false;
    const hist = Array.isArray(tab.history) ? tab.history : [];
    return hist.every((entry) => entry && entry.input === null);
  }
  if (tab.mode === 'scratchpad') {
    return (!tab.scratchpadCode || !tab.scratchpadCode.trim()) && !tab.scratchpadResult;
  }
  if (tab.mode === 'notebook') {
    return !tab.notebookDirty && !tab.notebookFileHandle && !tab.notebookFileName && !tab.notebookFileHandleId && !tab.notebookFilename;
  }
  return false;
};

export const useTabsStore = defineStore('tabs', {
  state: () => {
    const initialMode = getInitialTabMode();
    const initialTab = createTabInstance({ mode: initialMode });
    return {
      tabs: [initialTab],
      activeTabId: initialTab.id,
      maxTabs: MAX_TABS,
    };
  },

  getters: {
    activeTab: (state) => {
      return state.tabs.find((tab) => tab.id === state.activeTabId) || state.tabs[0] || null;
    },
    canAddTab: (state) => {
      return state.tabs.length < state.maxTabs;
    },
    tabCount: (state) => {
      return state.tabs.length;
    },
    activeTabIndex: (state) => {
      return state.tabs.findIndex((tab) => tab.id === state.activeTabId);
    },
    isCurrentTabReadOnly: (state) => {
      const tab = Array.isArray(state.tabs)
        ? (state.tabs.find((t) => t.id === state.activeTabId) || state.tabs[0] || null)
        : null;
      return isTabLocked(tab);
    },
  },

  actions: {
    /**
     * Creates and adds a new tab, activating it.
     *
     * @param {object} options
     * @return {object|null} The created tab or null if max limit reached.
     */
    createTab(options = {}) {
      if (this.tabs.length >= this.maxTabs) {
        return null;
      }

      const opts = { ...options };
      if (!opts.mode) {
        opts.mode = getInitialTabMode();
      }
      if (!opts.existingTabs) {
        opts.existingTabs = this.tabs;
      }

      const newTab = createTabInstance(opts);
      this.tabs.push(newTab);
      this.activeTabId = newTab.id;
      return newTab;
    },

    /**
     * Closes a tab by ID.
     * If the closed tab was active, activates the adjacent tab.
     * If it is the last tab remaining, resets it with a clean tab.
     *
     * @param {string} id
     * @return {boolean}
     */
    closeTab(id) {
      const index = this.tabs.findIndex((tab) => tab.id === id);
      if (index === -1) return false;

      if (this.tabs.length === 1) {
        // If closing the sole tab, reset it to a clean slate
        const cleanTab = createTabInstance({ mode: getInitialTabMode() });
        this.tabs = [cleanTab];
        this.activeTabId = cleanTab.id;
        return true;
      }

      const isCurrentActive = this.activeTabId === id;

      if (isCurrentActive) {
        const nextActiveIndex = index === this.tabs.length - 1 ? index - 1 : index + 1;
        this.activeTabId = this.tabs[nextActiveIndex].id;
      }

      this.tabs.splice(index, 1);
      return true;
    },

    /**
     * Closes all tabs, resetting to a single fresh tab.
     *
     * @return {boolean}
     */
    closeAllTabs() {
      const cleanTab = createTabInstance({ mode: getInitialTabMode() });
      this.tabs = [cleanTab];
      this.activeTabId = cleanTab.id;
      return true;
    },

    /**
     * Closes all tabs except the specified tab.
     * Activates the preserved tab.
     *
     * @param {string} [id] Target tab ID. Defaults to active tab.
     * @return {boolean}
     */
    closeOtherTabs(id = null) {
      const targetId = id || this.activeTabId;
      const targetTab = this.tabs.find((tab) => tab.id === targetId);
      if (!targetTab) return false;

      this.tabs = [targetTab];
      this.activeTabId = targetTab.id;
      return true;
    },

    /**
     * Closes all tabs situated to the right of the specified tab.
     *
     * @param {string} [id] Target tab ID. Defaults to active tab.
     * @return {boolean}
     */
    closeTabsToTheRight(id = null) {
      const targetId = id || this.activeTabId;
      const index = this.tabs.findIndex((tab) => tab.id === targetId);
      if (index === -1) return false;
      if (index >= this.tabs.length - 1) return true;

      const targetTab = this.tabs[index];
      const activeIndex = this.activeTabIndex;
      if (activeIndex > index) {
        this.activeTabId = targetTab.id;
      }

      this.tabs = this.tabs.slice(0, index + 1);
      return true;
    },

    /**
     * Prompts the user with a confirmation modal when attempting to close one or more
     * dirty notebook tabs. (Professional Edition only)
     *
     * @param {Array<object>} dirtyTabs
     * @return {Promise<boolean>}
     */
    async confirmCloseDirtyNotebookTabs(dirtyTabs) {
      if (!Array.isArray(dirtyTabs) || dirtyTabs.length === 0) {
        return true;
      }

      let uiStore = null;
      try {
        uiStore = useUiStore();
      } catch {
        uiStore = null;
      }

      if (!uiStore || typeof uiStore.showDialog !== 'function') {
        return true;
      }

      const isMultiple = dirtyTabs.length > 1;
      const title = __('Unsaved Changes');
      const message = isMultiple
        ? sprintf(
            __('%d notebooks have unsaved changes. Discard changes and close all?'),
            dirtyTabs.length
          )
        : sprintf(
            __('"%s" has unsaved changes. Discard changes and close?'),
            dirtyTabs[0]?.title || __('Notebook')
          );

      return await uiStore.showDialog({
        type: 'confirm',
        title,
        message,
        confirmText: __('Discard'),
        cancelText: __('Cancel'),
      });
    },

    /**
     * Requests closing a tab by ID, prompting for confirmation if it is an unsaved notebook.
     *
     * @param {string} id
     * @return {Promise<boolean>}
     */
    requestCloseTab(id) {
      const targetTab = this.tabs.find((tab) => tab.id === id);
      if (!targetTab) return Promise.resolve(false);

      if (!isDirtyNotebookTab(targetTab)) {
        return Promise.resolve(this.closeTab(id));
      }

      return this.confirmCloseDirtyNotebookTabs([targetTab]).then((confirmed) => {
        if (!confirmed) return false;
        return this.closeTab(id);
      });
    },

    /**
     * Requests closing all tabs, prompting for confirmation if any notebook tab has unsaved changes.
     *
     * @return {Promise<boolean>}
     */
    requestCloseAllTabs() {
      const dirtyTabs = this.tabs.filter(isDirtyNotebookTab);
      if (dirtyTabs.length === 0) {
        return Promise.resolve(this.closeAllTabs());
      }

      return this.confirmCloseDirtyNotebookTabs(dirtyTabs).then((confirmed) => {
        if (!confirmed) return false;
        return this.closeAllTabs();
      });
    },

    /**
     * Requests closing all tabs except the specified tab, prompting if any target notebook tab is dirty.
     *
     * @param {string} [id]
     * @return {Promise<boolean>}
     */
    requestCloseOtherTabs(id = null) {
      const targetId = id || this.activeTabId;
      const targetTab = this.tabs.find((tab) => tab.id === targetId);
      if (!targetTab) return Promise.resolve(false);

      const dirtyTabs = this.tabs.filter((tab) => tab.id !== targetId && isDirtyNotebookTab(tab));
      if (dirtyTabs.length === 0) {
        return Promise.resolve(this.closeOtherTabs(targetId));
      }

      return this.confirmCloseDirtyNotebookTabs(dirtyTabs).then((confirmed) => {
        if (!confirmed) return false;
        return this.closeOtherTabs(targetId);
      });
    },

    /**
     * Requests closing all tabs to the right of the specified tab, prompting if any target notebook tab is dirty.
     *
     * @param {string} [id]
     * @return {Promise<boolean>}
     */
    requestCloseTabsToTheRight(id = null) {
      const targetId = id || this.activeTabId;
      const index = this.tabs.findIndex((tab) => tab.id === targetId);
      if (index === -1) return Promise.resolve(false);
      if (index >= this.tabs.length - 1) return Promise.resolve(true);

      const dirtyTabs = this.tabs.slice(index + 1).filter(isDirtyNotebookTab);
      if (dirtyTabs.length === 0) {
        return Promise.resolve(this.closeTabsToTheRight(targetId));
      }

      return this.confirmCloseDirtyNotebookTabs(dirtyTabs).then((confirmed) => {
        if (!confirmed) return false;
        return this.closeTabsToTheRight(targetId);
      });
    },

    /**
     * Sets the active tab by ID.
     *
     * @param {string} id
     */
    setActiveTab(id) {
      const exists = this.tabs.some((tab) => tab.id === id);
      if (exists) {
        this.activeTabId = id;
      }
    },

    /**
     * Reorders tabs by moving an item from fromIndex to toIndex.
     *
     * @param {number} fromIndex
     * @param {number} toIndex
     */
    reorderTabs(fromIndex, toIndex) {
      if (
        fromIndex < 0 ||
        fromIndex >= this.tabs.length ||
        toIndex < 0 ||
        toIndex >= this.tabs.length ||
        fromIndex === toIndex
      ) {
        return;
      }

      const [movedTab] = this.tabs.splice(fromIndex, 1);
      this.tabs.splice(toIndex, 0, movedTab);
    },

    /**
     * Updates the title of a specific tab.
     *
     * @param {string} id
     * @param {string} newTitle
     */
    setTabTitle(id, newTitle) {
      const tab = this.tabs.find((t) => t.id === id);
      if (!tab) return;

      const trimmed = (newTitle || '').trim();
      if (!trimmed) {
        tab.title = resolveDefaultTabTitle(
          tab.mode,
          this.tabs.filter((t) => t.id !== id)
        );
        tab.isCustomTitle = false;
      } else {
        const formattedTitle =
          tab.mode === 'notebook'
            ? (trimmed.toLowerCase().endsWith('.elab') ? trimmed : `${trimmed}.elab`)
            : trimmed;
        tab.title = formattedTitle;
        tab.isCustomTitle = true;
      }
    },

    /**
     * Updates the mode of a specific tab ('repl', 'scratchpad', or 'notebook').
     * If the title was not customized, updates the title to the mode default.
     *
     * @param {string} id
     * @param {'repl'|'scratchpad'|'notebook'} mode
     */
    setTabMode(id, mode) {
      const tab = this.tabs.find((t) => t.id === id);
      if (!tab) return;

      tab.mode = mode;
      if (mode === 'repl' && (!Array.isArray(tab.history) || tab.history.length === 0)) {
        tab.history = getInitialBanner();
      }
      if (!tab.isCustomTitle) {
        tab.title = resolveDefaultTabTitle(
          mode,
          this.tabs.filter((t) => t.id !== id)
        );
      }
    },

    /**
     * Updates the evaluation status of a specific tab.
     *
     * @param {string} id
     * @param {boolean} isEvaluating
     */
    setTabEvaluating(id, isEvaluating) {
      const tab = this.tabs.find((t) => t.id === id);
      if (tab) {
        tab.isEvaluating = Boolean(isEvaluating);
      }
    },

    /**
     * Updates the execution context (user and/or site) of a specific tab.
     *
     * @param {string} id
     * @param {object} context
     */
    updateTabContext(id, { user_id, site_id } = {}) {
      const tab = this.tabs.find((t) => t.id === id);
      if (!tab) return;

      if (user_id !== undefined) tab.selectedUser = user_id;
      if (site_id !== undefined) tab.selectedSite = site_id;
    },

    /**
     * Updates scratchpad-related properties for a specific tab.
     *
     * @param {string} id
     * @param {object} patch
     */
    updateTabScratchpad(id, patch = {}) {
      const tab = this.tabs.find((t) => t.id === id);
      if (!tab) return;

      if (patch.scratchpadCode !== undefined) tab.scratchpadCode = patch.scratchpadCode;
      if (patch.scratchpadResult !== undefined) tab.scratchpadResult = patch.scratchpadResult;
      if (patch.scratchpadActiveTab !== undefined) tab.scratchpadActiveTab = patch.scratchpadActiveTab;
      if (patch.scratchpadEditorRatio !== undefined) tab.scratchpadEditorRatio = patch.scratchpadEditorRatio;
      if (patch.isScratchpadOutputVisible !== undefined) tab.isScratchpadOutputVisible = patch.isScratchpadOutputVisible;
      if (patch.needsLayoutRefresh !== undefined) tab.needsLayoutRefresh = patch.needsLayoutRefresh;
    },

    /**
     * Checks whether the given tab (or active tab) is completely clean and empty.
     *
     * @param {object|null} [tab]
     * @return {boolean}
     */
    isTabCleanAndEmpty(tab = null) {
      return isTabCleanAndEmpty(tab || this.activeTab);
    },

    /**
     * Updates notebook-related properties for a specific tab.
     *
     * @param {string} id
     * @param {object} patch
     */
    updateTabNotebook(id, patch = {}) {
      const tab = this.tabs.find((t) => t.id === id);
      if (!tab) return;

      if (patch.notebookDocHandle !== undefined) tab.notebookDocHandle = patch.notebookDocHandle;
      if (patch.notebookMeta !== undefined) tab.notebookMeta = patch.notebookMeta;
      if (patch.notebookFileHandle !== undefined) tab.notebookFileHandle = patch.notebookFileHandle;
      if (patch.notebookFileName !== undefined) {
        tab.notebookFileName = patch.notebookFileName;
        tab.notebookFilename = patch.notebookFileName;
      }
      if (patch.notebookFileHandleId !== undefined) tab.notebookFileHandleId = patch.notebookFileHandleId;
      if (patch.notebookFilename !== undefined) {
        tab.notebookFilename = patch.notebookFilename;
        tab.notebookFileName = patch.notebookFilename;
      }
      if (patch.notebookDirty !== undefined) tab.notebookDirty = Boolean(patch.notebookDirty);
      if (patch.notebookPayload !== undefined) tab.notebookPayload = patch.notebookPayload;
      if (patch.canUndoCell !== undefined) tab.canUndoCell = Boolean(patch.canUndoCell);
      if (patch.canRedoCell !== undefined) tab.canRedoCell = Boolean(patch.canRedoCell);
      if (patch.notebookClonedFromDocHandle !== undefined) {
        tab.notebookClonedFromDocHandle = patch.notebookClonedFromDocHandle;
      }
      if (patch.notebookOpenPassword !== undefined) {
        tab.isEncrypted = Boolean(patch.notebookOpenPassword);
      }
      if (patch.isEncrypted !== undefined) {
        tab.isEncrypted = Boolean(patch.isEncrypted);
      }
      if (patch.notebookUnlockedForEditing !== undefined) tab.notebookUnlockedForEditing = Boolean(patch.notebookUnlockedForEditing);
      if (patch.notebookPersistedReadOnly !== undefined) tab.notebookPersistedReadOnly = Boolean(patch.notebookPersistedReadOnly);
    },

    /**
     * Updates REPL-related properties for a specific tab.
     *
     * @param {string} id
     * @param {object} patch
     */
    updateTabRepl(id, patch = {}) {
      const tab = this.tabs.find((t) => t.id === id);
      if (!tab) return;

      if (patch.history !== undefined) tab.history = patch.history;
      if (patch.historyIndex !== undefined) tab.historyIndex = patch.historyIndex;
      if (patch.replDraft !== undefined) tab.replDraft = patch.replDraft;
    },

    /**
     * Appends a new REPL entry to the tab's history.
     *
     * @param {string} id
     * @param {object} entry
     * @return {number} Index of the added entry
     */
    addReplEntry(id, entry) {
      const tab = this.tabs.find((t) => t.id === id);
      if (!tab) return -1;
      if (!tab.history) tab.history = [];
      tab.history.push(entry);
      tab.historyIndex = tab.history.length;
      return tab.history.length - 1;
    },

    /**
     * Updates an existing REPL entry in the tab's history.
     *
     * @param {string} id
     * @param {number} index
     * @param {object} patch
     */
    updateReplEntry(id, index, patch = {}) {
      const tab = this.tabs.find((t) => t.id === id);
      if (!tab || !tab.history || !tab.history[index]) return;
      Object.assign(tab.history[index], patch);
    },

    /**
     * Duplicates an existing tab, placing the clone immediately after it.
     *
     * @param {string} id
     * @return {object|null} The cloned tab or null if max limit reached.
     */
    duplicateTab(id) {
      if (this.tabs.length >= this.maxTabs) return null;

      const index = this.tabs.findIndex((t) => t.id === id);
      if (index === -1) return null;

      const source = this.tabs[index];
      const clonedTab = createTabInstance({
        title: source.isCustomTitle ? `${source.title} (Copy)` : null,
        mode: source.mode,
        selectedUser: source.selectedUser,
        selectedSite: source.selectedSite,
        scratchpadCode: source.scratchpadCode,
        isEncrypted: source.isEncrypted,
        notebookClonedFromDocHandle: source.notebookDocHandle || null,
        notebookPersistedReadOnly: source.notebookPersistedReadOnly,
        existingTabs: this.tabs,
      });

      clonedTab.scratchpadEditorRatio = source.scratchpadEditorRatio;
      clonedTab.isScratchpadOutputVisible = source.isScratchpadOutputVisible;

      this.tabs.splice(index + 1, 0, clonedTab);
      this.activeTabId = clonedTab.id;
      return clonedTab;
    },

    /**
     * Alias for duplicateTab.
     *
     * @param {string} id
     * @return {object|null}
     */
    cloneTab(id) {
      return this.duplicateTab(id);
    },
  },
});
