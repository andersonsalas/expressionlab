/**
 * Expression Lab
 * (c) Anderson Salas <github@andersonsalas.com>
 * @license GPL-2.0+
 **/
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import AppRoot from '../views/AppRoot.vue';
import router from '../router/index.js';
import { cleanUpWpAdmin, __, sprintf } from '../lib/helpers.js';
import '../../scss/expressionlab-app.scss';
import '@vscode/codicons/dist/codicon.css';
import '@vscode/codicons/dist/codicon.ttf';

const app = createApp(AppRoot);
const pinia = createPinia();

app.use(pinia);
app.use(router);

app.config.globalProperties.__ = __;
app.config.globalProperties.sprintf = sprintf;

app.config.globalProperties.assets_url = function(url) {
  return window.expressionlab.assets_url + '/' + url;
};

app.mount('.expressionlab-app');

document.addEventListener('DOMContentLoaded', cleanUpWpAdmin);