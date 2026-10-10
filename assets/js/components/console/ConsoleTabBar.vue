<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { useTabsStore } from '../../stores/tabs.js';
import { useSettingsStore } from '../../stores/settings.js';
import { __ } from '../../lib/helpers.js';

const tabsStore = useTabsStore();
const settingsStore = useSettingsStore();

const scrollContainerRef = ref(null);
const renameInputRef = ref(null);

const isOverflowing = ref(false);
const canScrollLeft = ref(false);
const canScrollRight = ref(false);

const draggedIndex = ref(null);
const dragOverIndex = ref(null);

const editingTabId = ref(null);
const editingTitle = ref('');

const isAddDropdownOpen = ref(false);
const addDropdownBtnRef = ref(null);
const dropdownMenuRef = ref(null);
const dropdownStyle = ref({});

const isContextMenuOpen = ref(false);
const contextMenuTab = ref(null);
const contextMenuRef = ref(null);
const contextMenuStyle = ref({});

let resizeObserver = null;
let addTabScrollTimeout = null;

const canCloseOtherTabs = computed(() => {
  return tabsStore.tabCount > 1;
});

const canCloseTabsToTheRight = computed(() => {
  if (!contextMenuTab.value) return false;
  const index = tabsStore.tabs.findIndex((t) => t.id === contextMenuTab.value.id);
  return index !== -1 && index < tabsStore.tabs.length - 1;
});

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
  if (isAddDropdownOpen.value) {
    updateDropdownPosition();
  }
  if (isContextMenuOpen.value) {
    isContextMenuOpen.value = false;
  }
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

const scrollToEnd = () => {
  const el = scrollContainerRef.value;
  if (!el) return;
  if (typeof el.scrollTo === 'function') {
    el.scrollTo({ left: el.scrollWidth, behavior: 'smooth' });
  } else {
    el.scrollLeft = el.scrollWidth;
  }
  checkOverflow();
};

watch(() => tabsStore.activeTabId, scrollToActiveTab);
watch(() => tabsStore.tabCount, () => {
  nextTick(checkOverflow);
});

const handleSelectTab = (tabId) => {
  if (editingTabId.value) return;
  tabsStore.setActiveTab(tabId);
};

const updateDropdownPosition = () => {
  if (!addDropdownBtnRef.value) return;
  const rect = addDropdownBtnRef.value.getBoundingClientRect();
  dropdownStyle.value = {
    position: 'fixed',
    top: `${rect.bottom + 2}px`,
    left: `${rect.left}px`,
    zIndex: 99999,
  };
};

const CONTEXT_MENU_MARGIN = 4;

const updateContextMenuPosition = (x, y) => {
  if (!contextMenuRef.value) return;
  const rect = contextMenuRef.value.getBoundingClientRect();
  const menuWidth = rect.width || contextMenuRef.value.offsetWidth || 220;
  const menuHeight = rect.height || contextMenuRef.value.offsetHeight || 160;
  const viewportWidth = window.innerWidth;
  const viewportHeight = window.innerHeight;

  let left = x;
  let top = y;

  // Render to the right of cursor whenever possible.
  // If it exceeds the right viewport edge, flip to the left of the cursor.
  if (left + menuWidth > viewportWidth - CONTEXT_MENU_MARGIN) {
    left = x - menuWidth;
  }
  if (top + menuHeight > viewportHeight - CONTEXT_MENU_MARGIN) {
    top = y - menuHeight;
  }

  // Final clamp keeps a safe margin on every edge (min wins on tiny viewports).
  left = Math.max(CONTEXT_MENU_MARGIN, Math.min(left, viewportWidth - menuWidth - CONTEXT_MENU_MARGIN));
  top = Math.max(CONTEXT_MENU_MARGIN, Math.min(top, viewportHeight - menuHeight - CONTEXT_MENU_MARGIN));

  contextMenuStyle.value = {
    position: 'fixed',
    top: `${top}px`,
    left: `${left}px`,
    zIndex: 99999,
  };
};

const toggleAddDropdown = () => {
  if (!tabsStore.canAddTab) return;
  if (isContextMenuOpen.value) {
    isContextMenuOpen.value = false;
  }
  isAddDropdownOpen.value = !isAddDropdownOpen.value;
  if (isAddDropdownOpen.value) {
    nextTick(updateDropdownPosition);
  }
};

