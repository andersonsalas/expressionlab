import { setActivePinia, createPinia } from 'pinia';
import { useTabsStore, MAX_TABS } from '../../stores/tabs.js';
import { useSettingsStore } from '../../stores/settings.js';

describe('stores/tabs.js - Tabs Management Store', () => {
  beforeEach(() => {
    window.el_settings = {
      user_id: 1,
      multisite: true,
      site: {
        sites: [{ id: 10, name: 'Main Site' }, { id: 20, name: 'Sub Site' }],
      },
      banner: 'Welcome to Expression Lab',
    };
    setActivePinia(createPinia());
  });

  afterEach(() => {
    delete window.el_settings;
  });

  describe('Initial State', () => {
    it('initializes with a single default tab with banner', () => {
      const store = useTabsStore();

      expect(store.tabCount).toBe(1);
      expect(store.tabs.length).toBe(1);
      expect(store.activeTab).not.toBeNull();
      expect(store.activeTab.id).toBe(store.activeTabId);
      expect(store.activeTab.title).toBe('Console');
      expect(store.activeTab.mode).toBe('repl');
      expect(store.activeTab.selectedUser).toBe(1);
      expect(store.activeTab.selectedSite).toBe(10);
      expect(store.activeTab.history.length).toBe(1);
      expect(store.activeTab.history[0].messages).toEqual(['Welcome to Expression Lab']);
      expect(store.canAddTab).toBe(true);
      expect(store.activeTabIndex).toBe(0);
    });
  });

  describe('createTab()', () => {
    it('creates and activates a new tab in repl mode by default with banner', () => {
      const store = useTabsStore();
      const newTab = store.createTab();

      expect(store.tabCount).toBe(2);
      expect(newTab).not.toBeNull();
      expect(newTab.title).toBe('Console');
      expect(newTab.mode).toBe('repl');
      expect(store.activeTabId).toBe(newTab.id);
      expect(store.activeTab.id).toBe(newTab.id);
      expect(newTab.history.length).toBe(1);
      expect(newTab.history[0].messages).toEqual(['Welcome to Expression Lab']);
    });

    it('creates a tab in scratchpad mode with default title Scratchpad without banner', () => {
      const store = useTabsStore();
      const newTab = store.createTab({ mode: 'scratchpad' });

      expect(newTab.title).toBe('Scratchpad');
      expect(newTab.mode).toBe('scratchpad');
      expect(store.activeTabId).toBe(newTab.id);
      expect(newTab.history.length).toBe(0);
    });

    it('allows creating a tab in repl mode with banner explicitly disabled', () => {
      const store = useTabsStore();
      const newTab = store.createTab({ withBanner: false });

      expect(newTab.mode).toBe('repl');
      expect(newTab.history.length).toBe(0);
    });

    it('creates a tab with custom title and preserves custom flag', () => {
      const store = useTabsStore();
      const newTab = store.createTab({ title: 'My Custom Query' });

      expect(newTab.title).toBe('My Custom Query');
      expect(newTab.isCustomTitle).toBe(true);
    });

    it('enforces maximum 12 tabs limit', () => {
      const store = useTabsStore();

      // Store starts with 1 tab, create 11 more to reach 12
      for (let i = 0; i < MAX_TABS - 1; i++) {
        const tab = store.createTab();
        expect(tab).not.toBeNull();
      }

      expect(store.tabCount).toBe(12);
      expect(store.canAddTab).toBe(false);

      // Attempting to create the 13th tab must fail
      const overflowTab = store.createTab();
      expect(overflowTab).toBeNull();
      expect(store.tabCount).toBe(12);
    });
  });

  describe('closeTab()', () => {
    it('closes a non-active tab without changing activeTabId', () => {
      const store = useTabsStore();
      const initialTabId = store.activeTabId;
      const tab2 = store.createTab();
      store.setActiveTab(initialTabId);

      expect(store.activeTabId).toBe(initialTabId);
      expect(store.tabCount).toBe(2);

      const result = store.closeTab(tab2.id);
      expect(result).toBe(true);
      expect(store.tabCount).toBe(1);
      expect(store.activeTabId).toBe(initialTabId);
    });

    it('activates adjacent tab when active tab is closed', () => {
      const store = useTabsStore();
      const tab1Id = store.activeTabId;
      const tab2 = store.createTab();
      const tab3 = store.createTab();

      // Currently tab3 is active
      expect(store.activeTabId).toBe(tab3.id);

      // Closing tab3 (last tab) should activate tab2 (previous tab)
      store.closeTab(tab3.id);
      expect(store.tabCount).toBe(2);
      expect(store.activeTabId).toBe(tab2.id);

      // Closing tab1 when tab2 is active should leave tab2 active
      store.closeTab(tab1Id);
      expect(store.tabCount).toBe(1);
      expect(store.activeTabId).toBe(tab2.id);
    });

    it('resets to a fresh tab with banner when closing the sole remaining tab', () => {
      const store = useTabsStore();
      const originalId = store.activeTabId;

      const result = store.closeTab(originalId);
      expect(result).toBe(true);
      expect(store.tabCount).toBe(1);
      expect(store.activeTabId).not.toBe(originalId);
      expect(store.activeTab.title).toBe('Console');
      expect(store.activeTab.mode).toBe('repl');
      expect(store.activeTab.history.length).toBe(1);
      expect(store.activeTab.history[0].messages).toEqual(['Welcome to Expression Lab']);
    });

    it('returns false when trying to close a non-existent tab', () => {
      const store = useTabsStore();
      const result = store.closeTab('non-existent-id');
      expect(result).toBe(false);
      expect(store.tabCount).toBe(1);
    });
  });

  describe('reorderTabs()', () => {
    it('reorders tabs from index to index cleanly', () => {
      const store = useTabsStore();
      const tab1 = store.activeTab;
      const tab2 = store.createTab({ title: 'Tab 2' });
      const tab3 = store.createTab({ title: 'Tab 3' });

      expect(store.tabs.map((t) => t.id)).toEqual([tab1.id, tab2.id, tab3.id]);

      // Move tab3 from index 2 to index 0
      store.reorderTabs(2, 0);
      expect(store.tabs.map((t) => t.id)).toEqual([tab3.id, tab1.id, tab2.id]);

      // Move tab1 from index 1 to index 2
      store.reorderTabs(1, 2);
      expect(store.tabs.map((t) => t.id)).toEqual([tab3.id, tab2.id, tab1.id]);
    });

    it('ignores invalid indices in reorderTabs', () => {
      const store = useTabsStore();
      const tab1 = store.activeTab;
      const tab2 = store.createTab();

      const originalOrder = [tab1.id, tab2.id];
      store.reorderTabs(-1, 1);
      expect(store.tabs.map((t) => t.id)).toEqual(originalOrder);

      store.reorderTabs(0, 99);
      expect(store.tabs.map((t) => t.id)).toEqual(originalOrder);

      store.reorderTabs(1, 1);
      expect(store.tabs.map((t) => t.id)).toEqual(originalOrder);
    });
  });

  describe('setTabTitle()', () => {
    it('updates title and marks isCustomTitle as true', () => {
      const store = useTabsStore();
      const tabId = store.activeTabId;

      store.setTabTitle(tabId, 'Analysis Script');
      expect(store.activeTab.title).toBe('Analysis Script');
      expect(store.activeTab.isCustomTitle).toBe(true);
    });

    it('resets title to mode default when given empty string', () => {
      const store = useTabsStore();
      const tabId = store.activeTabId;

      store.setTabTitle(tabId, 'Temp');
      expect(store.activeTab.isCustomTitle).toBe(true);

      store.setTabTitle(tabId, '   ');
      expect(store.activeTab.title).toBe('Console');
      expect(store.activeTab.isCustomTitle).toBe(false);
    });
  });

  describe('setTabMode()', () => {
    it('switches mode and updates title if not customized', () => {
      const store = useTabsStore();
      const tabId = store.activeTabId;

      expect(store.activeTab.title).toBe('Console');
      expect(store.activeTab.mode).toBe('repl');

      store.setTabMode(tabId, 'scratchpad');
      expect(store.activeTab.mode).toBe('scratchpad');
      expect(store.activeTab.title).toBe('Scratchpad');

      store.setTabMode(tabId, 'repl');
      expect(store.activeTab.mode).toBe('repl');
      expect(store.activeTab.title).toBe('Console');
    });

    it('preserves custom title when switching mode', () => {
      const store = useTabsStore();
      const tabId = store.activeTabId;

      store.setTabTitle(tabId, 'My Special Workspace');
      expect(store.activeTab.isCustomTitle).toBe(true);

      store.setTabMode(tabId, 'scratchpad');
      expect(store.activeTab.mode).toBe('scratchpad');
      expect(store.activeTab.title).toBe('My Special Workspace');
    });
  });

  describe('setTabEvaluating()', () => {
    it('toggles isEvaluating boolean', () => {
      const store = useTabsStore();
      const tabId = store.activeTabId;

      expect(store.activeTab.isEvaluating).toBe(false);
      store.setTabEvaluating(tabId, true);
      expect(store.activeTab.isEvaluating).toBe(true);
      store.setTabEvaluating(tabId, false);
      expect(store.activeTab.isEvaluating).toBe(false);
    });
  });

  describe('updateTabContext()', () => {
    it('updates user and site for the specified tab independently', () => {
      const store = useTabsStore();
      const tab1 = store.activeTab;
      const tab2 = store.createTab();

      store.updateTabContext(tab1.id, { user_id: 42, site_id: 99 });
      store.updateTabContext(tab2.id, { user_id: 7, site_id: 3 });

      expect(tab1.selectedUser).toBe(42);
      expect(tab1.selectedSite).toBe(99);

      expect(tab2.selectedUser).toBe(7);
      expect(tab2.selectedSite).toBe(3);
    });
  });

  describe('updateTabScratchpad() and updateTabRepl()', () => {
    it('updates scratchpad properties', () => {
      const store = useTabsStore();
      const tabId = store.activeTabId;

      store.updateTabScratchpad(tabId, {
        scratchpadCode: 'echo 1;',
        scratchpadEditorRatio: 65,
        isScratchpadOutputVisible: true,
      });

      expect(store.activeTab.scratchpadCode).toBe('echo 1;');
      expect(store.activeTab.scratchpadEditorRatio).toBe(65);
      expect(store.activeTab.isScratchpadOutputVisible).toBe(true);
    });

    it('updates REPL properties', () => {
      const store = useTabsStore();
      const tabId = store.activeTabId;

      store.updateTabRepl(tabId, {
        replDraft: 'Graph.bar()',
        historyIndex: 2,
      });

      expect(store.activeTab.replDraft).toBe('Graph.bar()');
      expect(store.activeTab.historyIndex).toBe(2);
    });
  });

  describe('duplicateTab()', () => {
    it('duplicates existing tab and activates the clone', () => {
      const store = useTabsStore();
      const tab1 = store.activeTab;
      store.setTabMode(tab1.id, 'scratchpad');
      store.setTabTitle(tab1.id, 'Original Script');
      store.updateTabScratchpad(tab1.id, { scratchpadCode: '$x = 100;' });
      store.updateTabContext(tab1.id, { user_id: 5, site_id: 2 });

      const clone = store.duplicateTab(tab1.id);

      expect(clone).not.toBeNull();
      expect(store.tabCount).toBe(2);
      expect(store.activeTabId).toBe(clone.id);
      expect(clone.title).toBe('Original Script (Copy)');
      expect(clone.mode).toBe('scratchpad');
      expect(clone.scratchpadCode).toBe('$x = 100;');
      expect(clone.selectedUser).toBe(5);
      expect(clone.selectedSite).toBe(2);
      expect(clone.history.length).toBe(0);
      expect(store.tabs[1].id).toBe(clone.id);
    });

    it('duplicates repl tab with banner initialized', () => {
      const store = useTabsStore();
      const tab1 = store.activeTab;
      const clone = store.duplicateTab(tab1.id);

      expect(clone).not.toBeNull();
      expect(clone.mode).toBe('repl');
      expect(clone.history.length).toBe(1);
      expect(clone.history[0].messages).toEqual(['Welcome to Expression Lab']);
    });

    it('returns null when duplicating at max tabs limit', () => {
      const store = useTabsStore();
      for (let i = 0; i < MAX_TABS - 1; i++) {
        store.createTab();
      }
      expect(store.tabCount).toBe(12);

      const clone = store.duplicateTab(store.tabs[0].id);
      expect(clone).toBeNull();
      expect(store.tabCount).toBe(12);
    });
  });

  describe('Default Mode Settings Integration', () => {
    it('initializes tab in scratchpad mode when defaultTabMode is scratchpad in localStorage', () => {
      localStorage.setItem('el_user_settings', JSON.stringify({ defaultTabMode: 'scratchpad' }));
      setActivePinia(createPinia());

      const store = useTabsStore();
      expect(store.activeTab.mode).toBe('scratchpad');
      expect(store.activeTab.title).toBe('Scratchpad');

      localStorage.removeItem('el_user_settings');
    });

    it('resets to clean tab in scratchpad mode when closing the sole tab with scratchpad default setting', () => {
      localStorage.setItem('el_user_settings', JSON.stringify({ defaultTabMode: 'scratchpad' }));
      setActivePinia(createPinia());

      const store = useTabsStore();
      const tabId = store.activeTab.id;
      store.closeTab(tabId);

      expect(store.tabCount).toBe(1);
      expect(store.activeTab.mode).toBe('scratchpad');
      expect(store.activeTab.title).toBe('Scratchpad');

      localStorage.removeItem('el_user_settings');
    });

    it('resets to clean tab in scratchpad mode when closing sole tab with settingsStore defaultTabMode (sandbox iframe scenario)', () => {
      setActivePinia(createPinia());
      const settingsStore = useSettingsStore();
      settingsStore.defaultTabMode = 'scratchpad';

      const store = useTabsStore();
      const tabId = store.activeTab.id;
      store.closeTab(tabId);

      expect(store.tabCount).toBe(1);
      expect(store.activeTab.mode).toBe('scratchpad');
      expect(store.activeTab.title).toBe('Scratchpad');
    });

    it('resets to scratchpad mode when closing sole tab after setting was changed dynamically during session', () => {
      setActivePinia(createPinia());
      const store = useTabsStore();
      expect(store.activeTab.mode).toBe('repl');

      const settingsStore = useSettingsStore();
      settingsStore.defaultTabMode = 'scratchpad';

      store.closeTab(store.activeTab.id);

      expect(store.tabCount).toBe(1);
      expect(store.activeTab.mode).toBe('scratchpad');
      expect(store.activeTab.title).toBe('Scratchpad');
    });

    it('creates tab in scratchpad mode by default when defaultTabMode is scratchpad', () => {
      setActivePinia(createPinia());
      const settingsStore = useSettingsStore();
      settingsStore.defaultTabMode = 'scratchpad';

      const store = useTabsStore();
      const newTab = store.createTab();

      expect(newTab.mode).toBe('scratchpad');
      expect(newTab.title).toBe('Scratchpad');
    });
  });
});
