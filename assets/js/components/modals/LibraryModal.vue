<script setup>
import { computed, watch, onMounted, onUnmounted, nextTick, ref, reactive } from 'vue';
import { useUiStore } from '../../stores/ui';
import { useOutlineStore } from '../../stores/outline.js';
import { EditorState } from '@codemirror/state';
import { EditorView, keymap } from '@codemirror/view';
import { indentUnit, bracketMatching } from '@codemirror/language';
import { elscript } from '../../lib/codemirror/elscript.js';
import { vsCodeLight } from '@fsegurai/codemirror-theme-bundle';
import { history, historyKeymap, indentWithTab, insertNewlineAndIndent } from '@codemirror/commands';
import { autocompletion, snippet as cmSnippet, closeCompletion } from '@codemirror/autocomplete';
import { createCompletionSource } from '../../lib/autocomplete.js';
import { formatEditorDocument } from '../../lib/codemirror/format-command.js';
import {
  handleLocalStorage,
  handleDownload,
  handleSelectDirectory,
  handleReadLocalSnippets,
  handleWriteLocalSnippets,
  handleOpenFilePicker,
  handleGetLocalFsStatus,
  handleDisconnectLocalFs,
  handleRequestFsPermission
} from '../../lib/api/client.js';
import { getUniqueSnippetName, normalizeSnippet, debugLog, __, sprintf } from '../../lib/helpers.js';
import TagInput from '../ui/TagInput.vue';

const props = defineProps({
  closeOnOutsideClick: {
    type: Boolean,
    default: true
  },
  customCloseElementSelector: {
    type: String,
    default: null
  }
});

const uiStore = useUiStore();
const outlineStore = useOutlineStore();
const isOpen = computed(() => uiStore.activeModal === 'library');

const closeModal = () => {
  uiStore.closeModal();
};

async function checkUnsavedAndProceed(callback) {
  if (unsavedSnippetIds.size > 0) {
    const action = await uiStore.showDialog({
      type: 'confirm',
      title: __('Save changes?'),
      message: __('You have unsaved changes in the current snippet. Do you want to save them?'),
      confirmText: __('Save'),
      denyText: __('Don\'t save'),
      cancelText: __('Cancel')
    });
    if (action === 'cancel') return;
    if (action === 'confirm') {
      const saved = await saveActiveSnippet();
      if (!saved) return;
    } else if (action === 'deny') {
      if (activeSnippet.value) {
        activeSnippet.value.name = activeSnippet.value.id;
      }
      unsavedSnippetIds.clear();
    }
  }
  callback();
}

const handleCloseModal = () => checkUnsavedAndProceed(closeModal);

const handleOutsideClick = () => {
  if (props.closeOnOutsideClick) {
    handleCloseModal();
  }
};

const attachArbitraryListener = () => {
  if (props.customCloseElementSelector) {
    const el = document.querySelector(props.customCloseElementSelector);
    if (el) {
      el.addEventListener('click', handleCloseModal);
    }
  }
};

const removeArbitraryListener = () => {
  if (props.customCloseElementSelector) {
    const el = document.querySelector(props.customCloseElementSelector);
    if (el) {
      el.removeEventListener('click', handleCloseModal);
    }
  }
};

watch(isOpen, async (newVal) => {
  if (newVal) {
    await nextTick();
    attachArbitraryListener();
  } else {
    removeArbitraryListener();
  }
});

onUnmounted(() => {
  removeArbitraryListener();
});

// Snippet Management State & Storage
const SNIPPETS_STORAGE_KEY = 'el_snippets';
const USE_LOCAL_FS_KEY = 'el_use_local_fs';

const snippets = ref([]);
const activeSnippetId = ref(null);
const searchQuery = ref('');
const unsavedSnippetIds = reactive(new Set());
const hasUnsavedChanges = computed(() => unsavedSnippetIds.has(activeSnippetId.value));
const useLocalFileSystem = ref(false);
const fsPermissionPending = ref(false);
const fsDirName = ref('');
const showImportExportMenu = ref(false);
const libraryListRef = ref(null);

const deduplicateSnippets = (list) => {
  const existingNames = new Set();
  return (Array.isArray(list) ? list : []).map(s => {
    const normalized = normalizeSnippet(s);
    const uniqueName = getUniqueSnippetName(normalized.name, existingNames);
    return {
      ...normalized,
      id: uniqueName,
      name: uniqueName
    };
  });
};

