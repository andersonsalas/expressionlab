/**
 * Expression Lab Onboarding
 * (c) Anderson Salas <github@andersonsalas.com>
 * @license GPL-2.0+
 **/
import { createApp } from 'vue'
import { Marked } from 'marked';
import DOMPurify from 'dompurify';
import OnboardView from '../views/OnboardView.vue';
import { cleanUpWpAdmin, __, sprintf, debugLog, hasAddons } from '../lib/helpers.js';
import { sendToSandbox } from '../lib/sandbox-communication.js';
import { handleOpenUrl } from '../lib/api/client.js';
import '../../scss/expressionlab-onboard.scss';
import '@vscode/codicons/dist/codicon.css';
import '@vscode/codicons/dist/codicon.ttf';

const sandboxIframe = document.getElementById('expressionlab-sandbox');

if (sandboxIframe) {
  window.addEventListener('message', (event) => {
    if (event.source !== sandboxIframe.contentWindow || event.origin !== 'null') {
      return;
    }

    const msg = event.data;
    if (!msg || typeof msg !== 'object') {
      return;
    }

    if (msg.type === 'open_url' && msg.payload?.url) {
      try {
        const parsedUrl = new URL(msg.payload.url, window.location.origin);
        if (['http:', 'https:', 'mailto:'].includes(parsedUrl.protocol)) {
          window.open(parsedUrl.href, '_blank', 'noopener,noreferrer');
        } else {
          debugLog('Blocked opening URL with disallowed protocol:', parsedUrl.protocol, 'warn');
        }
      } catch (err) {
        debugLog('Failed to parse URL for open_url in onboarding:', err, 'error');
      }
      return;
    }

    if (msg.type === 'localStorage' && msg.payload) {
      const { method, key, value } = msg.payload;
      let result = null;

      // The onboarding iframe only ever needs to persist or clear the encrypted license entry in Professional Edition.
      if (!hasAddons() || key !== 'el_license') {
        sendToSandbox(sandboxIframe, 'response', { response_to: msg.message_id, value: null });
        return;
      }

      try {
        if (typeof localStorage !== 'undefined') {
          if (method === 'getItem' || method === 'get') {
            result = localStorage.getItem(key);
          } else if (method === 'setItem' || method === 'set') {
            localStorage.setItem(key, value);
          } else if (method === 'removeItem' || method === 'remove') {
            localStorage.removeItem(key);
          }
        }
      } catch (e) {
        debugLog('Onboarding localStorage operation failed', e, 'error');
      }

      sendToSandbox(sandboxIframe, 'response', { response_to: msg.message_id, value: result });
    }
  });
} else {
  const app = createApp(OnboardView);

  app.config.globalProperties.__ = __;
  app.config.globalProperties.sprintf = sprintf;
  app.config.globalProperties.hasAddons = hasAddons;
  app.config.globalProperties.$withAddons = Boolean(__WITH_ADDONS__);

  app.config.globalProperties.assets_url = function(url) {
    return window.expressionlab.assets_url + '/' + url;
  };

  const core = {
    api: {
      handleOpenUrl,
    },
    markdown: {
      Marked,
      DOMPurify,
    },
    helpers: {
      __,
      sprintf,
      hasAddons,
    },
    addons: {
      validateLicense: null,
    },
  };

  app.provide('elCore', core);

  async function initAddons() {
    if (hasAddons()) {
      try {
        const addon = await import(/* webpackMode: "eager" */ '@addon');
        if (addon && addon.ProEulaModal) {
          app.component('ProEulaModal', addon.ProEulaModal);
        }
        if (addon && typeof addon.initLicenseWasm === 'function') {
          addon.initLicenseWasm();
        }
        if (addon && typeof addon.validateLicense === 'function') {
          core.addons.validateLicense = addon.validateLicense;
        }
      } catch (err) {
        console.error('[Expression Lab] Failed to register Professional Edition onboarding components:', err);
      }
    }
  }

  initAddons().finally(() => {
    app.mount('.expressionlab-app');
  });
}

document.addEventListener('DOMContentLoaded', cleanUpWpAdmin);
