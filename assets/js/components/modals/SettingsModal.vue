<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { useUiStore } from '../../stores/ui';
import { useSettingsStore } from '../../stores/settings';
import { __, hasAddons } from '../../lib/helpers.js';

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

const resolveInitialTab = () => {
  const requested = uiStore.activeModalData?.tab || uiStore.activeModalData?.initialTab;
  if (requested === 'license' && !hasAddons()) {
    return 'general';
  }
  if (requested && ['general', 'data', 'license'].includes(requested)) {
    return requested;
  }
  return 'general';
};

const activeTab = ref(resolveInitialTab());

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
    activeTab.value = resolveInitialTab();
    await settingsStore.checkOutlineCache();
    await nextTick();
    attachArbitraryListener();
  } else {
    removeArbitraryListener();
  }
});

onMounted(async () => {
  if (isOpen.value) {
    activeTab.value = resolveInitialTab();
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
    <div
      class="modal-container settings-modal-container"
      role="dialog"
      aria-modal="true"
      :aria-label="__('Settings')"
    >
      <button
        type="button"
        class="modal-close"
        :title="__('Close')"
        @click="closeModal"
      >
        <div class="codicon codicon-close" />
      </button>

      <div class="modal-header">
        <h2>{{ __('Settings') }}</h2>
      </div>

      <!-- Tabs Header -->
      <div class="settings-tabs-header">
        <button
          type="button"
          class="builder-tab-btn"
          :class="{ active: activeTab === 'general' }"
          @click="activeTab = 'general'"
        >
          <span>{{ __('General') }}</span>
        </button>

        <button
          type="button"
          class="builder-tab-btn"
          :class="{ active: activeTab === 'data' }"
          @click="activeTab = 'data'"
        >
          <span>{{ __('Data') }}</span>
        </button>

        <button
          v-if="hasAddons()"
          type="button"
          class="builder-tab-btn"
          :class="{ active: activeTab === 'license' }"
          @click="activeTab = 'license'"
        >
          <span>{{ __('License') }}</span>
        </button>
      </div>

      <div class="modal-content settings-modal-content">
        <!-- 1. General Tab -->
        <div
          v-show="activeTab === 'general'"
          class="settings-tab-panel tab-general"
        >
          <div class="settings-section">
            <div class="panel-section-header">
              <h4>{{ __('Default new tab layout') }}</h4>
            </div>
            <p class="form-help">
              {{ __('Choose the default layout for newly created tabs. This also affects the action of the New Tab button in the tab bar.') }}
            </p>

            <div
              class="presets-grid"
              role="radiogroup"
              :aria-label="__('Default new tab layout')"
            >
              <!-- Console Option -->
              <label
                class="preset-card"
                :class="{ active: selectedTabMode === 'console' }"
                :title="__('Single expression evaluation with instant output.')"
              >
                <input
                  v-model="selectedTabMode"
                  type="radio"
                  name="defaultTabMode"
                  value="console"
                >
                <span class="preset-type-icon codicon codicon-terminal" />
                <span>
                  <strong>{{ __('Console') }}</strong>
                  <small>
                    {{ __('Single expression evaluation with instant output.') }}
                  </small>
                </span>
              </label>

              <!-- Scratchpad Option -->
              <label
                class="preset-card"
                :class="{ active: selectedTabMode === 'scratchpad' }"
                :title="__('Multi-line script editor with dedicated output.')"
              >
                <input
                  v-model="selectedTabMode"
                  type="radio"
                  name="defaultTabMode"
                  value="scratchpad"
                >
                <span class="preset-type-icon codicon codicon-new-session" />
                <span>
                  <strong>{{ __('Scratchpad') }}</strong>
                  <small>
                    {{ __('Multi-line script editor with dedicated output.') }}
                  </small>
                </span>
              </label>

              <!-- Notebook Option -->
              <label
                v-if="hasAddons()"
                class="preset-card"
                :class="{ active: selectedTabMode === 'notebook' }"
                :title="__('Interactive cells with Markdown and code blocks.')"
              >
                <input
                  v-model="selectedTabMode"
                  type="radio"
                  name="defaultTabMode"
                  value="notebook"
                >
                <span class="preset-type-icon codicon codicon-notebook" />
                <span>
                  <strong>{{ __('Notebook') }}</strong>
                  <small>
                    {{ __('Interactive cells with Markdown and code blocks.') }}
                  </small>
                </span>
              </label>
            </div>
          </div>
        </div>

        <!-- 2. Data Tab -->
        <div
          v-show="activeTab === 'data'"
          class="settings-tab-panel tab-data"
        >
          <div class="settings-section">
            <div class="panel-section-header">
              <h4>{{ __('Browser cache') }}</h4>
            </div>
            <p class="form-help">
              {{ __('Clear cached WordPress functions, classes, and outline symbols stored in your browser.') }}
            </p>

            <div class="settings-cache-actions">
              <button
                type="button"
                class="btn btn-default"
                :disabled="!hasOutlineCache || isClearingCache"
                @click="handleClearCache"
              >
                {{ isClearingCache ? __('Clearing...') : __('Clear cache') }}
              </button>
            </div>
          </div>
        </div>

        <!-- 3. License Tab (Solo addon) -->
        <div
          v-if="hasAddons()"
          v-show="activeTab === 'license'"
          class="settings-tab-panel tab-license"
        >
          <component :is="'ProLicenseSettings'" />
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="modal-footer">
        <button
          type="button"
          class="btn btn-default"
          @click="closeModal"
        >
          {{ __('Close') }}
        </button>
      </div>
    </div>
  </div>
</template>
