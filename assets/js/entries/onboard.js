/**
 * Expression Lab Onboarding
 * (c) Anderson Salas <github@andersonsalas.com>
 * @license GPL-2.0+
 **/
import { createApp } from 'vue'
import OnboardView from '../views/OnboardView.vue';
import { cleanUpWpAdmin, __, sprintf, debugLog } from '../lib/helpers.js';
import { sendToSandbox } from '../lib/sandbox-communication.js';
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

    if (msg.type === 'localStorage' && msg.payload) {
      const { method, key, value } = msg.payload;
      let result = null;

      // The onboarding iframe only ever needs to persist or clear the encrypted license entry.
      if (key !== 'el_license') {
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

  app.config.globalProperties.assets_url = function(url) {
    return window.expressionlab.assets_url + '/' + url;
  };

  app.mount('.expressionlab-app');
}

document.addEventListener('DOMContentLoaded', cleanUpWpAdmin);
