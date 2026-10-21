<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { useUiStore } from '../../stores/ui.js';
import { __ } from '../../lib/helpers.js';
import {
  parseUrlAndParams,
  buildFullUrl,
  generateElscriptHttpRequest
} from '../../lib/http-builder-generator.js';
import { EditorState, Compartment } from '@codemirror/state';
import { EditorView, keymap } from '@codemirror/view';
import { defaultKeymap, history, historyKeymap, indentWithTab } from '@codemirror/commands';
import { bracketMatching, syntaxHighlighting, defaultHighlightStyle } from '@codemirror/language';
import { vsCodeLight } from '@fsegurai/codemirror-theme-bundle';
import { linter } from '@codemirror/lint';
import { json, jsonParseLinter } from '@codemirror/lang-json';
import { html } from '@codemirror/lang-html';
import { elscript } from '../../lib/codemirror/elscript.js';
import { formatExpressionLab } from '../../lib/codemirror/formatter.js';

const props = defineProps({
  closeOnOutsideClick: {
    type: Boolean,
    default: false
  }
});

const uiStore = useUiStore();
const isOpen = computed(() => uiStore.activeModal === 'http-builder');

const HTTP_METHODS = ['GET', 'POST', 'HEAD'];

// Active method and URL
const method = ref('GET');
const isMethodDropdownOpen = ref(false);
const methodDropdownRef = ref(null);
const url = ref('https://example.com');
const activeTab = ref('params');

const toggleMethodDropdown = () => {
  isMethodDropdownOpen.value = !isMethodDropdownOpen.value;
};

const selectMethod = (newMethod) => {
  method.value = newMethod;
  isMethodDropdownOpen.value = false;
};

const handleDocumentClick = (e) => {
  if (
    isMethodDropdownOpen.value &&
    methodDropdownRef.value &&
    !methodDropdownRef.value.contains(e.target)
  ) {
    isMethodDropdownOpen.value = false;
  }
};

onMounted(() => {
  document.addEventListener('click', handleDocumentClick);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', handleDocumentClick);
  destroyJsonEditor();
  destroyRawEditor();
  destroyPreviewEditor();
});

// Query parameters
const params = ref([
  { enabled: true, key: '', value: '' }
]);

// Authorization
const auth = ref({
  type: 'none',
  bearerToken: '',
  basicUser: '',
  basicPass: '',
  apiKeyName: '',
  apiKeyValue: '',
  apiKeyAddTo: 'header'
});

// Headers
const headers = ref([
  { enabled: true, key: '', value: '' }
]);

// Body
const body = ref({
  type: 'none',
  jsonText: '{\n  "key": "value"\n}',
  formData: [
    { enabled: true, key: '', value: '' }
  ],
  rawText: '',
  rawContentType: 'text/plain'
});

// Settings
const settings = ref({
  timeout: 10,
  userAgent: '',
  httpVersion: '1.1',
  blocking: true,
  compress: false,
  decompress: true,
  cookies: [
    { enabled: true, key: '', value: '' }
  ]
});

// Code preview & copy status
const isCopied = ref(false);

// Bidirectional URL <-> Params sync guard
let isInternalUrlUpdate = false;
let isInternalParamsUpdate = false;

const syncUrlFromState = () => {
  isInternalUrlUpdate = true;
  const { baseUrl } = parseUrlAndParams(url.value);
  url.value = buildFullUrl(baseUrl || 'https://example.com', params.value, auth.value);
  nextTick(() => {
    isInternalUrlUpdate = false;
  });
};

// Watch URL changes from manual input in the URL bar
watch(url, (newUrl) => {
  if (isInternalUrlUpdate) return;

  const { params: parsedParams } = parseUrlAndParams(newUrl);

  // If auth is using an API key in query, don't contaminate the params table with it
  const isAuthQuery = auth.value.type === 'apikey' && auth.value.apiKeyAddTo === 'query' && Boolean(auth.value.apiKeyName.trim());
  const authKey = isAuthQuery ? auth.value.apiKeyName.trim() : null;

  const regularParams = [];
  for (const p of parsedParams) {
    if (authKey && p.key === authKey) {
      if (auth.value.apiKeyValue !== p.value) {
        auth.value.apiKeyValue = p.value;
      }
    } else {
      regularParams.push(p);
    }
  }

  // Keep user-disabled rows: they are not part of the URL so typing there must not wipe them.
  const disabledParams = params.value.filter((p) => !p.enabled && p.key);
  const merged = [...regularParams, ...disabledParams];

  isInternalParamsUpdate = true;
  params.value = merged.length > 0 ? merged : [{ enabled: true, key: '', value: '' }];
  nextTick(() => {
    isInternalParamsUpdate = false;
  });
});