const loadSnippets = async () => {
  const savedUseFs = await handleLocalStorage('getItem', USE_LOCAL_FS_KEY);
  if (savedUseFs === 'true') {
    useLocalFileSystem.value = true;
    const result = await handleReadLocalSnippets();
    if (result && result.success && Array.isArray(result.snippets)) {
      fsPermissionPending.value = false;
      fsDirName.value = result.dirName || '';
      snippets.value = deduplicateSnippets(result.snippets);
      if (snippets.value.length > 0) {
        if (!activeSnippet.value) {
          activeSnippetId.value = snippets.value[0].id;
        }
      } else {
        activeSnippetId.value = null;
      }
      return;
    } else if (result && result.permission === 'prompt') {
      fsPermissionPending.value = true;
      fsDirName.value = result.dirName || '';
      try {
        const permRes = await handleRequestFsPermission();
        if (permRes && permRes.success && Array.isArray(permRes.snippets)) {
          fsPermissionPending.value = false;
          fsDirName.value = permRes.dirName || '';
          snippets.value = deduplicateSnippets(permRes.snippets);
          if (snippets.value.length > 0) {
            if (!activeSnippet.value) {
              activeSnippetId.value = snippets.value[0].id;
            }
          } else {
            activeSnippetId.value = null;
          }
          return;
        }
      } catch (e) {
        /* Ignore */
      }
      return;
    }
  }

  // Fallback / standard localStorage loading
  useLocalFileSystem.value = false;
  fsPermissionPending.value = false;
  try {
    const raw = await handleLocalStorage('getItem', SNIPPETS_STORAGE_KEY);
    if (raw !== null && raw !== undefined) {
      const parsed = JSON.parse(raw);
      if (Array.isArray(parsed)) {
        snippets.value = deduplicateSnippets(parsed);
        if (snippets.value.length > 0) {
          if (!activeSnippet.value) {
            activeSnippetId.value = snippets.value[0].id;
          }
        } else {
          activeSnippetId.value = null;
        }
        return;
      }
    }
  } catch (e) {
    /* Fall through */
  }

  // Initialize with empty snippets
  snippets.value = [];
  activeSnippetId.value = null;
};

const grantFsPermission = async () => {
  const res = await handleRequestFsPermission();
  if (res && res.success && Array.isArray(res.snippets)) {
    fsPermissionPending.value = false;
    fsDirName.value = res.dirName || '';
    snippets.value = deduplicateSnippets(res.snippets);
    if (snippets.value.length > 0) {
      activeSnippetId.value = snippets.value[0].id;
    } else {
      activeSnippetId.value = null;
    }
  }
};

const onTitleInput = () => {
  if (activeSnippetId.value !== null) {
    unsavedSnippetIds.add(activeSnippetId.value);
  }
};

const onTagChange = () => {
  if (activeSnippetId.value !== null) {
    unsavedSnippetIds.add(activeSnippetId.value);
  }
};

const allLibraryTags = computed(() => {
  const tagSet = new Set();
  for (const s of snippets.value) {
    if (Array.isArray(s.tags)) {
      for (const t of s.tags) {
        if (typeof t === 'string' && t.trim()) tagSet.add(t.trim());
      }
    }
  }
  return Array.from(tagSet).sort((a, b) => a.localeCompare(b));
});

const persistSnippets = async () => {
  if (useLocalFileSystem.value) {
    await handleWriteLocalSnippets(snippets.value);
  } else {
    await handleLocalStorage('setItem', SNIPPETS_STORAGE_KEY, JSON.stringify(snippets.value, null, 2));
  }
};

