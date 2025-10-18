import axios from 'axios';

const api = axios.create({
    // Чтобы не дублировать префикс /api в каждом вызове (для api.get('/jobs') будет выполнен запрос на GET http://мой-хост/api/jobs)
    baseURL: '/api',
});

// Теперь в любом Vue-компоненте можно подключить этот api
export default api;
