<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useUiStore } from '../../stores/ui';
import { __, sprintf } from '../../lib/helpers.js';
import { handleClipboardCopy } from '../../lib/api/client.js';
import { detectDataType } from '../../lib/data-detector.js';
import DataTree from '../ui/DataTree.vue';
import ImageVisualization from '../console/visualizations/ImageVisualization.vue';
import FileVisualization from '../console/visualizations/FileVisualization.vue';
import DOMPurify from 'dompurify';
import hljs from 'highlight.js/lib/core';
import elscriptLang from '../../lib/highlight/elscript.js';
import javascriptLang from 'highlight.js/lib/languages/javascript';
import phpLang from 'highlight.js/lib/languages/php';
import cssLang from 'highlight.js/lib/languages/css';
import xmlLang from 'highlight.js/lib/languages/xml';
import sqlLang from 'highlight.js/lib/languages/sql';
import jsonLang from 'highlight.js/lib/languages/json';
import markdownLang from 'highlight.js/lib/languages/markdown';

// Register highlight.js grammars
hljs.registerLanguage('elscript', elscriptLang);
hljs.registerLanguage('expressionlab', elscriptLang);
hljs.registerLanguage('javascript', javascriptLang);
hljs.registerLanguage('php', phpLang);
hljs.registerLanguage('css', cssLang);
hljs.registerLanguage('html', xmlLang);
hljs.registerLanguage('xml', xmlLang);
hljs.registerLanguage('sql', sqlLang);
hljs.registerLanguage('json', jsonLang);
hljs.registerLanguage('markdown', markdownLang);

const props = defineProps({
  closeOnOutsideClick: {
    type: Boolean,
    default: false,
  },
});

const uiStore = useUiStore();
const isOpen = computed(() => uiStore.activeModal === 'data-viewer');
const modalData = computed(() => uiStore.activeModalData || {});

const modalTitle = computed(() => {
  if (modalData.value.title) {
    return modalData.value.title;
  }
  if (modalData.value.column) {
    return sprintf(__('Data viewer - %s'), modalData.value.column);
  }
  return __('Data viewer');
});

const viewMode = ref('raw'); // 'raw' | 'preview'
const highlightLanguage = ref('auto');
const wordWrap = ref(false);
const isCopied = ref(false);
const treeRef = ref(null);

const expandAll = () => {
  if (treeRef.value && typeof treeRef.value.expandAll === 'function') {
    treeRef.value.expandAll();
  }
};

const collapseAll = () => {
  if (treeRef.value && typeof treeRef.value.collapseAll === 'function') {
    treeRef.value.collapseAll();
  }
};

const rawContent = computed(() => {
  return modalData.value.content !== undefined ? modalData.value.content : modalData.value.value;
});

const rawVisualizations = computed(() => {
  const data = modalData.value;
  if (!data) return [];
  if (Array.isArray(data.visualizations)) {
    return data.visualizations;
  }
  if (data.item && Array.isArray(data.item.visualizations)) {
    return data.item.visualizations;
  }
  if (data.content && typeof data.content === 'object' && Array.isArray(data.content.visualizations)) {
    return data.content.visualizations;
  }
  if (data.type === 'image' || data.type === 'file') {
    return [data];
  }
  if (data.content && typeof data.content === 'object' && (data.content.type === 'image' || data.content.type === 'file')) {
    return [data.content];
  }
  return [];
});

const supportedVisualizations = computed(() => {
  return rawVisualizations.value.filter((v) => v && (v.type === 'image' || v.type === 'file'));
});

const activeVisualization = computed(() => {
  if (typeof viewMode.value === 'number') {
    return supportedVisualizations.value[viewMode.value] || null;
  }
  return null;
});

const isWordWrapAvailable = computed(() => {
  if (viewMode.value === 'raw') {
    return true;
  }
  if (activeVisualization.value?.type === 'file') {
    return true;
  }
  return false;
});

const detectedType = computed(() => {
  return detectDataType(rawContent.value);
});

const rawString = computed(() => {
  const content = rawContent.value;
  if (content === null) return 'null';
  if (content === undefined) return '';
  if (typeof content === 'object') {
    return JSON.stringify(content, null, 2);
  }
  if (detectedType.value.unwrappedString) {
    return detectedType.value.unwrappedString;
  }
  return String(content);
});

const canPreview = computed(() => {
  return detectedType.value.format === 'json' || detectedType.value.format === 'php-serialized';
});

const initViewMode = () => {
  if (!isOpen.value) return;
  isCopied.value = false;
  wordWrap.value = false;
  const detected = detectedType.value;
  if (supportedVisualizations.value.length > 0) {
    viewMode.value = 0;
  } else if (canPreview.value) {
    viewMode.value = 'preview';
  } else {
    viewMode.value = 'raw';
  }

  if (detected.suggestedLang && detected.suggestedLang !== 'auto') {
    highlightLanguage.value = detected.suggestedLang;
  } else {
    highlightLanguage.value = 'auto';
  }
};