const handleCreateTab = (mode = 'repl') => {
  isAddDropdownOpen.value = false;
  const newTab = tabsStore.createTab({ mode });
  if (newTab) {
    if (addTabScrollTimeout) {
      clearTimeout(addTabScrollTimeout);
    }
    addTabScrollTimeout = setTimeout(() => {
      scrollToEnd();
      addTabScrollTimeout = null;
    }, 60);
  }
};

const defaultAddTabMode = computed(() => {
  return settingsStore.defaultTabMode === 'scratchpad' ? 'scratchpad' : 'repl';
});

const defaultAddTabTitle = computed(() => {
  return defaultAddTabMode.value === 'scratchpad'
    ? __('New Scratchpad tab')
    : __('New Console tab');
});

const handleCreateDefaultTab = () => {
  handleCreateTab(defaultAddTabMode.value);
};

const handleContextMenu = (tab, event) => {
  event.preventDefault();
  if (editingTabId.value) return;

  isAddDropdownOpen.value = false;
  contextMenuTab.value = tab;

  const clientX = event.clientX;
  const clientY = event.clientY;

  // Set default fixed position at cursor coordinates immediately so it doesn't render as a full-width block
  contextMenuStyle.value = {
    position: 'fixed',
    top: `${clientY}px`,
    left: `${clientX}px`,
    zIndex: 99999,
  };
  isContextMenuOpen.value = true;

  nextTick(() => {
    updateContextMenuPosition(clientX, clientY);
  });
};

const handleCloseAllTabs = () => {
  isContextMenuOpen.value = false;
  tabsStore.closeAllTabs();
  nextTick(checkOverflow);
};

const handleCloseOtherTabs = () => {
  if (!canCloseOtherTabs.value || !contextMenuTab.value) return;
  const tabId = contextMenuTab.value.id;
  isContextMenuOpen.value = false;
  tabsStore.closeOtherTabs(tabId);
  nextTick(checkOverflow);
};

const handleCloseTabsToTheRight = () => {
  if (!canCloseTabsToTheRight.value || !contextMenuTab.value) return;
  const tabId = contextMenuTab.value.id;
  isContextMenuOpen.value = false;
  tabsStore.closeTabsToTheRight(tabId);
  nextTick(checkOverflow);
};

const handleRenameFromMenu = () => {
  if (!contextMenuTab.value) return;
  const tabToRename = contextMenuTab.value;
  isContextMenuOpen.value = false;
  startRename(tabToRename);
};

const handleClickOutside = (event) => {
  if (
    isAddDropdownOpen.value &&
    dropdownMenuRef.value &&
    !dropdownMenuRef.value.contains(event.target) &&
    addDropdownBtnRef.value &&
    !addDropdownBtnRef.value.contains(event.target)
  ) {
    isAddDropdownOpen.value = false;
  }

  if (
    isContextMenuOpen.value &&
    contextMenuRef.value &&
    !contextMenuRef.value.contains(event.target)
  ) {
    isContextMenuOpen.value = false;
  }
};

const handleKeydown = (event) => {
  if (event.key === 'Escape') {
    if (isAddDropdownOpen.value) {
      isAddDropdownOpen.value = false;
    }
    if (isContextMenuOpen.value) {
      isContextMenuOpen.value = false;
    }
  }
};

const handleCloseTab = (tabId) => {
  tabsStore.closeTab(tabId);
  nextTick(checkOverflow);
};

