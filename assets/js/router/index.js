/**
 * Expression Lab
 * (c) Anderson Salas <github@andersonsalas.com>
 * @license GPL-2.0+
 **/
import { createRouter, createWebHashHistory } from 'vue-router';
import ConsoleView from '../views/ConsoleView.vue';

const routes = [
  {
    path: '/',
    name: 'console',
    component: ConsoleView,
  },
  {
    path: '/console',
    redirect: '/',
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/',
  },
];

const router = createRouter({
  history: createWebHashHistory(),
  routes,
});

export default router;