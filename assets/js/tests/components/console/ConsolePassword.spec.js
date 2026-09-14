import { shallowMount } from '@vue/test-utils';
import ConsolePassword from '../../../components/console/ConsolePassword.vue';

jest.mock('../../../lib/helpers.js', () => {
  const actual = jest.requireActual('../../../lib/helpers.js');
  return {
    ...actual,
    deriveKey: jest.fn(async () => new Uint8Array(32).fill(1)),
  };
});

const createElSettings = (overrides = {}) => ({
  user: {
    display_name: 'Test Administrator',
    gravatar: 'https://example.com/avatar.png'
  },
  salt: 'YTM0NTVmNmc3aDg5MGFiYw==',
  public_key: 'iojj3XQJ8ZX9UtstPLpdcspnCb8dlBIb83SIAbQPb1w=',
  ...overrides,
});

describe('ConsolePassword.vue', () => {
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

  it('renders correctly', () => {
    const wrapper = shallowMount(ConsolePassword);
    expect(wrapper.find('h2').text()).toBe('Test Administrator');
    expect(wrapper.find('input[type="password"]').exists()).toBe(true);
  });

  it('shows error on empty password', async () => {
    const wrapper = shallowMount(ConsolePassword);
    await wrapper.find('button').trigger('click');
    expect(wrapper.find('.error-message').text()).toBe('Please enter your passphrase.');
  });

  it('shows error on short password', async () => {
    const wrapper = shallowMount(ConsolePassword);
    await wrapper.find('input[type="password"]').setValue('short');
    await wrapper.find('button').trigger('click');
    expect(wrapper.find('.error-message').text()).toBe('Password too short.');
  });

  it('shows error on invalid passphrase when derived public key mismatches', async () => {
    window.el_settings.public_key = 'WRONG_PUBLIC_KEY_BASE64==';
    const wrapper = shallowMount(ConsolePassword);
    await wrapper.find('input[type="password"]').setValue('wrong-passphrase-1234');
    await wrapper.find('button').trigger('click');

    await new Promise(resolve => setTimeout(resolve, 150));
    await wrapper.vm.$nextTick();

    expect(wrapper.find('.error-message').text()).toBe('Invalid passphrase.');
    expect(wrapper.emitted('unlocked')).toBeFalsy();
  });

  it('emits unlocked with derived seed when public key matches', async () => {
    const wrapper = shallowMount(ConsolePassword);
    await wrapper.find('input[type="password"]').setValue('correct-passphrase-1234');
    await wrapper.find('button').trigger('click');

    await new Promise(resolve => setTimeout(resolve, 150));
    await wrapper.vm.$nextTick();

    expect(wrapper.emitted('unlocked')).toBeTruthy();
    expect(wrapper.emitted('unlocked')[0][0]).toBeInstanceOf(Uint8Array);
  });

  it('shows error when salt is missing from configuration', async () => {
    window.el_settings = createElSettings({ salt: '' });
    const wrapper = shallowMount(ConsolePassword);
    await wrapper.find('input[type="password"]').setValue('valid-passphrase-1234');
    await wrapper.find('button').trigger('click');

    await new Promise(resolve => setTimeout(resolve, 150));
    await wrapper.vm.$nextTick();

    expect(wrapper.find('.error-message').exists()).toBe(true);
    expect(wrapper.find('.error-message').text()).toContain('Salt not found in configuration.');
    expect(wrapper.emitted('unlocked')).toBeFalsy();
  });

  it('skips public key verification and emits unlock when public_key is empty', async () => {
    window.el_settings = createElSettings({ public_key: '' });
    const wrapper = shallowMount(ConsolePassword);
    await wrapper.find('input[type="password"]').setValue('any-passphrase-12345');
    await wrapper.find('button').trigger('click');

    await new Promise(resolve => setTimeout(resolve, 150));
    await wrapper.vm.$nextTick();

    expect(wrapper.emitted('unlocked')).toBeTruthy();
    expect(wrapper.emitted('unlocked')[0][0]).toBeInstanceOf(Uint8Array);
  });

  it('shows error when server challenge callback reports failure', async () => {
    const wrapper = shallowMount(ConsolePassword);
    await wrapper.find('input[type="password"]').setValue('correct-passphrase-1234');
    await wrapper.find('button').trigger('click');

    await new Promise(resolve => setTimeout(resolve, 150));
    await wrapper.vm.$nextTick();

    expect(wrapper.emitted('unlocked')).toBeTruthy();
    const emittedArgs = wrapper.emitted('unlocked')[0];
    const callback = emittedArgs[1];
    expect(typeof callback).toBe('function');

    callback('Invalid passphrase.');

    await new Promise(resolve => setTimeout(resolve, 150));
    await wrapper.vm.$nextTick();

    expect(wrapper.find('.error-message').exists()).toBe(true);
    expect(wrapper.find('.error-message').text()).toContain('Invalid passphrase.');
  });
});
