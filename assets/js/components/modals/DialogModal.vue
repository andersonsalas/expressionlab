<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { useUiStore } from '../../stores/ui';
import { __ } from '../../lib/helpers.js';

const uiStore = useUiStore();
const isOpen = computed(() => uiStore.activeDialog === 'dialog');
const modalData = computed(() => uiStore.activeDialogData || {});

const iconMap = {
  info: 'codicon-info',
  warning: 'codicon-warning',
  error: 'codicon-error',
  success: 'codicon-pass',
  prompt: 'codicon-key',
};

const dialogIcon = computed(() => {
  if (modalData.value.icon && iconMap[modalData.value.icon]) {
    return iconMap[modalData.value.icon];
  }
  if (modalData.value.icon && typeof modalData.value.icon === 'string' && modalData.value.icon.startsWith('codicon-')) {
    return modalData.value.icon;
  }
  return iconMap[modalData.value.type] || iconMap.info;
});
const isConfirm = computed(() => modalData.value.type === 'confirm');
const isPrompt = computed(() => modalData.value.type === 'prompt');
const title = computed(() => modalData.value.title ? __(modalData.value.title) : '');
const message = computed(() => modalData.value.message ? __(modalData.value.message) : '');
const confirmText = computed(() => {
  const text = modalData.value.confirmText || modalData.value.confirmLabel;
  return text ? __(text) : __('Accept');
});
const cancelText = computed(() => {
  const text = modalData.value.cancelText || modalData.value.cancelLabel;
  return text ? __(text) : __('Cancel');
});
const denyText = computed(() => {
  const text = modalData.value.denyText || modalData.value.denyLabel;
  return text ? __(text) : '';
});

const promptValue = ref('');
const promptInputRef = ref(null);
const confirmBtnRef = ref(null);
const promptInputType = computed(() => modalData.value.inputType || 'text');
const promptAutocomplete = computed(
  () => modalData.value.autocomplete || (promptInputType.value === 'password' ? 'current-password' : 'off')
);
const promptPlaceholder = computed(() => modalData.value.placeholder ? __(modalData.value.placeholder) : '');

let openedAt = 0;

const handleCancel = () => {
  promptValue.value = '';
  if (typeof modalData.value.onCancel === 'function') {
    modalData.value.onCancel();
  }
};

const handleConfirm = () => {
  const submitted = isPrompt.value ? promptValue.value : undefined;
  promptValue.value = '';
  if (typeof modalData.value.onConfirm === 'function') {
    modalData.value.onConfirm(submitted);
  }
};

const handleKeyDown = (e) => {
  if (!isOpen.value) return;

  if (e.key === 'Escape') {
    e.preventDefault();
    if (modalData.value.type === 'alert') {
      handleConfirm();
    } else {
      handleCancel();
    }
    return;
  }

  if (e.key === 'Enter') {
    if (modalData.value.type === 'alert') {
      // Prevent dismissing an alert modal immediately if triggered by Enter key in quick succession
      if (Date.now() - openedAt < 150) {
        return;
      }
      e.preventDefault();
      handleConfirm();
    } else if (!isPrompt.value) {
      e.preventDefault();
      handleConfirm();
    }
  }
};

watch(modalData, () => {
  openedAt = Date.now();
});

watch(isOpen, async (val) => {
  if (val) {
    openedAt = Date.now();
    if (typeof window !== 'undefined') {
      window.addEventListener('keydown', handleKeyDown);
    }
    await nextTick();
    if (isPrompt.value) {
      promptValue.value = modalData.value.defaultValue || '';
      promptInputRef.value?.focus?.();
    } else {
      confirmBtnRef.value?.focus?.();
    }
  } else {
    promptValue.value = '';
    if (typeof window !== 'undefined') {
      window.removeEventListener('keydown', handleKeyDown);
    }
  }
});

const handleDeny = () => {
  promptValue.value = '';
  if (typeof modalData.value.onDeny === 'function') {
    modalData.value.onDeny();
  }
};
</script>

<template>
  <Teleport to="body">
    <div
      v-if="isOpen"
      id="dialog-modal"
      class="dialog-overlay"
    >
      <div class="modal-container dialog-modal-container">
        <div class="modal-header">
          <h2>{{ title }}</h2>
        </div>
        <div
          class="modal-content dialog-content"
          :class="{ 'has-prompt': isPrompt }"
        >
          <div
            v-if="isConfirm && !modalData.icon"
            class="dialog-icon confirm-icon"
          >
            <div class="codicon codicon-question" />
          </div>
          <div
            v-else
            class="dialog-icon"
            :class="{ 'prompt-icon': isPrompt, 'confirm-icon': isConfirm }"
          >
            <div :class="['codicon', dialogIcon]" />
          </div>

          <div class="dialog-body">
            <p class="dialog-message">
              {{ message }}
            </p>

            <div
              v-if="isPrompt"
              class="dialog-prompt-wrapper"
            >
              <input
                ref="promptInputRef"
                v-model="promptValue"
                :type="promptInputType"
                :autocomplete="promptAutocomplete"
                class="form-control dialog-prompt-input"
                :placeholder="promptPlaceholder"
                @keydown.enter.prevent.stop="handleConfirm"
              >
            </div>
          </div>
        </div>
        <div 
          class="modal-footer"
          :style="denyText ? 'gap: 8px;' : ''"
        >
          <button
            v-if="isConfirm || isPrompt"
            class="btn btn-default fw-100px"
            @click="handleCancel"
          >
            {{ cancelText }}
          </button>
          <button
            v-if="isConfirm && denyText"
            class="btn btn-default fw-100px"
            @click="handleDeny"
          >
            {{ denyText }}
          </button>
          <button
            ref="confirmBtnRef"
            class="btn fw-100px"
            :class="[isConfirm || isPrompt ? 'btn-primary' : 'btn-default']"
            @click="handleConfirm"
          >
            {{ confirmText }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>