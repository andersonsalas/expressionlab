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
    expect(wrapper.text()).toContain('Default new tab layout');
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
});
