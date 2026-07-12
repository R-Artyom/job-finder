<?php

namespace App\Services\Vacancies\DTO;

use Illuminate\Support\Collection;

class SearchResponse
{
    public function __construct(
        public Collection $vacancies,
        public ?array $nextCursor,
        public int $totalCount,
        public array $facets = [],
    ) {}

    public function withFacets(array $facets): self
    {
        return new self(
            vacancies: $this->vacancies,
            nextCursor: $this->nextCursor,
            totalCount: $this->totalCount,
            facets: $facets,
        );
    }
}