const handleToggleLocalFs = async () => {
  debugLog('LibraryModal: handleToggleLocalFs triggered', { useLocalFileSystem: useLocalFileSystem.value });
  if (useLocalFileSystem.value) {
    const status = await handleGetLocalFsStatus();
    let res;
    if (status && status.hasStoredHandle && status.permission === 'prompt') {
      debugLog('LibraryModal: Existing stored handle requires permission prompt');
      res = await handleRequestFsPermission();
    } else {
      debugLog('LibraryModal: Requesting directory selection');
      res = await handleSelectDirectory();
    }

    if (res.cancelled) {
      debugLog('LibraryModal: Directory selection was cancelled');
      useLocalFileSystem.value = false;
      return;
    }
    if (res.error) {
      debugLog('LibraryModal: File system error encountered, opening dialog modal:', res, 'warn');
      useLocalFileSystem.value = false;
      await uiStore.showDialog({
        type: 'error',
        title: __('File System Error'),
        message: res.message || __('Could not connect to local folder.'),
        confirmText: __('OK')
      });
      return;
    }
    if (res.success) {
      useLocalFileSystem.value = true;
      fsPermissionPending.value = false;
      fsDirName.value = res.dirName || '';
      snippets.value = deduplicateSnippets(res.snippets || []);
      unsavedSnippetIds.clear();
      if (snippets.value.length > 0) {
        activeSnippetId.value = snippets.value[0].id;
      } else {
        activeSnippetId.value = null;
      }
      await handleLocalStorage('setItem', USE_LOCAL_FS_KEY, 'true');
    }
  } else {
    await handleDisconnectLocalFs();
    await handleLocalStorage('setItem', USE_LOCAL_FS_KEY, 'false');
    fsPermissionPending.value = false;
    try {
      const raw = await handleLocalStorage('getItem', SNIPPETS_STORAGE_KEY);
      if (raw) {
        const parsed = JSON.parse(raw);
        snippets.value = deduplicateSnippets(parsed);
      } else {
        snippets.value = [];
      }
    } catch (err) {
      snippets.value = [];
    }
    unsavedSnippetIds.clear();
    if (snippets.value.length > 0) {
      activeSnippetId.value = snippets.value[0].id;
    } else {
      activeSnippetId.value = null;
    }
  }
};

const toggleImportExportMenu = () => {
  showImportExportMenu.value = !showImportExportMenu.value;
};

const closeImportExportMenu = () => {
  showImportExportMenu.value = false;
};

const handleExportSnippets = () => {
  closeImportExportMenu();
  const jsonStr = JSON.stringify(snippets.value, null, 2);
  handleDownload(jsonStr, 'snippets.json', 'application/json');
};

const handleImportSnippets = async () => {
  closeImportExportMenu();
  const res = await handleOpenFilePicker();
  if (res.cancelled) return;
  if (res.error) {
    await uiStore.showDialog({
      type: 'error',
      title: __('Import Error'),
      message: res.message || __('Failed to select file.'),
      confirmText: __('OK')
    });
    return;
  }
  if (!res.content) return;

  let importedData;
  try {
    importedData = JSON.parse(res.content);
  } catch (e) {
    await uiStore.showDialog({
      type: 'error',
      title: __('Invalid JSON'),
      message: __('The selected file is not a valid JSON document.'),
      confirmText: __('OK')
    });
    return;
  }

  const items = Array.isArray(importedData)
    ? importedData
    : (typeof importedData === 'object' && importedData !== null ? [importedData] : []);

  if (items.length === 0) {
    await uiStore.showDialog({
      type: 'warning',
      title: __('Empty File'),
      message: __('No snippets found in the selected file.'),
      confirmText: __('OK')
    });
    return;
  }

  const existingNames = new Set(snippets.value.map(s => (s.name || '').toLowerCase()));
  const addedSnippets = [];

  for (const item of items) {
    const rawName = (item.name || item.title || __('Untitled')).trim() || __('Untitled');
    const uniqueName = getUniqueSnippetName(rawName, existingNames);
    const snippetObj = {
      id: uniqueName,
      name: uniqueName,
      code: typeof item.code === 'string' ? item.code : (typeof item.content === 'string' ? item.content : ''),
      tags: Array.isArray(item.tags) ? item.tags.filter(t => typeof t === 'string' && t.trim()).map(t => t.trim()) : []
    };
    snippets.value.push(snippetObj);
    addedSnippets.push(snippetObj);
  }

  await persistSnippets();

  if (addedSnippets.length > 0) {
    activeSnippetId.value = addedSnippets[0].id;
    await scrollToActiveSnippet();
    await uiStore.showDialog({
      type: 'success',
      title: __('Import Complete'),
      message: sprintf(__('Successfully imported %d snippet(s).'), addedSnippets.length),
      confirmText: __('OK')
    });
  }
};

