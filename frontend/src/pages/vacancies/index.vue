<template>
    <div class="p-6">
        <!-- Спиннер -->
        <div v-if="loading" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="flex items-center gap-3 bg-white px-6 py-4 rounded shadow">
                <svg class="animate-spin h-5 w-5 text-orange-700" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                </svg>
                <span class="font-semibold">Загрузка...</span>
            </div>
        </div>

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
                                @keyup.enter="getVacancies(true)"
                                placeholder="Поиск по названию"
                                class="text-xs text-orange-700 placeholder-gray-400 bg-white border border-transparent focus:border-orange-700 focus:outline-none px-1 py-0.5"
                            />
                        </div>
                    </th>
                    <th class="border border-gray-400 px-1 py-2 w-32">
                        <div class="flex flex-col gap-1">
                            <span>Работодатель</span>
                            <input
                                type="text"
                                v-model="employerNameFilter"
                                @keyup.enter="getVacancies(true)"
                                placeholder="Поиск по работодателю"
                                class="text-xs text-orange-700 placeholder-gray-400 bg-white border border-transparent focus:border-orange-700 focus:outline-none px-1 py-0.5"
                            />
                            <FacetDropdown
                                v-if="hasFacet('employerId')"
                                v-model="selectedFilters.employerId"
                                :options="filterOptions.employerId"
                                :dictionary="employers"
                                placeholder="Работодатель"
                                @change="applyFilters"
                            />
                        </div>
                    </th>
                    <th class="border border-gray-400 px-1 py-2 w-32">
                        <span>Регион</span>
                        <FacetDropdown
                            v-if="hasFacet('areaId')"
                            v-model="selectedFilters.areaId"
                            :options="filterOptions.areaId"
                            :dictionary="areas"
                            placeholder="Регион"
                            @change="applyFilters"
                        />
                    </th>
                    <th class="border border-gray-400 px-1 py-2 w-32">
                        <span>Страна</span>
                        <FacetDropdown
                            v-if="hasFacet('countryId')"
                            v-model="selectedFilters.countryId"
                            :options="filterOptions.countryId"
                            :dictionary="countries"
                            placeholder="Страна"
                            @change="applyFilters"
                        />
                    </th>
                    <th class="border border-gray-400 px-1 py-2 w-64">Описание</th>
                    <th class="border border-gray-400 px-1 py-2 w-24">ЗП от</th>
                    <th class="border border-gray-400 px-1 py-2 w-24">ЗП до</th>
                    <th class="border border-gray-400 px-1 py-2 w-20">
                        <span>Валюта</span>
                        <FacetDropdown
                            v-if="hasFacet('salaryCurrency')"
                            v-model="selectedFilters.salaryCurrency"
                            :options="filterOptions.salaryCurrency"
                            placeholder="Валюта"
                            @change="applyFilters"
                        />
                    </th>
                    <th class="border border-gray-400 px-1 py-2 w-24">
                        <span>В архиве</span>
                        <FacetDropdown
                            v-if="hasFacet('archived')"
                            v-model="selectedFilters.archived"
                            :options="filterOptions.archived"
                            :label-map="{ 1: 'Да' }"
                            null-label="Нет"
                            placeholder="В архиве"
                            @change="applyFilters"
                        />
                    </th>
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

                <!-- Индикатор догрузки страниц-->
                <tr v-if="isLoadingMore">
                    <td colspan="11" class="py-2">
                        <div class="flex justify-center items-center gap-2 text-gray-400">
                            <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" opacity="0.25"/>
                                <path d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" fill="currentColor" opacity="0.75"/>
                            </svg>
                            Загрузка ещё…
                        </div>
                    </td>
                </tr>

                <!-- Конец списка -->
                <tr v-if="next === null && vacancies.length && !loading && !isLoadingMore">
                    <td colspan="11" class="text-center py-2 text-gray-400">
                        Больше вакансий нет
                    </td>
                </tr>

                <!-- якорь для IntersectionObserver -->
                <tr ref="loadMoreTrigger">
                    <td colspan="11" class="h-1"></td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script>
    import api from '../../axios.js';
    import FacetDropdown from '../../components/FacetDropdown.vue';

    export default {
        name: "index",

        components: {
            FacetDropdown
        },

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
                // Спиннер загрузки
                loading: false,
                // Параметры пагинации
                next: null,
                isLoadingMore: false,
                observer: null,
                // Фасеты
                filterOptions: {},
                selectedFilters: {
                    employerId: [],
                    areaId: [],
                    countryId: [],
                    salaryCurrency: [],
                    archived: [],
                },
            }
        },

        mounted() {
            this.getVacancies(true);

            // Объект, который следит за пересечением наблюдаемого элемента и области видимости (root) для запуска подгрузки новой страницы
            this.observer = new IntersectionObserver(
                entries => {
                    // Если элемент попал в зону наблюдения
                    if (entries[0].isIntersecting) {
                        this.getVacancies();
                    }
                },
                {
                    // viewport браузера, наблюдаем пересечение с окном страницы
                    root: null,
                    // Начинать грузить заранеена 200 пикс до скрытого элемента
                    rootMargin: '200px',
                    // Срабатывает, как только 1 пиксель элемента попал в зону
                    threshold: 0,
                }
            );

            // Браузер начинает следить за объектом после того, как Vue обновит DOM, при пересечении вызывает callback объекта
            this.$nextTick(() => {
                if (this.$refs.loadMoreTrigger) {
                    this.observer.observe(this.$refs.loadMoreTrigger);
                }
            });
        },

        beforeUnmount() {
            this.observer?.disconnect();
        },

        methods: {
            getVacancies(reset = false) {
                // Защиты от повторного вызова (IntersectionObserver может вызывать callback несколько раз, пока элемент в зоне)
                if (this.loading || this.isLoadingMore) {
                    return;
                }

                // Сброс фильтров и т.п.
                if (reset) {
                    this.vacancies = [];
                    this.next = null;
                // Если это непервый запрос и больше страниц нет — дальше грузить нельзя
                } else if (this.next === null) {
                    return;
                }

                // Отключаем observer на время запроса
                if (this.$refs.loadMoreTrigger) {
                    this.observer?.unobserve(this.$refs.loadMoreTrigger);
                }

                // Индикатор загрузки страницы с нуля
                this.loading = this.next === null;
                // Индикатор подгрузки новой страницы
                this.isLoadingMore = this.next !== null;

                api.get('/vacancies', {
                    params: {
                        next: this.next ?? undefined,
                        filters: {
                            // Текстовые фильтры
                            name: this.nameFilter || undefined,
                            employerName: this.employerNameFilter || undefined,

                            // Фильтры по значению
                            employerId: this.selectedFilters.employerId.length
                                ? this.selectedFilters.employerId
                                : undefined,

                            areaId: this.selectedFilters.areaId.length
                                ? this.selectedFilters.areaId
                                : undefined,

                            countryId: this.selectedFilters.countryId.length
                                ? this.selectedFilters.countryId
                                : undefined,

                            salaryCurrency: this.selectedFilters.salaryCurrency.length
                                ? this.selectedFilters.salaryCurrency
                                : undefined,

                            archived: this.selectedFilters.archived.length
                                ? this.selectedFilters.archived
                                : undefined,
                        }
                    }
                })
                .then(res => {
                    const data = res.data.data;

                    // Добавляем данные новой страницы, а не перезаписываем
                    this.vacancies.push(...data);
                    this.employers = {
                        ...this.employers,
                        ...(res.data.dictionaries?.employers || {})
                    };
                    this.areas = {
                        ...this.areas,
                        ...(res.data.dictionaries?.areas || {})
                    };
                    this.countries = {
                        ...this.countries,
                        ...(res.data.dictionaries?.countries || {})
                    };

                    // Флаг конца данных
                    this.next = res.data.pagination?.next ?? null;

                    // Если страницы ещё есть — снова включаем observer
                    if (this.next !== null) {
                        this.$nextTick(() => {
                            if (this.$refs.loadMoreTrigger) {
                                this.observer?.observe(this.$refs.loadMoreTrigger);
                            }
                        });
                    } else {
                        // Если страниц больше нет — отключаем навсегда
                        this.observer?.disconnect();
                    }

                    // Фасеты (опции фильтрации)
                    this.filterOptions = res.data.filterOptions || {};
                })
                .catch(err => {
                    console.error('Ошибка загрузки вакансий', err);
                })
                .finally(() => {
                    this.loading = false;
                    this.isLoadingMore = false;
                });
            },

            // Очистка текста от тегов HTML, кроме тегов выделения текста
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
            },

            hasFacet(key) {
                return Array.isArray(this.filterOptions?.[key])
                    && this.filterOptions[key].length > 0;
            },

            applyFilters() {
                this.getVacancies(true);
            },
        }
    }
</script>

<style scoped>

</style>