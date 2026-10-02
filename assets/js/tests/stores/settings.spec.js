import { setActivePinia, createPinia } from 'pinia';
import {
  useSettingsStore,
  USER_SETTINGS_STORAGE_KEY,
  OUTLINE_CACHE_KEY,
  getStoredDefaultTabMode,
  getStoredSidebarVisible,
} from '../../stores/settings.js';
import * as client from '../../lib/api/client.js';

jest.mock('../../lib/api/client.js', () => ({
  handleLocalStorage: jest.fn(),
  isSandboxEnabled: jest.fn(() => false),
}));

describe('stores/settings.js - Settings Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    jest.clearAllMocks();
    localStorage.clear();
  });

  describe('getStoredDefaultTabMode()', () => {
    it('returns console when localStorage is empty', () => {
      expect(getStoredDefaultTabMode()).toBe('console');
    });

    it('returns scratchpad when el_user_settings has scratchpad mode', () => {
      localStorage.setItem(
        USER_SETTINGS_STORAGE_KEY,
        JSON.stringify({ defaultTabMode: 'scratchpad' })
      );
      expect(getStoredDefaultTabMode()).toBe('scratchpad');
    });

    it('returns console when el_user_settings has invalid or console mode', () => {
      localStorage.setItem(
        USER_SETTINGS_STORAGE_KEY,
        JSON.stringify({ defaultTabMode: 'invalid_mode' })
      );
      expect(getStoredDefaultTabMode()).toBe('console');
    });

    it('returns console when localStorage has malformed JSON', () => {
      localStorage.setItem(USER_SETTINGS_STORAGE_KEY, '{invalid json');
      expect(getStoredDefaultTabMode()).toBe('console');
    });
  });

  describe('getStoredSidebarVisible()', () => {
    it('returns true when localStorage is empty', () => {
      expect(getStoredSidebarVisible()).toBe(true);
    });

    it('returns false when el_user_settings has isSidebarVisible: false', () => {
      localStorage.setItem(
        USER_SETTINGS_STORAGE_KEY,
        JSON.stringify({ isSidebarVisible: false })
      );
      expect(getStoredSidebarVisible()).toBe(false);
    });

    it('returns true when el_user_settings has isSidebarVisible: true', () => {
      localStorage.setItem(
        USER_SETTINGS_STORAGE_KEY,
        JSON.stringify({ isSidebarVisible: true })
      );
      expect(getStoredSidebarVisible()).toBe(true);
    });

    it('returns true when el_user_settings has invalid type for isSidebarVisible', () => {
      localStorage.setItem(
        USER_SETTINGS_STORAGE_KEY,
        JSON.stringify({ isSidebarVisible: 'invalid' })
      );
      expect(getStoredSidebarVisible()).toBe(true);
    });
  });

  describe('Initial State', () => {
    it('initializes with default values', () => {
      const store = useSettingsStore();

      expect(store.defaultTabMode).toBe('console');
      expect(store.isSidebarVisible).toBe(true);
      expect(store.hasOutlineCache).toBe(false);
      expect(store.isClearingCache).toBe(false);
      expect(store.loaded).toBe(false);
    });

    it('initializes defaultTabMode from localStorage when available', () => {
      localStorage.setItem(
        USER_SETTINGS_STORAGE_KEY,
        JSON.stringify({ defaultTabMode: 'scratchpad' })
      );

      const store = useSettingsStore();
      expect(store.defaultTabMode).toBe('scratchpad');
    });

    it('initializes isSidebarVisible from localStorage when available', () => {
      localStorage.setItem(
        USER_SETTINGS_STORAGE_KEY,
        JSON.stringify({ isSidebarVisible: false })
      );

      const store = useSettingsStore();
      expect(store.isSidebarVisible).toBe(false);
    });
  });

  describe('loadSettings()', () => {
    it('loads settings and checks outline cache', async () => {
      client.handleLocalStorage.mockImplementation((method, key) => {
        if (key === USER_SETTINGS_STORAGE_KEY) {
          return Promise.resolve(JSON.stringify({ defaultTabMode: 'scratchpad' }));
        }
        if (key === OUTLINE_CACHE_KEY) {
          return Promise.resolve(JSON.stringify({ symbols: [] }));
        }
        return Promise.resolve(null);
      });

      const store = useSettingsStore();
      await store.loadSettings();

      expect(store.defaultTabMode).toBe('scratchpad');
      expect(store.hasOutlineCache).toBe(true);
      expect(store.loaded).toBe(true);
    });

    it('handles null storage response gracefully', async () => {
      client.handleLocalStorage.mockResolvedValue(null);

      const store = useSettingsStore();
      await store.loadSettings();

      expect(store.defaultTabMode).toBe('console');
      expect(store.hasOutlineCache).toBe(false);
      expect(store.loaded).toBe(true);
    });
  });

  describe('setDefaultTabMode()', () => {
    it('updates defaultTabMode and persists to storage while preserving existing properties', async () => {
      client.handleLocalStorage.mockImplementation((method, key) => {
        if (method === 'getItem' && key === USER_SETTINGS_STORAGE_KEY) {
          return Promise.resolve(JSON.stringify({ someOtherSetting: true }));
        }
        return Promise.resolve(null);
      });

      const store = useSettingsStore();
      await store.setDefaultTabMode('scratchpad');

      expect(store.defaultTabMode).toBe('scratchpad');
      expect(client.handleLocalStorage).toHaveBeenCalledWith(
        'setItem',
        USER_SETTINGS_STORAGE_KEY,
        JSON.stringify({ someOtherSetting: true, defaultTabMode: 'scratchpad' })
      );
    });

    it('normalizes unexpected mode values to console', async () => {
      client.handleLocalStorage.mockResolvedValue(null);

      const store = useSettingsStore();
      await store.setDefaultTabMode('unknown_mode');

      expect(store.defaultTabMode).toBe('console');
      expect(client.handleLocalStorage).toHaveBeenCalledWith(
        'setItem',
        USER_SETTINGS_STORAGE_KEY,
        JSON.stringify({ defaultTabMode: 'console' })
      );
    });
  });

  describe('checkOutlineCache()', () => {
    it('sets hasOutlineCache to true if cache exists', async () => {
      client.handleLocalStorage.mockResolvedValue('{"cached":true}');

      const store = useSettingsStore();
      await store.checkOutlineCache();

      expect(store.hasOutlineCache).toBe(true);
      expect(client.handleLocalStorage).toHaveBeenCalledWith('getItem', OUTLINE_CACHE_KEY);
    });

    it('sets hasOutlineCache to false if cache is null or empty', async () => {
      client.handleLocalStorage.mockResolvedValue(null);

      const store = useSettingsStore();
      await store.checkOutlineCache();

      expect(store.hasOutlineCache).toBe(false);
    });
  });

  describe('clearOutlineCache()', () => {
    it('removes outline cache and updates hasOutlineCache to false', async () => {
      client.handleLocalStorage.mockResolvedValue(null);

      const store = useSettingsStore();
      store.hasOutlineCache = true;

      const result = await store.clearOutlineCache();

      expect(result).toBe(true);
      expect(client.handleLocalStorage).toHaveBeenCalledWith('removeItem', OUTLINE_CACHE_KEY);
      expect(store.hasOutlineCache).toBe(false);
      expect(store.isClearingCache).toBe(false);
    });
  });

  describe('setSidebarVisible() & toggleSidebarVisible()', () => {
    it('updates isSidebarVisible and persists to storage while preserving existing properties', async () => {
      client.handleLocalStorage.mockImplementation((method, key) => {
        if (method === 'getItem' && key === USER_SETTINGS_STORAGE_KEY) {
          return Promise.resolve(JSON.stringify({ defaultTabMode: 'scratchpad' }));
        }
        return Promise.resolve(null);
      });

      const store = useSettingsStore();
      await store.setSidebarVisible(false);

      expect(store.isSidebarVisible).toBe(false);
      expect(client.handleLocalStorage).toHaveBeenCalledWith(
        'setItem',
        USER_SETTINGS_STORAGE_KEY,
        JSON.stringify({ defaultTabMode: 'scratchpad', isSidebarVisible: false })
      );
    });

    it('toggles isSidebarVisible from true to false and back', async () => {
      client.handleLocalStorage.mockResolvedValue(null);

      const store = useSettingsStore();
      expect(store.isSidebarVisible).toBe(true);

      await store.toggleSidebarVisible();
      expect(store.isSidebarVisible).toBe(false);

      await store.toggleSidebarVisible();
      expect(store.isSidebarVisible).toBe(true);
    });
  });

  describe('Sandbox Mode', () => {
    beforeEach(() => {
      client.isSandboxEnabled.mockReturnValue(true);
    });

    afterEach(() => {
      client.isSandboxEnabled.mockReturnValue(false);
    });

    it('does not access localStorage synchronously when sandbox is enabled', () => {
      localStorage.setItem(
        USER_SETTINGS_STORAGE_KEY,
        JSON.stringify({ defaultTabMode: 'scratchpad', isSidebarVisible: false })
      );

      expect(getStoredDefaultTabMode()).toBe('console');
      expect(getStoredSidebarVisible()).toBe(true);
    });

    it('loads settings and cache status via postMessage (handleLocalStorage) in sandbox mode', async () => {
      client.handleLocalStorage.mockImplementation((method, key) => {
        if (method === 'getItem' && key === USER_SETTINGS_STORAGE_KEY) {
          return Promise.resolve(JSON.stringify({ defaultTabMode: 'scratchpad', isSidebarVisible: false }));
        }
        if (method === 'getItem' && key === OUTLINE_CACHE_KEY) {
          return Promise.resolve('{"data":[]}');
        }
        return Promise.resolve(null);
      });

      const store = useSettingsStore();
      await store.loadSettings();

      expect(store.defaultTabMode).toBe('scratchpad');
      expect(store.isSidebarVisible).toBe(false);
      expect(store.hasOutlineCache).toBe(true);
      expect(store.loaded).toBe(true);
    });

    it('persists sidebar visibility and clears outline cache via postMessage without touching localStorage', async () => {
      client.handleLocalStorage.mockResolvedValue(null);

      const store = useSettingsStore();
      store.hasOutlineCache = true;

      await store.setSidebarVisible(false);
      expect(client.handleLocalStorage).toHaveBeenCalledWith(
        'setItem',
        USER_SETTINGS_STORAGE_KEY,
        expect.stringContaining('"isSidebarVisible":false')
      );

      const cleared = await store.clearOutlineCache();
      expect(cleared).toBe(true);
      expect(client.handleLocalStorage).toHaveBeenCalledWith('removeItem', OUTLINE_CACHE_KEY);
      expect(store.hasOutlineCache).toBe(false);
    });
  });

  describe('Edge Cases & Negative Paths', () => {
    describe('Malformed and Non-Object JSON in storage', () => {
      it('getStoredDefaultTabMode returns console when storage contains non-object JSON (number)', () => {
        localStorage.setItem(USER_SETTINGS_STORAGE_KEY, '123');
        expect(getStoredDefaultTabMode()).toBe('console');
      });

      it('getStoredDefaultTabMode returns console when storage contains non-object JSON (boolean)', () => {
        localStorage.setItem(USER_SETTINGS_STORAGE_KEY, 'true');
        expect(getStoredDefaultTabMode()).toBe('console');
      });

      it('getStoredDefaultTabMode returns console when storage contains non-object JSON (array)', () => {
        localStorage.setItem(USER_SETTINGS_STORAGE_KEY, '["scratchpad"]');
        expect(getStoredDefaultTabMode()).toBe('console');
      });

      it('getStoredSidebarVisible returns true when storage contains non-object JSON (number)', () => {
        localStorage.setItem(USER_SETTINGS_STORAGE_KEY, '42');
        expect(getStoredSidebarVisible()).toBe(true);
      });

      it('getStoredSidebarVisible returns true when storage contains non-object JSON (string)', () => {
        localStorage.setItem(USER_SETTINGS_STORAGE_KEY, '"false"');
        expect(getStoredSidebarVisible()).toBe(true);
      });

      it('getStoredSidebarVisible returns true when storage contains malformed JSON', () => {
        localStorage.setItem(USER_SETTINGS_STORAGE_KEY, '{corrupted');
        expect(getStoredSidebarVisible()).toBe(true);
      });
    });

    describe('loadSettings() resilience', () => {
      it('handles malformed JSON from handleLocalStorage gracefully', async () => {
        client.handleLocalStorage.mockImplementation((method, key) => {
          if (key === USER_SETTINGS_STORAGE_KEY) {
            return Promise.resolve('{invalid json');
          }
          return Promise.resolve(null);
        });

        const store = useSettingsStore();
        await store.loadSettings();

        expect(store.defaultTabMode).toBe('console');
        expect(store.isSidebarVisible).toBe(true);
        expect(store.loaded).toBe(true);
      });

      it('handles non-object JSON from handleLocalStorage (number)', async () => {
        client.handleLocalStorage.mockImplementation((method, key) => {
          if (key === USER_SETTINGS_STORAGE_KEY) {
            return Promise.resolve('123');
          }
          return Promise.resolve(null);
        });

        const store = useSettingsStore();
        await store.loadSettings();

        expect(store.defaultTabMode).toBe('console');
        expect(store.isSidebarVisible).toBe(true);
        expect(store.loaded).toBe(true);
      });

      it('handles rejected handleLocalStorage promise without crashing', async () => {
        client.handleLocalStorage.mockRejectedValue(new Error('Network failure'));

        const store = useSettingsStore();
        await store.loadSettings();

        expect(store.defaultTabMode).toBe('console');
        expect(store.loaded).toBe(true);
      });

      it('sets loaded to true even when loadSettings encounters errors', async () => {
        client.handleLocalStorage.mockRejectedValue(new Error('Storage unavailable'));

        const store = useSettingsStore();
        expect(store.loaded).toBe(false);

        await store.loadSettings();
        expect(store.loaded).toBe(true);
      });
    });

    describe('Unexpected defaultTabMode values', () => {
      it('normalizes null mode to console', async () => {
        client.handleLocalStorage.mockResolvedValue(null);

        const store = useSettingsStore();
        await store.setDefaultTabMode(null);

        expect(store.defaultTabMode).toBe('console');
      });

      it('normalizes undefined mode to console', async () => {
        client.handleLocalStorage.mockResolvedValue(null);

        const store = useSettingsStore();
        await store.setDefaultTabMode(undefined);

        expect(store.defaultTabMode).toBe('console');
      });

      it('rejects prototype pollution attempt in defaultTabMode', async () => {
        client.handleLocalStorage.mockImplementation((method, key) => {
          if (method === 'getItem' && key === USER_SETTINGS_STORAGE_KEY) {
            return Promise.resolve(JSON.stringify({ defaultTabMode: '__proto__' }));
          }
          return Promise.resolve(null);
        });

        const store = useSettingsStore();
        await store.loadSettings();

        expect(store.defaultTabMode).toBe('console');
      });

      it('rejects XSS payload in defaultTabMode', async () => {
        client.handleLocalStorage.mockImplementation((method, key) => {
          if (method === 'getItem' && key === USER_SETTINGS_STORAGE_KEY) {
            return Promise.resolve(JSON.stringify({ defaultTabMode: '<script>alert(1)</script>' }));
          }
          return Promise.resolve(null);
        });

        const store = useSettingsStore();
        await store.loadSettings();

        expect(store.defaultTabMode).toBe('console');
      });
    });

    describe('Non-boolean isSidebarVisible values', () => {
      it('ignores string "true" for isSidebarVisible in loadSettings', async () => {
        client.handleLocalStorage.mockImplementation((method, key) => {
          if (method === 'getItem' && key === USER_SETTINGS_STORAGE_KEY) {
            return Promise.resolve(JSON.stringify({ isSidebarVisible: 'true' }));
          }
          return Promise.resolve(null);
        });

        const store = useSettingsStore();
        await store.loadSettings();

        // Should remain at default (true) since 'true' (string) !== boolean
        expect(store.isSidebarVisible).toBe(true);
      });

      it('ignores numeric 1 for isSidebarVisible in loadSettings', async () => {
        client.handleLocalStorage.mockImplementation((method, key) => {
          if (method === 'getItem' && key === USER_SETTINGS_STORAGE_KEY) {
            return Promise.resolve(JSON.stringify({ isSidebarVisible: 1 }));
          }
          return Promise.resolve(null);
        });

        const store = useSettingsStore();
        await store.loadSettings();

        expect(store.isSidebarVisible).toBe(true);
      });

      it('ignores null for isSidebarVisible in loadSettings', async () => {
        client.handleLocalStorage.mockImplementation((method, key) => {
          if (method === 'getItem' && key === USER_SETTINGS_STORAGE_KEY) {
            return Promise.resolve(JSON.stringify({ isSidebarVisible: null }));
          }
          return Promise.resolve(null);
        });

        const store = useSettingsStore();
        await store.loadSettings();

        expect(store.isSidebarVisible).toBe(true);
      });

      it('coerces truthy values to boolean in setSidebarVisible', async () => {
        client.handleLocalStorage.mockResolvedValue(null);

        const store = useSettingsStore();
        await store.setSidebarVisible(1);
        expect(store.isSidebarVisible).toBe(true);

        await store.setSidebarVisible(0);
        expect(store.isSidebarVisible).toBe(false);

        await store.setSidebarVisible('yes');
        expect(store.isSidebarVisible).toBe(true);

        await store.setSidebarVisible('');
        expect(store.isSidebarVisible).toBe(false);
      });
    });

    describe('clearOutlineCache() error handling', () => {
      it('returns false and resets isClearingCache on handleLocalStorage rejection', async () => {
        client.handleLocalStorage.mockRejectedValue(new Error('Storage error'));

        const store = useSettingsStore();
        store.hasOutlineCache = true;

        const result = await store.clearOutlineCache();

        expect(result).toBe(false);
        expect(store.isClearingCache).toBe(false);
        // hasOutlineCache should remain true since the clear failed
        expect(store.hasOutlineCache).toBe(true);
      });
    });

    describe('checkOutlineCache() error handling', () => {
      it('sets hasOutlineCache to false when handleLocalStorage rejects', async () => {
        client.handleLocalStorage.mockRejectedValue(new Error('Storage error'));

        const store = useSettingsStore();
        store.hasOutlineCache = true;

        await store.checkOutlineCache();

        expect(store.hasOutlineCache).toBe(false);
      });

      it('sets hasOutlineCache to false for empty string cache value', async () => {
        client.handleLocalStorage.mockResolvedValue('');

        const store = useSettingsStore();
        await store.checkOutlineCache();

        expect(store.hasOutlineCache).toBe(false);
      });
    });

    describe('setDefaultTabMode() preserves sibling keys with non-object stored data', () => {
      it('overwrites corrupted non-object storage with fresh settings object', async () => {
        client.handleLocalStorage.mockImplementation((method, key) => {
          if (method === 'getItem' && key === USER_SETTINGS_STORAGE_KEY) {
            return Promise.resolve('123');
          }
          return Promise.resolve(null);
        });

        const store = useSettingsStore();
        await store.setDefaultTabMode('scratchpad');

        expect(store.defaultTabMode).toBe('scratchpad');
        expect(client.handleLocalStorage).toHaveBeenCalledWith(
          'setItem',
          USER_SETTINGS_STORAGE_KEY,
          JSON.stringify({ defaultTabMode: 'scratchpad' })
        );
      });

      it('overwrites array-type stored data with fresh settings object', async () => {
        client.handleLocalStorage.mockImplementation((method, key) => {
          if (method === 'getItem' && key === USER_SETTINGS_STORAGE_KEY) {
            return Promise.resolve('[1, 2, 3]');
          }
          return Promise.resolve(null);
        });

        const store = useSettingsStore();
        await store.setDefaultTabMode('scratchpad');

        expect(client.handleLocalStorage).toHaveBeenCalledWith(
          'setItem',
          USER_SETTINGS_STORAGE_KEY,
          JSON.stringify({ defaultTabMode: 'scratchpad' })
        );
      });
    });

    describe('setSidebarVisible() error handling', () => {
      it('handles handleLocalStorage rejection gracefully', async () => {
        client.handleLocalStorage.mockRejectedValue(new Error('Write failure'));

        const store = useSettingsStore();
        // Should not throw
        await store.setSidebarVisible(false);

        // State should still update even if persistence failed
        expect(store.isSidebarVisible).toBe(false);
      });
    });
  });
});
