<script setup>
import { ref, onMounted, onUnmounted, nextTick, watch, computed } from 'vue';
import { useTabsStore } from '../../stores/tabs.js';
import { handleFetch } from '../../lib/api/client.js';
import { __ } from '../../lib/helpers.js';
import ConsoleLog from './ConsoleLog.vue';
import ConsoleEditor from './ConsoleEditor.vue';
import ScratchpadOutput from './ScratchpadOutput.vue';

const props = defineProps({
  tabId: {
    type: String,
    required: true,
  },
  isActive: {
    type: Boolean,
    default: false,
  },
  outlineFlat: {
    type: Array,
    default: () => [],
  },
  outlineChained: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['save-snippet', 'edit-entry']);

const tabsStore = useTabsStore();

const tab = computed(() => {
  return tabsStore.tabs.find((t) => t.id === props.tabId) || {};
});

const editorRef = ref(null);
const scratchpadEditorRef = ref(null);
const isDraggingSplitter = ref(false);
const isAutoAdjusting = ref(false);

let pendingEvaluationAutoAdjust = false;
let resizeObserver = null;
let userScrolledUp = false;
let currentAbortController = null;

const isNearBottom = () => {
  const buffer = document.querySelector(`.console-session-${props.tabId} .entries`);
  if (!buffer) return true;
  const threshold = 120;
  return buffer.scrollHeight - buffer.scrollTop - buffer.clientHeight <= threshold;
};

const handleBufferScroll = () => {
  userScrolledUp = !isNearBottom();
};

const scrollBufferToBottom = (force = false) => {
  const entries = document.querySelector(`.console-session-${props.tabId} .entries`);
  if (!entries) return;

  if (force || !userScrolledUp) {
    entries.scrollTop = entries.scrollHeight;
  }
};

const getActiveEditor = () => {
  return tab.value.mode === 'scratchpad' ? scratchpadEditorRef.value : editorRef.value;
};

const clearSession = () => {
  if (tab.value.mode === 'scratchpad') {
    scratchpadEditorRef.value?.clearEditor();
    tabsStore.updateTabScratchpad(props.tabId, {
      scratchpadCode: '',
      scratchpadResult: null,
    });
  } else {
    userScrolledUp = false;
    tabsStore.updateTabRepl(props.tabId, {
      history: [],
      historyIndex: -1,
    });
    if (editorRef.value) {
      editorRef.value.setEditorValue('');
      editorRef.value.focus();
    }
    nextTick(() => scrollBufferToBottom(true));
  }
};

const formatCode = () => {
  const activeEditor = getActiveEditor();
  return activeEditor?.formatCode();
};

const insertSnippet = (code, options = {}) => {
  const activeEditor = getActiveEditor();
  if (activeEditor) {
    activeEditor.insertSnippet(code, options);
  }
};

const focus = () => {
  getActiveEditor()?.focus();
};

const handleHistoryNav = (direction) => {
  const history = tab.value.history || [];
  if (history.length === 0) return;

  let newIndex = tab.value.historyIndex;
  if (direction === 'up') {
    newIndex = Math.max(0, newIndex - 1);
  } else {
    newIndex = Math.min(history.length - 1, newIndex + 1);
  }

  tabsStore.updateTabRepl(props.tabId, { historyIndex: newIndex });

  const item = history[newIndex];
  if (item && item.input !== null && editorRef.value) {
    editorRef.value.setEditorValue(item.input);
    nextTick(() => {
      editorRef.value.moveCursorToEnd();
    });
  }
};

const handleEditEntry = (index, type = 'input', customText = null) => {
  const activeEditor = getActiveEditor();
  if (customText !== null) {
    if (activeEditor) {
      activeEditor.setEditorValue(customText);
      nextTick(() => {
        activeEditor.focus();
        activeEditor.moveCursorToEnd();
      });
    }
    return;
  }

  const history = tab.value.history || [];
  const item = history[index];
  if (item && activeEditor) {
    let text = item.input;
    if (type === 'output') {
      text =
        typeof item.output === 'object' && item.output !== null
          ? JSON.stringify(item.output, null, 2)
          : String(item.output);
    }

    activeEditor.setEditorValue(text);
    nextTick(() => {
      activeEditor.focus();
      activeEditor.moveCursorToEnd();
    });
  }
};

const handleRunCode = (code) => {
  const text =
    typeof code === 'object' && code !== null
      ? JSON.stringify(code, null, 2)
      : String(code);

  if (tab.value.mode === 'scratchpad') {
    if (scratchpadEditorRef.value) {
      scratchpadEditorRef.value.setEditorValue(text);
      nextTick(() => {
        scratchpadEditorRef.value.focus();
      });
    }
    executeScratchpad(text);
  } else {
    handleExecute(text);
    if (editorRef.value) {
      editorRef.value.setEditorValue('');
      nextTick(() => {
        editorRef.value.focus();
      });
    }
  }
};

const handleExecute = (input) => {
  userScrolledUp = false;
  tabsStore.setTabEvaluating(props.tabId, true);

  const currentItemIndex = tabsStore.addReplEntry(props.tabId, {
    input,
    output: '"' + __('Loading...') + '"',
    pending: true,
    type: null,
    mode: 'evaluate',
    messages: [],
    visualizations: [],
    object_type: null,
  });

  const requestBody = new URLSearchParams({
    action: 'expressionlab_evaluate_expression',
    nonce: window.el_settings.nonce,
    expression: input,
  });

  if (tab.value.selectedUser) requestBody.append('user_id', tab.value.selectedUser);
  if (tab.value.selectedSite) requestBody.append('site_id', tab.value.selectedSite);

  nextTick(() => {
    scrollBufferToBottom(true);
  });

  if (currentAbortController) {
    currentAbortController.abort();
  }
  currentAbortController = new AbortController();

  handleFetch(window.el_settings.ajax_url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
    },
    body: requestBody,
    signal: currentAbortController.signal,
  })
    .then((response) => response.json())
    .then((response) => {
      if (response.success === true) {
        tabsStore.updateReplEntry(props.tabId, currentItemIndex, {
          output: response.data.result ?? 'null',
          messages: response.data.messages ?? [],
          visualizations: response.data.visualizations ?? [],
          object_type: response.data.object_type ?? null,
          pending: false,
        });
      } else {
        tabsStore.updateReplEntry(props.tabId, currentItemIndex, {
          output: response.data.message ?? __('Error evaluating expression.'),
          type: 'error',
          pending: false,
        });
      }
      tabsStore.setTabEvaluating(props.tabId, false);

      nextTick(() => {
        scrollBufferToBottom(true);
        if (props.isActive) {
          editorRef.value?.focus();
        }
        requestAnimationFrame(() => scrollBufferToBottom(true));
      });
    })
    .catch((err) => {
      if (err.name === 'AbortError') return;

      tabsStore.setTabEvaluating(props.tabId, false);
      tabsStore.updateReplEntry(props.tabId, currentItemIndex, {
        output: __('Error evaluating expression.'),
        pending: false,
        type: 'error',
        object_type: null,
      });
      nextTick(() => {
        scrollBufferToBottom(true);
        if (props.isActive) {
          editorRef.value?.focus();
        }
        requestAnimationFrame(() => scrollBufferToBottom(true));
      });
    });
};

