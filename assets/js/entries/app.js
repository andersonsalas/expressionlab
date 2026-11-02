/**
 * Expression Lab
 * (c) Anderson Salas <github@andersonsalas.com>
 * @license GPL-2.0+
 **/
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { Marked } from 'marked';
import DOMPurify from 'dompurify';
import hljs from 'highlight.js/lib/core';
import AppRoot from '../views/AppRoot.vue';
import router from '../router/index.js';
import { cleanUpWpAdmin, __, sprintf, hasAddons } from '../lib/helpers.js';
import {
  handleFetch,
  handleLocalStorage,
  handleOpenBinaryFilePicker,
  handleSaveBinaryFile,
  handleSaveBinaryFileAs,
  handleClipboardCopy,
  handleOpenUrl,
} from '../lib/api/client.js';
import { formatExpressionLab } from '../lib/codemirror/formatter.js';
import { formatEditorDocument } from '../lib/codemirror/format-command.js';
import { useTabsStore } from '../stores/tabs.js';
import { useUiStore } from '../stores/ui.js';
import { useSettingsStore } from '../stores/settings.js';
import { useOutlineStore } from '../stores/outline.js';
import ConsoleEditor from '../components/console/ConsoleEditor.vue';
import ScratchpadOutput from '../components/console/ScratchpadOutput.vue';
import SafeFormattedText from '../components/console/SafeFormattedText.vue';
import DataTree from '../components/ui/DataTree.vue';
import '../../scss/expressionlab-app.scss';
import '@vscode/codicons/dist/codicon.css';
import '@vscode/codicons/dist/codicon.ttf';

const app = createApp(AppRoot);
const pinia = createPinia();

app.use(pinia);
app.use(router);

app.config.globalProperties.__ = __;
app.config.globalProperties.sprintf = sprintf;
app.config.globalProperties.hasAddons = hasAddons;
app.config.globalProperties.$withAddons = Boolean(__WITH_ADDONS__);

app.config.globalProperties.assets_url = function(url) {
  return window.expressionlab.assets_url + '/' + url;
};

const core = {
  components: {
    ConsoleEditor,
    ScratchpadOutput,
    SafeFormattedText,
    DataTree,
  },
  stores: {
    useTabsStore,
    useUiStore,
    useSettingsStore,
    useOutlineStore,
  },
  api: {
    handleFetch,
    handleLocalStorage,
    handleOpenBinaryFilePicker,
    handleSaveBinaryFile,
    handleSaveBinaryFileAs,
    handleClipboardCopy,
    handleOpenUrl,
  },
  formatter: {
    formatExpressionLab,
    formatEditorDocument,
  },
  markdown: {
    Marked,
    DOMPurify,
    hljs,
  },
  helpers: {
    __,
    sprintf,
    hasAddons,
  },
};

app.provide('elCore', core);

async function initAddons() {
  if (hasAddons()) {
    try {
      const addon = await import(/* webpackMode: "eager" */ '@addon');
      if (addon && typeof addon.register === 'function') {
        await addon.register({ app, pinia, router, core });
      }
    } catch (err) {
      console.error('[Expression Lab] Failed to register Pro Addon:', err);
    }
  }
}

initAddons().finally(() => {
  app.mount('.expressionlab-app');
});

document.addEventListener('DOMContentLoaded', cleanUpWpAdmin);