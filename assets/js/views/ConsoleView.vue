<script setup>
import { ref, onMounted, onUnmounted, nextTick, computed, watch } from 'vue';
import { useUiStore } from '../stores/ui';
import { useOutlineStore } from '../stores/outline.js';
import { handleFetch } from '../lib/api/client.js';
import { debugLog, __, sprintf } from '../lib/helpers.js';
import ConsoleSidebar from '../components/console/ConsoleSidebar.vue';
import ConsoleLog from '../components/console/ConsoleLog.vue';
import ConsoleEditor from '../components/console/ConsoleEditor.vue';
import ScratchpadOutput from '../components/console/ScratchpadOutput.vue';
import PaginatedSearchDropdown from '../components/ui/PaginatedSearchDropdown.vue';

let signatureInterval = null;

const uiStore = useUiStore();
const outlineStore = useOutlineStore();
const initialBanner = window.el_settings?.banner
  ? [
      {
        input: null,
        output: null,
        pending: false,
        type: null,
        mode: null,
        messages: Array.isArray(window.el_settings.banner)
          ? window.el_settings.banner
          : [window.el_settings.banner],
        visualizations: [],
        object_type: null,
      },
    ]
  : [];
const history = ref(initialBanner);
const historyIndex = ref(-1);
const loading = ref(false);
const showSensitive = ref(true);
const selectedUser = ref(window.el_settings.user_id ?? null);
const selectedSite = ref(window.el_settings.multisite ? (window.el_settings.site.sites && window.el_settings.site.sites.length > 0 ? window.el_settings.site.sites[0].id : null) : null);
const isMultisite = ref(window.el_settings.multisite);
const initialUsers = ref(window.el_settings.site.users ?? []);
const initialSites = ref(window.el_settings.site.sites ?? []);
const signatureDuration = window.el_settings.settings.signature_duration ?? 30;
const signatureTimeRemaining = ref(0);
const signatureProgress = ref(0);
const editorRef = ref(null);
const scratchpadEditorRef = ref(null);
const isScratchpad = ref(false);
const scratchpadResult = ref(null);
const isScratchpadOutputVisible = ref(false);
const isSidebarVisible = ref(true);
const scratchpadEditorRatio = ref(50);
const isDraggingSplitter = ref(false);
const isAutoAdjusting = ref(false);
let pendingEvaluationAutoAdjust = false;
const enableSandbox = window.el_settings.settings.enable_sandbox;

const updateSignatureTime = () => {
  if (uiStore.currentChallenge && uiStore.challengeFetchedAt) {
    const elapsed = Math.floor((Date.now() - uiStore.challengeFetchedAt) / 1000);
    signatureTimeRemaining.value = Math.max(0, signatureDuration - elapsed);
    signatureProgress.value = Math.max(0, 100 - (elapsed / signatureDuration) * 100);
  } else {
    signatureTimeRemaining.value = 0;
    signatureProgress.value = 0;
  }
};

watch(() => uiStore.challengeFetchedAt, updateSignatureTime);

watch(
  () => uiStore.snippetToInsert,
  (code) => {
    if (code) {
      const activeEditor = isScratchpad.value ? scratchpadEditorRef.value : editorRef.value;
      if (activeEditor) {
        activeEditor.insertSnippet(code, { replaceWord: true });
        uiStore.triggerSnippetInsert(null);
      }
    }
  }
);

const signatureState = computed(() => {
  if (uiStore.isRequestingChallenge) return 'requesting';
  if (signatureTimeRemaining.value <= 0) return 'expired';
  return 'valid';
});

const footerInfo = Object.freeze({
  php_version: window.el_settings.server.php_version,
  wp_version: window.el_settings.server.wp_version,
  server_ip: window.el_settings.server.server_ip,
  user_ip: window.el_settings.server.user_ip,
  debug_enabled: window.el_settings.server.debug_enabled,
  database_readonly: window.el_settings.settings.database_readonly,
  filesystem_readonly: window.el_settings.settings.filesystem_readonly,
  network_readonly: window.el_settings.settings.network_readonly,
});

let resizeObserver = null;
let userScrolledUp = false;

const isNearBottom = () => {
  const buffer = document.querySelector('.console-buffer');
  if (!buffer) return true;
  const threshold = 120;
  return buffer.scrollHeight - buffer.scrollTop - buffer.clientHeight <= threshold;
};

