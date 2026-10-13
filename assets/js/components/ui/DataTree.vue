<script setup>
import { ref, computed } from 'vue';
import PhpSerializedTreeNode from './PhpSerializedTreeNode.vue';
import { convertToTreeAst } from '../../lib/data-tree-adapter.js';
import { searchTreeAst } from '../../lib/data-tree-filter.js';
import { __, sprintf } from '../../lib/helpers.js';

const props = defineProps({
  ast: {
    type: Object,
    default: null,
  },
  data: {
    type: [Object, Array, String, Number, Boolean],
    default: undefined,
  },
  showControls: {
    type: Boolean,
    default: false,
  },
  showSearch: {
    type: Boolean,
    default: false,
  },
  defaultExpandedDepth: {
    type: Number,
    default: 1,
  },
});

const treeKey = ref(0);
const currentExpandedDepth = ref(props.defaultExpandedDepth);
const searchQuery = ref('');
const searchMode = ref('text'); // 'text' | 'path'

const rootAst = computed(() => {
  if (props.ast) {
    return props.ast;
  }
  return convertToTreeAst(props.data);
});

const isSearchActive = computed(() => Boolean(searchQuery.value.trim()));

const searchResult = computed(() => {
  return searchTreeAst(rootAst.value, searchQuery.value, searchMode.value);
});

const displayedAst = computed(() => searchResult.value.filteredAst);
const matchCount = computed(() => searchResult.value.matchCount);

const activeExpandedDepth = computed(() => {
  if (isSearchActive.value) {
    return 999;
  }
  return currentExpandedDepth.value;
});

const clearSearch = () => {
  searchQuery.value = '';
};

// Clear a non-empty query without letting Escape bubble to window-level handlers
// (e.g. the Data Viewer modal would otherwise close on the same keystroke).
const onSearchEscape = (event) => {
  if (searchQuery.value) {
    event.stopPropagation();
    clearSearch();
  }
};

const expandAll = () => {
  currentExpandedDepth.value = 999;
  treeKey.value++;
};

const collapseAll = () => {
  currentExpandedDepth.value = 0;
  treeKey.value++;
};

defineExpose({
  expandAll,
  collapseAll,
  clearSearch,
  searchQuery,
  searchMode,
});
</script>

<template>
  <div
    class="data-tree-container php-serialized-tree-container"
    :class="{ inline: props.showControls }"
  >
    <div
      class="php-tree-content"
      :class="{ 'is-empty': !displayedAst && isSearchActive }"
    >
      <PhpSerializedTreeNode
        v-if="displayedAst"
        :key="treeKey + (isSearchActive ? `_s_${searchQuery}_${searchMode}` : '')"
        :node="displayedAst"
        :current-depth="0"
        :default-expanded-depth="activeExpandedDepth"
        :show-controls="props.showControls"
        @expand-all="expandAll"
        @collapse-all="collapseAll"
      />
      <div
        v-else-if="isSearchActive"
        class="data-tree-no-matches"
      >
        <span class="codicon codicon-search" />
        <span>{{ searchMode === 'path' ? __('Path not found') : __('No matches found') }}</span>
      </div>
    </div>

    <!-- Bottom Search Bar -->
    <div
      v-if="props.showSearch"
      class="data-tree-search-bar"
    >
      <div class="search-input-wrapper">
        <span class="codicon codicon-search search-icon" />
        <input
          v-model="searchQuery"
          type="text"
          class="data-tree-search-input"
          :placeholder="searchMode === 'path' ? __('Search path (e.g. response.args[0])...') : __('Search in data...')"
          @keydown.esc="onSearchEscape"
        >

        <!-- Match status badge (inside input wrapper on the right) -->
        <div
          v-if="isSearchActive"
          class="search-match-badge"
          :class="{ 'has-matches': matchCount > 0, 'no-matches': matchCount === 0 }"
        >
          <template v-if="searchMode === 'path'">
            {{ matchCount > 0 ? __('Path found') : __('Path not found') }}
          </template>
          <template v-else>
            {{ matchCount === 1 ? __('1 match') : sprintf(__('%d matches'), matchCount) }}
          </template>
        </div>

        <button
          v-if="searchQuery"
          type="button"
          class="search-clear-btn"
          :title="__('Clear search')"
          @click="clearSearch"
        >
          <span class="codicon codicon-close" />
        </button>
      </div>

      <!-- Mode Selector -->
      <div class="search-mode-selector">
        <button
          type="button"
          class="toolbar-button mode-toggle-btn btn-mode-text"
          :class="{ active: searchMode === 'text' }"
          :title="__('Free text search')"
          @click="searchMode = 'text'"
        >
          <span class="codicon codicon-whole-word" />
        </button>
        <button
          type="button"
          class="toolbar-button mode-toggle-btn btn-mode-path"
          :class="{ active: searchMode === 'path' }"
          :title="__('Path expression (Symfony / Dot-syntax)')"
          @click="searchMode = 'path'"
        >
          <span class="codicon codicon-symbol-property" />
        </button>
      </div>
    </div>
  </div>
</template>
