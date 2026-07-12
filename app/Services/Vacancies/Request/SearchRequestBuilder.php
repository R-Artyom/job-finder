<?php

namespace App\Services\Vacancies\Request;

use App\DTO\Vacancies\SearchDTO;
use App\Elastic\Index;
use App\Services\Vacancies\Aggregation\AggregationBuilder;
use App\Services\Vacancies\Query\QueryBuilder;
use App\Services\Vacancies\Sort\SortBuilder;

class SearchRequestBuilder
{
    public function __construct(
        private QueryBuilder $queryBuilder,
        private SortBuilder $sortBuilder,
        private AggregationBuilder $aggregationBuilder,
    ) {}

    public function build(SearchDTO $dto): array
    {
        $request = [
            'index' => Index::VACANCIES,
            'body' => [
                'query' => $this->queryBuilder->build($dto->filters),
                'sort' => $this->sortBuilder->build($dto->sort),
                'aggs' => $this->aggregationBuilder->build($dto->filters),
                'size' => $dto->limit,
            ],
        ];

        if ($dto->cursor) {
            $request['body']['search_after'] = $dto->cursor;
        }

        return $request;
    }
}