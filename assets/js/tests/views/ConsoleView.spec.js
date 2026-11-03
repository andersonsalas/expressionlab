import { mount } from '@vue/test-utils';
import { createTestingPinia } from '@pinia/testing';

const mockOpenSearch = jest.fn();

jest.mock('../../components/console/ConsoleSession.vue', () => ({
  name: 'ConsoleSession',
  template: '<div class="console-session-stub" />',
  methods: {
    focus() {},
    openSearch(...args) {
      mockOpenSearch(...args);
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

describe('ConsoleView.vue Toolbar', () => {
  beforeEach(() => {
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
    expect(items.length).toBe(4);
    expect(items[0].text()).toContain('Search');
    expect(items[0].find('.codicon-search').exists()).toBe(true);
    expect(items[1].text()).toContain('Replace');
    expect(items[1].find('.codicon-replace').exists()).toBe(true);
    expect(items[2].text()).toContain('Settings');
    expect(items[2].find('.codicon-settings-gear').exists()).toBe(true);
    expect(items[3].text()).toContain('About');
    expect(items[3].find('.codicon-info').exists()).toBe(true);

    // Click Settings
    await items[2].trigger('click');
    expect(uiStore.openModal).toHaveBeenCalledWith('settings');
    expect(wrapper.find('.console-file-dropdown-menu').exists()).toBe(false);

    // Open File dropdown again and click About
    await fileDropdownBtn.trigger('click');
    const itemsAgain = wrapper.findAll('.console-file-dropdown-menu .tab-menu-item');
    await itemsAgain[3].trigger('click');
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
    await items[0].trigger('click');
    expect(mockOpenSearch).toHaveBeenCalledWith(false);

    // Replace item
    await fileDropdownBtn.trigger('click');
    const itemsAgain = wrapper.findAll('.console-file-dropdown-menu .tab-menu-item');
    await itemsAgain[1].trigger('click');
    expect(mockOpenSearch).toHaveBeenCalledWith(true);

    // Global Ctrl+F
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'f', ctrlKey: true }));
    expect(mockOpenSearch).toHaveBeenCalledWith(false);

    // Global Ctrl+H
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'h', ctrlKey: true }));
    expect(mockOpenSearch).toHaveBeenCalledWith(true);
  });

  it('renders Open button in Group 2 and Undo/Redo in File dropdown when addons are enabled', async () => {
    const wrapper = mountConsoleView({}, true);
    const group2 = wrapper.find('.group-file-actions');
    expect(group2.find('.btn-open-notebook').exists()).toBe(true);
    expect(group2.find('.btn-scratchpad-save').exists()).toBe(true);

    const fileDropdownBtn = wrapper.find('.group-file .menu-dropdown-btn');
    await fileDropdownBtn.trigger('click');
    const fileMenu = wrapper.find('.console-file-dropdown-menu');
    expect(fileMenu.exists()).toBe(true);

    const items = fileMenu.findAll('.tab-menu-item');
    expect(items.length).toBe(6);
    expect(items[0].text()).toContain('Undo cell action');
    expect(items[0].find('.codicon-discard').exists()).toBe(true);
    expect(items[1].text()).toContain('Redo cell action');
    expect(items[1].find('.codicon-redo').exists()).toBe(true);
    expect(items[2].text()).toContain('Search');
    expect(items[3].text()).toContain('Replace');
    expect(items[4].text()).toContain('Settings');
    expect(items[5].text()).toContain('About');
  });
});
