<?php

namespace App\Services\Vacancies\Mapper;

use App\Services\Vacancies\DTO\SearchResponse;
use App\Services\Vacancies\VacancyLoader;

class SearchResponseParser
{
    public function __construct(
        private VacancyLoader $vacancyLoader,
    ) {}

    public function map(array $response): SearchResponse
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

        return new SearchResponse(
            vacancies: $this->vacancyLoader->load($ids),
            nextCursor: $nextCursor,
            totalCount: $response['hits']['total']['value'],
            facets: $this->parseAggregations($response),
        );
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
