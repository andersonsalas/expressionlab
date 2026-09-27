import { mount } from '@vue/test-utils';

jest.mock('../../../components/console/visualizations/GraphVisualization.vue', () => ({
  name: 'GraphVisualization',
  template: '<div class="mock-graph-visualization" />',
}));

import ScratchpadOutput from '../../../components/console/ScratchpadOutput.vue';
import * as client from '../../../lib/api/client.js';

jest.mock('../../../lib/api/client.js', () => ({
  handleClipboardCopy: jest.fn(),
}));

jest.mock('vue-json-pretty', () => ({
  name: 'VueJsonPretty',
  template: '<div class="vue-json-pretty-mock"></div>',
}));

describe('ScratchpadOutput.vue', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('renders empty state when no result is passed', () => {
    const wrapper = mount(ScratchpadOutput, {
      props: {
        result: null,
        loading: false,
      },
    });

    const emptyState = wrapper.find('.scratchpad-empty-state');
    expect(emptyState.exists()).toBe(true);
    expect(emptyState.find('.codicon.codicon-play').exists()).toBe(true);
    expect(wrapper.text()).toContain('Run an expression');
  });

  it('renders empty state in output tab when no messages were emitted', async () => {
    const wrapper = mount(ScratchpadOutput, {
      props: {
        result: {
          result: 'hello',
          messages: [],
          visualizations: [],
        },
        loading: false,
      },
    });

    const outputTabBtn = wrapper.findAll('.scratchpad-tab-btn').find(b => b.text().includes('Output'));
    expect(outputTabBtn).toBeTruthy();
    await outputTabBtn.trigger('click');
    await wrapper.vm.$nextTick();

    const emptyState = wrapper.find('.output-messages-content .scratchpad-empty-state');
    expect(emptyState.exists()).toBe(true);
    expect(emptyState.find('.codicon.codicon-info').exists()).toBe(true);
    expect(emptyState.text()).toContain('No deferred console messages available.');
  });

  it('shows loading spinner when loading is true', () => {
    const wrapper = mount(ScratchpadOutput, {
      props: {
        result: null,
        loading: true,
      },
    });

    expect(wrapper.find('.scratchpad-output-loading').exists()).toBe(true);
    expect(wrapper.text()).toContain('Evaluating expression...');
  });

  it('renders raw scalar output properly with syntax highlighting', () => {
    const wrapper = mount(ScratchpadOutput, {
      props: {
        result: {
          result: 42,
          messages: [],
          visualizations: [],
          type: null,
        },
        loading: false,
      },
    });

    expect(wrapper.find('.entry.out.mode-evaluate pre.hljs').exists()).toBe(true);
    expect(wrapper.find('.entry.out.mode-evaluate pre.hljs').html()).toContain('hljs-number');
    expect(wrapper.text()).toContain('42');
  });

  it('renders string, boolean and null scalars with proper syntax highlighting', () => {
    const stringWrapper = mount(ScratchpadOutput, {
      props: {
        result: {
          result: '\'Hola mundo\'',
          messages: [],
          visualizations: [],
          type: null,
        },
        loading: false,
      },
    });
    expect(stringWrapper.find('pre.hljs').html()).toContain('hljs-string');
    expect(stringWrapper.text()).toContain('Hola mundo');

    const boolWrapper = mount(ScratchpadOutput, {
      props: {
        result: {
          result: true,
          messages: [],
          visualizations: [],
          type: null,
        },
        loading: false,
      },
    });
    expect(boolWrapper.find('pre.hljs').html()).toContain('hljs-literal');
    expect(boolWrapper.text()).toContain('true');

    const nullWrapper = mount(ScratchpadOutput, {
      props: {
        result: {
          result: null,
          messages: [],
          visualizations: [],
          type: null,
        },
        loading: false,
      },
    });
    expect(nullWrapper.find('pre.hljs').html()).toContain('hljs-literal');
    expect(nullWrapper.text()).toContain('null');
  });

  it('renders error entry when type is error', () => {
    const wrapper = mount(ScratchpadOutput, {
      props: {
        result: {
          result: 'Syntax error on line 1',
          type: 'error',
          messages: [],
          visualizations: [],
        },
        loading: false,
      },
    });

    expect(wrapper.find('.entry.out.error').exists()).toBe(true);
    expect(wrapper.text()).toContain('Syntax error on line 1');
  });

  it('switches to output tab when only messages are present and result is null', async () => {
    const wrapper = mount(ScratchpadOutput, {
      props: {
        result: {
          result: null,
          messages: [
            { type: 'log', text: 'Processing node 1' },
            { type: 'warning', text: 'Slow query detected' },
          ],
          visualizations: [],
        },
      },
    });

    await wrapper.vm.$nextTick();
    expect(wrapper.find('.scratchpad-tab-btn.active').text()).toContain('Output');
    expect(wrapper.findAll('.entry.out').length).toBe(2);
    expect(wrapper.text()).toContain('Processing node 1');
    expect(wrapper.text()).toContain('Slow query detected');
  });

  it('emits clear-output and close events on button clicks', async () => {
    const wrapper = mount(ScratchpadOutput, {
      props: {
        result: { result: 'test' },
      },
    });

    const actionButtons = wrapper.findAll('.output-action-btn');
    expect(actionButtons.length).toBeGreaterThanOrEqual(3);

    // Clear output button
    await actionButtons[1].trigger('click');
    expect(wrapper.emitted('clear-output')).toBeTruthy();

    // Close button
    await actionButtons[2].trigger('click');
    expect(wrapper.emitted('close')).toBeTruthy();
  });

  it('copies output to clipboard when copy button is clicked', async () => {
    const wrapper = mount(ScratchpadOutput, {
      props: {
        result: { result: 'Hello World' },
      },
    });

    const copyBtn = wrapper.find('.output-action-btn');
    await copyBtn.trigger('click');

    expect(client.handleClipboardCopy).toHaveBeenCalledWith('Hello World');
  });
});
