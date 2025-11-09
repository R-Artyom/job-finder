<template>
    <div class="p-6">
        <h1 class="text-2xl font-bold mb-4">Список вакансий</h1>

        <table class="min-w-full border border-gray-400 border-collapse text-sm">
            <thead class="bg-gray-100">
                <tr>
                    <th class="border border-gray-400 px-1 py-2 w-16">№</th>
                    <th class="border border-gray-400 px-1 py-2 w-64">Название</th>
                    <th class="border border-gray-400 px-1 py-2 w-32">Регион</th>
                    <th class="border border-gray-400 px-1 py-2 w-64">Описание</th>
                    <th class="border border-gray-400 px-1 py-2 w-24">ЗП от</th>
                    <th class="border border-gray-400 px-1 py-2 w-24">ЗП до</th>
                    <th class="border border-gray-400 px-1 py-2 w-20">Валюта</th>
                    <th class="border border-gray-400 px-1 py-2 w-24">В архиве</th>
                    <th class="border border-gray-400 px-1 py-2 w-32">Опубликовано</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="vacancy in vacancies" :key="vacancy.id" class="hover:bg-gray-50">
                    <td class="border border-gray-400 px-1 py-2 text-center">{{ vacancy.id }}</td>
                    <td class="border border-gray-400 px-1 py-2 max-w-64">
                        <div class="truncate whitespace-nowrap overflow-hidden" v-html="cleanHtml(vacancy.name)" :title="cleanAllHtml(vacancy.name)"></div>
                    </td>
                    <td class="border border-gray-400 px-1 py-2 text-center">{{ vacancy.area_id }}</td>
                    <td class="border border-gray-400 px-1 py-2 max-w-64">
                        <div class="truncate whitespace-nowrap overflow-hidden" v-html="cleanHtml(vacancy.description)" :title="cleanAllHtml(vacancy.description)"></div>
                    </td>
                    <td class="border border-gray-400 px-1 py-2 text-center">{{ vacancy.salary_from ?? '—' }}</td>
                    <td class="border border-gray-400 px-1 py-2 text-center">{{ vacancy.salary_to ?? '—' }}</td>
                    <td class="border border-gray-400 px-1 py-2 text-center">{{ vacancy.salary_currency ?? '—' }}</td>
                    <td class="border border-gray-400 px-1 py-2 text-center">
                        <span :class="vacancy.archived ? 'text-red-600' : 'text-green-600'"> {{ vacancy.archived ? 'Да' : 'Нет' }}</span>
                    </td>
                    <td class="border border-gray-400 px-1 py-2 text-center">
                        <div class="truncate whitespace-nowrap overflow-hidden"> {{ vacancy.published_at ? formatDate(vacancy.published_at) : '—' }}</div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script>
    import api from '../../axios.js'; // Путь к кастомному axios

    export default {
        name: "index",

        data() {
            return {
                vacancies: []
            }
        },

        mounted() {
            this.getVacancies()
        },

        methods: {
            getVacancies() {
                api.get('/vacancies')
                    .then( res => {
                        this.vacancies = res.data
                    })
            },

            // Очиста текста от тегов HTML, кроме тегов выделения текста
            cleanHtml(html) {
                if (!html) return '';

                let s = String(html).trim();

                // 1) Заменяем <br> на пробел
                s = s.replace(/<br\s*\/?>/gi, ' ');

                // 2) Заменяем блоковые теги (p, div, li, ul, ol, h1..h6, table, tr, td, th) на пробел
                s = s.replace(/<\/?(p|div|li|ul|ol|h[1-6]|table|tr|td|th)[^>]*>/gi, ' ');

                // // 3) Удаляем все оставшиеся теги (например <b>, <i> оставим текст, но удалим теги)
                // s = s.replace(/<[^>]+>/g, '');

                // 4) Убираем незакрытые обрывки в конце типа "<" или "</"
                s = s.replace(/(<\/?$|<\/?[^>]*$)/g, '');

                // 5) Сжимаем множественные пробелы в один и trim
                s = s.replace(/\s+/g, ' ').trim();

                // Возвращаем безопасный HTML (в данном случае — чистый текст)
                return s;
            },

            // Очиста текста от всех тегов HTML
            cleanAllHtml(html) {
                if (!html) return '';

                let s = String(html).trim();

                // 1) Заменяем <br> на пробел
                s = s.replace(/<br\s*\/?>/gi, ' ');

                // 2) Заменяем блоковые теги (p, div, li, ul, ol, h1..h6, table, tr, td, th) на пробел
                s = s.replace(/<\/?(p|div|li|ul|ol|h[1-6]|table|tr|td|th)[^>]*>/gi, ' ');

                // 3) Удаляем все оставшиеся теги (например <b>, <i> оставим текст, но удалим теги)
                s = s.replace(/<[^>]+>/g, '');

                // 4) Убираем незакрытые обрывки в конце типа "<" или "</"
                s = s.replace(/(<\/?$|<\/?[^>]*$)/g, '');

                // 5) Сжимаем множественные пробелы в один и trim
                s = s.replace(/\s+/g, ' ').trim();

                // Возвращаем безопасный HTML (в данном случае — чистый текст)
                return s;
            },

            formatDate(dateStr) {
                const date = new Date(dateStr)
                return date.toLocaleDateString('ru-RU', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                })
            }
        }
    }
</script>

<style scoped>

</style>