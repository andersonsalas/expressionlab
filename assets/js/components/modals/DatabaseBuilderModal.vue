<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { useUiStore } from '../../stores/ui.js';
import { useTabsStore } from '../../stores/tabs.js';
import { __ } from '../../lib/helpers.js';
import {
  generateElscriptDatabasePipeline,
  parseListValue,
  toMysqlDatetime,
  fromMysqlDatetime,
  toUnixTimestamp,
  fromUnixTimestamp
} from '../../lib/database-builder-generator.js';
import {
  WP_CORE_TABLES,
  getTableColumns,
  findSuggestedJoin
} from '../../lib/database-schema-constants.js';
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
const isOpen = computed(() => uiStore.activeModal === 'database-builder');

const OPERATORS = [
  { value: '=', label: '=' },
  { value: '$in', label: 'IN' },
  { value: '$notIn', label: 'NOT IN' },
  { value: '$like', label: 'LIKE' },
  { value: '$notLike', label: 'NOT LIKE' },
  { value: '$between', label: 'BETWEEN' },
  { value: '$gt', label: '>' },
  { value: '$gte', label: '>=' },
  { value: '$lt', label: '<' },
  { value: '$lte', label: '<=' },
  { value: '$ne', label: '!=' },
  { value: '$isNull', label: 'IS NULL' },
  { value: '$isNotNull', label: 'IS NOT NULL' },
  { value: '$join', label: 'JOIN' }
];

// Mode
const mode = ref('pipeline'); // 'pipeline' | 'count'

// Available tabs
const availableTabs = computed(() => {
  if (mode.value === 'count') {
    return ['table', 'filters', 'preview'];
  }
  return ['table', 'filters', 'options', 'buffer', 'terminal', 'preview'];
});

// Active step & tab
const activeStepIndex = ref(0);
const activeTab = ref('table'); // 'table', 'filters', 'options', 'buffer', 'terminal', 'preview'

// When operation mode changes, activate the first available tab
watch(mode, (newMode) => {
  activeTab.value = availableTabs.value[0] || 'table';
  if (newMode === 'pipeline') {
    nextTick(() => {
      checkPipelineOverflow();
      scrollToActiveStep();
    });
  }
});

// Step ID generator
let stepIdCounter = 1;

const createDefaultFilterRow = (defaultSource = '$prev', defaultCol = 'ID') => ({
  enabled: true,
  column: '',
  operator: '=',
  valueType: 'text',
  value: '',
  valueBetweenMin: '',
  valueBetweenMax: '',
  joinSource: defaultSource,
  joinCol: defaultCol,
  joinRef: `${defaultSource}.${defaultCol}`
});

const createDefaultStep = (table = '') => {
  stepIdCounter++;
  const defaultRow = createDefaultFilterRow();
  const stepObj = {
    id: stepIdCounter,
    table,
    as: '',
    filterGroups: [[defaultRow]],
    options: {
      select: '',
      limit: '',
      offset: '',
      orderbyCol: '',
      orderbyDir: 'ASC',
      prefix: '',
      disablePrefix: false
    }
  };
  Object.defineProperty(stepObj, 'filters', {
    get() {
      return this.filterGroups?.[0] || [];
    },
    set(val) {
      if (!this.filterGroups) this.filterGroups = [[]];
      this.filterGroups[0] = val;
    },
    enumerable: true,
    configurable: true
  });
  return stepObj;
};

// Initial pipeline state
const steps = ref([createDefaultStep('')]);

// Buffer state
const buffer = ref({
  enabled: false,
  rows: '',
  memory: '',
  phpOverhead: ''
});

// Terminal state
const terminal = ref({
  action: 'fetch', // 'fetch', 'flush', 'query'
  querySql: ''
});

// Direct Count state
const createDefaultCountConfig = () => {
  const cfg = {
    table: '',
    disablePrefix: false,
    filterGroups: [[createDefaultFilterRow('$prev', 'ID')]],
    prefix: ''
  };
  Object.defineProperty(cfg, 'filters', {
    get() {
      return this.filterGroups?.[0] || [];
    },
    set(val) {
      if (!this.filterGroups) this.filterGroups = [[]];
      this.filterGroups[0] = val;
    },
    enumerable: true,
    configurable: true
  });
  return cfg;
};

const countConfig = ref(createDefaultCountConfig());

const isCopied = ref(false);

// Active Step Helper
const currentStep = computed(() => {
  if (steps.value.length === 0) return null;
  const idx = Math.max(0, Math.min(activeStepIndex.value, steps.value.length - 1));
  return steps.value[idx];
});

// Column suggestions for current step
const currentStepColumns = computed(() => {
  if (mode.value === 'count') {
    return getTableColumns(countConfig.value.table);
  }
  return currentStep.value ? getTableColumns(currentStep.value.table) : [];
});

// Available In-site Join sources for current step (from previous steps)
const availableJoinSources = computed(() => {
  if (activeStepIndex.value === 0) return [];

  const prevStep = steps.value[activeStepIndex.value - 1];
  const prevTable = prevStep ? prevStep.table || 'prev' : 'prev';

  const sources = [
    {
      value: '$prev',
      label: `$prev (${prevTable})`,
      table: prevTable
    }
  ];

  for (let i = 0; i < activeStepIndex.value; i++) {
    const s = steps.value[i];
    const tableName = (s.table || '').trim();
    if (tableName) {
      sources.push({
        value: `$${tableName}`,
        label: `$${tableName} (Step ${i + 1})`,
        table: tableName
      });
    }
  }

  return sources;
});

// Join reference parsing and synchronization helpers
const parseJoinRef = (refStr) => {
  if (!refStr || typeof refStr !== 'string') return { source: '$prev', col: 'ID' };
  const dotIndex = refStr.indexOf('.');
  if (dotIndex === -1) return { source: refStr || '$prev', col: 'ID' };
  return {
    source: refStr.slice(0, dotIndex),
    col: refStr.slice(dotIndex + 1)
  };
};

const getFilterJoinSource = (filter) => {
  if (!filter.joinSource) {
    const { source } = parseJoinRef(filter.joinRef);
    filter.joinSource = source;
  }
  return filter.joinSource;
};

const getFilterJoinCol = (filter) => {
  if (filter.joinCol === undefined || filter.joinCol === null) {
    const { col } = parseJoinRef(filter.joinRef);
    filter.joinCol = col;
  }
  return filter.joinCol;
};

