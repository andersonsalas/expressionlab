<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';
import DocTooltip from '../ui/DocTooltip.vue';
import NotebookOutlineItem from './NotebookOutlineItem.vue';
import { __, hasAddons } from '../../lib/helpers.js';
import { useTabsStore } from '../../stores/tabs.js';
import { extractNotebookOutline, smoothFastScrollTo, highlightCell } from '../../lib/notebook-outline.js';

const props = defineProps({
  outlineTree: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['item-click', 'clear-console']);

// Active tab in sidebar ('symbols' | 'outline')
const activeSidebarTab = ref('symbols');


// 1. Notebook Outline (Pro Addon)

let tabsStore = null;
try {
  tabsStore = useTabsStore();
} catch {
  tabsStore = null;
}

const activeTab = computed(() => tabsStore?.activeTab || null);
const isNotebookOpen = computed(() => Boolean(activeTab.value && activeTab.value.mode === 'notebook'));

const notebookOutline = ref([]);

const updateNotebookOutline = () => {
  if (!isNotebookOpen.value) {
    notebookOutline.value = [];
    return;
  }

  const cells = activeTab.value?.notebookMeta?.cells;
  if (!Array.isArray(cells) || cells.length === 0) {
    notebookOutline.value = [];
    return;
  }

  notebookOutline.value = extractNotebookOutline(cells);
};

watch(
  () => [tabsStore?.activeTabId, activeTab.value?.mode],
  () => {
    updateNotebookOutline();
  },
  { immediate: true }
);

watch(
  () => activeTab.value?.notebookMeta,
  () => {
    updateNotebookOutline();
  },
  { deep: true }
);

const handleOutlineUpdateEvent = () => {
  updateNotebookOutline();
};

const handleHeadingClick = (node) => {
  if (!node || !node.cellId) return;

  let cellEl =
    document.getElementById(`notebook-cell-${node.cellId}`) ||
    document.querySelector(`.notebook-cell[data-cell-id="${node.cellId}"]`);

  if (!cellEl && activeTab.value?.notebookMeta?.cells) {
    const idx = activeTab.value.notebookMeta.cells.findIndex((c) => c.id === node.cellId);
    if (idx !== -1) {
      const allCells = document.querySelectorAll('.notebook-cell');
      if (allCells[idx]) {
        cellEl = allCells[idx];
      }
    }
  }

  if (cellEl) {
    const headingEls = Array.from(cellEl.querySelectorAll('h1, h2, h3, h4, h5, h6'));
    const matchingHeading = headingEls.find((h) => h.textContent.trim() === node.label);
    const target = matchingHeading || cellEl;

    smoothFastScrollTo(target);
    highlightCell(cellEl);
  }
};

// 2. Symbols Outline (Context Symbols)

const searchQuery = ref('');
const displayedOutline = ref([]);

// Tooltip state
const hoveredItem = ref(null);
const tooltipStyle = ref({});
const tooltipRef = ref(null);
const isShiftHeld = ref(false);
const isMouseOverItem = ref(false);
const isMouseOverTooltip = ref(false);
let closeTimeout = null;

const cancelCloseTooltip = () => {
  if (closeTimeout) {
    clearTimeout(closeTimeout);
    closeTimeout = null;
  }
};

const scheduleCloseTooltip = () => {
  cancelCloseTooltip();
  if (isShiftHeld.value) return;

  closeTimeout = setTimeout(() => {
    if (!isMouseOverItem.value && !isMouseOverTooltip.value && !isShiftHeld.value) {
      hoveredItem.value = null;
      stopAutoScroll();
    }
  }, 250);
};

const closeTooltip = () => {
  cancelCloseTooltip();
  isShiftHeld.value = false;
  hoveredItem.value = null;
  stopAutoScroll();
};

const onTooltipClick = (event) => {
  const insertBtn = event.target?.closest?.('.el-code-insert-btn');
  if (insertBtn) {
    closeTooltip();
  }
};

const handleItemClick = (method) => {
  closeTooltip();
  emit('item-click', method);
};

const onTooltipMouseEnter = () => {
  cancelCloseTooltip();
  isMouseOverTooltip.value = true;
  stopAutoScroll();
};

const onTooltipMouseLeave = () => {
  isMouseOverTooltip.value = false;
  scheduleCloseTooltip();
};

const handleKeyDown = (e) => {
  if (e.key === 'Shift') {
    isShiftHeld.value = true;
    cancelCloseTooltip();
  }
};

const handleKeyUp = (e) => {
  if (e.key === 'Shift') {
    isShiftHeld.value = false;
    if (!isMouseOverItem.value && !isMouseOverTooltip.value) {
      scheduleCloseTooltip();
    }
  }
};

onMounted(() => {
  window.addEventListener('keydown', handleKeyDown);
  window.addEventListener('keyup', handleKeyUp);
  window.addEventListener('el:notebook-outline-update', handleOutlineUpdateEvent);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown);
  window.removeEventListener('keyup', handleKeyUp);
  window.removeEventListener('el:notebook-outline-update', handleOutlineUpdateEvent);
  cancelCloseTooltip();
  stopAutoScroll();
});

