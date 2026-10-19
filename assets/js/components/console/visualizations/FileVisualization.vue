<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { EditorState, Compartment, StateEffect, StateField, RangeSetBuilder } from '@codemirror/state';
import { EditorView, lineNumbers, highlightActiveLine, highlightActiveLineGutter, Decoration } from '@codemirror/view';
import { bracketMatching, syntaxHighlighting, defaultHighlightStyle } from '@codemirror/language';
import { search, SearchQuery, setSearchQuery, findNext, findPrevious } from '@codemirror/search';
import { vsCodeLight } from '@fsegurai/codemirror-theme-bundle';
import { php } from '@codemirror/lang-php';
import { javascript } from '@codemirror/lang-javascript';
import { json } from '@codemirror/lang-json';
import { html } from '@codemirror/lang-html';
import { css } from '@codemirror/lang-css';
import { sql } from '@codemirror/lang-sql';
import { markdown } from '@codemirror/lang-markdown';
import { elscript } from '../../../lib/codemirror/elscript.js';
import { logLanguage } from '../../../lib/codemirror/log.js';
import { __, sprintf } from '../../../lib/helpers.js';

const setSearchHighlightEffect = StateEffect.define();

const matchMark = Decoration.mark({ class: 'cm-searchMatch' });
const activeMatchMark = Decoration.mark({ class: 'cm-searchMatch cm-searchMatch-selected' });

const searchHighlightField = StateField.define({
  create() {
    return { query: '', activeIndex: 0, decorations: Decoration.none };
  },
  update(value, tr) {
    for (const effect of tr.effects) {
      if (effect.is(setSearchHighlightEffect)) {
        const { query, activeIndex } = effect.value;
        const trimmed = (query || '').trim();
        if (!trimmed) {
          return { query: '', activeIndex: 0, decorations: Decoration.none };
        }
        const docText = tr.state.doc.toString();
        const lowerDoc = docText.toLowerCase();
        const lowerQ = trimmed.toLowerCase();
        const builder = new RangeSetBuilder();
        let pos = 0;
        let matchIdx = 1;
        while ((pos = lowerDoc.indexOf(lowerQ, pos)) !== -1) {
          const to = pos + lowerQ.length;
          builder.add(pos, to, matchIdx === activeIndex ? activeMatchMark : matchMark);
          matchIdx++;
          pos = to;
        }
        return { query: trimmed, activeIndex, decorations: builder.finish() };
      }
    }
    if (tr.docChanged && value.query) {
      const docText = tr.state.doc.toString();
      const lowerDoc = docText.toLowerCase();
      const lowerQ = value.query.toLowerCase();
      const builder = new RangeSetBuilder();
      let pos = 0;
      let matchIdx = 1;
      while ((pos = lowerDoc.indexOf(lowerQ, pos)) !== -1) {
        const to = pos + lowerQ.length;
        builder.add(pos, to, matchIdx === value.activeIndex ? activeMatchMark : matchMark);
        matchIdx++;
        pos = to;
      }
      return { ...value, decorations: builder.finish() };
    }
    return value;
  },
  provide: (f) => EditorView.decorations.from(f, (v) => v.decorations),
});

const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
  wordWrap: {
    type: Boolean,
    default: undefined,
  },
  hideWordWrap: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['rendered']);

const editorContainer = ref(null);
const searchText = ref('');
const isWordWrap = ref(props.wordWrap !== undefined ? props.wordWrap : false);
const currentMatchIndex = ref(0);
const wrapCompartment = new Compartment();

watch(
  () => props.wordWrap,
  (val) => {
    if (val !== undefined && val !== isWordWrap.value) {
      isWordWrap.value = val;
      if (editorView) {
        editorView.dispatch({
          effects: wrapCompartment.reconfigure(val ? EditorView.lineWrapping : []),
        });
      }
    }
  }
);

let editorView = null;

const resolveLanguageExtension = (ext, mime) => {
  const cleanExt = String(ext || '').toLowerCase().replace(/^\./, '');
  const cleanMime = String(mime || '').toLowerCase();

  if (['php', 'phtml', 'php4', 'php5', 'php7', 'php8', 'phps'].includes(cleanExt) || cleanMime.includes('php')) {
    return php();
  }
  if (['js', 'mjs', 'cjs', 'jsx'].includes(cleanExt) || cleanMime.includes('javascript')) {
    return javascript({ jsx: true });
  }
  if (['ts', 'tsx'].includes(cleanExt) || cleanMime.includes('typescript')) {
    return javascript({ typescript: true, jsx: true });
  }
  if (['json', 'jsonld', 'map'].includes(cleanExt) || cleanMime.includes('json')) {
    return json();
  }
  if (
    ['html', 'htm', 'vue', 'svelte', 'xml', 'svg', 'rss', 'atom', 'xhtml'].includes(cleanExt) ||
    cleanMime.includes('html') || cleanMime.includes('xml') || cleanMime.includes('svg')
  ) {
    return html();
  }
  if (['css', 'scss', 'less', 'sass'].includes(cleanExt) || cleanMime.includes('css')) {
    return css();
  }
  if (['sql'].includes(cleanExt) || cleanMime.includes('sql')) {
    return sql();
  }
  if (['md', 'markdown', 'mdown', 'mkdn'].includes(cleanExt) || cleanMime.includes('markdown')) {
    return markdown();
  }
  if (['log', 'txt'].includes(cleanExt) || cleanMime.includes('log')) {
    return logLanguage();
  }
  if (['env', 'ini', 'conf', 'htaccess', 'properties'].includes(cleanExt)) {
    return logLanguage();
  }
  if (['elscript', 'expressionlab'].includes(cleanExt)) {
    return elscript();
  }
  return null;
};

