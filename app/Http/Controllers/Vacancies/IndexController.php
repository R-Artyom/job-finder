<?php

namespace App\Http\Controllers\Vacancies;

use App\Http\Controllers\Controller;
use App\Models\Vacancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class IndexController extends Controller
{
    // Параметры пагинации по умолчанию
    private const MAX_LIMIT = 200;
    private const DEFAULT_LIMIT = 100;

    // Формат данных для фильтрации
    // "in" - фильтр по значению
    // "like" - фильтр по шаблону
    // "date" - фильтр по дате
    // "from" - фильтр "От"
    // "to" - фильтр "До"
    const FILTERS_FORMAT = [
        // Работодатель
        'employerId' => [
            'type' => 'in',
            'column' => 'employer_id',
        ],
        // Регион
        'areaId' => [
            'type' => 'in',
            'column' => 'area_id',
        ],
        // Валюта
        'salaryCurrency' => [
            'type' => 'in',
            'column' => 'salary_currency',
        ],
        // В архиве
        'archived' => [
            'type' => 'in',
            'column' => 'archived',
        ],
        // Название
        'name' => [
            'type' => 'like',
            'column' => 'name',
        ],
        // Описание
        'description' => [
            'type' => 'like',
            'column' => 'description',
        ],
        // Дата публикации
        'publishedAt' => [
            'type' => 'date',
            'column' => 'published_at',
        ],
        // ЗП от
        'salaryFrom' => [
            'type' => 'from',
            'column' => 'salary_from',
        ],
        // ЗП до
        'salaryTo' => [
            'type' => 'to',
            'column' => 'salary_to',
        ],
    ];

    // Поля для сортировки
    const SORT_FIELD = [
        'id' => 'id',
        'publishedAt' => 'published_at',
        'name' => 'name',
    ];

    /**
     * Список вакансий
     *
     * @param Request $request
     * @return JsonResponse|array
     * @throws ValidationException
     */
    public function __invoke(Request $request): JsonResponse|array
    {
        // Валидация
        $validator = Validator::make($request->all(), [
            // * Параметры курсорной пагинации (keyset pagination):
            'limit' => 'integer',
            'next' => 'integer',

            // * Фильтры с опциями:
            'filters' => 'array|min:1',
            // Работодатель
            'filters.employerId' => 'array',
            'filters.employerId.*' => 'nullable|distinct|integer',
            // Регион
            'filters.areaId' => 'array',
            'filters.areaId.*' => 'nullable|distinct|integer',
            // Валюта
            'filters.salaryCurrency' => 'array',
            'filters.salaryCurrency.*' => 'nullable|distinct|string',
            // В архиве
            'filters.archived' => 'array',
            'filters.archived.*' => 'nullable|distinct|integer',

            // * Фильтры без опций:
            // Название вакансии
            'filters.name' => 'string',
            // Описание вакансии
            'filters.description' => 'string',
            // ЗП от
            'filters.salaryFrom' => 'integer',
            // ЗП до
            'filters.salaryTo' => 'integer',
            // Дата публикации
            'filters.publishedAt' => 'array|size:2',
            'filters.publishedAt.*' => 'string',

            // * Сортировка:
            'sort' => 'array|min:1',
            'sort.*.field' => 'required_with:sort|string|in:id,publishedAt,name',
            'sort.*.order' => 'string|in:asc,desc',
        ]);
        // Ошибки валидации
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Failed',
                'errors' => $validator->errors(),
            ], 400);
        }
        // Проверенные данные
        $validated = $validator->validated();

        // * Параметры пагинации
        // Предельное количество возвращаемых данных
        $limit = isset($validated['limit'])
            ? max(1, min(self::MAX_LIMIT, (int) $validated['limit']))
            : self::DEFAULT_LIMIT;
        // Значение, после которого необходимо получить следующий пакет данных.
        // В первом запросе должен отсутствовать. Для следующих запросов необходимо брать значения из одноимённого поля в ответе
        $next = isset($validated['next']) ? (int) $validated['next'] : null;

        // * Параметры фильтрации
        $filters = $validated['filters'] ?? [];

        // * Параметры множественной сортировки
        if (!empty($validated['sort'])) {
            foreach ($validated['sort'] as $value) {
                // $sort[0] - параметры для первичной сортировки, $sort[1] - вторичной и т.д.
                $sort[] = [
                    'field' => self::SORT_FIELD[$value['field']],
                    'order' => $value['order'] ?? 'asc',
                ];
            }
        }
        // Сортировка по умолчанию
        if (empty($sort)) {
            $sort[0] = [
                'field' => 'id',
                'order' => 'desc',
            ];
        }

        // * Начальное значение построителя запроса
        $vacanciesBuilder = Vacancy::query()
            ->select(
                // №
                'id',
                // Название
                'name',
                // Работодатель
                'employer_id',
                // Регион
                'area_id',
                // Описание
                'description',
                // ЗП от
                'salary_from',
                // ЗП до
                'salary_to',
                // Валюта
                'salary_currency',
                // В архиве
                'archived',
                // Опубликовано
                'published_at',
            );

        // * Фильтрация
        $this->applyFiltersToQuery($vacanciesBuilder, $filters, self::FILTERS_FORMAT);

        // * Пагинация (с учетом множественной сортировки)
        if ($next !== null) {
            // Последняя (граничная) запись на текущей странице
            $pivot = Vacancy::query()
                ->select('id', 'published_at', 'name')
                ->where('id', $next)
                ->first();
            // Если эта запись существует, то найти все записи, идущие за ней (в соответствии с сортировкой)
            if ($pivot) {
                $vacanciesBuilder->where(function ($builder) use ($sort, $pivot) {
                    $this->applyMultiFieldKeyset($builder, $sort, $pivot, 0);
                });
            }
        }

        // * Сортировка
        foreach ($sort as $value) {
            $vacanciesBuilder->orderBy($value['field'], $value['order']);
        }

        // * Получение данных (+1 строка для hasMore)
        $vacanciesModels = $vacanciesBuilder->limit($limit + 1)->get();
        // Признак наличия других страниц
        $hasMore = $vacanciesModels->count() > $limit;
        // Обрезка, если надо
        if ($hasMore) {
            $vacanciesModels = $vacanciesModels->slice(0, $limit);
        }

        // * Ссылка на id самой последней записи текущей страницы после выполнения всех фильтраций и сортировок
        $next = $vacanciesModels->isNotEmpty() && $hasMore ? $vacanciesModels->last()->id : null;

        // * Фасеты
        $facetResults = $this->getFacetOptions($filters, self::FILTERS_FORMAT);

        // * Словари
        // Id работодателей (исключить пустые значения)
        $ids['employers'] = array_unique(array_merge(
            $vacanciesModels->pluck('employer_id')->filter()->all(),
            collect($facetResults['employerId'] ?? [])->filter()->all()
        ));
        // Id регионов (исключить пустые значения)
        $ids['areas'] = array_unique(array_merge(
            $vacanciesModels->pluck('area_id')->filter()->all(),
            collect($facetResults['areaId'] ?? [])->filter()->all()
        ));
        // Получить словари
        $dictionaries = $this->getDictionaries($ids);

        // * Подсчёт общего количества записей
        $countQuery = Vacancy::query();
        $this->applyFiltersToQuery($countQuery, $filters, self::FILTERS_FORMAT);
        $totalCount = $countQuery->count();

        // * Ответ
        return [
            'data' => $vacanciesModels->values()->map(function ($vacancy) {
                return [
                    'id' => $vacancy->id,
                    'name' => $vacancy->name,
                    'employerId' => $vacancy->employer_id,
                    'areaId' => $vacancy->area_id,
                    'description' => $vacancy->description,
                    'salaryFrom' => $vacancy->salary_from,
                    'salaryTo' => $vacancy->salary_to,
                    'salaryCurrency' => $vacancy->salary_currency,
                    'archived' => $vacancy->archived,
                    'publishedAt' => empty($vacancy->published_at) ? null : strtotime($vacancy->published_at),
                ];
            }),
            'filterOptions' => $facetResults,
            'dictionaries' => $dictionaries,
            'pagination' => [
                'limit' => $limit,
                'next' => $next,
            ],
            'filteredElementsCount' => $totalCount,
        ];
    }

    /**
     * Фасеты (опции фильтрации)
     *
     * @param array $filters Массив фильтров из запроса
     * @param array $filtersFormat Формат данных
     * @return array
     */
    protected function getFacetOptions(array $filters, array $filtersFormat): array
    {
        $facetResults = [];
        foreach ($filtersFormat as $key => $value) {
            if ($value['type'] === 'in') {
                // Полная копия входных фильтров
                $filtersForFacet = $filters;
                // Удаление фильтра, для которого будет происходить поиск опций
                unset($filtersForFacet[$key]);
                // Формирование запроса
                $query = Vacancy::query();
                // Применить усеченные фильтьры
                $this->applyFiltersToQuery($query, $filtersForFacet, self::FILTERS_FORMAT);
                // Результат
                $facetResults[$key] = $query->select($value['column'])
                    ->distinct()
                    ->orderBy($value['column'])
                    ->pluck($value['column'])
                    ->toArray();
            }
        }

        return $facetResults;
    }
}
