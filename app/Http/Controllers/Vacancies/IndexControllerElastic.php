<?php

namespace App\Http\Controllers\Vacancies;

use App\Http\Controllers\Controller;
use App\Models\Counter;
use App\Models\Vacancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\DTO\Vacancies\SearchDTO;
use App\Services\Vacancies\SearchService;

class IndexControllerElastic extends Controller
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
            'column' => 'vacancies.employer_id',
        ],
        // Регион
        'areaId' => [
            'type' => 'in',
            'column' => 'vacancies.area_id',
        ],
        // Валюта
        'salaryCurrency' => [
            'type' => 'in',
            'column' => 'vacancies.salary_currency',
        ],
        // В архиве
        'archived' => [
            'type' => 'in',
            'column' => 'vacancies.archived',
        ],
        // Название
        'name' => [
            'type' => 'like',
            'column' => 'vacancies.name',
        ],
        // Описание
        'description' => [
            'type' => 'like',
            'column' => 'vacancies.description',
        ],
        // Название работодателя
        'employerName' => [
            'type' => 'like',
            'column' => 'employers.name',
        ],
        // Дата публикации
        'publishedAt' => [
            'type' => 'date',
            'column' => 'vacancies.published_at',
        ],
        // ЗП от
        'salaryFrom' => [
            'type' => 'from',
            'column' => 'vacancies.salary_from',
        ],
        // ЗП до
        'salaryTo' => [
            'type' => 'to',
            'column' => 'vacancies.salary_to',
        ],
        // Страна
        'countryId' => [
            'type' => 'in',
            'column' => 'areas.country_id',
        ],
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
            'next' => 'array',

            // * Фильтры с опциями:
            'filters' => 'array|min:1',
            // Работодатель
            'filters.employerId' => 'array',
            'filters.employerId.*' => 'nullable|distinct|integer',
            // Регион
            'filters.areaId' => 'array',
            'filters.areaId.*' => 'nullable|distinct|integer',
            // Страна
            'filters.countryId' => 'array',
            'filters.countryId.*' => 'nullable|distinct|integer',
            // Валюта
            'filters.salaryCurrency' => 'array',
            'filters.salaryCurrency.*' => 'nullable|distinct|string',
            // В архиве
            'filters.archived' => 'array',
            'filters.archived.*' => 'nullable|distinct|integer',

            // * Фильтры без опций:
            // Название вакансии
            'filters.name' => 'string|min:3',
            // Описание вакансии
            'filters.description' => 'string|min:3',
            // Название работодателя
            'filters.employerName' => 'string|min:3',
            // ЗП от
            'filters.salaryFrom' => 'integer',
            // ЗП до
            'filters.salaryTo' => 'integer',
            // Дата публикации
            'filters.publishedAt' => 'array|size:2',
            'filters.publishedAt.*' => 'nullable|string',

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
        $next = $validated['next'] ?? null;

        // * Параметры фильтрации
        $filters = $validated['filters'] ?? [];

        // * Параметры множественной сортировки
        $elasticSort = [];
        $sortMap = [
            'id' => 'id',
            'publishedAt' => 'published_at',
            'name' => 'name.keyword',
        ];
        $hasIdSort = false;
        foreach ($validated['sort'] ?? [] as $value) {
            if (!isset($sortMap[$value['field']])) {
                continue;
            }
            if ($value['field'] === 'id') {
                $hasIdSort = true;
            }
            $elasticSort[] = [
                $sortMap[$value['field']] => $value['order'],
            ];
        }
        // Сортировка по одному уникальному полю обязательна (в данном случае id)
        if (!$hasIdSort) {
            $elasticSort[] = [
                'id' => 'desc',
            ];
        }

        // Объект с данными для поиска через Elasticsearch
        $dto = new SearchDTO(
            limit: $limit,
            cursor: $next,
            sort: $elasticSort,
            filters: $filters
        );

        // Найти все вакансии в соотвествии с фисльтрами и сортировкой
        $result = app(SearchService::class)->search($dto);

        // Найденные мождели вакансий
        $vacanciesModels = $result['vacanciesModels'];

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
        // Id стран (исключить пустые значения)
        $ids['countries'] = array_unique(array_merge(
            $vacanciesModels->pluck('countryId')->filter()->all(),
            collect($facetResults['countryId'] ?? [])->filter()->all()
        ));
        // Получить словари
        $dictionaries = $this->getDictionaries($ids);

        // * Id последней вакансии
        $lastElementId = Counter::query()
            ->where('name','vacancyId')
            ->value('limit');

        // * Ответ
        return [
            'data' => $vacanciesModels->values()->map(function ($vacancy) {
                return [
                    'id' => $vacancy->id,
                    'name' => $vacancy->name,
                    'employerId' => $vacancy->employer_id,
                    'areaId' => $vacancy->area_id,
                    'countryId' => $vacancy->countryId,
                    'description' => $vacancy->description,
                    'salaryFrom' => $vacancy->salary_from,
                    'salaryTo' => $vacancy->salary_to,
                    'salaryCurrency' => $vacancy->salary_currency,
                    'archived' => $vacancy->archived,
                    'publishedAt' => empty($vacancy->published_at) ? null : strtotime($vacancy->published_at),
                    'createdAt' => empty($vacancy->created_at) ? null : strtotime($vacancy->created_at),
                ];
            }),
            'filterOptions' => $facetResults,
            'dictionaries' => $dictionaries,
            'pagination' => [
                'limit' => $limit,
                'next' => $result['next'],
            ],
            'lastElementId' => $lastElementId,
            'filteredElementsCount' => $result['totalCount'],
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
                $query = Vacancy::query()
                    ->leftJoin('employers', 'employers.id', 'vacancies.employer_id')
                    ->leftJoin('areas', 'areas.id', 'vacancies.area_id')
                    ->select($value['column']);
                // Применить усеченные фильтры
                $this->applyFiltersToQuery($query, $filtersForFacet, self::FILTERS_FORMAT);
                // Результат
                $facetResults[$key] = $query
                    ->distinct()
                    ->orderBy($value['column'])
                    ->pluck($value['column'])
                    ->toArray();
            }
        }

        return $facetResults;
    }
}
