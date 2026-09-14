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

  it('renders JSON pretty view for non-scalar objects', () => {
    const wrapper = mount(VisualizationRenderer, {
      props: {
        item: { user: 'Alice', active: true },
      },
    });

    expect(wrapper.find('.vjs-tree').exists()).toBe(true);
  });

  it('renders visualization tabs when visualizations are provided', async () => {
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
    expect(tabs.length).toBeGreaterThanOrEqual(2); // Raw tab + User Table tab
    expect(wrapper.text()).toContain('Raw');
    expect(wrapper.text()).toContain('User Table');

    // Initial watcher emission when hasViz is true
    expect(wrapper.emitted('update:modelValue')[0]).toEqual([0]);

    // Click Raw tab
    await tabs[0].trigger('click');

    // Second emission when switching to Raw (json)
    expect(wrapper.emitted('update:modelValue')[1]).toEqual(['json']);
  });
});
