<script setup>
import { ref, onMounted, onUnmounted, nextTick, computed, watch } from 'vue';
import { useUiStore } from '../stores/ui';
import { useOutlineStore } from '../stores/outline.js';
import { handleFetch } from '../lib/api/client.js';
import { debugLog, __ } from '../lib/helpers.js';
import ConsoleSidebar from '../components/console/ConsoleSidebar.vue';
import ConsoleLog from '../components/console/ConsoleLog.vue';
import ConsoleEditor from '../components/console/ConsoleEditor.vue';
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
    if (code && editorRef.value) {
      editorRef.value.insertSnippet(code, { replaceWord: true });
      uiStore.triggerSnippetInsert(null);
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
  if (customText !== null) {
      uiStore.setConsoleMode('evaluate');
      if (editorRef.value) {
        editorRef.value.setEditorValue(customText);
        nextTick(() => {
          editorRef.value.focus();
          editorRef.value.moveCursorToEnd();
        });
      }
      return;
  }
  const item = history.value[index];
  if (item && editorRef.value) {
    let text = item.input;
    if (type === 'output') {
        text = (typeof item.output === 'object' && item.output !== null) 
               ? JSON.stringify(item.output, null, 2) 
               : String(item.output); 
    }

    editorRef.value.setEditorValue(text);
    nextTick(() => {
      editorRef.value.focus();
      editorRef.value.moveCursorToEnd();
    });
  }
};

const handleRunCode = (code) => {
  uiStore.setConsoleMode('evaluate');
  const text = (typeof code === 'object' && code !== null) 
          ? JSON.stringify(code, null, 2) 
          : String(code);

  handleExecute(text);

  if (editorRef.value) {
    editorRef.value.setEditorValue('');
    nextTick(() => {
      editorRef.value.focus();
    });
  }
};

const handleSidebarItemClick = (item) => {
    if (editorRef.value && item && item.insertText) {
        editorRef.value.insertSnippet(item.insertText);
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
            @click="clearConsole"
          >
            <div class="codicon codicon-circle-slash" />
            <span>{{ __('Clear') }}</span>
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
            @click="uiStore.openModal('about')"
          >
            <div class="codicon codicon-info" />
          </div>
        </div>
      </div>
      <div class="console-buffer">
        <ul class="entries">
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
            @execute="handleExecute"
            @clear="clearConsole"
            @history-up="handleHistoryNav('up')"
            @history-down="handleHistoryNav('down')"
          />
        </ul>
        
        <ConsoleSidebar 
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
