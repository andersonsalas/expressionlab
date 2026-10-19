<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { getActivePinia } from 'pinia';
import { useUiStore } from '../../../stores/ui';
import { handleDownload, handleClipboardCopy } from '../../../lib/api/client.js';
import { __, sprintf, sanitizeHtml } from '../../../lib/helpers.js';

const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
  defaultPageSize: {
    type: Number,
    default: 10,
  },
});

const emit = defineEmits(['rendered']);
const getUiStore = () => (getActivePinia() ? useUiStore() : null);

const availablePageSizes = [10, 20, 50, 100];

const isPaginated = ref(true);
const initialPageSize = props.data?.pageSize || props.data?.defaultPageSize || props.defaultPageSize || 10;
const pageSize = ref(availablePageSizes.includes(initialPageSize) ? initialPageSize : 10);
const currentPage = ref(1);
const sortColumn = ref(null);
const sortOrder = ref('asc'); // 'asc' | 'desc'
const searchQueries = ref({});

// Context Menu State
const isContextMenuOpen = ref(false);
const contextMenuRef = ref(null);
const contextMenuStyle = ref({});
const activeCell = ref({ value: null, column: null });
const CONTEXT_MENU_MARGIN = 4;

// Get columns from the first row of data, if available
const columns = computed(() => {
  if (props.data && props.data.data && props.data.data.length > 0) {
    return Object.keys(props.data.data[0]);
  }
  return [];
});

const filteredAndSortedData = computed(() => {
  if (!props.data || !props.data.data) return [];
  
  let result = [...props.data.data];

  // 1. Filtering
  const activeFilters = Object.entries(searchQueries.value).filter(([_, val]) => val && val.trim() !== '');
  if (activeFilters.length > 0) {
    result = result.filter(row => {
      return activeFilters.every(([key, query]) => {
        const cellValue = String(row[key] ?? '').toLowerCase();
        return cellValue.includes(query.toLowerCase());
      });
    });
  }

  // 2. Sorting
  if (sortColumn.value) {
    result.sort((a, b) => {
      let valA = a[sortColumn.value];
      let valB = b[sortColumn.value];

      // Attempt numeric sort if possible
      const numA = Number(valA);
      const numB = Number(valB);

      if (!isNaN(numA) && !isNaN(numB) && valA !== '' && valA !== null && valB !== '' && valB !== null) {
        valA = numA;
        valB = numB;
      } else {
        valA = String(valA ?? '').toLowerCase();
        valB = String(valB ?? '').toLowerCase();
      }

      if (valA < valB) return sortOrder.value === 'asc' ? -1 : 1;
      if (valA > valB) return sortOrder.value === 'asc' ? 1 : -1;
      return 0;
    });
  }

  return result;
});

const displayedData = computed(() => {
  if (!isPaginated.value) {
    return filteredAndSortedData.value;
  }
  const start = (currentPage.value - 1) * pageSize.value;
  const end = start + pageSize.value;
  return filteredAndSortedData.value.slice(start, end);
});

const totalPages = computed(() => {
  if (!isPaginated.value) return 1;
  return Math.ceil(filteredAndSortedData.value.length / pageSize.value);
});

// Watchers to reset pagination when data/filters change
watch(searchQueries, () => {
  currentPage.value = 1;
}, { deep: true });

watch(() => props.data.data, () => {
  currentPage.value = 1;
});

watch(isPaginated, () => {
  currentPage.value = 1;
});

watch(pageSize, () => {
  currentPage.value = 1;
});

watch(
  [() => props.data?.pageSize, () => props.data?.defaultPageSize, () => props.defaultPageSize],
  ([newCustomSize, newCustomDefault, newPropDefault]) => {
    const size = newCustomSize || newCustomDefault || newPropDefault;
    if (size && availablePageSizes.includes(size)) {
      pageSize.value = size;
    }
  }
);

