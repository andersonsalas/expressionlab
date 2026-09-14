<script setup>
import { onMounted, onUnmounted } from 'vue';
import { useUiStore } from '../stores/ui';
import { handleUnlock } from '../lib/unlock-handler.js';
import { setupCodeActionListeners, removeCodeActionListeners } from '../lib/code-actions.js';
import AboutModal from '../components/modals/AboutModal.vue';
import LibraryModal from '../components/modals/LibraryModal.vue';
import DialogModal from '../components/modals/DialogModal.vue';
import ConsolePassword from '../components/console/ConsolePassword.vue';

const uiStore = useUiStore();

onMounted(() => {
  setupCodeActionListeners();
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