const startRename = (tab) => {
  if (!tab) return;
  tabsStore.setActiveTab(tab.id);
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

const handleWindowResize = () => {
  updateDropdownPosition();
  if (isContextMenuOpen.value) {
    isContextMenuOpen.value = false;
  }
};

onMounted(() => {
  checkOverflow();
  if (typeof window !== 'undefined' && 'ResizeObserver' in window && scrollContainerRef.value) {
    resizeObserver = new ResizeObserver(() => {
      checkOverflow();
    });
    resizeObserver.observe(scrollContainerRef.value);
  }
  if (typeof window !== 'undefined') {
    window.addEventListener('click', handleClickOutside);
    // Capture phase: runs before a tab's own contextmenu handler, so right-clicking
    // elsewhere dismisses the menu while right-clicking another tab re-opens it.
    window.addEventListener('contextmenu', handleClickOutside, true);
    window.addEventListener('keydown', handleKeydown);
    window.addEventListener('resize', handleWindowResize);
  }
});

onBeforeUnmount(() => {
  if (addTabScrollTimeout) {
    clearTimeout(addTabScrollTimeout);
    addTabScrollTimeout = null;
  }
  if (resizeObserver) {
    resizeObserver.disconnect();
    resizeObserver = null;
  }
  if (typeof window !== 'undefined') {
    window.removeEventListener('click', handleClickOutside);
    window.removeEventListener('contextmenu', handleClickOutside, true);
    window.removeEventListener('keydown', handleKeydown);
    window.removeEventListener('resize', handleWindowResize);
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
      :class="{ 'has-horizontal-scroll': isOverflowing }"
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
        @contextmenu.prevent="handleContextMenu(tab, $event)"
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

      <!-- Add Tab Split Button Group (Inside scroll container at the end of tabs list) -->
      <div
        v-if="tabsStore.canAddTab"
        class="console-tab-add-group"
      >
        <button
          class="console-tab-add-btn btn-add-main"
          :title="defaultAddTabTitle"
          @click="handleCreateDefaultTab"
        >
          <span class="codicon codicon-plus" />
        </button>
        <button
          ref="addDropdownBtnRef"
          class="console-tab-add-btn btn-add-dropdown"
          :class="{ active: isAddDropdownOpen }"
          :title="__('New tab options')"
          @click.stop="toggleAddDropdown"
        >
          <span class="codicon codicon-chevron-down" />
        </button>
      </div>
    </div>

    <!-- Dropdown Menu for New Tab Options (Teleported to avoid scroll clipping) -->
    <Teleport to="body">
      <div
        v-if="isAddDropdownOpen"
        ref="dropdownMenuRef"
        class="console-tab-add-menu"
        :style="dropdownStyle"
      >
        <div
          class="tab-add-menu-item"
          @click="handleCreateTab('repl')"
        >
          <span class="codicon codicon-console" />
          <span>{{ __('Console') }}</span>
        </div>
        <div
          class="tab-add-menu-item"
          @click="handleCreateTab('scratchpad')"
        >
          <span class="codicon codicon-notebook" />
          <span>{{ __('Scratchpad') }}</span>
        </div>
      </div>
    </Teleport>

    <!-- Context Menu for Tab Items (Teleported to avoid scroll clipping) -->
    <Teleport to="body">
      <div
        v-if="isContextMenuOpen"
        ref="contextMenuRef"
        class="console-tab-context-menu"
        role="menu"
        :style="contextMenuStyle"
      >
        <div
          class="tab-menu-item btn-close-all"
          role="menuitem"
          @click="handleCloseAllTabs"
        >
          <span class="codicon codicon-close-all" />
          <span>{{ __('Close all tabs') }}</span>
        </div>
        <div
          class="tab-menu-item btn-close-other"
          role="menuitem"
          :class="{ disabled: !canCloseOtherTabs }"
          :aria-disabled="!canCloseOtherTabs"
          @click="handleCloseOtherTabs"
        >
          <span class="codicon codicon-close" />
          <span>{{ __('Close all except current') }}</span>
        </div>
        <div
          class="tab-menu-item btn-close-right"
          role="menuitem"
          :class="{ disabled: !canCloseTabsToTheRight }"
          :aria-disabled="!canCloseTabsToTheRight"
          @click="handleCloseTabsToTheRight"
        >
          <span class="codicon codicon-arrow-right" />
          <span>{{ __('Close all to the right') }}</span>
        </div>
        <div
          class="tab-menu-separator"
          role="separator"
        />
        <div
          class="tab-menu-item btn-rename"
          role="menuitem"
          @click="handleRenameFromMenu"
        >
          <span class="codicon codicon-edit" />
          <span>{{ __('Rename') }}</span>
        </div>
      </div>
    </Teleport>

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
