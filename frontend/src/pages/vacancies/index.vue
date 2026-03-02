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

        <!-- № последней вакансии -->
        <div
            v-if="lastElementId"
            title="Максимальный ID вакансии в базе"
            class="fixed top-4 right-4 z-40 bg-white/90 backdrop-blur border border-orange-700 rounded-lg px-4 py-2 shadow text-sm text-gray-700 flex items-center gap-2"
        >
            <span class="text-gray-400">№ последней вакансии:</span>
            <span class="font-semibold text-orange-700">
                <a
                    :href="`https://hh.ru/vacancy/${lastElementId}`"
                    target="_blank"
                    class="hover:underline"
                    title="Открыть вакансию на hh.ru"
                >
                    {{ lastElementId.toLocaleString('ru-RU') }}
                </a>
            </span>
        </div>

        <h1 class="text-2xl font-bold mb-4">Список вакансий</h1>

        <table class="min-w-full border border-gray-400 border-collapse text-sm">
            <thead class="bg-gray-100">
                <tr>
                    <th class="border border-gray-400 px-1 py-2 w-16">№</th>
                    <th class="border border-gray-400 px-1 py-2 w-70">
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
                    <th class="border border-gray-400 px-1 py-2 w-56">
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
                    <th class="border border-gray-400 px-1 py-2 w-56">Сайт</th>
                    <th class="border border-gray-400 px-1 py-2 w-26">
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
                    <th class="border border-gray-400 px-1 py-2 w-26">
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
                    <th class="border border-gray-400 px-1 py-2 w-72">Описание</th>
                    <th class="border border-gray-400 px-1 py-2 w-18">ЗП от</th>
                    <th class="border border-gray-400 px-1 py-2 w-18">ЗП до</th>
                    <th class="border border-gray-400 px-1 py-2 w-18">
                        <span>Валюта</span>
                        <FacetDropdown
                            v-if="hasFacet('salaryCurrency')"
                            v-model="selectedFilters.salaryCurrency"
                            :options="filterOptions.salaryCurrency"
                            placeholder="Валюта"
                            @change="applyFilters"
                        />
                    </th>
                    <th class="border border-gray-400 px-1 py-2 w-18">
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
                    <th class="border border-gray-400 px-1 py-2 w-32">
                        <div class="flex flex-col gap-1">
                            <span>Опубликовано</span>
                            <div class="flex gap-1">
                                <input
                                    type="date"
                                    v-model="publishedFromDate"
                                    :class="[
                                        'text-xs bg-white border px-1 py-0.5 focus:outline-none flex-1',
                                        publishedFromDate
                                            ? 'text-orange-700'
                                            : 'text-gray-400',
                                        publishedDateError
                                            ? 'border-red-600 focus:border-red-600'
                                            : 'border-transparent focus:border-orange-700'
                                    ]"
                                    @change="onPublishedFromChange"
                                />
                                <input
                                    type="time"
                                    v-model="publishedFromTime"
                                    :class="[
                                        'text-xs bg-white border px-1 py-0.5 focus:outline-none w-13',
                                        publishedFromDate
                                            ? 'text-orange-700'
                                            : 'text-gray-400',
                                        publishedDateError
                                            ? 'border-red-600 focus:border-red-600'
                                            : 'border-transparent focus:border-orange-700'
                                    ]"
                                    @change="onPublishedFromTimeChange"
                                />
                            </div>
                            <div class="flex gap-1 mt-1">
                                <input
                                    type="date"
                                    v-model="publishedToDate"
                                    :class="[
                                        'text-xs bg-white border px-1 py-0.5 focus:outline-none flex-1',
                                        publishedToDate
                                            ? 'text-orange-700'
                                            : 'text-gray-400',
                                        publishedDateError
                                            ? 'border-red-600 focus:border-red-600'
                                            : 'border-transparent focus:border-orange-700'
                                    ]"
                                    @change="onPublishedToChange"
                                />
                                <input
                                    type="time"
                                    v-model="publishedToTime"
                                    :class="[
                                        'text-xs bg-white border px-1 py-0.5 focus:outline-none w-13',
                                        publishedToDate
                                            ? 'text-orange-700'
                                            : 'text-gray-400',
                                        publishedDateError
                                            ? 'border-red-600 focus:border-red-600'
                                            : 'border-transparent focus:border-orange-700'
                                    ]"
                                    @change="onPublishedToTimeChange"
                                />
                            </div>
                            <p v-if="publishedDateError" class="text-xs text-red-600 leading-tight">
                                {{ publishedDateError }}
                            </p>
                        </div>
                    </th>
                    <th class="border border-gray-400 px-1 py-2 w-32">Создано</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="vacancy in vacancies" :key="vacancy.id" class="hover:bg-gray-50">
                    <td class="border border-gray-400 px-1 py-2 text-center">{{ vacancy.id.toLocaleString('ru-RU') }}</td>
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
                    <td class="border border-gray-400 px-1 py-2 max-w-56">
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
                            <a
                                v-if="vacancy.employerId"
                                :href="`/vacancies?filters[employerId][]=${vacancy.employerId}`"
                                target="_blank"
                                rel="noopener"
                                class="truncate whitespace-nowrap overflow-hidden text-orange-800 hover:underline"
                                :title="employers[vacancy.employerId]?.name ?? '—'"
                            >
                                {{ employers[vacancy.employerId]?.name ?? '—' }}
                            </a>

                            <span v-else>—</span>
                        </div>
                    </td>
                    <td class="border border-gray-400 px-1 py-0 max-w-56 align-middle overflow-visible">
                        <div class="flex items-center gap-1 h-full group">
                            <!-- Есть siteUrl -->
                            <template v-if="employers[vacancy.employerId]?.siteUrl">
                                <div class="flex items-center h-full w-[32px] min-w-[32px]">
                                    <!-- Логотип компании -->
                                    <img
                                        v-if="employers[vacancy.employerId]?.logoUrl"
                                        :src="employers[vacancy.employerId].logoUrl"
                                        class="h-full max-h-[32px] w-auto rounded-md object-contain flex-shrink-0 group-hover:scale-450 group-hover:z-50 group-hover:relative transition-transform duration-200 transform-gpu"
                                        loading="lazy"
                                        alt=""
                                        style="transform-origin: right center"
                                    />

                                    <!-- Логотип по умолчанию (пустой блок такого же размера) -->
                                    <div
                                        v-else
                                        class="h-[32px] w-[32px] flex-shrink-0"
                                        title="Логотип не указан"
                                    ></div>
                                </div>

                                <!-- siteUrl текстом -->
                                <a
                                    :href="employers[vacancy.employerId].siteUrl"
                                    target="_blank"
                                    rel="noopener"
                                    class="truncate whitespace-nowrap text-orange-800 hover:underline ml-1 group-hover:opacity-30 transition-opacity"
                                    :title="employers[vacancy.employerId].siteUrl"
                                >
                                    {{ employers[vacancy.employerId].siteUrl }}
                                </a>
                            </template>

                            <!-- Нет siteUrl -->
                            <span v-else class="flex-1 text-center">—</span>
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
                        <span :class="vacancy.archived ? 'text-orange-800' : 'text-green-600'"> {{ vacancy.archived ? 'Да' : 'Нет' }}</span>
                    </td>
                    <td class="border border-gray-400 px-1 py-2 text-center">
                        <div class="truncate whitespace-nowrap overflow-hidden"> {{ vacancy.publishedAt ? formatDate(vacancy.publishedAt) : '—' }}</div>
                    </td>
                    <td class="border border-gray-400 px-1 py-2 text-center">
                        <div class="truncate whitespace-nowrap overflow-hidden"> {{ vacancy.createdAt ? formatDate(vacancy.createdAt) : '—' }}</div>
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
                // Id последней вакансии
                lastElementId: null,
                // Опубликовано (разделено на дату и время)
                publishedFromDate: '',
                publishedFromTime: '00:00',
                publishedToDate: '',
                publishedToTime: '23:59',
                publishedDateError: null,
            }
        },

        mounted() {
            const params = new URLSearchParams(window.location.search);
            // Чтение employerId из URL
            const employerIds = params.getAll('filters[employerId][]');
            if (employerIds.length) {
                this.selectedFilters.employerId = employerIds.map(id => Number(id));
            }

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

                // Полные даты для API (конвертируем в UTC)
                const publishedFrom = this.publishedFromDate
                    ? this.convertLocalToUTC(`${this.publishedFromDate}T${this.publishedFromTime}:00`)
                    : '';

                const publishedTo = this.publishedToDate
                    ? this.convertLocalToUTC(`${this.publishedToDate}T${this.publishedToTime}:00`)
                    : '';

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

                            // Фильтры по дате
                            publishedAt: (this.publishedFromDate || this.publishedToDate)
                                ? [
                                    publishedFrom,
                                    publishedTo,
                                ]
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

                    // Id последней вакансии
                    this.lastElementId = res.data.lastElementId ?? null;
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

            // Действие, необходимое выполнить при изменении данных фильтрации
            applyFilters() {
                // Если дата "От" больше даты "До", то запрос на бэк отправлять не надо
                if (!this.validatePublishedRange()) {
                    return;
                }
                this.getVacancies(true);
            },

            // Валидация значений даты "От" и "До" (с учетом временных зон)
            validatePublishedRange() {
                // Если обе даты пустые - валидно
                if (!this.publishedFromDate && !this.publishedToDate) {
                    this.publishedDateError = null;
                    return true;
                }

                // Создаем полные даты для сравнения (локальное время)
                let from, to;

                if (this.publishedFromDate) {
                    const fromTime = this.publishedFromTime || '00:00';
                    from = new Date(`${this.publishedFromDate}T${fromTime}`);
                }

                if (this.publishedToDate) {
                    const toTime = this.publishedToTime || '23:59';
                    to = new Date(`${this.publishedToDate}T${toTime}`);
                }

                // Проверяем валидность дат
                if (from && isNaN(from.getTime())) {
                    this.publishedDateError = 'Некорректная дата "От"';
                    return false;
                }

                if (to && isNaN(to.getTime())) {
                    this.publishedDateError = 'Некорректная дата "До"';
                    return false;
                }

                // Если есть обе даты, проверяем диапазон
                if (from && to && from > to) {
                    this.publishedDateError = 'Дата "От" не может быть больше даты "До"';
                    return false;
                }

                this.publishedDateError = null;
                return true;
            },

            // Обработчик изменения даты "От"
            onPublishedFromChange() {
                // Автоматически устанавливаем время 00:00 при выборе даты
                if (this.publishedFromDate && !this.publishedFromTime) {
                    this.publishedFromTime = '00:00';
                }
                this.applyFilters();
            },

            // Обработчик изменения даты "До"
            onPublishedToChange() {
                // Автоматически устанавливаем время 23:59 при выборе даты
                if (this.publishedToDate && !this.publishedToTime) {
                    this.publishedToTime = '23:59';
                }
                this.applyFilters();
            },

            // Обработчик изменения времени "От"
            onPublishedFromTimeChange() {
                // Отправляем запрос только если дата установлена
                if (this.publishedFromDate) {
                    this.applyFilters();
                }
                // Если дата не установлена - игнорируем изменение времени
            },

            // Обработчик изменения времени "До"
            onPublishedToTimeChange() {
                // Отправляем запрос только если дата установлена
                if (this.publishedToDate) {
                    this.applyFilters();
                }
                // Если дата не установлена - игнорируем изменение времени
            },

            // Конвертация локального времени в UTC для отправки на бэкенд
            convertLocalToUTC(localDateTime) {
                // Если дата отсутствует совсем, то параметр в url всё равно надо отправить с пустым значением, на бэке это будет null
                if (!localDateTime) {
                    return '';
                }

                try {
                    // Создаем дату из локального времени
                    const localDate = new Date(localDateTime);

                    // Проверяем валидность даты
                    if (isNaN(localDate.getTime())) {
                        console.error('Некорректная дата:', localDateTime);
                        return '';
                    }

                    // Получаем UTC компоненты
                    const year = localDate.getUTCFullYear();
                    const month = String(localDate.getUTCMonth() + 1).padStart(2, '0');
                    const day = String(localDate.getUTCDate()).padStart(2, '0');
                    const hours = String(localDate.getUTCHours()).padStart(2, '0');
                    const minutes = String(localDate.getUTCMinutes()).padStart(2, '0');
                    const seconds = String(localDate.getUTCSeconds()).padStart(2, '0');

                    // Формат для бэкенда: 'YYYY-MM-DD HH:mm:ss'
                    return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
                } catch (error) {
                    console.error('Ошибка конвертации даты:', error);
                    return '';
                }
            },

        }
    }
</script>

<style scoped>

</style>