const onJoinSourceChange = (filter, newSource) => {
  filter.joinSource = newSource;
  const col = filter.joinCol !== undefined ? filter.joinCol : 'ID';
  filter.joinRef = `${newSource}.${col}`;
};

const onJoinColInput = (filter, newCol) => {
  filter.joinCol = newCol;
  const source = filter.joinSource || '$prev';
  filter.joinRef = `${source}.${newCol}`;
};

const getJoinColumnsForFilter = (filter) => {
  const source = getFilterJoinSource(filter);
  let tableName = '';

  if (source === '$prev') {
    const prevStep = steps.value[activeStepIndex.value - 1];
    tableName = prevStep?.table || '';
  } else if (source.startsWith('$')) {
    tableName = source.slice(1);
  }

  return getTableColumns(tableName);
};

// Auto-capping / Buffer warning detection
const maxExplicitLimit = computed(() => {
  let maxLim = 0;
  for (const s of steps.value) {
    const lim = parseInt(s.options?.limit, 10);
    if (!isNaN(lim) && lim > maxLim) {
      maxLim = lim;
    }
  }
  return maxLim;
});

const isBufferAutoRequired = computed(() => maxExplicitLimit.value > 1000);

const isOptionsModified = computed(() => {
  if (!currentStep.value || !currentStep.value.options) return false;
  const opt = currentStep.value.options;
  if (opt.select && String(opt.select).trim()) return true;
  if (opt.limit !== '' && opt.limit !== null && opt.limit !== undefined && String(opt.limit).trim() !== '') return true;
  if (opt.offset !== '' && opt.offset !== null && opt.offset !== undefined && String(opt.offset).trim() !== '') return true;
  if (opt.orderbyCol && String(opt.orderbyCol).trim()) return true;
  if (opt.orderbyDir && opt.orderbyDir !== 'ASC') return true;
  if (!opt.disablePrefix && opt.prefix && String(opt.prefix).trim()) return true;
  return false;
});

const isBufferModified = computed(() => {
  if (!buffer.value.enabled) return false;
  if (
    buffer.value.rows !== '' &&
    buffer.value.rows !== null &&
    buffer.value.rows !== undefined &&
    String(buffer.value.rows).trim() !== ''
  ) {
    const r = parseInt(buffer.value.rows, 10);
    if (!isNaN(r) && r > 0 && r !== 1000) return true;
  }
  if (
    buffer.value.memory !== '' &&
    buffer.value.memory !== null &&
    buffer.value.memory !== undefined &&
    String(buffer.value.memory).trim() !== ''
  ) {
    const m = parseInt(buffer.value.memory, 10);
    if (!isNaN(m) && m > 0 && m !== 50) return true;
  }
  if (
    buffer.value.phpOverhead !== '' &&
    buffer.value.phpOverhead !== null &&
    buffer.value.phpOverhead !== undefined &&
    String(buffer.value.phpOverhead).trim() !== ''
  ) {
    const o = parseFloat(buffer.value.phpOverhead);
    if (!isNaN(o) && o > 0 && o !== 3.5) return true;
  }
  return false;
});

// Detect duplicate tables without alias
const duplicateTableWarning = computed(() => {
  const tableCounts = {};
  for (const s of steps.value) {
    const t = (s.table || '').trim();
    if (t) {
      tableCounts[t] = (tableCounts[t] || 0) + 1;
    }
  }

  for (const s of steps.value) {
    const t = (s.table || '').trim();
    if (t && tableCounts[t] > 1 && !s.as?.trim()) {
      return true;
    }
  }
  return false;
});

const getDuplicateColumnsInGroup = (group) => {
  if (!Array.isArray(group)) return [];
  const counts = {};
  for (const f of group) {
    if (!f || !f.enabled) continue;
    const col = (f.column || '').trim().toLowerCase();
    if (!col) continue;
    counts[col] = (counts[col] || 0) + 1;
  }
  return Object.keys(counts).filter((col) => counts[col] > 1);
};

// Watch for table changes to auto-update join suggestions on downstream steps
watch(
  () => currentStep.value?.table,
  (newTable, oldTable) => {
    if (!newTable || newTable === oldTable) return;
    if (activeStepIndex.value === 0) return;

    const prevStep = steps.value[activeStepIndex.value - 1];
    if (!prevStep) return;

    const suggestion = findSuggestedJoin(newTable, prevStep.table);
    if (suggestion && currentStep.value) {
      const filters = currentStep.value.filters;
      if (filters.length === 1 && (!filters[0].column || filters[0].operator === '$join')) {
        filters[0].column = suggestion.foreignKey;
        filters[0].operator = '$join';
        filters[0].joinSource = '$prev';
        filters[0].joinCol = suggestion.targetKey;
        filters[0].joinRef = `$prev.${suggestion.targetKey}`;
      }
    }
  }
);

// Pipeline horizontal scroll handling
const pipelineScrollContainerRef = ref(null);
const isPipelineOverflowing = ref(false);
const canScrollPipelineLeft = ref(false);
const canScrollPipelineRight = ref(false);
let pipelineResizeObserver = null;
let addStepScrollTimeout = null;

const checkPipelineOverflow = () => {
  const el = pipelineScrollContainerRef.value;
  if (!el || el.clientWidth === 0) return;
  const scrollWidth = el.scrollWidth;
  const clientWidth = el.clientWidth;
  const scrollLeft = el.scrollLeft;

  isPipelineOverflowing.value = scrollWidth > clientWidth;
  canScrollPipelineLeft.value = scrollLeft > 2;
  canScrollPipelineRight.value = scrollLeft < scrollWidth - clientWidth - 2;
};

const handlePipelineScroll = () => {
  checkPipelineOverflow();
};

const scrollPipeline = (delta) => {
  const el = pipelineScrollContainerRef.value;
  if (el) {
    if (typeof el.scrollBy === 'function') {
      el.scrollBy({ left: delta, behavior: 'smooth' });
    } else {
      el.scrollLeft += delta;
    }
    setTimeout(checkPipelineOverflow, 150);
  }
};

const scrollToActiveStep = () => {
  nextTick(() => {
    const el = pipelineScrollContainerRef.value;
    if (!el) return;
    const activeItem = el.querySelector('.step-pill.active');
    if (activeItem && typeof activeItem.scrollIntoView === 'function') {
      activeItem.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
    }
    checkPipelineOverflow();
  });
};

