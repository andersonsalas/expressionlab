<script setup>
import { ref, computed, nextTick } from 'vue';
import { __ } from '../../lib/helpers.js';

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  },
  allTags: {
    type: Array,
    default: () => []
  },
  placeholder: {
    type: String,
    default: () => __('Add tags...')
  },
  disabled: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['update:modelValue', 'change']);

const inputValue = ref('');
const isFocused = ref(false);
const highlightedIndex = ref(-1);
const inputRef = ref(null);
const chipsWrapperRef = ref(null);

const filteredSuggestions = computed(() => {
  const query = inputValue.value.trim().toLowerCase();
  if (!query) return [];

  const currentSet = new Set((props.modelValue || []).map(t => String(t).toLowerCase()));
  return props.allTags
    .filter(t => {
      const lower = String(t).toLowerCase();
      return !currentSet.has(lower) && lower.includes(query);
    })
    .slice(0, 8);
});

const showDropdown = computed(() => {
  return isFocused.value && filteredSuggestions.value.length > 0;
});

const scrollToInputEnd = async () => {
  await nextTick();
  if (chipsWrapperRef.value) {
    chipsWrapperRef.value.scrollLeft = chipsWrapperRef.value.scrollWidth;
  }
};

const addTag = (rawTag) => {
  const clean = (rawTag || '').trim();
  if (!clean) return;

  const current = props.modelValue || [];
  const exists = current.some(t => String(t).toLowerCase() === clean.toLowerCase());

  if (!exists) {
    const updated = [...current, clean];
    emit('update:modelValue', updated);
    emit('change', updated);
  }

  inputValue.value = '';
  highlightedIndex.value = -1;
  scrollToInputEnd();
};

const removeTag = (index) => {
  const current = props.modelValue || [];
  if (index >= 0 && index < current.length) {
    const updated = [...current];
    updated.splice(index, 1);
    emit('update:modelValue', updated);
    emit('change', updated);
  }
};

const handleKeyDown = (e) => {
  if (e.key === 'ArrowDown') {
    if (filteredSuggestions.value.length > 0) {
      e.preventDefault();
      highlightedIndex.value = (highlightedIndex.value + 1) % filteredSuggestions.value.length;
    }
  } else if (e.key === 'ArrowUp') {
    if (filteredSuggestions.value.length > 0) {
      e.preventDefault();
      highlightedIndex.value = highlightedIndex.value <= 0
        ? filteredSuggestions.value.length - 1
        : highlightedIndex.value - 1;
    }
  } else if (e.key === 'Enter') {
    e.preventDefault();
    if (highlightedIndex.value >= 0 && highlightedIndex.value < filteredSuggestions.value.length) {
      addTag(filteredSuggestions.value[highlightedIndex.value]);
    } else if (inputValue.value.trim()) {
      addTag(inputValue.value.trim());
    }
  } else if (e.key === ',') {
    e.preventDefault();
    if (inputValue.value.trim()) {
      addTag(inputValue.value.trim());
    }
  } else if (e.key === 'Backspace') {
    if (inputValue.value === '' && (props.modelValue || []).length > 0) {
      removeTag((props.modelValue || []).length - 1);
    }
  } else if (e.key === 'Escape') {
    highlightedIndex.value = -1;
    isFocused.value = false;
    inputRef.value?.blur();
  }
};

const handleBlur = () => {
  if (inputValue.value.trim()) {
    addTag(inputValue.value.trim());
  }
  isFocused.value = false;
  highlightedIndex.value = -1;
};

const focusInput = () => {
  inputRef.value?.focus();
};
</script>

<template>
  <div
    class="tag-input-container"
    :class="{ focused: isFocused }"
    @click="focusInput"
  >
    <div
      ref="chipsWrapperRef"
      class="tag-chips-wrapper"
    >
      <span
        v-for="(tag, index) in modelValue"
        :key="tag"
        class="tag-chip"
      >
        <span class="tag-chip-label">{{ tag }}</span>
        <button
          type="button"
          class="tag-remove-btn"
          :title="__('Remove tag')"
          @click.stop="removeTag(index)"
        >
          <span class="codicon codicon-close" />
        </button>
      </span>

      <input
        ref="inputRef"
        v-model="inputValue"
        type="text"
        class="tag-inline-input"
        :placeholder="(!modelValue || modelValue.length === 0) ? placeholder : ''"
        :disabled="disabled"
        @focus="isFocused = true"
        @blur="handleBlur"
        @keydown="handleKeyDown"
      >
    </div>

    <!-- Autocomplete dropdown suggestions (anchored floating upwards) -->
    <div
      v-if="showDropdown"
      class="tag-suggestions-dropdown"
      @mousedown.prevent
    >
      <div
        v-for="(suggestion, idx) in filteredSuggestions"
        :key="suggestion"
        class="tag-suggestion-item"
        :class="{ active: idx === highlightedIndex }"
        @click="addTag(suggestion)"
        @mouseenter="highlightedIndex = idx"
      >
        <span class="codicon codicon-tag" />
        <span class="suggestion-text">{{ suggestion }}</span>
      </div>
    </div>
  </div>
</template>
