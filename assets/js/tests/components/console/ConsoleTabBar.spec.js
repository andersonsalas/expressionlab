import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import ConsoleTabBar from '../../../components/console/ConsoleTabBar.vue';
import { useTabsStore } from '../../../stores/tabs.js';

describe('ConsoleTabBar.vue', () => {
  let pinia;
  let tabsStore;

  beforeEach(() => {
    window.el_settings = {
      user_id: 1,
      multisite: false,
    };
    pinia = createPinia();
    setActivePinia(pinia);
    tabsStore = useTabsStore();
  });

  afterEach(() => {
    delete window.el_settings;
  });

  it('renders initial tab with default title and active class', () => {
    const wrapper = mount(ConsoleTabBar, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    const tabItems = wrapper.findAll('.console-tab-item');
    expect(tabItems.length).toBe(1);
    expect(tabItems[0].classes()).toContain('active');
    expect(tabItems[0].find('.tab-title').text()).toBe('Console');
    expect(wrapper.find('.console-tab-add-btn').exists()).toBe(true);
  });

  it('selects active tab on click', async () => {
    tabsStore.createTab({ title: 'Query 2' });

    const wrapper = mount(ConsoleTabBar, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    const tabItems = wrapper.findAll('.console-tab-item');
    expect(tabItems.length).toBe(2);

    // Click on the first tab
    await tabItems[0].trigger('click');
    expect(tabsStore.activeTabId).toBe(tabsStore.tabs[0].id);

    // Click on the second tab
    await tabItems[1].trigger('click');
    expect(tabsStore.activeTabId).toBe(tabsStore.tabs[1].id);
  });

  it('closes tab when close button is clicked', async () => {
    const tab2 = tabsStore.createTab({ title: 'Tab to Close' });
    expect(tabsStore.tabCount).toBe(2);

    const wrapper = mount(ConsoleTabBar, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    const closeBtns = wrapper.findAll('.tab-close-btn');
    expect(closeBtns.length).toBe(2);

    await closeBtns[1].trigger('click');
    expect(tabsStore.tabCount).toBe(1);
    expect(tabsStore.tabs.some((t) => t.id === tab2.id)).toBe(false);
  });

  it('adds a new tab when add button is clicked', async () => {
    const wrapper = mount(ConsoleTabBar, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    expect(tabsStore.tabCount).toBe(1);
    await wrapper.find('.console-tab-add-btn').trigger('click');

    expect(tabsStore.tabCount).toBe(2);
    expect(wrapper.findAll('.console-tab-item').length).toBe(2);
  });

  it('hides add button when 12 tabs limit is reached', async () => {
    for (let i = 0; i < 11; i++) {
      tabsStore.createTab();
    }
    expect(tabsStore.tabCount).toBe(12);
    expect(tabsStore.canAddTab).toBe(false);

    const wrapper = mount(ConsoleTabBar, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    expect(wrapper.find('.console-tab-add-btn').exists()).toBe(false);
  });

  it('renders evaluating spinner when tab.isEvaluating is true', async () => {
    const wrapper = mount(ConsoleTabBar, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    expect(wrapper.find('.tab-spinner').exists()).toBe(false);

    tabsStore.setTabEvaluating(tabsStore.activeTabId, true);
    await wrapper.vm.$nextTick();

    expect(wrapper.find('.tab-spinner').exists()).toBe(true);
  });

  it('supports in-place title rename on double click', async () => {
    const wrapper = mount(ConsoleTabBar, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    const tabItem = wrapper.find('.console-tab-item');
    expect(wrapper.find('.tab-title-input').exists()).toBe(false);

    // Double-click triggers edit mode
    await tabItem.trigger('dblclick');
    expect(wrapper.find('.tab-title-input').exists()).toBe(true);

    const input = wrapper.find('.tab-title-input');
    await input.setValue('Renamed Query');
    await input.trigger('keydown.enter');

    expect(wrapper.find('.tab-title-input').exists()).toBe(false);
    expect(tabsStore.activeTab.title).toBe('Renamed Query');
    expect(tabsStore.activeTab.isCustomTitle).toBe(true);
  });

  it('cancels in-place rename on escape key', async () => {
    const wrapper = mount(ConsoleTabBar, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    const tabItem = wrapper.find('.console-tab-item');
    await tabItem.trigger('dblclick');

    const input = wrapper.find('.tab-title-input');
    await input.setValue('Cancelled Query');
    await input.trigger('keydown.esc');

    expect(wrapper.find('.tab-title-input').exists()).toBe(false);
    expect(tabsStore.activeTab.title).toBe('Console');
  });

  it('handles drag and drop reordering of tabs', async () => {
    const tab1 = tabsStore.activeTab;
    const tab2 = tabsStore.createTab({ title: 'Tab 2' });

    const wrapper = mount(ConsoleTabBar, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    const tabElements = wrapper.findAll('.console-tab-item');
    const dataTransfer = {
      setData: jest.fn(),
      effectAllowed: 'none',
    };

    // Drag tab 0
    await tabElements[0].trigger('dragstart', { dataTransfer });
    expect(dataTransfer.setData).toHaveBeenCalledWith('text/plain', '0');

    // Drag over tab 1
    await tabElements[1].trigger('dragover');

    // Drop onto tab 1
    await tabElements[1].trigger('drop');

    // Verify tabs reordered
    expect(tabsStore.tabs[0].id).toBe(tab2.id);
    expect(tabsStore.tabs[1].id).toBe(tab1.id);
  });
});