const executeScratchpad = (input = null) => {
  if (tab.value.isEvaluating) return;
  const code = input ?? scratchpadEditorRef.value?.getEditorValue() ?? tab.value.scratchpadCode ?? '';
  if (!code.trim()) return;

  tabsStore.updateTabScratchpad(props.tabId, { scratchpadCode: code });
  tabsStore.setTabEvaluating(props.tabId, true);

  const requestBody = new URLSearchParams({
    action: 'expressionlab_evaluate_expression',
    nonce: window.el_settings.nonce,
    expression: code,
  });

  if (tab.value.selectedUser) requestBody.append('user_id', tab.value.selectedUser);
  if (tab.value.selectedSite) requestBody.append('site_id', tab.value.selectedSite);

  if (currentAbortController) {
    currentAbortController.abort();
  }
  currentAbortController = new AbortController();

  handleFetch(window.el_settings.ajax_url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
    },
    body: requestBody,
    signal: currentAbortController.signal,
  })
    .then((response) => response.json())
    .then((response) => {
      if (response.success === true) {
        tabsStore.updateTabScratchpad(props.tabId, {
          scratchpadResult: {
            result: response.data.result ?? 'null',
            messages: response.data.messages ?? [],
            visualizations: response.data.visualizations ?? [],
            object_type: response.data.object_type ?? null,
            type: null,
          },
          isScratchpadOutputVisible: true,
        });
      } else {
        tabsStore.updateTabScratchpad(props.tabId, {
          scratchpadResult: {
            result: response.data.message ?? __('Error evaluating expression.'),
            messages: response.data.messages ?? [],
            visualizations: [],
            object_type: null,
            type: 'error',
          },
          isScratchpadOutputVisible: true,
        });
      }
      tabsStore.setTabEvaluating(props.tabId, false);

      if (
        response.success === true &&
        Array.isArray(response.data.visualizations) &&
        response.data.visualizations.length > 0
      ) {
        if (props.isActive) {
          pendingEvaluationAutoAdjust = true;
        } else {
          tabsStore.updateTabScratchpad(props.tabId, { needsLayoutRefresh: true });
        }
      }

      nextTick(() => {
        if (props.isActive) {
          scratchpadEditorRef.value?.focus();
        }
      });
    })
    .catch((err) => {
      if (err.name === 'AbortError') return;

      tabsStore.updateTabScratchpad(props.tabId, {
        scratchpadResult: {
          result: __('Error evaluating expression.'),
          messages: [],
          visualizations: [],
          object_type: null,
          type: 'error',
        },
        isScratchpadOutputVisible: true,
      });
      tabsStore.setTabEvaluating(props.tabId, false);
      nextTick(() => {
        if (props.isActive) {
          scratchpadEditorRef.value?.focus();
        }
      });
    });
};