// Watch params changes
watch(
  params,
  () => {
    if (isInternalParamsUpdate) return;
    syncUrlFromState();
  },
  { deep: true }
);

// Watch auth changes if apiKeyAddTo === 'query' or affected
watch(
  () => [auth.value.type, auth.value.apiKeyAddTo, auth.value.apiKeyName, auth.value.apiKeyValue],
  () => {
    syncUrlFromState();
  }
);

// Scroll helper
const tabPanelRef = ref(null);

const scrollToBottom = async (smooth = true) => {
  await nextTick();
  if (!tabPanelRef.value) return;

  if (typeof tabPanelRef.value.scrollTo === 'function') {
    tabPanelRef.value.scrollTo({
      top: tabPanelRef.value.scrollHeight,
      behavior: smooth ? 'smooth' : 'auto'
    });
  } else {
    tabPanelRef.value.scrollTop = tabPanelRef.value.scrollHeight;
  }
};

// Row helpers
const addParamRow = async () => {
  params.value.push({ enabled: true, key: '', value: '' });
  await scrollToBottom();
};

const removeParamRow = (index) => {
  if (params.value.length === 1) {
    params.value = [{ enabled: true, key: '', value: '' }];
  } else {
    params.value.splice(index, 1);
  }
};

const addHeaderRow = async () => {
  headers.value.push({ enabled: true, key: '', value: '' });
  await scrollToBottom();
};

const removeHeaderRow = (index) => {
  if (headers.value.length === 1) {
    headers.value = [{ enabled: true, key: '', value: '' }];
  } else {
    headers.value.splice(index, 1);
  }
};

const addFormDataRow = async () => {
  body.value.formData.push({ enabled: true, key: '', value: '' });
  await scrollToBottom();
};

const removeFormDataRow = (index) => {
  if (body.value.formData.length === 1) {
    body.value.formData = [{ enabled: true, key: '', value: '' }];
  } else {
    body.value.formData.splice(index, 1);
  }
};

const addCookieRow = async () => {
  settings.value.cookies.push({ enabled: true, key: '', value: '' });
  await scrollToBottom();
};

const removeCookieRow = (index) => {
  if (settings.value.cookies.length === 1) {
    settings.value.cookies = [{ enabled: true, key: '', value: '' }];
  } else {
    settings.value.cookies.splice(index, 1);
  }
};

// CodeMirror body editors
const jsonEditorContainerRef = ref(null);
const rawEditorContainerRef = ref(null);

let jsonEditorView = null;
let rawEditorView = null;
let isInternalJsonUpdate = false;
let isInternalRawUpdate = false;

const rawLanguageCompartment = new Compartment();

const getRawLanguageExtension = (contentType) => {
  if (contentType === 'application/xml' || contentType === 'text/html') {
    return html();
  }
  return [];
};

const jsonError = ref(null);

const validateJson = (text) => {
  const trimmed = (text || '').trim();
  if (!trimmed) {
    jsonError.value = null;
    return;
  }
  try {
    JSON.parse(trimmed);
    jsonError.value = null;
  } catch (err) {
    jsonError.value = err.message;
  }
};

const destroyJsonEditor = () => {
  if (jsonEditorView) {
    jsonEditorView.destroy();
    jsonEditorView = null;
  }
};

const initJsonEditor = (container) => {
  destroyJsonEditor();
  if (!container) return;

  validateJson(body.value.jsonText);

  const state = EditorState.create({
    doc: body.value.jsonText || '',
    extensions: [
      json(),
      linter(jsonParseLinter()),
      vsCodeLight,
      syntaxHighlighting(defaultHighlightStyle, { fallback: true }),
      history(),
      bracketMatching(),
      EditorView.lineWrapping,
      keymap.of([...defaultKeymap, ...historyKeymap, indentWithTab]),
      EditorView.updateListener.of((update) => {
        if (update.docChanged) {
          const newDoc = update.state.doc.toString();
          if (newDoc !== body.value.jsonText) {
            isInternalJsonUpdate = true;
            body.value.jsonText = newDoc;
            validateJson(newDoc);
            nextTick(() => {
              isInternalJsonUpdate = false;
            });
          }
        }
      })
    ]
  });

  jsonEditorView = new EditorView({
    state,
    parent: container
  });
};

const destroyRawEditor = () => {
  if (rawEditorView) {
    rawEditorView.destroy();
    rawEditorView = null;
  }
};

