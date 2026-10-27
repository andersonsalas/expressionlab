<script setup>
import { ref, computed, watch, onUnmounted } from 'vue';
import { EditorState } from '@codemirror/state';
import { EditorView } from '@codemirror/view';
import { syntaxHighlighting, defaultHighlightStyle, bracketMatching } from '@codemirror/language';
import { elscript } from '../../lib/codemirror/elscript.js';
import { vsCodeLight } from '@fsegurai/codemirror-theme-bundle';
import { useUiStore } from '../../stores/ui.js';
import { useTabsStore } from '../../stores/tabs.js';
import { useFilesystemStore } from '../../stores/filesystem.js';
import { __ } from '../../lib/helpers.js';
import {
  FILES_OPERATIONS,
  FILES_OPERATION_CONFIGS,
  FILES_MAX_READ_KB,
  FILES_MIN_READ_KB,
  DEFAULT_TAIL_LINES,
  MIN_TAIL_LINES,
  MAX_TAIL_LINES,
  sanitizeRelativePath,
  isOperationApplicable,
  generateFilesExpression,
  filterTreeNodes,
} from '../../lib/files-builder-generator.js';
import { formatExpressionLab } from '../../lib/codemirror/formatter.js';
import FilesystemTreeNode from './FilesystemTreeNode.vue';

defineProps({
  closeOnOutsideClick: {
    type: Boolean,
    default: false,
  },
});

const uiStore = useUiStore();
const tabsStore = useTabsStore();
const filesystemStore = useFilesystemStore();

const isScratchpad = computed(() => tabsStore.activeTab?.mode === 'scratchpad');
const isOpen = computed(() => uiStore.activeModal === 'filesystem-inspector');

// Navigation Pills: 'presets' | 'tree' | 'custom'
const activeView = ref('presets');

// Navigation Tabs: 'target' | 'operation'
const activeMainTab = ref('target');

// Unique target origin tracking: 'presets' | 'tree' | 'custom' | null
const targetOrigin = ref(null);

// Computed alias for backward compatibility
const activeTab = computed({
  get: () => activeMainTab.value,
  set: (val) => {
    if (val === 'target' || val === 'operation') {
      activeMainTab.value = val;
    } else if (val === 'presets' || val === 'tree' || val === 'custom') {
      activeView.value = val;
      activeMainTab.value = 'target';
    }
  },
});

// Presets and Tree from Pinia store (backed by async AJAX + localStorage)
const presetsList = computed(() => filesystemStore.presets || []);
const treeRoot = computed(() => filesystemStore.tree || []);
const isLoading = computed(() => filesystemStore.loading && !filesystemStore.loaded);
const loadError = computed(() => filesystemStore.error);

// Target Selection State (defaults to empty: no selection until user chooses a target)
const selectedTarget = ref({
  path: '',
  name: '',
  type: 'file', // 'file' | 'dir'
  size: null,
  exists: true,
  description: '',
});

// Selected Operation
const selectedOperation = ref(FILES_OPERATIONS.READ);
const isManualOperation = ref(false);

// Parameters State
const parameters = ref({
  tailLines: null,
  readKb: null,
  sizeProperty: 'all',
  modifiedProperty: 'all',
  permsProperty: 'all',
  statsProperty: 'all',
});

// Custom Path State
const customPathInput = ref('');
const customPathType = ref('file'); // 'file' | 'dir'

// Tree Search & Expand State
const treeSearchQuery = ref('');
const expandedDirs = ref(new Set(['wp-content', 'wp-content/themes', 'wp-content/plugins', 'wp-content/mu-plugins']));
const collapsedInSearch = ref(new Set());

const isSearchActive = computed(() => Boolean(treeSearchQuery.value.trim()));

watch(treeSearchQuery, () => {
  collapsedInSearch.value.clear();
});

const toggleDirExpand = (dirPath) => {
  if (isSearchActive.value) {
    if (collapsedInSearch.value.has(dirPath)) {
      collapsedInSearch.value.delete(dirPath);
    } else {
      collapsedInSearch.value.add(dirPath);
    }
    return;
  }

  if (expandedDirs.value.has(dirPath)) {
    expandedDirs.value.delete(dirPath);
  } else {
    expandedDirs.value.add(dirPath);
  }
};

const isDirExpanded = (dirPath) => {
  if (isSearchActive.value) {
    return !collapsedInSearch.value.has(dirPath);
  }
  return expandedDirs.value.has(dirPath);
};

