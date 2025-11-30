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
    // Данные для пагинации по умолчанию
    const LIMIT = 100;
    const OFFSET = 0;

    // Формат данных для фильтрации
    const FILTERS_FORMAT = [
        // Фильтры "ПО ЗНАЧЕНИЮ" - перечислить все ожидаемые поля с опциями фильтрации
        'filtersByIn' => [
            'employerId', // Работодатель
            'areaId', // Регион
            'salaryCurrency', // Валюта
        ],
        // Фильтры "ПО ШАБЛОНУ" в любой позиции - перечислить все ожидаемые поля без опций (Не должны совпадать с 'filtersByIn')
        'filtersByLike' => [],
    ];

    // Формат данных для словарей - перечислить поля, для которых необходим словарь (с названием словаря в качестве значения поля)
    const DICTIONARIES_FORMAT = [
        'employerId' => 'employers', // Работодатели
        'areaId' => 'areas', // Регионы
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
            // * Данные для пагинации:
            'limit' => 'integer',
            'offset' => 'integer',

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

        // Корректировка данных для пагинации
        $limit = (isset($validated['limit']) ? (int) $validated['limit'] : self::LIMIT) ?: null;
        $offset = isset($validated['offset']) ? (int) $validated['offset'] : self::OFFSET;

        // Построитель запроса
        $vacanciesModels = Vacancy::query()
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
            )
            ->with(['employer:id,name', 'area:id,name'])
            ->orderBy('id', 'desc')
            ->limit(100)
            ->get();

        // * Результирующий массив
        $result = [];
        $dictionariesAll = [];
        foreach ($vacanciesModels as $vacancy) {
            $result[] = [
                'id' => $vacancy->id,
                'name' => $vacancy->name,
                'employerId' => $vacancy->employer_id,
                'areaId' => $vacancy->area_id,
                'description' => $vacancy->description,
                'salaryFrom' => $vacancy->salary_from,
                'salaryTo' => $vacancy->salary_to,
                'salaryCurrency' => $vacancy->salary_currency,
                'archived' => $vacancy->archived,
                'publishedAt' => $vacancy->published_at,
            ];
            // Словарь "Работодатели"
            if (isset($vacancy->employer_id) && !isset($dictionariesAll['employers'][$vacancy->employer_id])) {
                $dictionariesAll['employers'][$vacancy->employer_id] = [
                    'id' => $vacancy->employer_id,
                    'name' => $vacancy->employer->name ?? null,
                ];
            }
            // Словарь "Регионы"
            if (isset($vacancy->area_id) && !isset($dictionariesAll['areas'][$vacancy->area_id])) {
                $dictionariesAll['areas'][$vacancy->area_id] = [
                    'id' => $vacancy->area_id,
                    'name' => $vacancy->area->name ?? null,
                ];
            }
        }

        // Преобразовать результирующий массив в коллекцию
        $resultCollect = collect($result);

        // * Опции фильтрации по полям
        $filterLists = $this->getOptionsForFilters($resultCollect, $validated['filters'] ?? null, self::FILTERS_FORMAT);

        // * Фильтрация
        $resultCollect = $this->filterCollection($resultCollect, $validated['filters'] ?? null, self::FILTERS_FORMAT);

        // * Общее количество отфильтрованных элементов
        $filteredCount = $resultCollect->count();

        // * Выбрать срез коллекции
        $resultCollect = $resultCollect->slice($offset, $limit);

        // * Словари
        $dictionaries = $this->getResultDictionaries($resultCollect, $filterLists, $dictionariesAll, self::DICTIONARIES_FORMAT);

        // * Результирующий массив
        return [
            // Данные
            'data' => $resultCollect->values()->toArray(),
            // Доступные для фильтрации опции
            'filterOptions' => $filterLists,
            // Словари
            'dictionaries' => $dictionaries,
            // Общее количество отфильтрованных элементов
            'filteredElementsCount' => $filteredCount
        ];
    }
}