const lineCount = computed(() => {
  if (props.data?.line_count) {
    return props.data.line_count;
  }
  const content = props.data?.content || '';
  if (!content) return 0;
  return content.split('\n').length;
});

const formattedSize = computed(() => {
  return props.data?.size_human || (props.data?.bytes ? `${props.data.bytes} B` : null);
});

// Calculate character offsets for all match occurrences
const matchOffsets = computed(() => {
  const query = searchText.value.trim();
  if (!query) return [];
  const content = String(props.data?.content || '');
  const indices = [];
  const lowerContent = content.toLowerCase();
  const lowerQuery = query.toLowerCase();
  let pos = 0;
  while ((pos = lowerContent.indexOf(lowerQuery, pos)) !== -1) {
    indices.push(pos);
    pos += lowerQuery.length;
  }
  return indices;
});

const matchCount = computed(() => matchOffsets.value.length);

const matchStatusText = computed(() => {
  if (!searchText.value.trim()) return '';
  if (matchCount.value === 0) return __('No results');
  if (currentMatchIndex.value > 0) {
    return sprintf(__('%d of %d'), currentMatchIndex.value, matchCount.value);
  }
  return sprintf(__('%d matches'), matchCount.value);
});

const updateCurrentMatchIndex = () => {
  if (!editorView || matchOffsets.value.length === 0) {
    currentMatchIndex.value = 0;
    return;
  }
  const selFrom = editorView.state.selection.main.from;
  const idx = matchOffsets.value.findIndex((pos) => pos === selFrom);
  if (idx !== -1) {
    currentMatchIndex.value = idx + 1;
  } else {
    let found = 1;
    for (let i = 0; i < matchOffsets.value.length; i++) {
      if (matchOffsets.value[i] <= selFrom) {
        found = i + 1;
      } else {
        break;
      }
    }
    currentMatchIndex.value = found;
  }
};

const dispatchSearchHighlight = () => {
  if (!editorView) return;
  editorView.dispatch({
    effects: setSearchHighlightEffect.of({
      query: searchText.value,
      activeIndex: currentMatchIndex.value,
    }),
  });
};

const updateSearchQuery = (autoNavigate = true) => {
  if (!editorView) return;
  const query = new SearchQuery({
    search: searchText.value,
    caseSensitive: false,
  });
  editorView.dispatch({
    effects: setSearchQuery.of(query),
  });

  if (autoNavigate && matchOffsets.value.length > 0) {
    findNext(editorView);
    updateCurrentMatchIndex();
  } else if (matchOffsets.value.length === 0) {
    currentMatchIndex.value = 0;
  }
  dispatchSearchHighlight();
};

const onSearchInput = () => {
  updateSearchQuery(true);
};

const onSearchEnter = (event) => {
  if (event.shiftKey) {
    goToPreviousMatch();
  } else {
    goToNextMatch();
  }
};

const goToNextMatch = () => {
  if (!editorView || matchCount.value === 0) return;
  findNext(editorView);
  updateCurrentMatchIndex();
  dispatchSearchHighlight();
};

const goToPreviousMatch = () => {
  if (!editorView || matchCount.value === 0) return;
  findPrevious(editorView);
  updateCurrentMatchIndex();
  dispatchSearchHighlight();
};

const clearSearch = () => {
  searchText.value = '';
  currentMatchIndex.value = 0;
  if (editorView) {
    editorView.dispatch({
      effects: [
        setSearchQuery.of(new SearchQuery({ search: '' })),
        setSearchHighlightEffect.of({ query: '', activeIndex: 0 }),
      ],
    });
  }
};

const toggleWordWrap = () => {
  isWordWrap.value = !isWordWrap.value;
  if (editorView) {
    editorView.dispatch({
      effects: wrapCompartment.reconfigure(isWordWrap.value ? EditorView.lineWrapping : []),
    });
  }
};