const filteredTree = computed(() => {
  return filterTreeNodes(treeRoot.value, treeSearchQuery.value);
});

// Pill Selection Handler
const selectPill = (view) => {
  activeView.value = view;
  activeMainTab.value = 'target';
};

// Selection Handlers
const selectTarget = (target) => {
  const normPath = sanitizeRelativePath(target.path);
  const targetType = target.type === 'dir' ? 'dir' : 'file';

  selectedTarget.value = {
    path: normPath,
    name: target.name || normPath,
    type: targetType,
    size: target.size || null,
    exists: target.exists !== undefined ? target.exists : true,
    description: target.description || '',
  };

  // Smart operation choice vs User manual choice
  if (!isManualOperation.value) {
    if (targetType === 'dir') {
      selectedOperation.value = FILES_OPERATIONS.LIST;
    } else {
      selectedOperation.value = normPath.endsWith('.log') ? FILES_OPERATIONS.TAIL : FILES_OPERATIONS.READ;
    }
  } else if (!isOperationApplicable(selectedOperation.value, targetType)) {
    // Current manual operation is incompatible with new target type: adapt and reset manual flag
    isManualOperation.value = false;
    if (targetType === 'dir') {
      selectedOperation.value = FILES_OPERATIONS.LIST;
    } else {
      selectedOperation.value = normPath.endsWith('.log') ? FILES_OPERATIONS.TAIL : FILES_OPERATIONS.READ;
    }
  }
};

const selectPreset = (preset) => {
  targetOrigin.value = 'presets';
  customPathInput.value = '';
  selectTarget({
    path: preset.path,
    name: preset.name,
    type: preset.type === 'dir' ? 'dir' : 'file',
    size: preset.size,
    exists: preset.exists,
    description: preset.description,
  });

  if (!preset.exists) {
    selectedOperation.value = FILES_OPERATIONS.EXISTS;
    isManualOperation.value = false;
  } else if (!isManualOperation.value && preset.id === 'debug_log') {
    selectedOperation.value = FILES_OPERATIONS.TAIL;
  }
};

const selectTreeNode = (node) => {
  targetOrigin.value = 'tree';
  customPathInput.value = '';
  selectTarget(node);

  if (node.path) {
    const parts = node.path.split('/');
    let acc = '';
    for (let i = 0; i < parts.length - 1; i++) {
      acc = acc ? `${acc}/${parts[i]}` : parts[i];
      expandedDirs.value.add(acc);
    }
  }
};

const selectOperation = (opId) => {
  selectedOperation.value = opId;
  isManualOperation.value = true;
};

const applyCustomPath = () => {
  const sanitized = sanitizeRelativePath(customPathInput.value);
  if (!sanitized) {
    if (targetOrigin.value === 'custom') {
      selectedTarget.value = {
        path: '',
        name: '',
        type: customPathType.value,
        size: null,
        exists: true,
        description: '',
      };
      targetOrigin.value = null;
    }
    return;
  }

  targetOrigin.value = 'custom';
  selectTarget({
    path: sanitized,
    name: sanitized.split('/').pop(),
    type: customPathType.value,
    size: null,
    exists: true,
  });
};

watch(customPathInput, () => {
  if (activeView.value === 'custom') {
    applyCustomPath();
  }
});

watch(customPathType, () => {
  if (activeView.value === 'custom' && customPathInput.value.trim()) {
    applyCustomPath();
  }
});


// Operation Ordering and Applicability
const FILE_OPERATIONS_ORDER = [
  FILES_OPERATIONS.READ,
  FILES_OPERATIONS.TAIL,
  FILES_OPERATIONS.SIZE,
  FILES_OPERATIONS.MODIFIED,
  FILES_OPERATIONS.PERMS,
  FILES_OPERATIONS.EXISTS,
];

const DIR_OPERATIONS_ORDER = [
  FILES_OPERATIONS.LIST,
  FILES_OPERATIONS.STATS,
  FILES_OPERATIONS.PERMS,
  FILES_OPERATIONS.SIZE,
  FILES_OPERATIONS.MODIFIED,
  FILES_OPERATIONS.IS_DIR,
  FILES_OPERATIONS.EXISTS,
];

const applicableOperations = computed(() => {
  const targetType = selectedTarget.value.type;
  const order = targetType === 'dir' ? DIR_OPERATIONS_ORDER : FILE_OPERATIONS_ORDER;
  return order
    .map((opId) => FILES_OPERATION_CONFIGS[opId])
    .filter(Boolean);
});