const clearScratchpadOutput = () => {
  tabsStore.updateTabScratchpad(props.tabId, { scratchpadResult: null });
};

const handleSaveScratchpadSnippet = () => {
  const code = scratchpadEditorRef.value?.getEditorValue() || tab.value.scratchpadCode || '';
  emit('save-snippet', code);
};

const startSplitterDrag = (e) => {
  if (e.detail === 2) {
    e.preventDefault();
    adjustOutputForVisualization(true);
    return;
  }
  e.preventDefault();
  isDraggingSplitter.value = true;
  const container = document.querySelector(`.console-session-${props.tabId} .scratchpad-workspace`);
  if (!container) return;

  const rect = container.getBoundingClientRect();
  const startRatio = tab.value.scratchpadEditorRatio || 50;
  const startY = e.clientY;

  const onMouseMove = (moveEvent) => {
    const deltaY = moveEvent.clientY - startY;
    const newRatio = startRatio + (deltaY / rect.height) * 100;
    tabsStore.updateTabScratchpad(props.tabId, {
      scratchpadEditorRatio: Math.max(15, Math.min(85, newRatio)),
    });
  };

  const onMouseUp = () => {
    isDraggingSplitter.value = false;
    window.removeEventListener('mousemove', onMouseMove);
    window.removeEventListener('mouseup', onMouseUp);
  };

  window.addEventListener('mousemove', onMouseMove);
  window.addEventListener('mouseup', onMouseUp);
};

