import { mount } from '@vue/test-utils';
import { createTestingPinia } from '@pinia/testing';

const mockOpenSearch = jest.fn();
const mockSaveAsPrimaryAction = jest.fn();

jest.mock('../../components/console/ConsoleSession.vue', () => ({
  name: 'ConsoleSession',
  template: '<div class="console-session-stub" />',
  methods: {
    focus() {},
    openSearch(...args) {
      mockOpenSearch(...args);
    },
    saveAsPrimaryAction(...args) {
      mockSaveAsPrimaryAction(...args);
    },
  },
}));
jest.mock('../../components/console/ConsoleTabBar.vue', () => ({
  name: 'ConsoleTabBar',
  template: '<div class="console-tab-bar-stub" />',
}));
jest.mock('../../components/console/ConsoleSidebar.vue', () => ({
  name: 'ConsoleSidebar',
  template: '<div class="console-sidebar-stub" />',
}));

import ConsoleView from '../../views/ConsoleView.vue';
import { useUiStore } from '../../stores/ui';
import { useTabsStore } from '../../stores/tabs';
import { menuRegistry } from '../../lib/menuRegistry.js';

describe('ConsoleView.vue Toolbar', () => {
  beforeEach(() => {
    menuRegistry.reset('file');
    window.el_settings = {
      multisite: false,
      site: { users: [], sites: [] },
      settings: {
        signature_duration: 30,
        enable_sandbox: false,
        database_readonly: false,
        filesystem_readonly: false,
        network_readonly: false,
      },
      server: {
        php_version: '8.2.0',
        wp_version: '6.5.0',
        server_ip: '127.0.0.1',
        user_ip: '127.0.0.1',
        debug_enabled: true,
      },
    };
  });

  afterEach(() => {
    menuRegistry.reset('file');
    delete global.__WITH_ADDONS__;
  });

  const mountConsoleView = (initialState = {}, withAddons = false) => {
    global.__WITH_ADDONS__ = withAddons;

    const pinia = createTestingPinia({
      initialState: {
        ui: {
          activeModal: null,
          isRequestingChallenge: false,
          currentChallenge: null,
          challengeFetchedAt: null,
          ...initialState.ui,
        },
        tabs: {
          tabs: [
            { id: 'tab-1', mode: 'scratchpad', isEvaluating: false, isScratchpadOutputVisible: false },
          ],
          activeTabId: 'tab-1',
          activeTab: { id: 'tab-1', mode: 'scratchpad' },
          ...initialState.tabs,
        },
        settings: {
          isSidebarVisible: true,
          ...initialState.settings,
        },
        outline: {
          outlineTree: [],
          loading: false,
          ...initialState.outline,
        },
      },
    });

    const wrapper = mount(ConsoleView, {
      global: {
        plugins: [pinia],
        stubs: {
          ConsoleTabBar: true,
          ConsoleSidebar: true,
          PaginatedSearchDropdown: true,
          Teleport: true,
        },
        mocks: {
          __: (str) => str,
        },
      },
    });

    return wrapper;
  };

  it('renders the 7 toolbar groups in the exact required order', () => {
    const wrapper = mountConsoleView();
    const toolbar = wrapper.find('.console-toolbar');
    expect(toolbar.exists()).toBe(true);

    const groups = toolbar.findAll('.console-toolbar-group');
    expect(groups.length).toBe(7);

    // Group 1: File dropdown
    expect(groups[0].classes()).toContain('group-file');
    const svgIcon = groups[0].find('svg');
    expect(svgIcon.exists()).toBe(true);
    expect(svgIcon.attributes('style')).toContain('width: 26px');
    expect(svgIcon.attributes('style')).toContain('height: 26px');

    // Group 2: Open / Save
    expect(groups[1].classes()).toContain('group-file-actions');
    expect(groups[1].find('.btn-scratchpad-save').exists()).toBe(true);

    // Group 3: Run / Clear / Format
    expect(groups[2].classes()).toContain('group-scratchpad-actions');
    expect(groups[2].find('.btn-scratchpad-run').exists()).toBe(true);
    expect(groups[2].find('.codicon-circle-slash').exists()).toBe(true);
    expect(groups[2].find('.codicon-json').exists()).toBe(true);

    // Group 4: Users dropdown
    expect(groups[3].classes()).toContain('group-users');

    // Group 5: Sites dropdown
    expect(groups[4].classes()).toContain('group-sites');

    // Group 6: Tools & Library
    expect(groups[5].classes()).toContain('group-tools-library');
    expect(groups[5].find('.menu-dropdown-btn').text()).toContain('Tools');
    expect(groups[5].text()).toContain('Library');

    // Group 7: Panel toggles (pull-right)
    expect(groups[6].classes()).toContain('pull-right');
    expect(groups[6].find('.custom-icon-panel-bottom').exists()).toBe(true);
    expect(groups[6].find('.custom-icon-panel-right').exists()).toBe(true);
    // About is no longer in pull-right
    expect(groups[6].find('.codicon-info').exists()).toBe(false);
  });

  it('toggles File dropdown and triggers Settings and About modals', async () => {
    const wrapper = mountConsoleView();
    const uiStore = useUiStore();

    const fileDropdownBtn = wrapper.find('.group-file .menu-dropdown-btn');
    expect(fileDropdownBtn.exists()).toBe(true);
    expect(wrapper.find('.console-file-dropdown-menu').exists()).toBe(false);

    // Open File dropdown
    await fileDropdownBtn.trigger('click');
    const fileMenu = wrapper.find('.console-file-dropdown-menu');
    expect(fileMenu.exists()).toBe(true);

    const items = fileMenu.findAll('.tab-menu-item');
    expect(items.length).toBe(10);
    const searchItem = items.find((i) => i.text().includes('Search'));
    expect(searchItem).toBeDefined();
    expect(searchItem.find('.codicon-search').exists()).toBe(true);
    const replaceItem = items.find((i) => i.text().includes('Replace'));
    expect(replaceItem).toBeDefined();
    expect(replaceItem.find('.codicon-replace').exists()).toBe(true);
    const settingsItem = items.find((i) => i.text().includes('Settings'));
    expect(settingsItem).toBeDefined();
    expect(settingsItem.find('.codicon-settings-gear').exists()).toBe(true);
    const aboutItem = items.find((i) => i.text().includes('About'));
    expect(aboutItem).toBeDefined();
    expect(aboutItem.find('.codicon-info').exists()).toBe(true);

    // Click Settings
    await settingsItem.trigger('click');
    expect(uiStore.openModal).toHaveBeenCalledWith('settings');
    expect(wrapper.find('.console-file-dropdown-menu').exists()).toBe(false);

    // Open File dropdown again and click About
    await fileDropdownBtn.trigger('click');
    const itemsAgain = wrapper.findAll('.console-file-dropdown-menu .tab-menu-item');
    const aboutItemAgain = itemsAgain.find((i) => i.text().includes('About'));
    await aboutItemAgain.trigger('click');
    expect(uiStore.openModal).toHaveBeenCalledWith('about');
    expect(wrapper.find('.console-file-dropdown-menu').exists()).toBe(false);
  });

  it('triggers Search and Replace from File dropdown and global shortcuts', async () => {
    mockOpenSearch.mockClear();
    const wrapper = mountConsoleView();

    const fileDropdownBtn = wrapper.find('.group-file .menu-dropdown-btn');
    await fileDropdownBtn.trigger('click');

    const items = wrapper.findAll('.console-file-dropdown-menu .tab-menu-item');
    // Search item
    const searchItem = items.find((i) => i.text().includes('Search'));
    await searchItem.trigger('click');
    expect(mockOpenSearch).toHaveBeenCalledWith(false);

    // Replace item
    await fileDropdownBtn.trigger('click');
    const itemsAgain = wrapper.findAll('.console-file-dropdown-menu .tab-menu-item');
    const replaceItem = itemsAgain.find((i) => i.text().includes('Replace'));
    await replaceItem.trigger('click');
    expect(mockOpenSearch).toHaveBeenCalledWith(true);

    // Global Ctrl+F
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'f', ctrlKey: true }));
    expect(mockOpenSearch).toHaveBeenCalledWith(false);

    // Global Ctrl+H
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'h', ctrlKey: true }));
    expect(mockOpenSearch).toHaveBeenCalledWith(true);
  });

  it('renders Open button in Group 2 and extended items in File dropdown when a transformer is registered', async () => {
    const mockTransformerAction = jest.fn();
    menuRegistry.registerTransformer('file', (groups) => {
      const fileGroup = groups.find((g) => g.id === 'file');
      if (fileGroup) {
        fileGroup.items.splice(1, 0, {
          id: 'open',
          label: 'Open...',
          icon: 'codicon-folder-opened',
          shortcut: 'Ctrl+O',
          action: mockTransformerAction,
        });
      }
      const editGroup = groups.find((g) => g.id === 'edit');
      if (editGroup) {
        editGroup.items.unshift(
          {
            id: 'undo',
            label: 'Undo cell action',
            icon: 'codicon-discard',
            shortcut: 'Ctrl+Z',
            disabled: false,
            action: jest.fn(),
          },
          {
            id: 'redo',
            label: 'Redo cell action',
            icon: 'codicon-redo',
            shortcut: 'Ctrl+Shift+Z',
            disabled: false,
            action: jest.fn(),
          }
        );
      }
      return groups;
    });

    const wrapper = mountConsoleView({}, true);
    const group2 = wrapper.find('.group-file-actions');
    expect(group2.find('.btn-open-notebook').exists()).toBe(true);
    expect(group2.find('.btn-scratchpad-save').exists()).toBe(true);

    const fileDropdownBtn = wrapper.find('.group-file .menu-dropdown-btn');
    await fileDropdownBtn.trigger('click');
    const fileMenu = wrapper.find('.console-file-dropdown-menu');
    expect(fileMenu.exists()).toBe(true);

    const items = fileMenu.findAll('.tab-menu-item');
    expect(items.length).toBe(13); // 10 base + 3 injected
    const undoItem = items.find((i) => i.text().includes('Undo cell action'));
    expect(undoItem).toBeDefined();
    expect(undoItem.find('.codicon-discard').exists()).toBe(true);
    const redoItem = items.find((i) => i.text().includes('Redo cell action'));
    expect(redoItem).toBeDefined();
    expect(redoItem.find('.codicon-redo').exists()).toBe(true);
    const openItem = items.find((i) => i.text().includes('Open...'));
    expect(openItem).toBeDefined();
    expect(openItem.find('.codicon-folder-opened').exists()).toBe(true);

    // Clicking openItem triggers action
    await openItem.trigger('click');
    expect(mockTransformerAction).toHaveBeenCalledTimes(1);
  });

  it('triggers Save as... from File dropdown and global Ctrl+Shift+S when registered by transformer in Notebook mode', async () => {
    mockSaveAsPrimaryAction.mockClear();

    menuRegistry.registerTransformer('file', (groups, context) => {
      const fileGroup = groups.find((g) => g.id === 'file');
      if (fileGroup) {
        fileGroup.items.push({
          id: 'save-as',
          label: 'Save as...',
          icon: 'codicon-save',
          shortcut: `${context.modKey}+Shift+S`,
          disabled: !context.isNotebook,
          action: () => {
            context.activeSession?.saveAsPrimaryAction?.();
          },
        });
      }
      return groups;
    });

    const wrapper = mountConsoleView(
      {
        tabs: {
          tabs: [{ id: 'tab-1', mode: 'notebook', isEvaluating: false }],
          activeTabId: 'tab-1',
          activeTab: { id: 'tab-1', mode: 'notebook' },
        },
      },
      true
    );

    const fileDropdownBtn = wrapper.find('.group-file .menu-dropdown-btn');
    await fileDropdownBtn.trigger('click');

    const items = wrapper.findAll('.console-file-dropdown-menu .tab-menu-item');
    const saveAsItem = items.find((i) => i.text().includes('Save as...'));
    expect(saveAsItem).toBeDefined();
    expect(saveAsItem.classes()).not.toContain('disabled');

    await saveAsItem.trigger('click');
    expect(mockSaveAsPrimaryAction).toHaveBeenCalledTimes(1);

    // Global Ctrl+Shift+S
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'S', ctrlKey: true, shiftKey: true }));
    expect(mockSaveAsPrimaryAction).toHaveBeenCalledTimes(2);
  });

  it('ensures only one dropdown is open at a time between File and Tools dropdowns', async () => {
    const wrapper = mountConsoleView();
    const uiStore = useUiStore();

    const fileBtn = wrapper.find('.group-file .menu-dropdown-btn');
    const toolsBtn = wrapper.find('.group-tools-library .menu-dropdown-btn');

    // Click File dropdown
    await fileBtn.trigger('click');
    expect(uiStore.activeDropdown).toBe('file-menu');
    expect(wrapper.find('.console-file-dropdown-menu').exists()).toBe(true);
    expect(wrapper.find('.console-tools-dropdown-menu').exists()).toBe(false);

    // Click Tools dropdown -> closes File and opens Tools
    await toolsBtn.trigger('click');
    expect(uiStore.activeDropdown).toBe('tools-menu');
    expect(wrapper.find('.console-file-dropdown-menu').exists()).toBe(false);
    expect(wrapper.find('.console-tools-dropdown-menu').exists()).toBe(true);

    // Click Tools dropdown again -> closes Tools
    await toolsBtn.trigger('click');
    expect(uiStore.activeDropdown).toBeNull();
    expect(wrapper.find('.console-tools-dropdown-menu').exists()).toBe(false);
  });

  it('ignores and resets snippetToInsert when active tab is a locked read-only notebook', async () => {
    const wrapper = mountConsoleView({
      tabs: {
        tabs: [
          {
            id: 'tab-locked',
            mode: 'notebook',
            notebookFileHandleId: 'handle-locked',
            notebookMeta: { meta: { isReadOnly: true } },
            notebookUnlockedForEditing: false,
            notebookPersistedReadOnly: true,
          },
        ],
        activeTabId: 'tab-locked',
      },
    }, true);

    const uiStore = useUiStore();
    uiStore.snippetToInsert = 'console.log("hacked")';
    await wrapper.vm.$nextTick();

    expect(uiStore.triggerSnippetInsert).toHaveBeenCalledWith(null);
  });

  it('triggers New tab, Close tab, and Close all tabs from File dropdown', async () => {
    const wrapper = mountConsoleView();
    const tabsStore = useTabsStore();

    const fileDropdownBtn = wrapper.find('.group-file .menu-dropdown-btn');
    await fileDropdownBtn.trigger('click');

    const fileMenu = wrapper.find('.console-file-dropdown-menu');
    const items = fileMenu.findAll('.tab-menu-item');

    // New item
    const newItem = items.find((i) => i.text().includes('New'));
    expect(newItem).toBeDefined();
    await newItem.trigger('click');
    expect(tabsStore.createTab).toHaveBeenCalled();

    // Close tab item
    await fileDropdownBtn.trigger('click');
    const itemsAgain = wrapper.findAll('.console-file-dropdown-menu .tab-menu-item');
    const closeTabItem = itemsAgain.find((i) => i.text().includes('Close tab'));
    expect(closeTabItem).toBeDefined();
    await closeTabItem.trigger('click');
    expect(tabsStore.requestCloseTab).toHaveBeenCalledWith(tabsStore.activeTabId);

    // Close all tabs item
    await fileDropdownBtn.trigger('click');
    const itemsThird = wrapper.findAll('.console-file-dropdown-menu .tab-menu-item');
    const closeAllItem = itemsThird.find((i) => i.text().includes('Close all tabs'));
    expect(closeAllItem).toBeDefined();
    await closeAllItem.trigger('click');
    expect(tabsStore.requestCloseAllTabs).toHaveBeenCalled();
  });
});
