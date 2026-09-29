import { defineStore } from 'pinia';

export const MAX_TABS = 12;

let tabCounter = 1;

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
  withBanner = false,
} = {}) => {
  const tabId = id || `tab_${Date.now()}_${tabCounter++}`;
  const isCustomTitle = Boolean(title);
  const defaultTitle = mode === 'scratchpad' ? 'Scratchpad' : 'Console';

  const defaultUser = typeof window !== 'undefined' ? (window.el_settings?.user_id ?? null) : null;
  const defaultSite = typeof window !== 'undefined' && window.el_settings?.multisite
    ? (window.el_settings.site?.sites?.[0]?.id ?? null)
    : null;

  return {
    id: tabId,
    title: title || defaultTitle,
    isCustomTitle,
    mode,
    selectedUser: selectedUser !== undefined ? selectedUser : defaultUser,
    selectedSite: selectedSite !== undefined ? selectedSite : defaultSite,
    isEvaluating: false,
    history: withBanner ? getInitialBanner() : [],
    historyIndex: -1,
    replDraft: '',
    scratchpadCode: scratchpadCode || '',
    scratchpadResult: null,
    scratchpadActiveTab: 'raw',
    scratchpadEditorRatio: 50,
    isScratchpadOutputVisible: false,
    needsLayoutRefresh: false,
  };
};

export const useTabsStore = defineStore('tabs', {
  state: () => {
    const initialTab = createTabInstance({ withBanner: true });
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

      const newTab = createTabInstance({ ...options, withBanner: false });
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
        const cleanTab = createTabInstance({ withBanner: false });
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
        tab.title = tab.mode === 'scratchpad' ? 'Scratchpad' : 'Console';
        tab.isCustomTitle = false;
      } else {
        tab.title = trimmed;
        tab.isCustomTitle = true;
      }
    },

    /**
     * Updates the mode of a specific tab ('repl' or 'scratchpad').
     * If the title was not customized, updates the title to the mode default.
     *
     * @param {string} id
     * @param {'repl'|'scratchpad'} mode
     */
    setTabMode(id, mode) {
      const tab = this.tabs.find((t) => t.id === id);
      if (!tab) return;

      tab.mode = mode;
      if (!tab.isCustomTitle) {
        tab.title = mode === 'scratchpad' ? 'Scratchpad' : 'Console';
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
        withBanner: false,
      });

      clonedTab.scratchpadEditorRatio = source.scratchpadEditorRatio;
      clonedTab.isScratchpadOutputVisible = source.isScratchpadOutputVisible;

      this.tabs.splice(index + 1, 0, clonedTab);
      this.activeTabId = clonedTab.id;
      return clonedTab;
    },
  },
});
