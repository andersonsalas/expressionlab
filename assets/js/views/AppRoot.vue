<script setup>
import { onMounted, onUnmounted } from 'vue';
import { useUiStore } from '../stores/ui';
import { useSettingsStore } from '../stores/settings.js';
import { useTabsStore } from '../stores/tabs.js';
import { handleUnlock } from '../lib/unlock-handler.js';
import { ensureStorageCryptoContext } from '../lib/storage-crypto.js';
import { hasAddons } from '../lib/helpers.js';
import { setupCodeActionListeners, removeCodeActionListeners } from '../lib/code-actions.js';
import AboutModal from '../components/modals/AboutModal.vue';
import SettingsModal from '../components/modals/SettingsModal.vue';
import LibraryModal from '../components/modals/LibraryModal.vue';
import DataViewerModal from '../components/modals/DataViewerModal.vue';
import HttpRequestBuilderModal from '../components/modals/HttpRequestBuilderModal.vue';
import DatabaseBuilderModal from '../components/modals/DatabaseBuilderModal.vue';
import EntityQueryBuilderModal from '../components/modals/EntityQueryBuilderModal.vue';
import FilesystemInspectorModal from '../components/modals/FilesystemInspectorModal.vue';
import DialogModal from '../components/modals/DialogModal.vue';
import ConsolePassword from '../components/console/ConsolePassword.vue';

const uiStore = useUiStore();
const settingsStore = useSettingsStore();
const tabsStore = useTabsStore();

onMounted(async () => {
  setupCodeActionListeners();

  // When the console starts unlocked (e.g. EXPRESSION_LAB_SANDBOX_ENABLED === false),
  // initialize dev-mode storage encryption and hydrate settings & license immediately.
  if (!uiStore.isLocked) {
    await ensureStorageCryptoContext();
    if (typeof window !== 'undefined' && typeof window.dispatchEvent === 'function') {
      window.dispatchEvent(new CustomEvent('expressionlab:unlocked'));
    }
    await settingsStore.loadSettings();

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
    <DataViewerModal
      :close-on-outside-click="false"
    />
    <HttpRequestBuilderModal
      :close-on-outside-click="false"
    />
    <DatabaseBuilderModal
      :close-on-outside-click="false"
    />
    <EntityQueryBuilderModal
      :close-on-outside-click="false"
    />
    <FilesystemInspectorModal
      :close-on-outside-click="false"
    />
    <component
      :is="'ProNagModal'"
      v-if="hasAddons()"
    />
    <component
      :is="'NotebookPropertiesModal'"
      v-if="hasAddons()"
    />
    <!-- /Base Modals -->

    <!-- Dialog Modal Layer (teleported to body, above base modals) -->
    <DialogModal />
    <!-- /Dialog Modal Layer -->

    <router-view />
  </template>
</template>
