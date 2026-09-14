<script setup>
import { ref, computed, watch } from 'vue';
import VueJsonPretty from 'vue-json-pretty';
import 'vue-json-pretty/lib/styles.css';
import { visualizationHandlers } from './registry';
import SafeFormattedText from '../SafeFormattedText.vue';
import { __, sprintf } from '../../../lib/helpers.js';

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

const localActiveTab = ref('json');

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
          :class="{ active: activeTab === 'json' }"
          @click="activeTab = 'json'"
        >
          {{ __('Raw') }}
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
        <!-- JSON/Raw Result Tab -->
        <div v-if="activeTab === 'json'">
          <vue-json-pretty
            v-if="isNonScalar(parsed.result)"
            :data="parsed.result"
            :show-length="true"
            :show-icon="true"
            :deep="1"
          />
          <pre
            v-else
            :class="{ 'no-highlight': props.resultType }"
          ><SafeFormattedText
            v-if="props.resultType"
            :text="parsed.result === null ? 'null' : (parsed.result === undefined ? 'undefined' : parsed.result)"
          /><template v-else>{{ parsed.result === null ? 'null' : (parsed.result === undefined ? 'undefined' : parsed.result) }}</template></pre>
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

    <!-- No Visualizations - Just render result -->
    <template v-else>
      <vue-json-pretty
        v-if="isNonScalar(parsed.result)"
        :data="parsed.result"
        :show-length="true"
        :show-icon="true"
        :deep="1"
      />
      <pre
        v-else
        :class="{ 'no-highlight': props.resultType }"
      ><SafeFormattedText
        v-if="props.resultType"
        :text="parsed.result === null ? 'null' : (parsed.result === undefined ? 'undefined' : parsed.result)"
      /><template v-else>{{ parsed.result === null ? 'null' : (parsed.result === undefined ? 'undefined' : parsed.result) }}</template></pre>
    </template>
  </div>
</template>
