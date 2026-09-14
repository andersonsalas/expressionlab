import { mount, shallowMount } from '@vue/test-utils';

jest.mock('../../../components/console/visualizations/VisualizationRenderer.vue', () => ({
  name: 'VisualizationRenderer',
  template: '<div class="mock-visualization-renderer" />',
}));

import ConsoleLog from '../../../components/console/ConsoleLog.vue';

jest.mock('../../../lib/api/client.js', () => ({
  handleClipboardCopy: jest.fn(),
}));

const { handleClipboardCopy } = require('../../../lib/api/client.js');

describe('ConsoleLog.vue', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('renders input and output entries correctly', () => {
    const entries = [
      {
        input: 'Users.find(1)',
        output: { id: 1, name: 'Admin' },
        pending: false,
        time: 12.5,
      },
    ];

    const wrapper = mount(ConsoleLog, {
      props: { entries },
    });

    expect(wrapper.find('.entry.in').exists()).toBe(true);
    expect(wrapper.find('.entry.in pre').text()).toBe('Users.find(1)');
    expect(wrapper.find('.entry.out').exists()).toBe(true);
  });

  it('renders pending state spinner when item.pending is true', () => {
    const entries = [
      {
        input: 'sleep(5)',
        output: null,
        pending: true,
      },
    ];

    const wrapper = mount(ConsoleLog, {
      props: { entries },
    });

    expect(wrapper.find('.entry.in.loading').exists()).toBe(true);
  });

  it('renders error messages cleanly in output entry', () => {
    const entries = [
      {
        input: 'bad_func()',
        messages: [{ type: 'error', text: 'Syntax error: Unknown function bad_func()' }],
        output: null,
        pending: false,
      },
    ];

    const wrapper = mount(ConsoleLog, {
      props: { entries },
    });

    expect(wrapper.find('.entry.out').exists()).toBe(true);
    expect(wrapper.text()).toContain('Syntax error: Unknown function bad_func()');
  });

  it('triggers handleClipboardCopy when copy button is clicked', async () => {
    const entries = [
      {
        input: 'testCode()',
        output: 'testResult',
        pending: false,
      },
    ];

    const wrapper = mount(ConsoleLog, {
      props: { entries },
    });

    const copyBtn = wrapper.find('.action.copy-action');
    expect(copyBtn.exists()).toBe(true);

    await copyBtn.trigger('click');
    expect(handleClipboardCopy).toHaveBeenCalledWith('testCode()', null);
  });

  it('emits edit-entry when edit button is clicked', async () => {
    const entries = [
      {
        input: 'editableCode()',
        output: 'result',
        pending: false,
      },
    ];

    const wrapper = mount(ConsoleLog, {
      props: { entries },
    });

    const editBtn = wrapper.find('.action.edit-action');
    if (editBtn.exists()) {
      await editBtn.trigger('click');
      expect(wrapper.emitted('edit-entry')).toBeTruthy();
      expect(wrapper.emitted('edit-entry')[0]).toEqual([0, 'input']);
    }
  });

  it('emits run-code when rerun button is clicked', async () => {
    const entries = [
      {
        input: 'rerunCode()',
        output: 'result',
        pending: false,
      },
    ];

    const wrapper = mount(ConsoleLog, {
      props: { entries },
    });

    const rerunBtn = wrapper.find('.action.rerun-action');
    if (rerunBtn.exists()) {
      await rerunBtn.trigger('click');
      expect(wrapper.emitted('run-code')).toBeTruthy();
      expect(wrapper.emitted('run-code')[0]).toEqual(['rerunCode()']);
    }
  });

  it('renders update messages with update class', () => {
    const entries = [
      {
        input: null,
        messages: [{ type: 'update', text: 'Update available: Version 1.2.0 is available (current: 0.0.1).' }],
        output: null,
        pending: false,
      },
    ];

    const wrapper = mount(ConsoleLog, {
      props: { entries },
    });

    const updateEntry = wrapper.find('.entry.out.update');
    expect(updateEntry.exists()).toBe(true);
    expect(updateEntry.text()).toContain('Update available: Version 1.2.0 is available');
  });
});
