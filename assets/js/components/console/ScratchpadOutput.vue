<script setup>
import { ref, computed, watch } from 'vue';
import VueJsonPretty from 'vue-json-pretty';
import 'vue-json-pretty/lib/styles.css';
import { visualizationHandlers } from './visualizations/registry.js';
import SafeFormattedText from './SafeFormattedText.vue';
import { handleClipboardCopy } from '../../lib/api/client.js';
import { __, sprintf } from '../../lib/helpers.js';
import hljs from 'highlight.js/lib/core';
import elscriptLang from '../../lib/highlight/elscript.js';
import DOMPurify from 'dompurify';

hljs.registerLanguage('elscript', elscriptLang);
hljs.registerLanguage('expressionlab', elscriptLang);

const props = defineProps({
  result: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['clear-output', 'close']);

const activeTab = ref('raw');

const isNonScalar = (val) => typeof val === 'object' && val !== null;

const visualizations = computed(() => {
  if (!props.result) return [];
  if (Array.isArray(props.result.visualizations)) return props.result.visualizations;
  if (props.result.output && Array.isArray(props.result.output.visualizations)) {
    return props.result.output.visualizations;
  }
  return [];
});

const messages = computed(() => {
  if (!props.result || !Array.isArray(props.result.messages)) return [];
  return props.result.messages;
});

const rawResult = computed(() => {
  if (!props.result) return null;
  if (props.result.result !== undefined) return props.result.result;
  if (props.result.output !== undefined) {
    if (props.result.output && typeof props.result.output === 'object' && props.result.output.result !== undefined) {
      return props.result.output.result;
    }
    return props.result.output;
  }
  return props.result;
});

const highlightedScalar = computed(() => {
  const text =
    rawResult.value === null
      ? 'null'
      : rawResult.value === undefined
        ? 'undefined'
        : String(rawResult.value);
  try {
    const { value } = hljs.highlight(text, { language: 'elscript' });
    return DOMPurify.sanitize(value);
  } catch {
    return DOMPurify.sanitize(escapeHtml(text));
  }
});

const objectType = computed(() => props.result?.object_type || null);
const isError = computed(() => props.result?.type === 'error');

// Automatically select optimal tab when new result arrives
watch(
  () => props.result,
  (newRes) => {
    if (!newRes) return;
    if (newRes.type === 'error') {
      activeTab.value = 'raw';
    } else if (visualizations.value.length > 0) {
      activeTab.value = 0;
    } else if (
      messages.value.length > 0 &&
      (rawResult.value === null || rawResult.value === 'null' || rawResult.value === undefined)
    ) {
      activeTab.value = 'output';
    } else {
      activeTab.value = 'raw';
    }
  },
  { immediate: true }
);

const getComponent = (type) => visualizationHandlers.value[type] || null;

const escapeHtml = (str) => {
  const div = document.createElement('div');
  div.appendChild(document.createTextNode(str));
  return div.innerHTML;
};

const generateHTMLTable = (data) => {
  if (!Array.isArray(data) || !data.length) return '';
  const headers = Object.keys(data[0]);
  let html = '<table border="1" style="border-collapse: collapse;"><thead><tr>';
  headers.forEach((h) => {
    html += `<th style="padding: 5px; border: 1px solid #ccc; background: #f0f0f0;">${escapeHtml(h)}</th>`;
  });
  html += '</tr></thead><tbody>';

  data.forEach((row) => {
    html += '<tr>';
    headers.forEach((h) => {
      html += `<td style="padding: 5px; border: 1px solid #ccc;">${escapeHtml(String(row[h] ?? ''))}</td>`;
    });
    html += '</tr>';
  });
  html += '</tbody></table>';
  return html;
};

const copyOutput = () => {
  if (!props.result) return;

  if (activeTab.value === 'output') {
    const text = messages.value.map((m) => `[${(m.type || 'log').toUpperCase()}] ${m.text}`).join('\n');
    handleClipboardCopy(text);
    return;
  }

  if (typeof activeTab.value === 'number') {
    const viz = visualizations.value[activeTab.value];
    if (viz && viz.type === 'table' && Array.isArray(viz.data) && viz.data.length > 0) {
      const headers = Object.keys(viz.data[0]);
      const text = headers.join('\t') + '\n' + viz.data.map((r) => headers.map((h) => r[h]).join('\t')).join('\n');
      const html = generateHTMLTable(viz.data);
      handleClipboardCopy(text, html);
      return;
    }
  }

  const data = rawResult.value;
  const text = isNonScalar(data) ? JSON.stringify(data, null, 2) : String(data ?? 'null');
  handleClipboardCopy(text);
};
</script>

<template>
  <div class="scratchpad-output">
    <!-- Header / Tab Bar -->
    <div class="scratchpad-output-header">
      <div class="scratchpad-output-tabs">
        <button
          type="button"
          class="scratchpad-tab-btn"
          :class="{ active: activeTab === 'raw' }"
          @click="activeTab = 'raw'"
        >
          <span>{{ __('Raw') }}</span>
        </button>

        <button
          v-for="(viz, idx) in visualizations"
          :key="idx"
          type="button"
          class="scratchpad-tab-btn"
          :class="{ active: activeTab === idx }"
          @click="activeTab = idx"
        >
          <span>{{ __(viz.title || 'Visualization') }}</span>
        </button>

        <button
          type="button"
          class="scratchpad-tab-btn"
          :class="{ active: activeTab === 'output' }"
          @click="activeTab = 'output'"
        >
          <span>{{ __('Output') }}</span>
          <span
            v-if="messages.length > 0"
            class="tab-count-badge"
          >{{ messages.length }}</span>
        </button>
      </div>

      <div class="scratchpad-output-actions">
        <div
          v-if="objectType"
          class="object-type-badge"
          :title="sprintf(__('Object Type: %s'), objectType)"
        >
          <span class="codicon codicon-symbol-class" />
          <span class="object-type-name">{{ objectType }}</span>
        </div>

        <button
          type="button"
          class="output-action-btn"
          :title="__('Copy to clipboard')"
          @click="copyOutput"
        >
          <span class="codicon codicon-copy" />
        </button>

        <button
          type="button"
          class="output-action-btn"
          :title="__('Clear output')"
          @click="emit('clear-output')"
        >
          <span class="codicon codicon-clear-all" />
        </button>

        <button
          type="button"
          class="output-action-btn"
          :title="__('Hide output panel')"
          @click="emit('close')"
        >
          <span class="codicon codicon-chevron-down" />
        </button>
      </div>
    </div>

    <!-- Body -->
    <div class="scratchpad-output-body">
      <!-- Loading Spinner -->
      <div
        v-if="loading"
        class="scratchpad-output-loading"
      >
        <div class="metro-spinner" />
        <span>{{ __('Evaluating expression...') }}</span>
      </div>

      <!-- Result Content -->
      <div
        v-else-if="result"
        class="scratchpad-output-content"
      >
        <!-- Error State -->
        <div
          v-if="isError && activeTab !== 'output' && typeof activeTab !== 'number'"
          class="scratchpad-tab-content raw-content error-content"
        >
          <ul class="entries scratchpad-entries">
            <li class="entry out error mode-evaluate">
              <pre class="no-highlight"><SafeFormattedText :text="String(rawResult)" /></pre>
            </li>
          </ul>
        </div>

        <!-- Raw Tab -->
        <div
          v-else-if="activeTab === 'raw'"
          class="scratchpad-tab-content raw-content"
        >
          <vue-json-pretty
            v-if="isNonScalar(rawResult)"
            :data="rawResult"
            :show-length="true"
            :show-icon="true"
            :deep="2"
          />
          <ul
            v-else
            class="entries scratchpad-entries"
          >
            <li class="entry out mode-evaluate">
              <!-- eslint-disable vue/no-v-html -->
              <pre
                class="hljs"
                v-html="highlightedScalar"
              />
              <!-- eslint-enable vue/no-v-html -->
            </li>
          </ul>
        </div>

        <!-- Visualization Tabs -->
        <div
          v-else-if="typeof activeTab === 'number' && visualizations[activeTab]"
          class="scratchpad-tab-content visualization-content"
        >
          <component
            :is="getComponent(visualizations[activeTab].type)"
            v-if="getComponent(visualizations[activeTab].type)"
            :data="visualizations[activeTab]"
          />
          <div
            v-else
            class="unknown-viz"
          >
            {{ sprintf(__('Unknown visualization type: %s'), visualizations[activeTab].type) }}
          </div>
        </div>

        <!-- Output / Console Messages Tab -->
        <div
          v-else-if="activeTab === 'output'"
          class="scratchpad-tab-content output-messages-content"
        >
          <ul
            v-if="messages.length > 0"
            class="entries scratchpad-entries"
          >
            <li
              v-for="(msg, mIdx) in messages"
              :key="mIdx"
              class="entry out"
              :class="[msg.type === 'log' ? 'info' : (msg.type || 'info'), 'mode-evaluate']"
            >
              <pre class="no-highlight"><SafeFormattedText :text="msg.text" /></pre>
            </li>
          </ul>
          <div
            v-else
            class="scratchpad-empty-state"
          >
            <span class="codicon codicon-info" />
            <span>{{ __('No deferred console messages available.') }}</span>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div
        v-else
        class="scratchpad-empty-state"
      >
        <span class="codicon codicon-play" />
        <span>{{ __('Run an expression (Ctrl+Enter or Play) to view results here.') }}</span>
      </div>
    </div>
  </div>
</template>
