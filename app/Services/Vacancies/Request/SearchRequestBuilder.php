<?php

namespace App\Services\Vacancies\Request;

use App\DTO\Vacancies\SearchDTO;
use App\Elastic\Index;
use App\Services\Vacancies\Query\QueryBuilder;
use App\Services\Vacancies\Sort\SortBuilder;

class SearchRequestBuilder
{
    public function __construct(
        private QueryBuilder $queryBuilder,
        private SortBuilder $sortBuilder,
    ) {}

    public function build(SearchDTO $dto): array
    {
        $request = [
            'index' => Index::VACANCIES,
            'body' => [
                'query' => $this->queryBuilder->build($dto->filters),
                'sort' => $this->sortBuilder->build($dto->sort),
                'size' => $dto->limit,
            ],
        ];

        if ($dto->cursor) {
            $request['body']['search_after'] = $dto->cursor;
        }

        return $request;
    }
}