// Actions
function toggleSort(column) {
  if (sortColumn.value === column) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortColumn.value = column;
    sortOrder.value = 'asc';
  }
}

function handleCellClick(event) {
  const target = event.currentTarget.querySelector('.inner-scroll');
  if (target) {
    const selection = window.getSelection();
    if (!selection || selection.isCollapsed || selection.toString().length === 0) {
      const range = document.createRange();
      range.selectNodeContents(target);
      selection.removeAllRanges();
      selection.addRange(range);
    }
  }
}

function updateContextMenuPosition(x, y, menuWidth = 180, menuHeight = 70) {
  const viewportWidth = window.innerWidth;
  const viewportHeight = window.innerHeight;

  let left = x;
  let top = y;

  if (left + menuWidth > viewportWidth - CONTEXT_MENU_MARGIN) {
    left = x - menuWidth;
  }
  if (top + menuHeight > viewportHeight - CONTEXT_MENU_MARGIN) {
    top = y - menuHeight;
  }

  left = Math.max(CONTEXT_MENU_MARGIN, Math.min(left, viewportWidth - menuWidth - CONTEXT_MENU_MARGIN));
  top = Math.max(CONTEXT_MENU_MARGIN, Math.min(top, viewportHeight - menuHeight - CONTEXT_MENU_MARGIN));

  contextMenuStyle.value = {
    position: 'fixed',
    top: `${top}px`,
    left: `${left}px`,
    zIndex: 100000,
  };
}

function handleCellContextMenu(value, column, event) {
  activeCell.value = { value, column };
  const { clientX, clientY } = event;
  // First pass with an estimated size so the menu never renders off-screen,
  // then re-clamp with the real rendered size.
  updateContextMenuPosition(clientX, clientY);
  isContextMenuOpen.value = true;
  nextTick(() => {
    const rect = contextMenuRef.value?.getBoundingClientRect();
    if (isContextMenuOpen.value && rect && rect.width > 0 && rect.height > 0) {
      updateContextMenuPosition(clientX, clientY, rect.width, rect.height);
    }
  });
}

function handleViewData() {
  isContextMenuOpen.value = false;
  const store = getUiStore();
  if (store) {
    store.openModal('data-viewer', {
      content: activeCell.value.value,
      column: activeCell.value.column,
    });
  }
}

async function handleCopyCellValue() {
  isContextMenuOpen.value = false;
  const content = activeCell.value.value;
  const text = content === null ? 'null' : (content === undefined ? '' : (typeof content === 'object' ? JSON.stringify(content, null, 2) : String(content)));
  await handleClipboardCopy(text);
}

function closeContextMenu() {
  if (isContextMenuOpen.value) {
    isContextMenuOpen.value = false;
  }
}

function handleGlobalKeydown(event) {
  if (isContextMenuOpen.value && event.key === 'Escape') {
    isContextMenuOpen.value = false;
  }
}

// Capture phase: runs before a cell's own handler, so right-clicking another cell
// re-targets the menu, while right-clicking anywhere else (including another
// table's cell) closes this instance's menu.
function handleOutsideContextMenu(event) {
  if (!isContextMenuOpen.value) return;
  if (contextMenuRef.value && contextMenuRef.value.contains(event.target)) return;
  isContextMenuOpen.value = false;
}

