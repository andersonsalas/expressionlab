<script setup>
import { ref } from 'vue';
import { __ } from '../../lib/helpers.js';

const props = defineProps({
  searchQuery: {
    type: String,
    default: '',
  },
  replaceQuery: {
    type: String,
    default: '',
  },
  isReplaceOpen: {
    type: Boolean,
    default: false,
  },
  matchCase: {
    type: Boolean,
    default: false,
  },
  wholeWord: {
    type: Boolean,
    default: false,
  },
  useRegex: {
    type: Boolean,
    default: false,
  },
  regexError: {
    type: String,
    default: '',
  },
  totalMatches: {
    type: Number,
    default: 0,
  },
  currentMatchIndex: {
    type: Number,
    default: 0,
  },
});

const emit = defineEmits([
  'update:searchQuery',
  'update:replaceQuery',
  'toggle-replace',
  'toggle-match-case',
  'toggle-whole-word',
  'toggle-use-regex',
  'next',
  'prev',
  'replace',
  'replace-all',
  'close',
]);

const searchInputRef = ref(null);
const replaceInputRef = ref(null);

const focusSearchInput = () => {
  if (searchInputRef.value) {
    searchInputRef.value.focus();
    searchInputRef.value.select();
  }
};

const focusReplaceInput = () => {
  if (replaceInputRef.value) {
    replaceInputRef.value.focus();
    replaceInputRef.value.select();
  }
};

defineExpose({
  focusSearchInput,
  focusReplaceInput,
});
</script>

<template>
  <div
    class="console-find-widget notebook-find-widget"
    @keydown.esc.stop="emit('close')"
    @keydown.alt.c.stop.prevent="emit('toggle-match-case')"
    @keydown.alt.w.stop.prevent="emit('toggle-whole-word')"
    @keydown.alt.r.stop.prevent="emit('toggle-use-regex')"
  >
    <!-- Main Search Row -->
    <div class="find-widget-main-row">
      <button
        type="button"
        class="widget-icon-btn toggle-replace-btn"
        :class="{ expanded: isReplaceOpen }"
        :title="isReplaceOpen ? __('Hide Replace') : __('Show Replace')"
        @click="emit('toggle-replace')"
      >
        <span
          class="codicon"
          :class="isReplaceOpen ? 'codicon-chevron-down' : 'codicon-chevron-right'"
        />
      </button>

      <div class="widget-input-wrapper">
        <input
          ref="searchInputRef"
          type="text"
          class="widget-input search-input"
          :class="{ 'is-invalid-regex': Boolean(regexError) }"
          :placeholder="__('Find in scratchpad...')"
          :title="regexError || ''"
          :value="searchQuery"
          @input="emit('update:searchQuery', $event.target.value)"
          @keydown.enter.exact.prevent="emit('next')"
          @keydown.shift.enter.prevent="emit('prev')"
        >
        <span
          v-if="searchQuery"
          class="matches-count"
          :class="{ 'no-matches': totalMatches === 0 || Boolean(regexError) }"
        >
          {{ regexError ? __('Invalid regex') : (totalMatches > 0 ? `${currentMatchIndex + 1} of ${totalMatches}` : __('No results')) }}
        </span>
      </div>

      <div class="widget-actions">
        <button
          type="button"
          class="widget-icon-btn"
          :class="{ active: matchCase }"
          :title="__('Match Case (Alt+C)')"
          @click="emit('toggle-match-case')"
        >
          <span class="toggle-text">Aa</span>
        </button>
        <button
          type="button"
          class="widget-icon-btn"
          :class="{ active: wholeWord }"
          :title="__('Match Whole Word (Alt+W)')"
          @click="emit('toggle-whole-word')"
        >
          <span class="codicon codicon-whole-word" />
        </button>
        <button
          type="button"
          class="widget-icon-btn"
          :class="{ active: useRegex }"
          :title="__('Use Regular Expression (Alt+R)')"
          @click="emit('toggle-use-regex')"
        >
          <span class="codicon codicon-regex" />
        </button>
        <button
          type="button"
          class="widget-icon-btn"
          :disabled="totalMatches === 0 || Boolean(regexError)"
          :title="__('Previous Match (Shift+Enter)')"
          @click="emit('prev')"
        >
          <span class="codicon codicon-arrow-up" />
        </button>
        <button
          type="button"
          class="widget-icon-btn"
          :disabled="totalMatches === 0 || Boolean(regexError)"
          :title="__('Next Match (Enter)')"
          @click="emit('next')"
        >
          <span class="codicon codicon-arrow-down" />
        </button>
        <button
          type="button"
          class="widget-icon-btn close-btn"
          :title="__('Close (Esc)')"
          @click="emit('close')"
        >
          <span class="codicon codicon-close" />
        </button>
      </div>
    </div>

    <!-- Replace Row (Collapsible) -->
    <div
      v-show="isReplaceOpen"
      class="find-widget-replace-row"
    >
      <div class="widget-spacer" />
      <div class="widget-input-wrapper">
        <input
          ref="replaceInputRef"
          type="text"
          class="widget-input replace-input"
          :placeholder="__('Replace with...')"
          :value="replaceQuery"
          @input="emit('update:replaceQuery', $event.target.value)"
          @keydown.enter.exact.prevent="emit('replace')"
          @keydown.shift.enter.prevent="emit('replace-all')"
        >
      </div>
      <div class="widget-replace-actions">
        <button
          type="button"
          class="widget-icon-btn"
          :disabled="totalMatches === 0 || Boolean(regexError)"
          :title="__('Replace (Enter)')"
          @click="emit('replace')"
        >
          <span class="codicon codicon-replace" />
        </button>
        <button
          type="button"
          class="widget-icon-btn"
          :disabled="totalMatches === 0 || Boolean(regexError)"
          :title="__('Replace All (Shift+Enter)')"
          @click="emit('replace-all')"
        >
          <span class="codicon codicon-replace-all" />
        </button>
      </div>
    </div>
  </div>
</template>
