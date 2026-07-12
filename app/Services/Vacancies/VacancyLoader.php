<?php

namespace App\Services\Vacancies;

use App\Models\Vacancy;
use Illuminate\Support\Collection;

class VacancyLoader
{
    public function load(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        // Получить модели вакансий по списку id
        $vacancies = Vacancy::query()
            ->leftJoin('employers', 'employers.id', 'vacancies.employer_id')
            ->leftJoin('areas', 'areas.id', 'vacancies.area_id')
            ->select(
                // №
                'vacancies.id',
                // Название
                'vacancies.name',
                // Работодатель
                'vacancies.employer_id',
                // Регион
                'vacancies.area_id',
                // Описание
                'vacancies.description',
                // ЗП от
                'vacancies.salary_from',
                // ЗП до
                'vacancies.salary_to',
                // Валюта
                'vacancies.salary_currency',
                // В архиве
                'vacancies.archived',
                // Опубликовано
                'vacancies.published_at',
                // Название работодателя
                'employers.name as employerName',
                // Страна
                'areas.country_id as countryId',
                // Оригинальная дата создания
                'vacancies.created_at',
            )
            ->whereIn('vacancies.id', $ids)
            ->get();

        // Отсортировать вакансии, как в ElasticSearch
        $order = array_flip($ids);
        $vacancies = $vacancies
            ->sortBy(fn ($vacancy) => $order[$vacancy->id])
            ->values();

        return $vacancies;
    }
}