import { mount, flushPromises } from '@vue/test-utils';
import { createTestingPinia } from '@pinia/testing';
import EntityQueryBuilderModal from '../../../components/modals/EntityQueryBuilderModal.vue';
import { useUiStore } from '../../../stores/ui';
import { useTabsStore } from '../../../stores/tabs';

describe('EntityQueryBuilderModal.vue', () => {
  beforeEach(() => {
    window.el_settings = {
      settings: {}
    };
  });

  it('renders correctly when open', () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'entity-builder' }
      }
    });

    const wrapper = mount(EntityQueryBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    expect(wrapper.find('#entity-builder-modal').exists()).toBe(true);
    expect(wrapper.find('.modal-header h2').text()).toBe('Entity Query Builder');

    const pills = wrapper.findAll('.entity-pill-btn');
    expect(pills.length).toBe(4);
    expect(pills[0].text()).toContain('Users');
    expect(pills[1].text()).toContain('Posts');
    expect(pills[1].classes()).toContain('active');
    expect(pills[2].text()).toContain('Media');
    expect(pills[3].text()).toContain('More...');

    const tabs = wrapper.findAll('.entity-builder-tabs-header .builder-tab-btn');
    expect(tabs.length).toBe(4);
    expect(tabs[0].text()).toContain('Conditions');
    expect(tabs[1].text()).toContain('Sorting & Pagination');
    expect(tabs[2].text()).toContain('Terminal Action');
    expect(tabs[3].text()).toContain('Code Preview');
  });

  it('does not render when closed', () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: null }
      }
    });

    const wrapper = mount(EntityQueryBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    expect(wrapper.find('#entity-builder-modal').exists()).toBe(false);
  });

  it('switches tabs and displays respective tab panes', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'entity-builder' }
      }
    });

    const wrapper = mount(EntityQueryBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    expect(wrapper.find('.tab-filters').exists()).toBe(true);

    const tabs = wrapper.findAll('.entity-builder-tabs-header .builder-tab-btn');

    // Switch to Sorting & Pagination tab
    await tabs[1].trigger('click');
    expect(wrapper.find('.tab-sorting').exists()).toBe(true);

    // Switch to Terminal Action tab
    await tabs[2].trigger('click');
    expect(wrapper.find('.tab-terminal').exists()).toBe(true);

    // Switch to Code Preview tab
    await tabs[3].trigger('click');
    expect(wrapper.find('.tab-preview').exists()).toBe(true);
  });

  it('preserves draft progress when switching between entities', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'entity-builder' }
      }
    });

    const wrapper = mount(EntityQueryBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    // Add filter on Posts
    const colInput = wrapper.find('.tab-filters .kv-table .col-col input');
    await colInput.setValue('post_status');

    const valInput = wrapper.find('.tab-filters .kv-table .col-val input');
    await valInput.setValue('publish');

    // Helper to switch entity via pills
    const switchEntity = async (entityName) => {
      const pill = wrapper.findAll('.entity-pill-btn').find((w) => w.text().includes(entityName));
      if (pill) {
        await pill.trigger('click');
      } else {
        await wrapper.find('.entity-pill-more').trigger('click');
        const option = wrapper.findAll('.entity-more-dropdown-menu .tab-menu-item').find((w) => w.text().includes(entityName));
        await option.trigger('click');
      }
      await flushPromises();
    };

    // Switch entity to Users
    await switchEntity('Users');

    // Verify Users starts clean
    const usersColInput = wrapper.find('.tab-filters .kv-table .col-col input');
    expect(usersColInput.element.value).toBe('');

    // Add filter on Users
    await usersColInput.setValue('user_email');

    // Switch back to Posts
    await switchEntity('Posts');

    // Verify Posts filter is intact
    const restoredColInput = wrapper.find('.tab-filters .kv-table .col-col input');
    expect(restoredColInput.element.value).toBe('post_status');
    const restoredValInput = wrapper.find('.tab-filters .kv-table .col-val input');
    expect(restoredValInput.element.value).toBe('publish');

    // Switch back to Users and verify Users filter is intact
    await switchEntity('Users');
    const restoredUsersCol = wrapper.find('.tab-filters .kv-table .col-col input');
    expect(restoredUsersCol.element.value).toBe('user_email');
  });

  it('adds and removes filter rows and OR groups', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'entity-builder' }
      }
    });

    const wrapper = mount(EntityQueryBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    expect(wrapper.findAll('.filter-group-card').length).toBe(1);
    expect(wrapper.findAll('.tab-filters .kv-table tbody tr').length).toBe(1);

    // Add row in group 1
    const addFilterBtn = wrapper.find('.filter-group-card .group-actions-bar button');
    await addFilterBtn.trigger('click');
    expect(wrapper.findAll('.tab-filters .kv-table tbody tr').length).toBe(2);

    // Add OR Group
    const addOrGroupBtn = wrapper.find('.or-groups-bottom-actions button');
    await addOrGroupBtn.trigger('click');
    expect(wrapper.findAll('.filter-group-card').length).toBe(2);
    expect(wrapper.findAll('.or-badge').length).toBe(1);

    // Remove OR Group
    const removeGroupBtn = wrapper.find('.btn-remove-group');
    await removeGroupBtn.trigger('click');
    expect(wrapper.findAll('.filter-group-card').length).toBe(1);
    expect(wrapper.findAll('.or-badge').length).toBe(0);
  });

  it('configures sorting, limit, and offset in sorting tab', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'entity-builder' }
      }
    });

    const wrapper = mount(EntityQueryBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    // Switch to Sorting tab
    const tabs = wrapper.findAll('.entity-builder-tabs-header .builder-tab-btn');
    await tabs[1].trigger('click');

    const sortColInput = wrapper.find('.tab-sorting .kv-table .col-col input');
    await sortColInput.setValue('post_date');

    const sortDirSelect = wrapper.find('.tab-sorting .kv-table .col-op select');
    await sortDirSelect.setValue('DESC');

    const limitInput = wrapper.find('.form-row input[type="number"]');
    await limitInput.setValue(15);

    // Add second order by
    const addSortBtn = wrapper.find('.tab-sorting .group-actions-bar button');
    await addSortBtn.trigger('click');
    expect(wrapper.findAll('.tab-sorting .kv-table tbody tr').length).toBe(2);
  });

  it('selects terminal actions including count, to_sql, and query', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'entity-builder' }
      }
    });

    const wrapper = mount(EntityQueryBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    // Switch to Terminal tab
    const tabs = wrapper.findAll('.entity-builder-tabs-header .builder-tab-btn');
    await tabs[2].trigger('click');

    const radioLabels = wrapper.findAll('.terminal-radio-label');
    expect(radioLabels.length).toBe(4);
    expect(radioLabels[0].find('strong').text()).toBe('Posts.get()');
    expect(radioLabels[1].find('strong').text()).toBe('Posts.count()');
    expect(radioLabels[2].find('strong').text()).toBe('Posts.to_sql()');
    expect(radioLabels[3].find('strong').text()).toBe('Posts.query(SQL)');

    // Changing entity updates the labels dynamically
    wrapper.vm.selectEntity('Users');
    await wrapper.vm.$nextTick();
    expect(radioLabels[0].find('strong').text()).toBe('Users.get()');
    expect(radioLabels[1].find('strong').text()).toBe('Users.count()');
    expect(radioLabels[2].find('strong').text()).toBe('Users.to_sql()');
    expect(radioLabels[3].find('strong').text()).toBe('Users.query(SQL)');

    // Select .count()
    await radioLabels[1].find('input').setValue();
    expect(wrapper.vm.currentDraft.terminal.action).toBe('count');

    // Select .to_sql()
    await radioLabels[2].find('input').setValue();
    expect(wrapper.vm.currentDraft.terminal.action).toBe('to_sql');
    expect(wrapper.find('.to-sql-options-wrapper').exists()).toBe(true);
    expect(wrapper.find('.to-sql-options-wrapper .panel-section-header h4').text()).toBe('Options');

    const countSqlCheckbox = wrapper.find('.to-sql-options-wrapper input[type="checkbox"]');
    await countSqlCheckbox.setChecked(true);
    expect(wrapper.vm.currentDraft.terminal.isCountSql).toBe(true);

    // Select .query(SQL)
    await radioLabels[3].find('input').setValue();
    expect(wrapper.vm.currentDraft.terminal.action).toBe('query');
    expect(wrapper.find('.query-editor-wrapper').exists()).toBe(true);
    expect(wrapper.find('.query-editor-wrapper .panel-section-header h4').text()).toBe('SQL Query');
  });

  it('opens, edits, and applies Bulk List Editor mini-modal', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'entity-builder' }
      }
    });

    const wrapper = mount(EntityQueryBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    // Select IN operator
    const opSelect = wrapper.find('.tab-filters .kv-table .col-op select');
    await opSelect.setValue('$in');

    const listEditorBtn = wrapper.find('.btn-list-editor-trigger');
    expect(listEditorBtn.exists()).toBe(true);
    await listEditorBtn.trigger('click');

    expect(wrapper.find('.list-editor-modal-container').exists()).toBe(true);
    expect(wrapper.find('.list-editor-modal-container .modal-header h2').text()).toBe('List (IN)');

    const textarea = wrapper.find('.list-editor-textarea');
    await textarea.setValue('1\n2\n3\n3\n');

    // Check remove duplicates (checkbox 0 is trimLines, checkbox 1 is deduplicate)
    const checkboxes = wrapper.findAll('.list-editor-option-label input[type="checkbox"]');
    await checkboxes[1].setChecked(true);

    // Click apply
    const applyBtn = wrapper.findAll('.list-editor-modal-footer button')[1];
    await applyBtn.trigger('click');

    expect(wrapper.find('.list-editor-modal-container').exists()).toBe(false);
    expect(wrapper.vm.currentDraft.filters[0][0].value).toEqual(['1', '2', '3']);
  });

  it('triggers snippet insert on primary button click (insert vs replace)', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'entity-builder' },
        tabs: {
          tabs: [{ id: 'tab1', mode: 'scratchpad' }],
          activeTabId: 'tab1'
        }
      }
    });

    const wrapper = mount(EntityQueryBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    const uiStore = useUiStore();

    // In scratchpad mode, button says Replace
    const primaryBtn = wrapper.find('.footer-actions-right .btn-primary');
    expect(primaryBtn.text()).toBe('Replace');

    await primaryBtn.trigger('click');
    expect(uiStore.triggerSnippetInsert).toHaveBeenCalledWith({
      code: 'Posts.get()',
      mode: 'replace'
    });
    expect(uiStore.closeModal).toHaveBeenCalled();
  });

  it('prompts confirmation when resetting dirty draft', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'entity-builder' }
      }
    });

    const wrapper = mount(EntityQueryBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    const uiStore = useUiStore();
    uiStore.showDialog.mockResolvedValue(true);

    const resetBtn = wrapper.find('.btn-reset');
    expect(resetBtn.attributes('disabled')).toBeDefined();

    // Make dirty by setting column
    const colInput = wrapper.find('.tab-filters .kv-table .col-col input');
    await colInput.setValue('post_title');

    expect(resetBtn.attributes('disabled')).toBeUndefined();

    await resetBtn.trigger('click');
    expect(uiStore.showDialog).toHaveBeenCalled();
    await flushPromises();

    expect(wrapper.vm.currentDraft.filters[0][0].column).toBe('');
  });

  it('keeps the preview editor alive after resetting from the preview tab', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'entity-builder' }
      }
    });

    const wrapper = mount(EntityQueryBuilderModal, {
      attachTo: document.body,
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    const uiStore = useUiStore();
    uiStore.showDialog.mockResolvedValue(true);

    await wrapper.find('.tab-filters .kv-table .col-col input').setValue('post_title');
    await wrapper.find('.tab-filters .kv-table .col-val input').setValue('Hello');
    await wrapper.findAll('.builder-tab-btn').at(3).trigger('click');
    await flushPromises();
    expect(wrapper.find('.tab-preview .code-preview-cm-editor .cm-editor').exists()).toBe(true);

    await wrapper.find('.btn-reset').trigger('click');
    await flushPromises();

    expect(wrapper.find('.tab-preview .code-preview-cm-editor .cm-editor').exists()).toBe(true);
    expect(wrapper.find('.tab-preview .cm-content').text()).toContain('Posts.get()');
    wrapper.unmount();
  });

  it('closes modal cleanly on Cancel and X button clicks', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'entity-builder' }
      }
    });

    const wrapper = mount(EntityQueryBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    const uiStore = useUiStore();

    const closeBtn = wrapper.find('.modal-header .modal-close');
    await closeBtn.trigger('click');
    expect(uiStore.closeModal).toHaveBeenCalled();

    const cancelBtn = wrapper.find('.footer-actions-right .btn-default');
    await cancelBtn.trigger('click');
    expect(uiStore.closeModal).toHaveBeenCalledTimes(2);
  });

  it('validates required inputs, shows notice in code preview, and disables insert when invalid', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'entity-builder' }
      }
    });

    const wrapper = mount(EntityQueryBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    // Default configuration (Posts.get()) is valid
    expect(wrapper.vm.isEmpty).toBe(false);
    const primaryBtn = wrapper.find('.footer-actions-right .btn-primary');
    expect(primaryBtn.attributes('disabled')).toBeUndefined();

    // Switch to Terminal tab and select .query(SQL)
    const tabs = wrapper.findAll('.entity-builder-tabs-header .builder-tab-btn');
    await tabs[2].trigger('click');

    const radioLabels = wrapper.findAll('.terminal-radio-label');
    await radioLabels[3].find('input').setValue();
    expect(wrapper.vm.currentDraft.terminal.action).toBe('query');

    // Query SQL is empty -> isEmpty should be true and primary button disabled
    expect(wrapper.vm.isEmpty).toBe(true);
    expect(primaryBtn.attributes('disabled')).toBeDefined();

    // Switch to Code Preview tab -> notice-info is displayed instead of CodeMirror
    await tabs[3].trigger('click');
    expect(wrapper.find('.tab-preview .builder-notice.notice-info').exists()).toBe(true);
    expect(wrapper.find('.tab-preview .builder-notice.notice-info p').text()).toContain('The SQL query is empty');
    expect(wrapper.find('.tab-preview .code-preview-cm-editor').exists()).toBe(false);

    // Provide valid SQL
    wrapper.vm.currentDraft.terminal.querySql = 'SELECT * FROM wp_posts';
    await wrapper.vm.$nextTick();

    expect(wrapper.vm.isEmpty).toBe(false);
    expect(primaryBtn.attributes('disabled')).toBeUndefined();
    expect(wrapper.find('.tab-preview .builder-notice.notice-info').exists()).toBe(false);
    expect(wrapper.find('.tab-preview .code-preview-cm-editor').exists()).toBe(true);
  });

  it('correctly recognizes IN and NOT IN conditions without flagging them as incomplete', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'entity-builder' }
      }
    });

    const wrapper = mount(EntityQueryBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    const colInput = wrapper.find('.tab-filters .kv-table .col-col input');
    await colInput.setValue('post_status');

    const opSelect = wrapper.find('.tab-filters .kv-table .col-op select');
    await opSelect.setValue('$in');

    const valInput = wrapper.find('.tab-filters .kv-table .col-val input');
    await valInput.setValue('draft,publish');

    expect(wrapper.vm.hasIncompleteFilters).toBe(false);
    expect(wrapper.vm.isEmpty).toBe(false);

    const primaryBtn = wrapper.find('.footer-actions-right .btn-primary');
    expect(primaryBtn.attributes('disabled')).toBeUndefined();

    // Verify generated code contains where with IN
    expect(wrapper.vm.generatedCode).toContain('where(\'post_status\', \'in\', [');

    // Also verify when value is an Array (from Bulk List Editor)
    wrapper.vm.currentDraft.filters[0][0].value = ['draft', 'publish'];
    await wrapper.vm.$nextTick();

    expect(wrapper.vm.hasIncompleteFilters).toBe(false);
    expect(wrapper.vm.isEmpty).toBe(false);
    expect(primaryBtn.attributes('disabled')).toBeUndefined();
    expect(wrapper.vm.generatedCode).toContain('where(\'post_status\', \'in\', [');
  });

  it('handles segmented entity pills selection and polymorphic More behavior', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'entity-builder' }
      }
    });

    const wrapper = mount(EntityQueryBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    const pills = wrapper.findAll('.entity-pill-btn');
    expect(pills.length).toBe(4);

    // Initial state: Posts is active
    expect(wrapper.vm.selectedEntity).toBe('Posts');
    expect(pills[1].classes()).toContain('active');

    // Click Users pill
    await pills[0].trigger('click');
    expect(wrapper.vm.selectedEntity).toBe('Users');
    expect(pills[0].classes()).toContain('active');
    expect(pills[1].classes()).not.toContain('active');

    // Click Media pill
    await pills[2].trigger('click');
    expect(wrapper.vm.selectedEntity).toBe('Media');
    expect(pills[2].classes()).toContain('active');

    // More pill: initially shows 'More...'
    const morePill = wrapper.find('.entity-pill-more');
    expect(morePill.text()).toContain('More...');
    expect(wrapper.find('.entity-more-dropdown-menu').exists()).toBe(false);

    // Click More pill to open dropdown
    await morePill.trigger('click');
    expect(wrapper.vm.isMoreDropdownOpen).toBe(true);
    expect(wrapper.find('.entity-more-dropdown-menu').exists()).toBe(true);
    expect(wrapper.find('.entity-more-dropdown-menu .tab-menu-item.empty').exists()).toBe(true);
    expect(wrapper.find('.entity-more-dropdown-menu .tab-menu-item.empty').text()).toContain('No additional entities');

    // If an overflow entity is selected (polymorphic state)
    wrapper.vm.selectEntity('Comments');
    await wrapper.vm.$nextTick();

    expect(wrapper.vm.selectedEntity).toBe('Comments');
    expect(wrapper.vm.isOverflowSelected).toBe(true);
    expect(morePill.classes()).toContain('active');
    expect(morePill.text()).toContain('Comments');
  });

  it('smoothly scrolls to bottom when adding OR group, filter row, and sorting clause', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'entity-builder' }
      }
    });

    const wrapper = mount(EntityQueryBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });
    await flushPromises();

    const tabPanel = wrapper.find('.entity-builder-tab-panel');
    const scrollToSpy = jest.fn();
    tabPanel.element.scrollTo = scrollToSpy;
    Object.defineProperty(tabPanel.element, 'scrollHeight', { value: 1200, configurable: true });

    // 1. Add Filter row
    const addFilterBtn = wrapper.find('.btn-add-filter');
    await addFilterBtn.trigger('click');
    await flushPromises();
    expect(scrollToSpy).toHaveBeenCalledWith(expect.objectContaining({ behavior: 'smooth' }));

    // 2. Add OR Group
    scrollToSpy.mockClear();
    const addOrBtn = wrapper.find('.add-or-group-btn');
    await addOrBtn.trigger('click');
    await flushPromises();
    expect(scrollToSpy).toHaveBeenCalledWith(expect.objectContaining({ behavior: 'smooth' }));

    // 3. Switch to Sorting tab & Add Sorting Clause
    scrollToSpy.mockClear();
    const tabs = wrapper.findAll('.builder-tab-btn');
    await tabs[1].trigger('click'); // Sorting & Pagination tab
    await flushPromises();

    const addSortBtn = wrapper.find('.btn-add-sorting');
    await addSortBtn.trigger('click');
    await flushPromises();
    expect(scrollToSpy).toHaveBeenCalledWith(expect.objectContaining({ behavior: 'smooth' }));

    wrapper.unmount();
  });

  it('displays warning when duplicate order by columns are present while keeping code generation enabled', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'entity-builder' }
      }
    });

    const wrapper = mount(EntityQueryBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });
    await flushPromises();

    // Switch to Sorting & Pagination tab
    const tabs = wrapper.findAll('.entity-builder-tabs-header .builder-tab-btn');
    await tabs[1].trigger('click');
    await flushPromises();

    // First order by clause: post_author
    const sortColInput = wrapper.find('.tab-sorting .kv-table .col-col input');
    await sortColInput.setValue('post_author');

    // Add second order by clause
    const addSortBtn = wrapper.find('.btn-add-sorting');
    await addSortBtn.trigger('click');
    await flushPromises();

    const inputs = wrapper.findAll('.tab-sorting .kv-table .col-col input');
    await inputs[1].setValue('post_author');
    await flushPromises();

    expect(wrapper.vm.hasDuplicateOrderColumns).toBe(true);
    // Duplicate columns should NOT block code generation or disable the primary button
    expect(wrapper.vm.isEmpty).toBe(false);

    const warning = wrapper.find('.tab-sorting .builder-notice.notice-warning');
    expect(warning.exists()).toBe(true);
    expect(warning.text()).toContain('Duplicate column detected in sorting clauses');

    const primaryBtn = wrapper.find('.footer-actions-right .btn-primary');
    expect(primaryBtn.attributes('disabled')).toBeUndefined();

    // Code generation should contain both order_by calls
    expect(wrapper.vm.generatedCode).toContain('order_by(\'post_author\', \'ASC\')');

    wrapper.unmount();
  });
});

