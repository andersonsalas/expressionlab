import { mount } from '@vue/test-utils';
import { createTestingPinia } from '@pinia/testing';
import SettingsModal from '../../../components/modals/SettingsModal.vue';
import { useUiStore } from '../../../stores/ui';
import { useSettingsStore } from '../../../stores/settings';

describe('SettingsModal.vue', () => {
  it('renders correctly when open', () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'settings' },
        settings: {
          defaultTabMode: 'console',
          hasOutlineCache: false,
          isClearingCache: false,
        },
      },
    });

    const wrapper = mount(SettingsModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    expect(wrapper.find('#settings-modal').exists()).toBe(true);
    expect(wrapper.find('.modal-header h2').text()).toBe('Settings');
    expect(wrapper.find('.panel-section-header h4').text()).toBe('Default new tab layout');
    expect(wrapper.find('.form-help').exists()).toBe(true);
    expect(wrapper.find('.presets-grid').exists()).toBe(true);
    expect(wrapper.findAll('.preset-card').length).toBe(2);
    expect(wrapper.text()).toContain('Console');
    expect(wrapper.text()).toContain('Scratchpad');
    expect(wrapper.text()).toContain('Browser cache');
    expect(wrapper.find('.modal-close').exists()).toBe(true);

    const radioInputs = wrapper.findAll('input[type="radio"]');
    expect(radioInputs.length).toBe(2);
    expect(radioInputs[0].element.value).toBe('console');
    expect(radioInputs[1].element.value).toBe('scratchpad');

    // Button should be disabled since hasOutlineCache is false
    const clearBtn = wrapper.find('.settings-cache-actions button');
    expect(clearBtn.exists()).toBe(true);
    expect(clearBtn.text()).toBe('Clear cache');
    expect(clearBtn.attributes('disabled')).toBeDefined();
  });

  it('does not render when closed', () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: null },
      },
    });

    const wrapper = mount(SettingsModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    expect(wrapper.find('#settings-modal').exists()).toBe(false);
  });

  it('calls closeModal when close button is clicked', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'settings' },
      },
    });

    const wrapper = mount(SettingsModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    const uiStore = useUiStore();
    await wrapper.find('.modal-close').trigger('click');
    expect(uiStore.closeModal).toHaveBeenCalled();
  });

  it('enables Clear cache button when hasOutlineCache is true and triggers clearOutlineCache on click', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'settings' },
        settings: {
          defaultTabMode: 'console',
          hasOutlineCache: true,
          isClearingCache: false,
        },
      },
    });

    const wrapper = mount(SettingsModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    const settingsStore = useSettingsStore();
    const clearBtn = wrapper.find('.settings-cache-actions button');

    expect(clearBtn.attributes('disabled')).toBeUndefined();

    await clearBtn.trigger('click');
    expect(settingsStore.clearOutlineCache).toHaveBeenCalled();
  });

  it('changes defaultTabMode when radio option is selected', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'settings' },
        settings: {
          defaultTabMode: 'console',
          hasOutlineCache: false,
          isClearingCache: false,
        },
      },
    });

    const wrapper = mount(SettingsModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    const settingsStore = useSettingsStore();
    const radioInputs = wrapper.findAll('input[type="radio"]');

    // Select scratchpad
    await radioInputs[1].setValue(true);
    expect(settingsStore.setDefaultTabMode).toHaveBeenCalledWith('scratchpad');
  });

  it('handles outside click when closeOnOutsideClick is true', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'settings' },
      },
    });

    const wrapper = mount(SettingsModal, {
      props: {
        closeOnOutsideClick: true,
      },
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    const uiStore = useUiStore();
    await wrapper.find('#settings-modal').trigger('mousedown');
    expect(uiStore.closeModal).toHaveBeenCalled();
  });

  it('does not close on outside click when closeOnOutsideClick is false', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'settings' },
      },
    });

    const wrapper = mount(SettingsModal, {
      props: {
        closeOnOutsideClick: false,
      },
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    const uiStore = useUiStore();
    await wrapper.find('#settings-modal').trigger('mousedown');
    expect(uiStore.closeModal).not.toHaveBeenCalled();
  });

  it('renders Notebook radio option and ProLicenseSettings when addons are enabled', async () => {
    const prevAddons = global.__WITH_ADDONS__;
    global.__WITH_ADDONS__ = true;

    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'settings' },
        settings: {
          defaultTabMode: 'console',
          hasOutlineCache: false,
          isClearingCache: false,
        },
      },
    });

    const wrapper = mount(SettingsModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
        stubs: {
          ProLicenseSettings: { template: '<div class="pro-license-stub">License Settings</div>' },
        },
      },
    });

    const radioInputs = wrapper.findAll('input[type="radio"]');
    expect(radioInputs.length).toBe(3);
    expect(wrapper.findAll('.preset-card').length).toBe(3);
    expect(radioInputs[2].element.value).toBe('notebook');
    expect(wrapper.find('.pro-license-stub').exists()).toBe(true);

    const settingsStore = useSettingsStore();
    await radioInputs[2].setValue(true);
    expect(settingsStore.setDefaultTabMode).toHaveBeenCalledWith('notebook');

    // Also check License tab exists when addons are enabled
    const tabButtons = wrapper.findAll('.settings-tabs-header .builder-tab-btn');
    expect(tabButtons.length).toBe(3);
    expect(tabButtons[0].text()).toContain('General');
    expect(tabButtons[1].text()).toContain('Data');
    expect(tabButtons[2].text()).toContain('License');

    global.__WITH_ADDONS__ = prevAddons;
  });

  it('activates the requested tab when opened with modalData tab parameter', async () => {
    const prevAddons = global.__WITH_ADDONS__;
    global.__WITH_ADDONS__ = true;

    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'settings', activeModalData: { tab: 'license' } },
        settings: {
          defaultTabMode: 'console',
          hasOutlineCache: false,
          isClearingCache: false,
        },
      },
    });

    const wrapper = mount(SettingsModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
        stubs: {
          ProLicenseSettings: true,
        },
      },
    });

    const activeBtn = wrapper.find('.settings-tabs-header .builder-tab-btn.active');
    expect(activeBtn.text()).toContain('License');
    expect(wrapper.find('.tab-license').isVisible()).toBe(true);

    global.__WITH_ADDONS__ = prevAddons;
  });

  it('renders General and Data tabs by default (without addons) and allows switching tabs', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'settings' },
        settings: {
          defaultTabMode: 'console',
          hasOutlineCache: false,
          isClearingCache: false,
        },
      },
    });

    const wrapper = mount(SettingsModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    const tabButtons = wrapper.findAll('.settings-tabs-header .builder-tab-btn');
    expect(tabButtons.length).toBe(2);
    expect(tabButtons[0].text()).toContain('General');
    expect(tabButtons[1].text()).toContain('Data');
    expect(tabButtons[0].classes()).toContain('active');

    // General panel visible, data panel hidden
    expect(wrapper.find('.tab-general').isVisible()).toBe(true);
    expect(wrapper.find('.tab-data').isVisible()).toBe(false);

    // Switch to Data tab
    await tabButtons[1].trigger('click');
    expect(tabButtons[1].classes()).toContain('active');
    expect(tabButtons[0].classes()).not.toContain('active');
    expect(wrapper.find('.tab-general').isVisible()).toBe(false);
    expect(wrapper.find('.tab-data').isVisible()).toBe(true);
  });

  it('calls closeModal when modal footer Close button is clicked', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'settings' },
      },
    });

    const wrapper = mount(SettingsModal, {
      global: {
        plugins: [pinia],
        mocks: {
          __: (str) => str,
        },
      },
    });

    const uiStore = useUiStore();
    const footerCloseBtn = wrapper.find('.modal-footer button');
    expect(footerCloseBtn.exists()).toBe(true);
    expect(footerCloseBtn.text()).toBe('Close');

    await footerCloseBtn.trigger('click');
    expect(uiStore.closeModal).toHaveBeenCalled();
  });
});
