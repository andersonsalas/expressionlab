/**
 * Expression Lab Onboarding
 * (c) Anderson Salas <github@andersonsalas.com>
 * @license GPL-2.0+
 **/
import { createApp } from 'vue'
import OnboardView from '../views/OnboardView.vue';
import { cleanUpWpAdmin, __, sprintf } from '../lib/helpers.js';
import '../../scss/expressionlab-onboard.scss';
import '@vscode/codicons/dist/codicon.css';
import '@vscode/codicons/dist/codicon.ttf';

const app = createApp(OnboardView);

app.config.globalProperties.__ = __;
app.config.globalProperties.sprintf = sprintf;

app.config.globalProperties.assets_url = function(url) {
  return window.expressionlab.assets_url + '/' + url;
};

app.mount('.expressionlab-app');

document.addEventListener('DOMContentLoaded', cleanUpWpAdmin);
