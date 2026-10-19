import { mount } from '@vue/test-utils';

jest.mock('../../../../components/console/visualizations/GraphVisualization.vue', () => ({
  name: 'GraphVisualization',
  template: '<div class="mock-graph-visualization" />',
}));

import VisualizationRenderer from '../../../../components/console/visualizations/VisualizationRenderer.vue';

describe('VisualizationRenderer.vue', () => {
  it('renders scalar values directly', () => {
    const wrapper = mount(VisualizationRenderer, {
      props: {
        item: 'Hello World',
      },
    });

    expect(wrapper.text()).toContain('Hello World');
  });

  it('renders DataTree directly for non-scalar objects in compact console mode', () => {
    const wrapper = mount(VisualizationRenderer, {
      props: {
        item: { user: 'Alice', active: true },
      },
    });

    expect(wrapper.find('.data-tree-container').exists()).toBe(true);
    expect(wrapper.find('.visualization-tabs').exists()).toBe(false);
  });

  it('renders DataTree directly for serialized PHP string literals in compact console mode', () => {
    const wrapper = mount(VisualizationRenderer, {
      props: {
        item: '\'a:1:{s:4:"user";s:5:"Alice";}\'',
      },
    });

    expect(wrapper.find('.data-tree-container').exists()).toBe(true);
    expect(wrapper.find('.visualization-tabs').exists()).toBe(false);
  });

  it('renders Preview, Raw, and visualization tabs when visualizations are provided', async () => {
    const wrapper = mount(VisualizationRenderer, {
      props: {
        item: {
          result: [{ id: 1, name: 'Alice' }],
          visualizations: [
            {
              type: 'table',
              title: 'User Table',
              data: [{ id: 1, name: 'Alice' }],
            },
          ],
        },
      },
    });

    const tabs = wrapper.findAll('.visualization-tabs li');
    expect(tabs.length).toBe(3); // Raw + Preview + User Table
    expect(tabs[0].text()).toBe('Raw');
    expect(tabs[1].text()).toBe('Preview');
    expect(tabs[2].text()).toBe('User Table');

    // Initial watcher emission when hasViz is true (defaults to visualization 0)
    expect(wrapper.emitted('update:modelValue')[0]).toEqual([0]);

    // Click Raw tab
    await tabs[0].trigger('click');
    expect(wrapper.emitted('update:modelValue')[1]).toEqual(['raw']);
    expect(wrapper.find('pre.hljs').exists()).toBe(true);

    // Click Preview tab
    await tabs[1].trigger('click');
    expect(wrapper.emitted('update:modelValue')[2]).toEqual(['preview']);
    expect(wrapper.findComponent({ name: 'DataTree' }).exists()).toBe(true);
  });

  it('does not display expand controls when raw content height <= 400px', async () => {
    const wrapper = mount(VisualizationRenderer, {
      props: {
        item: {
          result: 'Short raw text',
          visualizations: [
            { type: 'table', title: 'Table', data: [] },
          ],
        },
      },
      attachTo: document.body,
    });

    const rawTab = wrapper.findAll('.visualization-tabs li')[0];
    await rawTab.trigger('click');

    const pre = wrapper.find('pre.hljs');
    expect(pre.exists()).toBe(true);
    Object.defineProperty(pre.element, 'scrollHeight', { value: 120, configurable: true });

    window.dispatchEvent(new Event('resize'));
    await wrapper.vm.$nextTick();

    expect(wrapper.find('.raw-expand-btn').exists()).toBe(false);
    expect(wrapper.find('.raw-fade-overlay').exists()).toBe(false);
    expect(wrapper.find('.raw-code-wrapper').classes()).not.toContain('is-collapsed');
    wrapper.unmount();
  });

  it('caps at 400px and renders expand/collapse button when raw content overflows > 400px', async () => {
    Element.prototype.scrollIntoView = jest.fn();

    const wrapper = mount(VisualizationRenderer, {
      props: {
        item: {
          result: 'Very long raw payload text...'.repeat(50),
          visualizations: [
            { type: 'table', title: 'Table', data: [] },
          ],
        },
      },
      attachTo: document.body,
    });

    const rawTab = wrapper.findAll('.visualization-tabs li')[0];
    await rawTab.trigger('click');

    const pre = wrapper.find('pre.hljs');
    expect(pre.exists()).toBe(true);
    Object.defineProperty(pre.element, 'scrollHeight', { value: 1200, configurable: true });

    window.dispatchEvent(new Event('resize'));
    await wrapper.vm.$nextTick();

    const wrapperEl = wrapper.find('.raw-code-wrapper');
    expect(wrapperEl.classes()).toContain('is-collapsed');
    expect(wrapper.find('.raw-fade-overlay').exists()).toBe(true);

    const expandBtn = wrapper.find('.raw-expand-btn');
    expect(expandBtn.exists()).toBe(true);
    expect(expandBtn.classes()).toContain('php-root-btn');
    expect(expandBtn.find('.codicon-add').exists()).toBe(true);
    expect(expandBtn.text()).toContain('Expand all');

    // Click Expand
    await expandBtn.trigger('click');

    expect(wrapperEl.classes()).not.toContain('is-collapsed');
    expect(wrapper.find('.raw-fade-overlay').exists()).toBe(false);
    expect(expandBtn.find('.codicon-remove').exists()).toBe(true);
    expect(expandBtn.text()).toContain('Collapse');

    // Click Collapse
    await expandBtn.trigger('click');

    expect(wrapperEl.classes()).toContain('is-collapsed');
    expect(wrapper.find('.raw-fade-overlay').exists()).toBe(true);
    expect(expandBtn.find('.codicon-add').exists()).toBe(true);
    expect(Element.prototype.scrollIntoView).toHaveBeenCalled();

    wrapper.unmount();
  });
});
