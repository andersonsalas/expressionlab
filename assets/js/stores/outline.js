import { defineStore } from 'pinia';
import { handleFetch, handleLocalStorage } from '../lib/api/client.js';
import { processOutlineData } from '../lib/outline-processor.js';

const OUTLINE_CACHE_KEY = 'el_outline_cache';

export const useOutlineStore = defineStore('outline', {
  state: () => ({
    outlineTree: [],
    outlineFlat: [],
    outlineChained: null,
    loading: true,
    loaded: false,
  }),

  actions: {
    async loadOutline(force = false) {
      if (this.loaded && !force) return;

      const enableCache = Boolean(window.el_settings?.settings?.enable_cache);

      if (enableCache && !force) {
        try {
          const cached = await handleLocalStorage('getItem', OUTLINE_CACHE_KEY);
          if (cached) {
            const compactData = JSON.parse(cached);
            const result = processOutlineData(compactData);
            this.outlineFlat = result.outlineFlat;
            this.outlineTree = result.outlineTree;
            this.outlineChained = result.outlineChained;
            this.loading = false;
            this.loaded = true;
            return;
          }
        } catch (e) {
          /* Fall through to fetch */
        }
      }

      const outlineUrl =
        window.el_settings.ajax_url +
        '?action=expressionlab_get_outline&nonce=' +
        window.el_settings.nonce;

      try {
        const response = await handleFetch(outlineUrl, { method: 'GET' });
        const json = await response.json();

        if (json.success === true) {
          const result = processOutlineData(json.data.data);
          this.outlineFlat = result.outlineFlat;
          this.outlineTree = result.outlineTree;
          this.outlineChained = result.outlineChained;
          this.loading = false;
          this.loaded = true;

          if (enableCache) {
            await handleLocalStorage('setItem', OUTLINE_CACHE_KEY, JSON.stringify(json.data.data));
          }
        } else {
          this.outlineFlat = [];
          this.outlineTree = [];
          this.outlineChained = null;
          this.loading = false;
          this.loaded = false;
        }
      } catch (err) {
        this.outlineFlat = [];
        this.outlineTree = [];
        this.outlineChained = null;
        this.loading = false;
        this.loaded = false;
      }
    },
  },
});
