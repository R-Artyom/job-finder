<template>
    <div class="p-6">
        <h1 class="text-2xl font-bold mb-4">Список вакансий</h1>

        <table class="min-w-full border border-gray-400 border-collapse text-sm">
            <thead class="bg-gray-100">
                <tr>
                    <th class="border border-gray-400 px-3 py-2 text-left">ID</th>
                    <th class="border border-gray-400 px-3 py-2 text-left">Название</th>
                    <th class="border border-gray-400 px-3 py-2 text-left">Регион</th>
                    <th class="border border-gray-400 px-3 py-2 text-left">Описание</th>
                    <th class="border border-gray-400 px-3 py-2 text-left">ЗП от</th>
                    <th class="border border-gray-400 px-3 py-2 text-left">ЗП до</th>
                    <th class="border border-gray-400 px-3 py-2 text-left">Валюта</th>
                    <th class="border border-gray-400 px-3 py-2 text-left">В архиве</th>
                    <th class="border border-gray-400 px-3 py-2 text-left">Опубликовано</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="vacancy in vacancies" :key="vacancy.id" class="hover:bg-gray-50">
                    <td class="border border-gray-400 px-3 py-2">{{ vacancy.id }}</td>
                    <td class="border border-gray-400 px-3 py-2">{{ vacancy.name }}</td>
                    <td class="border border-gray-400 px-3 py-2">{{ vacancy.area_id }}</td>
                    <td class="border border-gray-400 px-3 py-2">{{ vacancy.description }}</td>
                    <td class="border border-gray-400 px-3 py-2">{{ vacancy.salary_from ?? '—' }}</td>
                    <td class="border border-gray-400 px-3 py-2">{{ vacancy.salary_to ?? '—' }}</td>
                    <td class="border border-gray-400 px-3 py-2">{{ vacancy.salary_currency ?? '—' }}</td>
                    <td class="border border-gray-400 px-3 py-2">
                <span :class="vacancy.archived ? 'text-red-600' : 'text-green-600'">
                    {{ vacancy.archived ? 'Да' : 'Нет' }}
                </span>
                    </td>
                    <td class="border border-gray-400 px-3 py-2">{{ vacancy.published_at ? formatDate(vacancy.published_at) : '—' }}</td>
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