// Check if currently selected operation requires parameter configuration
const hasParameters = computed(() => {
  return [
    FILES_OPERATIONS.TAIL,
    FILES_OPERATIONS.READ,
    FILES_OPERATIONS.SIZE,
    FILES_OPERATIONS.MODIFIED,
    FILES_OPERATIONS.PERMS,
    FILES_OPERATIONS.STATS,
  ].includes(selectedOperation.value);
});

// Generated Elscript Expression
const generatedCode = computed(() => {
  const targetPath = selectedTarget.value.path;
  if (!targetPath) {
    return '';
  }
  const op = selectedOperation.value;

  const raw = generateFilesExpression({
    operation: op,
    path: targetPath,
    tailLines: parameters.value.tailLines,
    readKb: parameters.value.readKb,
    sizeProperty: parameters.value.sizeProperty,
    modifiedProperty: parameters.value.modifiedProperty,
    permsProperty: parameters.value.permsProperty,
    statsProperty: parameters.value.statsProperty,
  });

  return raw ? formatExpressionLab(raw) : '';
});

// CodeMirror Expression Preview Editor
const expressionEditorContainerRef = ref(null);
let expressionEditorView = null;

const destroyExpressionEditor = () => {
  if (expressionEditorView) {
    expressionEditorView.destroy();
    expressionEditorView = null;
  }
};

const initExpressionEditor = (container) => {
  destroyExpressionEditor();
  if (!container) return;

  const state = EditorState.create({
    doc: generatedCode.value || '',
    extensions: [
      elscript(),
      vsCodeLight,
      syntaxHighlighting(defaultHighlightStyle, { fallback: true }),
      bracketMatching(),
      EditorState.readOnly.of(true),
      EditorView.editable.of(false),
      EditorView.lineWrapping,
    ],
  });

  expressionEditorView = new EditorView({
    state,
    parent: container,
  });
};

watch(
  expressionEditorContainerRef,
  (container) => {
    if (container) {
      initExpressionEditor(container);
    } else {
      destroyExpressionEditor();
    }
  },
  { flush: 'post' }
);

watch(generatedCode, (newCode) => {
  if (expressionEditorView) {
    const currentDoc = expressionEditorView.state.doc.toString();
    if (currentDoc !== newCode) {
      expressionEditorView.dispatch({
        changes: { from: 0, to: currentDoc.length, insert: newCode || '' },
      });
    }
  }
});

onUnmounted(() => {
  destroyExpressionEditor();
  clearTimeout(copyTimer);
});

const isCodeEmpty = computed(() => !generatedCode.value);

// Dirty State Tracking: Only dirty if a target has been selected or custom path entered
const isDirty = computed(() => {
  return Boolean(selectedTarget.value.path || customPathInput.value.trim());
});

// Copy status
const isCopied = ref(false);

let copyTimer = null;

const copyCode = async () => {
  if (isCodeEmpty.value) return;
  try {
    if (navigator?.clipboard?.writeText) {
      await navigator.clipboard.writeText(generatedCode.value);
    }
    isCopied.value = true;
    clearTimeout(copyTimer);
    copyTimer = setTimeout(() => {
      isCopied.value = false;
      copyTimer = null;
    }, 2000);
  } catch {
    // fallback
  }
};

// Lifecycle & Dialogs
const handleClose = () => {
  uiStore.closeModal();
};

const resetState = () => {
  selectedTarget.value = {
    path: '',
    name: '',
    type: 'file',
    size: null,
    exists: true,
    description: '',
  };
  selectedOperation.value = FILES_OPERATIONS.READ;
  isManualOperation.value = false;
  parameters.value = {
    tailLines: null,
    readKb: null,
    sizeProperty: 'all',
    modifiedProperty: 'all',
    permsProperty: 'all',
    statsProperty: 'all',
  };
  customPathInput.value = '';
  customPathType.value = 'file';
  treeSearchQuery.value = '';
  expandedDirs.value = new Set(['wp-content', 'wp-content/themes', 'wp-content/plugins', 'wp-content/mu-plugins']);
  collapsedInSearch.value.clear();
  activeView.value = 'presets';
  activeMainTab.value = 'target';
  targetOrigin.value = null;
};

