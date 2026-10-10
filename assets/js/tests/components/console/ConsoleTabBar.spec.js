import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import ConsoleTabBar from '../../../components/console/ConsoleTabBar.vue';
import { useTabsStore } from '../../../stores/tabs.js';
import { useSettingsStore } from '../../../stores/settings.js';

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
    await wrapper.find('.console-tab-add-btn.btn-add-main').trigger('click');

    expect(tabsStore.tabCount).toBe(2);
    expect(tabsStore.activeTab.mode).toBe('repl');
    expect(wrapper.findAll('.console-tab-item').length).toBe(2);
  });

  it('adds a scratchpad tab when add button is clicked and defaultTabMode is scratchpad', async () => {
    const settingsStore = useSettingsStore();
    settingsStore.defaultTabMode = 'scratchpad';

    const wrapper = mount(ConsoleTabBar, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    const addBtn = wrapper.find('.console-tab-add-btn.btn-add-main');
    expect(addBtn.attributes('title')).toBe('New Scratchpad tab');

    await addBtn.trigger('click');

    expect(tabsStore.tabCount).toBe(2);
    expect(tabsStore.activeTab.mode).toBe('scratchpad');
  });

  it('toggles dropdown and creates scratchpad tab from dropdown item', async () => {
    const wrapper = mount(ConsoleTabBar, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
      attachTo: document.body,
    });

    const dropdownBtn = wrapper.find('.btn-add-dropdown');
    expect(dropdownBtn.exists()).toBe(true);

    await dropdownBtn.trigger('click');
    expect(document.body.querySelector('.console-tab-add-menu')).not.toBeNull();

    const menuItems = document.body.querySelectorAll('.tab-add-menu-item');
    expect(menuItems.length).toBe(2);

    // Click scratchpad option
    menuItems[1].dispatchEvent(new MouseEvent('click', { bubbles: true }));
    await wrapper.vm.$nextTick();

    expect(tabsStore.tabCount).toBe(2);
    expect(tabsStore.activeTab.mode).toBe('scratchpad');
    expect(tabsStore.activeTab.title).toBe('Scratchpad');
    expect(document.body.querySelector('.console-tab-add-menu')).toBeNull();

    wrapper.unmount();
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

  describe('Context menu', () => {
    it('opens context menu on right click with expected menu items', async () => {
      const wrapper = mount(ConsoleTabBar, {
        global: {
          plugins: [pinia],
          mocks: {
            __: (str) => str,
          },
        },
        attachTo: document.body,
      });

      const tabItem = wrapper.find('.console-tab-item');
      expect(document.body.querySelector('.console-tab-context-menu')).toBeNull();

      // Right-click the tab
      await tabItem.trigger('contextmenu', { clientX: 100, clientY: 50 });
      const menu = document.body.querySelector('.console-tab-context-menu');
      expect(menu).not.toBeNull();

      // Verify all items exist
      expect(menu.querySelector('.btn-close-all')).not.toBeNull();
      expect(menu.querySelector('.btn-close-other')).not.toBeNull();
      expect(menu.querySelector('.btn-close-right')).not.toBeNull();
      expect(menu.querySelector('.tab-menu-separator')).not.toBeNull();
      expect(menu.querySelector('.btn-rename')).not.toBeNull();

      // With only 1 tab, "close other" and "close right" are disabled
      expect(menu.querySelector('.btn-close-other').classList.contains('disabled')).toBe(true);
      expect(menu.querySelector('.btn-close-right').classList.contains('disabled')).toBe(true);

      wrapper.unmount();
    });

    it('enables close other and close right when multiple tabs exist and tab is not rightmost', async () => {
      tabsStore.createTab({ title: 'Tab 2' });
      tabsStore.createTab({ title: 'Tab 3' });

      const wrapper = mount(ConsoleTabBar, {
        global: {
          plugins: [pinia],
          mocks: {
            __: (str) => str,
          },
        },
        attachTo: document.body,
      });

      const tabItems = wrapper.findAll('.console-tab-item');
      // Right click middle tab (Tab 2, index 1)
      await tabItems[1].trigger('contextmenu', { clientX: 150, clientY: 50 });

      const menu = document.body.querySelector('.console-tab-context-menu');
      expect(menu).not.toBeNull();
      expect(menu.querySelector('.btn-close-other').classList.contains('disabled')).toBe(false);
      expect(menu.querySelector('.btn-close-right').classList.contains('disabled')).toBe(false);

      wrapper.unmount();
    });

    it('executes close all tabs from context menu', async () => {
      tabsStore.createTab({ title: 'Tab 2' });
      expect(tabsStore.tabCount).toBe(2);

      const wrapper = mount(ConsoleTabBar, {
        global: {
          plugins: [pinia],
          mocks: {
            __: (str) => str,
          },
        },
        attachTo: document.body,
      });

      await wrapper.find('.console-tab-item').trigger('contextmenu');
      const closeAllBtn = document.body.querySelector('.console-tab-context-menu .btn-close-all');
      closeAllBtn.dispatchEvent(new MouseEvent('click', { bubbles: true }));
      await wrapper.vm.$nextTick();

      expect(tabsStore.tabCount).toBe(1);
      expect(document.body.querySelector('.console-tab-context-menu')).toBeNull();

      wrapper.unmount();
    });

    it('executes close other tabs from context menu', async () => {
      const tab1 = tabsStore.activeTab;
      const tab2 = tabsStore.createTab({ title: 'Tab 2' });
      tabsStore.createTab({ title: 'Tab 3' });
      expect(tabsStore.tabCount).toBe(3);

      const wrapper = mount(ConsoleTabBar, {
        global: {
          plugins: [pinia],
          mocks: {
            __: (str) => str,
          },
        },
        attachTo: document.body,
      });

      const tabItems = wrapper.findAll('.console-tab-item');
      // Right-click tab 2
      await tabItems[1].trigger('contextmenu');

      const closeOtherBtn = document.body.querySelector('.console-tab-context-menu .btn-close-other');
      closeOtherBtn.dispatchEvent(new MouseEvent('click', { bubbles: true }));
      await wrapper.vm.$nextTick();

      expect(tabsStore.tabCount).toBe(1);
      expect(tabsStore.tabs[0].id).toBe(tab2.id);
      expect(tabsStore.tabs.some((t) => t.id === tab1.id)).toBe(false);
      expect(document.body.querySelector('.console-tab-context-menu')).toBeNull();

      wrapper.unmount();
    });

    it('executes close tabs to the right from context menu', async () => {
      const tab1 = tabsStore.activeTab;
      const tab2 = tabsStore.createTab({ title: 'Tab 2' });
      const tab3 = tabsStore.createTab({ title: 'Tab 3' });
      expect(tabsStore.tabCount).toBe(3);

      const wrapper = mount(ConsoleTabBar, {
        global: {
          plugins: [pinia],
          mocks: {
            __: (str) => str,
          },
        },
        attachTo: document.body,
      });

      const tabItems = wrapper.findAll('.console-tab-item');
      // Right-click tab 2
      await tabItems[1].trigger('contextmenu');

      const closeRightBtn = document.body.querySelector('.console-tab-context-menu .btn-close-right');
      closeRightBtn.dispatchEvent(new MouseEvent('click', { bubbles: true }));
      await wrapper.vm.$nextTick();

      expect(tabsStore.tabCount).toBe(2);
      expect(tabsStore.tabs.map((t) => t.id)).toEqual([tab1.id, tab2.id]);
      expect(tabsStore.tabs.some((t) => t.id === tab3.id)).toBe(false);
      expect(document.body.querySelector('.console-tab-context-menu')).toBeNull();

      wrapper.unmount();
    });

    it('triggers in-place rename from context menu', async () => {
      const tab2 = tabsStore.createTab({ title: 'Tab 2' });

      const wrapper = mount(ConsoleTabBar, {
        global: {
          plugins: [pinia],
          mocks: {
            __: (str) => str,
          },
        },
        attachTo: document.body,
      });

      const tabItems = wrapper.findAll('.console-tab-item');
      // Right click first tab (Console)
      await tabItems[0].trigger('contextmenu');

      const renameBtn = document.body.querySelector('.console-tab-context-menu .btn-rename');
      renameBtn.dispatchEvent(new MouseEvent('click', { bubbles: true }));
      await wrapper.vm.$nextTick();

      // Menu is closed and edit input appears
      expect(document.body.querySelector('.console-tab-context-menu')).toBeNull();
      expect(wrapper.find('.tab-title-input').exists()).toBe(true);

      wrapper.unmount();
    });

    it('closes context menu when Escape key is pressed', async () => {
      const wrapper = mount(ConsoleTabBar, {
        global: {
          plugins: [pinia],
          mocks: {
            __: (str) => str,
          },
        },
        attachTo: document.body,
      });

      await wrapper.find('.console-tab-item').trigger('contextmenu');
      expect(document.body.querySelector('.console-tab-context-menu')).not.toBeNull();

      window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
      await wrapper.vm.$nextTick();

      expect(document.body.querySelector('.console-tab-context-menu')).toBeNull();
      wrapper.unmount();
    });

    it('closes context menu when clicking outside', async () => {
      const wrapper = mount(ConsoleTabBar, {
        global: {
          plugins: [pinia],
          mocks: {
            __: (str) => str,
          },
        },
        attachTo: document.body,
      });

      await wrapper.find('.console-tab-item').trigger('contextmenu');
      expect(document.body.querySelector('.console-tab-context-menu')).not.toBeNull();

      document.body.dispatchEvent(new MouseEvent('click', { bubbles: true }));
      await wrapper.vm.$nextTick();

      expect(document.body.querySelector('.console-tab-context-menu')).toBeNull();
      wrapper.unmount();
    });

    it('positions context menu to the right of cursor by default', async () => {
      const wrapper = mount(ConsoleTabBar, {
        global: {
          plugins: [pinia],
          mocks: {
            __: (str) => str,
          },
        },
        attachTo: document.body,
      });

      await wrapper.find('.console-tab-item').trigger('contextmenu', { clientX: 120, clientY: 45 });
      await wrapper.vm.$nextTick();

      const menu = document.body.querySelector('.console-tab-context-menu');
      expect(menu).not.toBeNull();
      expect(menu.style.left).toBe('120px');
      expect(menu.style.top).toBe('45px');

      wrapper.unmount();
    });

    const mountBar = () =>
      mount(ConsoleTabBar, {
        global: {
          plugins: [pinia],
          mocks: {
            __: (str) => str,
          },
        },
        attachTo: document.body,
      });

    it('does not execute disabled actions when clicked', async () => {
      const wrapper = mountBar();

      // Single tab: both "close other" and "close right" are disabled
      await wrapper.find('.console-tab-item').trigger('contextmenu');
      const menu = document.body.querySelector('.console-tab-context-menu');
      const idBefore = tabsStore.tabs[0].id;

      menu.querySelector('.btn-close-other').dispatchEvent(new MouseEvent('click', { bubbles: true }));
      await wrapper.vm.$nextTick();
      menu.querySelector('.btn-close-right').dispatchEvent(new MouseEvent('click', { bubbles: true }));
      await wrapper.vm.$nextTick();

      expect(tabsStore.tabs.length).toBe(1);
      expect(tabsStore.tabs[0].id).toBe(idBefore);

      wrapper.unmount();
    });

    it('disables close to the right for the rightmost tab even with multiple tabs', async () => {
      tabsStore.createTab({ title: 'Tab 2' });
      const wrapper = mountBar();

      const tabItems = wrapper.findAll('.console-tab-item');
      await tabItems[1].trigger('contextmenu');

      const menu = document.body.querySelector('.console-tab-context-menu');
      expect(menu.querySelector('.btn-close-other').classList.contains('disabled')).toBe(false);
      expect(menu.querySelector('.btn-close-right').classList.contains('disabled')).toBe(true);

      wrapper.unmount();
    });

    it('closes context menu when the tab bar is scrolled', async () => {
      const wrapper = mountBar();

      await wrapper.find('.console-tab-item').trigger('contextmenu');
      expect(document.body.querySelector('.console-tab-context-menu')).not.toBeNull();

      await wrapper.find('.console-tabbar-scroll-wrapper').trigger('scroll');
      expect(document.body.querySelector('.console-tab-context-menu')).toBeNull();

      wrapper.unmount();
    });

    it('closes context menu when the window is resized', async () => {
      const wrapper = mountBar();

      await wrapper.find('.console-tab-item').trigger('contextmenu');
      expect(document.body.querySelector('.console-tab-context-menu')).not.toBeNull();

      window.dispatchEvent(new Event('resize'));
      await wrapper.vm.$nextTick();
      expect(document.body.querySelector('.console-tab-context-menu')).toBeNull();

      wrapper.unmount();
    });

    it('closes context menu when right-clicking outside of it', async () => {
      const wrapper = mountBar();

      await wrapper.find('.console-tab-item').trigger('contextmenu');
      expect(document.body.querySelector('.console-tab-context-menu')).not.toBeNull();

      document.body.dispatchEvent(new MouseEvent('contextmenu', { bubbles: true }));
      await wrapper.vm.$nextTick();
      expect(document.body.querySelector('.console-tab-context-menu')).toBeNull();

      wrapper.unmount();
    });

    it('re-targets the menu when right-clicking another tab while it is open', async () => {
      tabsStore.createTab({ title: 'Tab 2' });
      const wrapper = mountBar();

      const tabItems = wrapper.findAll('.console-tab-item');
      await tabItems[0].trigger('contextmenu');
      await tabItems[1].trigger('contextmenu');

      const menus = document.body.querySelectorAll('.console-tab-context-menu');
      expect(menus.length).toBe(1);
      // Targeting the rightmost tab disables "close to the right"
      expect(menus[0].querySelector('.btn-close-right').classList.contains('disabled')).toBe(true);

      wrapper.unmount();
    });

    it('renames the right-clicked tab (not necessarily the active one)', async () => {
      const tab1 = tabsStore.activeTab;
      tabsStore.createTab({ title: 'Tab 2' });
      const wrapper = mountBar();

      const tabItems = wrapper.findAll('.console-tab-item');
      await tabItems[0].trigger('contextmenu');
      document.body
        .querySelector('.console-tab-context-menu .btn-rename')
        .dispatchEvent(new MouseEvent('click', { bubbles: true }));
      await wrapper.vm.$nextTick();

      expect(tabsStore.activeTabId).toBe(tab1.id);
      const inputs = wrapper.findAll('.tab-title-input');
      expect(inputs.length).toBe(1);
      expect(wrapper.findAll('.console-tab-item')[0].find('.tab-title-input').exists()).toBe(true);

      wrapper.unmount();
    });

    it('flips the menu to the left of the cursor near the right viewport edge', async () => {
      const rectSpy = jest
        .spyOn(Element.prototype, 'getBoundingClientRect')
        .mockReturnValue({ width: 200, height: 100, top: 0, left: 0, right: 200, bottom: 100 });
      const wrapper = mountBar();

      const clientX = window.innerWidth - 20;
      await wrapper.find('.console-tab-item').trigger('contextmenu', { clientX, clientY: 40 });
      await wrapper.vm.$nextTick();

      const menu = document.body.querySelector('.console-tab-context-menu');
      expect(menu.style.left).toBe(`${clientX - 200}px`);

      wrapper.unmount();
      rectSpy.mockRestore();
    });

    it('clamps the menu inside the viewport with a 4px safe margin', async () => {
      const rectSpy = jest
        .spyOn(Element.prototype, 'getBoundingClientRect')
        .mockReturnValue({ width: 200, height: 100, top: 0, left: 0, right: 200, bottom: 100 });
      const wrapper = mountBar();

      // Cursor near the top-left: must not be placed closer than 4px to the edges
      await wrapper.find('.console-tab-item').trigger('contextmenu', { clientX: 0, clientY: 0 });
      await wrapper.vm.$nextTick();
      let menu = document.body.querySelector('.console-tab-context-menu');
      expect(menu.style.left).toBe('4px');
      expect(menu.style.top).toBe('4px');

      // Cursor near the bottom edge: flips upwards and stays within the viewport
      await wrapper.find('.console-tab-item').trigger('contextmenu', {
        clientX: 100,
        clientY: window.innerHeight - 5,
      });
      await wrapper.vm.$nextTick();
      menu = document.body.querySelector('.console-tab-context-menu');
      expect(menu.style.top).toBe(`${window.innerHeight - 5 - 100}px`);

      wrapper.unmount();
      rectSpy.mockRestore();
    });
  });

  describe('Horizontal scroll ergonomics', () => {
    it('applies has-horizontal-scroll class on scroll wrapper when isOverflowing is true', async () => {
      const wrapper = mount(ConsoleTabBar, {
        global: {
          plugins: [pinia],
          mocks: {
            __: (str) => str,
          },
        },
      });

      const scrollWrapper = wrapper.find('.console-tabbar-scroll-wrapper');
      expect(scrollWrapper.classes()).not.toContain('has-horizontal-scroll');

      // Simulate overflow
      Object.defineProperty(scrollWrapper.element, 'scrollWidth', { value: 1000, configurable: true });
      Object.defineProperty(scrollWrapper.element, 'clientWidth', { value: 300, configurable: true });

      // Trigger scroll or re-check
      await scrollWrapper.trigger('scroll');
      expect(scrollWrapper.classes()).toContain('has-horizontal-scroll');

      // Overflow resolved: class is removed again
      Object.defineProperty(scrollWrapper.element, 'scrollWidth', { value: 200, configurable: true });
      await scrollWrapper.trigger('scroll');
      expect(scrollWrapper.classes()).not.toContain('has-horizontal-scroll');

      wrapper.unmount();
    });

    afterEach(() => {
      jest.useRealTimers();
    });

    it('triggers scroll to end when a new tab is added', async () => {
      jest.useFakeTimers();

      const wrapper = mount(ConsoleTabBar, {
        global: {
          plugins: [pinia],
          mocks: {
            __: (str) => str,
          },
        },
      });

      const scrollWrapper = wrapper.find('.console-tabbar-scroll-wrapper');
      const scrollToSpy = jest.fn();
      scrollWrapper.element.scrollTo = scrollToSpy;
      Object.defineProperty(scrollWrapper.element, 'scrollWidth', { value: 850, configurable: true });

      const addBtn = wrapper.find('.console-tab-add-btn.btn-add-main');
      await addBtn.trigger('click');

      expect(tabsStore.tabCount).toBe(2);

      // Fast-forward timeout
      jest.advanceTimersByTime(100);

      expect(scrollToSpy).toHaveBeenCalledWith({ left: 850, behavior: 'smooth' });

      wrapper.unmount();
    });

    it('cancels the pending scroll-to-end timeout on unmount', async () => {
      jest.useFakeTimers();

      const wrapper = mount(ConsoleTabBar, {
        global: {
          plugins: [pinia],
          mocks: {
            __: (str) => str,
          },
        },
      });

      const scrollWrapper = wrapper.find('.console-tabbar-scroll-wrapper');
      const scrollToSpy = jest.fn();
      scrollWrapper.element.scrollTo = scrollToSpy;

      await wrapper.find('.console-tab-add-btn.btn-add-main').trigger('click');
      wrapper.unmount();

      jest.advanceTimersByTime(100);
      expect(scrollToSpy).not.toHaveBeenCalled();
    });
  });
});
