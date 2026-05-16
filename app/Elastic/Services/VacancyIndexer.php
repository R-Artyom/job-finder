<?php

namespace App\Elastic\Services;

use App\Elastic\ElasticClient;
use App\Elastic\Index;
use App\Models\Vacancy;
use Carbon\Carbon;

class VacancyIndexer
{
    public function index(Vacancy $vacancy): void
    {
        $client = ElasticClient::make();

        $client->index([
            'index' => Index::VACANCIES,
            'id' => $vacancy->id,
            'body' => [
                'id' => $vacancy->id,
                'name' => $vacancy->name,
                'description' => $vacancy->description,
                'area_id' => $vacancy->area_id,
                'employer_id' => $vacancy->employer_id,
                'salary_from' => $vacancy->salary_from,
                'salary_to' => $vacancy->salary_to,
                // Преобразование "1" и "0" в boolean
                'archived' => $vacancy->archived !== null
                    ? (bool) $vacancy->archived
                    : null,
                // Вызывать метод преобразования даты в формат ISO 8601 (напр. "2024-01-15T14:30:00+00:00")
                'published_at' => $vacancy->published_at
                    ? Carbon::parse($vacancy->published_at)->toISOString()
                    : null,
            ],
        ]);
    }
}