const handleReset = async () => {
  if (!isDirty.value) return;
  const confirmed = await uiStore.showDialog({
    type: 'confirm',
    title: __('Reset Filesystem Inspector?'),
    message: __('This will clear all configured file selections and parameters, restoring defaults. Are you sure?'),
    confirmText: __('Reset'),
    cancelText: __('Cancel'),
  });
  if (!confirmed) return;
  resetState();
};

const handleInsert = () => {
  if (isCodeEmpty.value) return;
  const code = generatedCode.value;
  uiStore.triggerSnippetInsert({
    code,
    mode: isScratchpad.value ? 'replace' : 'insert',
  });
  uiStore.closeModal();
};

const isRefreshing = computed(() => filesystemStore.loading);

const handleRefresh = async () => {
  if (isRefreshing.value) return;
  await filesystemStore.refreshFilesystem();
};

watch(
  isOpen,
  (val) => {
    if (val) {
      filesystemStore.loadFilesystem();
    }
  },
  { immediate: true }
);

</script>

<template>
  <div
    v-if="isOpen"
    id="filesystem-inspector-modal"
    class="modal-overlay"
    @click.self="closeOnOutsideClick ? handleClose() : null"
  >
    <div
      class="modal-container filesystem-inspector-modal-container"
      role="dialog"
      aria-modal="true"
      :aria-label="__('Filesystem Inspector')"
      spellcheck="false"
    >
      <!-- Modal Header -->
      <div class="modal-header filesystem-inspector-header">
        <div class="header-title-group">
          <h2>{{ __('Filesystem Inspector') }}</h2>
        </div>
        <button
          type="button"
          class="modal-close"
          :title="__('Close')"
          @click="handleClose"
        >
          <span class="codicon codicon-close" />
        </button>
      </div>

      <!-- Segmented Header Bar (Pills: Presets | File Tree | Custom Path) -->
      <div class="filesystem-inspector-header-bar">
        <div class="scope-pills-group">
          <button
            type="button"
            class="refresh-pill-btn"
            :class="{ 'is-loading': isRefreshing }"
            :disabled="isRefreshing"
            :title="__('Refresh filesystem cache')"
            @click="handleRefresh"
          >
            <span
              class="codicon"
              :class="isRefreshing ? 'codicon-loading codicon-modifier-spin' : 'codicon-refresh'"
            />
          </button>
          <div class="header-separator" />
          <button
            type="button"
            class="scope-pill-btn"
            :class="{ active: activeView === 'presets' }"
            @click="selectPill('presets')"
          >
            <span class="codicon codicon-star-empty pill-icon" />
            <span class="pill-label">{{ __('Presets') }}</span>
            <span
              v-if="selectedTarget.path && targetOrigin === 'presets'"
              class="tab-active-dot"
              :title="__('Selected target is from Presets')"
            />
          </button>

          <button
            type="button"
            class="scope-pill-btn"
            :class="{ active: activeView === 'tree' }"
            @click="selectPill('tree')"
          >
            <span class="codicon codicon-folder pill-icon" />
            <span class="pill-label">{{ __('File Tree') }}</span>
            <span
              v-if="selectedTarget.path && targetOrigin === 'tree'"
              class="tab-active-dot"
              :title="__('Selected target is from File Tree')"
            />
          </button>

          <button
            type="button"
            class="scope-pill-btn"
            :class="{ active: activeView === 'custom' }"
            @click="selectPill('custom')"
          >
            <span class="codicon codicon-rename pill-icon" />
            <span class="pill-label">{{ __('Custom Path') }}</span>
            <span
              v-if="selectedTarget.path && targetOrigin === 'custom'"
              class="tab-active-dot"
              :title="__('Selected target is from Custom Path')"
            />
          </button>
        </div>
      </div>

      <!-- Tabs Header (Target | Operation) -->
      <div class="filesystem-inspector-tabs-header">
        <button
          type="button"
          class="builder-tab-btn"
          :class="{ active: activeMainTab === 'target' }"
          @click="activeMainTab = 'target'"
        >
          <span>{{ __('Target') }}</span>
        </button>

        <button
          type="button"
          class="builder-tab-btn"
          :class="{ active: activeMainTab === 'operation' }"
          @click="activeMainTab = 'operation'"
        >
          <span>{{ __('Operation') }}</span>
        </button>
      </div>

      <!-- Modal Body Content -->
      <div class="modal-content filesystem-inspector-modal-content">
        <div class="filesystem-inspector-tab-panel">
          <!-- TAB 1: Target View (Dynamic based on active pill) -->
          <div
            v-if="activeMainTab === 'target'"
            class="tab-pane tab-target"
          >
            <!-- 1.1. Presets View -->
            <div
              v-if="activeView === 'presets'"
              class="pill-panel-section presets-section"
            >
              <div class="panel-section-header">
                <h4>{{ __('Common System Files & Shortcuts') }}</h4>
              </div>

              <!-- Loading State -->
              <div
                v-if="isLoading"
                class="presets-loading-state"
              >
                <div class="metro-spinner" />
                <span>{{ __('Loading filesystem metadata...') }}</span>
              </div>

              <!-- Error State -->
              <div
                v-else-if="loadError"
                class="presets-error-state"
              >
                <span class="codicon codicon-warning" />
                <span>{{ loadError }}</span>
                <button
                  type="button"
                  class="btn btn-default btn-sm fw-50px"
                  @click="filesystemStore.loadFilesystem(true)"
                >
                  {{ __('Retry') }}
                </button>
              </div>

              <!-- Presets Grid -->
              <div
                v-else
                class="presets-grid"
              >
                <div
                  v-for="preset in presetsList"
                  :key="preset.id"
                  class="preset-card"
                  :class="{
                    active: selectedTarget.path === preset.path && targetOrigin === 'presets',
                    'is-missing': !preset.exists,
                  }"
                  @click="selectPreset(preset)"
                >
                  <span
                    class="preset-type-icon codicon"
                    :class="preset.type === 'dir' ? 'codicon-folder' : 'codicon-file'"
                  />
                  <span>
                    <strong>{{ preset.name }}</strong>
                    <small :title="preset.path">&lrm;{{ preset.path }}&lrm;</small>
                  </span>
                  <span
                    v-if="!preset.exists"
                    class="preset-status-badge missing"
                  >
                    {{ __('Not found') }}
                  </span>
                </div>
              </div>
            </div>

            <!-- 1.2. File Tree View -->
            <div
              v-else-if="activeView === 'tree'"
              class="pill-panel-section tree-section"
            >
              <div class="panel-section-header">
                <h4>{{ __('WordPress Filesystem Tree') }}</h4>
                <div class="tree-search-wrapper">
                  <span class="codicon codicon-search search-icon" />
                  <input
                    v-model="treeSearchQuery"
                    type="text"
                    class="form-control tree-search-input"
                    :placeholder="__('Filter files and folders...')"
                    spellcheck="false"
                    autocomplete="off"
                    autocapitalize="off"
                    autocorrect="off"
                  >
                  <button
                    v-if="treeSearchQuery"
                    type="button"
                    class="clear-search-btn"
                    @click="treeSearchQuery = ''"
                  >
                    <span class="codicon codicon-close" />
                  </button>
                </div>
              </div>

              <!-- Tree Container -->
              <div class="filesystem-tree-container">
                <!-- Sticky Column Headers -->
                <div
                  v-if="!isLoading && filteredTree.length > 0"
                  class="tree-columns-header"
                >
                  <span class="header-col header-name-col">{{ __('Name') }}</span>
                  <div class="header-meta-cols">
                    <span class="header-col header-size-col">{{ __('Size') }}</span>
                    <span class="header-col header-owner-col">{{ __('User:Group') }}</span>
                    <span class="header-col header-perms-col">{{ __('Perms') }}</span>
                  </div>
                </div>

                <div
                  v-if="isLoading"
                  class="tree-loading-state"
                >
                  <div class="metro-spinner" />
                  <span>{{ __('Scanning filesystem tree...') }}</span>
                </div>

                <div
                  v-else-if="filteredTree.length === 0"
                  class="tree-empty-state"
                >
                  {{ __('No matching files or directories found.') }}
                </div>

                <!-- Root Tree Nodes -->
                <ul
                  v-else
                  class="tree-node-list"
                >
                  <FilesystemTreeNode
                    v-for="node in filteredTree"
                    :key="node.path"
                    :node="node"
                    :selected-path="selectedTarget.path"
                    :target-origin="targetOrigin"
                    :is-expanded="isDirExpanded"
                    :depth="0"
                    @select="selectTreeNode"
                    @toggle-expand="toggleDirExpand"
                  />
                </ul>
              </div>
            </div>

            <!-- 1.3. Custom Path View -->
            <div
              v-else-if="activeView === 'custom'"
              class="pill-panel-section custom-path-section"
            >
              <div class="panel-section-header">
                <h4>{{ __('Manual Relative Path') }}</h4>
              </div>

              <div class="custom-path-form">
                <div class="form-row">
                  <div class="form-group flex-1">
                    <label for="custom-path-input-field">{{ __('Relative Path') }}</label>
                    <input
                      id="custom-path-input-field"
                      v-model="customPathInput"
                      type="text"
                      class="form-control font-monospace custom-path-input"
                      :placeholder="__('e.g. wp-content/debug.log or wp-content/plugins/my-plugin/config.json')"
                      maxlength="4096"
                      spellcheck="false"
                      autocomplete="off"
                      autocapitalize="off"
                      autocorrect="off"
                    >
                    <span class="form-help">{{ __('Enter any path relative to WordPress root (ABSPATH).') }}</span>
                  </div>
                </div>
              </div>

              <div class="panel-section-header">
                <h4>{{ __('Target Type') }}</h4>
              </div>

              <div class="terminal-action-group">
                <label
                  class="terminal-radio-label"
                  :class="{ active: customPathType === 'file' }"
                >
                  <input
                    v-model="customPathType"
                    type="radio"
                    value="file"
                  >
                  <span>
                    <strong>{{ __('File') }}</strong>
                    <small>{{ __('Enables file operations.') }}</small>
                  </span>
                </label>

                <label
                  class="terminal-radio-label"
                  :class="{ active: customPathType === 'dir' }"
                >
                  <input
                    v-model="customPathType"
                    type="radio"
                    value="dir"
                  >
                  <span>
                    <strong>{{ __('Directory') }}</strong>
                    <small>{{ __('Enables directory operations.') }}</small>
                  </span>
                </label>
              </div>
            </div>
          </div>

          <!-- TAB 2: Operation & Parameters View -->
          <div
            v-else-if="activeMainTab === 'operation'"
            class="tab-pane tab-operation"
          >
            <!-- Applicable Operations Section (Minimalist terminal-action-group layout) -->
            <div class="panel-section-header">
              <h4>{{ __('Applicable Operations') }}</h4>
            </div>

            <div class="terminal-action-group">
              <label
                v-for="opConfig in applicableOperations"
                :key="opConfig.id"
                class="terminal-radio-label"
                :class="{ active: selectedOperation === opConfig.id }"
                @click="selectOperation(opConfig.id)"
              >
                <input
                  v-model="selectedOperation"
                  type="radio"
                  :value="opConfig.id"
                  @change="selectOperation(opConfig.id)"
                >
                <span>
                  <strong>{{ opConfig.label }}</strong>
                  <small>{{ opConfig.shortDesc }}</small>
                </span>
              </label>
            </div>

            <!-- Parameters Section (Only if selected operation requires parameters) -->
            <div
              v-if="hasParameters"
              class="operation-parameters-section"
            >
              <div class="panel-section-header border-top">
                <h4>{{ __('Parameters') }}</h4>
              </div>

              <!-- Tail Parameters -->
              <div
                v-if="selectedOperation === FILES_OPERATIONS.TAIL"
                class="form-row"
              >
                <div class="form-group">
                  <label for="tail-lines-input">{{ __('Lines to Tail') }}</label>
                  <input
                    id="tail-lines-input"
                    v-model.number="parameters.tailLines"
                    type="number"
                    :min="MIN_TAIL_LINES"
                    :max="MAX_TAIL_LINES"
                    class="form-control"
                    :placeholder="DEFAULT_TAIL_LINES"
                    spellcheck="false"
                    autocomplete="off"
                  >
                  <span class="form-help">{{ __('Number of lines from end of file (1 - 500).') }}</span>
                </div>
              </div>

              <!-- Read Parameters -->
              <div
                v-else-if="selectedOperation === FILES_OPERATIONS.READ"
                class="form-row"
              >
                <div class="form-group">
                  <label for="read-kb-input">{{ __('Maximum Kilobytes to Read') }}</label>
                  <input
                    id="read-kb-input"
                    v-model.number="parameters.readKb"
                    type="number"
                    :min="FILES_MIN_READ_KB"
                    :max="FILES_MAX_READ_KB"
                    class="form-control"
                    :placeholder="FILES_MAX_READ_KB"
                    spellcheck="false"
                    autocomplete="off"
                  >
                  <span class="form-help">{{ __('Maximum kilobytes to read into memory (1 - 256 KB).') }}</span>
                </div>
              </div>

              <!-- Size Projections -->
              <div
                v-else-if="selectedOperation === FILES_OPERATIONS.SIZE"
                class="param-group"
              >
                <div class="form-group">
                  <div class="projection-radios">
                    <label
                      class="terminal-radio-label"
                      :class="{ active: parameters.sizeProperty === 'all' }"
                    >
                      <input
                        v-model="parameters.sizeProperty"
                        type="radio"
                        value="all"
                      >
                      <span>
                        <strong>{{ __('Full Array') }}</strong>
                        <small>{{ __('Byte count and formatted size string.') }}</small>
                      </span>
                    </label>

                    <label
                      class="terminal-radio-label"
                      :class="{ active: parameters.sizeProperty === 'human' }"
                    >
                      <input
                        v-model="parameters.sizeProperty"
                        type="radio"
                        value="human"
                      >
                      <span>
                        <strong>{{ __('Human-Readable String') }}</strong>
                        <small>{{ __('Formatted size string with binary units.') }}</small>
                      </span>
                    </label>

                    <label
                      class="terminal-radio-label"
                      :class="{ active: parameters.sizeProperty === 'bytes' }"
                    >
                      <input
                        v-model="parameters.sizeProperty"
                        type="radio"
                        value="bytes"
                      >
                      <span>
                        <strong>{{ __('Raw Bytes Count') }}</strong>
                        <small>{{ __('Numeric file size in raw bytes.') }}</small>
                      </span>
                    </label>
                  </div>
                </div>
              </div>

              <!-- Modified Projections -->
              <div
                v-else-if="selectedOperation === FILES_OPERATIONS.MODIFIED"
                class="param-group"
              >
                <div class="projection-radios">
                  <label
                    class="terminal-radio-label"
                    :class="{ active: parameters.modifiedProperty === 'all' }"
                  >
                    <input
                      v-model="parameters.modifiedProperty"
                      type="radio"
                      value="all"
                    >
                    <span>
                      <strong>{{ __('Full Array') }}</strong>
                      <small>{{ __('Unix timestamp and formatted UTC date.') }}</small>
                    </span>
                  </label>

                  <label
                    class="terminal-radio-label"
                    :class="{ active: parameters.modifiedProperty === 'formatted' }"
                  >
                    <input
                      v-model="parameters.modifiedProperty"
                      type="radio"
                      value="formatted"
                    >
                    <span>
                      <strong>{{ __('Formatted UTC String') }}</strong>
                      <small>{{ __('Human-readable UTC date and time.') }}</small>
                    </span>
                  </label>

                  <label
                    class="terminal-radio-label"
                    :class="{ active: parameters.modifiedProperty === 'timestamp' }"
                  >
                    <input
                      v-model="parameters.modifiedProperty"
                      type="radio"
                      value="timestamp"
                    >
                    <span>
                      <strong>{{ __('Unix Epoch Timestamp') }}</strong>
                      <small>{{ __('Numeric seconds since unix epoch.') }}</small>
                    </span>
                  </label>
                </div>
              </div>

              <!-- Perms Projections -->
              <div
                v-else-if="selectedOperation === FILES_OPERATIONS.PERMS"
                class="param-group"
              >
                <div class="projection-radios">
                  <label
                    class="terminal-radio-label"
                    :class="{ active: parameters.permsProperty === 'all' }"
                  >
                    <input
                      v-model="parameters.permsProperty"
                      type="radio"
                      value="all"
                    >
                    <span>
                      <strong>{{ __('Full Details') }}</strong>
                      <small>{{ __('Unix permissions and ownership.') }}</small>
                    </span>
                  </label>

                  <label
                    class="terminal-radio-label"
                    :class="{ active: parameters.permsProperty === 'octal' }"
                  >
                    <input
                      v-model="parameters.permsProperty"
                      type="radio"
                      value="octal"
                    >
                    <span>
                      <strong>{{ __('Octal Permissions') }}</strong>
                      <small>{{ __('Canonical octal permissions string.') }}</small>
                    </span>
                  </label>

                  <label
                    class="terminal-radio-label"
                    :class="{ active: parameters.permsProperty === 'symbolic' }"
                  >
                    <input
                      v-model="parameters.permsProperty"
                      type="radio"
                      value="symbolic"
                    >
                    <span>
                      <strong>{{ __('Symbolic Mode') }}</strong>
                      <small>{{ __('Standard Unix style permissions string.') }}</small>
                    </span>
                  </label>

                  <label
                    class="terminal-radio-label"
                    :class="{ active: parameters.permsProperty === 'owner' }"
                  >
                    <input
                      v-model="parameters.permsProperty"
                      type="radio"
                      value="owner"
                    >
                    <span>
                      <strong>{{ __('Owner Username') }}</strong>
                      <small>{{ __('Unix user name of the file owner.') }}</small>
                    </span>
                  </label>

                  <label
                    class="terminal-radio-label"
                    :class="{ active: parameters.permsProperty === 'group' }"
                  >
                    <input
                      v-model="parameters.permsProperty"
                      type="radio"
                      value="group"
                    >
                    <span>
                      <strong>{{ __('Group Name') }}</strong>
                      <small>{{ __('Unix group name of the file.') }}</small>
                    </span>
                  </label>
                </div>
              </div>

              <!-- Stats Projections -->
              <div
                v-else-if="selectedOperation === FILES_OPERATIONS.STATS"
                class="param-group"
              >
                <div class="form-group">
                  <div class="projection-radios">
                    <label
                      class="terminal-radio-label"
                      :class="{ active: parameters.statsProperty === 'all' }"
                    >
                      <input
                        v-model="parameters.statsProperty"
                        type="radio"
                        value="all"
                      >
                      <span>
                        <strong>{{ __('Full Summary with Charts') }}</strong>
                        <small>{{ __('Interactive summary and charts.') }}</small>
                      </span>
                    </label>

                    <label
                      class="terminal-radio-label"
                      :class="{ active: parameters.statsProperty === 'total_size' }"
                    >
                      <input
                        v-model="parameters.statsProperty"
                        type="radio"
                        value="total_size"
                      >
                      <span>
                        <strong>{{ __('Total Size String') }}</strong>
                        <small>{{ __('Human-readable total size.') }}</small>
                      </span>
                    </label>

                    <label
                      class="terminal-radio-label"
                      :class="{ active: parameters.statsProperty === 'files' }"
                    >
                      <input
                        v-model="parameters.statsProperty"
                        type="radio"
                        value="files"
                      >
                      <span>
                        <strong>{{ __('Total File Count') }}</strong>
                        <small>{{ __('Total number of files.') }}</small>
                      </span>
                    </label>

                    <label
                      class="terminal-radio-label"
                      :class="{ active: parameters.statsProperty === 'directories' }"
                    >
                      <input
                        v-model="parameters.statsProperty"
                        type="radio"
                        value="directories"
                      >
                      <span>
                        <strong>{{ __('Total Subdirectories Count') }}</strong>
                        <small>{{ __('Number of subdirectories.') }}</small>
                      </span>
                    </label>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Live Expression Preview Bar (Light Theme with CodeMirror 6) -->
      <div class="live-expression-bar">
        <div
          v-if="isCodeEmpty"
          class="builder-notice notice-info"
        >
          <span class="codicon codicon-info" />
          <p>{{ __('Please select a file or directory to preview the generated expression.') }}</p>
        </div>

        <template v-else>
          <div class="expression-preview-content">
            <span class="codicon codicon-chevron-right expression-icon" />
            <div
              ref="expressionEditorContainerRef"
              class="expression-code expression-code-cm-wrapper"
            />
          </div>
          <button
            type="button"
            class="btn btn-default copy-expression-btn"
            :title="__('Copy expression to clipboard')"
            @click="copyCode"
          >
            <span class="copy-label">{{ isCopied ? __('Copied!') : __('Copy code') }}</span>
          </button>
        </template>
      </div>

      <!-- Modal Footer -->
      <div class="modal-footer filesystem-inspector-footer">
        <button
          type="button"
          class="btn btn-default btn-reset fw-80px reset-btn"
          :disabled="!isDirty"
          @click="handleReset"
        >
          {{ __('Reset') }}
        </button>

        <div class="footer-actions-right">
          <button
            type="button"
            class="btn btn-default fw-80px"
            @click="handleClose"
          >
            {{ __('Cancel') }}
          </button>
          <button
            type="button"
            class="btn btn-primary fw-80px insert-btn"
            :disabled="isCodeEmpty"
            @click="handleInsert"
          >
            {{ isScratchpad ? __('Replace') : __('Insert') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
