<template>
    <div class="p-6">
        <h1 class="text-2xl font-bold mb-4">Список вакансий</h1>

        <table class="min-w-full border border-gray-400 border-collapse text-sm">
            <thead class="bg-gray-100">
                <tr>
                    <th class="border border-gray-400 px-1 py-2 w-16">№</th>
                    <th class="border border-gray-400 px-1 py-2 w-64">
                        <div class="flex flex-col gap-1">
                            <span>Название</span>
                            <input
                                type="text"
                                v-model="nameFilter"
                                @keyup.enter="getVacancies"
                                placeholder="Поиск по названию"
                                class="text-xs text-amber-700 placeholder-gray-400 bg-white border border-transparent focus:border-amber-700 focus:outline-none px-1 py-0.5"
                            />
                        </div>
                    </th>
                    <th class="border border-gray-400 px-1 py-2 w-32">
                        <div class="flex flex-col gap-1">
                            <span>Работодатель</span>
                            <input
                                type="text"
                                v-model="employerNameFilter"
                                @keyup.enter="getVacancies"
                                placeholder="Поиск по работодателю"
                                class="text-xs text-amber-700 placeholder-gray-400 bg-white border border-transparent focus:border-amber-700 focus:outline-none px-1 py-0.5"
                            />
                        </div>
                    </th>
                    <th class="border border-gray-400 px-1 py-2 w-32">Регион</th>
                    <th class="border border-gray-400 px-1 py-2 w-32">Страна</th>
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
                        <div class="flex items-center gap-1 overflow-hidden">
                            <a
                                :href="`https://hh.ru/vacancy/${vacancy.id}`"
                                target="_blank"
                                class="flex-shrink-0 px-1.5 py-0.5 text-[10px] font-semibold rounded"
                                style="background:#c5222a;color:#fff"
                                title="Открыть вакансию на hh.ru"
                            >
                                hh.ru
                            </a>
                            <div class="truncate whitespace-nowrap overflow-hidden" v-html="cleanHtml(vacancy.name)" :title="cleanAllHtml(vacancy.name)"></div>
                        </div>
                    </td>
                    <td class="border border-gray-400 px-1 py-2 max-w-32">
                        <div class="flex items-center gap-1 overflow-hidden">
                            <a
                                v-if="vacancy.employerId"
                                :href="`https://hh.ru/employer/${vacancy.employerId}`"
                                target="_blank"
                                class="flex-shrink-0 px-1.5 py-0.5 text-[10px] font-semibold rounded"
                                style="background:#c5222a;color:#fff"
                                title="Открыть работодателя на hh.ru"
                            >
                                hh.ru
                            </a>
                            <div class="truncate whitespace-nowrap overflow-hidden" :title="employers[vacancy.employerId]?.name ?? '—'">
                                {{ employers[vacancy.employerId]?.name ?? '—' }}
                            </div>
                        </div>
                    </td>
                    <td class="border border-gray-400 px-1 py-2 max-w-32">
                        <div class="truncate whitespace-nowrap overflow-hidden" :title="areas[vacancy.areaId]?.name ?? '—' ">
                            {{ areas[vacancy.areaId]?.name ?? '—' }}
                        </div>
                    </td>
                    <td class="border border-gray-400 px-1 py-2 max-w-32">
                        <div class="truncate whitespace-nowrap overflow-hidden" :title="countries[vacancy.countryId]?.name ?? '—' ">
                            {{ countries[vacancy.countryId]?.name ?? '—' }}
                        </div>
                    </td>
                    <td class="border border-gray-400 px-1 py-2 max-w-64">
                        <div class="truncate whitespace-nowrap overflow-hidden" v-html="cleanHtml(vacancy.description)" :title="cleanAllHtml(vacancy.description)"></div>
                    </td>
                    <td class="border border-gray-400 px-1 py-2 text-center">{{ vacancy.salaryFrom ?? '—' }}</td>
                    <td class="border border-gray-400 px-1 py-2 text-center">{{ vacancy.salaryTo ?? '—' }}</td>
                    <td class="border border-gray-400 px-1 py-2 text-center">{{ vacancy.salaryCurrency ?? '—' }}</td>
                    <td class="border border-gray-400 px-1 py-2 text-center">
                        <span :class="vacancy.archived ? 'text-red-600' : 'text-green-600'"> {{ vacancy.archived ? 'Да' : 'Нет' }}</span>
                    </td>
                    <td class="border border-gray-400 px-1 py-2 text-center">
                        <div class="truncate whitespace-nowrap overflow-hidden"> {{ vacancy.publishedAt ? formatDate(vacancy.publishedAt) : '—' }}</div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script>
    import api from '../../axios.js';

    export default {
        name: "index",

        data() {
            return {
                // Вакансии
                vacancies: [],
                // Работодатели
                employers: {},
                // Регионы
                areas: {},
                // Страны
                countries: {},
                // Фильтр по названию вакансии
                nameFilter: '',
                // Фильтр по названию работодателя
                employerNameFilter: '',
            }
        },

        mounted() {
            this.getVacancies();
        },

        methods: {
            getVacancies() {
                api.get('/vacancies', {
                    params: {
                        filters: {
                            name: this.nameFilter || undefined,
                            employerName: this.employerNameFilter || undefined
                        }
                    }
                })
                .then(res => {
                    this.vacancies = res.data.data;
                    this.employers = res.data.dictionaries?.employers || {};
                    this.areas = res.data.dictionaries?.areas || {};
                    this.countries = res.data.dictionaries?.countries || {};
                })
                .catch(err => {
                    console.error('Ошибка загрузки вакансий', err);
                });
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

            formatDate(timestamp) {
                // Преобразуем Unix timestamp из секунд в миллисекунды
                const date = new Date(timestamp * 1000);

                // Проверяем, что дата валидна
                if (isNaN(date.getTime())) {
                    return '—';
                }

                return date.toLocaleDateString('ru-RU', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
            }
        }
    }
</script>

<style scoped>

</style>