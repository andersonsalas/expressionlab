<script setup>
import { ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { useUiStore } from '../../stores/ui';
import { EditorState } from '@codemirror/state';
import { EditorView, keymap, lineNumbers } from '@codemirror/view';
import { indentUnit, bracketMatching, foldGutter, foldKeymap } from '@codemirror/language';
import { lintGutter } from '@codemirror/lint';
import { elscript } from '../../lib/codemirror/elscript.js';
import { autocompletion, snippet, closeCompletion } from '@codemirror/autocomplete';
import { createCompletionSource } from '../../lib/autocomplete.js';
import { history as cmHistory, historyKeymap, indentWithTab, insertNewlineAndIndent } from '@codemirror/commands';
import { vsCodeLight } from '@fsegurai/codemirror-theme-bundle';
import { formatEditorDocument } from '../../lib/codemirror/format-command.js';
import { expressionLabLinter, lintExpressionLab } from '../../lib/codemirror/elscript-linter.js';
import {
  search,
  setSearchQuery,
  SearchQuery,
  findNext,
  findPrevious,
  replaceNext,
  replaceAll,
} from '@codemirror/search';
import { searchHighlightField, setSearchHighlightEffect } from '../../lib/codemirror/search-highlight.js';
import { __ } from '../../lib/helpers.js';
import ConsoleFindWidget from './ConsoleFindWidget.vue';

const uiStore = useUiStore();

const props = defineProps({
  loading: {
    type: Boolean,
    default: false
  },
  completionItems: {
    type: Array, // The flat list of completion items
    default: () => []
  },
  chainData: {
    type: Object, // { typeRegistry, rootObjects } for lazy chain resolution
    default: null
  },
  mode: {
    type: String, // 'repl' | 'scratchpad' | 'notebook'
    default: 'repl'
  },
  initialValue: {
    type: String,
    default: ''
  }
});

const emit = defineEmits(['execute', 'history-up', 'history-down', 'clear', 'change', 'focus']);

const editorContainer = ref(null);
const hasSyntaxErrors = ref(false);
let view = null;
let editorExtensions = [];
let syntaxDebounceTimer = null;

const checkSyntax = (state) => {
  const targetState = state || view?.state;
  if (!targetState) {
    hasSyntaxErrors.value = false;
    return;
  }

  const text = targetState.doc.toString();
  if (!text.trim()) {
    if (syntaxDebounceTimer) {
      clearTimeout(syntaxDebounceTimer);
      syntaxDebounceTimer = null;
    }
    hasSyntaxErrors.value = false;
    return;
  }

  if (syntaxDebounceTimer) {
    clearTimeout(syntaxDebounceTimer);
  }

  syntaxDebounceTimer = setTimeout(() => {
    const currentState = view ? view.state : targetState;
    const diagnostics = lintExpressionLab(currentState);
    hasSyntaxErrors.value = diagnostics.some((d) => d.severity === 'error');
  }, 150);
};

const getEditorValue = () => {
  if (!view) return '';
  return view.state.doc.toString();
};

const setEditorValue = (val) => {
  if (!view) return;
  const tr = view.state.update({
    changes: { from: 0, to: view.state.doc.length, insert: val }
  });
  view.dispatch(tr);
};

const formatCode = () => {
  if (!view) return false;
  return formatEditorDocument(view);
};

const insertSnippet = (text, options = {}) => {
    if (!view) {
        return;
    }

    try {
        closeCompletion(view);
        view.focus();
        
        if (!view.state.selection.main) {
            view.dispatch({
                selection: { anchor: view.state.doc.length, head: view.state.doc.length }
            });
        }

        let from = view.state.selection.main.from;
        let to = view.state.selection.main.to;

        if (options.replaceWord && from === to) {
            const line = view.state.doc.lineAt(from);
            const beforeCursor = view.state.doc.sliceString(line.from, from);
            const match = beforeCursor.match(/[\w.]*$/);
            if (match && match[0].length > 0) {
                from = from - match[0].length;
            }
        }

        const command = snippet(text);
        command(view, text, from, to);

        if (!text.includes('${')) {
            nextTick(() => {
                moveCursorToEnd();
            });
        }
    } catch (e) {
        view.dispatch(view.state.replaceSelection(text));
        view.focus();
        nextTick(() => {
            moveCursorToEnd();
        });
    }
};


const moveCursorToEnd = () => {
    if (!view) return;
    const length = view.state.doc.length;
    view.dispatch({
        selection: { anchor: length, head: length },
        scrollIntoView: true
    });
};

const clearEditor = () => {
  if (!view) return;
  const state = EditorState.create({
    doc: '',
    extensions: editorExtensions
  });
  view.setState(state);
  hasSyntaxErrors.value = false;
  view.focus();
};

const isSearchOpen = ref(false);
const isReplaceOpen = ref(false);
const searchQuery = ref('');
const replaceQuery = ref('');
const matchCase = ref(false);
const wholeWord = ref(false);
const useRegex = ref(false);
const regexError = ref('');
const totalMatches = ref(0);
const currentMatchIndex = ref(0);
const findWidgetRef = ref(null);

const buildCurrentSearchQuery = () => {
  const q = searchQuery.value || '';
  let rxErr = '';
  if (useRegex.value && q) {
    try {
      new RegExp(q);
    } catch (e) {
      rxErr = e.message;
    }
  }
  regexError.value = rxErr;

  return new SearchQuery({
    search: q,
    replace: replaceQuery.value || '',
    caseSensitive: matchCase.value,
    wholeWord: wholeWord.value,
    regexp: useRegex.value,
  });
};

const updateSearchMatches = () => {
  if (!view || !searchQuery.value) {
    totalMatches.value = 0;
    currentMatchIndex.value = 0;
    return;
  }
  const query = buildCurrentSearchQuery();
  if (!query.valid) {
    totalMatches.value = 0;
    currentMatchIndex.value = 0;
    return;
  }

  const cursor = query.getCursor(view.state.doc);
  const matchesList = [];
  let m;
  while (!(m = cursor.next()).done) {
    matchesList.push(m.value);
  }
  totalMatches.value = matchesList.length;

  const sel = view.state.selection.main;
  const idx = matchesList.findIndex((match) => match.from === sel.from && match.to === sel.to);
  currentMatchIndex.value = idx !== -1 ? idx : 0;

  const activeRange = idx !== -1 ? { from: sel.from, to: sel.to } : null;
  view.dispatch({
    effects: setSearchHighlightEffect.of({
      query: searchQuery.value,
      caseSensitive: matchCase.value,
      wholeWord: wholeWord.value,
      regexp: useRegex.value,
      activeRange,
    }),
  });
};

const syncSearchToCodeMirror = () => {
  if (!view) return;
  const query = buildCurrentSearchQuery();
  view.dispatch({
    effects: [
      setSearchQuery.of(query),
      setSearchHighlightEffect.of({
        query: searchQuery.value,
        caseSensitive: matchCase.value,
        wholeWord: wholeWord.value,
        regexp: useRegex.value,
      }),
    ],
  });
  updateSearchMatches();
};

const openSearch = (withReplace = false) => {
  if (props.mode !== 'scratchpad') return;
  isSearchOpen.value = true;
  if (withReplace) {
    isReplaceOpen.value = true;
  }
  if (view) {
    const sel = view.state.selection.main;
    if (!sel.empty) {
      const selectedText = view.state.sliceDoc(sel.from, sel.to);
      if (selectedText && !selectedText.includes('\n')) {
        searchQuery.value = selectedText;
      }
    }
  }
  syncSearchToCodeMirror();
  nextTick(() => {
    if (withReplace) {
      findWidgetRef.value?.focusReplaceInput?.();
    } else {
      findWidgetRef.value?.focusSearchInput?.();
    }
  });
};

const closeSearch = () => {
  isSearchOpen.value = false;
  if (view) {
    view.dispatch({
      effects: [
        setSearchQuery.of(new SearchQuery({ search: '' })),
        setSearchHighlightEffect.of({ query: '' }),
      ],
    });
    view.focus();
  }
};

const handleFindNext = () => {
  if (!view) return;
  findNext(view);
  updateSearchMatches();
};

const handleFindPrev = () => {
  if (!view) return;
  findPrevious(view);
  updateSearchMatches();
};

const handleReplace = () => {
  if (!view) return;
  const query = buildCurrentSearchQuery();
  view.dispatch({ effects: setSearchQuery.of(query) });
  replaceNext(view);
  updateSearchMatches();
};

const handleReplaceAll = () => {
  if (!view) return;
  const query = buildCurrentSearchQuery();
  view.dispatch({ effects: setSearchQuery.of(query) });
  replaceAll(view);
  updateSearchMatches();
};

watch(
  [searchQuery, matchCase, wholeWord, useRegex],
  () => {
    if (isSearchOpen.value) {
      syncSearchToCodeMirror();
    }
  }
);

watch(replaceQuery, () => {
  if (isSearchOpen.value && view) {
    const query = buildCurrentSearchQuery();
    view.dispatch({ effects: setSearchQuery.of(query) });
  }
});

const setSelection = (from, to, options = {}) => {
  if (!view) return;
  const docLen = view.state.doc.length;
  const clampedFrom = Math.max(0, Math.min(from, docLen));
  const clampedTo = Math.max(0, Math.min(to, docLen));
  const shouldScroll = options?.scroll !== false;
  view.dispatch({
    selection: { anchor: clampedFrom, head: clampedTo },
    ...(shouldScroll ? { scrollIntoView: true } : {}),
  });
  if (options && options.focus === false) {
    return;
  }
  view.focus();
};

const collapseSelection = () => {
  if (!view) return;
  if (view.state.selection.main.empty) return;
  view.dispatch({
    selection: { anchor: view.state.selection.main.head },
  });
};

const setSearchHighlight = (queryText, options = {}) => {
  if (!view) return;
  view.dispatch({
    effects: setSearchHighlightEffect.of({
      query: queryText || '',
      activeRange: options?.activeRange || null,
      caseSensitive: Boolean(options?.caseSensitive),
      wholeWord: Boolean(options?.wholeWord),
      regexp: Boolean(options?.regexp),
    }),
  });
};

const setSearchQueryText = (queryText, options = {}) => {
  if (!view) return;
  const q = queryText || '';
  view.dispatch({
    effects: setSearchQuery.of(
      new SearchQuery({
        search: q,
        caseSensitive: Boolean(options?.caseSensitive),
        wholeWord: Boolean(options?.wholeWord),
        regexp: Boolean(options?.regexp),
      })
    ),
  });
};

// Expose methods to parent
defineExpose({
    hasSyntaxErrors,
    getEditorValue,
    setEditorValue,
    clearEditor,
    insertSnippet,
    moveCursorToEnd,
    formatCode,
    openSearch,
    closeSearch,
    isSearchOpen,
    setSelection,
    collapseSelection,
    setSearchQueryText,
    setSearchHighlight,
    focus: () => view?.focus()
});

const handleEnter = () => {
    const input = getEditorValue();
    if (!input.trim()) return;

    if (input.toLowerCase().trim() === 'clear') {
        emit('clear');
        return;
    }
    
    emit('execute', input);
    setEditorValue('');
    hasSyntaxErrors.value = false;
    if (syntaxDebounceTimer) {
        clearTimeout(syntaxDebounceTimer);
        syntaxDebounceTimer = null;
    }
    return true; 
};

const handleScratchpadExecute = () => {
    const input = getEditorValue();
    if (!input.trim()) return;
    emit('execute', input);
    return true;
};

// Autocomplete source (delegated to autocomplete.js)
const cmCompletionSource = createCompletionSource(
    () => props.completionItems || [],
    () => props.chainData
);

onMounted(() => {
    const isScratchpad = props.mode === 'scratchpad';
    const isNotebook = props.mode === 'notebook';
    const isMultiline = isScratchpad || isNotebook;

    const enterKeymap = isMultiline
      ? [
          {
            key: 'Shift-Enter',
            run: isNotebook
              ? () => { handleScratchpadExecute(); return true; }
              : insertNewlineAndIndent
          },
          {
            key: 'Enter',
            run: insertNewlineAndIndent
          },
          {
            key: 'Mod-Enter',
            run: () => { handleScratchpadExecute(); return true; }
          },
          {
            key: 'Mod-r',
            run: (cmView, event) => {
              if (event) event.preventDefault();
              handleScratchpadExecute();
              return true;
            }
          },
          ...foldKeymap
        ]
      : [
          {
            key: 'Shift-Enter',
            run: insertNewlineAndIndent
          },
          {
            key: 'Enter',
            run: () => { handleEnter(); return true; }
          }
        ];

    const upDownHistoryKeymap = isMultiline
      ? []
      : [
          {
            key: 'Alt-ArrowUp',
            run: () => { emit('history-up'); return true; }
          },
          {
            key: 'Alt-ArrowDown',
            run: () => { emit('history-down'); return true; }
          }
        ];

    const formatKeymap = [
      {
        key: 'Shift-Alt-f',
        run: (cmView) => {
          return formatEditorDocument(cmView);
        }
      }
    ];

    const syntaxUpdateListener = EditorView.updateListener.of((update) => {
      if (update.docChanged) {
        checkSyntax(update.state);
        emit('change', update.state.doc.toString());
        if (isSearchOpen.value && isScratchpad) {
          updateSearchMatches();
        }
      } else if (update.selectionSet && isSearchOpen.value && isScratchpad) {
        updateSearchMatches();
      }
      if (update.focusChanged && update.view.hasFocus) {
        emit('focus');
      }
    });

    const extensions = [
      elscript(),
      vsCodeLight,
      cmHistory(),
      autocompletion({ override: [cmCompletionSource] }),
      keymap.of([
        indentWithTab,
        ...historyKeymap,
        ...enterKeymap,
        ...upDownHistoryKeymap,
        ...formatKeymap,
        ...(isScratchpad ? [
          { key: 'Mod-f', run: () => { openSearch(false); return true; } },
          { key: 'Mod-h', run: () => { openSearch(true); return true; } },
          { key: 'F3', run: () => { handleFindNext(); return true; } },
          { key: 'Shift-F3', run: () => { handleFindPrev(); return true; } },
          { key: 'Escape', run: () => { if (isSearchOpen.value) { closeSearch(); return true; } return false; } },
        ] : []),
      ]),
      indentUnit.of('    '),
      EditorState.tabSize.of(4),
      EditorView.lineWrapping,
      EditorView.domEventHandlers({
          paste(event, view) {
              event.preventDefault();
              const text = event.clipboardData.getData('text/plain');
              if (text) {
                  view.dispatch(view.state.replaceSelection(text.replace(/\t/g, '    ')));
              }
          }
      }),
      EditorView.theme(),
      bracketMatching(),
      expressionLabLinter(),
      syntaxUpdateListener,
      searchHighlightField,
    ];

    if (isScratchpad || props.mode === 'notebook') {
      extensions.push(search({ createPanel: () => null }));
    }

    if (isMultiline) {
      extensions.unshift(
        lineNumbers(),
        foldGutter({
          openText: ' ',
          closedText: ' ',
          markerDOM: (open) => {
            const span = document.createElement('span');
            span.className = `fold-marker ${open ? 'open' : 'closed'}`;
            span.title = open ? 'Fold line' : 'Unfold line';
            span.setAttribute('aria-expanded', String(open));
            return span;
          },
        }),
        lintGutter()
      );
    }

    editorExtensions = extensions;

    const state = EditorState.create({
      doc: props.initialValue || '',
      extensions
    });

    view = new EditorView({ state, parent: editorContainer.value });
    if (props.initialValue) {
      checkSyntax(state);
    }
    if (!isNotebook) {
      view.focus();
    }
});

onBeforeUnmount(() => {
    if (syntaxDebounceTimer) {
        clearTimeout(syntaxDebounceTimer);
        syntaxDebounceTimer = null;
    }
    if (view) view.destroy();
});
</script>

<template>
  <div
    class="entry editor-entry in active"
    :class="[
      { loading: props.mode === 'repl' && loading, 'has-syntax-errors': hasSyntaxErrors },
      `mode-${uiStore.consoleMode}`,
      props.mode === 'scratchpad'
        ? 'scratchpad-editor'
        : props.mode === 'notebook'
          ? 'notebook-code-editor'
          : 'repl-editor'
    ]"
  >
    <ConsoleFindWidget
      v-if="props.mode === 'scratchpad' && isSearchOpen"
      ref="findWidgetRef"
      v-model:search-query="searchQuery"
      v-model:replace-query="replaceQuery"
      :is-replace-open="isReplaceOpen"
      :match-case="matchCase"
      :whole-word="wholeWord"
      :use-regex="useRegex"
      :regex-error="regexError"
      :total-matches="totalMatches"
      :current-match-index="currentMatchIndex"
      @toggle-replace="isReplaceOpen = !isReplaceOpen"
      @toggle-match-case="matchCase = !matchCase"
      @toggle-whole-word="wholeWord = !wholeWord"
      @toggle-use-regex="useRegex = !useRegex"
      @next="handleFindNext"
      @prev="handleFindPrev"
      @replace="handleReplace"
      @replace-all="handleReplaceAll"
      @close="closeSearch"
    />

    <div
      v-show="props.mode !== 'repl' || !loading"
      ref="editorContainer"
      class="code-editor-container cm-container cm-lang-elscript"
      :style="
        props.mode === 'scratchpad'
          ? { width: '100%', height: '100%' }
          : props.mode === 'notebook'
            ? { width: '100%', minHeight: '56px' }
            : { width: '100%', minHeight: '36px' }
      "
    />
    <div
      v-show="props.mode === 'repl' && loading"
      class="metro-spinner"
    />
  </div>
</template>
