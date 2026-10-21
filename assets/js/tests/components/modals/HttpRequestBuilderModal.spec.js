import { mount } from '@vue/test-utils';
import { createTestingPinia } from '@pinia/testing';
import HttpRequestBuilderModal from '../../../components/modals/HttpRequestBuilderModal.vue';
import { useUiStore } from '../../../stores/ui';

describe('HttpRequestBuilderModal.vue', () => {
  beforeEach(() => {
    window.el_settings = {
      settings: {}
    };
  });

  it('renders correctly when open', () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'http-builder' }
      }
    });

    const wrapper = mount(HttpRequestBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    expect(wrapper.find('#http-request-builder-modal').exists()).toBe(true);
    expect(wrapper.find('.modal-header h2').text()).toBe('HTTP Request Builder');
    expect(wrapper.find('.request-url-input').element.value).toBe('https://example.com');
    expect(wrapper.find('.request-method-trigger').text()).toContain('GET');

    const tabs = wrapper.findAll('.builder-tab-btn');
    expect(tabs.length).toBe(6);
    expect(tabs[0].text()).toContain('Params');
    expect(tabs[1].text()).toContain('Authorization');
    expect(tabs[2].text()).toContain('Headers');
    expect(tabs[3].text()).toContain('Body');
    expect(tabs[4].text()).toContain('Settings');
    expect(tabs[5].text()).toContain('Code Preview');
  });

  it('does not render when closed', () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: null }
      }
    });

    const wrapper = mount(HttpRequestBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    expect(wrapper.find('#http-request-builder-modal').exists()).toBe(false);
  });

  it('switches tabs and reflects active pane', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'http-builder' }
      }
    });

    const wrapper = mount(HttpRequestBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    expect(wrapper.find('.tab-params').exists()).toBe(true);

    const tabs = wrapper.findAll('.builder-tab-btn');
    await tabs[1].trigger('click'); // Authorization
    expect(wrapper.find('.tab-auth').exists()).toBe(true);

    await tabs[2].trigger('click'); // Headers
    expect(wrapper.find('.tab-headers').exists()).toBe(true);

    await tabs[5].trigger('click'); // Preview
    expect(wrapper.find('.tab-preview').exists()).toBe(true);
    expect(wrapper.find('.tab-preview .code-preview-cm-editor').exists()).toBe(true);
    expect(wrapper.find('.tab-preview .table-actions-bar button').text()).toContain('Copy Code');
  });

  it('adds and removes parameter rows and scrolls to bottom', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'http-builder' }
      }
    });

    const wrapper = mount(HttpRequestBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    const panelEl = wrapper.find('.http-builder-tab-panel').element;
    panelEl.scrollTo = jest.fn();

    expect(wrapper.findAll('.tab-params .kv-row').length).toBe(1);

    const addBtn = wrapper.find('.tab-params .table-actions-bar button');
    await addBtn.trigger('click');
    expect(wrapper.findAll('.tab-params .kv-row').length).toBe(2);
    expect(panelEl.scrollTo).toHaveBeenCalledWith(expect.objectContaining({ behavior: 'smooth' }));

    const deleteBtn = wrapper.find('.tab-params .btn-row-delete');
    await deleteBtn.trigger('click');
    expect(wrapper.findAll('.tab-params .kv-row').length).toBe(1);
  });

  it('disables Body tab when method is GET and shows notice', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'http-builder' }
      }
    });

    const wrapper = mount(HttpRequestBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    const tabs = wrapper.findAll('.builder-tab-btn');
    await tabs[3].trigger('click'); // Body tab
    expect(wrapper.find('.body-disabled-notice').exists()).toBe(true);
    expect(wrapper.find('.body-disabled-notice').text()).toContain('Body payload is only supported for POST requests');

    // Switch method to POST via custom dropdown
    await wrapper.find('.request-method-trigger').trigger('click');
    expect(wrapper.find('.request-method-dropdown-menu').exists()).toBe(true);
    const postOption = wrapper.findAll('.method-option').filter(w => w.text().includes('POST'))[0];
    await postOption.trigger('click');

    expect(wrapper.find('.request-method-dropdown-menu').exists()).toBe(false);
    expect(wrapper.find('.request-method-trigger').text()).toContain('POST');
    expect(wrapper.find('.body-type-toggles').exists()).toBe(true);
    // When body type is 'none', it shows the notice with codicon
    expect(wrapper.find('.body-disabled-notice').exists()).toBe(true);
    expect(wrapper.find('.body-disabled-notice .codicon-info').exists()).toBe(true);
    expect(wrapper.find('.body-disabled-notice').text()).toContain('This request does not have a body.');
  });

  it('handles JSON body with CodeMirror editor and Format JSON button below', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'http-builder' }
      }
    });

    const wrapper = mount(HttpRequestBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    // Switch method to POST
    await wrapper.find('.request-method-trigger').trigger('click');
    const postOption = wrapper.findAll('.method-option').filter(w => w.text().includes('POST'))[0];
    await postOption.trigger('click');

    // Go to Body tab
    const tabs = wrapper.findAll('.builder-tab-btn');
    await tabs[3].trigger('click');

    // Select JSON radio
    const jsonRadio = wrapper.findAll('.body-type-toggles input[type="radio"]').filter(w => w.element.value === 'json')[0];
    await jsonRadio.setChecked();

    // Verify 'none' notice is gone, editor container exists, and Format JSON button is inside table-actions-bar below editor
    expect(wrapper.find('.body-disabled-notice').exists()).toBe(false);
    expect(wrapper.find('.body-editor-container .body-cm-editor').exists()).toBe(true);
    expect(wrapper.find('.body-editor-container .table-actions-bar .format-json-btn').exists()).toBe(true);
    expect(wrapper.find('.body-editor-container .table-actions-bar .format-json-btn').text()).toContain('Format JSON');

    // Click format JSON button
    await wrapper.find('.format-json-btn').trigger('click');
  });

  it('validates JSON syntax and displays console-styled error notice when invalid', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'http-builder' }
      }
    });

    const wrapper = mount(HttpRequestBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    // Switch method to POST
    await wrapper.find('.request-method-trigger').trigger('click');
    const postOption = wrapper.findAll('.method-option').filter(w => w.text().includes('POST'))[0];
    await postOption.trigger('click');

    // Go to Body tab and select JSON
    const tabs = wrapper.findAll('.builder-tab-btn');
    await tabs[3].trigger('click');
    const jsonRadio = wrapper.findAll('.body-type-toggles input[type="radio"]').filter(w => w.element.value === 'json')[0];
    await jsonRadio.setChecked();

    // Default JSON is valid
    expect(wrapper.find('.json-syntax-error').exists()).toBe(false);
    expect(wrapper.find('.body-cm-editor').classes()).not.toContain('has-error');

    // Set invalid JSON
    wrapper.vm.body.jsonText = '{\n  "key": "value"\n  invalid syntax\n}';
    await wrapper.vm.$nextTick();

    expect(wrapper.find('.json-syntax-error').exists()).toBe(true);
    expect(wrapper.find('.json-syntax-error .codicon-close').exists()).toBe(true);
    expect(wrapper.find('.json-syntax-error').text().length).toBeGreaterThan(0);
    expect(wrapper.find('.body-cm-editor').classes()).toContain('has-error');

    // Correct the JSON
    wrapper.vm.body.jsonText = '{\n  "key": "value"\n}';
    await wrapper.vm.$nextTick();

    expect(wrapper.find('.json-syntax-error').exists()).toBe(false);
    expect(wrapper.find('.body-cm-editor').classes()).not.toContain('has-error');
  });

  it('handles Raw body with CodeMirror editor and Content-Type dropdown', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'http-builder' }
      }
    });

    const wrapper = mount(HttpRequestBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    // Switch method to POST
    await wrapper.find('.request-method-trigger').trigger('click');
    const postOption = wrapper.findAll('.method-option').filter(w => w.text().includes('POST'))[0];
    await postOption.trigger('click');

    // Go to Body tab
    const tabs = wrapper.findAll('.builder-tab-btn');
    await tabs[3].trigger('click');

    // Select Raw radio
    const rawRadio = wrapper.findAll('.body-type-toggles input[type="radio"]').filter(w => w.element.value === 'raw')[0];
    await rawRadio.setChecked();

    // Verify raw editor container and content type select exist
    expect(wrapper.find('.body-raw-container .body-cm-editor').exists()).toBe(true);
    expect(wrapper.find('.body-raw-container .raw-type-select').exists()).toBe(true);

    // Switch content type to XML
    const select = wrapper.find('.body-raw-container .raw-type-select');
    await select.setValue('application/xml');
    expect(select.element.value).toBe('application/xml');
  });

  it('toggles method dropdown and selects HEAD', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'http-builder' }
      }
    });

    const wrapper = mount(HttpRequestBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    expect(wrapper.find('.request-method-dropdown-menu').exists()).toBe(false);

    // Click trigger to open dropdown
    const trigger = wrapper.find('.request-method-trigger');
    await trigger.trigger('click');
    expect(wrapper.find('.request-method-dropdown-menu').exists()).toBe(true);

    const options = wrapper.findAll('.method-option');
    expect(options.length).toBe(3);

    // Click HEAD option
    const headOption = options.filter(w => w.text().includes('HEAD'))[0];
    await headOption.trigger('click');

    expect(wrapper.find('.request-method-dropdown-menu').exists()).toBe(false);
    expect(wrapper.find('.request-method-trigger').text()).toContain('HEAD');
  });

  it('triggers snippet insertion on Insert click', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'http-builder' }
      }
    });

    const wrapper = mount(HttpRequestBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    const uiStore = useUiStore();
    const insertBtn = wrapper.find('.http-builder-footer .btn-primary');
    await insertBtn.trigger('click');

    expect(uiStore.triggerSnippetInsert).toHaveBeenCalledWith('Http.get(\'https://example.com\')');
    expect(uiStore.closeModal).toHaveBeenCalled();
  });

  it('closes directly when not dirty', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'http-builder' }
      }
    });

    const wrapper = mount(HttpRequestBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    const uiStore = useUiStore();
    const cancelBtn = wrapper.find('.http-builder-footer .btn-default');
    await cancelBtn.trigger('click');

    expect(uiStore.showDialog).not.toHaveBeenCalled();
    expect(uiStore.closeModal).toHaveBeenCalled();
  });

  it('prompts confirm dialog on cancel when modified', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'http-builder' }
      }
    });

    const wrapper = mount(HttpRequestBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    const uiStore = useUiStore();
    uiStore.showDialog.mockResolvedValue(true);

    // Modify URL
    const urlInput = wrapper.find('.request-url-input');
    await urlInput.setValue('https://api.github.com/users');

    const cancelBtn = wrapper.find('.http-builder-footer .btn-default');
    await cancelBtn.trigger('click');

    expect(uiStore.showDialog).toHaveBeenCalled();
    expect(uiStore.closeModal).toHaveBeenCalled();
  });

  it('does not cause recursive loop when configuring API Key in Query Params', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'http-builder' }
      }
    });

    const wrapper = mount(HttpRequestBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    // Switch to Authorization tab
    const tabs = wrapper.findAll('.builder-tab-btn');
    await tabs[1].trigger('click'); // Auth tab

    // Select API Key
    const authTypeSelect = wrapper.find('.auth-select');
    await authTypeSelect.setValue('apikey');

    // Select Query Params
    const addToSelect = wrapper.find('.auth-field-addto select');
    await addToSelect.setValue('query');

    // Type "foo" and "bar" step-by-step
    const keyInput = wrapper.findAll('.auth-field input')[0];
    const valInput = wrapper.findAll('.auth-field input')[1];

    await keyInput.setValue('f');
    await keyInput.setValue('fo');
    await keyInput.setValue('foo');
    await valInput.setValue('b');
    await valInput.setValue('ba');
    await valInput.setValue('bar');

    // Verify URL does not explode recursively
    const urlInput = wrapper.find('.request-url-input');
    expect(urlInput.element.value).toBe('https://example.com?foo=bar');

    // Switch back to Params tab
    await tabs[0].trigger('click');
    const paramRows = wrapper.findAll('.tab-params .kv-row');
    expect(paramRows.length).toBe(1);
    expect(wrapper.find('.tab-params .kv-row .col-key input').element.value).toBe('');
  });

  it('applies elscript formatting to generated and inserted code', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'http-builder' }
      }
    });

    const wrapper = mount(HttpRequestBuilderModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str
        }
      }
    });

    const uiStore = useUiStore();

    // Configure a POST request
    await wrapper.find('.request-method-trigger').trigger('click');
    const postOption = wrapper.findAll('.method-option').filter(w => w.text().includes('POST'))[0];
    await postOption.trigger('click');

    // Switch to Preview tab
    const tabs = wrapper.findAll('.builder-tab-btn');
    await tabs[5].trigger('click');

    expect(wrapper.find('.tab-preview .code-preview-cm-editor').exists()).toBe(true);

    // Verify Copy Code button copies formatted code
    Object.assign(navigator, {
      clipboard: {
        writeText: jest.fn().mockResolvedValue(undefined)
      }
    });
    const copyBtn = wrapper.find('.tab-preview .table-actions-bar button');
    await copyBtn.trigger('click');
    expect(navigator.clipboard.writeText).toHaveBeenCalled();
    const copiedText = navigator.clipboard.writeText.mock.calls[0][0];
    expect(copiedText).toContain('Http.post(');

    // Verify Insert triggers insert with formatted code
    const insertBtn = wrapper.find('.http-builder-footer .btn-primary');
    await insertBtn.trigger('click');
    expect(uiStore.triggerSnippetInsert).toHaveBeenCalled();
    const insertedText = uiStore.triggerSnippetInsert.mock.calls[0][0];
    expect(insertedText).toContain('Http.post(');
  });
});
