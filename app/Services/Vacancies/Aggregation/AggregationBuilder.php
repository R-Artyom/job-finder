<?php

namespace App\Services\Vacancies\Aggregation;

use App\Services\Vacancies\Query\QueryBuilder;

class AggregationBuilder
{
    private const FACETS = [
        'employerId' => [
            'field' => 'employer_id',
        ],
        'areaId' => [
            'field' => 'area_id',
        ],
        'countryId' => [
            'field' => 'country_id',
        ],
        'salaryCurrency' => [
            'field' => 'salary_currency',
        ],
        'archived' => [
            'field' => 'archived',
        ],
    ];

    public function __construct(
        private QueryBuilder $queryBuilder,
    ) {}

    public function build(array $filters): array
    {
        $aggregations = [];

        foreach (self::FACETS as $facetName => $config) {
            $facetFilters = $filters;
            unset($facetFilters[$facetName]);
            $aggregations[$facetName] = [
                'filter' => $this->queryBuilder->build($facetFilters),
                'aggs' => [
                    'values' => [
                        'terms' => [
                            'field' => $config['field'],
                            'size' => 10000,
                        ],
                    ],
                ],
            ];
        }

        return $aggregations;
    }
}
