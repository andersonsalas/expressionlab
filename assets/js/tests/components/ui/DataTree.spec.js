import { mount } from '@vue/test-utils';
import DataTree from '../../../components/ui/DataTree.vue';
import { parsePhpSerialized } from '../../../lib/php-serializer/parser.js';

describe('DataTree.vue', () => {
  it('renders raw JavaScript objects and arrays via data prop', () => {
    const wrapper = mount(DataTree, {
      props: {
        data: {
          username: 'Anderson',
          roles: ['administrator', 'developer'],
        },
      },
    });

    expect(wrapper.find('.data-tree-container').exists()).toBe(true);
    expect(wrapper.find('.php-class-name').text()).toBe('Object');
    expect(wrapper.text()).toContain('username');
    expect(wrapper.text()).toContain('"Anderson"');
    expect(wrapper.text()).toContain('roles');
  });

  it('renders PHP serialized AST via ast prop', () => {
    const sampleAst = {
      type: 'object',
      className: 'stdClass',
      propertyCount: 1,
      properties: [
        {
          name: 'title',
          visibility: null,
          value: { type: 'string', value: 'Expression Lab' },
        },
      ],
    };

    const wrapper = mount(DataTree, {
      props: {
        ast: sampleAst,
      },
    });

    expect(wrapper.find('.php-class-name').text()).toBe('stdClass');
    expect(wrapper.text()).toContain('title');
    expect(wrapper.text()).toContain('"Expression Lab"');
  });

  it('renders inline controls on root node when showControls is true', async () => {
    const wrapper = mount(DataTree, {
      props: {
        data: { a: 1 },
        showControls: true,
      },
    });

    const controls = wrapper.find('.php-root-controls');
    expect(controls.exists()).toBe(true);
    expect(controls.find('.btn-expand-all').exists()).toBe(true);
    expect(controls.find('.btn-collapse-all').exists()).toBe(true);

    await controls.find('.btn-expand-all').trigger('click');
    await controls.find('.btn-collapse-all').trigger('click');
  });

  it('does not render inline controls by default', () => {
    const wrapper = mount(DataTree, {
      props: {
        data: { a: 1 },
      },
    });

    expect(wrapper.find('.php-root-controls').exists()).toBe(false);
  });

  it('expands and collapses via exposed methods', async () => {
    const wrapper = mount(DataTree, {
      props: {
        data: {
          user: { name: 'Alice', address: { city: 'Caracas' } },
        },
        defaultExpandedDepth: 1,
      },
    });

    wrapper.vm.collapseAll();
    await wrapper.vm.$nextTick();
    expect(wrapper.find('.php-node-children').exists()).toBe(false);
    expect(wrapper.text()).not.toContain('Caracas');

    wrapper.vm.expandAll();
    await wrapper.vm.$nextTick();
    expect(wrapper.text()).toContain('"Caracas"');
  });

  it('clears a non-empty query on Escape without letting the event reach window listeners', async () => {
    const wrapper = mount(DataTree, {
      props: { data: { a: 'hello' }, showSearch: true },
      attachTo: document.body,
    });
    const windowListener = jest.fn();
    window.addEventListener('keydown', windowListener);

    const input = wrapper.find('.data-tree-search-input');
    await input.setValue('hello');
    await input.trigger('keydown', { key: 'Escape' });
    expect(wrapper.vm.searchQuery).toBe('');
    expect(windowListener).not.toHaveBeenCalled();

    // With an empty query Escape propagates (e.g. to close the Data Viewer modal).
    await input.trigger('keydown', { key: 'Escape' });
    expect(windowListener).toHaveBeenCalledTimes(1);

    window.removeEventListener('keydown', windowListener);
    wrapper.unmount();
  });

  it('renders JSON objects that look like AST nodes as plain objects', () => {
    const wrapper = mount(DataTree, {
      props: { data: { type: 'text', value: 'foo', name: 'email' } },
    });
    expect(wrapper.find('.php-class-name').text()).toBe('Object');
    expect(wrapper.text()).toContain('name');
    expect(wrapper.text()).toContain('"email"');
  });

  it('renders parsed PHP payloads passed through the data prop', () => {
    const ast = parsePhpSerialized('O:7:"WP_User":1:{s:6:"\0*\0cap";s:5:"admin";}');
    const wrapper = mount(DataTree, { props: { data: ast } });
    expect(wrapper.find('.php-class-name').text()).toBe('WP_User');
    expect(wrapper.find('.php-badge-visibility.protected').exists()).toBe(true);
  });

  it('renders bottom search bar and filters by text and path when showSearch is true', async () => {
    const wrapper = mount(DataTree, {
      props: {
        data: {
          username: 'Anderson',
          roles: ['administrator', 'developer'],
        },
        showSearch: true,
      },
    });

    const searchBar = wrapper.find('.data-tree-search-bar');
    expect(searchBar.exists()).toBe(true);

    const input = wrapper.find('.data-tree-search-input');
    expect(input.exists()).toBe(true);

    // Free text search
    await input.setValue('administrator');
    await wrapper.vm.$nextTick();
    expect(wrapper.text()).toContain('administrator');
    expect(wrapper.text()).toContain('1 match');

    // Switch to path mode
    const pathBtn = wrapper.find('.btn-mode-path');
    await pathBtn.trigger('click');
    await input.setValue('roles[1]');
    await wrapper.vm.$nextTick();
    expect(wrapper.text()).toContain('Path found');
    expect(wrapper.text()).toContain('"developer"');

    // Clear search
    await input.trigger('keydown.esc');
    await wrapper.vm.$nextTick();
    expect(wrapper.vm.searchQuery).toBe('');
  });

  it('renders .data-tree-no-matches empty state and adds .is-empty class when no matches are found', async () => {
    const wrapper = mount(DataTree, {
      props: {
        data: { a: 'hello' },
        showSearch: true,
      },
    });

    const input = wrapper.find('.data-tree-search-input');
    await input.setValue('nonexistent_value_xyz');
    await wrapper.vm.$nextTick();

    const noMatches = wrapper.find('.data-tree-no-matches');
    expect(noMatches.exists()).toBe(true);
    expect(noMatches.find('.codicon.codicon-search').exists()).toBe(true);
    expect(noMatches.text()).toContain('No matches found');
    expect(wrapper.find('.php-tree-content.is-empty').exists()).toBe(true);
  });

  it('does not render bottom search bar by default', () => {
    const wrapper = mount(DataTree, {
      props: {
        data: { a: 1 },
      },
    });

    expect(wrapper.find('.data-tree-search-bar').exists()).toBe(false);
  });

  describe('search debounce behavior', () => {
    beforeEach(() => {
      jest.useFakeTimers();
    });

    afterEach(() => {
      jest.useRealTimers();
    });

    it('filters immediately for datasets with node count at or below the threshold', async () => {
      const wrapper = mount(DataTree, {
        props: {
          data: { a: 'foo', b: 'bar' },
          showSearch: true,
          debounceThreshold: 20,
        },
      });

      expect(wrapper.vm.isLargeDataset).toBe(false);

      const input = wrapper.find('.data-tree-search-input');
      await input.setValue('foo');
      await wrapper.vm.$nextTick();

      expect(wrapper.vm.isDebouncing).toBe(false);
      expect(wrapper.vm.appliedQuery).toBe('foo');
      expect(wrapper.text()).toContain('1 match');
    });

    it('debounces search on datasets exceeding the threshold', async () => {
      const largeData = Array.from({ length: 30 }, (_, i) => ({
        id: i,
        name: `item_${i}`,
      }));

      const wrapper = mount(DataTree, {
        props: {
          data: largeData,
          showSearch: true,
          debounceThreshold: 20,
          debounceDelay: 250,
        },
      });

      expect(wrapper.vm.isLargeDataset).toBe(true);

      const input = wrapper.find('.data-tree-search-input');
      await input.setValue('item_15');
      await wrapper.vm.$nextTick();

      expect(wrapper.vm.isDebouncing).toBe(true);
      expect(wrapper.vm.appliedQuery).toBe('');
      expect(wrapper.find('.search-match-badge').text()).toContain('Searching...');

      jest.advanceTimersByTime(250);
      await wrapper.vm.$nextTick();

      expect(wrapper.vm.isDebouncing).toBe(false);
      expect(wrapper.vm.appliedQuery).toBe('item_15');
      expect(wrapper.find('.search-match-badge').text()).toContain('1 match');
      expect(wrapper.text()).toContain('item_15');
    });

    it('resets immediately without delay when search query is cleared', async () => {
      const largeData = Array.from({ length: 30 }, (_, i) => ({ id: i, name: `item_${i}` }));

      const wrapper = mount(DataTree, {
        props: {
          data: largeData,
          showSearch: true,
          debounceDelay: 250,
        },
      });

      const input = wrapper.find('.data-tree-search-input');
      await input.setValue('item_15');
      jest.advanceTimersByTime(250);
      await wrapper.vm.$nextTick();
      expect(wrapper.vm.appliedQuery).toBe('item_15');

      await input.setValue('');
      await wrapper.vm.$nextTick();
      expect(wrapper.vm.isDebouncing).toBe(false);
      expect(wrapper.vm.appliedQuery).toBe('');
    });
  });
});
