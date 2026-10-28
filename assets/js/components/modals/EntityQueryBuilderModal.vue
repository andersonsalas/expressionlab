<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { useUiStore } from '../../stores/ui.js';
import { useTabsStore } from '../../stores/tabs.js';
import { __ } from '../../lib/helpers.js';
import {
  SUPPORTED_ENTITIES,
  ENTITY_CONFIGS,
  ENTITY_OPERATORS,
  normalizeOperator,
  getDuplicateColumnsInGroup,
  getDuplicateOrderColumns,
  generateElscriptEntityQuery,
  parseListValue,
  toMysqlDatetime,
  fromMysqlDatetime,
  toUnixTimestamp,
  fromUnixTimestamp
} from '../../lib/entity-builder-generator.js';
import { WP_TABLE_COLUMNS } from '../../lib/database-schema-constants.js';
import { EditorState } from '@codemirror/state';
import { EditorView } from '@codemirror/view';
import { bracketMatching, syntaxHighlighting, defaultHighlightStyle } from '@codemirror/language';
import { vsCodeLight } from '@fsegurai/codemirror-theme-bundle';
import { sql, SQLite } from '@codemirror/lang-sql';
import { linter } from '@codemirror/lint';
import { validateSql, createSqlLinter, formatSql } from '../../lib/sqlite-linter.js';
import { elscript } from '../../lib/codemirror/elscript.js';
import { formatExpressionLab } from '../../lib/codemirror/formatter.js';

defineProps({
  closeOnOutsideClick: {
    type: Boolean,
    default: false
  }
});

const uiStore = useUiStore();
const tabsStore = useTabsStore();

const isScratchpad = computed(() => tabsStore.activeTab?.mode === 'scratchpad');
const isOpen = computed(() => uiStore.activeModal === 'entity-builder');

// Selected Entity & Segmented Pills
const PRIMARY_ENTITIES = [
  { id: 'Users', label: 'Users', icon: 'dashicons-admin-users' },
  { id: 'Posts', label: 'Posts', icon: 'dashicons-admin-post' },
  { id: 'Media', label: 'Media', icon: 'dashicons-admin-media' }
];
const PRIMARY_ENTITY_IDS = PRIMARY_ENTITIES.map((e) => e.id);

const selectedEntity = ref('Users');
const isMoreDropdownOpen = ref(false);

const overflowEntities = computed(() => {
  return SUPPORTED_ENTITIES.filter((ent) => !PRIMARY_ENTITY_IDS.includes(ent));
});

const isOverflowSelected = computed(() => {
  return !PRIMARY_ENTITY_IDS.includes(selectedEntity.value);
});

const toggleMoreDropdown = () => {
  isMoreDropdownOpen.value = !isMoreDropdownOpen.value;
};

const selectEntity = (ent) => {
  if (!entityDrafts.value[ent]) {
    entityDrafts.value[ent] = createDefaultEntityDraft();
  }
  selectedEntity.value = ent;
  isMoreDropdownOpen.value = false;
};

// Active tab
const activeTab = ref('filters'); // 'filters', 'sorting', 'terminal', 'preview'

// Filter row factory
const createDefaultFilterRow = () => ({
  enabled: true,
  column: '',
  operator: '=',
  valueType: 'text',
  value: '',
  valueBetweenMin: '',
  valueBetweenMax: ''
});

// Entity draft factory
const createDefaultEntityDraft = () => ({
  filters: [
    [createDefaultFilterRow()]
  ],
  orderBys: [
    { column: '', direction: 'ASC' }
  ],
  limit: null,
  offset: null,
  terminal: {
    action: 'get', // 'get' | 'count' | 'to_sql' | 'query'
    isCountSql: false,
    querySql: ''
  }
});

// Drafts cache by entity
const entityDrafts = ref({
  Users: createDefaultEntityDraft(),
  Posts: createDefaultEntityDraft(),
  Media: createDefaultEntityDraft()
});

const currentDraft = computed(() => {
  return entityDrafts.value[selectedEntity.value] || entityDrafts.value.Users;
});

const availableColumns = computed(() => {
  const table = ENTITY_CONFIGS[selectedEntity.value]?.table || 'users';
  return WP_TABLE_COLUMNS[table] || [];
});

// Filter helper counts
const activeFiltersCount = computed(() => {
  let count = 0;
  for (const group of currentDraft.value.filters) {
    if (!Array.isArray(group)) continue;
    for (const f of group) {
      if (f && f.enabled && f.column.trim()) {
        count++;
      }
    }
  }
  return count;
});

// Has active sorting/pagination
const hasActiveSorting = computed(() => {
  const hasOrders = currentDraft.value.orderBys.some((o) => o && o.column && o.column.trim());
  const hasLimit = currentDraft.value.limit !== null && currentDraft.value.limit !== '' && Number(currentDraft.value.limit) > 0;
  const hasOffset = currentDraft.value.offset !== null && currentDraft.value.offset !== '' && Number(currentDraft.value.offset) > 0;
  return hasOrders || hasLimit || hasOffset;
});

// Has custom terminal action
const hasCustomTerminal = computed(() => {
  const term = currentDraft.value.terminal;
  return term.action !== 'get';
});

// Check if draft has any changes (isDirty)
const isDirty = computed(() => {
  if (activeFiltersCount.value > 0) return true;
  if (hasActiveSorting.value) return true;
  if (hasCustomTerminal.value) return true;
  if (currentDraft.value.filters.length > 1) return true;
  return false;
});

// Check for incomplete filter conditions
const hasIncompleteFilters = computed(() => {
  for (const group of currentDraft.value.filters) {
    if (!Array.isArray(group)) continue;
    for (const f of group) {
      if (!f || !f.enabled) continue;
      const col = (f.column || '').trim();
      if (col) {
        const normOp = normalizeOperator(f.operator);
        if (normOp === 'between') {
          const min = f.valueBetweenMin !== null && f.valueBetweenMin !== undefined ? String(f.valueBetweenMin).trim() : '';
          const max = f.valueBetweenMax !== null && f.valueBetweenMax !== undefined ? String(f.valueBetweenMax).trim() : '';
          if (min === '' || max === '') return true;
        } else if (normOp === 'in' || normOp === 'not in') {
          const list = parseListValue(f.value);
          if (!list || list.length === 0) return true;
        } else if (normOp !== 'is_null' && normOp !== 'is_not_null') {
          const val = f.value !== null && f.value !== undefined ? String(f.value).trim() : '';
          if (val === '') return true;
        }
      }
    }
  }
  return false;
});

