<?php

namespace App\Services\Vacancies\Sort;

use App\DTO\Vacancies\SearchDTO;

class SortBuilder
{
    public function build(SearchDTO $dto): array
    {
        // Формирование 'sort' для Elasticsearch
        $sort = [];
        foreach ($dto->sort as $item) {
            $field = array_key_first($item);
            $direction = $item[$field];
            $sort[] = [
                $field => [
                    'order' => $direction,
                ],
            ];
        }

        return $sort;
    }
}