// Auto-scroll logic
let scrollAnimationId = null;

const stopAutoScroll = () => {
  if (scrollAnimationId) {
    cancelAnimationFrame(scrollAnimationId);
    scrollAnimationId = null;
  }
};

const startAutoScroll = (el) => {
  stopAutoScroll();

  if (el.scrollHeight <= el.clientHeight) return;

  const maxScroll = el.scrollHeight - el.clientHeight;
  let scrollTop = 0;
  let frameCount = 0;
  const pauseFrames = 60; // ~1 second
  let state = 'pause-top';

  const animate = () => {
    // If the element is no longer in the DOM or valid
    if (!el) {
      stopAutoScroll();
      return;
    }

    if (state === 'pause-top') {
      frameCount++;
      if (frameCount >= pauseFrames) {
        state = 'scrolling';
        frameCount = 0;
      }
    } else if (state === 'scrolling') {
      scrollTop += 0.5; // pixels per frame
      if (scrollTop >= maxScroll) {
        scrollTop = maxScroll;
        state = 'pause-bottom';
      }
      el.scrollTop = scrollTop;
    } else if (state === 'pause-bottom') {
      frameCount++;
      if (frameCount >= pauseFrames) {
        scrollTop = 0;
        el.scrollTop = 0;
        state = 'pause-top';
        frameCount = 0;
      }
    }

    scrollAnimationId = requestAnimationFrame(animate);
  };

  scrollAnimationId = requestAnimationFrame(animate);
};

const onMouseEnter = (item, event) => {
  cancelCloseTooltip();
  isMouseOverItem.value = true;

  if (!item.doc) {
    hoveredItem.value = null;
    stopAutoScroll();
    return;
  }

  if (hoveredItem.value === item) {
    return;
  }

  hoveredItem.value = item;

  const rect = event.currentTarget.getBoundingClientRect();

  // Position: to the left of the item
  // Start hidden to calculate position
  tooltipStyle.value = {
    top: `${rect.top}px`,
    right: `${window.innerWidth - rect.left + 11}px`,
    opacity: '0',
  };

  nextTick(() => {
    if (tooltipRef.value) {
      // Calculate position relative to footer
      const tooltipRect = tooltipRef.value.getBoundingClientRect();
      const footer = document.querySelector('.editor-footer');
      const footerTop = footer ? footer.getBoundingClientRect().top : window.innerHeight;

      const buffer = 10;
      const maxBottom = footerTop - buffer;

      let newTop = rect.top;
      let maxHeight = null;

      if (newTop + tooltipRect.height > maxBottom) {
        newTop = maxBottom - tooltipRect.height;

        // Check top boundary
        if (newTop < buffer) {
          newTop = buffer;
          maxHeight = maxBottom - buffer;
        }
      }

      const style = {
        ...tooltipStyle.value,
        top: `${newTop}px`,
        opacity: '1',
      };

      tooltipStyle.value = style;

      if (tooltipRef.value) {
        const wrapper = tooltipRef.value.querySelector('.description-wrapper');
        if (wrapper) {
          startAutoScroll(wrapper);
        } else {
          stopAutoScroll();
        }
      }
    }
  });
};

const onMouseLeave = () => {
  isMouseOverItem.value = false;
  scheduleCloseTooltip();
};

