<script setup>
import { nextTick, watch, onMounted, reactive } from 'vue';
import hljs from 'highlight.js/lib/core';
import elscriptLang from '../../lib/highlight/elscript.js';
import javascriptLang from 'highlight.js/lib/languages/javascript';
import phpLang from 'highlight.js/lib/languages/php';
import cssLang from 'highlight.js/lib/languages/css';
import xmlLang from 'highlight.js/lib/languages/xml';
import sqlLang from 'highlight.js/lib/languages/sql';
import DOMPurify from 'dompurify';
import VisualizationRenderer from './visualizations/VisualizationRenderer.vue';
import SafeFormattedText from './SafeFormattedText.vue';
import { handleClipboardCopy } from '../../lib/api/client.js';
import { __ } from '../../lib/helpers.js';

hljs.registerLanguage('elscript', elscriptLang);
hljs.registerLanguage('expressionlab', elscriptLang);
hljs.registerLanguage('javascript', javascriptLang);
hljs.registerLanguage('php', phpLang);
hljs.registerLanguage('css', cssLang);
hljs.registerLanguage('html', xmlLang);
hljs.registerLanguage('sql', sqlLang);

const emit = defineEmits(['edit-entry', 'run-code']);
const visualizationStates = reactive({});

const getVisualizationType = (item, idx) => {
  const activeIdx = visualizationStates[idx];
  if (activeIdx === undefined || activeIdx === 'json') return null;

  const vizs = item.visualizations || (item.output && item.output.visualizations) || [];
  if (vizs[activeIdx]) {
      return vizs[activeIdx].type;
  }
  return null;
};

const escapeHtml = (str) => {
  const div = document.createElement('div');
  div.appendChild(document.createTextNode(str));
  return div.innerHTML;
};

const generateHTMLTable = (data) => {
  if (!Array.isArray(data) || !data.length) return '';
  const headers = Object.keys(data[0]);
  let html = '<table border="1" style="border-collapse: collapse;"><thead><tr>';
  headers.forEach(h => html += `<th style="padding: 5px; border: 1px solid #ccc; background: #f0f0f0;">${escapeHtml(h)}</th>`);
  html += '</tr></thead><tbody>';
  
  data.forEach(row => {
    html += '<tr>';
    headers.forEach(h => html += `<td style="padding: 5px; border: 1px solid #ccc;">${escapeHtml(String(row[h] ?? ''))}</td>`);
    html += '</tr>';
  });
  html += '</tbody></table>';
  return DOMPurify.sanitize(html);
};


const props = defineProps({
  entries: {
    type: Array,
    default: () => [],
  },
});

const applySyntaxHighlight = () => {
  nextTick(() => {
    const buffers = document.querySelectorAll('.entries .entry pre');
    buffers.forEach((pre) => {
        // Skip if already highlighted
        if (pre.classList.contains('hljs') || pre.classList.contains('no-highlight')) return;
        
        const text = pre.textContent || '';
        const { value } = hljs.highlight(text, { language: 'elscript' });
        pre.innerHTML = DOMPurify.sanitize(value);
        pre.classList.add('hljs');
    });
  });
};

watch(() => props.entries, applySyntaxHighlight, { deep: true });
watch(visualizationStates, applySyntaxHighlight, { deep: true });
watch(() => props.entries.length, (newLen) => {
  if (newLen === 0) {
    Object.keys(visualizationStates).forEach(key => delete visualizationStates[key]);
  }
});
onMounted(applySyntaxHighlight);

const isNonScalar = (val) => {
  return typeof val === 'object' && val !== null;
};

const copyToClipboard = (item, visualizationType = null, idx = null, direction = 'out') => {
  let text = '';
  let html = null;

  // Let's determine if we should copy a visualization's data
  let targetData = 'out' === direction ? item.output : item.input;
  
  if (visualizationType === 'table') {
    const activeIdx = idx !== null ? visualizationStates[idx] : null;
    const vizs = item.visualizations || (item.output && item.output.visualizations) || [];
    
    if (activeIdx !== undefined && activeIdx !== 'json' && vizs[activeIdx]) {
      targetData = vizs[activeIdx].data;
    }
  } else {
    if ( 'out' === direction ) {
      targetData = targetData && targetData.output !== undefined ? targetData.output : targetData;
    } else {
      targetData = targetData && targetData.input !== undefined ? targetData.input : targetData;
    }
  }

  const outputArray = Array.isArray(targetData) ? targetData : (targetData && typeof targetData === 'object' ? Object.values(targetData) : []);

  if (visualizationType === 'table' && outputArray.length > 0) {
      // Table visualization
      const headers = Object.keys(outputArray[0]);
      // TSV for text fallback
      text = headers.join('\t') + '\n';
      text += outputArray.map(r => headers.map(h => r[h]).join('\t')).join('\n');
      
      // HTML for rich paste
      html = generateHTMLTable(outputArray);
  } else {
     // Default copy
     text = isNonScalar(targetData) ? JSON.stringify(targetData, null, 2) : targetData;
  }

  handleClipboardCopy(text, html);
};
</script>

<template>
  <template
    v-for="(item, idx) in entries"
    :key="idx"
  >
    <li 
      v-if="null !== item.input"
      class="entry in"
      :class="[{ 'loading' : item.pending }, item.mode ? 'mode-' + item.mode : '']"
    >
      <div class="entry-actions">
        <div
          class="action copy-action"
          :title="__('Copy to clipboard')"
          @click="copyToClipboard(item, null, idx, 'in')"
        >
          <div class="codicon codicon-copy" />
        </div>
        <div 
          v-if="!item.pending"
          class="action edit-action"
          :title="__('Edit input')"
          @click="$emit('edit-entry', idx, 'input')"
        >
          <div class="codicon codicon-edit" />
        </div>
      </div>
      <pre>{{ item.input }}</pre>
    </li>

    <!-- Render Messages first -->
    <template v-if="item.messages && item.messages.length">
      <li
        v-for="(msg, msgIdx) in item.messages"
        :key="'msg-' + idx + '-' + msgIdx"
        class="entry out"
        :class="[msg.type, item.mode ? 'mode-' + item.mode : '']"
      >
        <pre class="no-highlight"><SafeFormattedText :text="msg.text" /></pre>
      </li>
    </template>

    <li
      v-if="!item.pending && (null !== item.input || (item.output !== null && item.output !== undefined) || (item.visualizations && item.visualizations.length > 0))"
      class="entry out"
      :class="[item.type, item.mode ? 'mode-' + item.mode : '']"
    >
      <div class="entry-actions">
        <div
          class="action copy-action"
          :title="__('Copy to clipboard')"
          @click="copyToClipboard(item, getVisualizationType(item, idx), idx, 'out')"
        >
          <div class="codicon codicon-copy" />
        </div>
        <div
          v-if="null !== item.input"
          class="action edit-action"
          :title="__('Edit input')"
          @click="$emit('edit-entry', idx, 'output')"
        >
          <div class="codicon codicon-edit" />
        </div>  
      </div>
      <div 
        v-if="item.mode === 'evaluate'"
        class="out-container mode-evaluate-container"
      >
        <div
          v-if="null !==item.object_type"
          class="object-type-label-container"
        >
          <div class="object-type-label">
            <div class="codicon codicon-symbol-class" />
            <span>{{ item.object_type }}</span>
          </div>
        </div>
        <VisualizationRenderer
          v-model="visualizationStates[idx]"
          :item="item"
          :result-type="item.type"
        />
      </div>      
    </li>
  </template>
</template>
