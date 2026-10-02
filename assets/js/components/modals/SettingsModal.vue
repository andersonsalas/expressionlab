<script setup>
import { computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { useUiStore } from '../../stores/ui';
import { useSettingsStore } from '../../stores/settings';
import { __ } from '../../lib/helpers.js';

const props = defineProps({
  closeOnOutsideClick: {
    type: Boolean,
    default: true
  },
  customCloseElementSelector: {
    type: String,
    default: null
  }
});

const uiStore = useUiStore();
const settingsStore = useSettingsStore();

const isOpen = computed(() => uiStore.activeModal === 'settings');

const selectedTabMode = computed({
  get: () => settingsStore.defaultTabMode,
  set: (val) => {
    settingsStore.setDefaultTabMode(val);
  }
});

const hasOutlineCache = computed(() => settingsStore.hasOutlineCache);
const isClearingCache = computed(() => settingsStore.isClearingCache);

const closeModal = () => {
  uiStore.closeModal();
};

const handleOutsideClick = () => {
  if (props.closeOnOutsideClick) {
    closeModal();
  }
};

const handleClearCache = async () => {
  if (!hasOutlineCache.value || isClearingCache.value) return;
  await settingsStore.clearOutlineCache();
};

const attachArbitraryListener = () => {
  if (props.customCloseElementSelector) {
    const el = document.querySelector(props.customCloseElementSelector);
    if (el) {
      el.addEventListener('click', closeModal);
    }
  }
};

const removeArbitraryListener = () => {
  if (props.customCloseElementSelector) {
    const el = document.querySelector(props.customCloseElementSelector);
    if (el) {
      el.removeEventListener('click', closeModal);
    }
  }
};

watch(isOpen, async (newVal) => {
  if (newVal) {
    await settingsStore.checkOutlineCache();
    await nextTick();
    attachArbitraryListener();
  } else {
    removeArbitraryListener();
  }
});

onMounted(async () => {
  if (isOpen.value) {
    await settingsStore.checkOutlineCache();
  }
});

onUnmounted(() => {
  removeArbitraryListener();
});
</script>

<template>
  <div
    v-if="isOpen"
    id="settings-modal"
    class="modal-overlay"
    @mousedown.self="handleOutsideClick"
  >
    <div class="modal-container settings-modal-container">
      <button
        class="modal-close"
        :title="__('Close')"
        @click="closeModal"
      >
        <div class="codicon codicon-close" />
      </button>

      <div class="modal-header">
        <h2>{{ __('Settings') }}</h2>
      </div>

      <div class="modal-content settings-modal-content">
        <!-- Default New Tab Layout Section -->
        <div class="settings-section">
          <div class="settings-section-title">
            {{ __('Default new tab layout') }}
          </div>
          <div class="settings-section-desc">
            {{ __('Choose the default layout for newly created tabs. This also affects the action of the New Tab button in the tab bar.') }}
          </div>

          <div
            class="settings-options-group"
            role="radiogroup"
            :aria-label="__('Default new tab layout')"
          >
            <!-- Console Option -->
            <label
              class="settings-option"
              :class="{ selected: selectedTabMode === 'console' }"
            >
              <div class="settings-radio-wrapper">
                <input
                  v-model="selectedTabMode"
                  type="radio"
                  name="defaultTabMode"
                  value="console"
                  class="settings-radio"
                >
              </div>
              <div class="settings-option-icon">
                <span class="codicon codicon-terminal" />
              </div>
              <div class="settings-option-body">
                <div class="settings-option-title">
                  {{ __('Console') }}
                </div>
                <div class="settings-option-desc">
                  {{ __('Interactive single-expression evaluation with immediate output.') }}
                </div>
              </div>
            </label>

            <!-- Scratchpad Option -->
            <label
              class="settings-option"
              :class="{ selected: selectedTabMode === 'scratchpad' }"
            >
              <div class="settings-radio-wrapper">
                <input
                  v-model="selectedTabMode"
                  type="radio"
                  name="defaultTabMode"
                  value="scratchpad"
                  class="settings-radio"
                >
              </div>
              <div class="settings-option-icon">
                <span class="codicon codicon-notebook" />
              </div>
              <div class="settings-option-body">
                <div class="settings-option-title">
                  {{ __('Scratchpad') }}
                </div>
                <div class="settings-option-desc">
                  {{ __('Multi-line script editor with dedicated execution and output inspection panels.') }}
                </div>
              </div>
            </label>
          </div>
        </div>

        <hr class="settings-divider">

        <!-- Browser Cache Section -->
        <div class="settings-section">
          <div class="settings-section-title">
            {{ __('Browser cache') }}
          </div>
          <div class="settings-section-desc">
            {{ __('Clear cached WordPress functions, classes, and outline symbols stored in your browser.') }}
          </div>

          <div class="settings-cache-actions">
            <button
              type="button"
              class="btn btn-default"
              :disabled="!hasOutlineCache || isClearingCache"
              @click="handleClearCache"
            >
              <div class="codicon codicon-trash" />
              {{ isClearingCache ? __('Clearing...') : __('Clear cache') }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
