import { createRouter, createWebHashHistory } from 'vue-router';

const routes = [
    { path: '/', name: 'home', component: Dashboard },
    { path: '/history', name: 'history', component: Dashboard }
];

const router = createRouter({
    history: createWebHashHistory(),
    routes,
});

export default router;
