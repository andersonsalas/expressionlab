import { defineStore } from 'pinia';
import { handleFetch, handleLocalStorage } from '../lib/api/client.js';

export const FILESYSTEM_CACHE_KEY = 'el_filesystem_cache';
export const FILESYSTEM_CACHE_TTL_MS = 60 * 60 * 1000; // 1 hour

export const useFilesystemStore = defineStore('filesystem', {
  state: () => ({
    presets:
      typeof window !== 'undefined' && window.el_settings?.filesystem?.presets
        ? window.el_settings.filesystem.presets
        : [],
    tree:
      typeof window !== 'undefined' && window.el_settings?.filesystem?.tree
        ? window.el_settings.filesystem.tree
        : [],
    limits:
      typeof window !== 'undefined' && window.el_settings?.filesystem?.limits
        ? window.el_settings.filesystem.limits
        : {
            max_read_bytes: 262144,
            max_tail_bytes: 1048576,
          },
    loading: false,
    loaded: Boolean(typeof window !== 'undefined' && window.el_settings?.filesystem?.presets?.length),
    error: null,
    lastFetched: null,
  }),

  actions: {
    resetStore() {
      this.presets = [];
      this.tree = [];
      this.loaded = false;
      this.loading = false;
      this.error = null;
      this.lastFetched = null;
    },

    async clearCache() {
      try {
        await handleLocalStorage('removeItem', FILESYSTEM_CACHE_KEY);
      } catch {
        /* Ignore storage errors */
      }
      this.resetStore();
    },

    async refreshFilesystem() {
      await this.clearCache();
      return this.loadFilesystem(true);
    },

    async loadFilesystem(force = false) {
      const now = Date.now();
      const isMemoryExpired = Boolean(
        this.lastFetched && now - this.lastFetched >= FILESYSTEM_CACHE_TTL_MS
      );

      if (this.loading) return;
      if (this.loaded && !force && !isMemoryExpired) return;

      const enableCache = Boolean(window.el_settings?.settings?.enable_cache);

      if (force) {
        try {
          await handleLocalStorage('removeItem', FILESYSTEM_CACHE_KEY);
        } catch {
          /* Ignore storage errors */
        }
        this.resetStore();
      } else if (enableCache) {
        try {
          const cached = await handleLocalStorage('getItem', FILESYSTEM_CACHE_KEY);
          if (cached) {
            const parsed = JSON.parse(cached);
            if (parsed && typeof parsed === 'object') {
              const hasValidTimestamp = typeof parsed.timestamp === 'number';
              const isExpired = hasValidTimestamp
                ? now - parsed.timestamp >= FILESYSTEM_CACHE_TTL_MS
                : true;

              if (!isExpired && parsed.data) {
                const payload = parsed.data;
                this.presets = payload.presets || [];
                this.tree = payload.tree || [];
                this.limits = payload.limits || this.limits;
                this.lastFetched = parsed.timestamp;
                this.loading = false;
                this.loaded = true;
                return;
              } else {
                await handleLocalStorage('removeItem', FILESYSTEM_CACHE_KEY);
              }
            }
          }
        } catch {
          /* Fall through to fetch */
        }
      }

      this.loading = true;
      this.error = null;

      const url =
        window.el_settings.ajax_url +
        '?action=expressionlab_get_filesystem&nonce=' +
        window.el_settings.nonce;

      try {
        const response = await handleFetch(url, { method: 'GET' });
        const json = await response.json();

        if (json.success === true && json.data?.data) {
          const payload = json.data.data;
          this.presets = payload.presets || [];
          this.tree = payload.tree || [];
          this.limits = payload.limits || this.limits;
          this.lastFetched = Date.now();
          this.loading = false;
          this.loaded = true;

          if (enableCache) {
            const cacheEntry = {
              timestamp: this.lastFetched,
              data: payload,
            };
            await handleLocalStorage('setItem', FILESYSTEM_CACHE_KEY, JSON.stringify(cacheEntry));
          }
        } else {
          this.loading = false;
          this.error = json.data?.message || 'Failed to load filesystem metadata';
        }
      } catch (err) {
        this.loading = false;
        this.error = err.message || 'Network error';
      }
    },
  },
});
