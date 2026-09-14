<script setup>
import { computed } from 'vue';
import { useUiStore } from '../../stores/ui';
import { __ } from '../../lib/helpers.js';

const uiStore = useUiStore();
const isOpen = computed(() => uiStore.activeDialog === 'dialog');
const modalData = computed(() => uiStore.activeDialogData || {});

const iconMap = {
  info: 'codicon-info',
  warning: 'codicon-warning',
  error: 'codicon-error',
  success: 'codicon-pass'
};

const dialogIcon = computed(() => iconMap[modalData.value.type] || iconMap.info);
const isConfirm = computed(() => modalData.value.type === 'confirm');
const title = computed(() => modalData.value.title ? __(modalData.value.title) : '');
const message = computed(() => modalData.value.message ? __(modalData.value.message) : '');
const confirmText = computed(() => modalData.value.confirmText ? __(modalData.value.confirmText) : __('Accept'));
const cancelText = computed(() => modalData.value.cancelText ? __(modalData.value.cancelText) : __('Cancel'));
const denyText = computed(() => modalData.value.denyText ? __(modalData.value.denyText) : '');

const handleConfirm = () => {
  if (typeof modalData.value.onConfirm === 'function') {
    modalData.value.onConfirm();
  }
};

const handleCancel = () => {
  if (typeof modalData.value.onCancel === 'function') {
    modalData.value.onCancel();
  }
};

const handleDeny = () => {
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
        <div class="modal-content dialog-content">
          <div
            v-if="isConfirm"
            class="dialog-icon confirm-icon"
          >
            <div class="codicon codicon-question" />
          </div>
          <div
            v-else
            class="dialog-icon"
          >
            <div :class="['codicon', dialogIcon]" />
          </div>
          <p class="dialog-message">
            {{ message }}
          </p>
        </div>
        <div 
          class="modal-footer"
          :style="denyText ? 'gap: 8px;' : ''"
        >
          <button
            v-if="isConfirm && denyText"
            class="btn btn-default fw-100px"
            @click="handleDeny"
          >
            {{ denyText }}
          </button>
          <button
            v-if="isConfirm"
            class="btn btn-default fw-100px"
            @click="handleCancel"
          >
            {{ cancelText }}
          </button>
          <button
            class="btn fw-100px"
            :class="[isConfirm ? 'btn-primary' : 'btn-default']"
            @click="handleConfirm"
          >
            {{ confirmText }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>