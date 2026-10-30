/**
 * Expression Lab
 * (c) Anderson Salas <github@andersonsalas.com>
 * @license GPL-2.0+
 **/
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import AppRoot from '../views/AppRoot.vue';
import router from '../router/index.js';
import { cleanUpWpAdmin, __, sprintf, hasAddons } from '../lib/helpers.js';
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

async function initAddons() {
  if (hasAddons()) {
    try {
      const addon = await import(/* webpackMode: "eager" */ '@addon');
      if (addon && typeof addon.register === 'function') {
        await addon.register({ app, pinia, router });
      }
    } catch (err) {
      console.error('[Expression Lab] Failed to register addon:', err);
    }
  }
}

initAddons().finally(() => {
  app.mount('.expressionlab-app');
});

document.addEventListener('DOMContentLoaded', cleanUpWpAdmin);