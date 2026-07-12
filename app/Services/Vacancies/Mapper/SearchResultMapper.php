<?php

namespace App\Services\Vacancies\Mapper;

use App\Models\Vacancy;

class SearchResultMapper
{
    public function map(array $response): array
    {
        // 1. Получить cursor следующей страницы
        $hits = $response['hits']['hits'];
        $nextCursor = null;
        if (!empty($hits)) {
            $lastHit = end($hits);
            $nextCursor = $lastHit['sort'] ?? null;
        }

//        dd(array_column($hits, '_source'));

        // 2. Получить модели вакансий по списку id
        $ids = collect($hits)
            ->pluck('_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $vacanciesModels = Vacancy::query()
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

        // 3. Отсортировать вакансии, как в ElasticSearch
        $order = array_flip($ids);
        $vacanciesModels = $vacanciesModels
            ->sortBy(fn ($item) => $order[$item->id])
            ->values();

        return [
            'vacanciesModels' => $vacanciesModels,
            'next' => $nextCursor,
            'totalCount' => $response['hits']['total']['value'],
            'facets' => $this->parseAggregations($response),
        ];
    }

    private function parseAggregations(array $response): array
    {
        $result = [];

        foreach ($response['aggregations'] as $name => $aggregation) {
            $result[$name] = collect($aggregation['values']['buckets'])
                ->pluck('key')
                ->all();
        }

        return $result;
    }
}
