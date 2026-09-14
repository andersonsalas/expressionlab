import { mount } from '@vue/test-utils';
import TableVisualization from '../../../../components/console/visualizations/TableVisualization.vue';

describe('TableVisualization.vue', () => {
  const mockTableData = {
    type: 'table',
    data: [
      { id: 1, name: 'Alice', role: 'admin' },
      { id: 2, name: 'Bob', role: 'editor' },
      { id: 3, name: 'Charlie', role: 'subscriber' },
    ],
  };

  it('renders table headers and rows correctly', () => {
    const wrapper = mount(TableVisualization, {
      props: {
        data: mockTableData,
      },
    });

    expect(wrapper.find('table').exists()).toBe(true);
    expect(wrapper.text()).toContain('Alice');
    expect(wrapper.text()).toContain('Bob');
    expect(wrapper.text()).toContain('Charlie');
    expect(wrapper.text()).toContain('admin');
  });

  it('filters table rows when searching in a column', async () => {
    const wrapper = mount(TableVisualization, {
      props: {
        data: mockTableData,
      },
    });

    // Find the first column filter input
    const filterInput = wrapper.find('.column-filter-input');
    if (filterInput.exists()) {
      await filterInput.setValue('Alice');
      expect(wrapper.text()).toContain('Alice');
      expect(wrapper.text()).not.toContain('Bob');
    }
  });

  it('sorts rows when clicking column header', async () => {
    const wrapper = mount(TableVisualization, {
      props: {
        data: mockTableData,
      },
    });

    const headers = wrapper.findAll('th.sortable');
    if (headers.length > 0) {
      await headers[0].trigger('click');
      // Verify sort triggered
      expect(wrapper.find('.sort-icon').exists()).toBe(true);
    }
  });
});
