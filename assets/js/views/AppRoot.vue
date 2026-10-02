<script setup>
import { onMounted, onUnmounted } from 'vue';
import { useUiStore } from '../stores/ui';
import { useSettingsStore } from '../stores/settings.js';
import { useTabsStore } from '../stores/tabs.js';
import { handleUnlock } from '../lib/unlock-handler.js';
import { setupCodeActionListeners, removeCodeActionListeners } from '../lib/code-actions.js';
import AboutModal from '../components/modals/AboutModal.vue';
import SettingsModal from '../components/modals/SettingsModal.vue';
import LibraryModal from '../components/modals/LibraryModal.vue';
import DialogModal from '../components/modals/DialogModal.vue';
import ConsolePassword from '../components/console/ConsolePassword.vue';

const uiStore = useUiStore();
const settingsStore = useSettingsStore();
const tabsStore = useTabsStore();

onMounted(async () => {
  setupCodeActionListeners();
  await settingsStore.loadSettings();

  // If in sandbox mode where settings are loaded asynchronously:
  if (settingsStore.defaultTabMode === 'scratchpad' && tabsStore.tabs.length === 1) {
    const firstTab = tabsStore.tabs[0];
    if (firstTab.mode === 'repl' && !firstTab.isCustomTitle && firstTab.history.length <= 1 && !firstTab.scratchpadCode && !firstTab.replDraft) {
      tabsStore.setTabMode(firstTab.id, 'scratchpad');
    }
  }
});

onUnmounted(() => {
  removeCodeActionListeners();
});
</script>

<template>
  <!-- Lock Screen -->
  <ConsolePassword
    v-if="uiStore.isLocked"
    @unlocked="handleUnlock"
  />
  <!-- /Lock Screen -->

  <!-- Base Modals -->
  <template v-else>
    <AboutModal
      :close-on-outside-click="false"
    />
    <SettingsModal
      :close-on-outside-click="false"
    />
    <LibraryModal
      :close-on-outside-click="false"
    />
    <!-- /Base Modals -->

    <!-- Dialog Modal Layer (teleported to body, above base modals) -->
    <DialogModal />
    <!-- /Dialog Modal Layer -->

    <router-view />
  </template>
</template>