// Check for duplicate columns in the same group
const hasDuplicateColumns = computed(() => {
  for (const group of currentDraft.value.filters) {
    if (!Array.isArray(group)) continue;
    if (getDuplicateColumnsInGroup(group).length > 0) return true;
  }
  return false;
});

const hasDuplicateOrderColumns = computed(() => {
  return getDuplicateOrderColumns(currentDraft.value.orderBys).length > 0;
});

// Validation
const isEmpty = computed(() => {
  if (currentDraft.value.terminal.action === 'query') {
    if (!currentDraft.value.terminal.querySql || !currentDraft.value.terminal.querySql.trim()) {
      return true;
    }
    if (sqlError.value) {
      return true;
    }
  }
  if (hasIncompleteFilters.value) {
    return true;
  }
  return false;
});

const emptyNoticeMessage = computed(() => {
  if (currentDraft.value.terminal.action === 'query') {
    if (!currentDraft.value.terminal.querySql || !currentDraft.value.terminal.querySql.trim()) {
      return __('The SQL query is empty. Provide a valid SQLite query to generate a valid entity expression.');
    }
    if (sqlError.value) {
      return __('The SQL query contains syntax errors. Fix the errors to generate a valid entity expression.');
    }
  }
  if (hasIncompleteFilters.value) {
    return __('One or more filter conditions are incomplete. Specify all required values or remove empty filters.');
  }
  return __('The entity query configuration is empty or incomplete. Complete the required inputs to generate a valid expression.');
});

// Generated code
const generatedCode = computed(() => {
  const raw = generateElscriptEntityQuery({
    entity: selectedEntity.value,
    filterGroups: currentDraft.value.filters,
    orderBys: currentDraft.value.orderBys,
    limit: currentDraft.value.limit,
    offset: currentDraft.value.offset,
    terminal: currentDraft.value.terminal
  });
  return formatExpressionLab(raw, { indentUnit: 4 });
});

// Scroll helper
const tabPanelRef = ref(null);
let scrollTimeout = null;

const scrollTabPanelToBottom = (smooth = true) => {
  const el = tabPanelRef.value;
  if (!el) return;
  if (typeof el.scrollTo === 'function') {
    el.scrollTo({
      top: el.scrollHeight,
      behavior: smooth ? 'smooth' : 'auto'
    });
  } else {
    el.scrollTop = el.scrollHeight;
  }
};

const scrollToBottom = async (smooth = true) => {
  await nextTick();
  scrollTabPanelToBottom(smooth);

  if (scrollTimeout) {
    clearTimeout(scrollTimeout);
  }
  scrollTimeout = setTimeout(() => {
    scrollTabPanelToBottom(smooth);
    scrollTimeout = null;
  }, 60);
};