const filteredSnippets = computed(() => {
  if (!searchQuery.value) return snippets.value;
  const rawQuery = searchQuery.value.trim();
  if (!rawQuery) return snippets.value;

  const tagRegex = /tag:(?:\[([^\]]+)\]|([^\s]+))/gi;
  const tagFilters = [];
  let match;
  while ((match = tagRegex.exec(rawQuery)) !== null) {
    const tagVal = (match[1] || match[2] || '').trim().toLowerCase();
    if (tagVal) tagFilters.push(tagVal);
  }
  const textQuery = rawQuery.replace(tagRegex, '').trim().toLowerCase();

  return snippets.value.filter(s => {
    const snippetTags = (Array.isArray(s.tags) ? s.tags : []).map(t => String(t).toLowerCase());
    const snippetName = (s.name || '').toLowerCase();

    if (tagFilters.length > 0) {
      const matchesAllTags = tagFilters.every(f => snippetTags.some(t => t.includes(f)));
      if (!matchesAllTags) return false;
    }

    if (textQuery) {
      return snippetName.includes(textQuery) || snippetTags.some(t => t.includes(textQuery));
    }

    return true;
  });
});

const activeSnippet = computed(() => {
  const found = snippets.value.find(s => s.id === activeSnippetId.value);
  if (found && !Array.isArray(found.tags)) {
    found.tags = [];
  }
  return found || null;
});

const selectSnippet = async (id) => {
  if (hasUnsavedChanges.value) {
    const action = await uiStore.showDialog({
      type: 'confirm',
      title: __('Save changes?'),
      message: __('You have unsaved changes in the current snippet. Do you want to save them?'),
      confirmText: __('Save'),
      denyText: __('Don\'t save'),
      cancelText: __('Cancel')
    });
    if (action === 'cancel') return;
    if (action === 'confirm') {
      const saved = await saveActiveSnippet();
      if (!saved) return;
    } else if (action === 'deny') {
      if (activeSnippet.value) {
        activeSnippet.value.name = activeSnippet.value.id;
      }
      unsavedSnippetIds.delete(activeSnippetId.value);
    }
  }
  activeSnippetId.value = id;
};

const scrollToActiveSnippet = async (smooth = true) => {
  await nextTick();
  if (!libraryListRef.value) return;

  const activeEl = libraryListRef.value.querySelector('.library-list-item.active');
  if (activeEl) {
    if (typeof activeEl.scrollIntoView === 'function') {
      activeEl.scrollIntoView({
        behavior: smooth ? 'smooth' : 'auto',
        block: 'nearest'
      });
    } else {
      libraryListRef.value.scrollTop = libraryListRef.value.scrollHeight;
    }
  } else {
    if (typeof libraryListRef.value.scrollTo === 'function') {
      libraryListRef.value.scrollTo({
        top: libraryListRef.value.scrollHeight,
        behavior: smooth ? 'smooth' : 'auto'
      });
    } else {
      libraryListRef.value.scrollTop = libraryListRef.value.scrollHeight;
    }
  }
};

const createSnippet = async () => {
  if (hasUnsavedChanges.value) {
    const action = await uiStore.showDialog({
      type: 'confirm',
      title: __('Save changes?'),
      message: __('You have unsaved changes in the current snippet. Do you want to save them?'),
      confirmText: __('Save'),
      denyText: __('Don\'t save'),
      cancelText: __('Cancel')
    });
    if (action === 'cancel') return;
    if (action === 'confirm') {
      const saved = await saveActiveSnippet();
      if (!saved) return;
    } else if (action === 'deny') {
      if (activeSnippet.value) {
        activeSnippet.value.name = activeSnippet.value.id;
      }
      unsavedSnippetIds.delete(activeSnippetId.value);
    }
  }

  const existingNames = new Set(snippets.value.map(s => (s.name || '').toLowerCase()));
  const uniqueName = getUniqueSnippetName(__('New Snippet'), existingNames);
  const newSnippet = {
    id: uniqueName,
    name: uniqueName,
    code: '',
    tags: []
  };
  snippets.value.push(newSnippet);
  activeSnippetId.value = newSnippet.id;
  searchQuery.value = '';
  await persistSnippets();
  await scrollToActiveSnippet();
};