// Configure default view mode when modal opens or payload changes
watch(isOpen, (opened) => {
  if (opened) {
    initViewMode();
  }
}, { immediate: true });

watch(modalData, () => {
  initViewMode();
});

watch(canPreview, (can) => {
  if (!can && viewMode.value === 'preview') {
    viewMode.value = supportedVisualizations.value.length > 0 ? 0 : 'raw';
  }
});

watch(supportedVisualizations, (vizList) => {
  if (typeof viewMode.value === 'number' && !vizList[viewMode.value]) {
    viewMode.value = vizList.length > 0 ? 0 : (canPreview.value ? 'preview' : 'raw');
  }
});

const escapeHtml = (str) => {
  return str
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
};

const effectiveLanguage = computed(() => {
  if (highlightLanguage.value === 'none') {
    return 'plaintext';
  }
  if (highlightLanguage.value !== 'auto') {
    return highlightLanguage.value;
  }
  if (detectedType.value.suggestedLang && detectedType.value.suggestedLang !== 'auto') {
    return detectedType.value.suggestedLang;
  }
  return 'plaintext';
});

const highlightedCode = computed(() => {
  const text = rawString.value;
  if (highlightLanguage.value === 'none') {
    return escapeHtml(text);
  }

  // Safety threshold: don't highlight huge payloads to prevent main thread freeze
  if (text.length > 200000) {
    return escapeHtml(text);
  }

  const lang = effectiveLanguage.value;
  try {
    if (lang && lang !== 'auto' && lang !== 'plaintext' && hljs.getLanguage(lang)) {
      const res = hljs.highlight(text, { language: lang });
      return DOMPurify.sanitize(res.value);
    }
    const res = hljs.highlightAuto(text);
    return DOMPurify.sanitize(res.value);
  } catch {
    return escapeHtml(text);
  }
});

let copiedTimer = null;

const copyRaw = async () => {
  if (activeVisualization.value) {
    if (activeVisualization.value.type === 'file') {
      await handleClipboardCopy(String(activeVisualization.value.content || ''));
      isCopied.value = true;
      clearTimeout(copiedTimer);
      copiedTimer = setTimeout(() => {
        isCopied.value = false;
      }, 2000);
      return;
    }
    if (activeVisualization.value.type === 'image' && activeVisualization.value.base64) {
      const mime = activeVisualization.value.mime || 'image/png';
      await handleClipboardCopy(`data:${mime};base64,${activeVisualization.value.base64}`);
      isCopied.value = true;
      clearTimeout(copiedTimer);
      copiedTimer = setTimeout(() => {
        isCopied.value = false;
      }, 2000);
      return;
    }
  }
  await handleClipboardCopy(rawString.value);
  isCopied.value = true;
  clearTimeout(copiedTimer);
  copiedTimer = setTimeout(() => {
    isCopied.value = false;
  }, 2000);
};

const closeModal = () => {
  uiStore.closeModal();
};

const handleOutsideClick = () => {
  if (props.closeOnOutsideClick) {
    closeModal();
  }
};

const handleKeydown = (event) => {
  if (isOpen.value && event.key === 'Escape') {
    closeModal();
  }
};

onMounted(() => {
  window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown);
  clearTimeout(copiedTimer);
});
</script>

