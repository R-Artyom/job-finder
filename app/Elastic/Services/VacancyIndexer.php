<?php

namespace App\Elastic\Services;

use App\Elastic\ElasticClient;
use App\Models\Vacancy;
use Carbon\Carbon;

class VacancyIndexer
{
    public function index(Vacancy $vacancy, string $index): void
    {
        $client = ElasticClient::make();

        $client->index([
            'index' => $index,
            'id' => $vacancy->id,
            'body' => [
                // № вакансии
                'id' => $vacancy->id,

                // * "in" - фильтр по значению
                // Id работодателя
                'employer_id' => $vacancy->employer_id,
                // Id региона
                'area_id' => $vacancy->area_id,
                // Валюта
                'salary_currency' => $vacancy->salary_currency,
                // В архиве
                'archived' => (bool) $vacancy->archived,
                // Id страны
                'country_id' => $vacancy->countryId,

                // * "like" - фильтр по шаблону
                // Название вакансии
                'name' => $vacancy->name,
                // Описание вакансии
                'description' => $vacancy->description,
                // Название работодателя
                'employer_name' => $vacancy->employerName,

                // * "from" - фильтр "От"
                // ЗП от
                'salary_from' => $vacancy->salary_from,

                // * "to" - фильтр "До"
                // ЗП до
                'salary_to' => $vacancy->salary_to,

                // * "date" - фильтр по дате
                // Вызывать метод преобразования даты в формат ISO 8601 (напр. "2024-01-15T14:30:00+00:00")
                // Опубликовано
                'published_at' => $vacancy->published_at
                    ? Carbon::parse($vacancy->published_at)->toISOString()
                    : null,
                // Создано
                'created_at' => $vacancy->created_at
                    ? Carbon::parse($vacancy->created_at)->toISOString()
                    : null,
                // Обновлено
                'updated_at' => $vacancy->updated_at
                    ? Carbon::parse($vacancy->updated_at)->toISOString()
                    : null,
            ],
        ]);
    }
}