const handleBufferScroll = () => {
  userScrolledUp = !isNearBottom();
};

const scrollBufferToBottom = (force = false) => {
  const buffer = document.querySelector('.console-buffer');
  const entries = document.querySelector('.entries');
  if (!buffer) return;

  if (force || !userScrolledUp) {
    buffer.scrollTop = buffer.scrollHeight;
    if (entries) {
      entries.scrollTop = entries.scrollHeight;
    }
  }
};

const clearConsole = () => {
  userScrolledUp = false;
  history.value = [];
  historyIndex.value = -1;
  if (editorRef.value) {
    editorRef.value.setEditorValue('');
    editorRef.value.focus();
  }
  nextTick(() => scrollBufferToBottom(true));
};


const toggleScratchpad = () => {
  isScratchpad.value = !isScratchpad.value;
  nextTick(() => {
    if (isScratchpad.value) {
      scratchpadEditorRef.value?.focus();
    } else {
      editorRef.value?.focus();
      scrollBufferToBottom(true);
    }
  });
};

const executeScratchpad = (input = null) => {
  if (loading.value) return;
  const code = input ?? scratchpadEditorRef.value?.getEditorValue() ?? '';
  if (!code.trim()) return;

  loading.value = true;

  const requestBody = new URLSearchParams({
    action: 'expressionlab_evaluate_expression',
    nonce: window.el_settings.nonce,
    expression: code,
  });

  if (selectedUser.value) requestBody.append('user_id', selectedUser.value);
  if (selectedSite.value) requestBody.append('site_id', selectedSite.value);

  handleFetch(window.el_settings.ajax_url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
    },
    body: requestBody,
  })
    .then((response) => response.json())
    .then((response) => {
      if (response.success === true) {
        scratchpadResult.value = {
          result: response.data.result ?? 'null',
          messages: response.data.messages ?? [],
          visualizations: response.data.visualizations ?? [],
          object_type: response.data.object_type ?? null,
          type: null,
        };
      } else {
        scratchpadResult.value = {
          result: response.data.message ?? __('Error evaluating expression.'),
          messages: response.data.messages ?? [],
          visualizations: [],
          object_type: null,
          type: 'error',
        };
      }
      isScratchpadOutputVisible.value = true;
      loading.value = false;
      if (response.success === true && Array.isArray(response.data.visualizations) && response.data.visualizations.length > 0) {
        pendingEvaluationAutoAdjust = true;
      }
      nextTick(() => {
        scratchpadEditorRef.value?.focus();
      });
    })
    .catch(() => {
      scratchpadResult.value = {
        result: __('Error evaluating expression.'),
        messages: [],
        visualizations: [],
        object_type: null,
        type: 'error',
      };
      isScratchpadOutputVisible.value = true;
      loading.value = false;
      nextTick(() => {
        scratchpadEditorRef.value?.focus();
      });
    });
};

const clearScratchpadOutput = () => {
  scratchpadResult.value = null;
};

const handleClearScratchpad = () => {
  scratchpadEditorRef.value?.clearEditor();
  scratchpadResult.value = null;
};

const handleSaveScratchpadSnippet = () => {
  const code = scratchpadEditorRef.value?.getEditorValue() || '';
  uiStore.openModal('library', { draftCode: code });
};

const toggleSidebar = () => {
  isSidebarVisible.value = !isSidebarVisible.value;
};

