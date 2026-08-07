import { createRouter, createWebHistory } from 'vue-router';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/',
            redirect: '/vacancies'
        },
        {
            path: '/vacancies',
            name: 'vacancies.index',
            component: () => import('../pages/vacancies/index.vue')
        },
        // Все неизвестные маршруты перенаправляются на страницу вакансий
        {
            path: '/:pathMatch(.*)*',
            redirect: '/vacancies'
        }
    ],
})

export default router
