import { mount } from '@vue/test-utils';
import { createTestingPinia } from '@pinia/testing';
import TableVisualization from '../../../../components/console/visualizations/TableVisualization.vue';
import { useUiStore } from '../../../../stores/ui';
import * as clientApi from '../../../../lib/api/client';

describe('TableVisualization.vue', () => {
  const mockTableData = {
    type: 'table',
    data: [
      { id: 1, name: 'Alice', role: 'admin' },
      { id: 2, name: 'Bob', role: 'editor' },
      { id: 3, name: 'Charlie', role: 'subscriber' },
    ],
  };

  let wrapper;

  const mountTable = (props = {}) => {
    const pinia = createTestingPinia({
      stubActions: false,
    });

    wrapper = mount(TableVisualization, {
      props: {
        data: mockTableData,
        ...props,
      },
      global: {
        plugins: [pinia],
      },
      attachTo: document.body,
    });
    return wrapper;
  };

  beforeEach(() => {
    jest.spyOn(clientApi, 'handleClipboardCopy').mockResolvedValue(true);
  });

  afterEach(() => {
    if (wrapper) {
      wrapper.unmount();
      wrapper = null;
    }
    document.body.innerHTML = '';
    jest.restoreAllMocks();
  });

  it('renders table headers and rows correctly', () => {
    const w = mountTable();

    expect(w.find('table').exists()).toBe(true);
    expect(w.text()).toContain('Alice');
    expect(w.text()).toContain('Bob');
    expect(w.text()).toContain('Charlie');
    expect(w.text()).toContain('admin');
  });

  it('selects cell text on left click without expanding', async () => {
    const w = mountTable();
    const cell = w.find('td');

    await cell.trigger('click');

    // Should NOT have an expanded class
    expect(cell.find('.inner-scroll').classes()).not.toContain('expanded');
  });

  it('opens context menu on right click with View data and Copy options', async () => {
    const w = mountTable();
    const cell = w.find('td');

    await cell.trigger('contextmenu', {
      clientX: 100,
      clientY: 150,
    });

    const menu = document.body.querySelector('.table-cell-context-menu');
    expect(menu).not.toBeNull();
    expect(menu.textContent).toContain('View data...');
    expect(menu.textContent).toContain('Copy cell value');
  });

  it('opens DataViewerModal when "View data..." is clicked in context menu', async () => {
    const w = mountTable();
    const uiStore = useUiStore();

    const cell = w.findAll('td')[1]; // 'name' column ('Alice')
    await cell.trigger('contextmenu', {
      clientX: 100,
      clientY: 150,
    });

    const viewDataBtn = document.body.querySelector('.btn-view-data');
    expect(viewDataBtn).not.toBeNull();
    viewDataBtn.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    await w.vm.$nextTick();

    expect(uiStore.activeModal).toBe('data-viewer');
    expect(uiStore.activeModalData).toEqual(expect.objectContaining({
      column: 'name',
      content: 'Alice',
    }));
  });

  it('copies cell raw value when "Copy cell value" is clicked in context menu', async () => {
    const w = mountTable();
    const cell = w.findAll('td')[1]; // 'name' column ('Alice')

    await cell.trigger('contextmenu', {
      clientX: 100,
      clientY: 150,
    });

    const copyBtn = document.body.querySelector('.btn-copy-cell');
    expect(copyBtn).not.toBeNull();
    copyBtn.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    await w.vm.$nextTick();

    expect(clientApi.handleClipboardCopy).toHaveBeenCalledWith('Alice');
  });

  it('defaults page size to 10 when defaultPageSize is omitted', () => {
    const wrapper = mountTable();
    const select = wrapper.find('.page-size-select');
    expect(select.element.value).toBe('10');
  });

  it('supports custom defaultPageSize such as 100', () => {
    const wrapper = mountTable({ defaultPageSize: 100 });
    const select = wrapper.find('.page-size-select');
    expect(select.element.value).toBe('100');
  });

  describe('selection and context menu ergonomics', () => {
    const getMenu = () => document.body.querySelector('.table-cell-context-menu');
    const openMenu = async (w, cellIndex = 1, coords = { clientX: 100, clientY: 150 }) => {
      await w.findAll('td')[cellIndex].trigger('contextmenu', coords);
      await w.vm.$nextTick();
    };

    it('selects the full cell content on left click', async () => {
      const w = mountTable();
      window.getSelection().removeAllRanges();
      await w.findAll('td')[1].trigger('click');
      const selection = window.getSelection();
      expect(selection.rangeCount).toBe(1);
      expect(selection.getRangeAt(0).toString().trim()).toBe('Alice');
    });

    it('teleports the menu to <body>', async () => {
      const w = mountTable();
      await openMenu(w);
      expect(getMenu().parentElement).toBe(document.body);
    });

    it('flips and clamps the menu inside the viewport', async () => {
      const w = mountTable();
      await openMenu(w, 1, { clientX: window.innerWidth - 4, clientY: window.innerHeight - 8 });
      const menu = getMenu();
      const left = parseFloat(menu.style.left);
      const top = parseFloat(menu.style.top);
      expect(left).toBeLessThanOrEqual(window.innerWidth - 180 - 4);
      expect(top).toBeLessThanOrEqual(window.innerHeight - 70 - 4);

      await openMenu(w, 1, { clientX: 0, clientY: 0 });
      expect(getMenu().style.left).toBe('4px');
      expect(getMenu().style.top).toBe('4px');
    });

    it.each([
      ['Escape', () => window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))],
      ['outside click', () => document.body.dispatchEvent(new MouseEvent('click', { bubbles: true }))],
      ['outside right-click', () => document.body.dispatchEvent(new MouseEvent('contextmenu', { bubbles: true }))],
      ['window resize', () => window.dispatchEvent(new Event('resize'))],
      ['ancestor scroll', () => document.body.dispatchEvent(new Event('scroll'))],
      ['table scroll', () => document.body.querySelector('.table-scroll-wrapper').dispatchEvent(new Event('scroll'))],
    ])('dismisses the menu on %s', async (_label, dismiss) => {
      const w = mountTable();
      await openMenu(w);
      expect(getMenu()).not.toBeNull();
      dismiss();
      await w.vm.$nextTick();
      expect(getMenu()).toBeNull();
    });

    it('keeps the menu open on a right-click inside it', async () => {
      const w = mountTable();
      await openMenu(w);
      getMenu().dispatchEvent(new MouseEvent('contextmenu', { bubbles: true }));
      await w.vm.$nextTick();
      expect(getMenu()).not.toBeNull();
    });

    it('re-targets the menu when right-clicking another cell', async () => {
      const w = mountTable();
      const uiStore = useUiStore();
      await openMenu(w, 1);
      await openMenu(w, 4, { clientX: 200, clientY: 200 }); // 'Bob'
      expect(getMenu()).not.toBeNull();

      getMenu().querySelector('.btn-view-data').dispatchEvent(new MouseEvent('click', { bubbles: true }));
      await w.vm.$nextTick();
      expect(uiStore.activeModalData).toEqual(expect.objectContaining({ content: 'Bob', column: 'name' }));
    });

    it('removes capture-phase listeners on unmount', () => {
      const w = mountTable();
      const spy = jest.spyOn(window, 'removeEventListener');
      w.unmount();
      wrapper = null;
      expect(spy).toHaveBeenCalledWith('contextmenu', expect.any(Function), true);
      expect(spy).toHaveBeenCalledWith('scroll', expect.any(Function), true);
    });
  });
});
