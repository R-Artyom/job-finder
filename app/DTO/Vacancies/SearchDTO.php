<?php

namespace App\DTO\Vacancies;

final readonly class SearchDTO
{
    public function __construct(
        public int $limit = 100,
        public ?array $cursor = null,
        public array $sort = [
            ['published_at' => 'desc'],
            ['id' => 'desc'],
        ],
        public array $filters = [],
    ) {}
}