const saveActiveSnippet = async () => {
  if (!activeSnippet.value || !view) return false;

  const trimmedName = (activeSnippet.value.name || __('Untitled')).trim() || __('Untitled');

  const isDuplicate = snippets.value.some(
    s => s.id !== activeSnippetId.value && (s.name || '').trim().toLowerCase() === trimmedName.toLowerCase()
  );

  if (isDuplicate) {
    await uiStore.showDialog({
      type: 'warning',
      title: __('Duplicate Snippet Name'),
      message: sprintf(__('A snippet named "%s" already exists. Please choose a different name.'), trimmedName),
      confirmText: __('OK')
    });
    return false;
  }

  activeSnippet.value.code = getEditorValue();
  activeSnippet.value.name = trimmedName;
  activeSnippet.value.id = trimmedName;
  unsavedSnippetIds.delete(activeSnippetId.value);
  activeSnippetId.value = trimmedName;
  await persistSnippets();
  return true;
};

const handleGlobalKeydown = (e) => {
  if (isOpen.value && (e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
    e.preventDefault();
    saveActiveSnippet();
  }
};

onMounted(() => {
  loadSnippets();
  window.addEventListener('keydown', handleGlobalKeydown);
  window.addEventListener('click', closeImportExportMenu);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleGlobalKeydown);
  window.removeEventListener('click', closeImportExportMenu);
});

const deleteActiveSnippet = async () => {
  const idx = snippets.value.findIndex(s => s.id === activeSnippetId.value);
  if (idx !== -1) {
    const confirmed = await uiStore.showDialog({
      type: 'confirm',
      title: __('Delete Snippet'),
      message: __('Are you sure you want to delete this snippet?'),
      confirmText: __('Delete'),
      cancelText: __('Cancel')
    });
    if (!confirmed) return;
    unsavedSnippetIds.delete(activeSnippetId.value);
    snippets.value.splice(idx, 1);
    if (snippets.value.length > 0) {
      const nextIdx = Math.max(0, idx - 1);
      activeSnippetId.value = snippets.value[nextIdx].id;
    } else {
      activeSnippetId.value = null;
    }
    await persistSnippets();
  }
};

const insertActiveSnippet = () => {
  if (activeSnippet.value) {
    const code = getEditorValue();
    activeSnippet.value.code = code;
    unsavedSnippetIds.delete(activeSnippetId.value);
    persistSnippets();
    uiStore.triggerSnippetInsert(code);
    closeModal();
  }
};

// CodeMirror editor state
const editorContainer = ref(null);
let view = null;
let isProgrammaticUpdate = false;

const getEditorValue = () => {
  if (!view) return activeSnippet.value?.code || '';
  return view.state.doc.toString();
};

const setEditorValue = (val) => {
  if (!view) return;
  isProgrammaticUpdate = true;
  const tr = view.state.update({
    changes: { from: 0, to: view.state.doc.length, insert: val },
    selection: { anchor: 0, head: 0 }
  });
  view.dispatch(tr);
  if (view.scrollDOM) {
    view.scrollDOM.scrollTop = 0;
  }
  isProgrammaticUpdate = false;
};

const insertSnippetIntoLibrary = (text, options = {}) => {
  if (!view) return;

  try {
    closeCompletion(view);
    view.focus();

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

    const command = cmSnippet(text);
    command(view, text, from, to);

    if (!text.includes('${')) {
      nextTick(() => {
        const length = view.state.doc.length;
        view.dispatch({
          selection: { anchor: length, head: length },
          scrollIntoView: true
        });
      });
    }
  } catch (e) {
    view.dispatch(view.state.replaceSelection(text));
    view.focus();
  }
};

watch(
  () => uiStore.librarySnippetToInsert,
  (code) => {
    if (code && view) {
      insertSnippetIntoLibrary(code, { replaceWord: true });
      uiStore.triggerLibrarySnippetInsert(null);
    }
  }
);

const destroyCodeMirror = () => {
  if (view) {
    view.destroy();
    view = null;
  }
};

const formatActiveSnippetCode = () => {
  if (!view) return false;
  return formatEditorDocument(view);
};

