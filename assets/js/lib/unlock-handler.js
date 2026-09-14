import { nextTick } from 'vue';
import { useUiStore } from '../stores/ui.js';
import { requestChallenge } from './api/client.js';
import { __, debugLog } from './helpers.js';

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

    uiStore.unlock(seed);
    if (typeof callback === 'function') callback(null);
    nextTick(() => uiStore.triggerSnippetInsert(null));
  } catch (e) {
    debugLog('Verification error', e, 'error');
    uiStore.setPrivateKey(null);
    if (typeof callback === 'function') callback(__('An error occurred during verification.'));
  }
}
