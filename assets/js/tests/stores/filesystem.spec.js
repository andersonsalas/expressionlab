import { setActivePinia, createPinia } from 'pinia';
import {
  useFilesystemStore,
  FILESYSTEM_CACHE_KEY,
  FILESYSTEM_CACHE_TTL_MS,
} from '../../stores/filesystem.js';
import { handleFetch, handleLocalStorage } from '../../lib/api/client.js';

jest.mock('../../lib/api/client.js', () => ({
  handleFetch: jest.fn(),
  handleLocalStorage: jest.fn(),
}));

const mockFilesystemPayload = {
  presets: [
    { id: 'debug_log', name: 'debug.log', path: 'wp-content/debug.log', type: 'file' },
  ],
  tree: [
    { name: 'wp-content', path: 'wp-content', type: 'dir', children: [] },
  ],
  limits: {
    max_read_bytes: 262144,
    max_tail_bytes: 1048576,
  },
};

describe('stores/filesystem.js', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    jest.clearAllMocks();
    window.el_settings = {
      ajax_url: 'http://example.com/wp-admin/admin-ajax.php',
      nonce: 'files_nonce',
      settings: {
        enable_cache: false,
      },
    };
  });

  afterEach(() => {
    delete window.el_settings;
  });

  it('loads data via AJAX when not cached', async () => {
    handleFetch.mockResolvedValueOnce({
      json: () =>
        Promise.resolve({
          success: true,
          data: { data: mockFilesystemPayload },
        }),
    });

    const store = useFilesystemStore();
    expect(store.loaded).toBe(false);

    await store.loadFilesystem();

    expect(store.loaded).toBe(true);
    expect(store.loading).toBe(false);
    expect(store.presets.length).toBe(1);
    expect(store.tree.length).toBe(1);
    expect(handleFetch).toHaveBeenCalledTimes(1);
  });

  it('persists data with timestamp to localStorage when enable_cache is true', async () => {
    window.el_settings.settings.enable_cache = true;
    handleFetch.mockResolvedValueOnce({
      json: () =>
        Promise.resolve({
          success: true,
          data: { data: mockFilesystemPayload },
        }),
    });

    const store = useFilesystemStore();
    await store.loadFilesystem();

    expect(handleLocalStorage).toHaveBeenCalledWith(
      'setItem',
      FILESYSTEM_CACHE_KEY,
      expect.stringContaining('"timestamp":')
    );
  });

  it('hydrates from valid unexpired cache without making AJAX call', async () => {
    window.el_settings.settings.enable_cache = true;
    const validCache = JSON.stringify({
      timestamp: Date.now() - 10000, // 10s ago (< 1h)
      data: mockFilesystemPayload,
    });
    handleLocalStorage.mockResolvedValueOnce(validCache);

    const store = useFilesystemStore();
    await store.loadFilesystem();

    expect(handleFetch).not.toHaveBeenCalled();
    expect(store.loaded).toBe(true);
    expect(store.presets.length).toBe(1);
  });

  it('removes cache from localStorage and fetches from backend when cache is expired (> 1h)', async () => {
    window.el_settings.settings.enable_cache = true;
    const expiredCache = JSON.stringify({
      timestamp: Date.now() - (FILESYSTEM_CACHE_TTL_MS + 5000), // > 1h
      data: mockFilesystemPayload,
    });
    handleLocalStorage.mockResolvedValueOnce(expiredCache);
    handleFetch.mockResolvedValueOnce({
      json: () =>
        Promise.resolve({
          success: true,
          data: { data: mockFilesystemPayload },
        }),
    });

    const store = useFilesystemStore();
    await store.loadFilesystem();

    expect(handleLocalStorage).toHaveBeenCalledWith('removeItem', FILESYSTEM_CACHE_KEY);
    expect(handleFetch).toHaveBeenCalledTimes(1);
    expect(store.loaded).toBe(true);
  });

  it('refreshFilesystem() removes cache, resets state and fetches fresh data', async () => {
    window.el_settings.settings.enable_cache = true;
    handleFetch.mockResolvedValueOnce({
      json: () =>
        Promise.resolve({
          success: true,
          data: { data: mockFilesystemPayload },
        }),
    });

    const store = useFilesystemStore();
    store.presets = [{ id: 'old', name: 'old.txt', path: 'old.txt' }];
    store.loaded = true;

    await store.refreshFilesystem();

    expect(handleLocalStorage).toHaveBeenCalledWith('removeItem', FILESYSTEM_CACHE_KEY);
    expect(handleFetch).toHaveBeenCalledTimes(1);
    expect(store.presets[0].id).toBe('debug_log');
    expect(store.loaded).toBe(true);
  });

  it('captures backend error messages without throwing', async () => {
    handleFetch.mockResolvedValueOnce({
      json: () => Promise.resolve({ success: false, data: { message: 'Forbidden' } }),
    });

    const store = useFilesystemStore();
    await store.loadFilesystem();

    expect(store.error).toBe('Forbidden');
    expect(store.loading).toBe(false);
    expect(store.loaded).toBe(false);
  });

  it('captures network failures in error state', async () => {
    handleFetch.mockRejectedValueOnce(new Error('Network down'));

    const store = useFilesystemStore();
    await store.loadFilesystem();

    expect(store.error).toBe('Network down');
    expect(store.loading).toBe(false);
  });

  it('does not issue a second request while one is in flight', async () => {
    let resolveFetch;
    handleFetch.mockReturnValueOnce(
      new Promise((resolve) => {
        resolveFetch = resolve;
      })
    );

    const store = useFilesystemStore();
    const first = store.loadFilesystem();
    await store.loadFilesystem();

    expect(handleFetch).toHaveBeenCalledTimes(1);

    resolveFetch({
      json: () => Promise.resolve({ success: true, data: { data: mockFilesystemPayload } }),
    });
    await first;
    expect(store.loaded).toBe(true);
  });

  it('clearCache() removes cache and resets store state', async () => {
    const store = useFilesystemStore();
    store.presets = [{ id: 'test' }];
    store.tree = [{ name: 'folder' }];
    store.loaded = true;

    await store.clearCache();

    expect(handleLocalStorage).toHaveBeenCalledWith('removeItem', FILESYSTEM_CACHE_KEY);
    expect(store.presets).toEqual([]);
    expect(store.tree).toEqual([]);
    expect(store.loaded).toBe(false);
  });
});