<template>
  <div
    v-if="isOpen"
    id="data-viewer-modal"
    class="modal-overlay"
    @mousedown.self="handleOutsideClick"
  >
    <div class="modal-container data-viewer-modal-container">
      <button
        type="button"
        class="modal-close"
        :title="__('Close')"
        @click="closeModal"
      >
        <div class="codicon codicon-close" />
      </button>

      <div class="modal-header">
        <h2>{{ modalTitle }}</h2>
      </div>

      <!-- Tabs Header (.scratchpad-output-header layout) -->
      <div class="scratchpad-output-header data-viewer-tabs-header">
        <div class="scratchpad-output-tabs">
          <button
            type="button"
            class="scratchpad-tab-btn"
            :class="{ active: viewMode === 'raw' }"
            @click="viewMode = 'raw'"
          >
            <span>{{ __('Raw') }}</span>
          </button>

          <button
            v-if="canPreview"
            type="button"
            class="scratchpad-tab-btn"
            :class="{ active: viewMode === 'preview' }"
            @click="viewMode = 'preview'"
          >
            <span>{{ __('Preview') }}</span>
          </button>

          <button
            v-for="(viz, idx) in supportedVisualizations"
            :key="idx"
            type="button"
            class="scratchpad-tab-btn"
            :class="{ active: viewMode === idx }"
            @click="viewMode = idx"
          >
            <span>{{ __(viz.title || (viz.type === 'image' ? 'Image' : 'File')) }}</span>
          </button>
        </div>

        <div class="scratchpad-output-actions">
          <button
            type="button"
            class="output-action-btn"
            :title="isCopied ? __('Copied!') : __('Copy to clipboard')"
            @click="copyRaw"
          >
            <span :class="['codicon', isCopied ? 'codicon-check' : 'codicon-copy']" />
          </button>
        </div>
      </div>

      <!-- Toolbar inferior (.library-main-header layout) -->
      <div class="data-viewer-toolbar">
        <!-- Syntax Language Selector -->
        <div class="toolbar-section toolbar-section-lang">
          <select
            v-model="highlightLanguage"
            class="viewer-lang-select"
            :disabled="viewMode !== 'raw'"
          >
            <option value="auto">
              {{ __('Auto') }}
            </option>
            <option value="none">
              {{ __('No highlighting') }}
            </option>
            <option value="html">
              HTML
            </option>
            <option value="css">
              CSS
            </option>
            <option value="javascript">
              JavaScript
            </option>
            <option value="php">
              PHP
            </option>
            <option value="sql">
              SQL
            </option>
            <option value="json">
              JSON
            </option>
            <option value="elscript">
              Expression Lab Language
            </option>
            <option value="markdown">
              Markdown
            </option>
          </select>
        </div>

        <!-- Divider | -->
        <div class="toolbar-divider" />

        <!-- Word Wrap Button -->
        <div class="toolbar-section toolbar-section-wrap">
          <button
            type="button"
            class="toolbar-button btn-word-wrap"
            :class="{ active: wordWrap }"
            :disabled="!isWordWrapAvailable"
            :title="__('Word Wrap')"
            @click="wordWrap = !wordWrap"
          >
            <span class="codicon codicon-word-wrap" />
            <span>{{ __('Word Wrap') }}</span>
          </button>
        </div>

        <!-- Tree Controls Pushed Far Right -->
        <div class="toolbar-section toolbar-section-tree">
          <button
            type="button"
            class="toolbar-button btn-expand-all"
            :disabled="viewMode !== 'preview' || !canPreview"
            :title="__('Expand all')"
            @click="expandAll"
          >
            <span class="codicon codicon-expand-all" />
            <span>{{ __('Expand all') }}</span>
          </button>

          <button
            type="button"
            class="toolbar-button btn-collapse-all"
            :disabled="viewMode !== 'preview' || !canPreview"
            :title="__('Collapse all')"
            @click="collapseAll"
          >
            <span class="codicon codicon-collapse-all" />
            <span>{{ __('Collapse all') }}</span>
          </button>
        </div>
      </div>

      <!-- Main Viewer Area -->
      <div class="modal-content data-viewer-content">
        <!-- Raw Mode -->
        <div
          v-if="viewMode === 'raw'"
          class="viewer-raw-area viewer-highlight-area"
          :class="{ 'has-word-wrap': wordWrap }"
        >
          <!-- eslint-disable vue/no-v-html -->
          <pre><code
            :class="['hljs', `language-${effectiveLanguage}`]"
            v-html="highlightedCode"
          /></pre>
          <!-- eslint-enable vue/no-v-html -->
        </div>

        <!-- Preview Mode -->
        <div
          v-else-if="viewMode === 'preview'"
          class="viewer-preview-area"
        >
          <!-- When preview is available -->
          <template v-if="canPreview">
            <DataTree
              ref="treeRef"
              :data="detectedType.data"
              :show-search="true"
            />
          </template>

          <!-- Empty state when no preview is available -->
          <div
            v-else
            class="data-viewer-empty-state"
          >
            <div class="codicon codicon-info" />
            <span>{{ __('No preview available') }}</span>
          </div>
        </div>

        <!-- Visualization Mode (Image & File) -->
        <div
          v-else-if="typeof viewMode === 'number' && supportedVisualizations[viewMode]"
          class="viewer-preview-area data-viewer-viz-area"
          :class="[ 'visualization-type-' + supportedVisualizations[viewMode].type ]"
        >
          <ImageVisualization
            v-if="supportedVisualizations[viewMode].type === 'image'"
            :data="supportedVisualizations[viewMode]"
          />
          <FileVisualization
            v-else-if="supportedVisualizations[viewMode].type === 'file'"
            :data="supportedVisualizations[viewMode]"
            :word-wrap="wordWrap"
            :hide-word-wrap="true"
          />
        </div>
      </div>

      <!-- Footer -->
      <div class="modal-footer">
        <button
          type="button"
          class="btn btn-default fw-100px"
          @click="closeModal"
        >
          {{ __('Close') }}
        </button>
      </div>
    </div>
  </div>
</template>