const scrollPipelineToEnd = () => {
  const el = pipelineScrollContainerRef.value;
  if (!el) return;
  if (typeof el.scrollTo === 'function') {
    el.scrollTo({ left: el.scrollWidth, behavior: 'smooth' });
  } else {
    el.scrollLeft = el.scrollWidth;
  }
  checkPipelineOverflow();
};

const disconnectPipelineResizeObserver = () => {
  if (pipelineResizeObserver) {
    pipelineResizeObserver.disconnect();
    pipelineResizeObserver = null;
  }
};

watch(
  pipelineScrollContainerRef,
  (el) => {
    disconnectPipelineResizeObserver();
    if (el) {
      nextTick(() => {
        checkPipelineOverflow();
        if (typeof ResizeObserver !== 'undefined' && !pipelineResizeObserver) {
          pipelineResizeObserver = new ResizeObserver(() => {
            checkPipelineOverflow();
          });
          pipelineResizeObserver.observe(el);
        }
      });
    }
  },
  { flush: 'post' }
);

watch(activeStepIndex, () => {
  scrollToActiveStep();
});

watch(() => steps.value.length, () => {
  nextTick(checkPipelineOverflow);
});

// Pipeline Step Management
const addStep = () => {
  const newStep = createDefaultStep('');
  newStep.filters[0].operator = '$join';

  steps.value.push(newStep);
  activeStepIndex.value = steps.value.length - 1;
  activeTab.value = 'table';

  if (addStepScrollTimeout) {
    clearTimeout(addStepScrollTimeout);
  }
  addStepScrollTimeout = setTimeout(() => {
    scrollPipelineToEnd();
    addStepScrollTimeout = null;
  }, 60);
};

const removeStep = (index) => {
  if (steps.value.length <= 1) return;
  steps.value.splice(index, 1);
  if (activeStepIndex.value >= steps.value.length) {
    activeStepIndex.value = steps.value.length - 1;
  }
  nextTick(() => {
    checkPipelineOverflow();
    scrollToActiveStep();
  });
};

// Filter Groups Management
const activeFilterGroups = computed({
  get() {
    if (mode.value === 'count') {
      return countConfig.value.filterGroups || (countConfig.value.filters ? [countConfig.value.filters] : []);
    }
    if (!currentStep.value) return [];
    return currentStep.value.filterGroups || (currentStep.value.filters ? [currentStep.value.filters] : []);
  },
  set(newVal) {
    if (mode.value === 'count') {
      countConfig.value.filterGroups = newVal;
    } else if (currentStep.value) {
      currentStep.value.filterGroups = newVal;
    }
  }
});

const activeFilters = computed({
  get() {
    const groups = activeFilterGroups.value;
    return groups[0] || [];
  },
  set(newVal) {
    const groups = activeFilterGroups.value;
    if (groups.length === 0) {
      groups.push(newVal);
    } else {
      groups[0] = newVal;
    }
  }
});

const activeFiltersCount = computed(() => {
  let count = 0;
  for (const group of activeFilterGroups.value) {
    if (Array.isArray(group)) {
      for (const f of group) {
        if (f && f.enabled && (f.column || '').trim()) {
          count++;
        }
      }
    }
  }
  return count;
});

const addFilterRow = (groupIndex = 0) => {
  const groups = activeFilterGroups.value;
  if (!groups[groupIndex]) {
    groups[groupIndex] = [];
  }
  const defaultSource = availableJoinSources.value.length > 0 ? availableJoinSources.value[0].value : '$prev';
  const defaultCol = 'ID';
  groups[groupIndex].push(createDefaultFilterRow(defaultSource, defaultCol));
};

const removeFilterRow = (groupIndex, rowIndex) => {
  if (rowIndex === undefined) {
    rowIndex = groupIndex;
    groupIndex = 0;
  }
  const groups = activeFilterGroups.value;
  const group = groups[groupIndex];
  if (!group) return;

  if (group.length <= 1) {
    const defaultSource = availableJoinSources.value.length > 0 ? availableJoinSources.value[0].value : '$prev';
    const defaultCol = 'ID';
    group.splice(0, 1, createDefaultFilterRow(defaultSource, defaultCol));
  } else {
    group.splice(rowIndex, 1);
  }
};

const tabPanelRef = ref(null);
let addOrGroupScrollTimeout = null;

const scrollTabPanelToBottom = () => {
  const el = tabPanelRef.value;
  if (!el) return;
  if (typeof el.scrollTo === 'function') {
    el.scrollTo({ top: el.scrollHeight, behavior: 'smooth' });
  } else {
    el.scrollTop = el.scrollHeight;
  }
};

const addOrGroup = () => {
  const defaultSource = availableJoinSources.value.length > 0 ? availableJoinSources.value[0].value : '$prev';
  const defaultCol = 'ID';
  activeFilterGroups.value.push([createDefaultFilterRow(defaultSource, defaultCol)]);

  if (addOrGroupScrollTimeout) {
    clearTimeout(addOrGroupScrollTimeout);
  }
  addOrGroupScrollTimeout = setTimeout(() => {
    scrollTabPanelToBottom();
    addOrGroupScrollTimeout = null;
  }, 60);
};

const removeOrGroup = (groupIndex) => {
  const groups = activeFilterGroups.value;
  if (groups.length <= 1) return;
  groups.splice(groupIndex, 1);
};

// Bulk List Editor (IN / NOT IN)
const listEditor = ref({
  isOpen: false,
  targetFilter: null,
  column: '',
  text: '',
  trimLines: true,
  deduplicate: false
});
const listEditorTextareaRef = ref(null);

const listEditorItemsCount = computed(() => {
  if (!listEditor.value.text) return 0;
  const lines = listEditor.value.text.split('\n');
  let count = 0;
  for (const line of lines) {
    if (line.trim().length > 0) count++;
  }
  return count;
});

const listEditorTitle = computed(() => {
  const opLabel = listEditor.value.targetFilter?.operator === '$notIn' ? 'NOT IN' : 'IN';
  const col = listEditor.value.column;
  return col ? `${col} (${opLabel})` : `List (${opLabel})`;
});

