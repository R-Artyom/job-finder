<?php

namespace App\Http\Controllers\Vacancies;

use App\Http\Controllers\Controller;
use App\Models\Vacancy;
use Illuminate\Http\Request;

class IndexController extends Controller
{
    /**
     * Список вакансий
     */
    public function __invoke(Request $request): array
    {
        $vacanciesModels = Vacancy::query()
            ->with('employer:id,name')
            ->orderBy('id', 'desc')
            ->limit(100)
            ->get();

        // Результирующий массив
        $result = [];
        foreach ($vacanciesModels as $vacancy) {
            $result['data'][] = [
                // №
                'id' => $vacancy->id,
                // Название
                'name' => $vacancy->name,
                // Работодатель
                'employer_id' => $vacancy->employer_id,
                // Регион
                'area_id' => $vacancy->area_id,
                // Описание
                'description' => $vacancy->description,
                // ЗП от
                'salary_from' => $vacancy->salary_from,
                // ЗП до
                'salary_to' => $vacancy->salary_to,
                // Валюта
                'salary_currency' => $vacancy->salary_currency,
                // В архиве
                'archived' => $vacancy->archived,
                // Опубликовано
                'published_at' => $vacancy->published_at,
            ];
            $result['dictionaries']['employers'][$vacancy->employer->id] = [
                'id' => $vacancy->employer->id,
                'name' => $vacancy->employer->name,
            ];
        }

        return $result;
    }
}
