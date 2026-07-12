<?php

namespace App\Services\Vacancies\Sort;

class SortBuilder
{
    public function build(array $sorts): array
    {
        // Формирование 'sort' для Elasticsearch
        $sort = [];
        foreach ($sorts as $sortItem) {
            foreach ($sortItem as $field => $direction) {
                $sort[] = [
                    $field => [
                        'order' => $direction,
                    ],
                ];
            }
        }

        return $sort;
    }
}