const initCodeMirror = () => {
  destroyCodeMirror();
  if (!editorContainer.value) return;

  const cmCompletionSource = createCompletionSource(
    () => outlineStore.outlineFlat,
    () => outlineStore.outlineChained
  );

  const enterKeymap = [
    { key: 'Shift-Enter', run: insertNewlineAndIndent },
    { key: 'Enter', run: insertNewlineAndIndent }
  ];

  const state = EditorState.create({
    doc: activeSnippet.value?.code || '',
    extensions: [
      elscript(),
      vsCodeLight,
      history(),
      autocompletion({ override: [cmCompletionSource] }),
      keymap.of([
        indentWithTab, 
        ...historyKeymap, 
        ...enterKeymap,
        { key: 'Mod-s', run: () => { saveActiveSnippet(); return true; } },
        { key: 'Shift-Alt-f', run: () => { formatActiveSnippetCode(); return true; } }
      ]),
      indentUnit.of('    '),
      EditorState.tabSize.of(4),
      EditorView.lineWrapping,
      EditorView.theme(),
      bracketMatching(),
      EditorView.updateListener.of((update) => {
        if (update.docChanged && !isProgrammaticUpdate) {
          if (activeSnippetId.value !== null) {
            const currentCode = update.state.doc.toString();
            const originalCode = activeSnippet.value?.code || '';
            if (currentCode !== originalCode) {
              unsavedSnippetIds.add(activeSnippetId.value);
            } else {
              unsavedSnippetIds.delete(activeSnippetId.value);
            }
          }
        }
      })
    ]
  });

  view = new EditorView({ state, parent: editorContainer.value });

  view.dom.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
      e.preventDefault();
      saveActiveSnippet();
    }
  });
};

// Watch for modal open to init/re-init editor and reload snippets
watch(isOpen, async (open) => {
  if (open) {
    unsavedSnippetIds.clear();
    await loadSnippets();
    nextTick(() => {
      if (!view) {
        initCodeMirror();
      }
      setTimeout(() => { 
        if (view) {
          view.dispatch({ selection: { anchor: 0, head: 0 } });
          if (view.scrollDOM) {
            view.scrollDOM.scrollTop = 0;
          }
          view.focus(); 
          view.requestMeasure();
        } 
      }, 100);
    });
  } else {
    destroyCodeMirror();
    closeImportExportMenu();
  }
});

// Watch for snippet changes
watch(activeSnippet, (newSnippet) => {
  if (!newSnippet) {
    destroyCodeMirror();
    return;
  }
  nextTick(() => {
    if (!view && editorContainer.value) {
      initCodeMirror();
      setTimeout(() => { 
        if (view) {
          view.dispatch({ selection: { anchor: 0, head: 0 } });
          if (view.scrollDOM) {
            view.scrollDOM.scrollTop = 0;
          }
          view.focus(); 
          view.requestMeasure();
        } 
      }, 100);
    } else if (view) {
      const newCode = newSnippet.code || '';
      if (getEditorValue() !== newCode) {
        setEditorValue(newCode);
      }
      setTimeout(() => { 
        if (view) {
          view.dispatch({ 
            selection: { anchor: 0, head: 0 }
          });
          if (view.scrollDOM) {
            view.scrollDOM.scrollTop = 0;
          }
          view.focus(); 
          view.requestMeasure();
        } 
      }, 100);
    }
  });
}, { immediate: false });

onUnmounted(() => {
  destroyCodeMirror();
});
</script>

