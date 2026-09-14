import { mount } from '@vue/test-utils';
import { createTestingPinia } from '@pinia/testing';
import AboutModal from '../../../components/modals/AboutModal.vue';
import { useUiStore } from '../../../stores/ui';

const createElSettings = () => ({
  version: '0.0.1',
  user: {
    display_name: 'Test Administrator',
    gravatar: 'https://example.com/avatar.png'
  },
  settings: {}
});

describe('AboutModal.vue', () => {
  beforeEach(() => {
    Object.defineProperty(window, 'el_settings', {
      configurable: true,
      writable: true,
      value: createElSettings()
    });
  });

  afterEach(() => {
    delete window.el_settings;
  });

  it('renders correctly when open', () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'about' }
      }
    });

    const wrapper = mount(AboutModal, {
      global: {
        plugins: [pinia]
      }
    });

    expect(wrapper.find('#about-modal').exists()).toBe(true);
    expect(wrapper.find('h2').text()).toBe('About');
    expect(wrapper.find('.about-app-title').text()).toBe('Expression Lab');
    expect(wrapper.find('.about-app-version').text()).toBe('0.0.1');
    expect(wrapper.find('.about-credits').text()).toContain('Anderson Salas and contributors.');
    expect(wrapper.find('.about-license').text()).toContain('GNU General Public License');
    
    const links = wrapper.findAll('.about-links a');
    expect(links.length).toBe(3);
    expect(links[0].text()).toBe('Website');
    expect(links[1].text()).toBe('Docs');
    expect(links[2].text()).toBe('GitHub');
  });

  it('renders empty string when version is not set', () => {
    window.el_settings = { settings: {} };

    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'about' }
      }
    });

    const wrapper = mount(AboutModal, {
      global: {
        plugins: [pinia]
      }
    });

    expect(wrapper.find('.about-app-version').text()).toBe('');
  });

  it('does not render when closed', () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: null }
      }
    });

    const wrapper = mount(AboutModal, {
      global: {
        plugins: [pinia]
      }
    });

    expect(wrapper.find('#about-modal').exists()).toBe(false);
  });

  it('calls closeModal on close button click', async () => {
    const pinia = createTestingPinia({
      initialState: {
        ui: { activeModal: 'about' }
      }
    });

    const wrapper = mount(AboutModal, {
      global: {
        plugins: [pinia]
      }
    });

    const uiStore = useUiStore();
    await wrapper.find('.modal-close').trigger('click');
    expect(uiStore.closeModal).toHaveBeenCalled();
  });
});
