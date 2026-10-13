<script setup>
import { ref, computed, watch } from 'vue';
import DataTree from '../../ui/DataTree.vue';
import { visualizationHandlers } from './registry';
import SafeFormattedText from '../SafeFormattedText.vue';
import { __, sprintf } from '../../../lib/helpers.js';
import hljs from 'highlight.js/lib/core';
import elscriptLang from '../../../lib/highlight/elscript.js';
import phpLang from 'highlight.js/lib/languages/php';
import jsonLang from 'highlight.js/lib/languages/json';
import DOMPurify from 'dompurify';
import { detectDataType } from '../../../lib/data-detector.js';

hljs.registerLanguage('elscript', elscriptLang);
hljs.registerLanguage('expressionlab', elscriptLang);
hljs.registerLanguage('php', phpLang);
hljs.registerLanguage('json', jsonLang);

const props = defineProps({
  item: {
    type: [Object, String, Number, Array],
    required: true,
  },
  // Optional type hint (e.g. 'error', 'info') to disable highlighting or styling
  resultType: {
    type: String,
    default: null,
  },
  modelValue: {
    type: [String, Number],
    default: undefined,
  },
});

const emit = defineEmits(['update:modelValue']);

const localActiveTab = ref('preview');

const activeTab = computed({
  get: () => props.modelValue !== undefined ? props.modelValue : localActiveTab.value,
  set: (val) => {
    localActiveTab.value = val;
    emit('update:modelValue', val);
  }
});

const isNonScalar = (val) => {
  return typeof val === 'object' && val !== null;
};

// Extract standard structure: { result: ..., visualizations: [] }
// Or just treat output as result if it doesn't match that structure.
const parsed = computed(() => {
  const item = props.item;
  
  if (
    item &&
    typeof item === 'object' &&
    Object.prototype.hasOwnProperty.call(item, 'result') &&
    Array.isArray(item.visualizations)
  ) {
    return {
      result: item.result,
      visualizations: item.visualizations,
      hasViz: item.visualizations.length > 0,
    };
  }

  if (
    item &&
    typeof item === 'object' &&
    Object.prototype.hasOwnProperty.call(item, 'output') &&
    Array.isArray(item.visualizations)
  ) {
    return {
      result: item.output,
      visualizations: item.visualizations,
      hasViz: item.visualizations.length > 0,
    };
  }
  
  if (
    item &&
    typeof item === 'object' && 
    item.output && 
    typeof item.output === 'object' &&
    Object.prototype.hasOwnProperty.call(item.output, 'result') &&
    Array.isArray(item.output.visualizations)
  ) {
      return {
        result: item.output.result,
        visualizations: item.output.visualizations,
        hasViz: item.output.visualizations.length > 0
      };
  }

  // Fallback: Treat item as the result itself
  return {
    result: item,
    visualizations: [],
    hasViz: false,
  };
});

const detectedData = computed(() => {
  return detectDataType(parsed.value.result);
});

const canPreview = computed(() => {
  if (isNonScalar(parsed.value.result)) {
    return true;
  }
  return detectedData.value.format === 'json' || detectedData.value.format === 'php-serialized';
});

const previewPayload = computed(() => {
  if (isNonScalar(parsed.value.result)) {
    return parsed.value.result;
  }
  if (canPreview.value && detectedData.value.data !== null) {
    return detectedData.value.data;
  }
  return parsed.value.result;
});

const rawString = computed(() => {
  const data = parsed.value.result;
  if (data === null) return 'null';
  if (data === undefined) return 'undefined';
  if (typeof data === 'object') {
    return JSON.stringify(data, null, 2);
  }
  if (detectedData.value.unwrappedString) {
    return detectedData.value.unwrappedString;
  }
  return String(data);
});

const escapeHtml = (str) => {
  const div = document.createElement('div');
  div.appendChild(document.createTextNode(str));
  return div.innerHTML;
};

const highlightedRaw = computed(() => {
  const text = rawString.value;
  if (text.length > 200000) {
    return DOMPurify.sanitize(escapeHtml(text));
  }

  const lang = (detectedData.value.suggestedLang && detectedData.value.suggestedLang !== 'auto')
    ? detectedData.value.suggestedLang
    : 'elscript';

  try {
    if (lang && hljs.getLanguage(lang)) {
      const { value } = hljs.highlight(text, { language: lang });
      return DOMPurify.sanitize(value);
    }
    const { value } = hljs.highlight(text, { language: 'elscript' });
    return DOMPurify.sanitize(value);
  } catch {
    return DOMPurify.sanitize(escapeHtml(text));
  }
});

watch(() => parsed.value.hasViz, (hasViz) => {
  if (hasViz) {
    activeTab.value = 0;
  }
}, { immediate: true });

const getComponent = (type) => {
  return visualizationHandlers.value[type] || null;
};
</script>

<template>
  <div class="visualization-renderer">
    <!-- Tabs if visualizations exist -->
    <template v-if="parsed.hasViz">
      <ul class="visualization-tabs">
        <li
          :class="{ active: activeTab === 'raw' }"
          @click="activeTab = 'raw'"
        >
          {{ __('Raw') }}
        </li>
        <li
          v-if="canPreview"
          :class="{ active: activeTab === 'preview' || activeTab === 'json' }"
          @click="activeTab = 'preview'"
        >
          {{ __('Preview') }}
        </li>
        <li
          v-for="(viz, idx) in parsed.visualizations"
          :key="idx"
          :class="{ active: activeTab === idx }"
          @click="activeTab = idx"
        >
          {{ __(viz.title || 'Table') }}
        </li>
      </ul>

      <div class="visualization-content">
        <!-- Raw Result Tab -->
        <div v-if="activeTab === 'raw'">
          <!-- eslint-disable vue/no-v-html -->
          <pre
            class="hljs"
            v-html="highlightedRaw"
          />
          <!-- eslint-enable vue/no-v-html -->
        </div>

        <!-- Preview Tab -->
        <div v-else-if="activeTab === 'preview' || activeTab === 'json'">
          <DataTree
            :data="previewPayload"
            :show-controls="true"
          />
        </div>

        <!-- Visualization Tabs -->
        <template v-else>
          <div
            v-for="(viz, idx) in parsed.visualizations"
            v-show="activeTab === idx"
            :key="idx"
          >
            <component
              :is="getComponent(viz.type)"
              v-if="getComponent(viz.type)"
              :data="viz"
            />
            <div v-else>
              {{ sprintf(__('Unknown visualization type: %s'), viz.type) }}
            </div>
          </div>
        </template>
      </div>
    </template>

    <!-- No Visualizations -->
    <template v-else>
      <DataTree
        v-if="canPreview"
        :data="previewPayload"
        :show-controls="true"
      />
      <pre
        v-else
        :class="{ 'no-highlight': props.resultType }"
      ><SafeFormattedText
        v-if="props.resultType"
        :text="rawString"
      /><template v-else>{{ rawString }}</template></pre>
    </template>
  </div>
</template>
