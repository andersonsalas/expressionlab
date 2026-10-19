import { mount } from '@vue/test-utils';
import { createTestingPinia } from '@pinia/testing';
import DataViewerModal from '../../../components/modals/DataViewerModal.vue';
import { useUiStore } from '../../../stores/ui';

describe('DataViewerModal.vue', () => {
  const mountModal = (modalData = {}) => {
    const pinia = createTestingPinia({
      initialState: {
        ui: {
          activeModal: 'data-viewer',
          activeModalData: modalData,
        },
      },
    });

    return mount(DataViewerModal, {
      global: {
        plugins: [pinia],
      },
    });
  };

  it('renders tabs header and toolbar correctly when open with raw content (preview tab hidden)', () => {
    const wrapper = mountModal({
      content: 'Hello World',
      column: 'option_value',
    });

    expect(wrapper.find('#data-viewer-modal').exists()).toBe(true);
    expect(wrapper.find('.modal-header h2').text()).toBe('Data viewer - option_value');
    expect(wrapper.find('.data-viewer-tabs-header').exists()).toBe(true);

    const tabs = wrapper.findAll('.scratchpad-tab-btn');
    expect(tabs.length).toBe(1);
    expect(tabs[0].text()).toContain('Raw');

    expect(wrapper.find('.scratchpad-output-actions .output-action-btn').exists()).toBe(true);
    expect(wrapper.find('.data-viewer-toolbar').exists()).toBe(true);
  });

  it('initializes in preview mode for composite JSON payload and allows expand/collapse', async () => {
    const wrapper = mountModal({
      content: '{"site": "Expression Lab", "active": true}',
    });

    expect(wrapper.vm.viewMode).toBe('preview');
    expect(wrapper.find('.viewer-preview-area').exists()).toBe(true);
    expect(wrapper.findComponent({ name: 'DataTree' }).exists()).toBe(true);

    const expandBtn = wrapper.find('.btn-expand-all');
    const collapseBtn = wrapper.find('.btn-collapse-all');
    expect(expandBtn.attributes('disabled')).toBeUndefined();
    expect(collapseBtn.attributes('disabled')).toBeUndefined();

    await collapseBtn.trigger('click');
    await expandBtn.trigger('click');
  });

  it('initializes in preview mode for composite PHP serialized payload', () => {
    const wrapper = mountModal({
      content: 'a:1:{s:4:"name";s:8:"Anderson";}',
    });

    expect(wrapper.vm.viewMode).toBe('preview');
    expect(wrapper.find('.viewer-preview-area').exists()).toBe(true);
    expect(wrapper.findComponent({ name: 'DataTree' }).exists()).toBe(true);
  });

  it('controls activate and deactivate contextually in raw mode', async () => {
    const wrapper = mountModal({
      content: 'SELECT * FROM wp_posts',
    });

    // In raw mode
    await wrapper.setData({ viewMode: 'raw' });

    const langSelect = wrapper.find('.viewer-lang-select');
    const wordWrapBtn = wrapper.find('.btn-word-wrap');
    const expandBtn = wrapper.find('.btn-expand-all');

    expect(langSelect.attributes('disabled')).toBeUndefined();
    expect(wordWrapBtn.attributes('disabled')).toBeUndefined();
    expect(expandBtn.attributes('disabled')).toBeDefined();

    expect(wordWrapBtn.classes()).not.toContain('active');
    await wordWrapBtn.trigger('click');
    expect(wrapper.vm.wordWrap).toBe(true);
    expect(wordWrapBtn.classes()).toContain('active');
    expect(wrapper.find('.viewer-raw-area').classes()).toContain('has-word-wrap');
  });

  it('hides preview tab when content cannot be previewed as tree, and renders empty state if viewMode is forced to preview', async () => {
    const wrapper = mountModal({
      content: 'plain string content without tree preview',
    });

    // Preview tab should not exist in tab buttons
    const tabs = wrapper.findAll('.scratchpad-tab-btn');
    expect(tabs.length).toBe(1);
    expect(tabs[0].text()).toContain('Raw');

    // If viewMode is set to preview
    await wrapper.setData({ viewMode: 'preview' });

    expect(wrapper.vm.viewMode).toBe('preview');
    expect(wrapper.find('.data-viewer-empty-state').exists()).toBe(true);
    expect(wrapper.find('.data-viewer-empty-state').text()).toContain('No preview available');
    expect(wrapper.find('.data-viewer-empty-state .codicon-info').exists()).toBe(true);

    // Expand/collapse should be disabled
    expect(wrapper.find('.btn-expand-all').attributes('disabled')).toBeDefined();
    expect(wrapper.find('.btn-collapse-all').attributes('disabled')).toBeDefined();
  });

  it('embeds image visualizations as tabs in viewer-preview-area and disables word wrap', async () => {
    const wrapper = mountModal({
      content: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
      visualizations: [
        {
          type: 'image',
          title: 'Image: sample.png',
          filename: 'sample.png',
          mime: 'image/png',
          base64: 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        },
      ],
    });

    // Should have Raw and Image tabs
    const tabs = wrapper.findAll('.scratchpad-tab-btn');
    expect(tabs.length).toBe(2);
    expect(tabs[0].text()).toContain('Raw');
    expect(tabs[1].text()).toContain('Image: sample.png');

    // Should initialize on image visualization tab (index 0)
    expect(wrapper.vm.viewMode).toBe(0);
    expect(tabs[1].classes()).toContain('active');

    // Should be embedded inside viewer-preview-area
    const previewArea = wrapper.find('.viewer-preview-area');
    expect(previewArea.exists()).toBe(true);
    expect(previewArea.findComponent({ name: 'ImageVisualization' }).exists()).toBe(true);

    // Toolbar Word Wrap button should be disabled for image
    const wordWrapBtn = wrapper.find('.btn-word-wrap');
    expect(wordWrapBtn.attributes('disabled')).toBeDefined();
  });

  it('embeds file visualizations as tabs in viewer-preview-area and connects toolbar word wrap button', async () => {
    const wrapper = mountModal({
      content: 'console.log("hello world");',
      visualizations: [
        {
          type: 'file',
          title: 'File: app.js',
          filename: 'app.js',
          content: 'console.log("hello world");',
          ext: 'js',
          mime: 'application/javascript',
          line_count: 1,
        },
      ],
    });

    const tabs = wrapper.findAll('.scratchpad-tab-btn');
    expect(tabs.length).toBe(2);
    expect(tabs[0].text()).toContain('Raw');
    expect(tabs[1].text()).toContain('File: app.js');

    // Should initialize on file visualization tab (index 0)
    expect(wrapper.vm.viewMode).toBe(0);

    // Should be embedded inside viewer-preview-area
    const previewArea = wrapper.find('.viewer-preview-area');
    expect(previewArea.exists()).toBe(true);
    const fileViz = previewArea.findComponent({ name: 'FileVisualization' });
    expect(fileViz.exists()).toBe(true);

    // In DataViewer, redundant word wrap button in FileVisualization is omitted
    expect(fileViz.props('hideWordWrap')).toBe(true);
    expect(fileViz.find('.file-footer .btn-word-wrap').exists()).toBe(false);

    // Word wrap button in .data-viewer-toolbar should be enabled for file visualization
    const toolbarWordWrapBtn = wrapper.find('.btn-word-wrap');
    expect(toolbarWordWrapBtn.attributes('disabled')).toBeUndefined();
    expect(toolbarWordWrapBtn.classes()).not.toContain('active');
    expect(fileViz.props('wordWrap')).toBe(false);

    // Clicking word wrap in .data-viewer-toolbar toggles wordWrap
    await toolbarWordWrapBtn.trigger('click');
    expect(wrapper.vm.wordWrap).toBe(true);
    expect(toolbarWordWrapBtn.classes()).toContain('active');
    expect(fileViz.props('wordWrap')).toBe(true);
  });

  it('switches to raw mode and renders content with code formatting', async () => {
    const wrapper = mountModal({
      content: 'Sample text to inspect',
    });

    const rawTab = wrapper.findAll('.scratchpad-tab-btn')[0];
    await rawTab.trigger('click');

    expect(wrapper.vm.viewMode).toBe('raw');
    const rawArea = wrapper.find('.viewer-raw-area');
    expect(rawArea.exists()).toBe(true);
    expect(rawArea.text()).toContain('Sample text to inspect');
  });

  it('supports selecting No highlighting for unformatted plain text', async () => {
    const wrapper = mountModal({
      content: '<div><span>Test</span></div>',
    });

    await wrapper.setData({ viewMode: 'raw', highlightLanguage: 'none' });
    expect(wrapper.vm.effectiveLanguage).toBe('plaintext');
    const rawArea = wrapper.find('.viewer-raw-area');
    expect(rawArea.text()).toContain('<div><span>Test</span></div>');
  });

  it('calls uiStore.closeModal when close button is clicked', async () => {
    const wrapper = mountModal({
      content: 'test',
    });
    const uiStore = useUiStore();

    await wrapper.find('.modal-close').trigger('click');
    expect(uiStore.closeModal).toHaveBeenCalled();
  });

  it('falls back to the generic title when no column is provided', () => {
    const wrapper = mountModal({ content: 'test' });
    expect(wrapper.find('.modal-header h2').text()).toBe('Data viewer');
  });

  it('clears the tree search on Escape without closing the modal, then closes on a second Escape', async () => {
    const wrapper = mount(DataViewerModal, {
      global: {
        plugins: [createTestingPinia({
          initialState: { ui: { activeModal: 'data-viewer', activeModalData: { content: '{"a": "hello"}' } } },
        })],
      },
      attachTo: document.body,
    });
    const uiStore = useUiStore();

    const input = wrapper.find('.data-tree-search-input');
    await input.setValue('hello');
    await input.trigger('keydown', { key: 'Escape' });
    expect(input.element.value).toBe('');
    expect(uiStore.closeModal).not.toHaveBeenCalled();

    await input.trigger('keydown', { key: 'Escape' });
    expect(uiStore.closeModal).toHaveBeenCalledTimes(1);
    wrapper.unmount();
  });
});
