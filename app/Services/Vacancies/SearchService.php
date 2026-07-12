<?php

namespace App\Services\Vacancies;

use App\DTO\Vacancies\SearchDTO;
use App\Elastic\ElasticClient;
use App\Elastic\Index;
use App\Services\Vacancies\Aggregation\AggregationBuilder;
use App\Services\Vacancies\Mapper\SearchResultMapper;
use App\Services\Vacancies\Query\QueryBuilder;
use App\Services\Vacancies\Sort\SortBuilder;
use Elastic\Elasticsearch\Client;

class SearchService
{
    private Client $client;

    public function __construct(
        private AggregationBuilder $aggregationBuilder,
        private QueryBuilder $queryBuilder,
        private SearchResultMapper $searchResultMapper,
        private SortBuilder $sortBuilder,
    ) {
        $this->client = ElasticClient::make();
    }

    public function search(SearchDTO $dto): array
    {
        $params = [
            'index' => Index::VACANCIES,
            'body' => [
                'query' => $this->queryBuilder->build($dto->filters),
                'sort' => $this->sortBuilder->build($dto),
                'aggs' => $this->aggregationBuilder->build($dto->filters),
                'size' => $dto->limit,
            ],
        ];

        // Cursor pagination (search_after)
        if ($dto->cursor) {
            $params['body']['search_after'] = $dto->cursor;
        }

        // Запрос
        $response = $this->client
            ->search($params)
            ->asArray();

        return $this->searchResultMapper->map($response);
    }
}