function exportCsv() {
  const dataToExport = filteredAndSortedData.value;
  if (!dataToExport || !dataToExport.length) return;

  const cols = columns.value;
  const csvContent = [
    cols.join(','), // Header
    ...dataToExport.map(row => {
      return cols.map(col => {
        let cell = row[col] ?? '';
        cell = String(cell).replace(/"/g, '""'); // Escape double quotes
        // Wrap in quotes if contains comma, quote or newline
        if (cell.search(/("|,|\n)/g) >= 0) {
          cell = `"${cell}"`;
        }
        return cell;
      }).join(',');
    })
  ].join('\n');

  handleDownload(csvContent, 'export.csv', 'text/csv;charset=utf-8;');
}

onMounted(() => {
  window.addEventListener('click', closeContextMenu);
  window.addEventListener('keydown', handleGlobalKeydown);
  window.addEventListener('resize', closeContextMenu);
  window.addEventListener('contextmenu', handleOutsideContextMenu, true);
  // Scroll events do not bubble; capturing on window catches scrolling of any
  // ancestor (console feed, page) that would detach the fixed menu from its cell.
  window.addEventListener('scroll', closeContextMenu, true);
  nextTick(() => {
    emit('rendered');
  });
});

onUnmounted(() => {
  window.removeEventListener('click', closeContextMenu);
  window.removeEventListener('keydown', handleGlobalKeydown);
  window.removeEventListener('resize', closeContextMenu);
  window.removeEventListener('contextmenu', handleOutsideContextMenu, true);
  window.removeEventListener('scroll', closeContextMenu, true);
});

watch(displayedData, () => {
  nextTick(() => {
    emit('rendered');
  });
});
</script>

<template>
  <div class="fp-table-container">
    <div class="table-controls">
      <div class="left">
        <label class="paginate-toggle">
          <input
            v-model="isPaginated"
            type="checkbox"
          >
          {{ __('Paginate') }}
        </label>
        <select
          v-model="pageSize"
          class="page-size-select"
          :disabled="!isPaginated"
        >
          <option
            v-for="size in availablePageSizes"
            :key="size"
            :value="size"
          >
            {{ sprintf(__('%d per page'), size) }}
          </option>
        </select>
        <div class="row-count">
          {{ sprintf(__('%d rows'), filteredAndSortedData.length) }}
          <span v-if="filteredAndSortedData.length !== (data.data || []).length">
            &nbsp; {{ sprintf(__('(filtered from %d)'), (data.data || []).length) }}
          </span>
        </div>
      </div>
      <div class="right">
        <div
          v-if="isPaginated && totalPages > 1"
          class="pagination-controls"
        >
          <!-- eslint-disable vue/no-v-html -->
          <button
            class="fp-button"
            :disabled="currentPage <= 1"
            @click="currentPage--"
            v-html="sanitizeHtml(__('&laquo; Prev'))"
          />
          <!-- eslint-enable vue/no-v-html -->
          <span class="page-info">{{ sprintf(__('Page %1$d / %2$d'), currentPage, totalPages) }}</span>
          <!-- eslint-disable vue/no-v-html -->
          <button
            class="fp-button"
            :disabled="currentPage >= totalPages"
            @click="currentPage++"
            v-html="sanitizeHtml(__('Next &raquo;'))"
          />
          <!-- eslint-enable vue/no-v-html -->
        </div>
        <button
          class="fp-button btn-export"
          @click="exportCsv"
        >
          {{ __('Export CSV') }}
        </button>
      </div>
    </div>
    
    <div
      class="table-scroll-wrapper"
      @scroll="closeContextMenu"
    >
      <table
        v-if="columns.length"
        class="fp-table"
        :style="{ '--col-count': columns.length }"
      >
        <thead>
          <!-- Header Row with Sort -->
          <tr>
            <th 
              v-for="col in columns" 
              :key="col" 
              class="sortable-header"
              :class="{ 'sorted': sortColumn === col }"
              @click="toggleSort(col)"
            >
              {{ col }}
              <span
                v-if="sortColumn === col"
                class="sort-icon"
              >
                {{ sortOrder === 'asc' ? '▲' : '▼' }}
              </span>
            </th>
          </tr>
          <!-- Search Row -->
          <tr class="search-row">
            <th
              v-for="col in columns"
              :key="'search-' + col"
            >
              <input 
                v-model="searchQueries[col]" 
                type="text" 
                :placeholder="sprintf(__('Search %s'), col)"
                class="column-search"
              >
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(row, rIdx) in displayedData"
            :key="rIdx"
          >
            <td 
              v-for="col in columns" 
              :key="col" 
              @click="handleCellClick"
              @contextmenu.prevent="handleCellContextMenu(row[col], col, $event)"
            >
              <div class="inner-scroll">
                {{ row[col] }}
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-else>
        {{ __('No data to display.') }}
      </div>
    </div>

    <!-- Cell Context Menu -->
    <Teleport to="body">
      <div
        v-if="isContextMenuOpen"
        ref="contextMenuRef"
        class="table-cell-context-menu"
        role="menu"
        :style="contextMenuStyle"
      >
        <div
          class="cell-menu-item btn-view-data"
          role="menuitem"
          @click.stop="handleViewData"
        >
          <span class="codicon codicon-eye" />
          <span>{{ __('View data...') }}</span>
        </div>
        <div
          class="cell-menu-item btn-copy-cell"
          role="menuitem"
          @click.stop="handleCopyCellValue"
        >
          <span class="codicon codicon-copy" />
          <span>{{ __('Copy cell value') }}</span>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style lang="scss" scoped>
.fp-table-container {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
  height: 100%;
}

.table-controls {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 7px;
  margin-top: 1px;
}

.table-controls .left, .table-controls .right {
  display: flex;
  align-items: center;
  gap: 10px;
}

.table-scroll-wrapper {
  flex: 1;
  min-height: 0;
  overflow: auto;
}

.fp-table {
  display: grid;
  grid-template-columns: repeat(var(--col-count), minmax(200px, 1fr));
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
}

.fp-table thead,
.fp-table tbody,
.fp-table tr {
  display: contents;
}

.fp-table thead th {
  position: sticky;
  background: #f7f7f7;
  box-sizing: border-box;
}

.fp-table thead tr:first-child th {
  top: 0;
  height: 26px;
  line-height: 26px;
  padding-top: 0;
  padding-bottom: 0;
  z-index: 3;
  border-top: 1px solid #ddd;
  border-bottom: 1px solid #ddd;
}

.fp-table thead tr.search-row th {
  top: 26px;
  z-index: 2;
  border-bottom: 1px solid #ddd;
  background-color: #f9f9f9;
  padding: 3px;
}

.sortable-header {
  cursor: pointer;
  user-select: none;
  white-space: nowrap;
}

.sortable-header:hover {
  background-color: #f0f0f0;
}

.sort-icon {
  margin-left: 5px;
  font-size: 0.8em;
  float: right;
}

.column-search {
  width: 100%;
  box-sizing: border-box;
  padding: 4px;
  font-size: 13px;
  border: 1px solid #ccc;
  border-radius: 0;
  font-family: $font-family-sans;
  font-weight: normal;
  
  &:not(:empty) {
    background-color: #fff;
  }

  &:hover {
    border-color: black;
  }

  &:focus {
    border-color: $wordpress-primary;
  }
}

td {
  cursor: text;
  padding: 4px;
  border-bottom: 1px solid #f0f0f0;
}

.inner-scroll {
  max-height: 20px;
  width: 100%;
  overflow: hidden;
  display: block;
  word-break: break-all;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.table-cell-context-menu {
  position: fixed;
  min-width: 170px;
  background: #fff;
  border: 1px solid black;
  margin: 0;
  padding: 1px;
  z-index: 100000;

  .cell-menu-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    cursor: pointer;
    font-size: 13px;
    color: #333;
    height: 30px;
    box-sizing: border-box;
    white-space: nowrap;

    .codicon {
      font-size: 16px;
      color: #666;
    }

    &:hover {
      background: $wordpress-primary;
      color: white;

      .codicon {
        color: white;
      }
    }
  }
}

.page-size-select {
  padding: 4px;
}

.row-count {
  border-left: solid 1px #d4d4d4;
  padding-left: 8px;
  height: 30px;
  display: flex;
  align-items: center;
}

.page-info {
  font-size: 0.9em;
  font-weight: bold;
  display: inline-block;
  min-width: 100px;
  padding: 0 12px;
  text-align: center;
}
</style>