const initRawEditor = (container) => {
  destroyRawEditor();
  if (!container) return;

  const state = EditorState.create({
    doc: body.value.rawText || '',
    extensions: [
      rawLanguageCompartment.of(getRawLanguageExtension(body.value.rawContentType)),
      vsCodeLight,
      syntaxHighlighting(defaultHighlightStyle, { fallback: true }),
      history(),
      bracketMatching(),
      EditorView.lineWrapping,
      keymap.of([...defaultKeymap, ...historyKeymap, indentWithTab]),
      EditorView.updateListener.of((update) => {
        if (update.docChanged) {
          const newDoc = update.state.doc.toString();
          if (newDoc !== body.value.rawText) {
            isInternalRawUpdate = true;
            body.value.rawText = newDoc;
            nextTick(() => {
              isInternalRawUpdate = false;
            });
          }
        }
      })
    ]
  });

  rawEditorView = new EditorView({
    state,
    parent: container
  });
};

watch(
  jsonEditorContainerRef,
  (container) => {
    if (container) {
      initJsonEditor(container);
    } else {
      destroyJsonEditor();
    }
  },
  { flush: 'post' }
);

watch(
  rawEditorContainerRef,
  (container) => {
    if (container) {
      initRawEditor(container);
    } else {
      destroyRawEditor();
    }
  },
  { flush: 'post' }
);

watch(
  () => body.value.rawContentType,
  (newType) => {
    if (rawEditorView) {
      rawEditorView.dispatch({
        effects: rawLanguageCompartment.reconfigure(getRawLanguageExtension(newType))
      });
    }
  }
);

watch(
  () => body.value.jsonText,
  (newVal) => {
    validateJson(newVal);
    if (isInternalJsonUpdate) return;
    if (jsonEditorView) {
      const currentDoc = jsonEditorView.state.doc.toString();
      if (currentDoc !== newVal) {
        jsonEditorView.dispatch({
          changes: { from: 0, to: currentDoc.length, insert: newVal || '' }
        });
      }
    }
  }
);

watch(
  () => body.value.rawText,
  (newVal) => {
    if (isInternalRawUpdate) return;
    if (rawEditorView) {
      const currentDoc = rawEditorView.state.doc.toString();
      if (currentDoc !== newVal) {
        rawEditorView.dispatch({
          changes: { from: 0, to: currentDoc.length, insert: newVal || '' }
        });
      }
    }
  }
);

// Format JSON helper
const formatJsonBody = () => {
  try {
    const textToFormat = jsonEditorView
      ? jsonEditorView.state.doc.toString()
      : body.value.jsonText;
    const parsed = JSON.parse(textToFormat);
    const formatted = JSON.stringify(parsed, null, 2);
    body.value.jsonText = formatted;
    jsonError.value = null;
    if (jsonEditorView) {
      const currentDoc = jsonEditorView.state.doc.toString();
      if (currentDoc !== formatted) {
        jsonEditorView.dispatch({
          changes: { from: 0, to: currentDoc.length, insert: formatted }
        });
      }
    }
  } catch (err) {
    jsonError.value = err.message;
  }
};

// Generated Elscript expression
const generatedCode = computed(() => {
  const code = generateElscriptHttpRequest({
    method: method.value,
    url: url.value,
    params: params.value,
    auth: auth.value,
    headers: headers.value,
    body: body.value,
    settings: settings.value
  });
  return formatExpressionLab(code, { indent: '    ' });
});

// Code Preview CodeMirror editor
const previewEditorContainerRef = ref(null);
let previewEditorView = null;

const destroyPreviewEditor = () => {
  if (previewEditorView) {
    previewEditorView.destroy();
    previewEditorView = null;
  }
};

const initPreviewEditor = (container) => {
  destroyPreviewEditor();
  if (!container) return;

  const state = EditorState.create({
    doc: generatedCode.value || '',
    extensions: [
      elscript(),
      vsCodeLight,
      syntaxHighlighting(defaultHighlightStyle, { fallback: true }),
      bracketMatching(),
      EditorView.lineWrapping,
      EditorState.readOnly.of(true),
      EditorView.editable.of(false)
    ]
  });

  previewEditorView = new EditorView({
    state,
    parent: container
  });
};

watch(
  previewEditorContainerRef,
  (container) => {
    if (container) {
      initPreviewEditor(container);
    } else {
      destroyPreviewEditor();
    }
  },
  { flush: 'post' }
);

watch(
  generatedCode,
  (newCode) => {
    if (previewEditorView) {
      const currentDoc = previewEditorView.state.doc.toString();
      if (currentDoc !== newCode) {
        previewEditorView.dispatch({
          changes: { from: 0, to: currentDoc.length, insert: newCode || '' }
        });
      }
    }
  }
);

