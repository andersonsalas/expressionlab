<script setup>
import { computed, watch, onUnmounted, nextTick } from 'vue';
import { useUiStore } from '../../stores/ui';
import ExpressionLabIcon from '../icons/ExpressionLabIcon.vue';
import { __ } from '../../lib/helpers.js';
import { handleOpenUrl } from '../../lib/api/client.js';

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
const isOpen = computed(() => uiStore.activeModal === 'about' || uiStore.activeModal === 'settings');
const version = computed(() => window.el_settings?.version || '');

const closeModal = () => {
  uiStore.closeModal();
};

const handleOutsideClick = () => {
  if (props.closeOnOutsideClick) {
    closeModal();
  }
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
    await nextTick();
    attachArbitraryListener();
  } else {
    removeArbitraryListener();
  }
});

onUnmounted(() => {
  removeArbitraryListener();
});
</script>

<template>
  <div
    v-if="isOpen"
    id="about-modal"
    class="modal-overlay"
    @mousedown.self="handleOutsideClick"
  >
    <div class="modal-container about-modal-container">
      <button
        class="modal-close"
        :title="__('Close')"
        @click="closeModal"
      >
        <div class="codicon codicon-close" />
      </button>
      <div class="modal-header">
        <h2>{{ __('About') }}</h2>
      </div>
      <div class="modal-content about-modal-content">
        <div class="about-logo-card">
          <ExpressionLabIcon class="about-logo-svg" />
        </div>
        <div class="about-info-body">
          <div class="about-app-title">
            Expression Lab
          </div>
          <div class="about-app-version">
            {{ version }}
          </div>
          <div class="about-credits">
            {{ __('© Anderson Salas and contributors.') }}
          </div>
          <div class="about-license">
            <p>
              {{ __('This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.') }}
            </p>
            <p>
              {{ __('This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.') }}
            </p>
            <p>
              {{ __('You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.') }}
            </p>
          </div>
          <hr class="about-divider">
          <div class="about-links">
            <a
              href="https://expressionlab.io"
              target="_blank"
              rel="noopener noreferrer"
              @click.prevent="handleOpenUrl('https://expressionlab.io')"
            >{{ __('Website') }}</a>
            <a
              href="https://expressionlab.io/docs"
              target="_blank"
              rel="noopener noreferrer"
              @click.prevent="handleOpenUrl('https://expressionlab.io/docs')"
            >{{ __('Docs') }}</a>
            <a
              href="https://github.com/andersonsalas/expressionlab"
              target="_blank"
              rel="noopener noreferrer"
              @click.prevent="handleOpenUrl('https://github.com/andersonsalas/expressionlab')"
            >{{ __('GitHub') }}</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