const adjustOutputForVisualization = (force = false, retryCount = 0) => {
  if (!props.isActive) return;

  nextTick(() => {
    requestAnimationFrame(() => {
      const workspaceEl = document.querySelector(`.console-session-${props.tabId} .scratchpad-workspace`);
      const outputSectionEl = workspaceEl?.querySelector('.scratchpad-output-section');
      if (!workspaceEl || !outputSectionEl) return;

      if (outputSectionEl.offsetParent === null && retryCount < 15) {
        requestAnimationFrame(() => adjustOutputForVisualization(force, retryCount + 1));
        return;
      }

      const workspaceHeight = workspaceEl.getBoundingClientRect().height;
      if (!workspaceHeight || workspaceHeight <= 0) return;

      const MIN_EDITOR_PX = 220;
      const SPLITTER_PX = 6;
      const maxOutputHeight = Math.max(60, workspaceHeight - MIN_EDITOR_PX - SPLITTER_PX);

      const headerEl = outputSectionEl.querySelector('.scratchpad-output-header');
      const headerHeight = headerEl ? headerEl.getBoundingClientRect().height : 36;

      const bodyEl = outputSectionEl.querySelector('.scratchpad-output-body');
      let bodyPadding = 12;
      if (bodyEl) {
        const cs = window.getComputedStyle(bodyEl);
        bodyPadding = (parseFloat(cs.paddingTop) || 0) + (parseFloat(cs.paddingBottom) || 0);
      }

      let vizContentHeight = 0;
      const vizRoot = outputSectionEl.querySelector('.visualization-content');
      const tableEl = outputSectionEl.querySelector('.fp-table');
      const tableControlsEl = outputSectionEl.querySelector('.table-controls');
      const tableScrollWrapper = outputSectionEl.querySelector('.table-scroll-wrapper');

      if (tableEl) {
        const controlsHeight = tableControlsEl ? tableControlsEl.getBoundingClientRect().height + 8 : 0;
        const tableHeight = tableEl.getBoundingClientRect().height;
        const hasHScroll = tableScrollWrapper && tableScrollWrapper.scrollWidth > tableScrollWrapper.clientWidth;
        const scrollbarHeight = hasHScroll ? 16 : 0;
        vizContentHeight = controlsHeight + tableHeight + scrollbarHeight + 8;
      } else if (vizRoot) {
        const svgEl = vizRoot.querySelector('svg');
        const canvasEl = vizRoot.querySelector('canvas');
        if (svgEl || canvasEl) {
          const graphicEl = svgEl || canvasEl;
          vizContentHeight = graphicEl.getBoundingClientRect().height;
        } else {
          const vizRect = vizRoot.getBoundingClientRect();
          let maxBottom = vizRect.top;
          const elements = vizRoot.querySelectorAll('*');
          elements.forEach((el) => {
            const r = el.getBoundingClientRect();
            if (r.bottom > maxBottom) {
              maxBottom = r.bottom;
            }
          });
          vizContentHeight = maxBottom > vizRect.top ? maxBottom - vizRect.top + 8 : 0;
        }
      }

      if (vizContentHeight <= 0 && retryCount < 15) {
        requestAnimationFrame(() => adjustOutputForVisualization(force, retryCount + 1));
        return;
      }

      if (vizContentHeight <= 0) return;

      const neededOutputHeight = Math.ceil(headerHeight + bodyPadding + vizContentHeight + 6);
      const currentOutputHeight = outputSectionEl.getBoundingClientRect().height;

      if (Math.abs(neededOutputHeight - currentOutputHeight) < 4) return;
      if (!force && neededOutputHeight <= currentOutputHeight) return;

      const targetOutputHeight = Math.min(neededOutputHeight, maxOutputHeight);
      if (!force && targetOutputHeight <= currentOutputHeight) return;

      const targetEditorPx = Math.max(MIN_EDITOR_PX, workspaceHeight - targetOutputHeight - SPLITTER_PX);
      const targetEditorRatio = (targetEditorPx / workspaceHeight) * 100;

      isAutoAdjusting.value = true;
      tabsStore.updateTabScratchpad(props.tabId, {
        scratchpadEditorRatio: Math.max(15, Math.min(85, targetEditorRatio)),
      });

      setTimeout(() => {
        isAutoAdjusting.value = false;
      }, 260);
    });
  });
};

const handleVisualizationRendered = () => {
  if (pendingEvaluationAutoAdjust) {
    pendingEvaluationAutoAdjust = false;
    adjustOutputForVisualization(false);
  }
};

watch(
  () => props.isActive,
  (active) => {
    if (active) {
      if (tab.value.needsLayoutRefresh) {
        tabsStore.updateTabScratchpad(props.tabId, { needsLayoutRefresh: false });
        adjustOutputForVisualization(false);
      }
      nextTick(() => {
        getActiveEditor()?.focus();
        if (tab.value.mode === 'repl') {
          scrollBufferToBottom(true);
        }
      });
    }
  }
);