const formatItemForCell = (item) => {
  if (item === null || item === undefined) return '';
  const str = String(item);
  if (str.includes('\\')) {
    return `'${str.replace(/\\/g, '\\\\').replace(/'/g, '\\\'')}'`;
  }
  if (str.includes(',') || str.includes('\n') || str.includes('\r') || str !== str.trim()) {
    if (!str.includes('\'')) {
      return `'${str}'`;
    }
    if (!str.includes('"')) {
      return `"${str}"`;
    }
    return `'${str.replace(/'/g, '\\\'')}'`;
  }
  return str;
};

const formatItemForEditor = (item) => {
  if (item === null) return 'null';
  if (item === undefined) return '';
  const str = String(item);
  if (typeof item === 'string' && /^(-?\d+(\.\d+)?|true|false|null)$/i.test(str.trim())) {
    return `'${str}'`;
  }
  return str;
};

const openListEditorModal = (filter) => {
  if (!filter) return;
  const rawVal = filter.value;
  const items = Array.isArray(rawVal) ? rawVal : parseListValue(String(rawVal || ''));
  const lines = items.map(formatItemForEditor);

  listEditor.value = {
    isOpen: true,
    targetFilter: filter,
    column: filter.column || '',
    text: lines.join('\n'),
    trimLines: true,
    deduplicate: false
  };

  nextTick(() => {
    if (listEditorTextareaRef.value) {
      listEditorTextareaRef.value.focus();
    }
  });
};

const closeListEditorModal = () => {
  listEditor.value.isOpen = false;
  listEditor.value.targetFilter = null;
  listEditor.value.text = '';
};

const applyListEditorModal = () => {
  const filter = listEditor.value.targetFilter;
  if (!filter) {
    closeListEditorModal();
    return;
  }

  const rawLines = listEditor.value.text.split('\n');
  const seen = new Set();
  const processedItems = [];

  for (let line of rawLines) {
    if (listEditor.value.trimLines) {
      line = line.trim();
    }
    if (line.length === 0) continue;

    if (listEditor.value.deduplicate) {
      if (seen.has(line)) continue;
      seen.add(line);
    }

    processedItems.push(formatItemForCell(line));
  }

  filter.value = processedItems.join(', ');
  closeListEditorModal();
};

// Filter Value Type Switcher & Context Menu
const activeTypeDropdownKey = ref(null);
const activeTypeDropdownIdx = activeTypeDropdownKey;

const isTypeSwitcherAllowed = (filter) => {
  if (!filter) return false;
  return !['$join', '$isNull', '$isNotNull', '$in', '$notIn'].includes(filter.operator);
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
  if (type === 'datetime') return 'Datetime (datetime-local)';
  if (type === 'timestamp') return 'Timestamp (UNIX)';
  return 'Normal text';
};

const toggleTypeDropdown = (key) => {
  activeTypeDropdownKey.value = activeTypeDropdownKey.value === String(key) ? null : String(key);
};

const isTypeDropdownOpen = (key, fIdx) => {
  if (activeTypeDropdownKey.value === null) return false;
  return activeTypeDropdownKey.value === String(key) || activeTypeDropdownKey.value === String(fIdx);
};

