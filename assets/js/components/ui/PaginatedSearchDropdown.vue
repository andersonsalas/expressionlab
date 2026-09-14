<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { handleFetch } from '../../lib/api/client.js';
import { __ } from '../../lib/helpers.js';

const props = defineProps({
  modelValue: {
    type: [String, Number],
    default: null
  },
  label: {
    type: String,
    default: 'Select'
  },
  placeholder: {
    type: String,
    default: 'Search...'
  },
  icon: {
    type: String,
    default: 'codicon-symbol-variable'
  },
  action: {
    type: String,
    required: true
  },
  itemsKey: {
    type: String,
    required: true
  },
  labelKey: {
    type: String,
    default: 'display_name'
  },
  initialItems: {
    type: Array,
    default: () => []
  }
});

const emit = defineEmits(['update:modelValue']);

const isOpen = ref(false);
const searchQuery = ref('');
const containerRef = ref(null);
const items = ref([...props.initialItems]);
const page = ref(1);
const maxPages = ref(1);
const loading = ref(false);
const cachedSelected = ref(null);

const findSelectedOption = () => Object.values(items.value).find(opt => opt.id == props.modelValue)
  || props.initialItems.find(opt => opt.id == props.modelValue)

watch(
  [items, () => props.modelValue, () => props.initialItems],
  () => {
    const found = findSelectedOption();

    if (found) {
      cachedSelected.value = found;
    }
  },
  { immediate: true, deep: true }
);

const selected = computed(() => {
  return findSelectedOption() || cachedSelected.value;
});

const fetchItems = async (isSearch = false) => {
  if (loading.value) return;
  
  if (isSearch) {
    page.value = 1;
    items.value = [];
  }
  
  loading.value = true;
  
  try {
    const url = window.el_settings.ajax_url;
    const body = new URLSearchParams();
    body.append('action', props.action);
    body.append('nonce', window.el_settings.nonce);
    body.append('search', searchQuery.value);
    body.append('page', page.value);

    const response = await handleFetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: body
    });

    const clone = response.clone ? response.clone() : { json: response.json };
    const data = await clone.json();

    if (data && data.success && data.data) {
      if (isSearch) {
        items.value = data.data[props.itemsKey] || [];
      } else {
        items.value = [...items.value, ...(data.data[props.itemsKey] || [])];
      }
      
      // Ensure specific selected item is in the list
      if (!searchQuery.value && props.modelValue && !items.value.find(opt => opt.id == props.modelValue) && props.initialItems.length) {
          const initItem = props.initialItems.find(opt => opt.id == props.modelValue);
          if (initItem) {
              items.value.push(initItem);
          }
      }
      
      page.value = data.data.page;
      maxPages.value = data.data.max_pages;
    }
  } catch (error) {
    console.error('Error fetching items:', error);
  } finally {
    loading.value = false;
  }
};

let debounceTimeout = null;
watch(searchQuery, () => {
  if (debounceTimeout) clearTimeout(debounceTimeout);
  debounceTimeout = setTimeout(() => {
    fetchItems(true);
  }, 300);
});

const handleSelect = (option) => {
  emit('update:modelValue', option.id);
  isOpen.value = false;
};

const toggleDropdown = () => {
  isOpen.value = !isOpen.value;
  if (isOpen.value && items.value.length === 0) {
    fetchItems(true);
  } else if (!isOpen.value) {
    searchQuery.value = '';
  }
};

const loadMore = () => {
  if (page.value < maxPages.value && !loading.value) {
    page.value++;
    fetchItems();
  }
};

const handleScroll = (event) => {
  const target = event.target;
  if (target.scrollTop + target.clientHeight >= target.scrollHeight - 10) {
    loadMore();
  }
};

const closeOnOutsideClick = (e) => {
  if (containerRef.value && !containerRef.value.contains(e.target)) {
    isOpen.value = false;
  }
};

onMounted(() => {
  document.addEventListener('click', closeOnOutsideClick);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', closeOnOutsideClick);
});

const filterLabel = (option) => {
  if (!option) return null;
  return option[props.labelKey];
};
</script>

<template>
  <div
    ref="containerRef"
    class="searchable-dropdown-container"
  >
    <div
      class="searchable-dropdown-trigger"
      :class="{ active: isOpen }"
      @click="toggleDropdown"
    >
      <img
        v-if="selected && selected.avatar"
        :src="selected.avatar"
        class="option-avatar"
        :alt="__('Avatar')"
      >
      <div
        v-else
        class="codicon"
        :class="icon"
      />
      <span
        class="label"
        :title="filterLabel(selected) || label"
      >{{ __(filterLabel(selected) || label) }}</span>
      <span class="codicon codicon-chevron-down" />
    </div>

    <div
      v-if="isOpen"
      class="searchable-dropdown-menu"
    >
      <input
        v-model="searchQuery"
        type="text"
        :placeholder="__(placeholder)"
        class="paginated-search-input"
        autofocus
        @click.stop
      >

      <ul
        class="options-list"
        @scroll="handleScroll"
      >
        <li
          v-if="items.length === 0 && !loading"
          class="no-results"
        >
          {{ __('No results found.') }}
        </li>
        
        <li
          v-for="option in items"
          :key="option.id"
          class="option-item"
          :class="{ selected: option.id == modelValue }"
          @click.stop="handleSelect(option)"
        >
          <div class="option-content">
            <template v-if="option.avatar">
              <img
                :src="option.avatar"
                class="option-avatar"
                :alt="__('Avatar')"
              >
            </template>
            <div class="option-label">
              {{ filterLabel(option) }}
            </div>
          </div>
          <span
            v-if="option.id == modelValue"
            class="codicon codicon-check"
          />
        </li>
        
        <li
          v-if="loading"
          class="loading-state"
        >
          {{ __('Loading...') }}
        </li>
      </ul>
    </div>
  </div>
</template>