const filterOutline = () => {
  if (!props.outlineTree) {
    displayedOutline.value = [];
    return;
  }
  const query = searchQuery.value.trim().toLowerCase();

  let nodes = props.outlineTree;

  // Filter if query exists
  if (query) {
    nodes = props.outlineTree.reduce((acc, obj) => {
      const objMatches = obj.label.toLowerCase().includes(query);
      const matchingMethods = obj.methods.filter((m) =>
        m.label.toLowerCase().includes(query)
      );

      if (objMatches || matchingMethods.length > 0) {
        acc.push({
          ...obj,
          methods: matchingMethods,
        });
      }
      return acc;
    }, []);
  }

  // Group nodes: Functions and Objects
  const functionsNode = nodes.find((n) => n.label === 'Functions');
  const constantsNode = nodes.find((n) => n.label === 'Constants');
  const objectNodes = nodes.filter((n) => n.label !== 'Functions' && n.label !== 'Constants');

  const result = [];

  if (objectNodes.length > 0) {
    result.push({
      label: 'Objects',
      icon: 'codicon-extensions',
      expanded: true,
      isGroup: true,
      items: objectNodes.map((n) => ({
        ...n,
        expanded: query ? true : n.expanded,
        methods: [...n.methods].sort((a, b) => {
          if (a.kind === 'Constant' && b.kind !== 'Constant') return -1;
          if (a.kind !== 'Constant' && b.kind === 'Constant') return 1;

          if (a.kind === 'Property' && b.kind !== 'Property') return -1;
          if (a.kind !== 'Property' && b.kind === 'Property') return 1;

          return 0;
        }),
      })),
    });
  }

  if (functionsNode) {
    result.push({
      ...functionsNode,
      expanded: query ? true : functionsNode.expanded,
      isGroup: false,
    });
  }

  if (constantsNode) {
    result.push({
      ...constantsNode,
      expanded: query ? true : constantsNode.expanded,
      isGroup: false,
    });
  }

  displayedOutline.value = result;
};

watch(searchQuery, filterOutline);
watch(() => props.outlineTree, filterOutline, { immediate: true });
</script>

