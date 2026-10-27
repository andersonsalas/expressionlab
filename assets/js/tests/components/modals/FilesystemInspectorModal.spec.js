import { mount, flushPromises } from '@vue/test-utils';
import { createTestingPinia } from '@pinia/testing';
import FilesystemInspectorModal from '../../../components/modals/FilesystemInspectorModal.vue';
import { useUiStore } from '../../../stores/ui';
import { useTabsStore } from '../../../stores/tabs';
import { useFilesystemStore } from '../../../stores/filesystem';

describe('FilesystemInspectorModal.vue', () => {
  const mockFilesystemData = {
    presets: [
      {
        id: 'debug_log',
        name: 'debug.log',
        path: 'wp-content/debug.log',
        exists: true,
        type: 'log',
        size: '2.4 MB',
        description: 'WordPress active runtime debug log file',
      },
      {
        id: 'wp_config',
        name: 'wp-config.php',
        path: 'wp-config.php',
        exists: true,
        type: 'config',
        size: '3.1 KB',
        description: 'Core WordPress configuration',
      },
      {
        id: 'mu_plugins',
        name: 'mu-plugins',
        path: 'wp-content/mu-plugins',
        exists: true,
        type: 'dir',
        size: null,
        description: 'Must-use plugins directory',
      },
    ],
    tree: [
      {
        name: 'wp-config.php',
        path: 'wp-config.php',
        type: 'file',
        size: '3.1 KB',
        ext: 'php',
        perms: '0640',
        owner: 'anderson',
        group: 'www-data',
        opaque: false,
      },
      {
        name: 'wp-content',
        path: 'wp-content',
        type: 'dir',
        opaque: false,
        children: [
          {
            name: 'debug.log',
            path: 'wp-content/debug.log',
            type: 'file',
            size: '2.4 MB',
            ext: 'log',
            opaque: false,
          },
          {
            name: 'plugins',
            path: 'wp-content/plugins',
            type: 'dir',
            opaque: false,
            children: [
              {
                name: 'expressionlab',
                path: 'wp-content/plugins/expressionlab',
                type: 'dir',
                opaque: true,
                children: [],
              },
            ],
          },
          {
            name: 'uploads',
            path: 'wp-content/uploads',
            type: 'dir',
            opaque: true,
            children: [],
          },
        ],
      },
    ],
    limits: {
      max_read_bytes: 262144,
      max_tail_bytes: 1048576,
    },
  };

  beforeEach(() => {
    window.el_settings = {
      settings: {},
      filesystem: mockFilesystemData,
    };
  });

  it('renders correctly when open', () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'filesystem-inspector' },
      },
    });

    const wrapper = mount(FilesystemInspectorModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    expect(wrapper.find('#filesystem-inspector-modal').exists()).toBe(true);
    expect(wrapper.find('.modal-header h2').text()).toBe('Filesystem Inspector');

    const pills = wrapper.findAll('.scope-pill-btn');
    expect(pills.length).toBe(3);
    expect(pills[0].text()).toContain('Presets');
    expect(pills[0].classes()).toContain('active');
    expect(pills[0].find('.tab-active-dot').exists()).toBe(false); // no target selected initially
    expect(pills[1].find('.tab-active-dot').exists()).toBe(false);
    expect(pills[2].find('.tab-active-dot').exists()).toBe(false);
    expect(pills[1].text()).toContain('File Tree');
    expect(pills[2].text()).toContain('Custom Path');

    // Two main tabs: Target and Operation
    const tabs = wrapper.findAll('.filesystem-inspector-tabs-header .builder-tab-btn');
    expect(tabs.length).toBe(2);
    expect(tabs[0].text()).toContain('Target');
    expect(tabs[0].classes()).toContain('active');
    expect(tabs[1].text()).toContain('Operation');

    expect(wrapper.find('.live-expression-bar').exists()).toBe(true);
    expect(wrapper.find('.target-summary-banner').exists()).toBe(false);

    // Initial state: No target is selected by default
    expect(wrapper.findAll('.preset-card.active').length).toBe(0);
    expect(wrapper.find('.live-expression-bar .builder-notice.notice-info').exists()).toBe(true);
    expect(wrapper.find('.live-expression-bar .builder-notice p').text()).toBe(
      'Please select a file or directory to preview the generated expression.'
    );
    expect(wrapper.find('.insert-btn').attributes('disabled')).toBeDefined();
    expect(wrapper.find('.reset-btn').attributes('disabled')).toBeDefined();
  });

  it('does not render when closed', () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: null },
      },
    });

    const wrapper = mount(FilesystemInspectorModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    expect(wrapper.find('#filesystem-inspector-modal').exists()).toBe(false);
  });

  it('switches scope pills between Presets, File Tree, and Custom Path', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'filesystem-inspector' },
      },
    });

    const wrapper = mount(FilesystemInspectorModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    const pills = wrapper.findAll('.scope-pill-btn');

    // Switch to File Tree
    await pills[1].trigger('click');
    expect(wrapper.find('.tree-section').exists()).toBe(true);
    expect(wrapper.find('.presets-section').exists()).toBe(false);

    // Switch to Custom Path
    await pills[2].trigger('click');
    expect(wrapper.find('.custom-path-section').exists()).toBe(true);

    // Switch back to Presets
    await pills[0].trigger('click');
    expect(wrapper.find('.presets-section').exists()).toBe(true);
  });

  it('selects a preset and sets blue dot on Presets pill', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'filesystem-inspector' },
      },
    });

    const wrapper = mount(FilesystemInspectorModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    const presetCards = wrapper.findAll('.preset-card');
    expect(presetCards.length).toBe(3);

    // Click on wp-config.php preset
    await presetCards[1].trigger('click');

    const pills = wrapper.findAll('.scope-pill-btn');
    expect(pills[0].find('.tab-active-dot').exists()).toBe(true);
    expect(pills[1].find('.tab-active-dot').exists()).toBe(false);

    const liveExpression = wrapper.find('.live-expression-bar .expression-code');
    expect(liveExpression.text()).toContain('wp-config.php');
  });

  it('executes Quick Insert from Operation tab and closes modal', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'filesystem-inspector' },
      },
    });

    const wrapper = mount(FilesystemInspectorModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    const uiStore = useUiStore();

    // Select a target preset first so expression is generated and replace button is enabled
    const presetCards = wrapper.findAll('.preset-card');
    await presetCards[0].trigger('click');

    // Switch to Operation tab
    const tabs = wrapper.findAll('.filesystem-inspector-tabs-header .builder-tab-btn');
    await tabs[1].trigger('click');

    // Quick buttons have been completely removed from operations tab
    expect(wrapper.findAll('.quick-action-btn').length).toBe(0);

    // Clicking an operation selects it
    const opInputs = wrapper.findAll('.terminal-action-group input[type="radio"]');
    expect(opInputs.length).toBeGreaterThan(0);

    // Clicking replace inserts snippet and closes modal
    const replaceBtn = wrapper.find('.modal-footer .btn-primary');
    await replaceBtn.trigger('click');

    expect(uiStore.triggerSnippetInsert).toHaveBeenCalled();
    const callArgs = uiStore.triggerSnippetInsert.mock.calls[0][0];
    expect(callArgs.code).toContain('Files.');
    expect(uiStore.closeModal).toHaveBeenCalled();
  });

  it('filters file tree nodes and tracks blue dot on File Tree pill', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'filesystem-inspector' },
      },
    });

    const wrapper = mount(FilesystemInspectorModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    // Go to Tree pill
    const pills = wrapper.findAll('.scope-pill-btn');
    await pills[1].trigger('click');

    const searchInput = wrapper.find('.tree-search-input');
    expect(searchInput.exists()).toBe(true);
    expect(searchInput.attributes('spellcheck')).toBe('false');

    await searchInput.setValue('expressionlab');
    await flushPromises();

    const nodeRows = wrapper.findAll('.tree-node-row');
    expect(nodeRows.length).toBeGreaterThan(0);
    expect(wrapper.text()).toContain('expressionlab');

    // Click on a tree node to set target
    await nodeRows[0].trigger('click');

    // File Tree pill now has blue dot, Presets does not!
    expect(pills[1].find('.tab-active-dot').exists()).toBe(true);
    expect(pills[0].find('.tab-active-dot').exists()).toBe(false);
  });

  it('automatically expands directory paths during search to reveal matched nodes', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'filesystem-inspector' },
        filesystem: {
          loaded: true,
          tree: [
            {
              name: 'wp-content',
              path: 'wp-content',
              type: 'dir',
              children: [
                {
                  name: 'themes',
                  path: 'wp-content/themes',
                  type: 'dir',
                  children: [
                    {
                      name: 'twentytwentyfive',
                      path: 'wp-content/themes/twentytwentyfive',
                      type: 'dir',
                      children: [
                        {
                          name: 'functions.php',
                          path: 'wp-content/themes/twentytwentyfive/functions.php',
                          type: 'file',
                          ext: 'php',
                        },
                      ],
                    },
                  ],
                },
              ],
            },
          ],
        },
      },
    });

    const wrapper = mount(FilesystemInspectorModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    // Go to Tree view
    const pills = wrapper.findAll('.scope-pill-btn');
    await pills[1].trigger('click');

    // Initially, twentytwentyfive is not in default expandedDirs, so functions.php is hidden
    expect(wrapper.text()).not.toContain('functions.php');

    // Enter search query
    const searchInput = wrapper.find('.tree-search-input');
    await searchInput.setValue('functions.php');
    await flushPromises();

    // Now twentytwentyfive is auto-expanded and functions.php is visible
    expect(wrapper.text()).toContain('functions.php');
    expect(wrapper.text()).toContain('twentytwentyfive');

    // Click on functions.php to select it
    const fileRow = wrapper.findAll('.tree-node-row').find((r) => r.text().includes('functions.php'));
    expect(fileRow).toBeDefined();
    await fileRow.trigger('click');
    await flushPromises();

    // Live expression generated matches functions.php
    const liveExpression = wrapper.find('.live-expression-bar .expression-code');
    expect(liveExpression.text()).toContain('Files.read(\'wp-content/themes/twentytwentyfive/functions.php\')');

    // Clearing search keeps twentytwentyfive expanded because it was selected
    await searchInput.setValue('');
    await flushPromises();
    expect(wrapper.text()).toContain('functions.php');
  });

  it('recursively renders deep tree nodes without opaque text labels', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'filesystem-inspector' },
      },
    });

    const wrapper = mount(FilesystemInspectorModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    const pills = wrapper.findAll('.scope-pill-btn');
    await pills[1].trigger('click');

    expect(wrapper.text()).not.toContain('(opaque)');
    expect(wrapper.find('.node-opaque-tag').exists()).toBe(false);
    expect(wrapper.text()).toContain('expressionlab');
    expect(wrapper.find('.node-perms-col').exists()).toBe(true);
    expect(wrapper.text()).toContain('0640');
  });

  it('selects a directory preset and triggers insert from footer', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'filesystem-inspector' },
      },
    });

    const wrapper = mount(FilesystemInspectorModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    const uiStore = useUiStore();
    const presetCards = wrapper.findAll('.preset-card');
    // mu-plugins is a directory preset (index 2)
    const muPluginsCard = presetCards[2];
    await muPluginsCard.trigger('click');

    const liveExpression = wrapper.find('.live-expression-bar .expression-code');
    expect(liveExpression.text()).toContain('Files.list(\'wp-content/mu-plugins\')');

    const insertBtn = wrapper.find('.insert-btn');
    await insertBtn.trigger('click');

    expect(uiStore.triggerSnippetInsert).toHaveBeenCalled();
    const callArgs = uiStore.triggerSnippetInsert.mock.calls[0][0];
    expect(callArgs.code).toContain('Files.list(\'wp-content/mu-plugins\')');
    expect(uiStore.closeModal).toHaveBeenCalled();
  });

  it('configures custom path with type toggle and sets blue dot on Custom Path pill', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'filesystem-inspector' },
      },
    });

    const wrapper = mount(FilesystemInspectorModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    const pills = wrapper.findAll('.scope-pill-btn');
    await pills[2].trigger('click'); // Custom Path is index 2

    // Empty custom path shows the info notice in the live expression bar
    expect(wrapper.find('.live-expression-bar .builder-notice.notice-info').exists()).toBe(true);
    expect(wrapper.find('.live-expression-bar .builder-notice.notice-info p').text()).toContain('Please select a file or directory');

    const customInput = wrapper.find('.custom-path-input');
    expect(customInput.attributes('spellcheck')).toBe('false');
    expect(customInput.attributes('maxlength')).toBe('4096');

    await customInput.setValue('wp-content/custom/data.json');
    await flushPromises();

    // After setting a target path, notice disappears and live expression appears
    expect(wrapper.find('.live-expression-bar .builder-notice.notice-info').exists()).toBe(false);

    // Custom Path pill now has the blue dot!
    expect(pills[2].find('.tab-active-dot').exists()).toBe(true);
    expect(pills[0].find('.tab-active-dot').exists()).toBe(false);

    let liveExpression = wrapper.find('.live-expression-bar .expression-code');
    expect(liveExpression.text()).toContain('wp-content/custom/data.json');

    // Switch to Directory type
    const dirRadio = wrapper.findAll('.custom-path-section .terminal-action-group input[type="radio"]')[1];
    await dirRadio.setChecked(true);
    await flushPromises();

    liveExpression = wrapper.find('.live-expression-bar .expression-code');
    expect(liveExpression.text()).toContain('Files.list(\'wp-content/custom/data.json\')');
  });

  it('switches to Operation tab and configures inline parameters', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'filesystem-inspector' },
      },
    });

    const wrapper = mount(FilesystemInspectorModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    // Select debug.log preset card first (defaults to tail operation)
    const presetCards = wrapper.findAll('.preset-card');
    await presetCards[0].trigger('click');

    // Switch to Operation tab
    const tabs = wrapper.findAll('.filesystem-inspector-tabs-header .builder-tab-btn');
    await tabs[1].trigger('click');

    // Terminal action group layout
    expect(wrapper.find('.terminal-action-group').exists()).toBe(true);
    const radioLabels = wrapper.findAll('.terminal-radio-label');
    expect(radioLabels.length).toBeGreaterThan(0);

    // Number input inside operation parameters
    const numberInput = wrapper.find('.operation-parameters-section #tail-lines-input');
    expect(numberInput.exists()).toBe(true);
    expect(numberInput.attributes('placeholder')).toBe('50');
    expect(numberInput.element.value).toBe('');

    await numberInput.setValue(100);
    await flushPromises();

    const liveExpression = wrapper.find('.live-expression-bar .expression-code');
    expect(liveExpression.text()).toContain('Files.tail(\'wp-content/debug.log\', 100)');
  });

  it('updates read expression and converts KB to bytes when modifying read-kb-input', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'filesystem-inspector' },
      },
    });

    const wrapper = mount(FilesystemInspectorModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    // Select debug.log preset card first
    const presetCards = wrapper.findAll('.preset-card');
    await presetCards[0].trigger('click');

    const tabs = wrapper.findAll('.filesystem-inspector-tabs-header .builder-tab-btn');
    await tabs[1].trigger('click');

    // Switch operation to Files.read()
    const readRadio = wrapper.findAll('.tab-operation .terminal-action-group input[type="radio"]')[0];
    await readRadio.setValue();
    await flushPromises();

    const readKbInput = wrapper.find('.operation-parameters-section #read-kb-input');
    expect(readKbInput.exists()).toBe(true);
    expect(readKbInput.attributes('placeholder')).toBe('256');
    expect(readKbInput.element.value).toBe('');

    // Set 64 KB -> should convert to 65536 bytes
    await readKbInput.setValue(64);
    await flushPromises();

    const liveExpression = wrapper.find('.live-expression-bar .expression-code');
    expect(liveExpression.text()).toContain('Files.read(\'wp-content/debug.log\', 65536)');
  });

  it('prompts confirmation when resetting a dirty configuration', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'filesystem-inspector' },
      },
    });

    const wrapper = mount(FilesystemInspectorModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    const uiStore = useUiStore();
    uiStore.showDialog.mockResolvedValue(true);

    // Select debug.log preset card first
    const presetCards = wrapper.findAll('.preset-card');
    await presetCards[0].trigger('click');

    // Switch to Operation tab and modify parameter to make dirty
    const tabs = wrapper.findAll('.filesystem-inspector-tabs-header .builder-tab-btn');
    await tabs[1].trigger('click');

    const numberInput = wrapper.find('.operation-parameters-section #tail-lines-input');
    await numberInput.setValue(15);
    await flushPromises();

    const resetBtn = wrapper.find('.reset-btn');
    expect(resetBtn.attributes('disabled')).toBeUndefined();

    await resetBtn.trigger('click');
    expect(uiStore.showDialog).toHaveBeenCalledWith(
      expect.objectContaining({
        type: 'confirm',
        title: 'Reset Filesystem Inspector?',
      })
    );
    await flushPromises();
    expect(wrapper.find('.reset-btn').attributes('disabled')).toBeDefined();
    expect(wrapper.findAll('.tab-active-dot').length).toBe(0);
  });

  it('does not enable reset button when switching tabs or selecting operation without a target', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'filesystem-inspector' },
      },
    });

    const wrapper = mount(FilesystemInspectorModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    const resetBtn = wrapper.find('.reset-btn');
    expect(resetBtn.attributes('disabled')).toBeDefined();

    // Switch to Operation tab without target
    const tabs = wrapper.findAll('.filesystem-inspector-tabs-header .builder-tab-btn');
    await tabs[1].trigger('click');
    expect(resetBtn.attributes('disabled')).toBeDefined();

    // Select another operation without target
    const opLabels = wrapper.findAll('.terminal-action-group .terminal-radio-label');
    const sizeLabel = opLabels.find((l) => l.text().includes('Files.size()'));
    if (sizeLabel) {
      await sizeLabel.trigger('click');
      await flushPromises();
    }
    expect(resetBtn.attributes('disabled')).toBeDefined();

    // Switch pills without target
    const pills = wrapper.findAll('.scope-pill-btn');
    await pills[1].trigger('click'); // File tree
    expect(resetBtn.attributes('disabled')).toBeDefined();
    await pills[2].trigger('click'); // Custom path
    expect(resetBtn.attributes('disabled')).toBeDefined();
  });

  it('triggers insert / replace according to active tab mode', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'filesystem-inspector' },
        tabs: {
          tabs: [{ id: 'tab1', mode: 'scratchpad' }],
          activeTabId: 'tab1',
        },
      },
    });

    const wrapper = mount(FilesystemInspectorModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    const uiStore = useUiStore();

    // Select a preset card first so button is enabled
    const presetCards = wrapper.findAll('.preset-card');
    await presetCards[0].trigger('click');

    const insertBtn = wrapper.find('.insert-btn');
    expect(insertBtn.text()).toBe('Replace');
    expect(insertBtn.attributes('disabled')).toBeUndefined();

    await insertBtn.trigger('click');
    expect(uiStore.triggerSnippetInsert).toHaveBeenCalledWith(
      expect.objectContaining({
        mode: 'replace',
      })
    );
  });

  it('closes cleanly when cancel or close button is clicked', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'filesystem-inspector' },
      },
    });

    const wrapper = mount(FilesystemInspectorModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    const uiStore = useUiStore();
    expect(wrapper.find('#filesystem-inspector-modal').classes()).toContain('modal-overlay');
    const closeBtn = wrapper.find('.modal-close');
    await closeBtn.trigger('click');

    expect(uiStore.closeModal).toHaveBeenCalled();
  });

  it('correctly renders missing presets with is-missing class, disabled buttons, and Not found label', async () => {
    window.el_settings.filesystem = {
      ...mockFilesystemData,
      presets: [
        ...mockFilesystemData.presets,
        {
          id: 'dropin_db',
          name: 'db.php',
          path: 'wp-content/db.php',
          exists: false,
          type: 'php',
          size: null,
          description: 'Custom database drop-in',
        },
      ],
    };

    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'filesystem-inspector' },
      },
    });

    const wrapper = mount(FilesystemInspectorModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    const presetCards = wrapper.findAll('.preset-card');
    expect(presetCards.length).toBe(4);

    const missingCard = presetCards[3];
    expect(missingCard.classes()).toContain('is-missing');
    expect(missingCard.find('.preset-status-badge.missing').text()).toBe('Not found');

    // Existing preset has no status badge (green exists badge removed)
    expect(presetCards[0].find('.preset-status-badge').exists()).toBe(false);

    // No preset-desc in any card
    expect(wrapper.find('.preset-desc').exists()).toBe(false);

    // Preset cards use terminal-radio-label structure: strong for name, small for path, no code or preset-title
    const smallEl = missingCard.find('small');
    expect(smallEl.text()).toContain('wp-content/db.php');
    expect(smallEl.attributes('title')).toBe('wp-content/db.php');
    expect(missingCard.find('strong').text()).toBe('db.php');
    expect(missingCard.find('code').exists()).toBe(false);
    expect(missingCard.find('.preset-title').exists()).toBe(false);
    expect(missingCard.find('.preset-path-code').exists()).toBe(false);

    // No quick action buttons inside preset cards or modal
    expect(wrapper.find('.preset-quick-actions').exists()).toBe(false);
    expect(wrapper.findAll('.quick-action-btn').length).toBe(0);

    // Selecting a missing preset selects Files.exists operation
    await missingCard.trigger('click');
    const liveExpression = wrapper.find('.live-expression-bar .expression-code');
    expect(liveExpression.text()).toContain('Files.exists(\'wp-content/db.php\')');
  });

  it('triggers filesystemStore.refreshFilesystem when clicking the refresh button', async () => {
    const pinia = createTestingPinia({
      stubActions: false,
      initialState: {
        ui: { activeModal: 'filesystem-inspector' },
        tabs: { activeTab: { mode: 'console' } },
        filesystem: {
          presets: mockFilesystemData.presets,
          tree: mockFilesystemData.tree,
          loaded: true,
          loading: false,
        },
      },
    });

    const wrapper = mount(FilesystemInspectorModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    const filesystemStore = useFilesystemStore();
    jest.spyOn(filesystemStore, 'refreshFilesystem').mockResolvedValue();

    const refreshBtn = wrapper.find('.refresh-pill-btn');
    expect(refreshBtn.exists()).toBe(true);
    await refreshBtn.trigger('click');

    expect(filesystemStore.refreshFilesystem).toHaveBeenCalled();
  });

  it('distinguishes automatic smart operation defaults vs explicit user manual operation selection', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'filesystem-inspector' },
        tabs: { activeTab: { mode: 'console' } },
        filesystem: {
          presets: mockFilesystemData.presets,
          tree: mockFilesystemData.tree,
          loaded: true,
          loading: false,
        },
      },
    });

    const wrapper = mount(FilesystemInspectorModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    const scopePills = wrapper.findAll('.scope-pill-btn');
    const tabs = wrapper.findAll('.filesystem-inspector-tabs-header .builder-tab-btn');

    // 1. Go to File Tree pill (index 1)
    await scopePills[1].trigger('click');

    // 2. Select wp-config.php (regular file) -> defaults to Files.read()
    const treeRows = wrapper.findAll('.tree-node-row');
    const wpConfigRow = treeRows.find((r) => r.text().includes('wp-config.php'));
    await wpConfigRow.trigger('click');
    await flushPromises();
    expect(wrapper.find('.live-expression-bar .expression-code').text()).toContain('Files.read(\'wp-config.php\')');

    // 3. Select debug.log (log file) -> defaults to Files.tail()
    const debugLogRow = treeRows.find((r) => r.text().includes('debug.log'));
    await debugLogRow.trigger('click');
    await flushPromises();
    expect(wrapper.find('.live-expression-bar .expression-code').text()).toContain('Files.tail(\'wp-content/debug.log\')');

    // 4. User explicitly picks Files.perms() in the Operation tab
    await tabs[1].trigger('click'); // Operation tab
    const opLabels = wrapper.findAll('.terminal-action-group .terminal-radio-label');
    const permsLabel = opLabels.find((l) => l.text().includes('Files.perms()'));
    await permsLabel.trigger('click');
    await flushPromises();
    expect(wrapper.find('.live-expression-bar .expression-code').text()).toContain('Files.perms(\'wp-content/debug.log\')');

    // 5. User returns to File Tree and selects wp-config.php: manual perms() is preserved!
    await tabs[0].trigger('click'); // Target tab
    await flushPromises();
    const newTreeRows = wrapper.findAll('.tree-node-row');
    const targetWpConfigRow = newTreeRows.find((r) => r.text().includes('wp-config.php'));
    await targetWpConfigRow.trigger('click');
    await flushPromises();
    expect(wrapper.find('.live-expression-bar .expression-code').text()).toContain('Files.perms(\'wp-config.php\')');
  });

  it('preserves File Tree selection and blue dot when alternating to Custom Path pill until path is explicitly typed', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'filesystem-inspector' },
        tabs: { activeTab: { mode: 'scratchpad' } },
        filesystem: {
          presets: mockFilesystemData.presets,
          tree: mockFilesystemData.tree,
          loaded: true,
          loading: false,
        },
      },
    });

    const wrapper = mount(FilesystemInspectorModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    const pills = wrapper.findAll('.scope-pill-btn');

    // 1. Go to File Tree pill (index 1) and select a node
    await pills[1].trigger('click');
    const treeRows = wrapper.findAll('.tree-node-row');
    const wpConfigRow = treeRows.find((r) => r.text().includes('wp-config.php'));
    expect(wpConfigRow).toBeDefined();
    await wpConfigRow.trigger('click');
    await flushPromises();

    // Verify File Tree pill has blue dot and expression is populated
    expect(pills[1].find('.tab-active-dot').exists()).toBe(true);
    expect(pills[0].find('.tab-active-dot').exists()).toBe(false);
    expect(pills[2].find('.tab-active-dot').exists()).toBe(false);
    expect(wrapper.find('.live-expression-bar .expression-code').text()).toContain('Files.read(\'wp-config.php\')');
    expect(wrapper.find('.insert-btn').attributes('disabled')).toBeUndefined();

    // 2. Switch to Custom Path pill (index 2) WITHOUT typing anything
    await pills[2].trigger('click');
    await flushPromises();

    // Custom Path input is empty
    const customInput = wrapper.find('.custom-path-input');
    expect(customInput.element.value).toBe('');

    // Crucial: File Tree MUST retain its blue dot, expression MUST remain, and button MUST remain enabled!
    expect(pills[1].find('.tab-active-dot').exists()).toBe(true);
    expect(pills[2].find('.tab-active-dot').exists()).toBe(false);
    expect(wrapper.find('.live-expression-bar .expression-code').text()).toContain('Files.read(\'wp-config.php\')');
    expect(wrapper.find('.insert-btn').attributes('disabled')).toBeUndefined();

    // 3. Switch back to File Tree pill (index 1) -> node remains selected (.active)
    await pills[1].trigger('click');
    await flushPromises();
    const activeTreeRows = wrapper.findAll('.tree-node-row.active');
    expect(activeTreeRows.length).toBe(1);
    expect(activeTreeRows[0].text()).toContain('wp-config.php');

    // 4. Switch back to Custom Path pill (index 2) and explicitly type a path
    await pills[2].trigger('click');
    await flushPromises();
    await customInput.setValue('wp-content/uploads/sample.pdf');
    await flushPromises();

    // 5. Now targetOrigin is 'custom': Custom Path pill gets the blue dot, File Tree loses it
    expect(pills[2].find('.tab-active-dot').exists()).toBe(true);
    expect(pills[1].find('.tab-active-dot').exists()).toBe(false);
    expect(wrapper.find('.live-expression-bar .expression-code').text()).toContain('Files.read(\'wp-content/uploads/sample.pdf\')');

    // 6. Clearing the custom input clears the custom target
    await customInput.setValue('');
    await flushPromises();
    expect(pills[2].find('.tab-active-dot').exists()).toBe(false);
    expect(pills[1].find('.tab-active-dot').exists()).toBe(false);
    expect(wrapper.find('.live-expression-bar .builder-notice.notice-info').exists()).toBe(true);
    expect(wrapper.find('.insert-btn').attributes('disabled')).toBeDefined();
  });
});