// Dirty state check
const isDirty = computed(() => {
  if (method.value !== 'GET') return true;
  if (url.value !== 'https://example.com') return true;
  if (params.value.some((p) => p.key || p.value)) return true;
  if (headers.value.some((h) => h.key || h.value)) return true;
  if (auth.value.type !== 'none') return true;
  if (body.value.type !== 'none') return true;
  if (settings.value.timeout !== 10) return true;
  if (settings.value.userAgent) return true;
  if (settings.value.httpVersion !== '1.1') return true;
  if (!settings.value.blocking) return true;
  if (settings.value.compress || !settings.value.decompress) return true;
  if (settings.value.cookies.some((c) => c.key || c.value)) return true;
  return false;
});

// Reset state
const resetState = () => {
  method.value = 'GET';
  isMethodDropdownOpen.value = false;
  url.value = 'https://example.com';
  activeTab.value = 'params';
  params.value = [{ enabled: true, key: '', value: '' }];
  auth.value = {
    type: 'none',
    bearerToken: '',
    basicUser: '',
    basicPass: '',
    apiKeyName: '',
    apiKeyValue: '',
    apiKeyAddTo: 'header'
  };
  headers.value = [{ enabled: true, key: '', value: '' }];
  body.value = {
    type: 'none',
    jsonText: '{\n  "key": "value"\n}',
    formData: [{ enabled: true, key: '', value: '' }],
    rawText: '',
    rawContentType: 'text/plain'
  };
  jsonError.value = null;
  settings.value = {
    timeout: 10,
    userAgent: '',
    httpVersion: '1.1',
    blocking: true,
    compress: false,
    decompress: true,
    cookies: [{ enabled: true, key: '', value: '' }]
  };
  isCopied.value = false;
};

// Actions
const handleClose = async () => {
  if (isDirty.value) {
    const confirmed = await uiStore.showDialog({
      type: 'confirm',
      title: __('Discard changes?'),
      message: __('You have unsaved changes in the HTTP Request Builder. Are you sure you want to discard them?'),
      confirmText: __('Discard'),
      cancelText: __('Cancel')
    });
    if (!confirmed) return;
  }
  resetState();
  uiStore.closeModal();
};

const handleInsert = () => {
  const code = generatedCode.value;
  uiStore.triggerSnippetInsert(code);
  resetState();
  uiStore.closeModal();
};

const handleCopyCode = async () => {
  try {
    if (navigator?.clipboard?.writeText) {
      await navigator.clipboard.writeText(generatedCode.value);
    }
    isCopied.value = true;
    setTimeout(() => {
      isCopied.value = false;
    }, 2000);
  } catch {
    // Fallback if clipboard API is restricted
  }
};

const handleOverlayClick = (e) => {
  if (props.closeOnOutsideClick && e.target === e.currentTarget) {
    handleClose();
  }
};
</script>