<template>
  <div class="console-sidebar">
    <!-- Two tabs (50%-50%) displayed ONLY if compilation includes Pro Addon -->
    <div
      v-if="hasAddons()"
      class="sidebar-tabbar"
      role="tablist"
    >
      <button
        type="button"
        role="tab"
        :aria-selected="activeSidebarTab === 'symbols'"
        class="sidebar-tab-item"
        :class="{ active: activeSidebarTab === 'symbols' }"
        @click="activeSidebarTab = 'symbols'"
      >
        <span>{{ __('Symbols') }}</span>
      </button>
      <button
        type="button"
        role="tab"
        :aria-selected="activeSidebarTab === 'outline'"
        class="sidebar-tab-item"
        :class="{ active: activeSidebarTab === 'outline' }"
        @click="activeSidebarTab = 'outline'"
      >
        <span>{{ __('Outline') }}</span>
      </button>
    </div>

    <!-- Symbols Section (shown if !hasAddons() or activeSidebarTab === 'symbols') -->
    <div
      v-show="!hasAddons() || activeSidebarTab === 'symbols'"
      id="sidebar-outline"
      class="sidebar-section"
    >
      <div class="outline-search-container">
        <input
          v-model="searchQuery"
          type="text"
          name="outline-search"
          :placeholder="__('Search...')"
          class="outline-search-input"
        >
      </div>
      <div
        v-if="displayedOutline && displayedOutline.length > 0"
        class="section-content"
      >
        <ul class="outline-list">
          <li
            v-for="(obj, objIdx) in displayedOutline"
            :key="objIdx"
            class="outline-object"
          >
            <div
              class="outline-item class"
              style="cursor: pointer; display: flex; align-items: center"
              @click="obj.expanded = !obj.expanded"
            >
              <span
                class="codicon"
                :class="
                  obj.expanded ? 'codicon-chevron-down' : 'codicon-chevron-right'
                "
                style="margin-right: 4px"
              />
              <span>{{ __(obj.label) }} ({{ obj.isGroup ? obj.items.length : obj.methods.length }})</span>
            </div>
            <ul
              v-show="obj.expanded"
              class="outline-method-list"
            >
              <template v-if="obj.isGroup">
                <li
                  v-for="(subObj, subIdx) in obj.items"
                  :key="subIdx"
                  class="outline-object"
                >
                  <div
                    class="outline-item class"
                    style="cursor: pointer; display: flex; align-items: center;"
                    @click="subObj.expanded = !subObj.expanded"
                  >
                    <span
                      class="codicon"
                      :class="
                        subObj.expanded
                          ? 'codicon-chevron-down'
                          : 'codicon-chevron-right'
                      "
                      style="margin-right: 4px"
                    />
                    <span
                      class="codicon"
                      :class="subObj.icon || 'codicon-symbol-class'"
                      style="margin-right: 4px"
                    />
                    <span>{{ subObj.label }}</span>
                  </div>
                  <ul
                    v-show="subObj.expanded"
                    class="outline-method-list"
                  >
                    <li
                      v-for="(method, mIdx) in subObj.methods"
                      :key="mIdx"
                      :class="'outline-item method ' + method.kind.toLowerCase()"
                      style="cursor: pointer;"
                      @click="handleItemClick(method)"
                      @mouseenter="onMouseEnter(method, $event)"
                      @mouseleave="onMouseLeave"
                    >
                      <div
                        class="codicon"
                        :class="method.icon || 'codicon-symbol-method'"
                        style="margin-right: 4px"
                      />
                      <span>{{ method.label }}</span>
                    </li>
                  </ul>
                </li>
              </template>
              <template v-else>
                <li
                  v-for="(method, mIdx) in obj.methods"
                  :key="mIdx"
                  :class="'outline-item method ' + method.kind.toLowerCase()"
                  style="cursor: pointer;"
                  @click="handleItemClick(method)"
                  @mouseenter="onMouseEnter(method, $event)"
                  @mouseleave="onMouseLeave"
                >
                  <div
                    class="codicon"
                    :class="method.icon || 'codicon-symbol-method'"
                    style="margin-right: 4px"
                  />
                  <span>{{ method.label }}</span>
                </li>
              </template>
            </ul>
          </li>
        </ul>
      </div>
      <div
        v-else-if="searchQuery"
        class="section-content search-content"
      >
        <div class="codicon codicon-search" />
        <p style="padding: 10px; color: #888">
          {{ __('No results found') }}
        </p>
      </div>
      <div
        v-else-if="loading"
        class="section-content loading-content"
      >
        <!-- Cube spinner -->
        <div 
          class="cube-spinner"
        >
          <div />
          <div />
          <div />
          <div />
          <div />
          <div />
        </div>
        <span>{{ __('Loading outline') }}</span>
      </div>
      <div
        v-else-if="!loading"
        class="section-content outline-empty"
      >
        <div class="codicon codicon-issue-draft" />
        <p style="padding: 10px; color: #888">
          {{ __('No outline available') }}
        </p>
      </div>
    </div>

    <!-- Outline Section (Pro Addon: shown only when hasAddons() and activeSidebarTab === 'outline') -->
    <div
      v-if="hasAddons() && activeSidebarTab === 'outline'"
      id="sidebar-notebook-outline"
      class="sidebar-section"
    >
      <div
        v-if="isNotebookOpen && notebookOutline && notebookOutline.length > 0"
        class="section-content"
      >
        <ul class="outline-list notebook-outline-tree">
          <NotebookOutlineItem
            v-for="node in notebookOutline"
            :key="node.id"
            :node="node"
            :depth="0"
            @select="handleHeadingClick"
          />
        </ul>
      </div>
      <div
        v-else
        class="section-content outline-empty"
      >
        <div class="codicon codicon-bookmark" />
        <p style="padding: 10px; color: #888">
          {{ __('No outline available') }}
        </p>
      </div>
    </div>

    <Teleport to="body">
      <div
        v-if="hoveredItem"
        ref="tooltipRef"
        class="console-sidebar-tooltip"
        :style="tooltipStyle"
        @mouseenter="onTooltipMouseEnter"
        @mouseleave="onTooltipMouseLeave"
        @click="onTooltipClick"
      >
        <DocTooltip :doc="hoveredItem.doc" />
      </div>
    </Teleport>
  </div>
</template>
