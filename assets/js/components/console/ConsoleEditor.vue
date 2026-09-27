<script setup>
import { ref, onMounted, onBeforeUnmount, nextTick } from 'vue';
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
    type: String, // 'repl' | 'scratchpad'
    default: 'repl'
  }
});

const emit = defineEmits(['execute', 'history-up', 'history-down', 'clear']);

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

// Expose methods to parent
defineExpose({
    hasSyntaxErrors,
    getEditorValue,
    setEditorValue,
    clearEditor,
    insertSnippet,
    moveCursorToEnd,
    formatCode,
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

    const enterKeymap = isScratchpad
      ? [
          {
            key: 'Shift-Enter',
            run: insertNewlineAndIndent
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

    const upDownHistoryKeymap = isScratchpad
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
      }
    });

    const extensions = [
      elscript(),
      vsCodeLight,
      cmHistory(),
      autocompletion({ override: [cmCompletionSource] }),
      keymap.of([indentWithTab, ...historyKeymap, ...enterKeymap, ...upDownHistoryKeymap, ...formatKeymap]),
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
    ];

    if (isScratchpad) {
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
      doc: '',
      extensions
    });

    view = new EditorView({ state, parent: editorContainer.value });
    view.focus();
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
      { loading: props.mode !== 'scratchpad' && loading, 'has-syntax-errors': hasSyntaxErrors },
      `mode-${uiStore.consoleMode}`,
      props.mode === 'scratchpad' ? 'scratchpad-editor' : 'repl-editor'
    ]"
  >
    <div
      v-show="props.mode === 'scratchpad' || !loading"
      ref="editorContainer"
      class="code-editor-container cm-container cm-lang-elscript"
      :style="props.mode === 'scratchpad' ? { width: '100%', height: '100%' } : { width: '100%', minHeight: '36px' }"
    />
    <div
      v-show="props.mode !== 'scratchpad' && loading"
      class="metro-spinner"
    />
  </div>
</template>