const setFilterValueType = (filter, newType) => {
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

const handleDocumentClick = (e) => {
  if (activeTypeDropdownKey.value !== null) {
    if (!e.target.closest('.val-type-dropdown-wrapper')) {
      activeTypeDropdownKey.value = null;
    }
  }
};

// Generated Elscript code
const generatedCode = computed(() => {
  const raw = generateElscriptDatabasePipeline({
    mode: mode.value,
    steps: steps.value,
    buffer: buffer.value,
    terminal: terminal.value,
    countConfig: countConfig.value
  });
  return formatExpressionLab(raw, { indent: '    ' });
});

// CodeMirror SQL Editor
const sqlEditorContainerRef = ref(null);
let sqlEditorView = null;
let isInternalSqlUpdate = false;
const sqlError = ref(null);

const validateSqlQuery = (text) => {
  const trimmed = (text || '').trim();
  if (!trimmed) {
    sqlError.value = null;
    return;
  }
  const result = validateSql(trimmed);
  if (!result.isValid) {
    sqlError.value = result.error;
  } else {
    sqlError.value = null;
  }
};

const formatSqlQuery = () => {
  const currentSql = sqlEditorView
    ? sqlEditorView.state.doc.toString()
    : terminal.value.querySql;
  if (!currentSql || !currentSql.trim()) return;

  const check = validateSql(currentSql);
  if (!check.isValid) {
    sqlError.value = check.error;
    return;
  }

  const formatted = formatSql(currentSql);
  terminal.value.querySql = formatted;
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

const destroySqlEditor = () => {
  if (sqlEditorView) {
    sqlEditorView.destroy();
    sqlEditorView = null;
  }
};

const initSqlEditor = (container) => {
  destroySqlEditor();
  if (!container) return;

  const defaultSql = terminal.value.querySql || '';
  validateSqlQuery(defaultSql);

  const state = EditorState.create({
    doc: defaultSql,
    extensions: [
      sql({ dialect: SQLite }),
      createSqlLinter(linter),
      vsCodeLight,
      syntaxHighlighting(defaultHighlightStyle, { fallback: true }),
      bracketMatching(),
      EditorView.lineWrapping,
      EditorView.updateListener.of((update) => {
        if (update.docChanged) {
          const newDoc = update.state.doc.toString();
          isInternalSqlUpdate = true;
          terminal.value.querySql = newDoc;
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

watch(
  sqlEditorContainerRef,
  (container) => {
    if (container && terminal.value.action === 'query') {
      initSqlEditor(container);
    } else {
      destroySqlEditor();
    }
  },
  { flush: 'post' }
);

watch(
  () => terminal.value.querySql,
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

// CodeMirror Preview Editor
const previewEditorContainerRef = ref(null);
let previewEditorView = null;

const destroyPreviewEditor = () => {
  if (previewEditorView) {
    previewEditorView.destroy();
    previewEditorView = null;
  }
};

const initPreviewEditor = (container) => {
  destroyPreviewEditor();
  if (!container) return;

  const state = EditorState.create({
    doc: generatedCode.value || '',
    extensions: [
      elscript(),
      vsCodeLight,
      syntaxHighlighting(defaultHighlightStyle, { fallback: true }),
      bracketMatching(),
      EditorView.lineWrapping,
      EditorState.readOnly.of(true),
      EditorView.editable.of(false)
    ]
  });

  previewEditorView = new EditorView({
    state,
    parent: container
  });
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

watch(isOpen, (open) => {
  if (open) {
    nextTick(() => {
      checkPipelineOverflow();
    });
  } else {
    disconnectPipelineResizeObserver();
    if (addStepScrollTimeout) {
      clearTimeout(addStepScrollTimeout);
      addStepScrollTimeout = null;
    }
    if (addOrGroupScrollTimeout) {
      clearTimeout(addOrGroupScrollTimeout);
      addOrGroupScrollTimeout = null;
    }
  }
});

// Validation
const isEmpty = computed(() => {
  if (mode.value === 'count') {
    return !countConfig.value.table.trim();
  }
  if (steps.value.length === 0) return true;
  if (steps.value.some((s) => !s.table.trim())) return true;
  if (terminal.value.action === 'query' && !terminal.value.querySql.trim()) return true;
  return false;
});

const emptyNoticeMessage = computed(() => {
  if (mode.value === 'count' || steps.value.some((s) => !s.table.trim())) {
    return __('The database query configuration is empty. Specify a table name to generate a valid database expression.');
  }
  if (terminal.value.action === 'query' && !terminal.value.querySql.trim()) {
    return __('The SQL query is empty. Provide a valid SQLite query to generate a valid database expression.');
  }
  return __('The database query configuration is empty. Specify a table name to generate a valid database expression.');
});

// Copy Code
const copyCode = async () => {
  if (isEmpty.value) return;
  try {
    await navigator.clipboard.writeText(generatedCode.value);
    isCopied.value = true;
    setTimeout(() => {
      isCopied.value = false;
    }, 2000);
  } catch {
    // fallback
  }
};

const isFilterGroupDirty = (group) => {
  if (!Array.isArray(group)) return false;
  return group.some((f) => (f.column && f.enabled) || (f.valueType && f.valueType !== 'text') || (f.operator && f.operator !== '=') || (f.value && String(f.value).trim() !== ''));
};

const isFilterGroupsDirty = (groups) => {
  if (!Array.isArray(groups)) return false;
  if (groups.length > 1) return true;
  return isFilterGroupDirty(groups[0]);
};

// Dirty State & Reset
const isPipelineDirty = computed(() => {
  if (steps.value.length > 1) return true;
  const s0 = steps.value[0];
  if (!s0) return false;
  if (s0.table.trim() !== '') return true;
  if (s0.as && s0.as.trim() !== '') return true;
  if (
    s0.options?.select ||
    s0.options?.limit ||
    s0.options?.offset ||
    s0.options?.orderbyCol ||
    s0.options?.disablePrefix ||
    (s0.options?.prefix && s0.options.prefix.trim() !== '') ||
    (s0.options?.orderbyDir && s0.options.orderbyDir !== 'ASC')
  ) return true;
  if (isFilterGroupsDirty(s0.filterGroups || [s0.filters])) return true;
  if (isBufferModified.value) return true;
  if (terminal.value.action !== 'fetch') return true;
  return false;
});

const isCountDirty = computed(() => {
  if (countConfig.value.table.trim() !== '') return true;
  if (countConfig.value.disablePrefix) return true;
  if (countConfig.value.prefix && countConfig.value.prefix.trim() !== '') return true;
  if (isFilterGroupsDirty(countConfig.value.filterGroups || [countConfig.value.filters])) return true;
  return false;
});

const isDirty = computed(() => {
  return isPipelineDirty.value || isCountDirty.value;
});

const resetState = () => {
  if (addStepScrollTimeout) {
    clearTimeout(addStepScrollTimeout);
    addStepScrollTimeout = null;
  }
  if (addOrGroupScrollTimeout) {
    clearTimeout(addOrGroupScrollTimeout);
    addOrGroupScrollTimeout = null;
  }
  mode.value = 'pipeline';
  activeStepIndex.value = 0;
  activeTab.value = 'table';
  stepIdCounter = 1;
  steps.value = [createDefaultStep('')];
  buffer.value = {
    enabled: false,
    rows: '',
    memory: '',
    phpOverhead: ''
  };
  terminal.value = {
    action: 'fetch',
    querySql: ''
  };
  sqlError.value = null;
  activeTypeDropdownKey.value = null;
  closeListEditorModal();
  countConfig.value = createDefaultCountConfig();
  destroySqlEditor();
  destroyPreviewEditor();
};

const handleClose = () => {
  uiStore.closeModal();
};

const handleReset = async () => {
  if (!isDirty.value) return;
  const confirmed = await uiStore.showDialog({
    type: 'confirm',
    title: __('Reset Query Builder?'),
    message: __('This will clear all configured tables, filters, and options, restoring the builder to its default state. Are you sure?'),
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
    mode: isScratchpad.value ? 'replace' : 'insert'
  });
  uiStore.closeModal();
};

onMounted(() => {
  document.addEventListener('click', handleDocumentClick);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', handleDocumentClick);
  disconnectPipelineResizeObserver();
  if (addStepScrollTimeout) {
    clearTimeout(addStepScrollTimeout);
    addStepScrollTimeout = null;
  }
  if (addOrGroupScrollTimeout) {
    clearTimeout(addOrGroupScrollTimeout);
    addOrGroupScrollTimeout = null;
  }
  destroySqlEditor();
  destroyPreviewEditor();
  closeListEditorModal();
});
</script>

<template>
  <div
    v-if="isOpen"
    id="database-builder-modal"
    class="modal-overlay"
    @click.self="closeOnOutsideClick ? handleClose() : null"
  >
    <!-- Datalist for WP tables -->
    <datalist id="wp-core-tables">
      <option
        v-for="tbl in WP_CORE_TABLES"
        :key="tbl"
        :value="tbl"
      />
    </datalist>

    <!-- Datalist for columns -->
    <datalist id="table-columns">
      <option
        v-for="col in currentStepColumns"
        :key="col"
        :value="col"
      />
    </datalist>

    <div class="modal-container database-builder-modal-container">
      <div class="modal-header">
        <h2>{{ __('Database Query Builder') }}</h2>
        <button
          type="button"
          class="modal-close"
          :title="__('Close')"
          @click="handleClose"
        >
          <span class="codicon codicon-close" />
        </button>
      </div>

      <div class="modal-content database-builder-modal-content">
        <!-- Header Bar: Mode Selector -->
        <div class="database-builder-header-bar">
          <div class="mode-toggle-group">
            <span class="mode-label">{{ __('Operation Mode:') }}</span>
            <label class="mode-radio-label">
              <input
                v-model="mode"
                type="radio"
                value="pipeline"
              >
              <span>{{ __('Mirror (Database.mirror)') }}</span>
            </label>
            <label class="mode-radio-label">
              <input
                v-model="mode"
                type="radio"
                value="count"
              >
              <span>{{ __('Count (Database.count)') }}</span>
            </label>
          </div>
        </div>

        <!-- Pipeline Bar (Steps) - Visible in Pipeline Mode -->
        <div
          v-if="mode === 'pipeline'"
          class="database-builder-pipeline-bar"
        >
          <!-- Left Scroll Button -->
          <button
            v-if="isPipelineOverflowing"
            type="button"
            class="pipeline-scroll-btn btn-scroll-left"
            :disabled="!canScrollPipelineLeft"
            :title="__('Scroll pipeline left')"
            @click="scrollPipeline(-140)"
          >
            <span class="codicon codicon-chevron-left" />
          </button>

          <!-- Scrollable Steps Container -->
          <div
            ref="pipelineScrollContainerRef"
            class="pipeline-scroll-wrapper"
            @scroll="handlePipelineScroll"
          >
            <div
              v-for="(step, idx) in steps"
              :key="step.id"
              class="step-pill"
              :class="{ active: idx === activeStepIndex }"
              @click="activeStepIndex = idx"
            >
              <span
                class="codicon step-icon"
                :class="idx === 0 ? 'codicon-table' : 'codicon-link'"
              />
              <span>{{ step.table || 'table' }}</span>
              <span
                v-if="step.as"
                class="step-alias"
              >({{ step.as }})</span>
              <button
                v-if="steps.length > 1"
                type="button"
                class="step-remove-btn"
                :title="__('Remove step')"
                @click.stop="removeStep(idx)"
              >
                <span class="codicon codicon-close" />
              </button>
            </div>

            <button
              type="button"
              class="add-step-btn"
              :title="__('Add In-site Join Step')"
              @click="addStep"
            >
              <span class="codicon codicon-add" />
              <span>{{ __('Add table') }}</span>
            </button>
          </div>

          <!-- Right Scroll Button -->
          <button
            v-if="isPipelineOverflowing"
            type="button"
            class="pipeline-scroll-btn btn-scroll-right"
            :disabled="!canScrollPipelineRight"
            :title="__('Scroll pipeline right')"
            @click="scrollPipeline(140)"
          >
            <span class="codicon codicon-chevron-right" />
          </button>
        </div>

        <!-- Tabs Header -->
        <div class="database-builder-tabs-header">
          <button
            type="button"
            class="builder-tab-btn"
            :class="{ active: activeTab === 'table' }"
            @click="activeTab = 'table'"
          >
            <span>{{ __('Table') }}</span>
          </button>

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
            v-if="mode === 'pipeline'"
            type="button"
            class="builder-tab-btn"
            :class="{ active: activeTab === 'options' }"
            @click="activeTab = 'options'"
          >
            <span>{{ __('Options') }}</span>
            <span
              v-if="isOptionsModified"
              class="tab-active-dot"
            />
          </button>

          <button
            v-if="mode === 'pipeline'"
            type="button"
            class="builder-tab-btn"
            :class="{ active: activeTab === 'buffer' }"
            @click="activeTab = 'buffer'"
          >
            <span>{{ __('Buffer Safety') }}</span>
            <span
              v-if="isBufferModified || isBufferAutoRequired"
              class="tab-active-dot"
            />
          </button>

          <button
            v-if="mode === 'pipeline'"
            type="button"
            class="builder-tab-btn"
            :class="{ active: activeTab === 'terminal' }"
            @click="activeTab = 'terminal'"
          >
            <span>{{ __('Result & SQL') }}</span>
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

        <!-- Tab Panels -->
        <div
          ref="tabPanelRef"
          class="database-builder-tab-panel"
        >
          <!-- Warning banner for duplicate tables without alias -->
          <div
            v-if="duplicateTableWarning"
            class="builder-notice notice-warning"
          >
            <span class="codicon codicon-warning" />
            <p>
              {{ __('Duplicate table detected. Calling mirror() twice on the same table overwrites it in SQLite unless a custom alias (\'as\') is assigned.') }}
            </p>
          </div>

          <!-- TAB 1: Table -->
          <div
            v-if="activeTab === 'table'"
            class="tab-pane tab-table"
          >
            <div class="panel-section-header">
              <h4>{{ __('Table') }}</h4>
            </div>

            <div
              class="form-row"
              style="min-height: 100px"
            >
              <div class="form-group">
                <label>{{ __('Table Name') }}</label>
                <input
                  v-if="mode === 'pipeline' && currentStep"
                  v-model="currentStep.table"
                  type="text"
                  class="form-control"
                  list="wp-core-tables"
                  placeholder="e.g. posts, postmeta, users"
                  spellcheck="false"
                >
                <input
                  v-else
                  v-model="countConfig.table"
                  type="text"
                  class="form-control"
                  list="wp-core-tables"
                  placeholder="e.g. posts, users"
                  spellcheck="false"
                >
                <span class="form-help">{{ __('Enter the table name (e.g. \'posts\', \'users\', or a custom table).') }}</span>
              </div>

              <div
                v-if="mode === 'pipeline' && currentStep"
                class="form-group"
              >
                <label>{{ __('SQLite Table Alias (\'as\')') }}</label>
                <input
                  v-model="currentStep.as"
                  type="text"
                  class="form-control"
                  :placeholder="currentStep.options.disablePrefix && currentStep.table.trim() ? `${currentStep.table.trim()} (${__('default')})` : 'e.g. my_posts, published_posts'"
                  spellcheck="false"
                >
                <span class="form-help">{{ currentStep.options.disablePrefix ? __('Optional. Defaults to the table name in SQLite so queries can reference it directly.') : __('Optional. Useful when mirroring the same table more than once, or to simplify your SQL queries.') }}</span>
              </div>

              <div
                v-if="mode === 'count'"
                class="form-group"
              >
                <label>{{ __('Table Prefix') }}</label>
                <input
                  v-model="countConfig.prefix"
                  type="text"
                  class="form-control"
                  :disabled="countConfig.disablePrefix"
                  :placeholder="countConfig.disablePrefix ? __('Prefix disabled') : __('Default ($wpdb->prefix)')"
                  spellcheck="false"
                >
                <span class="form-help">{{ countConfig.disablePrefix ? __('Prefix is disabled for this table.') : __('Optional. Override table prefix for multisite or external tables.') }}</span>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; cursor: pointer;">
                  <input
                    v-if="mode === 'pipeline' && currentStep"
                    v-model="currentStep.options.disablePrefix"
                    type="checkbox"
                  >
                  <input
                    v-else
                    v-model="countConfig.disablePrefix"
                    type="checkbox"
                  >
                  <span>{{ __('Disable table prefix') }}</span>
                </label>
                <span class="form-help">{{ __('Queries the exact table name in MySQL without prepending the WordPress database prefix.') }}</span>
              </div>
            </div>
          </div>

          <!-- TAB 2: Conditions -->
          <div
            v-if="activeTab === 'filters'"
            class="tab-pane tab-filters"
          >
            <div class="panel-section-header">
              <h4>{{ __('Conditions') }}</h4>
            </div>

            <div class="filter-groups-container">
              <div
                v-for="(group, gIdx) in activeFilterGroups"
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
                        >
                          <td class="col-check">
                            <input
                              v-model="filter.enabled"
                              type="checkbox"
                              class="kv-checkbox"
                            >
                          </td>
                          <td class="col-col">
                            <input
                              v-model="filter.column"
                              type="text"
                              class="kv-input"
                              list="table-columns"
                              :placeholder="__('Column name')"
                              spellcheck="false"
                            >
                          </td>
                          <td class="col-op">
                            <select
                              v-model="filter.operator"
                              class="kv-input kv-select"
                              @change="onFilterOperatorChange(filter)"
                            >
                              <option
                                v-for="op in OPERATORS"
                                :key="op.value"
                                :value="op.value"
                              >
                                {{ op.label }}
                              </option>
                            </select>
                          </td>
                          <td class="col-val">
                            <!-- In-Site Join compound selector -->
                            <div
                              v-if="filter.operator === '$join'"
                              class="join-compound-wrapper"
                            >
                              <template v-if="availableJoinSources.length > 0">
                                <select
                                  :value="getFilterJoinSource(filter)"
                                  class="kv-input kv-select join-source-select"
                                  @change="onJoinSourceChange(filter, $event.target.value)"
                                >
                                  <option
                                    v-for="src in availableJoinSources"
                                    :key="src.value"
                                    :value="src.value"
                                  >
                                    {{ src.label }}
                                  </option>
                                </select>

                                <span class="join-dot-sep">.</span>

                                <input
                                  :value="getFilterJoinCol(filter)"
                                  type="text"
                                  class="kv-input join-col-input"
                                  :list="`join-cols-${gIdx}-${fIdx}`"
                                  :placeholder="__('e.g. ID, post_author')"
                                  spellcheck="false"
                                  @input="onJoinColInput(filter, $event.target.value)"
                                >

                                <datalist :id="`join-cols-${gIdx}-${fIdx}`">
                                  <option
                                    v-for="c in getJoinColumnsForFilter(filter)"
                                    :key="c"
                                    :value="c"
                                  />
                                </datalist>
                              </template>

                              <input
                                v-else
                                v-model="filter.joinRef"
                                type="text"
                                class="kv-input"
                                placeholder="$prev.ID or $table.column"
                                spellcheck="false"
                              >
                            </div>

                            <!-- Between dual inputs -->
                            <div
                              v-else-if="filter.operator === '$between'"
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
                              <div class="val-type-dropdown-wrapper">
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

                            <!-- Null operators need no input -->
                            <div
                              v-else-if="filter.operator === '$isNull' || filter.operator === '$isNotNull'"
                              class="null-operator-hint"
                            >
                              {{ __('No value required (checks nullability)') }}
                            </div>

                            <!-- Standard value input -->
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
                                v-model="filter.value"
                                type="text"
                                class="kv-input val-input"
                                :placeholder="['$in', '$notIn'].includes(filter.operator) ? __('1, 2, 3 or \'draft\', \'publish\'') : __('Value')"
                                spellcheck="false"
                              >

                              <!-- Ellipsis button to open bulk list editor modal for IN / NOT IN -->
                              <button
                                v-if="['$in', '$notIn'].includes(filter.operator)"
                                type="button"
                                class="btn-list-editor-trigger"
                                :title="__('Edit items in bulk (multiline list)')"
                                @click.stop="openListEditorModal(filter)"
                              >
                                <span class="codicon codicon-ellipsis" />
                              </button>

                              <!-- Type dropdown trigger & menu (only for allowed scalar operators) -->
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

                  <!-- Warning banner for duplicate columns within the group -->
                  <div
                    v-if="getDuplicateColumnsInGroup(group).length > 0"
                    class="builder-notice notice-warning filter-group-warning"
                  >
                    <span class="codicon codicon-warning" />
                    <p>
                      {{ __('Duplicate column detected. Conditions on the same column in an AND group overwrite each other. Use IN / NOT IN, BETWEEN, or add an OR Group instead.') }}
                    </p>
                  </div>

                  <!-- Table Actions: Remove Group & Add Filter placed below the group table -->
                  <div class="table-actions-bar group-actions-bar">
                    <button
                      v-if="activeFilterGroups.length > 1"
                      type="button"
                      class="btn btn-sm btn-default btn-remove-group"
                      :title="__('Remove condition group')"
                      @click="removeOrGroup(gIdx)"
                    >
                      <span class="codicon codicon-trash" />
                      <span>{{ __('Remove Group') }}</span>
                    </button>
                    <button
                      type="button"
                      class="btn btn-sm btn-default"
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

              <div class="or-groups-bottom-actions">
                <button
                  type="button"
                  class="btn btn-sm btn-default add-or-group-btn"
                  @click="addOrGroup"
                >
                  <span>{{ __('Add OR Group') }}</span>
                </button>
              </div>
            </div>
          </div>

          <!-- TAB 3: Options -->
          <div
            v-if="mode === 'pipeline' && activeTab === 'options' && currentStep"
            class="tab-pane tab-options"
          >
            <div class="panel-section-header">
              <h4>{{ __('Options') }}</h4>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>{{ __('Select Columns') }}</label>
                <input
                  v-model="currentStep.options.select"
                  type="text"
                  class="form-control"
                  placeholder="* or comma separated (e.g. ID, post_title, post_date)"
                  spellcheck="false"
                >
                <span class="form-help">{{ __('Leave blank or * to retrieve all columns.') }}</span>
              </div>

              <div class="form-group">
                <label>{{ __('Table Prefix') }}</label>
                <input
                  v-model="currentStep.options.prefix"
                  type="text"
                  class="form-control"
                  :disabled="currentStep.options.disablePrefix"
                  :placeholder="currentStep.options.disablePrefix ? __('Prefix disabled by table setting') : __('Default ($wpdb->prefix)')"
                  spellcheck="false"
                >
                <span class="form-help">{{ currentStep.options.disablePrefix ? __('Prefix is disabled for this table in Table.') : __('Optional. Override table prefix for multisite or external tables.') }}</span>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>{{ __('Limit (Max rows)') }}</label>
                <input
                  v-model="currentStep.options.limit"
                  type="number"
                  class="form-control"
                  placeholder="e.g. 10, 50, 2000"
                >
                <span class="form-help">{{ __('Default is capped to in-memory buffer limit (1,000).') }}</span>
              </div>

              <div class="form-group">
                <label>{{ __('Offset') }}</label>
                <input
                  v-model="currentStep.options.offset"
                  type="number"
                  class="form-control"
                  placeholder="0"
                >
                <span class="form-help">{{ __('Number of rows to skip for pagination.') }}</span>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>{{ __('Order By Column') }}</label>
                <input
                  v-model="currentStep.options.orderbyCol"
                  type="text"
                  class="form-control"
                  list="table-columns"
                  placeholder="e.g. ID, post_date"
                  spellcheck="false"
                >
              </div>

              <div class="form-group">
                <label>{{ __('Order Direction') }}</label>
                <select
                  v-model="currentStep.options.orderbyDir"
                  class="form-control"
                >
                  <option value="ASC">
                    ASC (Ascending)
                  </option>
                  <option value="DESC">
                    DESC (Descending)
                  </option>
                </select>
              </div>
            </div>
          </div>

          <!-- TAB 4: Buffer Safety -->
          <div
            v-if="mode === 'pipeline' && activeTab === 'buffer'"
            class="tab-pane tab-buffer"
          >
            <div class="panel-section-header">
              <h4>{{ __('Buffer Safety') }}</h4>
            </div>

            <div class="buffer-safety">
              <div class="builder-notice notice-info">
                <span class="codicon codicon-info" />
                <p>
                  {{ __('Queries are limited to 1,000 rows by default to protect server memory. Expand the buffer below to process larger datasets.') }}
                </p>
              </div>

              <div style="margin-bottom: 16px;">
                <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; cursor: pointer;">
                  <input
                    v-model="buffer.enabled"
                    type="checkbox"
                  >
                  <span>{{ __('Custom Buffer Size') }}</span>
                  <span
                    v-if="isBufferAutoRequired"
                    class="auto-detected-badge"
                  >
                    {{ __('Auto-enabled (limit exceeds 1,000)') }}
                  </span>
                </label>
              </div>

              <div
                v-if="buffer.enabled"
                class="form-row"
              >
                <div class="form-group">
                  <label>{{ __('Max Total Rows') }}</label>
                  <input
                    v-model.number="buffer.rows"
                    type="number"
                    class="form-control"
                    placeholder="1000"
                  >
                  <span class="form-help">{{ __('Cumulative rows allowed in RAM across all mirrored tables.') }}</span>
                </div>

                <div class="form-group">
                  <label>{{ __('Max Memory Limit (MB)') }}</label>
                  <input
                    v-model.number="buffer.memory"
                    type="number"
                    class="form-control"
                    placeholder="50"
                  >
                  <span class="form-help">{{ __('Estimated PHP RAM usage guard in Megabytes.') }}</span>
                </div>

                <div class="form-group">
                  <label>{{ __('PHP Overhead Factor') }}</label>
                  <input
                    v-model.number="buffer.phpOverhead"
                    type="number"
                    step="0.5"
                    class="form-control"
                    placeholder="3.5"
                  >
                  <span class="form-help">{{ __('Multiplier heuristic for PHP in-memory structures.') }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- TAB 5: Result & SQL -->
          <div
            v-if="mode === 'pipeline' && activeTab === 'terminal'"
            class="tab-pane tab-query"
          >
            <div class="panel-section-header">
              <h4>{{ __('Result Action') }}</h4>
            </div>

            <div class="terminal-action-group">
              <label 
                class="terminal-radio-label"
                :class="{ 'active' : terminal.action === 'fetch' }"
              >
                <input
                  v-model="terminal.action"
                  type="radio"
                  value="fetch"
                >
                <span>
                  <strong>Database.fetch()</strong>
                  <small>
                    {{ __('Keep data in memory') }}
                  </small>
                </span>
              </label>

              <label 
                class="terminal-radio-label"
                :class="{ 'active' : terminal.action === 'flush' }"
              >
                <input
                  v-model="terminal.action"
                  type="radio"
                  value="flush"
                >
                <span>
                  <strong>Database.flush()</strong>
                  <small>
                    {{ __('Free memory after retrieval') }}
                  </small>
                </span>
              </label>

              <label 
                class="terminal-radio-label"
                :class="{ 'active' : terminal.action === 'query' }"
              >
                <input
                  v-model="terminal.action"
                  type="radio"
                  value="query"
                >
                <span>
                  <strong>Database.query(SQL)</strong>
                  <small>
                    {{ __('Execute custom SQLite query') }}
                  </small>
                </span>
              </label>
            </div>

            <div
              v-if="terminal.action === 'query'"
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
                  class="btn btn-sm btn-default format-sql-btn"
                  @click="formatSqlQuery"
                >
                  {{ __('Format SQL') }}
                </button>
              </div>
            </div>
          </div>

          <!-- TAB 6: Preview -->
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
                  class="btn btn-sm btn-default"
                  @click="copyCode"
                >
                  {{ isCopied ? __('Copied!') : __('Copy Code') }}
                </button>
              </div>
            </template>
          </div>
        </div>
      </div>

      <div class="modal-footer database-builder-footer">
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
            class="btn btn-default fw-80px"
            @click="handleClose"
          >
            {{ __('Cancel') }}
          </button>
          <button
            type="button"
            class="btn btn-primary fw-80px"
            :disabled="isEmpty"
            @click="handleInsert"
          >
            {{ isScratchpad ? __('Replace') : __('Insert') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Mini-modal / Dialog for Bulk List Editor (IN / NOT IN) -->
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
