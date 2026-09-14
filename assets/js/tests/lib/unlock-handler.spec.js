import { setActivePinia, createPinia } from 'pinia';
import { useUiStore } from '../../stores/ui.js';
import { handleUnlock } from '../../lib/unlock-handler.js';

jest.mock('../../lib/api/client.js', () => ({
  requestChallenge: jest.fn(),
}));

jest.mock('../../lib/helpers.js', () => {
  const actual = jest.requireActual('../../lib/helpers.js');
  return {
    ...actual,
    __: jest.fn((key) => key),
  };
});

const { requestChallenge } = require('../../lib/api/client.js');

const createElSettings = () => ({
  user: {
    display_name: 'Test Admin',
    gravatar: 'https://example.com/avatar.png',
  },
  salt: 'YTM0NTVmNmc3aDg5MGFiYw==',
  public_key: 'AgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgI=',
  ajax_url: 'http://example.com/wp-admin/admin-ajax.php',
  nonce: 'test_nonce',
  settings: {
    enable_sandbox: true,
    debug_mode: false,
    signature_duration: 120,
  },
});

describe('handleUnlock - Security Flow', () => {
  let consoleErrorSpy;

  beforeEach(() => {
    consoleErrorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});
    Object.defineProperty(window, 'el_settings', {
      configurable: true,
      writable: true,
      value: createElSettings(),
    });
    setActivePinia(createPinia());
    jest.clearAllMocks();
  });

  afterEach(() => {
    consoleErrorSpy.mockRestore();
    delete window.el_settings;
  });

  it('calls uiStore.unlock() only after successful challenge handshake', async () => {
    requestChallenge.mockResolvedValueOnce(true);
    const uiStore = useUiStore();
    expect(uiStore.isLocked).toBe(true);

    const mockSeed = new Uint8Array(32).fill(1);
    const err = await new Promise((resolve) => {
      handleUnlock(mockSeed, (e) => resolve(e));
    });

    expect(err).toBeNull();
    expect(requestChallenge).toHaveBeenCalledTimes(1);
    expect(uiStore.isLocked).toBe(false);
    expect(uiStore.privateKey).toBe(mockSeed);
  });

  it('keeps lock screen visible when challenge returns false', async () => {
    requestChallenge.mockResolvedValueOnce(false);
    const uiStore = useUiStore();
    expect(uiStore.isLocked).toBe(true);

    const mockSeed = new Uint8Array(32).fill(1);
    const err = await new Promise((resolve) => {
      handleUnlock(mockSeed, (e) => resolve(e));
    });

    expect(err).toBeTruthy();
    expect(err).toBe('Invalid passphrase.');
    expect(uiStore.isLocked).toBe(true);
    expect(uiStore.privateKey).toBeNull();
  });

  it('keeps lock screen visible when challenge throws a network error', async () => {
    requestChallenge.mockRejectedValueOnce(new Error('Network failure'));
    const uiStore = useUiStore();
    expect(uiStore.isLocked).toBe(true);

    const mockSeed = new Uint8Array(32).fill(1);
    const err = await new Promise((resolve) => {
      handleUnlock(mockSeed, (e) => resolve(e));
    });

    expect(err).toBeTruthy();
    expect(err).toBe('An error occurred during verification.');
    expect(uiStore.isLocked).toBe(true);
    expect(uiStore.privateKey).toBeNull();
  });

  it('sets privateKey temporarily during challenge but clears it on failure', async () => {
    let capturedPrivateKey = null;
    requestChallenge.mockImplementationOnce(() => {
      const uiStore = useUiStore();
      capturedPrivateKey = uiStore.privateKey;
      return Promise.resolve(false);
    });

    const mockSeed = new Uint8Array(32).fill(7);
    await new Promise((resolve) => {
      handleUnlock(mockSeed, () => resolve());
    });

    expect(capturedPrivateKey).toBe(mockSeed);
    const uiStore = useUiStore();
    expect(uiStore.privateKey).toBeNull();
  });

  it('does not call unlock() when challenge fails', async () => {
    requestChallenge.mockResolvedValueOnce(false);
    const uiStore = useUiStore();
    const unlockSpy = jest.spyOn(uiStore, 'unlock');

    const mockSeed = new Uint8Array(32).fill(1);
    await new Promise((resolve) => {
      handleUnlock(mockSeed, () => resolve());
    });

    expect(unlockSpy).not.toHaveBeenCalled();
    unlockSpy.mockRestore();
  });

  it('does not call unlock() when challenge throws', async () => {
    requestChallenge.mockRejectedValueOnce(new Error('boom'));
    const uiStore = useUiStore();
    const unlockSpy = jest.spyOn(uiStore, 'unlock');

    const mockSeed = new Uint8Array(32).fill(1);
    await new Promise((resolve) => {
      handleUnlock(mockSeed, () => resolve());
    });

    expect(unlockSpy).not.toHaveBeenCalled();
    unlockSpy.mockRestore();
  });
});
