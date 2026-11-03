<script setup>
import { ref, onMounted, onUnmounted, nextTick, computed, watch } from 'vue';
import { useUiStore } from '../stores/ui';
import { useOutlineStore } from '../stores/outline.js';
import { useTabsStore } from '../stores/tabs.js';
import { useSettingsStore } from '../stores/settings.js';
import { debugLog, __, hasAddons } from '../lib/helpers.js';
import ConsoleTabBar from '../components/console/ConsoleTabBar.vue';
import ConsoleSession from '../components/console/ConsoleSession.vue';
import ConsoleSidebar from '../components/console/ConsoleSidebar.vue';
import PaginatedSearchDropdown from '../components/ui/PaginatedSearchDropdown.vue';
import ExpressionLabIcon from '../components/icons/ExpressionLabIcon.vue';

let signatureInterval = null;

const uiStore = useUiStore();
const outlineStore = useOutlineStore();
const tabsStore = useTabsStore();
const settingsStore = useSettingsStore();

const showSensitive = ref(true);
const isMultisite = ref(window.el_settings.multisite);
const initialUsers = ref(window.el_settings.site?.users ?? []);
const initialSites = ref(window.el_settings.site?.sites ?? []);
const signatureDuration = window.el_settings.settings?.signature_duration ?? 30;
const signatureTimeRemaining = ref(0);
const signatureProgress = ref(0);
const isSidebarVisible = computed(() => settingsStore.isSidebarVisible);
const enableSandbox = window.el_settings.settings?.enable_sandbox;
const isPlayground = Boolean(window.el_settings.settings?.is_playground || window.el_settings.server?.is_playground);

const sessionRefs = ref({});

const activeTab = computed(() => tabsStore.activeTab);
const isScratchpad = computed(() => activeTab.value?.mode === 'scratchpad');
const isNotebook = computed(() => activeTab.value?.mode === 'notebook');
const activeTabLoading = computed(() => Boolean(activeTab.value?.isEvaluating));
const isScratchpadOutputVisible = computed(() => Boolean(activeTab.value?.isScratchpadOutputVisible));

const runButtonTitle = computed(() => {
  if (activeTabLoading.value) return __('Evaluating expression...');
  if (isNotebook.value) return __('Run all cells (Ctrl+R)');
  if (isScratchpad.value) return __('Run (Ctrl+Enter / Cmd+Enter / Ctrl+R)');
  return __('Run expression (Enter)');
});

const saveButtonTitle = computed(() => {
  if (isNotebook.value) return __('Save Notebook (.elab) (Ctrl+S / Cmd+S)');
  return __('Save snippet to Library (Ctrl+S / Cmd+S)');
});

const activeUser = computed({
  get: () => activeTab.value?.selectedUser ?? null,
  set: (val) => tabsStore.updateTabContext(tabsStore.activeTabId, { user_id: val }),
});

const activeSite = computed({
  get: () => activeTab.value?.selectedSite ?? null,
  set: (val) => tabsStore.updateTabContext(tabsStore.activeTabId, { site_id: val }),
});

const getActiveSession = () => {
  return sessionRefs.value[tabsStore.activeTabId] ?? null;
};

const isMac =
  typeof navigator !== 'undefined' &&
  Boolean(
    navigator.userAgentData?.platform
      ? /mac/i.test(navigator.userAgentData.platform)
      : /Mac|iPhone|iPad|iPod/i.test(navigator.platform || navigator.userAgent || '')
  );
const modKey = isMac ? '⌘' : 'Ctrl';

const canUndoCellAction = computed(() => isNotebook.value && Boolean(activeTab.value?.canUndoCell));
const canRedoCellAction = computed(() => isNotebook.value && Boolean(activeTab.value?.canRedoCell));
const canSearch = computed(() => {
  const mode = activeTab.value?.mode;
  return mode === 'scratchpad' || (mode === 'notebook' && hasAddons());
});

const handleUndoCellAction = () => {
  if (!canUndoCellAction.value) return;
  getActiveSession()?.undoCellAction?.();
  isFileDropdownOpen.value = false;
};

const handleRedoCellAction = () => {
  if (!canRedoCellAction.value) return;
  getActiveSession()?.redoCellAction?.();
  isFileDropdownOpen.value = false;
};

