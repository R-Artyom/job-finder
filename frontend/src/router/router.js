import { createRouter, createWebHistory } from 'vue-router';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/vacancies',
            name: 'vacancies.index',
            component: () => import('../pages/vacancies/index.vue')
        }
    ],
})

export default router
