<script setup>
import { ref, computed, watch } from 'vue';
import { handleDownload } from '../../../lib/api/client.js';
import { __, sprintf, sanitizeHtml } from '../../../lib/helpers.js';

const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
});

const isPaginated = ref(true);
const pageSize = ref(10);
const currentPage = ref(1);
const sortColumn = ref(null);
const sortOrder = ref('asc'); // 'asc' | 'desc'
const searchQueries = ref({});

const availablePageSizes = [10, 20, 50, 100];

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

// Actions
function toggleSort(column) {
  if (sortColumn.value === column) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortColumn.value = column;
    sortOrder.value = 'asc';
  }
}

function toggleCell(event) {
  const target = event.currentTarget.querySelector('.inner-scroll');
  if (target) {
    const isExpanded = target.classList.toggle('expanded');
    if (isExpanded) {
      const range = document.createRange();
      range.selectNodeContents(target);
      const selection = window.getSelection();
      selection.removeAllRanges();
      selection.addRange(range);
    } else {
      window.getSelection().removeAllRanges();
    }
  }
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
    
    <div class="table-scroll-wrapper">
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
              :title="__('Click to expand/collapse')"
              @click="toggleCell"
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
  </div>
</template>

<style lang="scss" scoped>
.fp-table-container {
  display: flex;
  flex-direction: column;
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
  overflow-x: auto;
}

.fp-table {
  display: grid;
  grid-template-columns: repeat(var(--col-count), minmax(200px, 1fr));
  width: 100%;
}

.fp-table thead,
.fp-table tbody,
.fp-table tr {
  display: contents;
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

.search-row th {
  padding: 3px;
  background-color: #f9f9f9;
}

.column-search {
  width: 100%;
  box-sizing: border-box;
  padding: 4px;
  font-size: 0.9em;
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
  transition: max-height 0.2s ease;
}

.inner-scroll:not(.expanded) {
  text-overflow: ellipsis;
  white-space: nowrap;
}

.inner-scroll.expanded {
  max-height: 200px;
  overflow-y: auto;
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
