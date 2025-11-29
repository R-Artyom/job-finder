<?php

namespace App\Http\Controllers\Vacancies;

use App\Http\Controllers\Controller;
use App\Models\Vacancy;
use Illuminate\Http\Request;

class IndexController extends Controller
{
    // Формат данных для словарей - перечислить поля, для которых необходим словарь (с названием словаря в качестве значения поля)
    const DICTIONARIES_FORMAT = [
        'employerId' => 'employers', // Работодатели
        'areaId' => 'areas', // Регионы
    ];

    /**
     * Список вакансий
     */
    public function __invoke(Request $request): array
    {
        $vacanciesModels = Vacancy::query()
            ->with(['employer:id,name', 'area:id,name'])
            ->orderBy('id', 'desc')
            ->limit(100)
            ->get();

        // * Результирующий массив
        $result = [];
        $dictionariesAll = [];
        foreach ($vacanciesModels as $vacancy) {
            $result[] = [
                // №
                'id' => $vacancy->id,
                // Название
                'name' => $vacancy->name,
                // Работодатель
                'employerId' => $vacancy->employer_id,
                // Регион
                'areaId' => $vacancy->area_id,
                // Описание
                'description' => $vacancy->description,
                // ЗП от
                'salaryFrom' => $vacancy->salary_from,
                // ЗП до
                'salaryTo' => $vacancy->salary_to,
                // Валюта
                'salaryCurrency' => $vacancy->salary_currency,
                // В архиве
                'archived' => $vacancy->archived,
                // Опубликовано
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

        // * Словари
        $dictionaries = $this->getResultDictionaries($result, $filterLists ?? [], $dictionariesAll, self::DICTIONARIES_FORMAT);

        // * Результирующий массив
        return [
            // Данные
            'data' => $result,
            // Словари
            'dictionaries' => $dictionaries,
        ];
    }
}
