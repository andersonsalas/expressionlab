import { mount, flushPromises } from '@vue/test-utils';
import { createTestingPinia } from '@pinia/testing';
import DatabaseBuilderModal from '../../../components/modals/DatabaseBuilderModal.vue';
import { useUiStore } from '../../../stores/ui';

describe('DatabaseBuilderModal.vue', () => {
  beforeEach(() => {
    window.el_settings = {
      settings: {}
    };
  });

  it('renders correctly when open', () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'database-builder' }
      }
    });

    const wrapper = mount(DatabaseBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    expect(wrapper.find('#database-builder-modal').exists()).toBe(true);
    expect(wrapper.find('.modal-header h2').text()).toBe('Database Query Builder');
    expect(wrapper.findAll('.step-pill').length).toBe(1);

    const tabs = wrapper.findAll('.builder-tab-btn');
    expect(tabs.length).toBe(6);
    expect(tabs[0].text()).toContain('Table');
    expect(tabs[1].text()).toContain('Conditions');
    expect(tabs[2].text()).toContain('Options');
    expect(tabs[3].text()).toContain('Buffer Safety');
    expect(tabs[4].text()).toContain('Result & SQL');
    expect(tabs[5].text()).toContain('Code Preview');
  });

  it('does not render when closed', () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: null }
      }
    });

    const wrapper = mount(DatabaseBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    expect(wrapper.find('#database-builder-modal').exists()).toBe(false);
  });

  it('switches tabs and displays respective tab panes', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'database-builder' }
      }
    });

    const wrapper = mount(DatabaseBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    expect(wrapper.find('.tab-table').exists()).toBe(true);

    const tabs = wrapper.findAll('.builder-tab-btn');
    expect(wrapper.find('.tab-table .panel-section-header h4').text()).toBe('Table');

    // Switch to Conditions tab
    await tabs[1].trigger('click');
    expect(wrapper.find('.tab-filters').exists()).toBe(true);
    expect(wrapper.find('.tab-filters .panel-section-header h4').text()).toBe('Conditions');

    // Switch to Options tab
    await tabs[2].trigger('click');
    expect(wrapper.find('.tab-options').exists()).toBe(true);
    expect(wrapper.find('.tab-options .panel-section-header h4').text()).toBe('Options');

    // Switch to Buffer Safety tab
    await tabs[3].trigger('click');
    expect(wrapper.find('.tab-buffer').exists()).toBe(true);
    expect(wrapper.find('.tab-buffer .panel-section-header h4').text()).toBe('Buffer Safety');
    expect(wrapper.find('.tab-buffer .form-row').exists()).toBe(false);

    const bufferCheckbox = wrapper.find('.tab-buffer input[type="checkbox"]');
    await bufferCheckbox.setChecked(true);
    expect(wrapper.find('.tab-buffer .form-row').exists()).toBe(true);

    const bufferInputs = wrapper.findAll('.tab-buffer .form-row input');
    expect(bufferInputs[0].element.value).toBe('');
    expect(bufferInputs[0].attributes('placeholder')).toBe('1000');
    expect(bufferInputs[1].element.value).toBe('');
    expect(bufferInputs[1].attributes('placeholder')).toBe('50');
    expect(bufferInputs[2].element.value).toBe('');
    expect(bufferInputs[2].attributes('placeholder')).toBe('3.5');

    // Switch to Result & SQL tab
    await tabs[4].trigger('click');
    expect(wrapper.find('.tab-query').exists()).toBe(true);
    expect(wrapper.find('.tab-query .panel-section-header h4').text()).toBe('Result Action');

    // Switch to Preview tab
    await tabs[5].trigger('click');
    expect(wrapper.find('.tab-preview').exists()).toBe(true);
    expect(wrapper.find('.tab-preview .panel-section-header h4').text()).toBe('Generated Expression');
    expect(wrapper.find('.tab-preview .builder-notice.notice-info').exists()).toBe(true);
    expect(wrapper.find('.tab-preview .code-preview-cm-editor').exists()).toBe(false);
  });

  it('allows adding and removing pipeline steps', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'database-builder' }
      }
    });

    const wrapper = mount(DatabaseBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    expect(wrapper.findAll('.step-pill').length).toBe(1);
    expect(wrapper.find('.step-pill .step-icon').classes()).toContain('codicon-table');

    // Click Add Step
    await wrapper.find('.add-step-btn').trigger('click');
    expect(wrapper.findAll('.step-pill').length).toBe(2);

    // Step 2 should be active with empty table (shows placeholder 'table')
    const pills = wrapper.findAll('.step-pill');
    expect(pills[0].find('.step-icon').classes()).toContain('codicon-table');
    expect(pills[1].find('.step-icon').classes()).toContain('codicon-link');
    expect(pills[1].classes()).toContain('active');
    expect(pills[1].text()).toContain('table');

    // Remove Step 2
    await pills[1].find('.step-remove-btn').trigger('click');
    expect(wrapper.findAll('.step-pill').length).toBe(1);
  });

  it('allows adding and removing filter rows', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'database-builder' }
      }
    });

    const wrapper = mount(DatabaseBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    // Go to filters tab
    const tabs = wrapper.findAll('.builder-tab-btn');
    await tabs[1].trigger('click');

    expect(wrapper.findAll('.filter-table tbody tr').length).toBe(1);

    // Add filter row via table actions bar below the table
    await wrapper.find('.table-actions-bar .btn').trigger('click');
    expect(wrapper.findAll('.filter-table tbody tr').length).toBe(2);

    // Delete one row
    const deleteBtns = wrapper.findAll('.btn-row-delete');
    await deleteBtns[0].trigger('click');
    expect(wrapper.findAll('.filter-table tbody tr').length).toBe(1);
  });

  it('switches between Mirror Pipeline and Direct Count mode', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'database-builder' }
      }
    });

    const wrapper = mount(DatabaseBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    expect(wrapper.find('.database-builder-pipeline-bar').exists()).toBe(true);

    // Select Direct Count radio
    const radios = wrapper.findAll('.mode-radio-label input[type="radio"]');
    await radios[1].setValue(true);

    // Pipeline bar should disappear in count mode
    expect(wrapper.find('.database-builder-pipeline-bar').exists()).toBe(false);

    // Tabs should be reduced to Table, Conditions, Preview
    const tabs = wrapper.findAll('.builder-tab-btn');
    expect(tabs.length).toBe(3);
    expect(tabs[0].text()).toContain('Table');
    expect(tabs[1].text()).toContain('Conditions');
    expect(tabs[2].text()).toContain('Code Preview');
    expect(wrapper.find('.tab-table .panel-section-header h4').text()).toBe('Table');
  });

  it('disables Insert and Copy Code when empty and inserts code when configured', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'database-builder' }
      }
    });

    const wrapper = mount(DatabaseBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    const uiStore = useUiStore();
    const insertBtn = wrapper.find('.database-builder-footer .btn-primary');

    // 1. When empty, Insert button is disabled
    expect(insertBtn.attributes('disabled')).toBeDefined();
    await insertBtn.trigger('click');
    expect(uiStore.triggerSnippetInsert).not.toHaveBeenCalled();

    // 2. In Code Preview tab, notice is shown and CodeMirror editor + Copy Code button are hidden
    const tabs = wrapper.findAll('.builder-tab-btn');
    await tabs[5].trigger('click'); // Code Preview
    expect(wrapper.find('.tab-preview .builder-notice.notice-info').exists()).toBe(true);
    expect(wrapper.find('.tab-preview .code-preview-cm-editor').exists()).toBe(false);
    expect(wrapper.find('.tab-preview .table-actions-bar').exists()).toBe(false);

    // 3. When table is configured, notice disappears and editor + Copy Code button appear
    wrapper.vm.steps[0].table = 'posts';
    await wrapper.vm.$nextTick();

    expect(wrapper.find('.tab-preview .builder-notice.notice-info').exists()).toBe(false);
    expect(wrapper.find('.tab-preview .code-preview-cm-editor').exists()).toBe(true);
    expect(wrapper.find('.tab-preview .table-actions-bar button').exists()).toBe(true);
    expect(insertBtn.attributes('disabled')).toBeUndefined();

    await insertBtn.trigger('click');
    expect(uiStore.triggerSnippetInsert).toHaveBeenCalled();
    expect(uiStore.closeModal).toHaveBeenCalled();
  });

  it('closes cleanly without prompt when clean', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'database-builder' }
      }
    });

    const wrapper = mount(DatabaseBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    const uiStore = useUiStore();
    const cancelBtn = wrapper.find('.database-builder-footer .footer-actions-right .btn-default');
    await cancelBtn.trigger('click');

    expect(uiStore.showDialog).not.toHaveBeenCalled();
    expect(uiStore.closeModal).toHaveBeenCalled();
  });

  it('does not prompt confirmation on Cancel when switching to Direct Count mode while empty', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'database-builder' }
      }
    });

    const wrapper = mount(DatabaseBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    // Toggle to Direct Count mode without adding any table or filter
    const countRadio = wrapper.findAll('.mode-radio-label input[type="radio"]')[1];
    await countRadio.setChecked(true);
    await wrapper.vm.$nextTick();

    const uiStore = useUiStore();
    const cancelBtn = wrapper.find('.database-builder-footer .footer-actions-right .btn-default');
    await cancelBtn.trigger('click');

    expect(uiStore.showDialog).not.toHaveBeenCalled();
    expect(uiStore.closeModal).toHaveBeenCalled();
  });

  it('preserves draft and closes without prompt on cancel even when dirty', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'database-builder' }
      }
    });

    const wrapper = mount(DatabaseBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    // Add step to make dirty
    await wrapper.find('.add-step-btn').trigger('click');

    const uiStore = useUiStore();
    const cancelBtn = wrapper.find('.database-builder-footer .footer-actions-right .btn-default');
    await cancelBtn.trigger('click');

    expect(uiStore.showDialog).not.toHaveBeenCalled();
    expect(uiStore.closeModal).toHaveBeenCalled();
  });

  it('disables reset button when clean and prompts confirmation when dirty', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'database-builder' }
      }
    });

    const wrapper = mount(DatabaseBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    const uiStore = useUiStore();
    const resetBtn = wrapper.find('.database-builder-footer .btn-reset');
    expect(resetBtn.attributes('disabled')).toBeDefined();

    // Add step to make dirty
    await wrapper.find('.add-step-btn').trigger('click');
    expect(resetBtn.attributes('disabled')).toBeUndefined();

    // Case 1: User cancels reset dialog
    uiStore.showDialog.mockResolvedValueOnce(false);
    await resetBtn.trigger('click');
    await flushPromises();
    expect(uiStore.showDialog).toHaveBeenCalledWith(
      expect.objectContaining({
        type: 'confirm',
        confirmText: 'Reset',
        cancelText: 'Cancel'
      })
    );
    expect(wrapper.findAll('.step-pill').length).toBe(2);

    // Case 2: User confirms reset
    uiStore.showDialog.mockResolvedValueOnce(true);
    await resetBtn.trigger('click');
    await flushPromises();
    expect(wrapper.findAll('.step-pill').length).toBe(1);
  });

  it('displays Replace button and passes mode replace when in scratchpad mode', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'database-builder' },
        tabs: {
          tabs: [{ id: 'tab1', mode: 'scratchpad' }],
          activeTabId: 'tab1'
        }
      }
    });

    const wrapper = mount(DatabaseBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    const uiStore = useUiStore();
    const replaceBtn = wrapper.find('.database-builder-footer .replace-btn');
    const insertBtn = wrapper.find('.database-builder-footer .insert-btn');

    expect(replaceBtn.exists()).toBe(true);
    expect(replaceBtn.text()).toBe('Replace');
    expect(replaceBtn.classes()).toContain('btn-primary');

    expect(insertBtn.exists()).toBe(true);
    expect(insertBtn.text()).toBe('Insert');
    expect(insertBtn.classes()).toContain('btn-default');

    wrapper.vm.steps[0].table = 'posts';
    await wrapper.vm.$nextTick();

    // Clicking Insert passes mode 'insert'
    await insertBtn.trigger('click');
    expect(uiStore.triggerSnippetInsert).toHaveBeenCalledWith(
      expect.objectContaining({
        mode: 'insert'
      })
    );

    // Clicking Replace passes mode 'replace'
    await replaceBtn.trigger('click');
    expect(uiStore.triggerSnippetInsert).toHaveBeenCalledWith(
      expect.objectContaining({
        mode: 'replace'
      })
    );
  });

  it('renders compound join selector and updates joinRef when changing source or column', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'database-builder' }
      }
    });

    const wrapper = mount(DatabaseBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    // Set Step 1 table to 'posts'
    const step1TableInput = wrapper.find('.tab-table input.form-control');
    await step1TableInput.setValue('posts');
    await step1TableInput.trigger('input');

    // Add step 2
    await wrapper.find('.add-step-btn').trigger('click');

    // Set Step 2 table to 'users'
    const step2TableInput = wrapper.find('.tab-table input.form-control');
    await step2TableInput.setValue('users');
    await step2TableInput.trigger('input');

    // Go to filters tab
    const tabs = wrapper.findAll('.builder-tab-btn');
    await tabs[1].trigger('click');

    // In step 2, compound wrapper should be rendered
    const compoundWrapper = wrapper.find('.join-compound-wrapper');
    expect(compoundWrapper.exists()).toBe(true);

    const sourceSelect = compoundWrapper.find('.join-source-select');
    const colInput = compoundWrapper.find('.join-col-input');

    expect(sourceSelect.exists()).toBe(true);
    expect(colInput.exists()).toBe(true);

    // Change join column to custom column
    await colInput.setValue('post_author');
    await colInput.trigger('input');

    // Code preview should include post_author
    const uiStore = useUiStore();
    const insertBtn = wrapper.find('.database-builder-footer .btn-primary');
    await insertBtn.trigger('click');

    expect(uiStore.triggerSnippetInsert).toHaveBeenCalledWith(
      expect.objectContaining({
        code: expect.stringContaining('post_author'),
        mode: 'insert'
      })
    );
  });

  it('auto-suggests reverse join when changing step table to users after posts', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'database-builder' }
      }
    });

    const wrapper = mount(DatabaseBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    // Set Step 1 table to 'posts'
    const step1TableInput = wrapper.find('.tab-table input.form-control');
    await step1TableInput.setValue('posts');
    await step1TableInput.trigger('input');

    // Add step 2 (defaults to empty table)
    await wrapper.find('.add-step-btn').trigger('click');

    // Switch to table tab
    const tabs = wrapper.findAll('.builder-tab-btn');
    await tabs[0].trigger('click');

    // Change table to 'users'
    const tableInput = wrapper.find('.tab-table input.form-control');
    await tableInput.setValue('users');
    await tableInput.trigger('input');

    // Switch back to filters tab
    await tabs[1].trigger('click');

    // Filter should have auto-updated to reverse join: ID = $prev.post_author
    const filterRow = wrapper.find('.filter-table tbody tr');
    const colInput = filterRow.find('.col-col input');
    expect(colInput.element.value).toBe('ID');

    const joinColInput = filterRow.find('.join-col-input');
    expect(joinColInput.element.value).toBe('post_author');
  });

  it('validates SQL in query mode, renders error notice and formats code with Format SQL button', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'database-builder' }
      }
    });

    const wrapper = mount(DatabaseBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    // Switch to Result & SQL tab (index 4)
    const tabs = wrapper.findAll('.builder-tab-btn');
    await tabs[4].trigger('click');

    // Switch radio to query action
    const queryRadio = wrapper.findAll('.terminal-radio-label input[type="radio"]')[2];
    await queryRadio.setChecked(true);

    // Verify query editor exists and header is SQL Query
    const editorContainer = wrapper.find('.sql-editor-container');
    expect(editorContainer.exists()).toBe(true);
    expect(wrapper.find('.query-editor-wrapper .panel-section-header h4').text()).toBe('SQL Query');

    // Format SQL button should be located below editor inside .table-actions-bar.sql-actions-bar
    const formatBtn = wrapper.find('.sql-actions-bar .format-sql-btn');
    expect(formatBtn.exists()).toBe(true);
    expect(formatBtn.text()).toContain('Format SQL');

    // Trigger an invalid SQL query in editor
    const vm = wrapper.vm;
    vm.terminal.querySql = 'SELECT * FROM wp_posts WHERE';
    await wrapper.vm.$nextTick();

    // Editor container should now have .has-error and error text should be rendered
    expect(wrapper.find('.sql-editor-container').classes()).toContain('has-error');
    const errorNotice = wrapper.find('.sql-syntax-error');
    expect(errorNotice.exists()).toBe(true);
    expect(errorNotice.text()).toContain('Incomplete statement: expected expression after \'WHERE\'');

    // Formatting an invalid SQL query should maintain error
    await formatBtn.trigger('click');
    expect(wrapper.find('.sql-editor-container').classes()).toContain('has-error');

    // Set valid unformatted SQL
    vm.terminal.querySql = 'select id, post_title from wp_posts where post_status = \'publish\' and id > 5 limit 10';
    await wrapper.vm.$nextTick();
    expect(wrapper.find('.sql-editor-container').classes()).not.toContain('has-error');
    expect(wrapper.find('.sql-syntax-error').exists()).toBe(false);

    // Click Format SQL button
    await formatBtn.trigger('click');
    expect(vm.terminal.querySql).toBe('SELECT\n  id,\n  post_title\nFROM wp_posts\nWHERE post_status = \'publish\'\n  AND id > 5\nLIMIT 10');
    expect(wrapper.find('.sql-editor-container').classes()).not.toContain('has-error');
    expect(wrapper.find('.sql-syntax-error').exists()).toBe(false);
  });

  it('resets active tab to the first available tab when toggling operation mode', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'database-builder' }
      }
    });

    const wrapper = mount(DatabaseBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    // 1. In pipeline mode, navigate to "Result & SQL" tab (index 4)
    const pipelineTabs = wrapper.findAll('.builder-tab-btn');
    await pipelineTabs[4].trigger('click');
    expect(wrapper.find('.tab-query').exists()).toBe(true);

    // 2. Toggle Operation Mode to "Direct Count"
    const countRadio = wrapper.findAll('.mode-radio-label input[type="radio"]')[1];
    await countRadio.setChecked(true);
    await wrapper.vm.$nextTick();

    // 3. First available tab ('Table') must be active
    const countTabs = wrapper.findAll('.builder-tab-btn');
    expect(countTabs.length).toBe(3); // Table, Conditions, Code Preview
    expect(countTabs[0].classes()).toContain('active');
    expect(wrapper.find('.tab-table').exists()).toBe(true);

    // 4. Incompatible pipeline panels must NOT exist in the DOM
    expect(wrapper.find('.tab-query').exists()).toBe(false);
    expect(wrapper.find('.tab-options').exists()).toBe(false);
    expect(wrapper.find('.tab-buffer').exists()).toBe(false);

    // 5. Navigate to "Code Preview" (index 2 in count mode)
    await countTabs[2].trigger('click');
    expect(wrapper.find('.tab-preview').exists()).toBe(true);

    // 6. Toggle Operation Mode back to "Mirror Pipeline"
    const pipelineRadio = wrapper.findAll('.mode-radio-label input[type="radio"]')[0];
    await pipelineRadio.setChecked(true);
    await wrapper.vm.$nextTick();

    // 7. First available tab ('Table') must be active again
    const restoredPipelineTabs = wrapper.findAll('.builder-tab-btn');
    expect(restoredPipelineTabs[0].classes()).toContain('active');
    expect(wrapper.find('.tab-table').exists()).toBe(true);
  });

  it('scrolls pipeline to end when clicking Add table after timeout', async () => {
    jest.useFakeTimers();

    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'database-builder' }
      }
    });

    const wrapper = mount(DatabaseBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    const scrollWrapper = wrapper.find('.pipeline-scroll-wrapper');
    const scrollToSpy = jest.fn();
    scrollWrapper.element.scrollTo = scrollToSpy;
    Object.defineProperty(scrollWrapper.element, 'scrollWidth', { value: 920, configurable: true });

    const addBtn = wrapper.find('.add-step-btn');
    await addBtn.trigger('click');

    expect(wrapper.findAll('.step-pill').length).toBe(2);

    // Fast-forward timeout
    jest.advanceTimersByTime(100);

    expect(scrollToSpy).toHaveBeenCalledWith({ left: 920, behavior: 'smooth' });

    wrapper.unmount();
    jest.useRealTimers();
  });

  it('re-evaluates pipeline scroll container overflow and enables scroll buttons when alternating mode', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'database-builder' }
      }
    });

    const wrapper = mount(DatabaseBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    // Add 3 steps
    await wrapper.find('.add-step-btn').trigger('click');
    await wrapper.find('.add-step-btn').trigger('click');

    const vm = wrapper.vm;
    const scrollEl = wrapper.find('.pipeline-scroll-wrapper').element;
    Object.defineProperty(scrollEl, 'scrollWidth', { value: 1000, configurable: true });
    Object.defineProperty(scrollEl, 'clientWidth', { value: 400, configurable: true });
    Object.defineProperty(scrollEl, 'scrollLeft', { value: 50, configurable: true, writable: true });

    // Manually trigger overflow check
    scrollEl.dispatchEvent(new Event('scroll'));
    await wrapper.vm.$nextTick();

    expect(wrapper.find('.btn-scroll-left').exists()).toBe(true);
    expect(wrapper.find('.btn-scroll-right').exists()).toBe(true);

    // Toggle to Direct Count
    const countRadio = wrapper.findAll('.mode-radio-label input[type="radio"]')[1];
    await countRadio.setChecked(true);
    await wrapper.vm.$nextTick();

    // Pipeline bar should be unmounted
    expect(wrapper.find('.database-builder-pipeline-bar').exists()).toBe(false);

    // Toggle back to Mirror Pipeline
    const pipelineRadio = wrapper.findAll('.mode-radio-label input[type="radio"]')[0];
    await pipelineRadio.setChecked(true);
    await wrapper.vm.$nextTick();

    // Pipeline bar is mounted again
    expect(wrapper.find('.database-builder-pipeline-bar').exists()).toBe(true);

    const newScrollEl = wrapper.find('.pipeline-scroll-wrapper').element;
    Object.defineProperty(newScrollEl, 'scrollWidth', { value: 1000, configurable: true });
    Object.defineProperty(newScrollEl, 'clientWidth', { value: 400, configurable: true });
    Object.defineProperty(newScrollEl, 'scrollLeft', { value: 0, configurable: true, writable: true });

    newScrollEl.dispatchEvent(new Event('scroll'));
    await wrapper.vm.$nextTick();

    // Scroll buttons should exist and right button should be enabled
    const rightBtn = wrapper.find('.btn-scroll-right');
    expect(rightBtn.exists()).toBe(true);
    expect(rightBtn.attributes('disabled')).toBeUndefined();

    // Clicking scroll right should call scrollBy
    const scrollBySpy = jest.fn();
    newScrollEl.scrollBy = scrollBySpy;
    await rightBtn.trigger('click');
    expect(scrollBySpy).toHaveBeenCalledWith({ left: 140, behavior: 'smooth' });

    wrapper.unmount();
  });

  it('renders active dot on Options tab when options are modified', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'database-builder' }
      }
    });

    const wrapper = mount(DatabaseBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    const optionsTab = wrapper.findAll('.builder-tab-btn')[2];
    expect(optionsTab.text()).toContain('Options');
    expect(optionsTab.find('.tab-active-dot').exists()).toBe(false);

    // Modify select
    wrapper.vm.steps[0].options.select = 'ID, post_title';
    await wrapper.vm.$nextTick();
    expect(optionsTab.find('.tab-active-dot').exists()).toBe(true);

    // Clear select
    wrapper.vm.steps[0].options.select = '';
    await wrapper.vm.$nextTick();
    expect(optionsTab.find('.tab-active-dot').exists()).toBe(false);

    // Modify limit
    wrapper.vm.steps[0].options.limit = 45;
    await wrapper.vm.$nextTick();
    expect(optionsTab.find('.tab-active-dot').exists()).toBe(true);

    // Clear limit
    wrapper.vm.steps[0].options.limit = '';
    await wrapper.vm.$nextTick();
    expect(optionsTab.find('.tab-active-dot').exists()).toBe(false);

    // Modify offset
    wrapper.vm.steps[0].options.offset = 1;
    await wrapper.vm.$nextTick();
    expect(optionsTab.find('.tab-active-dot').exists()).toBe(true);

    // Clear offset
    wrapper.vm.steps[0].options.offset = '';
    await wrapper.vm.$nextTick();
    expect(optionsTab.find('.tab-active-dot').exists()).toBe(false);

    // Modify orderbyCol
    wrapper.vm.steps[0].options.orderbyCol = 'post_date';
    await wrapper.vm.$nextTick();
    expect(optionsTab.find('.tab-active-dot').exists()).toBe(true);

    // Clear orderbyCol
    wrapper.vm.steps[0].options.orderbyCol = '';
    await wrapper.vm.$nextTick();
    expect(optionsTab.find('.tab-active-dot').exists()).toBe(false);

    // Modify orderbyDir to DESC
    wrapper.vm.steps[0].options.orderbyDir = 'DESC';
    await wrapper.vm.$nextTick();
    expect(optionsTab.find('.tab-active-dot').exists()).toBe(true);

    // Reset orderbyDir to ASC
    wrapper.vm.steps[0].options.orderbyDir = 'ASC';
    await wrapper.vm.$nextTick();
    expect(optionsTab.find('.tab-active-dot').exists()).toBe(false);

    wrapper.unmount();
  });

  it('renders active dot on Buffer Safety tab only when custom values differ from defaults or limit exceeds 1000', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'database-builder' }
      }
    });

    const wrapper = mount(DatabaseBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    const bufferTab = wrapper.findAll('.builder-tab-btn')[3];
    expect(bufferTab.text()).toContain('Buffer Safety');
    expect(bufferTab.find('.tab-active-dot').exists()).toBe(false);

    // Switch to Buffer tab and check "Custom Buffer Size"
    await bufferTab.trigger('click');
    const bufferCheckbox = wrapper.find('.tab-buffer input[type="checkbox"]');
    await bufferCheckbox.setChecked(true);
    await wrapper.vm.$nextTick();

    // Enabling checkbox without custom inputs must NOT show the dot
    expect(bufferTab.find('.tab-active-dot').exists()).toBe(false);

    // Entering default values must NOT show the dot
    wrapper.vm.buffer.rows = 1000;
    wrapper.vm.buffer.memory = 50;
    wrapper.vm.buffer.phpOverhead = 3.5;
    await wrapper.vm.$nextTick();
    expect(bufferTab.find('.tab-active-dot').exists()).toBe(false);

    // Modifying rows to custom value (2000)
    wrapper.vm.buffer.rows = 2000;
    await wrapper.vm.$nextTick();
    expect(bufferTab.find('.tab-active-dot').exists()).toBe(true);

    // Resetting rows back to 1000
    wrapper.vm.buffer.rows = 1000;
    await wrapper.vm.$nextTick();
    expect(bufferTab.find('.tab-active-dot').exists()).toBe(false);

    // Modifying memory to custom value (100)
    wrapper.vm.buffer.memory = 100;
    await wrapper.vm.$nextTick();
    expect(bufferTab.find('.tab-active-dot').exists()).toBe(true);

    // Resetting memory back to empty
    wrapper.vm.buffer.memory = '';
    await wrapper.vm.$nextTick();
    expect(bufferTab.find('.tab-active-dot').exists()).toBe(false);

    // Modifying phpOverhead to custom value (4.0)
    wrapper.vm.buffer.phpOverhead = 4.0;
    await wrapper.vm.$nextTick();
    expect(bufferTab.find('.tab-active-dot').exists()).toBe(true);

    // Resetting phpOverhead back to 3.5
    wrapper.vm.buffer.phpOverhead = 3.5;
    await wrapper.vm.$nextTick();
    expect(bufferTab.find('.tab-active-dot').exists()).toBe(false);

    // Step limit exceeding 1000 auto-requires buffer dot
    wrapper.vm.steps[0].options.limit = 2500;
    await wrapper.vm.$nextTick();
    expect(bufferTab.find('.tab-active-dot').exists()).toBe(true);

    wrapper.unmount();
  });

  it('handles filter value type switching between text, datetime, and timestamp', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'database-builder' }
      }
    });

    const wrapper = mount(DatabaseBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    // Go to filters tab
    const tabs = wrapper.findAll('.builder-tab-btn');
    await tabs[1].trigger('click');

    // By default, the type trigger badge is "Txt"
    const triggerBtn = wrapper.find('.val-type-trigger-btn');
    expect(triggerBtn.exists()).toBe(true);
    expect(triggerBtn.text()).toContain('Txt');

    // Click trigger button to open dropdown
    await triggerBtn.trigger('click');
    expect(wrapper.find('.val-type-dropdown-menu').exists()).toBe(true);

    const menuItems = wrapper.findAll('.val-type-dropdown-menu .dropdown-item');
    expect(menuItems.length).toBe(3);
    expect(menuItems[0].text()).toContain('Normal text');
    expect(menuItems[1].text()).toContain('Datetime');
    expect(menuItems[2].text()).toContain('Timestamp');

    // Select Datetime
    await menuItems[1].trigger('click');
    expect(wrapper.vm.steps[0].filters[0].valueType).toBe('datetime');
    expect(triggerBtn.text()).toContain('Date');

    // Now input is datetime-local with step="1"
    const datetimeInput = wrapper.find('input[type="datetime-local"]');
    expect(datetimeInput.exists()).toBe(true);
    expect(datetimeInput.attributes('step')).toBe('1');

    // Inputting a datetime value formats it as MySQL DATETIME
    await datetimeInput.setValue('2026-10-22T14:30');
    expect(wrapper.vm.steps[0].filters[0].value).toBe('2026-10-22 14:30:00');

    // Re-open type dropdown via trigger button
    await triggerBtn.trigger('click');
    expect(wrapper.find('.val-type-dropdown-menu').exists()).toBe(true);

    // Switch to Timestamp
    const menuItemsAfterReopen = wrapper.findAll('.val-type-dropdown-menu .dropdown-item');
    await menuItemsAfterReopen[2].trigger('click');
    expect(wrapper.vm.steps[0].filters[0].valueType).toBe('timestamp');
    expect(triggerBtn.text()).toContain('Time');

    // Value was converted to UNIX timestamp number
    expect(typeof wrapper.vm.steps[0].filters[0].value).toBe('number');

    // Test BETWEEN operator
    const operatorSelect = wrapper.find('.col-op select');
    await operatorSelect.setValue('$between');
    expect(wrapper.find('.between-inputs-wrapper').exists()).toBe(true);

    // Both Min and Max inputs are datetime-local when valueType is timestamp
    const betweenInputs = wrapper.findAll('.between-inputs-wrapper input[type="datetime-local"]');
    expect(betweenInputs.length).toBe(2);

    wrapper.unmount();
  });

  it('handles Disable table prefix and Table Prefix options correctly', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'database-builder' }
      }
    });

    const wrapper = mount(DatabaseBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    // Checkbox is present in Table tab
    const disablePrefixCheckbox = wrapper.find('.tab-table input[type="checkbox"]');
    expect(disablePrefixCheckbox.exists()).toBe(true);
    expect(wrapper.vm.steps[0].options.disablePrefix).toBe(false);

    // Toggle disablePrefix
    await disablePrefixCheckbox.setChecked(true);
    expect(wrapper.vm.steps[0].options.disablePrefix).toBe(true);

    // Set table name and verify alias input dynamic placeholder
    wrapper.vm.steps[0].table = 'custom_table';
    await wrapper.vm.$nextTick();
    const aliasInput = wrapper.find('.tab-table input[placeholder*="custom_table"]');
    expect(aliasInput.exists()).toBe(true);
    expect(aliasInput.attributes('placeholder')).toContain('custom_table (default)');

    // Switch to Options tab
    const tabs = wrapper.findAll('.builder-tab-btn');
    await tabs[2].trigger('click');
    expect(wrapper.find('.tab-options').exists()).toBe(true);

    // Prefix input should be disabled
    const prefixInput = wrapper.findAll('.tab-options input')[1];
    expect(prefixInput.attributes('disabled')).toBeDefined();
    expect(prefixInput.attributes('placeholder')).toContain('Prefix disabled');

    // Switch back and uncheck
    await tabs[0].trigger('click');
    await disablePrefixCheckbox.setChecked(false);
    expect(wrapper.vm.steps[0].options.disablePrefix).toBe(false);

    // Go to Options tab - prefix input is enabled
    await tabs[2].trigger('click');
    const prefixInputReenabled = wrapper.findAll('.tab-options input')[1];
    expect(prefixInputReenabled.attributes('disabled')).toBeUndefined();
    expect(prefixInputReenabled.attributes('placeholder')).toContain('Default');

    // Enter a custom prefix
    await prefixInputReenabled.setValue('wp_2_');
    expect(wrapper.vm.steps[0].options.prefix).toBe('wp_2_');
    expect(wrapper.find('.builder-tab-btn .tab-active-dot').exists()).toBe(true);

    wrapper.unmount();
  });

  it('supports OR filter groups, renders dividers, and generates canonical OR elscript syntax', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'database-builder' }
      }
    });

    const wrapper = mount(DatabaseBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    // Configure table name
    const tableInput = wrapper.find('.tab-table input[type="text"]');
    await tableInput.setValue('posts');

    // Switch to Filters tab
    const tabs = wrapper.findAll('.builder-tab-btn');
    await tabs[1].trigger('click');
    expect(wrapper.find('.tab-filters').exists()).toBe(true);

    // By default: 1 group card, no OR badge between groups, no remove group button
    expect(wrapper.findAll('.filter-group-card').length).toBe(1);
    expect(wrapper.find('.or-badge').exists()).toBe(false);
    expect(wrapper.find('.or-divider-simple').exists()).toBe(true);
    expect(wrapper.find('.btn-remove-group').exists()).toBe(false);

    // Group 1 condition 1
    const colInput = wrapper.find('.filter-group-card .col-col input');
    await colInput.setValue('post_type');
    const valInput = wrapper.find('.filter-group-card .col-val input');
    await valInput.setValue('post');

    // Tab badge should show 1
    expect(wrapper.find('.builder-tab-btn .tab-count-badge').text()).toBe('1');

    // Generated code with 1 group is a plain Object (AND)
    expect(wrapper.vm.generatedCode).toContain('post_type: \'post\'');
    expect(wrapper.vm.generatedCode).not.toContain('[\n');

    // Click "+ Add OR Group" button
    const addOrBtn = wrapper.find('.add-or-group-btn');
    expect(addOrBtn.exists()).toBe(true);
    await addOrBtn.trigger('click');

    // Now there are 2 group cards, 1 OR divider with OR badge, and Remove Group buttons in actions bars
    expect(wrapper.findAll('.filter-group-card').length).toBe(2);
    expect(wrapper.find('.or-badge').exists()).toBe(true);
    expect(wrapper.find('.or-badge').text()).toBe('OR');

    const removeBtns = wrapper.findAll('.group-actions-bar .btn-remove-group');
    expect(removeBtns.length).toBe(2);
    expect(removeBtns[0].text()).toContain('Remove Group');
    expect(removeBtns[1].text()).toContain('Remove Group');

    // Configure Group 2 with a condition: post_type = 'page'
    const group2Cards = wrapper.findAll('.filter-group-card');
    const group2Col = group2Cards[1].find('.col-col input');
    await group2Col.setValue('post_type');
    const group2Val = group2Cards[1].find('.col-val input');
    await group2Val.setValue('page');

    // Total active filter badge across both groups is 2
    expect(wrapper.find('.builder-tab-btn .tab-count-badge').text()).toBe('2');

    // Generated code now produces an Array of Objects (OR clauses)
    const code = wrapper.vm.generatedCode;
    expect(code).toContain('Database');
    expect(code).toContain('.mirror(');
    expect(code).toContain('\'posts\'');
    expect(code).toContain('post_type: \'post\'');
    expect(code).toContain('post_type: \'page\'');

    // Test removing an OR group
    await removeBtns[1].trigger('click');

    // Back to 1 group: divider badge and remove buttons disappear
    expect(wrapper.findAll('.filter-group-card').length).toBe(1);
    expect(wrapper.find('.or-badge').exists()).toBe(false);
    expect(wrapper.find('.or-divider-simple').exists()).toBe(true);
    expect(wrapper.find('.btn-remove-group').exists()).toBe(false);

    wrapper.unmount();
  });

  it('scrolls tab panel vertically to bottom when clicking Add OR Group after timeout', async () => {
    jest.useFakeTimers();

    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'database-builder' }
      }
    });

    const wrapper = mount(DatabaseBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    const tabs = wrapper.findAll('.builder-tab-btn');
    await tabs[1].trigger('click');

    const tabPanel = wrapper.find('.database-builder-tab-panel');
    const scrollToSpy = jest.fn();
    tabPanel.element.scrollTo = scrollToSpy;
    Object.defineProperty(tabPanel.element, 'scrollHeight', { value: 1450, configurable: true });

    const addOrBtn = wrapper.find('.add-or-group-btn');
    await addOrBtn.trigger('click');

    expect(wrapper.findAll('.filter-group-card').length).toBe(2);

    // Fast-forward timeout
    jest.advanceTimersByTime(100);

    expect(scrollToSpy).toHaveBeenCalledWith({ top: 1450, behavior: 'smooth' });

    wrapper.unmount();
    jest.useRealTimers();
  });

  it('correctly parses IN and NOT IN conditions with quotes and commas without double-escaping', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'database-builder' }
      }
    });

    const wrapper = mount(DatabaseBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    // Configure table
    const tableInput = wrapper.find('.tab-table input[type="text"]');
    await tableInput.setValue('posts');

    // Switch to Conditions tab
    const tabs = wrapper.findAll('.builder-tab-btn');
    await tabs[1].trigger('click');

    // Filter 1: operator IN, value with quotes containing comma
    const card = wrapper.find('.filter-group-card');
    const colInput = card.find('.col-col input');
    await colInput.setValue('test_a');

    const opSelect = card.find('.col-op select');
    await opSelect.setValue('$in');

    const valInput = card.find('.col-val input');
    await valInput.setValue('foo, bar, \'it is tricky to use \\, commas\'');

    expect(wrapper.vm.generatedCode).toContain('\'foo\'');
    expect(wrapper.vm.generatedCode).toContain('\'bar\'');
    expect(wrapper.vm.generatedCode).toContain('\'it is tricky to use , commas\'');
    expect(wrapper.vm.generatedCode).not.toContain('\\\'it is tricky');

    // Switch to NOT IN with double quotes containing comma
    await opSelect.setValue('$notIn');
    await valInput.setValue('"this,is a problem"');

    expect(wrapper.vm.generatedCode).toContain('\'this,is a problem\'');
    expect(wrapper.vm.generatedCode).not.toContain('"this');

    wrapper.unmount();
  });

  it('opens Bulk List Editor mini-modal via ellipsis button, supports multiline editing, deduplication and automatic quoting', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'database-builder' }
      }
    });

    const wrapper = mount(DatabaseBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    // Configure table
    const tableInput = wrapper.find('.tab-table input[type="text"]');
    await tableInput.setValue('posts');

    // Switch to Conditions tab
    const tabs = wrapper.findAll('.builder-tab-btn');
    await tabs[1].trigger('click');

    const card = wrapper.find('.filter-group-card');
    const colInput = card.find('.col-col input');
    await colInput.setValue('post_status');

    // Operator is '=': ellipsis trigger should NOT exist
    expect(card.find('.btn-list-editor-trigger').exists()).toBe(false);

    // Change operator to IN
    const opSelect = card.find('.col-op select');
    await opSelect.setValue('$in');

    // Ellipsis button should now exist with codicon-ellipsis
    const ellipsisBtn = card.find('.btn-list-editor-trigger');
    expect(ellipsisBtn.exists()).toBe(true);
    expect(ellipsisBtn.find('.codicon.codicon-ellipsis').exists()).toBe(true);

    // Initial value with commas and quotes
    const valInput = card.find('.col-val input');
    await valInput.setValue('foo, \'bar\', "this,is,a,test"');

    // Mini-modal is closed initially
    expect(wrapper.find('.list-editor-modal-overlay').exists()).toBe(false);

    // Click ellipsis button to open modal
    await ellipsisBtn.trigger('click');
    expect(wrapper.find('.list-editor-modal-overlay').exists()).toBe(true);
    expect(wrapper.find('.list-editor-modal-container .modal-header h2').text()).toContain('post_status (IN)');

    // Textarea should contain each item on its own clean line
    const textarea = wrapper.find('.list-editor-textarea');
    expect(textarea.element.value).toBe('foo\nbar\nthis,is,a,test');
    expect(wrapper.find('.list-editor-items-count').text()).toBe('3 items');

    // Edit textarea content with duplicates and a comma item
    await textarea.setValue('alpha\nbeta\nalpha\ngamma, with, comma\n\n');
    expect(wrapper.find('.list-editor-items-count').text()).toBe('4 items');

    // Check Remove duplicates
    const checkboxes = wrapper.findAll('.list-editor-option-label input[type="checkbox"]');
    // checkbox 0 is trimLines (true by default), checkbox 1 is deduplicate
    await checkboxes[1].setChecked(true);

    // Click Apply
    const applyBtn = wrapper.findAll('.list-editor-modal-footer button')[1];
    await applyBtn.trigger('click');

    // Mini-modal should now be closed
    expect(wrapper.find('.list-editor-modal-overlay').exists()).toBe(false);

    // Value input should have deduplicated and auto-quoted gamma, with, comma
    expect(valInput.element.value).toBe('alpha, beta, \'gamma, with, comma\'');

    // Generated code reflects applied items
    expect(wrapper.vm.generatedCode).toContain('\'alpha\'');
    expect(wrapper.vm.generatedCode).toContain('\'beta\'');
    expect(wrapper.vm.generatedCode).toContain('\'gamma, with, comma\'');

    // Test Cancel button behavior
    await ellipsisBtn.trigger('click');
    expect(wrapper.find('.list-editor-modal-overlay').exists()).toBe(true);
    await textarea.setValue('discarded');
    const cancelBtn = wrapper.findAll('.list-editor-modal-footer button')[0];
    await cancelBtn.trigger('click');
    expect(wrapper.find('.list-editor-modal-overlay').exists()).toBe(false);
    expect(valInput.element.value).toBe('alpha, beta, \'gamma, with, comma\'');

    // Verify that clicking outside (overlay) or pressing Escape does NOT close the modal
    await ellipsisBtn.trigger('click');
    expect(wrapper.find('.list-editor-modal-overlay').exists()).toBe(true);
    await wrapper.find('.list-editor-modal-overlay').trigger('click');
    expect(wrapper.find('.list-editor-modal-overlay').exists()).toBe(true);
    await wrapper.find('.list-editor-textarea').trigger('keydown.esc');
    expect(wrapper.find('.list-editor-modal-overlay').exists()).toBe(true);

    // Test modal-close X button behavior
    const closeXBtn = wrapper.find('.list-editor-modal-container .modal-close');
    await closeXBtn.trigger('click');
    expect(wrapper.find('.list-editor-modal-overlay').exists()).toBe(false);

    wrapper.unmount();
  });

  it('renders duplicate column warning below the filter table in an AND group, but not across separate OR groups', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'database-builder' }
      }
    });

    const wrapper = mount(DatabaseBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    // Switch to Conditions tab
    const tabs = wrapper.findAll('.builder-tab-btn');
    await tabs[1].trigger('click');

    // Initially 1 row, no warning
    expect(wrapper.find('.filter-group-warning').exists()).toBe(false);

    // Set first row column to post_status
    const colInputs = wrapper.findAll('.col-col input');
    await colInputs[0].setValue('post_status');
    expect(wrapper.find('.filter-group-warning').exists()).toBe(false);

    // Click Add Filter in group 0
    await wrapper.find('.group-actions-bar button:last-child').trigger('click');
    expect(wrapper.findAll('.col-col input').length).toBe(2);

    // Set second row column to post_status (same group)
    const updatedColInputs = wrapper.findAll('.col-col input');
    await updatedColInputs[1].setValue('post_status');
    expect(wrapper.find('.filter-group-warning').exists()).toBe(true);
    expect(wrapper.find('.filter-group-warning p').text()).toContain('Duplicate column detected');

    // Case-insensitivity check: 'POST_STATUS' vs 'post_status'
    await updatedColInputs[1].setValue('POST_STATUS');
    expect(wrapper.find('.filter-group-warning').exists()).toBe(true);

    // Disabling one of the duplicate filters hides the warning
    const checkboxes = wrapper.findAll('.col-check input[type="checkbox"]');
    await checkboxes[1].setChecked(false);
    expect(wrapper.find('.filter-group-warning').exists()).toBe(false);

    // Re-enabling shows it again
    await checkboxes[1].setChecked(true);
    expect(wrapper.find('.filter-group-warning').exists()).toBe(true);

    // Changing second row column to post_type removes warning
    await updatedColInputs[1].setValue('post_type');
    expect(wrapper.find('.filter-group-warning').exists()).toBe(false);

    // Now add a separate OR group with post_status in both groups
    await wrapper.find('.add-or-group-btn').trigger('click');
    await flushPromises();

    // Group 1 row 0 has post_status: no duplicate warning in either group
    const allColInputs = wrapper.findAll('.col-col input');
    await allColInputs[2].setValue('post_status');
    expect(wrapper.findAll('.filter-group-warning').length).toBe(0);

    // But if Group 1 gets another post_status row, only Group 1 shows the warning
    const addFilterBtns = wrapper.findAll('.group-actions-bar button:last-child');
    await addFilterBtns[1].trigger('click');
    const group1Inputs = wrapper.findAll('.filter-group-card')[1].findAll('.col-col input');
    await group1Inputs[1].setValue('post_status');

    // Exactly 1 warning exists, inside the second filter-group-card
    const groupCards = wrapper.findAll('.filter-group-card');
    expect(groupCards[0].find('.filter-group-warning').exists()).toBe(false);
    expect(groupCards[1].find('.filter-group-warning').exists()).toBe(true);

    wrapper.unmount();
  });
});