const startSplitterDrag = (e) => {
  if (e.detail === 2) {
    e.preventDefault();
    adjustOutputForVisualization(true);
    return;
  }
  e.preventDefault();
  isDraggingSplitter.value = true;
  const container = document.querySelector('.scratchpad-workspace');
  if (!container) return;

  const rect = container.getBoundingClientRect();
  const startRatio = scratchpadEditorRatio.value;
  const startY = e.clientY;

  const onMouseMove = (moveEvent) => {
    const deltaY = moveEvent.clientY - startY;
    const newRatio = startRatio + (deltaY / rect.height) * 100;
    scratchpadEditorRatio.value = Math.max(15, Math.min(85, newRatio));
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
  nextTick(() => {
    requestAnimationFrame(() => {
      const workspaceEl = document.querySelector('.scratchpad-workspace');
      const outputSectionEl = document.querySelector('.scratchpad-output-section');
      if (!workspaceEl || !outputSectionEl) return;

      // If output section is not visible yet, retry up to 15 frames (~250ms)
      if (outputSectionEl.offsetParent === null && retryCount < 15) {
        requestAnimationFrame(() => adjustOutputForVisualization(force, retryCount + 1));
        return;
      }

      const workspaceHeight = workspaceEl.getBoundingClientRect().height;
      if (!workspaceHeight || workspaceHeight <= 0) return;

      const MIN_EDITOR_PX = 220;
      const SPLITTER_PX = 6;
      const maxOutputHeight = Math.max(60, workspaceHeight - MIN_EDITOR_PX - SPLITTER_PX);

      // Measure header height
      const headerEl = outputSectionEl.querySelector('.scratchpad-output-header');
      const headerHeight = headerEl ? headerEl.getBoundingClientRect().height : 36;

      // Measure body padding
      const bodyEl = outputSectionEl.querySelector('.scratchpad-output-body');
      let bodyPadding = 12;
      if (bodyEl) {
        const cs = window.getComputedStyle(bodyEl);
        bodyPadding = (parseFloat(cs.paddingTop) || 0) + (parseFloat(cs.paddingBottom) || 0);
      }

      // Measure active visualization content — ONLY intrinsic element sizes,
      // NEVER container scrollHeight/clientHeight (which causes feedback loops).
      let vizContentHeight = 0;
      const vizRoot = outputSectionEl.querySelector('.visualization-content');

      const tableEl = outputSectionEl.querySelector('.fp-table');
      const tableControlsEl = outputSectionEl.querySelector('.table-controls');
      const tableScrollWrapper = outputSectionEl.querySelector('.table-scroll-wrapper');

      if (tableEl) {
        const controlsHeight = tableControlsEl ? tableControlsEl.getBoundingClientRect().height + 8 : 0;
        const tableHeight = tableEl.getBoundingClientRect().height;
        const hasHScroll = tableScrollWrapper && (tableScrollWrapper.scrollWidth > tableScrollWrapper.clientWidth);
        const scrollbarHeight = hasHScroll ? 16 : 0;

        vizContentHeight = controlsHeight + tableHeight + scrollbarHeight + 8;
      } else if (vizRoot) {
        const svgEl = vizRoot.querySelector('svg');
        const canvasEl = vizRoot.querySelector('canvas');
        const vegaActions = vizRoot.querySelector('.vega-actions');

        if (svgEl || canvasEl) {
          const graphicEl = svgEl || canvasEl;
          const graphicHeight = graphicEl.getBoundingClientRect().height;
          const actionsHeight = vegaActions ? (vegaActions.getBoundingClientRect().height || 26) : 0;
          vizContentHeight = graphicHeight + actionsHeight + 12;
        } else {
          // Fallback: measure bounding box of all child elements
          const vizRect = vizRoot.getBoundingClientRect();
          let maxBottom = vizRect.top;
          const elements = vizRoot.querySelectorAll('*');
          elements.forEach((el) => {
            const r = el.getBoundingClientRect();
            if (r.bottom > maxBottom) {
              maxBottom = r.bottom;
            }
          });
          vizContentHeight = maxBottom > vizRect.top ? (maxBottom - vizRect.top + 8) : 0;
        }
      }

      // If visualization DOM elements are not yet mounted or 0 height, retry next frame
      if (vizContentHeight <= 0 && retryCount < 15) {
        requestAnimationFrame(() => adjustOutputForVisualization(force, retryCount + 1));
        return;
      }

      if (vizContentHeight <= 0) return;

      const neededOutputHeight = Math.ceil(headerHeight + bodyPadding + vizContentHeight + 6);
      const currentOutputHeight = outputSectionEl.getBoundingClientRect().height;

      // Idempotency guard: if the difference is negligible, skip adjustment
      if (Math.abs(neededOutputHeight - currentOutputHeight) < 4) {
        return;
      }

      // Unidirectional rule: Only shrink editor (grow output), NEVER expand editor automatically
      if (!force && neededOutputHeight <= currentOutputHeight) {
        return;
      }

      const targetOutputHeight = Math.min(neededOutputHeight, maxOutputHeight);

      if (!force && targetOutputHeight <= currentOutputHeight) {
        return;
      }

      const targetEditorPx = Math.max(MIN_EDITOR_PX, workspaceHeight - targetOutputHeight - SPLITTER_PX);
      const targetEditorRatio = (targetEditorPx / workspaceHeight) * 100;

      // Apply with smooth transition
      isAutoAdjusting.value = true;
      scratchpadEditorRatio.value = Math.max(15, Math.min(85, targetEditorRatio));

      setTimeout(() => {
        isAutoAdjusting.value = false;
      }, 260);
    });
  });
};

const handleScratchpadTabChange = () => {
  // (Empty)
};

const handleVisualizationRendered = () => {
  if (pendingEvaluationAutoAdjust) {
    pendingEvaluationAutoAdjust = false;
    adjustOutputForVisualization(false);
  }
};

const handleFormatCode = () => {
  if (isScratchpad.value) {
    scratchpadEditorRef.value?.formatCode();
  } else {
    editorRef.value?.formatCode();
  }
};

const handleExecute = (input) => {
  userScrolledUp = false;
  loading.value = true;
  const mode = uiStore.consoleMode;

  history.value.push({ 
    input, 
    output: '"' + __('Loading...') + '"', 
    pending: true, 
    type: null, 
    mode, 
    messages: [], 
    visualizations: [], 
    object_type: null 
  });
  historyIndex.value = history.value.length;

  // Set loading state for latest item
  const currentItemIndex = history.value.length - 1;

  let action = 'expressionlab_evaluate_expression';
  
  const requestBody = new URLSearchParams({
    action: action,
    nonce: window.el_settings.nonce,
    expression: input,
  });

  if (selectedUser.value) requestBody.append('user_id', selectedUser.value);
  if (selectedSite.value) requestBody.append('site_id', selectedSite.value);

  nextTick(() => {
    scrollBufferToBottom(true);
  });

  handleFetch(window.el_settings.ajax_url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
    },
    body: requestBody,
  })
    .then((response) => response.json())
    .then((response) => {
      const item = history.value[currentItemIndex];
      if (response.success === true) {
        item.output = response.data.result ?? 'null';
        item.messages = response.data.messages ?? [];
        item.visualizations = response.data.visualizations ?? [];
        item.object_type = response.data.object_type ?? null;
      } else {
        item.output = response.data.message ?? __('Error evaluating expression.');
        item.type = 'error';
      }
      item.pending = false;
      loading.value = false;

      nextTick(() => {
        scrollBufferToBottom(true);
        editorRef.value?.focus();
        requestAnimationFrame(() => scrollBufferToBottom(true));
      });
    }).catch((err) => {
      loading.value = false; 
      const currentItemIndex = history.value.length - 1;
      if (history.value[currentItemIndex]) {
          history.value[currentItemIndex].output = __('Error evaluating expression.');
          history.value[currentItemIndex].pending = false;
          history.value[currentItemIndex].type = 'error';
          history.value[currentItemIndex].object_type = null;
      }
      nextTick(() => {
        scrollBufferToBottom(true);
        editorRef.value?.focus();
        requestAnimationFrame(() => scrollBufferToBottom(true));
      });
    });
};

