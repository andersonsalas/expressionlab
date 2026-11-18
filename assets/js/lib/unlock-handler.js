import { nextTick } from 'vue';
import { useUiStore } from '../stores/ui.js';
import { useSettingsStore } from '../stores/settings.js';
import { useTabsStore } from '../stores/tabs.js';
import { requestChallenge } from './api/client.js';
import { initStorageCryptoFromSeed } from './storage-crypto.js';
import { __, debugLog, hasAddons } from './helpers.js';

export async function handleUnlock(seed, callback) {
  const uiStore = useUiStore();
  uiStore.setPrivateKey(seed);

  try {
    const isUnlocked = await requestChallenge();
    if (!isUnlocked) {
      debugLog('Initial challenge request failed', null, 'error');
      uiStore.setPrivateKey(null);
      if (typeof callback === 'function') callback(__('Invalid passphrase.'));
      return;
    }

    try {
      await initStorageCryptoFromSeed(
        seed,
        window.el_settings?.salt,
        window.el_settings?.public_key,
        window.el_settings?.user_id
      );
    } catch (cryptoErr) {
      debugLog('Storage crypto initialization warning', cryptoErr, 'warn');
    }

    try {
      const settingsStore = useSettingsStore();
      await settingsStore.loadSettings();
      const tabsStore = useTabsStore();
      if (tabsStore.tabs.length === 1) {
        const firstTab = tabsStore.tabs[0];
        const targetMode =
          settingsStore.defaultTabMode === 'notebook' && hasAddons()
            ? 'notebook'
            : settingsStore.defaultTabMode === 'scratchpad'
              ? 'scratchpad'
              : 'repl';
        if (firstTab.mode !== targetMode && tabsStore.isTabCleanAndEmpty(firstTab)) {
          tabsStore.setTabMode(firstTab.id, targetMode);
        }
      }
    } catch (settingsErr) {
      debugLog('Post-unlock settings hydration warning', settingsErr, 'warn');
    }

    if (typeof window !== 'undefined' && typeof window.dispatchEvent === 'function') {
      window.dispatchEvent(new CustomEvent('expressionlab:unlocked'));
    }

    uiStore.unlock(seed);
    if (typeof callback === 'function') callback(null);
    nextTick(() => uiStore.triggerSnippetInsert(null));
  } catch (e) {
    debugLog('Verification error', e, 'error');
    uiStore.setPrivateKey(null);
    if (typeof callback === 'function') callback(__('An error occurred during verification.'));
  }
}
