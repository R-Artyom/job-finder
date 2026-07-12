<?php

namespace App\Services\Vacancies\DTO;

use Illuminate\Support\Collection;

class SearchResponse
{
    public function __construct(
        public readonly Collection $vacancies,
        public readonly ?array $nextCursor,
        public readonly int $totalCount,
        public readonly array $facets,
    ) {}
}