const initEditor = () => {
  if (!editorContainer.value) return;

  if (editorView) {
    editorView.destroy();
    editorView = null;
  }

  const langExt = resolveLanguageExtension(props.data?.ext, props.data?.mime);
  const extensions = [
    lineNumbers(),
    highlightActiveLineGutter(),
    highlightActiveLine(),
    bracketMatching(),
    vsCodeLight,
    syntaxHighlighting(defaultHighlightStyle, { fallback: true }),
    EditorState.readOnly.of(true),
    EditorView.editable.of(false),
    wrapCompartment.of(isWordWrap.value ? EditorView.lineWrapping : []),
    search({ top: false }),
    searchHighlightField,
    EditorView.theme({
      '&': {
        height: '100%',
        maxHeight: '520px',
        fontSize: '13px',
        fontFamily: 'monospace',
      },
      '.cm-scroller': {
        overflow: 'auto',
        fontFamily: 'SFMono-Regular, Consolas, "Liberation Mono", Menlo, monospace',
      },
      '.cm-gutters': {
        backgroundColor: '#f5f5f5',
        borderRight: '1px solid #e0e0e0',
        color: '#888',
      },
      '.cm-activeLine': {
        backgroundColor: 'rgba(0, 0, 0, 0.03)',
      },
      '.cm-activeLineGutter': {
        backgroundColor: '#eaeaea',
      },
      '&, & *': {
        fontWeight: 'normal !important',
        fontStyle: 'normal !important',
      },
    }),
  ];

  if (langExt) {
    extensions.push(langExt);
  }

  const state = EditorState.create({
    doc: String(props.data?.content || ''),
    extensions,
  });

  editorView = new EditorView({
    state,
    parent: editorContainer.value,
  });

  if (searchText.value) {
    updateSearchQuery(false);
  }

  emit('rendered');
};

onMounted(() => {
  nextTick(() => {
    initEditor();
  });
});

onUnmounted(() => {
  if (editorView) {
    editorView.destroy();
    editorView = null;
  }
});

watch(
  () => props.data?.content,
  (newContent) => {
    if (editorView && typeof newContent === 'string') {
      const currentDoc = editorView.state.doc.toString();
      if (currentDoc !== newContent) {
        editorView.dispatch({
          changes: { from: 0, to: currentDoc.length, insert: newContent },
        });
        if (searchText.value) {
          updateSearchQuery(false);
        }
      }
    } else {
      nextTick(() => {
        initEditor();
      });
    }
  }
);
</script>

<template>
  <div class="file-visualization">
    <!-- CodeMirror Editor Viewport (Starts directly with code) -->
    <div
      ref="editorContainer"
      class="editor-viewport"
    />

    <!-- Bottom Footer Bar -->
    <div class="file-footer">
      <!-- Search Input Wrapper -->
      <div class="search-input-wrapper">
        <span class="codicon codicon-search search-icon" />
        <input
          v-model="searchText"
          type="text"
          class="file-search-input"
          spellcheck="false"
          :placeholder="__('Search in file...')"
          @input="onSearchInput"
          @keydown.enter.prevent="onSearchEnter"
          @keydown.esc="clearSearch"
        >

        <!-- Match Status Badge -->
        <div
          v-if="searchText.trim()"
          class="search-match-badge"
          :class="{ 'has-matches': matchCount > 0, 'no-matches': matchCount === 0 }"
        >
          {{ matchStatusText }}
        </div>

        <!-- Clear Button -->
        <button
          v-if="searchText"
          type="button"
          class="search-clear-btn"
          :title="__('Clear search')"
          @click="clearSearch"
        >
          <span class="codicon codicon-close" />
        </button>
      </div>

      <!-- Previous Match Button -->
      <button
        type="button"
        class="footer-btn btn-search-nav"
        :disabled="matchCount === 0"
        :title="__('Previous match (Shift+Enter)')"
        @click="goToPreviousMatch"
      >
        <span class="codicon codicon-arrow-up" />
      </button>

      <!-- Next Match Button -->
      <button
        type="button"
        class="footer-btn btn-search-nav"
        :disabled="matchCount === 0"
        :title="__('Next match (Enter)')"
        @click="goToNextMatch"
      >
        <span class="codicon codicon-arrow-down" />
      </button>

      <!-- Line Count Segment -->
      <div
        v-if="lineCount > 0"
        class="footer-segment segment-lines"
      >
        {{ sprintf(__('%d lines'), lineCount) }}
      </div>

      <!-- File Size Segment -->
      <div
        v-if="formattedSize"
        class="footer-segment segment-size"
      >
        {{ formattedSize }}
      </div>

      <!-- Word Wrap Toggle Button -->
      <button
        v-if="!hideWordWrap"
        type="button"
        class="footer-btn btn-word-wrap"
        :class="{ active: isWordWrap }"
        :title="__('Toggle word wrap')"
        @click="toggleWordWrap"
      >
        <span class="codicon codicon-word-wrap" />
        <span>{{ __('Word Wrap') }}</span>
      </button>
    </div>
  </div>
</template>