const handleOpenFileSearch = (withReplace = false) => {
  if (!canSearch.value) return;
  isFileDropdownOpen.value = false;
  getActiveSession()?.openSearch?.(withReplace);
};

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
  (payload) => {
    if (payload) {
      const code = typeof payload === 'object' && payload !== null && payload.code !== undefined ? payload.code : payload;
      const mode = typeof payload === 'object' && payload !== null && payload.mode ? payload.mode : 'insert';
      const session = getActiveSession();
      if (session) {
        if (mode === 'replace' && typeof session.replaceContent === 'function') {
          session.replaceContent(code);
        } else {
          session.insertSnippet(code, { replaceWord: true });
        }
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

const handleCreateConsoleTab = () => {
  tabsStore.createTab({ mode: 'repl' });
};

const handleCreateScratchpadTab = () => {
  tabsStore.createTab({ mode: 'scratchpad' });
};

const handleExecuteActiveScratchpad = () => {
  const session = getActiveSession();
  if (typeof session?.runPrimaryAction === 'function') {
    session.runPrimaryAction();
  } else {
    session?.executeScratchpad?.();
  }
};

const handleSavePrimary = () => {
  const session = getActiveSession();
  if (typeof session?.savePrimaryAction === 'function') {
    session.savePrimaryAction();
  } else if (isScratchpad.value) {
    handleSaveScratchpadSnippet(activeTab.value?.scratchpadCode || '');
  }
};

const handleOpenFile = () => {
  if (typeof window !== 'undefined' && typeof window.__elOpenNotebookFile === 'function') {
    window.__elOpenNotebookFile({
      tabsStore,
      sessionRefs: sessionRefs.value,
    });
  } else {
    getActiveSession()?.openNotebookFile?.();
  }
};

const handleSaveScratchpadSnippet = (code) => {
  uiStore.openModal('library', { draftCode: code });
};

const handleClear = () => {
  getActiveSession()?.clearSession();
};

const handleFormatCode = () => {
  getActiveSession()?.formatCode();
};

const toggleSidebar = () => {
  settingsStore.toggleSidebarVisible();
};

const toggleOutputPanel = () => {
  if (isScratchpad.value && activeTab.value) {
    tabsStore.updateTabScratchpad(tabsStore.activeTabId, {
      isScratchpadOutputVisible: !activeTab.value.isScratchpadOutputVisible,
    });
  }
};

const handleSidebarItemClick = (item) => {
  if (item?.insertText) {
    getActiveSession()?.insertSnippet(item.insertText);
  }
};

const isFileDropdownOpen = ref(false);
const fileDropdownBtnRef = ref(null);
const fileDropdownMenuRef = ref(null);
const fileDropdownStyle = ref({});

const updateFileDropdownPosition = () => {
  if (!fileDropdownBtnRef.value) return;
  const rect = fileDropdownBtnRef.value.getBoundingClientRect();
  fileDropdownStyle.value = {
    position: 'fixed',
    top: `${rect.bottom + 2}px`,
    left: `${rect.left}px`,
    zIndex: 100000,
  };
};

const toggleFileDropdown = () => {
  isFileDropdownOpen.value = !isFileDropdownOpen.value;
  if (isFileDropdownOpen.value) {
    nextTick(updateFileDropdownPosition);
  }
};

const handleOpenFileSettings = () => {
  isFileDropdownOpen.value = false;
  uiStore.openModal('settings');
};

const handleOpenFileAbout = () => {
  isFileDropdownOpen.value = false;
  uiStore.openModal('about');
};

const isToolsDropdownOpen = ref(false);
const toolsDropdownBtnRef = ref(null);
const toolsDropdownMenuRef = ref(null);
const toolsDropdownStyle = ref({});

const updateToolsDropdownPosition = () => {
  if (!toolsDropdownBtnRef.value) return;
  const rect = toolsDropdownBtnRef.value.getBoundingClientRect();
  toolsDropdownStyle.value = {
    position: 'fixed',
    top: `${rect.bottom + 2}px`,
    left: `${rect.left}px`,
    zIndex: 100000,
  };
};

const toggleToolsDropdown = () => {
  isToolsDropdownOpen.value = !isToolsDropdownOpen.value;
  if (isToolsDropdownOpen.value) {
    nextTick(updateToolsDropdownPosition);
  }
};

const handleOpenHttpRequestBuilder = () => {
  isToolsDropdownOpen.value = false;
  uiStore.openModal('http-builder');
};

const handleOpenDatabaseBuilder = () => {
  isToolsDropdownOpen.value = false;
  uiStore.openModal('database-builder');
};

const handleOpenEntityBuilder = () => {
  isToolsDropdownOpen.value = false;
  uiStore.openModal('entity-builder');
};

const handleOpenFilesystemInspector = () => {
  isToolsDropdownOpen.value = false;
  uiStore.openModal('filesystem-inspector');
};

const handleWindowClick = (event) => {
  if (
    isToolsDropdownOpen.value &&
    toolsDropdownMenuRef.value &&
    !toolsDropdownMenuRef.value.contains(event.target) &&
    toolsDropdownBtnRef.value &&
    !toolsDropdownBtnRef.value.contains(event.target)
  ) {
    isToolsDropdownOpen.value = false;
  }
  if (
    isFileDropdownOpen.value &&
    fileDropdownMenuRef.value &&
    !fileDropdownMenuRef.value.contains(event.target) &&
    fileDropdownBtnRef.value &&
    !fileDropdownBtnRef.value.contains(event.target)
  ) {
    isFileDropdownOpen.value = false;
  }
};

const handleGlobalKeydown = (e) => {
  if (e.key === 'Escape') {
    if (isFileDropdownOpen.value) {
      isFileDropdownOpen.value = false;
      return;
    }
    if (isToolsDropdownOpen.value) {
      isToolsDropdownOpen.value = false;
      return;
    }
  }

  if ((e.ctrlKey || e.metaKey) && !e.altKey && !e.shiftKey) {
    if (e.key === 's' || e.key === 'S') {
      e.preventDefault();
      handleSavePrimary();
      return;
    }
    if ((e.key === 'o' || e.key === 'O') && hasAddons()) {
      e.preventDefault();
      handleOpenFile();
      return;
    }
    if (e.key === 'f' || e.key === 'F') {
      if (canSearch.value) {
        e.preventDefault();
        getActiveSession()?.openSearch?.(false);
        return;
      }
    }
    if (e.key === 'h' || e.key === 'H') {
      if (canSearch.value) {
        e.preventDefault();
        getActiveSession()?.openSearch?.(true);
        return;
      }
    }
  }

  if (!e.altKey || e.ctrlKey || e.metaKey) return;

  if (e.key === 't' || e.key === 'T' || e.key === 'n' || e.key === 'N') {
    e.preventDefault();
    tabsStore.createTab();
    return;
  }

  if (e.key === 'w' || e.key === 'W') {
    e.preventDefault();
    tabsStore.requestCloseTab(tabsStore.activeTabId);
    return;
  }

  if (e.key >= '1' && e.key <= '9') {
    const targetIndex = parseInt(e.key, 10) - 1;
    if (tabsStore.tabs[targetIndex]) {
      e.preventDefault();
      tabsStore.setActiveTab(tabsStore.tabs[targetIndex].id);
    }
    return;
  }

  if (e.key === 'ArrowLeft') {
    const idx = tabsStore.activeTabIndex;
    if (idx > 0) {
      e.preventDefault();
      tabsStore.setActiveTab(tabsStore.tabs[idx - 1].id);
    }
    return;
  }

  if (e.key === 'ArrowRight') {
    const idx = tabsStore.activeTabIndex;
    if (idx < tabsStore.tabs.length - 1) {
      e.preventDefault();
      tabsStore.setActiveTab(tabsStore.tabs[idx + 1].id);
    }
  }
};

const handleBeforeUnload = (event) => {
  const hasDirtyNotebook = tabsStore.tabs.some(
    (t) => t && t.mode === 'notebook' && t.notebookDirty
  );
  if (hasDirtyNotebook) {
    event.preventDefault();
    event.returnValue = '';
  }
};

onMounted(() => {
  signatureInterval = setInterval(updateSignatureTime, 1000);
  debugLog('Console component mounted', { enableSandbox, settings: window.el_settings });
  outlineStore.loadOutline();

  if (typeof window !== 'undefined') {
    window.addEventListener('keydown', handleGlobalKeydown);
    window.addEventListener('click', handleWindowClick);
    window.addEventListener('resize', updateToolsDropdownPosition);
    window.addEventListener('resize', updateFileDropdownPosition);
    window.addEventListener('beforeunload', handleBeforeUnload);
  }

  nextTick(() => {
    getActiveSession()?.focus?.();
  });
});

onUnmounted(() => {
  if (signatureInterval) clearInterval(signatureInterval);
  if (typeof window !== 'undefined') {
    window.removeEventListener('keydown', handleGlobalKeydown);
    window.removeEventListener('click', handleWindowClick);
    window.removeEventListener('resize', updateToolsDropdownPosition);
    window.removeEventListener('resize', updateFileDropdownPosition);
    window.removeEventListener('beforeunload', handleBeforeUnload);
  }
});
</script>

<template>
  <div class="console-content">
    <div class="console-main">
      <!-- 1. Top Action Toolbar -->
      <div class="console-toolbar">
        <div class="console-toolbar-group group-file">
          <div
            ref="fileDropdownBtnRef"
            class="console-toolbar-button menu-dropdown-btn"
            :class="{ active: isFileDropdownOpen }"
            :title="__('Expression Lab')"
            @click.stop="toggleFileDropdown"
          >
            <ExpressionLabIcon style="width: 26px; height: 26px; flex-shrink: 0; margin-right:4px;" />
            <span>{{ __('Expression Lab') }}</span>
            <div class="codicon codicon-chevron-down tools-chevron" />
          </div>
        </div>

        <div class="console-toolbar-group group-file-actions">
          <div
            v-if="hasAddons()"
            class="console-toolbar-button menu btn-open-notebook"
            :title="__('Open Notebook (.elab)... (Ctrl+O / Cmd+O)')"
            @click="handleOpenFile"
          >
            <div class="codicon codicon-folder-opened" />
          </div>
          <div
            class="console-toolbar-button menu btn-scratchpad-save"
            :title="saveButtonTitle"
            @click="handleSavePrimary"
          >
            <div
              class="codicon codicon-save"
              style="color: #3858e9;"
            />
          </div>
        </div>

        <div class="console-toolbar-group group-scratchpad-actions">
          <div
            class="console-toolbar-button menu btn-scratchpad-run"
            :class="{
              loading: activeTabLoading
            }"
            :title="runButtonTitle"
            @click="!activeTabLoading && handleExecuteActiveScratchpad()"
          >
            <div
              v-if="activeTabLoading"
              class="metro-spinner"
            />
            <div
              v-else
              class="codicon codicon-play"
              style="color: #3858e9;"
            />
          </div>
          <div
            class="console-toolbar-button menu"
            :title="isNotebook ? __('Clear all cell outputs') : __('Clear session')"
            @click="handleClear"
          >
            <div class="codicon codicon-circle-slash" />
          </div>
          <div
            class="console-toolbar-button menu"
            :title="isNotebook ? __('Format all code cells (Shift+Alt+F)') : __('Format code (Shift+Alt+F)')"
            @click="handleFormatCode"
          >
            <div class="codicon codicon-json" />
          </div>
        </div>

        <div class="console-toolbar-group group-users">
          <PaginatedSearchDropdown
            v-model="activeUser"
            :label="__('User')"
            icon="codicon-account"
            action="expressionlab_search_users"
            items-key="users"
            label-key="display_name"
            :initial-items="initialUsers"
            :placeholder="__('Search users...')"
          />
        </div>

        <div class="console-toolbar-group group-sites">
          <PaginatedSearchDropdown
            v-if="isMultisite"
            v-model="activeSite"
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

        <div class="console-toolbar-group group-tools-library">
          <div
            ref="toolsDropdownBtnRef"
            class="console-toolbar-button menu-dropdown-btn"
            :class="{ active: isToolsDropdownOpen }"
            :title="__('Open tools')"
            @click.stop="toggleToolsDropdown"
          >
            <div class="codicon codicon-tools" />
            <span>{{ __('Tools') }}</span>
            <div class="codicon codicon-chevron-down tools-chevron" />
          </div>
          <div
            class="console-toolbar-button"
            :title="__('Library')"
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
            @click="toggleOutputPanel"
          >
            <div class="custom-icon custom-icon-panel-bottom" />
          </div>
          <div
            class="console-toolbar-button menu"
            :class="{ active: isSidebarVisible }"
            :title="__('Toggle sidebar outline')"
            @click="toggleSidebar"
          >
            <div class="custom-icon custom-icon-panel-right" />
          </div>
        </div>
      </div>

      <!-- 2. Console Buffer and Workspace -->
      <div
        class="console-buffer"
        :class="{
          'scratchpad-active': isScratchpad || isNotebook,
          'notebook-active': isNotebook,
          'sidebar-hidden': !isSidebarVisible
        }"
      >
        <div class="console-sessions-wrapper">
          <ConsoleTabBar />

          <ConsoleSession
            v-for="tab in tabsStore.tabs"
            v-show="tab.id === tabsStore.activeTabId"
            :key="tab.id"
            :ref="(el) => { if (el) sessionRefs[tab.id] = el; else delete sessionRefs[tab.id]; }"
            :tab-id="tab.id"
            :is-active="tab.id === tabsStore.activeTabId"
            :outline-flat="outlineStore.outlineFlat"
            :outline-chained="outlineStore.outlineChained"
            @save-snippet="handleSaveScratchpadSnippet"
          />
        </div>

        <ConsoleSidebar
          v-show="isSidebarVisible"
          :outline-tree="outlineStore.outlineTree"
          :loading="outlineStore.loading"
          @item-click="handleSidebarItemClick"
          @clear-console="handleClear"
        />
      </div>
    </div>
  </div>

  <!-- 4. Global Footer -->
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
        v-else-if="!isPlayground"
        class="footer-badge sandbox-disabled-badge"
        :title="__('Sandbox disabled')"
      >
        <div class="codicon codicon-workspace-untrusted" />
        <span class="badge-text">{{ __('Sandbox disabled') }}</span>
      </div>
    </div>
  </div>

  <!-- File Dropdown Menu (Teleported to avoid clipping) -->
  <Teleport to="body">
    <div
      v-if="isFileDropdownOpen"
      ref="fileDropdownMenuRef"
      class="console-tab-context-menu console-file-dropdown-menu"
      :style="fileDropdownStyle"
    >
      <div
        v-if="hasAddons()"
        class="tab-menu-item"
        :class="{ disabled: !canUndoCellAction }"
        @click="handleUndoCellAction"
      >
        <span class="codicon codicon-discard" />
        <span>{{ __('Undo cell action') }}</span>
        <span class="menu-shortcut">{{ modKey }}+Z</span>
      </div>
      <div
        v-if="hasAddons()"
        class="tab-menu-item"
        :class="{ disabled: !canRedoCellAction }"
        @click="handleRedoCellAction"
      >
        <span class="codicon codicon-redo" />
        <span>{{ __('Redo cell action') }}</span>
        <span class="menu-shortcut">{{ modKey }}+Shift+Z</span>
      </div>

      <div 
        v-if="hasAddons()"
        class="tab-menu-separator" 
      />

      <div
        class="tab-menu-item"
        :class="{ disabled: !canSearch }"
        @click="handleOpenFileSearch(false)"
      >
        <span class="codicon codicon-search" />
        <span>{{ __('Search') }}</span>
        <span class="menu-shortcut">{{ modKey }}+F</span>
      </div>
      <div
        class="tab-menu-item"
        :class="{ disabled: !canSearch }"
        @click="handleOpenFileSearch(true)"
      >
        <span class="codicon codicon-replace" />
        <span>{{ __('Replace') }}</span>
        <span class="menu-shortcut">{{ modKey }}+H</span>
      </div>

      <div class="tab-menu-separator" />

      <div
        class="tab-menu-item"
        @click="handleOpenFileSettings"
      >
        <span class="codicon codicon-settings-gear" />
        <span>{{ __('Settings') }}</span>
      </div>
      <div
        class="tab-menu-item"
        @click="handleOpenFileAbout"
      >
        <span class="codicon codicon-info" />
        <span>{{ __('About') }}</span>
      </div>
    </div>
  </Teleport>

  <!-- Tools Dropdown Menu (Teleported to avoid clipping) -->
  <Teleport to="body">
    <div
      v-if="isToolsDropdownOpen"
      ref="toolsDropdownMenuRef"
      class="console-tab-context-menu console-tools-dropdown-menu"
      :style="toolsDropdownStyle"
    >
      <div
        class="tab-menu-item"
        @click="handleOpenHttpRequestBuilder"
      >
        <span class="codicon codicon-globe" />
        <span>{{ __('HTTP Request Builder') }}</span>
      </div>
      <div
        class="tab-menu-item"
        @click="handleOpenDatabaseBuilder"
      >
        <span class="codicon codicon-database" />
        <span>{{ __('Database Query Builder') }}</span>
      </div>
      <div
        class="tab-menu-item"
        @click="handleOpenEntityBuilder"
      >
        <span class="codicon codicon-layers" />
        <span>{{ __('Entity Query Builder') }}</span>
      </div>
      <div
        class="tab-menu-item"
        @click="handleOpenFilesystemInspector"
      >
        <span class="codicon codicon-folder" />
        <span>{{ __('Filesystem Inspector') }}</span>
      </div>
    </div>
  </Teleport>
</template>
