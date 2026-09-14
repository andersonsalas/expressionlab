import { setActivePinia, createPinia } from 'pinia';
import { useUiStore } from '../../stores/ui.js';

describe('stores/ui.js - Lock/Unlock Security State', () => {
  beforeEach(() => {
    window.el_settings = {
      settings: {
        enable_sandbox: true,
        debug_mode: false,
      },
    };
    setActivePinia(createPinia());
  });

  afterEach(() => {
    delete window.el_settings;
  });

  it('isLocked is true when enable_sandbox is true', () => {
    window.el_settings.settings.enable_sandbox = true;
    window.el_settings.settings.debug_mode = false;
    setActivePinia(createPinia());

    const store = useUiStore();
    expect(store.isLocked).toBe(true);
  });

  it('isLocked is true when debug_mode is true', () => {
    window.el_settings.settings.enable_sandbox = false;
    window.el_settings.settings.debug_mode = true;
    setActivePinia(createPinia());

    const store = useUiStore();
    expect(store.isLocked).toBe(true);
  });

  it('isLocked is true when both enable_sandbox and debug_mode are true', () => {
    window.el_settings.settings.enable_sandbox = true;
    window.el_settings.settings.debug_mode = true;
    setActivePinia(createPinia());

    const store = useUiStore();
    expect(store.isLocked).toBe(true);
  });

  it('isLocked is false when both enable_sandbox and debug_mode are false', () => {
    window.el_settings.settings.enable_sandbox = false;
    window.el_settings.settings.debug_mode = false;
    setActivePinia(createPinia());

    const store = useUiStore();
    expect(store.isLocked).toBe(false);
  });

  it('privateKey is null initially', () => {
    const store = useUiStore();
    expect(store.privateKey).toBeNull();
  });

  it('unlock() sets isLocked to false and stores the private key', () => {
    const store = useUiStore();
    const mockSeed = new Uint8Array(32).fill(42);

    expect(store.isLocked).toBe(true);
    store.unlock(mockSeed);

    expect(store.isLocked).toBe(false);
    expect(store.privateKey).toBe(mockSeed);
  });

  it('lock() sets isLocked to true and clears privateKey', () => {
    const store = useUiStore();
    const mockSeed = new Uint8Array(32).fill(42);

    store.unlock(mockSeed);
    expect(store.isLocked).toBe(false);
    expect(store.privateKey).toBe(mockSeed);

    store.lock();

    expect(store.isLocked).toBe(true);
    expect(store.privateKey).toBeNull();
  });

  it('setPrivateKey() sets the private key without changing isLocked', () => {
    const store = useUiStore();
    const mockSeed = new Uint8Array(32).fill(99);

    expect(store.isLocked).toBe(true);
    store.setPrivateKey(mockSeed);

    expect(store.privateKey).toBe(mockSeed);
    expect(store.isLocked).toBe(true);
  });

  it('setPrivateKey(null) clears the private key without changing isLocked', () => {
    const store = useUiStore();
    const mockSeed = new Uint8Array(32).fill(99);

    store.unlock(mockSeed);
    expect(store.isLocked).toBe(false);

    store.setPrivateKey(null);

    expect(store.privateKey).toBeNull();
    expect(store.isLocked).toBe(false);
  });

  it('setChallenge() stores the challenge and records fetch time', () => {
    const store = useUiStore();
    const challenge = { nonce: 'abc123', timestamp: Date.now() };
    const before = Date.now();

    store.setChallenge(challenge);

    expect(store.currentChallenge).toStrictEqual(challenge);
    expect(store.challengeFetchedAt).toBeGreaterThanOrEqual(before);
  });

  it('setRequestingChallenge() toggles isRequestingChallenge', () => {
    const store = useUiStore();

    expect(store.isRequestingChallenge).toBe(false);
    store.setRequestingChallenge(true);
    expect(store.isRequestingChallenge).toBe(true);
    store.setRequestingChallenge(false);
    expect(store.isRequestingChallenge).toBe(false);
  });
});
