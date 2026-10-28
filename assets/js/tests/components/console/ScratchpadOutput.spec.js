import { mount } from '@vue/test-utils';
import { setActivePinia, createPinia } from 'pinia';

jest.mock('../../../components/console/visualizations/GraphVisualization.vue', () => ({
  name: 'GraphVisualization',
  template: '<div class="mock-graph-visualization" />',
}));

import ScratchpadOutput from '../../../components/console/ScratchpadOutput.vue';
import * as client from '../../../lib/api/client.js';
import { useUiStore } from '../../../stores/ui.js';

jest.mock('../../../lib/api/client.js', () => ({
  handleClipboardCopy: jest.fn(),
}));

describe('ScratchpadOutput.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
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

  it('renders DataTree for non-scalar results and switches between Preview and Raw tabs', async () => {
    const wrapper = mount(ScratchpadOutput, {
      props: {
        result: {
          result: [{ option_id: '1', option_name: 'cron' }],
          messages: [],
          visualizations: [],
          type: null,
        },
        loading: false,
      },
    });

    await wrapper.vm.$nextTick();

    // Default tab for structured previewable payload is preview
    expect(wrapper.find('.scratchpad-tab-btn.active').text()).toContain('Preview');
    const dataTree = wrapper.findComponent({ name: 'DataTree' });
    expect(dataTree.exists()).toBe(true);

    // Expand / collapse all buttons on preview tab
    const expandBtn = wrapper.find('.output-action-btn[title="Expand all"]');
    const collapseBtn = wrapper.find('.output-action-btn[title="Collapse all"]');
    expect(expandBtn.exists()).toBe(true);
    expect(collapseBtn.exists()).toBe(true);
    await expandBtn.trigger('click');
    await collapseBtn.trigger('click');

    // Switch to Raw tab
    const rawTabBtn = wrapper.findAll('.scratchpad-tab-btn').find(b => b.text().includes('Raw'));
    await rawTabBtn.trigger('click');
    await wrapper.vm.$nextTick();

    expect(wrapper.find('.scratchpad-tab-btn.active').text()).toContain('Raw');
    expect(wrapper.find('.raw-content pre.hljs').exists()).toBe(true);
    expect(wrapper.find('.raw-content pre.hljs').text()).toContain('"option_id": "1"');

    // Word wrap toggle on Raw tab
    const wordWrapBtn = wrapper.find('.output-action-btn[title="Word Wrap"]');
    expect(wordWrapBtn.exists()).toBe(true);
    expect(wrapper.find('.raw-content').classes()).not.toContain('has-word-wrap');
    await wordWrapBtn.trigger('click');
    expect(wrapper.find('.raw-content').classes()).toContain('has-word-wrap');
  });

  it('renders Preview tree and highlighted PHP in Raw for quoted serialized PHP strings', async () => {
    const wrapper = mount(ScratchpadOutput, {
      props: {
        result: {
          result: '\'a:1:{s:4:"host";s:9:"localhost";}\'',
          messages: [],
          visualizations: [],
          type: null,
        },
        loading: false,
      },
    });

    await wrapper.vm.$nextTick();

    // Default tab for serialized payload is preview
    expect(wrapper.find('.scratchpad-tab-btn.active').text()).toContain('Preview');
    expect(wrapper.findComponent({ name: 'DataTree' }).exists()).toBe(true);

    // Switch to Raw tab
    const rawTabBtn = wrapper.findAll('.scratchpad-tab-btn').find(b => b.text().includes('Raw'));
    await rawTabBtn.trigger('click');
    await wrapper.vm.$nextTick();

    expect(wrapper.find('.scratchpad-tab-btn.active').text()).toContain('Raw');
    const rawPre = wrapper.find('.raw-content pre.hljs');
    expect(rawPre.exists()).toBe(true);
    expect(rawPre.text()).toBe('a:1:{s:4:"host";s:9:"localhost";}');
    expect(rawPre.html()).toContain('hljs-string');
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

    const clearBtn = wrapper.find('.output-action-btn[title="Clear output"]');
    const closeBtn = wrapper.find('.output-action-btn[title="Hide output panel"]');

    expect(clearBtn.exists()).toBe(true);
    expect(closeBtn.exists()).toBe(true);

    await clearBtn.trigger('click');
    expect(wrapper.emitted('clear-output')).toBeTruthy();

    await closeBtn.trigger('click');
    expect(wrapper.emitted('close')).toBeTruthy();
  });

  it('copies output to clipboard when copy button is clicked', async () => {
    const wrapper = mount(ScratchpadOutput, {
      props: {
        result: { result: 'Hello World' },
      },
    });

    const copyBtn = wrapper.find('.output-action-btn[title="Copy to clipboard"]');
    expect(copyBtn.exists()).toBe(true);
    await copyBtn.trigger('click');

    expect(client.handleClipboardCopy).toHaveBeenCalledWith('Hello World');
  });

  it('renders table visualization with defaultPageSize of 100 in scratchpad', async () => {
    const wrapper = mount(ScratchpadOutput, {
      props: {
        result: {
          result: [{ id: 1, name: 'Alice' }],
          visualizations: [
            {
              type: 'table',
              title: 'Test Table',
              data: [{ id: 1, name: 'Alice' }],
            },
          ],
        },
        loading: false,
      },
    });

    await wrapper.vm.$nextTick();

    const tableViz = wrapper.findComponent({ name: 'TableVisualization' });
    expect(tableViz.exists()).toBe(true);
    expect(tableViz.props('defaultPageSize')).toBe(100);
    const select = wrapper.find('.page-size-select');
    expect(select.exists()).toBe(true);
    expect(select.element.value).toBe('100');
  });

  it('renders View Data button in actions bar and opens data-viewer modal for raw tab', async () => {
    const uiStore = useUiStore();
    jest.spyOn(uiStore, 'openModal');

    const wrapper = mount(ScratchpadOutput, {
      props: {
        result: {
          result: 12345,
          type: null,
          visualizations: [],
          messages: [],
        },
      },
    });

    const viewDataBtn = wrapper.find('.output-action-btn.view-data-btn');
    expect(viewDataBtn.exists()).toBe(true);
    expect(viewDataBtn.attributes('title')).toBe('View data...');
    expect(viewDataBtn.find('.codicon.codicon-open-in-window').exists()).toBe(true);

    await viewDataBtn.trigger('click');
    expect(uiStore.openModal).toHaveBeenCalledWith('data-viewer', {
      title: 'Raw Output',
      content: 12345,
      viewMode: 'raw',
    });
  });

  it('opens data-viewer modal with preview mode when on Preview tab', async () => {
    const uiStore = useUiStore();
    jest.spyOn(uiStore, 'openModal');

    const testData = [{ id: 1, name: 'Sample' }];
    const wrapper = mount(ScratchpadOutput, {
      props: {
        result: {
          result: testData,
          type: null,
          visualizations: [],
          messages: [],
        },
      },
    });

    await wrapper.vm.$nextTick();
    expect(wrapper.find('.scratchpad-tab-btn.active').text()).toContain('Preview');

    const viewDataBtn = wrapper.find('.output-action-btn.view-data-btn');
    await viewDataBtn.trigger('click');

    expect(uiStore.openModal).toHaveBeenCalledWith('data-viewer', {
      title: 'Data Preview',
      content: testData,
      viewMode: 'preview',
    });
  });

  it('opens data-viewer modal with formatted messages when on Output tab', async () => {
    const uiStore = useUiStore();
    jest.spyOn(uiStore, 'openModal');

    const wrapper = mount(ScratchpadOutput, {
      props: {
        result: {
          result: 'Some text',
          messages: [
            { type: 'log', text: 'Info message' },
            { type: 'error', text: 'Failure alert' },
          ],
        },
      },
    });

    const outputTab = wrapper.findAll('.scratchpad-tab-btn').find(b => b.text().includes('Output'));
    await outputTab.trigger('click');
    await wrapper.vm.$nextTick();

    const viewDataBtn = wrapper.find('.output-action-btn.view-data-btn');
    await viewDataBtn.trigger('click');

    expect(uiStore.openModal).toHaveBeenCalledWith('data-viewer', {
      title: 'Output Messages',
      content: '[LOG] Info message\n[ERROR] Failure alert',
      viewMode: 'raw',
    });
  });

  it('opens data-viewer modal with visualization payload when on Visualization tab', async () => {
    const uiStore = useUiStore();
    jest.spyOn(uiStore, 'openModal');

    const imgViz = {
      type: 'image',
      title: 'Image: test.jpeg',
      data: 'data:image/jpeg;base64,...',
    };

    const wrapper = mount(ScratchpadOutput, {
      props: {
        result: {
          result: null,
          visualizations: [imgViz],
        },
      },
    });

    await wrapper.vm.$nextTick();
    expect(wrapper.find('.scratchpad-tab-btn.active').text()).toContain('Image: test.jpeg');

    const viewDataBtn = wrapper.find('.output-action-btn.view-data-btn');
    await viewDataBtn.trigger('click');

    expect(uiStore.openModal).toHaveBeenCalledWith('data-viewer', {
      title: 'Image: test.jpeg',
      content: imgViz.data,
      visualizations: [imgViz],
      viewMode: 0,
    });
  });
});