<template>
  <div
    v-if="isOpen"
    id="library-modal"
    class="modal-overlay"
    @mousedown.self="handleOutsideClick"
  >
    <div class="modal-container library-modal-container">
      <button
        class="modal-close"
        @click="handleCloseModal"
      >
        <div class="codicon codicon-close" />
      </button>
      <div class="modal-header">
        <h2>{{ __('Library') }}</h2>
      </div>
      <div class="modal-content library-layout">
        <div class="library-sidebar">
          <div class="library-search-bar">
            <input
              v-model="searchQuery"
              type="text"
              :placeholder="__('Search')"
            >
          </div>
          
          <div
            class="library-list-item btn-create"
            @click="createSnippet"
          >
            <div class="codicon codicon-add" />
            {{ __('Add Snippet') }}
          </div>

          <div
            ref="libraryListRef"
            class="library-list"
          >
            <div
              v-for="snippet in filteredSnippets"
              :key="snippet.id"
              class="library-list-item"
              :class="{ active: snippet.id === activeSnippetId, 'has-unsaved': unsavedSnippetIds.has(snippet.id) }"
              @click="selectSnippet(snippet.id)"
            >
              <span
                v-if="unsavedSnippetIds.has(snippet.id)"
                class="unsaved-dot"
              />
              {{ snippet.name || __('(Untitled)') }}
            </div>
            <div
              v-if="filteredSnippets.length === 0"
              class="no-results"
            >
              {{ __('No snippets found.') }}
            </div>
          </div>

          <!-- Sidebar Footer: Import/Export -->
          <div class="library-sidebar-footer">
            <div class="library-import-export-wrapper">
              <button
                type="button"
                class="library-import-export-btn"
                :title="__('Import or export snippets')"
                @click.stop="toggleImportExportMenu"
              >
                <div class="codicon codicon-cloud-upload import-icon" />
                <span>{{ __('Import/Export') }}</span>
              </button>

              <div
                v-if="showImportExportMenu"
                class="library-dropdown-menu"
                @click.stop
              >
                <button
                  type="button"
                  class="dropdown-item"
                  @click="handleImportSnippets"
                >
                  <div class="codicon codicon-cloud-upload" />
                  <span>{{ __('Import JSON') }}</span>
                </button>
                <button
                  type="button"
                  class="dropdown-item"
                  @click="handleExportSnippets"
                >
                  <div class="codicon codicon-cloud-download" />
                  <span>{{ __('Export JSON') }}</span>
                </button>
              </div>
            </div>
          </div>
        </div>
        <div
          v-if="activeSnippet"
          class="library-main"
        >
          <div class="library-main-header">
            <input
              v-model="activeSnippet.name"
              type="text"
              class="snippet-title-input"
              :placeholder="__('Snippet title')"
              @input="onTitleInput"
              @keydown.enter.prevent="saveActiveSnippet"
            >
            <div class="library-main-actions">
              <button
                :title="__('Format code (Shift+Alt+F)')"
                @click="formatActiveSnippetCode"
              >
                <div class="codicon codicon-wand" />
              </button>
              <button
                :title="__('Save')"
                @click="saveActiveSnippet"
              >
                <div class="codicon codicon-save" />
              </button>
              <button
                :title="__('Delete')"
                @click="deleteActiveSnippet"
              >
                <div class="codicon codicon-trash" />
              </button>
            </div>
          </div>
          <div class="library-main-editor">
            <div
              ref="editorContainer"
              class="snippet-code-editor cm-container cm-lang-elscript"
            />
          </div>
          <div class="library-main-tags">
            <span class="tags-icon">
              <span class="codicon codicon-tag" />
            </span>
            <TagInput
              v-model="activeSnippet.tags"
              :all-tags="allLibraryTags"
              :placeholder="__('Add tags...')"
              @change="onTagChange"
            />
          </div>
        </div>
        <div
          v-else
          class="library-main empty"
        >
          <div
            v-if="fsPermissionPending"
            class="library-fs-banner"
          >
            <span>{{ sprintf(__('Permission required to access folder "%s".'), fsDirName || 'local') }}</span>
            <button
              type="button"
              class="btn btn-primary"
              @click="grantFsPermission"
            >
              {{ __('Grant Access') }}
            </button>
          </div>
          <div class="library-empty-state">
            <div class="library-empty-icon-wrapper">
              <div class="codicon codicon-library" />
            </div>
            <h3 class="library-empty-title">
              {{ __('Snippet library') }}
            </h3>
            <p class="library-empty-desc">
              {{ __('Save your frequent snippets here to reuse them easily.') }}
            </p>
            <p class="library-empty-tip">
              <span class="codicon codicon-lightbulb tip-icon" />
              <span class="tip-label">{{ __('Pro tip:') }}</span> {{ __('select Use local file system to save snippets to your hard drive.') }}
            </p>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <div class="library-fs-container">
          <label
            class="library-fs-checkbox-label"
            :title="__('Store snippets in a local folder using native File System API')"
          >
            <input
              v-model="useLocalFileSystem"
              type="checkbox"
              class="library-fs-checkbox"
              @change="handleToggleLocalFs"
            >
            <span class="library-fs-text">{{ __('Use local file system') }}</span>
          </label>
        </div>
        <div class="modal-footer-actions">
          <button
            class="btn btn-default btn-cancel fw-80px"
            @click="handleCloseModal"
          >
            {{ __('Close') }}
          </button>
          <button
            class="btn btn-primary btn-insert fw-80px"
            :disabled="!activeSnippet"
            @click="insertActiveSnippet"
          >
            {{ __('Insert') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>