<script setup>
import { ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { useTabsStore } from '../../stores/tabs.js';
import { __ } from '../../lib/helpers.js';

const tabsStore = useTabsStore();

const scrollContainerRef = ref(null);
const renameInputRef = ref(null);

const isOverflowing = ref(false);
const canScrollLeft = ref(false);
const canScrollRight = ref(false);

const draggedIndex = ref(null);
const dragOverIndex = ref(null);

const editingTabId = ref(null);
const editingTitle = ref('');

let resizeObserver = null;

const checkOverflow = () => {
  const el = scrollContainerRef.value;
  if (!el) return;

  const scrollWidth = el.scrollWidth;
  const clientWidth = el.clientWidth;
  const scrollLeft = el.scrollLeft;

  isOverflowing.value = scrollWidth > clientWidth;
  canScrollLeft.value = scrollLeft > 2;
  canScrollRight.value = scrollLeft < scrollWidth - clientWidth - 2;
};

const handleScroll = () => {
  checkOverflow();
};

const scrollTabs = (delta) => {
  if (scrollContainerRef.value && typeof scrollContainerRef.value.scrollBy === 'function') {
    scrollContainerRef.value.scrollBy({ left: delta, behavior: 'smooth' });
  }
};

const scrollToActiveTab = () => {
  nextTick(() => {
    const el = scrollContainerRef.value;
    if (!el) return;

    const activeItem = el.querySelector('.console-tab-item.active');
    if (activeItem && typeof activeItem.scrollIntoView === 'function') {
      activeItem.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
    }
    checkOverflow();
  });
};

watch(() => tabsStore.activeTabId, scrollToActiveTab);
watch(() => tabsStore.tabCount, () => {
  nextTick(checkOverflow);
});

const handleSelectTab = (tabId) => {
  if (editingTabId.value) return;
  tabsStore.setActiveTab(tabId);
};

const handleCreateTab = () => {
  const newTab = tabsStore.createTab();
  if (newTab) {
    scrollToActiveTab();
  }
};

const handleCloseTab = (tabId) => {
  tabsStore.closeTab(tabId);
  nextTick(checkOverflow);
};

const startRename = (tab) => {
  editingTabId.value = tab.id;
  editingTitle.value = tab.title;
  nextTick(() => {
    const input = Array.isArray(renameInputRef.value)
      ? renameInputRef.value[0]
      : renameInputRef.value;
    input?.focus();
    input?.select();
  });
};

const commitRename = (tabId) => {
  if (!editingTabId.value) return;
  tabsStore.setTabTitle(tabId, editingTitle.value);
  editingTabId.value = null;
  editingTitle.value = '';
};

const cancelRename = () => {
  editingTabId.value = null;
  editingTitle.value = '';
};

const handleDragStart = (index, event) => {
  draggedIndex.value = index;
  event.dataTransfer.effectAllowed = 'move';
  event.dataTransfer.setData('text/plain', String(index));
};

const handleDragOver = (index) => {
  dragOverIndex.value = index;
};

const handleDragLeave = (index) => {
  if (dragOverIndex.value === index) {
    dragOverIndex.value = null;
  }
};

const handleDrop = (index) => {
  if (draggedIndex.value !== null && draggedIndex.value !== index) {
    tabsStore.reorderTabs(draggedIndex.value, index);
  }
  draggedIndex.value = null;
  dragOverIndex.value = null;
};

const handleDragEnd = () => {
  draggedIndex.value = null;
  dragOverIndex.value = null;
};

onMounted(() => {
  checkOverflow();
  if (typeof window !== 'undefined' && 'ResizeObserver' in window && scrollContainerRef.value) {
    resizeObserver = new ResizeObserver(() => {
      checkOverflow();
    });
    resizeObserver.observe(scrollContainerRef.value);
  }
});

onBeforeUnmount(() => {
  if (resizeObserver) {
    resizeObserver.disconnect();
    resizeObserver = null;
  }
});
</script>

<template>
  <div class="console-tabbar">
    <!-- Left Scroll Button -->
    <button
      v-if="isOverflowing"
      class="console-tabbar-scroll-btn btn-scroll-left"
      :disabled="!canScrollLeft"
      :title="__('Scroll tabs left')"
      @click="scrollTabs(-140)"
    >
      <span class="codicon codicon-chevron-left" />
    </button>

    <!-- Scrollable Tab Items Container -->
    <div
      ref="scrollContainerRef"
      class="console-tabbar-scroll-wrapper"
      @scroll="handleScroll"
    >
      <div
        v-for="(tab, index) in tabsStore.tabs"
        :key="tab.id"
        class="console-tab-item"
        :class="{
          active: tab.id === tabsStore.activeTabId,
          'is-dragging': draggedIndex === index,
          'drag-over': dragOverIndex === index
        }"
        :draggable="editingTabId !== tab.id"
        @click="handleSelectTab(tab.id)"
        @dblclick="startRename(tab)"
        @dragstart="handleDragStart(index, $event)"
        @dragover.prevent="handleDragOver(index)"
        @dragleave="handleDragLeave(index)"
        @drop.prevent="handleDrop(index)"
        @dragend="handleDragEnd"
      >
        <!-- In-place title edit input -->
        <input
          v-if="editingTabId === tab.id"
          ref="renameInputRef"
          v-model="editingTitle"
          class="tab-title-input"
          @blur="commitRename(tab.id)"
          @keydown.enter="commitRename(tab.id)"
          @keydown.esc="cancelRename"
          @click.stop
        >

        <!-- Static title -->
        <span
          v-else
          class="tab-title"
          :title="tab.title"
        >
          {{ tab.title }}
        </span>

        <!-- Evaluating Spinner -->
        <div
          v-if="tab.isEvaluating"
          class="metro-spinner tab-spinner"
          :title="__('Evaluating expression...')"
        />

        <!-- Close Button -->
        <button
          class="tab-close-btn"
          :title="__('Close tab')"
          @click.stop="handleCloseTab(tab.id)"
        >
          <span class="codicon codicon-close" />
        </button>
      </div>

      <!-- Add Tab Button (Inside scroll container at the end of tabs list) -->
      <button
        v-if="tabsStore.canAddTab"
        class="console-tab-add-btn"
        :title="__('New tab')"
        @click="handleCreateTab"
      >
        <span class="codicon codicon-plus" />
      </button>
    </div>

    <!-- Right Scroll Button -->
    <button
      v-if="isOverflowing"
      class="console-tabbar-scroll-btn btn-scroll-right"
      :disabled="!canScrollRight"
      :title="__('Scroll tabs right')"
      @click="scrollTabs(140)"
    >
      <span class="codicon codicon-chevron-right" />
    </button>
  </div>
</template>
