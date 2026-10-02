import { defineStore } from 'pinia';
import { handleLocalStorage, isSandboxEnabled } from '../lib/api/client.js';
import { debugLog } from '../lib/helpers.js';

export const USER_SETTINGS_STORAGE_KEY = 'el_user_settings';
export const OUTLINE_CACHE_KEY = 'el_outline_cache';

/**
 * Safely accesses window.localStorage without throwing SecurityError in sandboxed iframes.
 *
 * @returns {Storage|null}
 */
export const getSafeLocalStorage = () => {
  try {
    if (typeof isSandboxEnabled === 'function' && isSandboxEnabled()) {
      return null;
    }
    if (typeof window !== 'undefined') {
      return window.localStorage;
    }
  } catch {
    return null;
  }
  return null;
};

/**
 * Synchronously retrieves stored default tab mode from localStorage if available.
 *
 * @returns {'console'|'scratchpad'}
 */
export const getStoredDefaultTabMode = () => {
  try {
    const storage = getSafeLocalStorage();
    if (storage) {
      const stored = storage.getItem(USER_SETTINGS_STORAGE_KEY);
      if (stored) {
        const parsed = JSON.parse(stored);
        if (parsed?.defaultTabMode === 'scratchpad') {
          return 'scratchpad';
        }
      }
    }
  } catch {
    // Fall back to default
  }
  return 'console';
};

/**
 * Synchronously retrieves stored outline sidebar visibility from localStorage if available.
 *
 * @returns {boolean}
 */
export const getStoredSidebarVisible = () => {
  try {
    const storage = getSafeLocalStorage();
    if (storage) {
      const stored = storage.getItem(USER_SETTINGS_STORAGE_KEY);
      if (stored) {
        const parsed = JSON.parse(stored);
        if (typeof parsed?.isSidebarVisible === 'boolean') {
          return parsed.isSidebarVisible;
        }
      }
    }
  } catch {
    // Fall back to default
  }
  return true;
};

export const useSettingsStore = defineStore('settings', {
  state: () => ({
    defaultTabMode: getStoredDefaultTabMode(),
    isSidebarVisible: getStoredSidebarVisible(),
    hasOutlineCache: false,
    isClearingCache: false,
    loaded: false,
  }),

  actions: {
    /**
     * Loads user settings and cache status from storage.
     */
    async loadSettings() {
      try {
        const raw = await handleLocalStorage('getItem', USER_SETTINGS_STORAGE_KEY);
        if (raw) {
          const parsed = JSON.parse(raw);
          if (parsed && typeof parsed === 'object') {
            if (parsed.defaultTabMode === 'scratchpad' || parsed.defaultTabMode === 'console') {
              this.defaultTabMode = parsed.defaultTabMode;
            }
            if (typeof parsed.isSidebarVisible === 'boolean') {
              this.isSidebarVisible = parsed.isSidebarVisible;
            }
          }
        }
      } catch (err) {
        debugLog('Failed to load user settings', err);
      } finally {
        this.loaded = true;
      }

      await this.checkOutlineCache();
    },

    /**
     * Checks if outline cache exists in localStorage.
     */
    async checkOutlineCache() {
      try {
        const cached = await handleLocalStorage('getItem', OUTLINE_CACHE_KEY);
        this.hasOutlineCache = Boolean(cached);
      } catch {
        this.hasOutlineCache = false;
      }
    },

    /**
     * Updates and persists the default tab mode.
     *
     * @param {'console'|'scratchpad'} mode
     */
    async setDefaultTabMode(mode) {
      const validMode = mode === 'scratchpad' ? 'scratchpad' : 'console';
      this.defaultTabMode = validMode;

      try {
        let currentSettings = {};
        const raw = await handleLocalStorage('getItem', USER_SETTINGS_STORAGE_KEY);
        if (raw) {
          try {
            const parsed = JSON.parse(raw);
            if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
              currentSettings = parsed;
            }
          } catch {
            currentSettings = {};
          }
        }

        currentSettings.defaultTabMode = validMode;
        const serialized = JSON.stringify(currentSettings);

        await handleLocalStorage('setItem', USER_SETTINGS_STORAGE_KEY, serialized);
      } catch (err) {
        debugLog('Failed to save user settings', err);
      }
    },

    /**
     * Updates and persists the outline sidebar visibility.
     *
     * @param {boolean} visible
     */
    async setSidebarVisible(visible) {
      const isVisible = Boolean(visible);
      this.isSidebarVisible = isVisible;

      try {
        let currentSettings = {};
        const raw = await handleLocalStorage('getItem', USER_SETTINGS_STORAGE_KEY);
        if (raw) {
          try {
            const parsed = JSON.parse(raw);
            if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
              currentSettings = parsed;
            }
          } catch {
            currentSettings = {};
          }
        }

        currentSettings.isSidebarVisible = isVisible;
        const serialized = JSON.stringify(currentSettings);

        await handleLocalStorage('setItem', USER_SETTINGS_STORAGE_KEY, serialized);
      } catch (err) {
        debugLog('Failed to save sidebar visibility setting', err);
      }
    },

    /**
     * Toggles the outline sidebar visibility.
     */
    async toggleSidebarVisible() {
      await this.setSidebarVisible(!this.isSidebarVisible);
    },

    /**
     * Clears outline cache from storage.
     */
    async clearOutlineCache() {
      this.isClearingCache = true;
      try {
        await handleLocalStorage('removeItem', OUTLINE_CACHE_KEY);
        this.hasOutlineCache = false;
        return true;
      } catch (err) {
        debugLog('Failed to clear outline cache', err);
        return false;
      } finally {
        this.isClearingCache = false;
      }
    },
  },
});