<template>
  <div
    v-if="isOpen"
    id="http-request-builder-modal"
    class="modal-overlay"
    @mousedown="handleOverlayClick"
  >
    <div class="modal-container http-builder-modal-container">
      <!-- Modal Header -->
      <div class="modal-header">
        <h2>{{ __('HTTP Request Builder') }}</h2>
        <button
          class="modal-close"
          :title="__('Close')"
          @click="handleClose"
        >
          <span class="codicon codicon-close" />
        </button>
      </div>

      <!-- Modal Body Content -->
      <div class="modal-content http-builder-modal-content">
        <!-- Request Bar (Method + URL) -->
        <div class="http-builder-request-bar">
          <div
            ref="methodDropdownRef"
            class="request-method-dropdown-wrapper"
          >
            <button
              type="button"
              class="request-method-trigger"
              :class="[`method-${method.toLowerCase()}`, { active: isMethodDropdownOpen }]"
              :title="__('Select HTTP Method')"
              :aria-expanded="isMethodDropdownOpen"
              @click.stop="toggleMethodDropdown"
            >
              <span class="method-text">{{ method }}</span>
              <span class="codicon codicon-chevron-down select-chevron" />
            </button>

            <div
              v-if="isMethodDropdownOpen"
              class="request-method-dropdown-menu"
              role="menu"
              @click.stop
            >
              <button
                v-for="m in HTTP_METHODS"
                :key="m"
                type="button"
                class="dropdown-item method-option"
                :class="[`method-${m.toLowerCase()}`, { active: method === m }]"
                role="menuitem"
                @click="selectMethod(m)"
              >
                <span class="method-name">{{ m }}</span>
                <span
                  v-if="method === m"
                  class="codicon codicon-check active-check"
                />
              </button>
            </div>
          </div>
          <div class="request-bar-divider" />
          <div class="request-url-input-wrapper">
            <input
              v-model="url"
              type="text"
              class="request-url-input"
              placeholder="https://example.com"
              spellcheck="false"
            >
          </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="http-builder-tabs-header">
          <button
            class="builder-tab-btn"
            :class="{ active: activeTab === 'params' }"
            @click="activeTab = 'params'"
          >
            {{ __('Params') }}
            <span
              v-if="params.filter(p => p.enabled && p.key).length > 0"
              class="tab-count-badge"
            >
              {{ params.filter(p => p.enabled && p.key).length }}
            </span>
          </button>
          <button
            class="builder-tab-btn"
            :class="{ active: activeTab === 'auth' }"
            @click="activeTab = 'auth'"
          >
            {{ __('Authorization') }}
            <span
              v-if="auth.type !== 'none'"
              class="tab-active-dot"
            />
          </button>
          <button
            class="builder-tab-btn"
            :class="{ active: activeTab === 'headers' }"
            @click="activeTab = 'headers'"
          >
            {{ __('Headers') }}
            <span
              v-if="headers.filter(h => h.enabled && h.key).length > 0"
              class="tab-count-badge"
            >
              {{ headers.filter(h => h.enabled && h.key).length }}
            </span>
          </button>
          <button
            class="builder-tab-btn"
            :class="{ active: activeTab === 'body' }"
            @click="activeTab = 'body'"
          >
            {{ __('Body') }}
            <span
              v-if="method === 'POST' && body.type !== 'none'"
              class="tab-active-dot"
            />
          </button>
          <button
            class="builder-tab-btn"
            :class="{ active: activeTab === 'settings' }"
            @click="activeTab = 'settings'"
          >
            {{ __('Settings') }}
          </button>
          <button
            class="builder-tab-btn"
            :class="{ active: activeTab === 'preview' }"
            @click="activeTab = 'preview'"
          >
            {{ __('Code Preview') }}
          </button>
        </div>

        <!-- Active Tab Content -->
        <div
          ref="tabPanelRef"
          class="http-builder-tab-panel"
        >
          <!-- TAB: PARAMS -->
          <div
            v-if="activeTab === 'params'"
            class="tab-pane tab-params"
          >
            <div class="panel-section-header">
              <h4>{{ __('Query Parameters') }}</h4>
            </div>

            <div class="kv-table-container">
              <table class="kv-table">
                <thead>
                  <tr>
                    <th class="col-check" />
                    <th class="col-key">
                      {{ __('Key') }}
                    </th>
                    <th class="col-val">
                      {{ __('Value') }}
                    </th>
                    <th class="col-actions" />
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(p, index) in params"
                    :key="index"
                    class="kv-row"
                  >
                    <td class="col-check">
                      <input
                        v-model="p.enabled"
                        type="checkbox"
                        class="kv-checkbox"
                      >
                    </td>
                    <td class="col-key">
                      <input
                        v-model="p.key"
                        type="text"
                        class="kv-input"
                        :placeholder="__('Key')"
                      >
                    </td>
                    <td class="col-val">
                      <input
                        v-model="p.value"
                        type="text"
                        class="kv-input"
                        :placeholder="__('Value')"
                      >
                    </td>
                    <td class="col-actions">
                      <button
                        class="btn-row-action btn-row-delete"
                        :title="__('Remove')"
                        @click="removeParamRow(index)"
                      >
                        <span class="codicon codicon-trash" />
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="table-actions-bar">
              <button
                class="btn btn-sm btn-default"
                @click="addParamRow"
              >
                {{ __('Add Parameter') }}
              </button>
            </div>
          </div>

          <!-- TAB: AUTHORIZATION -->
          <div
            v-else-if="activeTab === 'auth'"
            class="tab-pane tab-auth"
          >
            <div class="panel-section-header">
              <h4>{{ __('Authorization') }}</h4>
            </div>

            <div class="auth-config-layout">
              <div class="auth-type-selector">
                <label class="form-label">{{ __('Type') }}</label>
                <select
                  v-model="auth.type"
                  class="form-select auth-select"
                >
                  <option value="none">
                    {{ __('No Auth') }}
                  </option>
                  <option value="bearer">
                    {{ __('Bearer Token') }}
                  </option>
                  <option value="basic">
                    {{ __('Basic Auth') }}
                  </option>
                  <option value="apikey">
                    {{ __('API Key') }}
                  </option>
                </select>
              </div>

              <div class="auth-fields-container">
                <div
                  v-if="auth.type === 'none'"
                  class="auth-hint-empty"
                >
                  <p>{{ __('This request does not use any authorization headers.') }}</p>
                </div>

                <!-- Bearer Token -->
                <div
                  v-else-if="auth.type === 'bearer'"
                  class="auth-group"
                >
                  <label class="form-label">{{ __('Token') }}</label>
                  <input
                    v-model="auth.bearerToken"
                    type="text"
                    class="form-input"
                    placeholder="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
                    spellcheck="false"
                  >
                  <span class="form-help">{{ __('Example:') }} <code>Authorization: Bearer &lt;token&gt;</code></span>
                </div>

                <!-- Basic Auth -->
                <div
                  v-else-if="auth.type === 'basic'"
                  class="auth-group"
                >
                  <div class="auth-row">
                    <div class="auth-field">
                      <label class="form-label">{{ __('Username') }}</label>
                      <input
                        v-model="auth.basicUser"
                        type="text"
                        class="form-input"
                        placeholder="admin"
                        spellcheck="false"
                      >
                    </div>
                    <div class="auth-field">
                      <label class="form-label">{{ __('Password') }}</label>
                      <input
                        v-model="auth.basicPass"
                        type="password"
                        class="form-input"
                        placeholder="••••••••"
                      >
                    </div>
                  </div>
                  <span class="form-help">{{ __('Example:') }} <code>Authorization: Basic &lt;base64&gt;</code></span>
                </div>

                <!-- API Key -->
                <div
                  v-else-if="auth.type === 'apikey'"
                  class="auth-group"
                >
                  <div class="auth-row">
                    <div class="auth-field">
                      <label class="form-label">{{ __('Key Name') }}</label>
                      <input
                        v-model="auth.apiKeyName"
                        type="text"
                        class="form-input"
                        placeholder="X-API-Key"
                        spellcheck="false"
                      >
                    </div>
                    <div class="auth-field">
                      <label class="form-label">{{ __('Value') }}</label>
                      <input
                        v-model="auth.apiKeyValue"
                        type="text"
                        class="form-input"
                        placeholder="api_key_value"
                        spellcheck="false"
                      >
                    </div>
                    <div class="auth-field auth-field-addto">
                      <label class="form-label">{{ __('Add to') }}</label>
                      <select
                        v-model="auth.apiKeyAddTo"
                        class="form-select"
                      >
                        <option value="header">
                          {{ __('Header') }}
                        </option>
                        <option value="query">
                          {{ __('Query Params') }}
                        </option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- TAB: HEADERS -->
          <div
            v-else-if="activeTab === 'headers'"
            class="tab-pane tab-headers"
          >
            <div class="panel-section-header">
              <h4>{{ __('Request Headers') }}</h4>
            </div>

            <!-- Datalist for common HTTP headers -->
            <datalist id="common-http-headers">
              <option value="Accept" />
              <option value="Content-Type" />
              <option value="User-Agent" />
              <option value="Authorization" />
              <option value="Cache-Control" />
              <option value="X-Requested-With" />
              <option value="Origin" />
              <option value="Referer" />
            </datalist>

            <div class="kv-table-container">
              <table class="kv-table">
                <thead>
                  <tr>
                    <th class="col-check" />
                    <th class="col-key">
                      {{ __('Key') }}
                    </th>
                    <th class="col-val">
                      {{ __('Value') }}
                    </th>
                    <th class="col-actions" />
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(h, index) in headers"
                    :key="index"
                    class="kv-row"
                  >
                    <td class="col-check">
                      <input
                        v-model="h.enabled"
                        type="checkbox"
                        class="kv-checkbox"
                      >
                    </td>
                    <td class="col-key">
                      <input
                        v-model="h.key"
                        type="text"
                        class="kv-input"
                        list="common-http-headers"
                        :placeholder="__('Header name')"
                        spellcheck="false"
                      >
                    </td>
                    <td class="col-val">
                      <input
                        v-model="h.value"
                        type="text"
                        class="kv-input"
                        :placeholder="__('Header value')"
                        spellcheck="false"
                      >
                    </td>
                    <td class="col-actions">
                      <button
                        class="btn-row-action btn-row-delete"
                        :title="__('Remove')"
                        @click="removeHeaderRow(index)"
                      >
                        <span class="codicon codicon-trash" />
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="table-actions-bar">
              <button
                class="btn btn-sm btn-default"
                @click="addHeaderRow"
              >
                {{ __('Add Header') }}
              </button>
            </div>
          </div>

          <!-- TAB: BODY -->
          <div
            v-else-if="activeTab === 'body'"
            class="tab-pane tab-body"
          >
            <!-- Notice if not POST -->
            <div
              v-if="method !== 'POST'"
              class="body-disabled-notice"
            >
              <div class="codicon codicon-info" />
              <p>
                {{ __('Body payload is only supported for POST requests. Select POST in the method dropdown above to configure a request body.') }}
              </p>
            </div>

            <!-- Body Config for POST -->
            <div
              v-else
              class="body-config-wrapper"
            >
              <div class="body-type-toggles">
                <label class="radio-label">
                  <input
                    v-model="body.type"
                    type="radio"
                    value="none"
                  >
                  <span>{{ __('None') }}</span>
                </label>
                <label class="radio-label">
                  <input
                    v-model="body.type"
                    type="radio"
                    value="json"
                  >
                  <span>{{ __('JSON') }}</span>
                </label>
                <label class="radio-label">
                  <input
                    v-model="body.type"
                    type="radio"
                    value="form-data"
                  >
                  <span>{{ __('x-www-form-urlencoded') }}</span>
                </label>
                <label class="radio-label">
                  <input
                    v-model="body.type"
                    type="radio"
                    value="raw"
                  >
                  <span>{{ __('Raw') }}</span>
                </label>
              </div>

              <!-- Sub-body: None -->
              <div
                v-if="body.type === 'none'"
                class="body-disabled-notice"
              >
                <div class="codicon codicon-info" />
                <p>{{ __('This request does not have a body.') }}</p>
              </div>

              <!-- Sub-body: JSON -->
              <div
                v-else-if="body.type === 'json'"
                class="body-editor-container"
              >
                <div
                  ref="jsonEditorContainerRef"
                  class="body-cm-editor"
                  :class="{ 'has-error': Boolean(jsonError) }"
                />
                <div class="table-actions-bar json-actions-bar">
                  <div
                    v-if="jsonError"
                    class="json-syntax-error"
                    :title="jsonError"
                  >
                    <span class="codicon codicon-close json-error-icon" />
                    <span class="json-error-text">{{ jsonError }}</span>
                  </div>
                  <button
                    class="btn btn-sm btn-default format-json-btn"
                    @click="formatJsonBody"
                  >
                    {{ __('Format JSON') }}
                  </button>
                </div>
              </div>

              <!-- Sub-body: Form-Data -->
              <div
                v-else-if="body.type === 'form-data'"
                class="body-form-data-container"
              >
                <div class="panel-section-header">
                  <h4>{{ __('Form Fields') }}</h4>
                </div>
                <div class="kv-table-container">
                  <table class="kv-table">
                    <thead>
                      <tr>
                        <th class="col-check" />
                        <th class="col-key">
                          {{ __('Key') }}
                        </th>
                        <th class="col-val">
                          {{ __('Value') }}
                        </th>
                        <th class="col-actions" />
                      </tr>
                    </thead>
                    <tbody>
                      <tr
                        v-for="(row, index) in body.formData"
                        :key="index"
                        class="kv-row"
                      >
                        <td class="col-check">
                          <input
                            v-model="row.enabled"
                            type="checkbox"
                            class="kv-checkbox"
                          >
                        </td>
                        <td class="col-key">
                          <input
                            v-model="row.key"
                            type="text"
                            class="kv-input"
                            :placeholder="__('Key')"
                          >
                        </td>
                        <td class="col-val">
                          <input
                            v-model="row.value"
                            type="text"
                            class="kv-input"
                            :placeholder="__('Value')"
                          >
                        </td>
                        <td class="col-actions">
                          <button
                            class="btn-row-action btn-row-delete"
                            :title="__('Remove')"
                            @click="removeFormDataRow(index)"
                          >
                            <span class="codicon codicon-trash" />
                          </button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div class="table-actions-bar">
                  <button
                    class="btn btn-sm btn-default"
                    @click="addFormDataRow"
                  >
                    {{ __('Add Field') }}
                  </button>
                </div>
              </div>

              <!-- Sub-body: Raw Text -->
              <div
                v-else-if="body.type === 'raw'"
                class="body-raw-container"
              >
                <div class="raw-content-type-bar">
                  <label class="form-label">{{ __('Content-Type:') }}</label>
                  <select
                    v-model="body.rawContentType"
                    class="form-select raw-type-select"
                  >
                    <option value="text/plain">
                      Text (text/plain)
                    </option>
                    <option value="application/xml">
                      XML (application/xml)
                    </option>
                    <option value="text/html">
                      HTML (text/html)
                    </option>
                  </select>
                </div>
                <div
                  ref="rawEditorContainerRef"
                  class="body-cm-editor"
                />
              </div>
            </div>
          </div>

          <!-- TAB: SETTINGS -->
          <div
            v-else-if="activeTab === 'settings'"
            class="tab-pane tab-settings"
          >
            <div class="panel-section-header">
              <h4>{{ __('Transport Settings') }}</h4>
            </div>

            <div class="settings-grid">
              <div class="setting-item">
                <label class="form-label">{{ __('Timeout (seconds)') }}</label>
                <input
                  v-model.number="settings.timeout"
                  type="number"
                  min="1"
                  max="300"
                  class="form-input setting-input-sm"
                >
                <span class="form-help">{{ __('Default: 10 seconds.') }}</span>
              </div>

              <div class="setting-item">
                <label class="form-label">{{ __('HTTP Version') }}</label>
                <select
                  v-model="settings.httpVersion"
                  class="form-select setting-input-sm"
                >
                  <option value="1.1">
                    1.1 (Default)
                  </option>
                  <option value="1.0">
                    1.0
                  </option>
                </select>
              </div>

              <div class="setting-item setting-item-full">
                <label class="form-label">{{ __('Custom User-Agent') }}</label>
                <input
                  v-model="settings.userAgent"
                  type="text"
                  class="form-input"
                  placeholder="ExpressionLab/1.0; WordPress/..."
                  spellcheck="false"
                >
              </div>

              <div class="setting-item setting-checkboxes setting-item-full">
                <label class="checkbox-label">
                  <input
                    v-model="settings.blocking"
                    type="checkbox"
                  >
                  <span>{{ __('Blocking request') }}</span>
                </label>
                <label class="checkbox-label">
                  <input
                    v-model="settings.compress"
                    type="checkbox"
                  >
                  <span>{{ __('Compress request') }}</span>
                </label>
                <label class="checkbox-label">
                  <input
                    v-model="settings.decompress"
                    type="checkbox"
                  >
                  <span>{{ __('Decompress response') }}</span>
                </label>
              </div>

              <!-- Cookies table -->
              <div class="setting-item setting-item-full">
                <div class="panel-section-header">
                  <h4>{{ __('Cookies') }}</h4>
                </div>
                <div class="kv-table-container">
                  <table class="kv-table">
                    <thead>
                      <tr>
                        <th class="col-check" />
                        <th class="col-key">
                          {{ __('Name') }}
                        </th>
                        <th class="col-val">
                          {{ __('Value') }}
                        </th>
                        <th class="col-actions" />
                      </tr>
                    </thead>
                    <tbody>
                      <tr
                        v-for="(c, index) in settings.cookies"
                        :key="index"
                        class="kv-row"
                      >
                        <td class="col-check">
                          <input
                            v-model="c.enabled"
                            type="checkbox"
                            class="kv-checkbox"
                          >
                        </td>
                        <td class="col-key">
                          <input
                            v-model="c.key"
                            type="text"
                            class="kv-input"
                            :placeholder="__('Cookie name')"
                          >
                        </td>
                        <td class="col-val">
                          <input
                            v-model="c.value"
                            type="text"
                            class="kv-input"
                            :placeholder="__('Cookie value')"
                          >
                        </td>
                        <td class="col-actions">
                          <button
                            class="btn-row-action btn-row-delete"
                            :title="__('Remove')"
                            @click="removeCookieRow(index)"
                          >
                            <span class="codicon codicon-trash" />
                          </button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div class="table-actions-bar">
                  <button
                    class="btn btn-sm btn-default"
                    @click="addCookieRow"
                  >
                    {{ __('Add Cookie') }}
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- TAB: CODE PREVIEW -->
          <div
            v-else-if="activeTab === 'preview'"
            class="tab-pane tab-preview"
          >
            <div class="panel-section-header">
              <h4>{{ __('Generated Elscript Expression') }}</h4>
            </div>

            <div
              ref="previewEditorContainerRef"
              class="code-preview-cm-editor"
            />

            <div class="table-actions-bar">
              <button
                class="btn btn-sm btn-default"
                @click="handleCopyCode"
              >
                {{ isCopied ? __('Copied!') : __('Copy Code') }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="modal-footer http-builder-footer">
        <button
          class="btn btn-default fw-100px"
          @click="handleClose"
        >
          {{ __('Cancel') }}
        </button>
        <button
          class="btn btn-primary fw-100px"
          @click="handleInsert"
        >
          {{ __('Insert') }}
        </button>
      </div>
    </div>
  </div>
</template>