// Filter Row Operations
const addFilterRow = async (groupIndex) => {
  const group = currentDraft.value.filters[groupIndex];
  if (group) {
    group.push(createDefaultFilterRow());
  }
  const isLastGroup = groupIndex === undefined || groupIndex === currentDraft.value.filters.length - 1;
  if (isLastGroup) {
    await scrollToBottom();
  } else {
    await nextTick();
    const cards = tabPanelRef.value?.querySelectorAll('.filter-group-card');
    if (cards && cards[groupIndex] && typeof cards[groupIndex].scrollIntoView === 'function') {
      cards[groupIndex].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else {
      await scrollToBottom();
    }
  }
};

const removeFilterRow = (groupIndex, rowIndex) => {
  const group = currentDraft.value.filters[groupIndex];
  if (!group) return;

  if (group.length === 1) {
    if (currentDraft.value.filters.length > 1) {
      currentDraft.value.filters.splice(groupIndex, 1);
    } else {
      group[0] = createDefaultFilterRow();
    }
  } else {
    group.splice(rowIndex, 1);
  }
};

// OR Groups Operations
const addOrGroup = async () => {
  currentDraft.value.filters.push([createDefaultFilterRow()]);
  await scrollToBottom();
};

const removeOrGroup = (groupIndex) => {
  if (currentDraft.value.filters.length > 1) {
    currentDraft.value.filters.splice(groupIndex, 1);
  } else {
    currentDraft.value.filters = [[createDefaultFilterRow()]];
  }
};

// Sorting operations
const addOrderBy = async () => {
  currentDraft.value.orderBys.push({ column: '', direction: 'ASC' });
  await scrollToBottom();
};

const removeOrderBy = (index) => {
  if (currentDraft.value.orderBys.length === 1) {
    currentDraft.value.orderBys[0] = { column: '', direction: 'ASC' };
  } else {
    currentDraft.value.orderBys.splice(index, 1);
  }
};

// Value Type Switcher Dropdown
const activeTypeDropdownKey = ref(null);

const isTypeSwitcherAllowed = (filter) => {
  if (!filter) return false;
  const op = filter.operator;
  return ['=', '$ne', '$gt', '$gte', '$lt', '$lte', '$between'].includes(op);
};

const onFilterOperatorChange = (filter) => {
  if (!isTypeSwitcherAllowed(filter)) {
    filter.valueType = 'text';
    activeTypeDropdownKey.value = null;
  }
};

const getValueTypeBadge = (type) => {
  if (type === 'datetime') return 'Date';
  if (type === 'timestamp') return 'Time';
  return 'Txt';
};

const getValueTypeLabel = (type) => {
  if (type === 'datetime') return __('Datetime (datetime-local)');
  if (type === 'timestamp') return __('Timestamp (UNIX)');
  return __('Normal text');
};

const toggleTypeDropdown = (key) => {
  activeTypeDropdownKey.value = activeTypeDropdownKey.value === String(key) ? null : String(key);
};

const isTypeDropdownOpen = (key, fIdx) => {
  if (activeTypeDropdownKey.value === null) return false;
  return activeTypeDropdownKey.value === String(key) || activeTypeDropdownKey.value === String(fIdx);
};

const setFilterValueType = (filter, newType) => {
  if (!filter) return;
  const oldType = filter.valueType || 'text';
  if (oldType === newType) {
    activeTypeDropdownKey.value = null;
    return;
  }

  const isBetween = filter.operator === '$between';

  const convertValue = (val) => {
    if (val === null || val === undefined || val === '') return '';
    if (oldType === 'datetime' && newType === 'timestamp') {
      return toUnixTimestamp(val);
    }
    if (oldType === 'timestamp' && newType === 'datetime') {
      return toMysqlDatetime(fromUnixTimestamp(val));
    }
    if (oldType === 'text') {
      if (newType === 'datetime') {
        const parsed = toMysqlDatetime(fromMysqlDatetime(String(val)));
        return parsed || val;
      }
      if (newType === 'timestamp') {
        const parsed = toUnixTimestamp(val);
        return parsed !== '' ? parsed : val;
      }
    }
    if (newType === 'text') {
      return String(val);
    }
    return val;
  };

  if (isBetween) {
    filter.valueBetweenMin = convertValue(filter.valueBetweenMin);
    filter.valueBetweenMax = convertValue(filter.valueBetweenMax);
  } else {
    filter.value = convertValue(filter.value);
  }

  filter.valueType = newType;
  activeTypeDropdownKey.value = null;
};

// Bulk List Editor Mini-Modal (IN / NOT IN)
const listEditor = ref({
  isOpen: false,
  groupIndex: null,
  rowIndex: null,
  column: '',
  operator: '$in',
  text: '',
  trimLines: true,
  deduplicate: false
});

const listEditorTextareaRef = ref(null);

const listEditorTitle = computed(() => {
  const opLabel = listEditor.value.operator === '$notIn' ? 'NOT IN' : 'IN';
  const col = listEditor.value.column;
  return col ? `${col} (${opLabel})` : `List (${opLabel})`;
});

const openListEditor = (groupIndex, rowIndex, filter) => {
  let initialText = '';
  if (Array.isArray(filter.value)) {
    initialText = filter.value.join('\n');
  } else if (typeof filter.value === 'string') {
    const parsed = parseListValue(filter.value);
    initialText = parsed.join('\n');
  }

  listEditor.value = {
    isOpen: true,
    groupIndex,
    rowIndex,
    column: filter.column || '',
    operator: filter.operator,
    text: initialText,
    trimLines: true,
    deduplicate: false
  };

  nextTick(() => {
    listEditorTextareaRef.value?.focus();
  });
};

const closeListEditorModal = () => {
  listEditor.value.isOpen = false;
  listEditor.value.groupIndex = null;
  listEditor.value.rowIndex = null;
};

const listEditorItemsCount = computed(() => {
  if (!listEditor.value.text) return 0;
  const rawLines = listEditor.value.text.split(/[\r\n]+/);
  const items = listEditor.value.trimLines
    ? rawLines.map((l) => l.trim()).filter(Boolean)
    : rawLines.filter(Boolean);
  return listEditor.value.deduplicate ? new Set(items).size : items.length;
});

const applyListEditorModal = () => {
  const { groupIndex, rowIndex, text, trimLines, deduplicate } = listEditor.value;
  if (groupIndex === null || rowIndex === null) {
    closeListEditorModal();
    return;
  }

  const group = currentDraft.value.filters[groupIndex];
  if (!group || !group[rowIndex]) {
    closeListEditorModal();
    return;
  }

  const rawLines = text.split(/[\r\n]+/);
  let items = trimLines ? rawLines.map((l) => l.trim()).filter(Boolean) : rawLines.filter(Boolean);
  if (deduplicate) {
    items = Array.from(new Set(items));
  }

  group[rowIndex].value = items;
  closeListEditorModal();
};

// Document Click Listener (Close dropdowns)
const handleDocumentClick = (e) => {
  if (isMoreDropdownOpen.value && !e.target.closest('.entity-more-dropdown-wrapper')) {
    isMoreDropdownOpen.value = false;
  }
  if (!e.target.closest('.val-type-dropdown-wrapper')) {
    activeTypeDropdownKey.value = null;
  }
};

// SQL Editor in Terminal Tab
const sqlEditorContainerRef = ref(null);
let sqlEditorView = null;
let isInternalSqlUpdate = false;
const sqlError = ref(null);

const validateSqlQuery = (sql) => {
  if (!sql || !sql.trim()) {
    sqlError.value = null;
    return;
  }
  const result = validateSql(sql.trim());
  if (!result.isValid) {
    sqlError.value = result.error;
  } else {
    sqlError.value = null;
  }
};

const destroySqlEditor = () => {
  if (sqlEditorView) {
    sqlEditorView.destroy();
    sqlEditorView = null;
  }
};

const initSqlEditor = (container = sqlEditorContainerRef.value) => {
  destroySqlEditor();
  if (!container) return;

  const defaultSql = currentDraft.value.terminal.querySql || '';
  validateSqlQuery(defaultSql);

  const state = EditorState.create({
    doc: defaultSql,
    extensions: [
      sql({ dialect: SQLite }),
      createSqlLinter(linter),
      bracketMatching(),
      syntaxHighlighting(defaultHighlightStyle, { fallback: true }),
      vsCodeLight,
      EditorView.lineWrapping,
      EditorView.updateListener.of((update) => {
        if (update.docChanged) {
          const newDoc = update.state.doc.toString();
          isInternalSqlUpdate = true;
          currentDraft.value.terminal.querySql = newDoc;
          validateSqlQuery(newDoc);
          nextTick(() => {
            isInternalSqlUpdate = false;
          });
        }
      })
    ]
  });

  sqlEditorView = new EditorView({
    state,
    parent: container
  });
};

const formatSqlQuery = () => {
  const currentSql = sqlEditorView
    ? sqlEditorView.state.doc.toString()
    : currentDraft.value.terminal.querySql;
  if (!currentSql || !currentSql.trim()) return;

  const check = validateSql(currentSql);
  if (!check.isValid) {
    sqlError.value = check.error;
    return;
  }

  const formatted = formatSql(currentSql);
  currentDraft.value.terminal.querySql = formatted;
  validateSqlQuery(formatted);
  if (sqlEditorView) {
    const doc = sqlEditorView.state.doc.toString();
    if (doc !== formatted) {
      sqlEditorView.dispatch({
        changes: { from: 0, to: doc.length, insert: formatted }
      });
    }
  }
};

watch(
  sqlEditorContainerRef,
  (container) => {
    if (container && currentDraft.value.terminal.action === 'query') {
      initSqlEditor(container);
    } else {
      destroySqlEditor();
    }
  },
  { flush: 'post' }
);

watch(
  () => currentDraft.value.terminal.querySql,
  (newSql) => {
    validateSqlQuery(newSql);
    if (isInternalSqlUpdate) return;
    if (sqlEditorView) {
      const currentDoc = sqlEditorView.state.doc.toString();
      if (currentDoc !== newSql) {
        sqlEditorView.dispatch({
          changes: { from: 0, to: currentDoc.length, insert: newSql || '' }
        });
      }
    }
  }
);

// Code Preview Editor
const previewEditorContainerRef = ref(null);
let previewEditorView = null;
const isCopied = ref(false);

const destroyPreviewEditor = () => {
  if (previewEditorView) {
    previewEditorView.destroy();
    previewEditorView = null;
  }
};

const initPreviewEditor = (container = previewEditorContainerRef.value) => {
  destroyPreviewEditor();
  if (!container) return;

  const state = EditorState.create({
    doc: generatedCode.value || '',
    extensions: [
      elscript(),
      bracketMatching(),
      syntaxHighlighting(defaultHighlightStyle, { fallback: true }),
      vsCodeLight,
      EditorView.editable.of(false),
      EditorState.readOnly.of(true)
    ]
  });

  previewEditorView = new EditorView({
    state,
    parent: container
  });
};

// Copy code action
const copyCode = async () => {
  if (isEmpty.value) return;
  try {
    if (navigator?.clipboard?.writeText) {
      await navigator.clipboard.writeText(generatedCode.value);
    }
    isCopied.value = true;
    setTimeout(() => {
      isCopied.value = false;
    }, 2000);
  } catch {
    // fallback
  }
};

watch(
  previewEditorContainerRef,
  (container) => {
    if (container) {
      initPreviewEditor(container);
    } else {
      destroyPreviewEditor();
    }
  },
  { flush: 'post' }
);

watch(generatedCode, (newCode) => {
  if (previewEditorView) {
    const currentDoc = previewEditorView.state.doc.toString();
    if (currentDoc !== newCode) {
      previewEditorView.dispatch({
        changes: { from: 0, to: currentDoc.length, insert: newCode || '' }
      });
    }
  }
});

watch(selectedEntity, () => {
  if (sqlEditorView && currentDraft.value.terminal.action === 'query') {
    const defaultSql = currentDraft.value.terminal.querySql || '';
    validateSqlQuery(defaultSql);
    const currentDoc = sqlEditorView.state.doc.toString();
    if (currentDoc !== defaultSql) {
      sqlEditorView.dispatch({
        changes: { from: 0, to: currentDoc.length, insert: defaultSql }
      });
    }
  }
});

// Lifecycle & Dialogs
const handleClose = () => {
  uiStore.closeModal();
};

const resetState = () => {
  entityDrafts.value[selectedEntity.value] = createDefaultEntityDraft();
  isMoreDropdownOpen.value = false;
  sqlError.value = null;
  activeTypeDropdownKey.value = null;
  closeListEditorModal();
  destroySqlEditor();
};

const handleReset = async () => {
  if (!isDirty.value) return;
  const confirmed = await uiStore.showDialog({
    type: 'confirm',
    title: __('Reset Entity Query Builder?'),
    message: __('This will clear all configured filters, sorting, and options for the current entity, restoring it to defaults. Are you sure?'),
    confirmText: __('Reset'),
    cancelText: __('Cancel')
  });
  if (!confirmed) return;
  resetState();
};

const handleInsert = () => {
  if (isEmpty.value) return;
  const code = generatedCode.value;
  uiStore.triggerSnippetInsert({
    code,
    mode: 'insert'
  });
  uiStore.closeModal();
};

const handleReplace = () => {
  if (isEmpty.value) return;
  const code = generatedCode.value;
  uiStore.triggerSnippetInsert({
    code,
    mode: 'replace'
  });
  uiStore.closeModal();
};

onMounted(() => {
  document.addEventListener('click', handleDocumentClick);
});

onBeforeUnmount(() => {
  if (scrollTimeout) {
    clearTimeout(scrollTimeout);
    scrollTimeout = null;
  }
  document.removeEventListener('click', handleDocumentClick);
  isMoreDropdownOpen.value = false;
  destroySqlEditor();
  destroyPreviewEditor();
  closeListEditorModal();
});
</script>

<template>
  <div
    v-if="isOpen"
    id="entity-builder-modal"
    class="modal-overlay"
    @click.self="closeOnOutsideClick ? handleClose() : null"
  >
    <!-- Core tables columns datalist -->
    <datalist id="entity-core-columns">
      <option
        v-for="col in availableColumns"
        :key="col"
        :value="col"
      />
    </datalist>

    <div
      class="modal-container entity-builder-modal-container"
      role="dialog"
      aria-modal="true"
      :aria-label="__('Entity Query Builder')"
    >
      <!-- Modal Header -->
      <div class="modal-header entity-builder-header">
        <div class="header-title-group">
          <h2>{{ __('Entity Query Builder') }}</h2>
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

      <!-- Header Bar (Target Service Selector Pills) -->
      <div class="entity-builder-header-bar">
        <div
          class="entity-pills-group"
          role="radiogroup"
          :aria-label="__('Target Entity')"
        >
          <button
            v-for="ent in PRIMARY_ENTITIES"
            :key="ent.id"
            type="button"
            class="entity-pill-btn"
            :class="{ active: selectedEntity === ent.id }"
            role="radio"
            :aria-checked="selectedEntity === ent.id"
            :title="ent.label"
            @click="selectEntity(ent.id)"
          >
            <span :class="['dashicons', ent.icon, 'pill-icon']" />
            <span class="pill-label">{{ ent.label }}</span>
          </button>

          <!-- Polymorphic More Button & Dropdown -->
          <div
            ref="moreDropdownRef"
            class="entity-more-dropdown-wrapper"
          >
            <button
              type="button"
              class="entity-pill-btn entity-pill-more"
              :class="{
                active: isOverflowSelected,
                open: isMoreDropdownOpen
              }"
              role="button"
              :aria-haspopup="true"
              :aria-expanded="isMoreDropdownOpen"
              :title="isOverflowSelected ? selectedEntity : __('More entities...')"
              @click.stop="toggleMoreDropdown"
            >
              <span class="codicon codicon-layers pill-icon" />
              <span class="pill-label">{{ isOverflowSelected ? selectedEntity : __('More...') }}</span>
              <span class="codicon codicon-chevron-down pill-chevron" />
            </button>

            <div
              v-if="isMoreDropdownOpen"
              class="console-tab-context-menu entity-more-dropdown-menu"
              role="menu"
              @click.stop
            >
              <template v-if="overflowEntities.length > 0">
                <button
                  v-for="ent in overflowEntities"
                  :key="ent"
                  type="button"
                  class="tab-menu-item entity-option"
                  :class="{ active: selectedEntity === ent }"
                  role="menuitem"
                  @click="selectEntity(ent)"
                >
                  <span class="entity-name">{{ ent }}</span>
                  <span
                    v-if="selectedEntity === ent"
                    class="codicon codicon-check active-check"
                  />
                </button>
              </template>
              <div
                v-else
                class="tab-menu-item empty"
              >
                <span>{{ __('No additional entities') }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tabs Header -->
      <div class="entity-builder-tabs-header">
        <button
          type="button"
          class="builder-tab-btn"
          :class="{ active: activeTab === 'filters' }"
          @click="activeTab = 'filters'"
        >
          <span>{{ __('Conditions') }}</span>
          <span
            v-if="activeFiltersCount > 0"
            class="tab-count-badge"
          >
            {{ activeFiltersCount }}
          </span>
        </button>

        <button
          type="button"
          class="builder-tab-btn"
          :class="{ active: activeTab === 'sorting' }"
          @click="activeTab = 'sorting'"
        >
          <span>{{ __('Sorting & Pagination') }}</span>
          <span
            v-if="hasActiveSorting"
            class="tab-active-dot"
          />
        </button>

        <button
          type="button"
          class="builder-tab-btn"
          :class="{ active: activeTab === 'terminal' }"
          @click="activeTab = 'terminal'"
        >
          <span>{{ __('Terminal Action') }}</span>
          <span
            v-if="hasCustomTerminal"
            class="tab-active-dot"
          />
        </button>

        <button
          type="button"
          class="builder-tab-btn"
          :class="{ active: activeTab === 'preview' }"
          @click="activeTab = 'preview'"
        >
          <span>{{ __('Code Preview') }}</span>
        </button>
      </div>

      <!-- Modal Body -->
      <div class="modal-content entity-builder-modal-content">
        <div
          ref="tabPanelRef"
          class="entity-builder-tab-panel"
        >
          <!-- TAB 1: Conditions (DNF) -->
          <div
            v-if="activeTab === 'filters'"
            class="tab-pane tab-filters"
          >
            <div class="panel-section-header">
              <h4>{{ __('Conditions') }}</h4>
            </div>

            <div class="filter-groups-container">
              <div
                v-for="(group, gIdx) in currentDraft.filters"
                :key="gIdx"
                class="filter-group-wrapper"
              >
                <!-- Visual OR divider between groups -->
                <div
                  v-if="gIdx > 0"
                  class="or-group-divider"
                >
                  <div class="or-divider-line" />
                  <span class="or-badge">{{ __('OR') }}</span>
                  <div class="or-divider-line" />
                </div>

                <!-- Filter Group Card -->
                <div class="filter-group-card">
                  <div class="kv-table-container filter-table-container">
                    <table class="kv-table filter-table">
                      <thead>
                        <tr>
                          <th class="col-check" />
                          <th class="col-col">
                            {{ __('Column') }}
                          </th>
                          <th class="col-op">
                            {{ __('Operator') }}
                          </th>
                          <th class="col-val">
                            {{ __('Value') }}
                          </th>
                          <th class="col-actions" />
                        </tr>
                      </thead>
                      <tbody>
                        <tr
                          v-for="(filter, fIdx) in group"
                          :key="fIdx"
                          class="kv-row"
                          :class="{ disabled: !filter.enabled }"
                        >
                          <!-- Checkbox Enabled -->
                          <td class="col-check">
                            <input
                              v-model="filter.enabled"
                              type="checkbox"
                              class="kv-checkbox"
                            >
                          </td>

                          <!-- Column Name with datalist -->
                          <td class="col-col">
                            <input
                              v-model="filter.column"
                              type="text"
                              class="kv-input"
                              list="entity-core-columns"
                              :placeholder="__('Column name')"
                              spellcheck="false"
                            >
                          </td>

                          <!-- Operator -->
                          <td class="col-op">
                            <select
                              v-model="filter.operator"
                              class="kv-input kv-select"
                              @change="onFilterOperatorChange(filter)"
                            >
                              <option
                                v-for="op in ENTITY_OPERATORS"
                                :key="op.value"
                                :value="op.value"
                              >
                                {{ op.label }}
                              </option>
                            </select>
                          </td>

                          <!-- Value Field -->
                          <td class="col-val">
                            <!-- BETWEEN Range -->
                            <div
                              v-if="filter.operator === '$between'"
                              class="between-inputs-wrapper"
                            >
                              <template v-if="filter.valueType === 'datetime'">
                                <input
                                  :value="fromMysqlDatetime(filter.valueBetweenMin)"
                                  type="datetime-local"
                                  step="1"
                                  class="kv-input kv-between-from"
                                  @input="filter.valueBetweenMin = toMysqlDatetime($event.target.value)"
                                >
                                <span class="between-sep">{{ __('and') }}</span>
                                <input
                                  :value="fromMysqlDatetime(filter.valueBetweenMax)"
                                  type="datetime-local"
                                  step="1"
                                  class="kv-input kv-between-to"
                                  @input="filter.valueBetweenMax = toMysqlDatetime($event.target.value)"
                                >
                              </template>

                              <template v-else-if="filter.valueType === 'timestamp'">
                                <input
                                  :value="fromUnixTimestamp(filter.valueBetweenMin)"
                                  type="datetime-local"
                                  step="1"
                                  class="kv-input kv-between-from"
                                  @input="filter.valueBetweenMin = toUnixTimestamp($event.target.value)"
                                >
                                <span class="between-sep">{{ __('and') }}</span>
                                <input
                                  :value="fromUnixTimestamp(filter.valueBetweenMax)"
                                  type="datetime-local"
                                  step="1"
                                  class="kv-input kv-between-to"
                                  @input="filter.valueBetweenMax = toUnixTimestamp($event.target.value)"
                                >
                              </template>

                              <template v-else>
                                <input
                                  v-model="filter.valueBetweenMin"
                                  type="text"
                                  class="kv-input kv-between-from"
                                  :placeholder="__('Min value')"
                                  spellcheck="false"
                                >
                                <span class="between-sep">{{ __('and') }}</span>
                                <input
                                  v-model="filter.valueBetweenMax"
                                  type="text"
                                  class="kv-input kv-between-to"
                                  :placeholder="__('Max value')"
                                  spellcheck="false"
                                >
                              </template>

                              <!-- Type dropdown trigger & menu -->
                              <div
                                v-if="isTypeSwitcherAllowed(filter)"
                                class="val-type-dropdown-wrapper"
                              >
                                <button
                                  type="button"
                                  class="val-type-trigger-btn"
                                  :class="`type-${filter.valueType || 'text'}`"
                                  :title="__('Value type: ') + getValueTypeLabel(filter.valueType)"
                                  @click.stop="toggleTypeDropdown(`${gIdx}-${fIdx}`)"
                                >
                                  <span class="val-type-badge">{{ getValueTypeBadge(filter.valueType) }}</span>
                                  <span class="codicon codicon-chevron-down val-type-chevron" />
                                </button>

                                <div
                                  v-if="isTypeDropdownOpen(`${gIdx}-${fIdx}`, fIdx)"
                                  class="val-type-dropdown-menu"
                                  role="menu"
                                  @click.stop
                                >
                                  <button
                                    type="button"
                                    class="dropdown-item"
                                    :class="{ active: !filter.valueType || filter.valueType === 'text' }"
                                    role="menuitem"
                                    @click="setFilterValueType(filter, 'text')"
                                  >
                                    <span class="item-label">{{ __('Normal text') }}</span>
                                    <span
                                      v-if="!filter.valueType || filter.valueType === 'text'"
                                      class="codicon codicon-check active-check"
                                    />
                                  </button>
                                  <button
                                    type="button"
                                    class="dropdown-item"
                                    :class="{ active: filter.valueType === 'datetime' }"
                                    role="menuitem"
                                    @click="setFilterValueType(filter, 'datetime')"
                                  >
                                    <span class="item-label">{{ __('Datetime') }}</span>
                                    <span
                                      v-if="filter.valueType === 'datetime'"
                                      class="codicon codicon-check active-check"
                                    />
                                  </button>
                                  <button
                                    type="button"
                                    class="dropdown-item"
                                    :class="{ active: filter.valueType === 'timestamp' }"
                                    role="menuitem"
                                    @click="setFilterValueType(filter, 'timestamp')"
                                  >
                                    <span class="item-label">{{ __('Timestamp') }}</span>
                                    <span
                                      v-if="filter.valueType === 'timestamp'"
                                      class="codicon codicon-check active-check"
                                    />
                                  </button>
                                </div>
                              </div>
                            </div>

                            <!-- Standard Single Input (Text, Datetime, Timestamp) -->
                            <div
                              v-else
                              class="val-input-group"
                            >
                              <input
                                v-if="filter.valueType === 'datetime'"
                                :value="fromMysqlDatetime(filter.value)"
                                type="datetime-local"
                                step="1"
                                class="kv-input val-input"
                                @input="filter.value = toMysqlDatetime($event.target.value)"
                              >
                              <input
                                v-else-if="filter.valueType === 'timestamp'"
                                :value="fromUnixTimestamp(filter.value)"
                                type="datetime-local"
                                step="1"
                                class="kv-input val-input"
                                @input="filter.value = toUnixTimestamp($event.target.value)"
                              >
                              <input
                                v-else
                                :value="Array.isArray(filter.value) ? filter.value.join(', ') : filter.value"
                                type="text"
                                class="kv-input val-input"
                                :placeholder="['$in', '$notIn'].includes(filter.operator) ? __('1, 2, 3 or \'draft\', \'publish\'') : __('Value')"
                                spellcheck="false"
                                @input="filter.value = $event.target.value"
                              >

                              <!-- Bulk List Editor Trigger for IN / NOT IN -->
                              <button
                                v-if="['$in', '$notIn'].includes(filter.operator)"
                                type="button"
                                class="btn-list-editor-trigger"
                                :title="__('Edit items in bulk (multiline list)')"
                                @click.stop="openListEditor(gIdx, fIdx, filter)"
                              >
                                <span class="codicon codicon-ellipsis" />
                              </button>

                              <!-- Type dropdown trigger & menu -->
                              <div
                                v-if="isTypeSwitcherAllowed(filter)"
                                class="val-type-dropdown-wrapper"
                              >
                                <button
                                  type="button"
                                  class="val-type-trigger-btn"
                                  :class="`type-${filter.valueType || 'text'}`"
                                  :title="__('Value type: ') + getValueTypeLabel(filter.valueType)"
                                  @click.stop="toggleTypeDropdown(`${gIdx}-${fIdx}`)"
                                >
                                  <span class="val-type-badge">{{ getValueTypeBadge(filter.valueType) }}</span>
                                  <span class="codicon codicon-chevron-down val-type-chevron" />
                                </button>

                                <div
                                  v-if="isTypeDropdownOpen(`${gIdx}-${fIdx}`, fIdx)"
                                  class="val-type-dropdown-menu"
                                  role="menu"
                                  @click.stop
                                >
                                  <button
                                    type="button"
                                    class="dropdown-item"
                                    :class="{ active: !filter.valueType || filter.valueType === 'text' }"
                                    role="menuitem"
                                    @click="setFilterValueType(filter, 'text')"
                                  >
                                    <span class="item-label">{{ __('Normal text') }}</span>
                                    <span
                                      v-if="!filter.valueType || filter.valueType === 'text'"
                                      class="codicon codicon-check active-check"
                                    />
                                  </button>
                                  <button
                                    type="button"
                                    class="dropdown-item"
                                    :class="{ active: filter.valueType === 'datetime' }"
                                    role="menuitem"
                                    @click="setFilterValueType(filter, 'datetime')"
                                  >
                                    <span class="item-label">{{ __('Datetime') }}</span>
                                    <span
                                      v-if="filter.valueType === 'datetime'"
                                      class="codicon codicon-check active-check"
                                    />
                                  </button>
                                  <button
                                    type="button"
                                    class="dropdown-item"
                                    :class="{ active: filter.valueType === 'timestamp' }"
                                    role="menuitem"
                                    @click="setFilterValueType(filter, 'timestamp')"
                                  >
                                    <span class="item-label">{{ __('Timestamp') }}</span>
                                    <span
                                      v-if="filter.valueType === 'timestamp'"
                                      class="codicon codicon-check active-check"
                                    />
                                  </button>
                                </div>
                              </div>
                            </div>
                          </td>

                          <!-- Actions -->
                          <td class="col-actions">
                            <button
                              type="button"
                              class="btn-row-action btn-row-delete"
                              :title="__('Remove row')"
                              @click="removeFilterRow(gIdx, fIdx)"
                            >
                              <span class="codicon codicon-trash" />
                            </button>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>

                  <!-- Duplicate Columns Banner -->
                  <div
                    v-if="getDuplicateColumnsInGroup(group).length > 0"
                    class="builder-notice notice-warning filter-group-warning"
                  >
                    <span class="codicon codicon-warning" />
                    <p>
                      {{ __('Duplicate column detected. Conditions on the same column in an AND group overwrite each other. Use IN / NOT IN, BETWEEN, or add an OR Group instead.') }}
                    </p>
                  </div>

                  <!-- Table Actions: Remove Group & Add Filter placed below the group table on the right -->
                  <div class="table-actions-bar group-actions-bar">
                    <button
                      v-if="currentDraft.filters.length > 1"
                      type="button"
                      class="btn btn-default btn-remove-group"
                      :title="__('Remove condition group')"
                      @click="removeOrGroup(gIdx)"
                    >
                      <span class="codicon codicon-trash" />
                      <span>{{ __('Remove Group') }}</span>
                    </button>
                    <button
                      type="button"
                      class="btn btn-default btn-add-filter"
                      @click="addFilterRow(gIdx)"
                    >
                      {{ __('Add Filter') }}
                    </button>
                  </div>
                </div>
              </div>

              <!-- Below all groups: Divider line and Add Group button -->
              <div class="or-group-divider or-divider-simple">
                <div class="or-divider-line" />
              </div>

              <!-- Add OR Group Button placed on the right -->
              <div class="or-groups-bottom-actions">
                <button
                  type="button"
                  class="btn btn-default add-or-group-btn"
                  @click="addOrGroup"
                >
                  <span>{{ __('Add OR Group') }}</span>
                </button>
              </div>
            </div>
          </div>

          <!-- TAB 2: Sorting & Pagination -->
          <div
            v-if="activeTab === 'sorting'"
            class="tab-pane tab-sorting"
          >
            <div class="panel-section-header">
              <h4>{{ __('Ordering') }}</h4>
            </div>

            <div class="kv-table-container filter-table-container">
              <table class="kv-table filter-table sorting-table">
                <thead>
                  <tr>
                    <th class="col-col">
                      {{ __('Column') }}
                    </th>
                    <th class="col-op">
                      {{ __('Direction') }}
                    </th>
                    <th class="col-actions" />
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(order, index) in currentDraft.orderBys"
                    :key="index"
                    class="kv-row"
                  >
                    <td class="col-col">
                      <input
                        v-model="order.column"
                        type="text"
                        class="kv-input"
                        list="entity-core-columns"
                        :placeholder="__('Column name')"
                        spellcheck="false"
                      >
                    </td>
                    <td class="col-op">
                      <select
                        v-model="order.direction"
                        class="kv-input kv-select"
                      >
                        <option value="ASC">
                          {{ __('Ascending (ASC)') }}
                        </option>
                        <option value="DESC">
                          {{ __('Descending (DESC)') }}
                        </option>
                      </select>
                    </td>
                    <td class="col-actions">
                      <button
                        type="button"
                        class="btn-row-action btn-row-delete"
                        :title="__('Delete sorting clause')"
                        @click="removeOrderBy(index)"
                      >
                        <span class="codicon codicon-trash" />
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Duplicate Sorting Columns Warning Banner -->
            <div
              v-if="hasDuplicateOrderColumns"
              class="builder-notice notice-warning filter-group-warning"
            >
              <span class="codicon codicon-warning" />
              <p>
                {{ __('Duplicate column detected in sorting clauses. Multiple order_by calls on the same column overwrite each other.') }}
              </p>
            </div>

            <!-- Add Sorting Clause aligned to the right -->
            <div class="table-actions-bar group-actions-bar">
              <button
                type="button"
                class="btn btn-default btn-add-sorting"
                @click="addOrderBy"
              >
                {{ __('Add Sorting Clause') }}
              </button>
            </div>

            <div class="panel-section-header margin-top-20 border-top">
              <h4>{{ __('Pagination') }}</h4>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>{{ __('Limit') }}</label>
                <input
                  v-model.number="currentDraft.limit"
                  type="number"
                  class="form-control"
                  placeholder="10"
                  min="1"
                >
                <span class="form-help">{{ __('Maximum number of records to return.') }}</span>
              </div>

              <div class="form-group">
                <label>{{ __('Offset') }}</label>
                <input
                  v-model.number="currentDraft.offset"
                  type="number"
                  class="form-control"
                  placeholder="0"
                  min="0"
                >
                <span class="form-help">{{ __('Number of records to skip.') }}</span>
              </div>
            </div>
          </div>

          <!-- TAB 3: Terminal Action -->
          <div
            v-if="activeTab === 'terminal'"
            class="tab-pane tab-terminal"
          >
            <div class="panel-section-header">
              <h4>{{ __('Query Execution Method') }}</h4>
            </div>

            <div class="terminal-action-group">
              <label
                class="terminal-radio-label"
                :class="{ active: currentDraft.terminal.action === 'get' }"
              >
                <input
                  v-model="currentDraft.terminal.action"
                  type="radio"
                  value="get"
                >
                <span>
                  <strong>{{ selectedEntity }}.get()</strong>
                  <small>{{ __('Retrieve records array.') }}</small>
                </span>
              </label>

              <label
                class="terminal-radio-label"
                :class="{ active: currentDraft.terminal.action === 'count' }"
              >
                <input
                  v-model="currentDraft.terminal.action"
                  type="radio"
                  value="count"
                >
                <span>
                  <strong>{{ selectedEntity }}.count()</strong>
                  <small>{{ __('Count matching records.') }}</small>
                </span>
              </label>

              <label
                class="terminal-radio-label"
                :class="{ active: currentDraft.terminal.action === 'to_sql' }"
              >
                <input
                  v-model="currentDraft.terminal.action"
                  type="radio"
                  value="to_sql"
                >
                <span>
                  <strong>{{ selectedEntity }}.to_sql()</strong>
                  <small>{{ __('Compile parameterized SQL.') }}</small>
                </span>
              </label>

              <label
                class="terminal-radio-label"
                :class="{ active: currentDraft.terminal.action === 'query' }"
              >
                <input
                  v-model="currentDraft.terminal.action"
                  type="radio"
                  value="query"
                >
                <span>
                  <strong>{{ selectedEntity }}.query(SQL)</strong>
                  <small>{{ __('Execute custom SQL query.') }}</small>
                </span>
              </label>
            </div>

            <!-- Options for to_sql -->
            <div
              v-if="currentDraft.terminal.action === 'to_sql'"
              class="to-sql-options-wrapper"
            >
              <div class="panel-section-header">
                <h4>{{ __('Options') }}</h4>
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label class="options-checkbox-label">
                    <input
                      v-model="currentDraft.terminal.isCountSql"
                      type="checkbox"
                    >
                    <span>{{ __('Compile as SELECT COUNT(*)') }}</span>
                  </label>
                  <span class="form-help">{{ __('Generates a query to count matching rows directly in MySQL instead of compiling full column selection.') }}</span>
                </div>
              </div>
            </div>

            <!-- Editor for .query(SQL) -->
            <div
              v-if="currentDraft.terminal.action === 'query'"
              class="query-editor-wrapper"
            >
              <div class="panel-section-header">
                <h4>{{ __('SQL Query') }}</h4>
              </div>
              <div
                ref="sqlEditorContainerRef"
                class="sql-editor-container"
                :class="{ 'has-error': Boolean(sqlError) }"
              />
              <div class="table-actions-bar sql-actions-bar">
                <div
                  v-if="sqlError"
                  class="sql-syntax-error"
                  :title="sqlError"
                >
                  <span class="codicon codicon-close sql-error-icon" />
                  <span class="sql-error-text">{{ sqlError }}</span>
                </div>
                <button
                  type="button"
                  class="btn btn-default format-sql-btn"
                  @click="formatSqlQuery"
                >
                  {{ __('Format SQL') }}
                </button>
              </div>
            </div>
          </div>

          <!-- TAB 4: Code Preview -->
          <div
            v-if="activeTab === 'preview'"
            class="tab-pane tab-preview"
          >
            <div class="panel-section-header">
              <h4>{{ __('Generated Expression') }}</h4>
            </div>

            <div
              v-if="isEmpty"
              class="builder-notice notice-info"
            >
              <span class="codicon codicon-info" />
              <p>{{ emptyNoticeMessage }}</p>
            </div>

            <template v-else>
              <div
                ref="previewEditorContainerRef"
                class="code-preview-cm-editor"
              />

              <div class="table-actions-bar">
                <button
                  type="button"
                  class="btn btn-default"
                  @click="copyCode"
                >
                  {{ isCopied ? __('Copied!') : __('Copy Code') }}
                </button>
              </div>
            </template>
          </div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="modal-footer entity-builder-footer">
        <button
          type="button"
          class="btn btn-default btn-reset fw-80px"
          :disabled="!isDirty"
          @click="handleReset"
        >
          {{ __('Reset') }}
        </button>

        <div class="footer-actions-right">
          <button
            type="button"
            class="btn btn-default fw-80px cancel-btn"
            @click="handleClose"
          >
            {{ __('Cancel') }}
          </button>
          <button
            type="button"
            class="btn fw-80px insert-btn"
            :class="isScratchpad ? 'btn-default' : 'btn-primary'"
            :disabled="isEmpty"
            @click="handleInsert"
          >
            {{ __('Insert') }}
          </button>
          <button
            v-if="isScratchpad"
            type="button"
            class="btn btn-primary fw-80px replace-btn"
            :disabled="isEmpty"
            @click="handleReplace"
          >
            {{ __('Replace') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Mini-Modal Bulk List Editor -->
    <div
      v-if="listEditor.isOpen"
      class="modal-overlay list-editor-modal-overlay"
      @keydown.esc.stop.prevent
    >
      <div
        class="modal-container list-editor-modal-container"
        role="dialog"
        aria-modal="true"
        :aria-label="__('Edit Values List')"
      >
        <div class="modal-header">
          <h2>{{ listEditorTitle }}</h2>
          <button
            type="button"
            class="modal-close"
            :title="__('Close')"
            @click="closeListEditorModal"
          >
            <span class="codicon codicon-close" />
          </button>
        </div>

        <div class="modal-content list-editor-modal-content">
          <p class="list-editor-help">
            {{ __('Enter or paste one value per line. Values with commas will be automatically handled.') }}
          </p>
          <textarea
            ref="listEditorTextareaRef"
            v-model="listEditor.text"
            class="list-editor-textarea"
            spellcheck="false"
            wrap="off"
            @keydown.ctrl.enter="applyListEditorModal"
            @keydown.meta.enter="applyListEditorModal"
            @keydown.esc.stop.prevent
          />
          <div class="list-editor-options-row">
            <label class="list-editor-option-label">
              <input
                v-model="listEditor.trimLines"
                type="checkbox"
              >
              <span>{{ __('Trim whitespace') }}</span>
            </label>
            <label class="list-editor-option-label">
              <input
                v-model="listEditor.deduplicate"
                type="checkbox"
              >
              <span>{{ __('Remove duplicates') }}</span>
            </label>
          </div>
        </div>

        <div class="modal-footer list-editor-modal-footer">
          <div class="list-editor-items-count">
            {{ listEditorItemsCount }} {{ listEditorItemsCount === 1 ? __('item') : __('items') }}
          </div>
          <button
            type="button"
            class="btn btn-default fw-80px"
            @click="closeListEditorModal"
          >
            {{ __('Cancel') }}
          </button>
          <button
            type="button"
            class="btn btn-primary fw-80px"
            @click="applyListEditorModal"
          >
            {{ __('Apply') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