onMounted(() => {
  const sessionEl = document.querySelector(`.console-session-${props.tabId}`);
  const entriesEl = sessionEl?.querySelector('.entries');

  if (entriesEl) {
    entriesEl.addEventListener('scroll', handleBufferScroll, { passive: true });
    if (typeof window !== 'undefined' && 'ResizeObserver' in window) {
      resizeObserver = new ResizeObserver(() => {
        if (!userScrolledUp || tab.value.isEvaluating) {
          scrollBufferToBottom(true);
        }
      });
      resizeObserver.observe(entriesEl);
    }
  }

  // Restore draft if any
  if (tab.value.mode === 'scratchpad' && tab.value.scratchpadCode) {
    nextTick(() => {
      scratchpadEditorRef.value?.setEditorValue(tab.value.scratchpadCode);
    });
  }

  nextTick(() => {
    if (props.isActive) {
      scrollBufferToBottom(true);
      getActiveEditor()?.focus();
    }
  });
});

onUnmounted(() => {
  if (currentAbortController) {
    currentAbortController.abort();
    currentAbortController = null;
  }
  if (resizeObserver) {
    resizeObserver.disconnect();
    resizeObserver = null;
  }
  const sessionEl = document.querySelector(`.console-session-${props.tabId}`);
  const entriesEl = sessionEl?.querySelector('.entries');
  if (entriesEl) {
    entriesEl.removeEventListener('scroll', handleBufferScroll);
  }
});

defineExpose({
  executeScratchpad,
  handleExecute,
  clearSession,
  formatCode,
  insertSnippet,
  focus,
  getActiveEditor,
  adjustOutputForVisualization,
  handleSaveScratchpadSnippet,
});
</script>

<template>
  <div
    class="console-session-container"
    :class="[`console-session-${tabId}`, { 'mode-scratchpad': tab.mode === 'scratchpad' }]"
  >
    <!-- REPL Entries Buffer -->
    <ul
      v-show="tab.mode !== 'scratchpad'"
      class="entries"
    >
      <ConsoleLog
        :entries="tab.history || []"
        @edit-entry="handleEditEntry"
        @run-code="handleRunCode"
      />

      <ConsoleEditor
        ref="editorRef"
        :loading="tab.isEvaluating"
        :completion-items="outlineFlat"
        :chain-data="outlineChained"
        mode="repl"
        @execute="handleExecute"
        @clear="clearSession"
        @history-up="handleHistoryNav('up')"
        @history-down="handleHistoryNav('down')"
      />
    </ul>

    <!-- Scratchpad Workspace -->
    <div
      v-show="tab.mode === 'scratchpad'"
      class="scratchpad-workspace"
      :class="{ 'auto-adjusting': isAutoAdjusting }"
    >
      <div
        class="scratchpad-editor-section"
        :style="tab.isScratchpadOutputVisible ? { height: `${tab.scratchpadEditorRatio || 50}%` } : { height: '100%' }"
      >
        <ConsoleEditor
          ref="scratchpadEditorRef"
          :loading="false"
          :completion-items="outlineFlat"
          :chain-data="outlineChained"
          mode="scratchpad"
          @execute="executeScratchpad"
        />
      </div>

      <div
        v-if="tab.isScratchpadOutputVisible"
        class="scratchpad-splitter"
        :class="{ dragging: isDraggingSplitter }"
        :title="__('Drag to resize / Double-click to auto-fit')"
        @mousedown="startSplitterDrag"
      >
        <div class="splitter-handle" />
      </div>

      <div
        v-show="tab.isScratchpadOutputVisible"
        class="scratchpad-output-section"
        :style="{ height: `calc(${100 - (tab.scratchpadEditorRatio || 50)}% - 6px)` }"
      >
        <ScratchpadOutput
          :result="tab.scratchpadResult"
          :loading="tab.isEvaluating"
          @clear-output="clearScratchpadOutput"
          @close="tabsStore.updateTabScratchpad(tabId, { isScratchpadOutputVisible: false })"
          @visualization-rendered="handleVisualizationRendered"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.console-session-container {
  width: 100%;
  height: 100%;
  min-height: 0;
  min-width: 0;
  display: flex;
  flex-direction: column;
  position: relative;
  overflow: hidden;
}
</style>