const handleHistoryNav = (direction) => {
  if (history.value.length === 0) return;

  if (direction === 'up') {
    historyIndex.value = Math.max(0, historyIndex.value - 1);
  } else {
    historyIndex.value = Math.min(history.value.length - 1, historyIndex.value + 1);
  }

  const item = history.value[historyIndex.value];
  if (item && item.input !== null && editorRef.value) {
    editorRef.value.setEditorValue(item.input);
    nextTick(() => {
      editorRef.value.moveCursorToEnd();
    });
  }
};

const handleEditEntry = (index, type = 'input', customText = null) => {
  const activeEditor = isScratchpad.value ? scratchpadEditorRef.value : editorRef.value;
  if (customText !== null) {
      uiStore.setConsoleMode('evaluate');
      if (activeEditor) {
        activeEditor.setEditorValue(customText);
        nextTick(() => {
          activeEditor.focus();
          activeEditor.moveCursorToEnd();
        });
      }
      return;
  }
  const item = history.value[index];
  if (item && activeEditor) {
    let text = item.input;
    if (type === 'output') {
        text = (typeof item.output === 'object' && item.output !== null) 
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
  uiStore.setConsoleMode('evaluate');
  const text = (typeof code === 'object' && code !== null) 
          ? JSON.stringify(code, null, 2) 
          : String(code);

  if (isScratchpad.value) {
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

const handleSidebarItemClick = (item) => {
    const activeEditor = isScratchpad.value ? scratchpadEditorRef.value : editorRef.value;
    if (activeEditor && item && item.insertText) {
        activeEditor.insertSnippet(item.insertText);
    }
};

onMounted(() => {    
    signatureInterval = setInterval(updateSignatureTime, 1000);

    debugLog('Console component mounted', { enableSandbox: enableSandbox, settings: window.el_settings });
    
    outlineStore.loadOutline();

    const bufferEl = document.querySelector('.console-buffer');
    const entriesEl = document.querySelector('.entries');

    if (bufferEl) {
      bufferEl.addEventListener('scroll', handleBufferScroll, { passive: true });
    }

    if (entriesEl && typeof window !== 'undefined' && 'ResizeObserver' in window) {
      resizeObserver = new ResizeObserver(() => {
        if (!userScrolledUp || loading.value) {
          scrollBufferToBottom(true);
        }
      });
      resizeObserver.observe(entriesEl);
    }

    nextTick(() => {
      scrollBufferToBottom(true);
      editorRef.value?.focus();
    });
});

onUnmounted(() => {
    if (signatureInterval) clearInterval(signatureInterval);
    if (resizeObserver) {
      resizeObserver.disconnect();
      resizeObserver = null;
    }
    const bufferEl = document.querySelector('.console-buffer');
    if (bufferEl) {
      bufferEl.removeEventListener('scroll', handleBufferScroll);
    }
});
</script>

<template>
  <div class="console-content">
    <div class="console-main">
      <div class="console-toolbar">
        <div class="console-toolbar-group">
          <div
            class="console-toolbar-button"
            :class="{ active: isScratchpad }"
            :title="__('Toggle Scratchpad mode')"
            @click="toggleScratchpad"
          >
            <div class="codicon codicon-edit" />
            <span>{{ __('Scratchpad') }}</span>
          </div>
        </div>

        <template v-if="isScratchpad">
          <div class="console-toolbar-group">
            <div
              class="console-toolbar-button menu btn-scratchpad-run"
              :class="{ disabled: loading }"
              :title="loading ? __('Evaluating expression...') : __('Run (Ctrl+Enter / Cmd+Enter / Ctrl+R)')"
              @click="!loading && executeScratchpad()"
            >
              <div
                v-if="loading"
                class="metro-spinner"
              />
              <div
                v-else
                class="codicon codicon-play"
                style="color: #3858e9;"
              />
            </div>
          </div>
          <div class="console-toolbar-group">
            <div
              class="console-toolbar-button menu"
              :title="__('Save snippet to Library')"
              @click="handleSaveScratchpadSnippet"
            >
              <div
                class="codicon codicon-save"
                style="color: #3858e9;"
              />
            </div>
          </div>
        </template>

        <div class="console-toolbar-group">
          <div
            class="console-toolbar-button"
            :title="__('Clear')"
            @click="isScratchpad ? handleClearScratchpad() : clearConsole()"
          >
            <div class="codicon codicon-circle-slash" />
            <span>{{ __('Clear') }}</span>
          </div>
        </div>

        <div class="console-toolbar-group">
          <div
            class="console-toolbar-button"
            :title="__('Format code (Shift+Alt+F)')"
            @click="handleFormatCode"
          >
            <div class="codicon codicon-json" />
            <span>{{ __('Format') }}</span>
          </div>
        </div>
        <div class="console-toolbar-group">
          <PaginatedSearchDropdown 
            v-model="selectedUser"
            :label="__('User')"
            icon="codicon-account"
            action="expressionlab_search_users"
            items-key="users"
            label-key="display_name"
            :initial-items="initialUsers"
            :placeholder="__('Search users...')"
          />
        </div>
        <div
          class="console-toolbar-group"
        >
          <PaginatedSearchDropdown 
            v-if="isMultisite"
            v-model="selectedSite"
            :label="__('Site')"
            icon="codicon-globe"
            action="expressionlab_search_sites"
            items-key="sites"
            label-key="name"
            :initial-items="initialSites"
            :placeholder="__('Search by domain/path...')"
          />
          <div
            v-else
            class="searchable-dropdown-container disabled"
            style="cursor: default;"
          >
            <div class="searchable-dropdown-trigger">
              <img
                v-if="initialSites.length > 0 && initialSites[0].avatar"
                :src="initialSites[0].avatar"
                class="option-avatar"
                :alt="__('Site Icon')"
              >
              <div
                v-else
                class="codicon codicon-globe"
              />
              <span class="label">{{ __('Current site') }}</span>   
              <span class="codicon codicon-chevron-down" />           
            </div>
          </div>
        </div>
        <div class="console-toolbar-group">
          <div
            class="console-toolbar-button"
            @click="uiStore.openModal('library')"
          >
            <div class="codicon codicon-library" />
            <span>{{ __('Library') }}</span>
          </div>
        </div>
        <div class="console-toolbar-group pull-right">
          <div
            class="console-toolbar-button menu"
            :class="{
              active: isScratchpad && isScratchpadOutputVisible,
              disabled: !isScratchpad
            }"
            :title="isScratchpad ? __('Toggle output panel') : __('Toggle output panel (Scratchpad mode only)')"
            @click="isScratchpad && (isScratchpadOutputVisible = !isScratchpadOutputVisible)"
          >
            <div class="codicon codicon-layout-panel" />
          </div>
          <div
            class="console-toolbar-button menu"
            :class="{ active: isSidebarVisible }"
            :title="__('Toggle sidebar outline')"
            @click="toggleSidebar"
          >
            <div class="codicon codicon-layout-sidebar-right" />
          </div>
          <div
            class="console-toolbar-button menu"
            :title="__('About Expression Lab')"
            @click="uiStore.openModal('about')"
          >
            <div class="codicon codicon-info" />
          </div>
        </div>
      </div>
      <div
        class="console-buffer"
        :class="{
          'scratchpad-active': isScratchpad,
          'sidebar-hidden': !isSidebarVisible
        }"
      >
        <ul
          v-show="!isScratchpad"
          class="entries"
        >
          <!-- History Log -->
          <ConsoleLog 
            :entries="history" 
            @edit-entry="handleEditEntry" 
            @run-code="handleRunCode"
          />
          
          <ConsoleEditor
            ref="editorRef"
            :loading="loading"
            :completion-items="outlineStore.outlineFlat"
            :chain-data="outlineStore.outlineChained"
            mode="repl"
            @execute="handleExecute"
            @clear="clearConsole"
            @history-up="handleHistoryNav('up')"
            @history-down="handleHistoryNav('down')"
          />
        </ul>

        <!-- Scratchpad Workspace -->
        <div
          v-show="isScratchpad"
          class="scratchpad-workspace"
          :class="{ 'auto-adjusting': isAutoAdjusting }"
        >
          <div
            class="scratchpad-editor-section"
            :style="isScratchpadOutputVisible ? { height: `${scratchpadEditorRatio}%` } : { height: '100%' }"
          >
            <ConsoleEditor
              ref="scratchpadEditorRef"
              :loading="false"
              :completion-items="outlineStore.outlineFlat"
              :chain-data="outlineStore.outlineChained"
              mode="scratchpad"
              @execute="executeScratchpad"
            />
          </div>

          <div
            v-if="isScratchpadOutputVisible"
            class="scratchpad-splitter"
            :class="{ dragging: isDraggingSplitter }"
            :title="__('Drag to resize / Double-click to auto-fit')"
            @mousedown="startSplitterDrag"
          >
            <div class="splitter-handle" />
          </div>

          <div
            v-show="isScratchpadOutputVisible"
            class="scratchpad-output-section"
            :style="{ height: `calc(${100 - scratchpadEditorRatio}% - 6px)` }"
          >
            <ScratchpadOutput
              :result="scratchpadResult"
              :loading="loading"
              @clear-output="clearScratchpadOutput"
              @close="isScratchpadOutputVisible = false"
              @tab-change="handleScratchpadTabChange"
              @visualization-rendered="handleVisualizationRendered"
            />
          </div>
        </div>
        
        <ConsoleSidebar 
          v-show="isSidebarVisible"
          :outline-tree="outlineStore.outlineTree" 
          :loading="outlineStore.loading" 
          @item-click="handleSidebarItemClick"
          @clear-console="clearConsole"
        />
      </div>
    </div>
  </div>
  <div
    class="editor-footer"
    :style="!enableSandbox ? { 'padding-right': '4px' } : {}"
  >
    <div class="left">
      <div 
        class="footer-toggle-btn" 
        :title="showSensitive ? __('Hide Sensitive Info') : __('Show Sensitive Info')"
        @click="showSensitive = !showSensitive"
      >
        <div :class="['codicon', showSensitive ? 'codicon-eye' : 'codicon-eye-closed']" />
      </div>
      <div 
        class="footer-badge el-server-badge"
        :title="`${__('Server:')} ${footerInfo.server_ip}`"
      >
        <span class="badge-label">{{ __('Server:') }}</span>
        <strong class="badge-value">{{ showSensitive ? footerInfo.server_ip : '***.***.***.***' }}</strong>
      </div>
      <div 
        class="footer-badge el-user-badge"
        :title="`${__('User:')} ${footerInfo.user_ip}`"
      >
        <span class="badge-label">{{ __('User:') }}</span>
        <strong class="badge-value">{{ showSensitive ? footerInfo.user_ip : '***.***.***.***' }}</strong>
      </div>
      <div 
        class="footer-badge el-php-badge"
        :title="`${__('PHP:')} ${footerInfo.php_version}`"
      >
        <span class="badge-label">{{ __('PHP:') }}</span>
        <strong class="badge-value">{{ footerInfo.php_version }}</strong>
      </div>
      <div 
        class="footer-badge el-wp-badge"
        :title="`${__('WP:')} ${footerInfo.wp_version}`"
      >
        <span class="badge-label">{{ __('WP:') }}</span>
        <strong class="badge-value">{{ footerInfo.wp_version }}</strong>
      </div>
      <div 
        class="footer-badge el-debug-badge"
        :title="`${__('Debug:')} ${footerInfo.debug_enabled ? __('Enabled') : __('Disabled')}`"
      >
        <span class="badge-label">{{ __('Debug:') }}</span>
        <strong class="badge-value">{{ footerInfo.debug_enabled ? __('Enabled') : __('Disabled') }}</strong>
      </div>
    </div>
    <div class="right">
      <div 
        class="footer-badge write-protection-badge el-database-badge"
        :title="`${__('Database:')} ${footerInfo.database_readonly ? __('Locked') : __('Unlocked')}`"
      >
        <div class="codicon codicon-database resource-icon" />
        <div :class="['codicon', 'lock-icon', footerInfo.database_readonly ? 'codicon-lock' : 'codicon-unlock']" /> 
        <span class="badge-label">{{ __('Database:') }}</span>
        <strong class="badge-status">{{ footerInfo.database_readonly ? __('Locked') : __('Unlocked') }}</strong>
      </div>
      <div 
        class="footer-badge write-protection-badge el-files-badge"
        :title="`${__('Files:')} ${footerInfo.filesystem_readonly ? __('Locked') : __('Unlocked')}`"
      >
        <div class="codicon codicon-files resource-icon" />
        <div :class="['codicon', 'lock-icon', footerInfo.filesystem_readonly ? 'codicon-lock' : 'codicon-unlock']" /> 
        <span class="badge-label">{{ __('Files:') }}</span>
        <strong class="badge-status">{{ footerInfo.filesystem_readonly ? __('Locked') : __('Unlocked') }}</strong>
      </div>
      <div 
        class="footer-badge write-protection-badge el-network-badge"
        :title="`${__('Network:')} ${footerInfo.network_readonly ? __('Locked') : __('Unlocked')}`"
      >
        <div class="codicon codicon-globe resource-icon" />
        <div :class="['codicon', 'lock-icon', footerInfo.network_readonly ? 'codicon-lock' : 'codicon-unlock']" /> 
        <span class="badge-label">{{ __('Network:') }}</span>
        <strong class="badge-status">{{ footerInfo.network_readonly ? __('Locked') : __('Unlocked') }}</strong>
      </div>
      <div
        v-if="enableSandbox"
        class="footer-badge signature-verification-badge"
      >
        <div 
          :class="['signature-indicator', `state-${signatureState}`]" 
          :style="signatureState === 'valid' ? { '--progress': signatureProgress } : {}"
        />
        <template v-if="signatureState === 'requesting'">
          {{ __('Requesting signature...') }}
        </template>
        <template v-else-if="signatureState === 'expired'">
          {{ __('Signature expired') }}
        </template>
        <template v-else>
          {{ __('Signed') }} <strong>{{ Math.floor(signatureTimeRemaining / 60).toString().padStart(2, '0') }}:{{ (signatureTimeRemaining % 60).toString().padStart(2, '0') }}</strong>
        </template>
      </div>
      <div
        v-else
        class="footer-badge sandbox-disabled-badge"
        :title="__('Sandbox disabled')"
      >
        <div class="codicon codicon-workspace-untrusted" />
        <span class="badge-text">{{ __('Sandbox disabled') }}</span>
      </div>
    </div>
  </div> 
</template>
