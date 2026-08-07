<?php

namespace App\Services\Vacancies\Facet;

use App\Services\Vacancies\Query\QueryBuilder;

class FacetService
{
    private const FACETS = [
        // TODO фасеты работодателя временно отключены, чтобы не грузить страницу миллионами значений
        //'employerId' => 'employer_id',
        'areaId' => 'area_id',
        'countryId' => 'country_id',
        'salaryCurrency' => 'salary_currency',
        'archived' => 'archived',
    ];

    public function __construct(
        private CompositeAggregationLoader $loader,
        private QueryBuilder $queryBuilder,
    ) {}

    public function load(array $filters): array
    {
        $result = [];

        foreach (self::FACETS as $facet => $field) {

            $facetFilters = $filters;
            unset($facetFilters[$facet]);

            $query = $this->queryBuilder->build($facetFilters);

            $result[$facet] = $this->loader->load(
                field: $field,
                query: $query,
            );
        }

        return $result;
    }
}