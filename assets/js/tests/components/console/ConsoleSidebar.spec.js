import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import ConsoleSidebar from '../../../components/console/ConsoleSidebar.vue';
import { useTabsStore } from '../../../stores/tabs.js';

describe('ConsoleSidebar.vue', () => {
  const mockOutlineTree = [
    {
      label: 'Functions',
      kind: 'Category',
      icon: 'codicon-library',
      expanded: true,
      methods: [
        { label: 'rand', kind: 'Function', insertText: 'rand()' },
        { label: 'time', kind: 'Function', insertText: 'time()' },
      ],
    },
    {
      label: 'Database',
      kind: 'Object',
      icon: 'codicon-symbol-class',
      expanded: false,
      methods: [
        { label: 'query', kind: 'Method', insertText: 'query()' },
        { label: 'connect', kind: 'Method', insertText: 'connect()' },
      ],
    },
    {
      label: 'Constants',
      kind: 'Category',
      icon: 'codicon-symbol-constant',
      expanded: true,
      methods: [
        { label: 'PHP_VERSION', kind: 'Constant', insertText: 'PHP_VERSION' },
      ],
    },
  ];

  it('renders outline tree with categories and objects', () => {
    const wrapper = mount(ConsoleSidebar, {
      props: {
        outlineTree: mockOutlineTree,
        loading: false,
      },
    });

    expect(wrapper.text()).toContain('Functions');
    expect(wrapper.text()).toContain('Database');
    expect(wrapper.text()).toContain('Constants');
  });

  it('filters items in real time when typing in search input', async () => {
    const wrapper = mount(ConsoleSidebar, {
      props: {
        outlineTree: mockOutlineTree,
        loading: false,
      },
    });

    const searchInput = wrapper.find('input[type="text"]');
    expect(searchInput.exists()).toBe(true);

    await searchInput.setValue('query');

    // Database.query should be matched
    expect(wrapper.text()).toContain('Database');
    expect(wrapper.text()).toContain('query');
    // time should be filtered out
    expect(wrapper.text()).not.toContain('time');
  });

  it('emits item-click when a method item is clicked', async () => {
    const wrapper = mount(ConsoleSidebar, {
      props: {
        outlineTree: mockOutlineTree,
        loading: false,
      },
    });

    const methodItem = wrapper.findAll('.outline-item').filter((w) => w.text().includes('rand'))[0];
    expect(methodItem).toBeDefined();

    await methodItem.trigger('click');

    expect(wrapper.emitted('item-click')).toBeTruthy();
    expect(wrapper.emitted('item-click')[0][0].label).toBe('rand');
  });

  it('toggles node expansion on click', async () => {
    const wrapper = mount(ConsoleSidebar, {
      props: {
        outlineTree: mockOutlineTree,
        loading: false,
      },
    });

    const dbHeader = wrapper.findAll('.outline-item.class').filter((w) => w.text().includes('Database'))[0];
    expect(dbHeader).toBeDefined();

    // Check chevron changes or sub-list toggles
    const chevron = dbHeader.find('.codicon');
    const wasExpanded = chevron.classes().includes('codicon-chevron-down');

    await dbHeader.trigger('click');

    const isNowExpanded = chevron.classes().includes('codicon-chevron-down');
    expect(isNowExpanded).toBe(!wasExpanded);
  });

  it('manages tooltip visibility with grace period and shift key', async () => {
    jest.useFakeTimers();

    const mockOutlineWithDoc = [
      {
        label: 'Database',
        kind: 'Object',
        icon: 'codicon-symbol-class',
        expanded: true,
        methods: [
          {
            label: 'query',
            kind: 'Method',
            insertText: 'query()',
            doc: { summary: 'Queries DB', description: 'Run query' },
          },
        ],
      },
    ];

    const wrapper = mount(ConsoleSidebar, {
      props: {
        outlineTree: mockOutlineWithDoc,
        loading: false,
      },
      attachTo: document.body,
    });

    const methodItem = wrapper.find('.outline-item.method');
    expect(methodItem.exists()).toBe(true);

    // Mouse enter triggers tooltip
    await methodItem.trigger('mouseenter');
    expect(document.querySelector('.console-sidebar-tooltip')).not.toBeNull();

    // Mouse leave schedules close after grace period (250ms)
    await methodItem.trigger('mouseleave');
    expect(document.querySelector('.console-sidebar-tooltip')).not.toBeNull();

    // Advance 100ms: still open
    jest.advanceTimersByTime(100);
    expect(document.querySelector('.console-sidebar-tooltip')).not.toBeNull();

    // Advance remaining 150ms: closed
    jest.advanceTimersByTime(150);
    await wrapper.vm.$nextTick();
    expect(document.querySelector('.console-sidebar-tooltip')).toBeNull();

    // Test Shift key pinning:
    await methodItem.trigger('mouseenter');
    expect(document.querySelector('.console-sidebar-tooltip')).not.toBeNull();

    // Press Shift
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Shift' }));
    await methodItem.trigger('mouseleave');

    // Advance past grace period (500ms): should still be pinned!
    jest.advanceTimersByTime(500);
    await wrapper.vm.$nextTick();
    expect(document.querySelector('.console-sidebar-tooltip')).not.toBeNull();

    // Release Shift
    window.dispatchEvent(new KeyboardEvent('keyup', { key: 'Shift' }));
    jest.advanceTimersByTime(250);
    await wrapper.vm.$nextTick();
    expect(document.querySelector('.console-sidebar-tooltip')).toBeNull();

    wrapper.unmount();
    jest.useRealTimers();
  });

  it('automatically closes tooltip on snippet insert, but keeps it open on copy', async () => {
    const mockOutlineWithDoc = [
      {
        label: 'Database',
        kind: 'Object',
        icon: 'codicon-symbol-class',
        expanded: true,
        methods: [
          {
            label: 'query',
            kind: 'Method',
            insertText: 'query()',
            doc: {
              summary: 'Queries DB',
              description: 'Example:\n\n```\nDatabase.query()\n```',
            },
          },
        ],
      },
    ];

    const wrapper = mount(ConsoleSidebar, {
      props: {
        outlineTree: mockOutlineWithDoc,
        loading: false,
      },
      attachTo: document.body,
    });

    const methodItem = wrapper.find('.outline-item.method');
    await methodItem.trigger('mouseenter');
    await wrapper.vm.$nextTick();

    const tooltip = document.querySelector('.console-sidebar-tooltip');
    expect(tooltip).not.toBeNull();

    // Clicking the copy button does not close the tooltip
    const copyBtn = tooltip.querySelector('.el-code-copy-btn');
    expect(copyBtn).not.toBeNull();
    copyBtn.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    await wrapper.vm.$nextTick();
    expect(document.querySelector('.console-sidebar-tooltip')).not.toBeNull();

    // Clicking the insert button closes the tooltip automatically
    const insertBtn = tooltip.querySelector('.el-code-insert-btn');
    expect(insertBtn).not.toBeNull();
    insertBtn.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    await wrapper.vm.$nextTick();
    expect(document.querySelector('.console-sidebar-tooltip')).toBeNull();

    wrapper.unmount();
  });

  describe('Tabs and Notebook Outline (Pro Addon integration)', () => {
    let pinia;
    let tabsStore;
    const prevAddons = global.__WITH_ADDONS__;

    beforeEach(() => {
      pinia = createPinia();
      setActivePinia(pinia);
      tabsStore = useTabsStore();
    });

    afterEach(() => {
      global.__WITH_ADDONS__ = prevAddons;
    });

    it('does not render sidebar tabs when Pro Addon is not included', () => {
      global.__WITH_ADDONS__ = false;

      const wrapper = mount(ConsoleSidebar, {
        props: {
          outlineTree: mockOutlineTree,
          loading: false,
        },
        global: {
          plugins: [pinia],
        },
      });

      expect(wrapper.find('.sidebar-tabbar').exists()).toBe(false);
      expect(wrapper.find('#sidebar-outline').isVisible()).toBe(true);
    });

    it('renders two tabs distributed 50%-50% when Pro Addon is included', () => {
      global.__WITH_ADDONS__ = true;

      const wrapper = mount(ConsoleSidebar, {
        props: {
          outlineTree: mockOutlineTree,
          loading: false,
        },
        global: {
          plugins: [pinia],
        },
      });

      const tabbar = wrapper.find('.sidebar-tabbar');
      expect(tabbar.exists()).toBe(true);

      const tabs = wrapper.findAll('.sidebar-tab-item');
      expect(tabs).toHaveLength(2);
      expect(tabs[0].text()).toContain('Symbols');
      expect(tabs[1].text()).toContain('Outline');

      // Symbols is active by default
      expect(tabs[0].classes()).toContain('active');
      expect(tabs[1].classes()).not.toContain('active');
    });

    it('switches to Outline tab and shows empty state when no notebook is open', async () => {
      global.__WITH_ADDONS__ = true;

      // Active tab is REPL
      tabsStore.createTab({ mode: 'repl' });

      const wrapper = mount(ConsoleSidebar, {
        props: {
          outlineTree: mockOutlineTree,
          loading: false,
        },
        global: {
          plugins: [pinia],
        },
      });

      const tabs = wrapper.findAll('.sidebar-tab-item');
      await tabs[1].trigger('click');

      expect(tabs[1].classes()).toContain('active');
      const outlineSection = wrapper.find('#sidebar-notebook-outline');
      expect(outlineSection.exists()).toBe(true);
      expect(outlineSection.text()).toContain('No outline available');
    });

    it('renders hierarchical outline tree with codicon-bookmark when a notebook is open', async () => {
      global.__WITH_ADDONS__ = true;

      const tab = tabsStore.createTab({ mode: 'notebook' });
      tabsStore.updateTabNotebook(tab.id, {
        notebookMeta: {
          cells: [
            {
              id: 'c1',
              type: 'markdown',
              source: '# Introduction\nSome text\n## Prerequisites',
            },
            {
              id: 'c2',
              type: 'code',
              source: 'return 1;',
            },
            {
              id: 'c3',
              type: 'markdown',
              source: '# Conclusion',
            },
          ],
        },
      });

      const wrapper = mount(ConsoleSidebar, {
        props: {
          outlineTree: mockOutlineTree,
          loading: false,
        },
        global: {
          plugins: [pinia],
        },
      });

      // Switch to Outline tab
      const tabButtons = wrapper.findAll('.sidebar-tab-item');
      await tabButtons[1].trigger('click');

      const outlineSection = wrapper.find('#sidebar-notebook-outline');
      expect(outlineSection.exists()).toBe(true);

      // Verify headings
      expect(outlineSection.text()).toContain('Introduction');
      expect(outlineSection.text()).toContain('Prerequisites');
      expect(outlineSection.text()).toContain('Conclusion');

      // Verify bookmark icons
      const bookmarkIcons = outlineSection.findAll('.codicon-bookmark');
      expect(bookmarkIcons.length).toBe(3);
    });

    it('scrolls smoothly to cell when a heading item is clicked', async () => {
      global.__WITH_ADDONS__ = true;

      const tab = tabsStore.createTab({ mode: 'notebook' });
      tabsStore.updateTabNotebook(tab.id, {
        notebookMeta: {
          cells: [
            {
              id: 'cell-md-target',
              type: 'markdown',
              source: '# Target Heading',
            },
          ],
        },
      });

      const targetCellEl = document.createElement('div');
      targetCellEl.id = 'notebook-cell-cell-md-target';
      targetCellEl.className = 'notebook-cell';
      targetCellEl.scrollIntoView = jest.fn();
      document.body.appendChild(targetCellEl);

      const wrapper = mount(ConsoleSidebar, {
        props: {
          outlineTree: mockOutlineTree,
          loading: false,
        },
        global: {
          plugins: [pinia],
        },
        attachTo: document.body,
      });

      const tabButtons = wrapper.findAll('.sidebar-tab-item');
      await tabButtons[1].trigger('click');

      const headingItem = wrapper.find('.heading-item');
      expect(headingItem.exists()).toBe(true);

      await headingItem.trigger('click');

      expect(targetCellEl.scrollIntoView).toHaveBeenCalledWith(
        expect.objectContaining({ behavior: 'smooth' })
      );

      document.body.removeChild(targetCellEl);
      wrapper.unmount();
    });

    it('automatically updates outline tree when markdown cells update', async () => {
      global.__WITH_ADDONS__ = true;

      const tab = tabsStore.createTab({ mode: 'notebook' });
      tabsStore.updateTabNotebook(tab.id, {
        notebookMeta: {
          cells: [
            {
              id: 'c1',
              type: 'markdown',
              source: '# Original Title',
            },
          ],
        },
      });

      const wrapper = mount(ConsoleSidebar, {
        props: {
          outlineTree: mockOutlineTree,
          loading: false,
        },
        global: {
          plugins: [pinia],
        },
      });

      const tabButtons = wrapper.findAll('.sidebar-tab-item');
      await tabButtons[1].trigger('click');

      expect(wrapper.find('#sidebar-notebook-outline').text()).toContain('Original Title');

      // Simulate saving markdown cell and switching to preview
      tabsStore.updateTabNotebook(tab.id, {
        notebookMeta: {
          cells: [
            {
              id: 'c1',
              type: 'markdown',
              source: '# Updated Title\n## New Subsection',
            },
          ],
        },
      });
      await wrapper.vm.$nextTick();

      expect(wrapper.find('#sidebar-notebook-outline').text()).toContain('Updated Title');
      expect(wrapper.find('#sidebar-notebook-outline').text()).toContain('New Subsection');
    });
  });
});
