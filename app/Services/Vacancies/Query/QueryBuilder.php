<?php

namespace App\Services\Vacancies\Query;

use Carbon\Carbon;

class QueryBuilder
{
    public function build(array $filters): array
    {
        // Булевый запрос
        $query = [
            'bool' => [
                'filter' => [],
                'must' => [],
            ],
        ];

        // * "in" - фильтр по значению
        $this->addEmployerIdFilter($query, $filters);
        $this->addAreaIdFilter($query, $filters);
        $this->addSalaryCurrencyFilter($query, $filters);
        $this->addArchivedFilter($query, $filters);
        $this->addCountryIdFilters($query, $filters);
        // * "like" - фильтр по шаблону
        $this->addNameFilters($query, $filters);
        $this->addDescriptionFilters($query, $filters);
        $this->addEmployerNameFilters($query, $filters);
        // * "from" - фильтр "От"
        $this->addSalaryFromFilter($query, $filters);
        // * "to" - фильтр "До"
        $this->addSalaryToFilter($query, $filters);
        // * "date" - фильтр по дате
        $this->addPublishedAtFilter($query, $filters);
        $this->addCreatedAtFilter($query, $filters);

        return $query;
    }

    private function addEmployerIdFilter(array &$query, array $filters): void
    {
        // Id работодателя
        if (!empty($filters['employerId'])) {
            $query['bool']['filter'][] = [
                'terms' => [
                    'employer_id' => $filters['employerId'],
                ],
            ];
        }
    }

    private function addAreaIdFilter(array &$query, array $filters): void
    {
        // Id региона
        if (!empty($filters['areaId'])) {
            $query['bool']['filter'][] = [
                'terms' => [
                    'area_id' => $filters['areaId'],
                ],
            ];
        }
    }

    private function addSalaryCurrencyFilter(array &$query, array $filters): void
    {
        // Валюта
        if (!empty($filters['salaryCurrency'])) {
            $query['bool']['filter'][] = [
                'terms' => [
                    'salary_currency' => $filters['salaryCurrency'],
                ],
            ];
        }
    }

    private function addArchivedFilter(array &$query, array $filters): void
    {
        // В архиве
        if (!empty($filters['archived'])) {
            $query['bool']['filter'][] = [
                'terms' => [
                    'archived' => array_map('boolval', $filters['archived']),
                ],
            ];
        }
    }

    private function addCountryIdFilters(array &$query, array $filters): void
    {
        // Id страны
        if (!empty($filters['countryId'])) {
            $query['bool']['filter'][] = [
                'terms' => [
                    'country_id' => $filters['countryId'],
                ],
            ];
        }
    }

    private function addNameFilters(array &$query, array $filters): void
    {
        // Название вакансии
        if (!empty($filters['name'])) {
            $query['bool']['must'][] = [
                'match' => [
                    'name' => $filters['name'],
                ],
            ];
        }
    }

    private function addDescriptionFilters(array &$query, array $filters): void
    {
        // Описание вакансии
        if (!empty($filters['description'])) {
            $query['bool']['must'][] = [
                'match' => [
                    'description' => $filters['description'],
                ],
            ];
        }
    }

    private function addEmployerNameFilters(array &$query, array $filters): void
    {
        // Название работодателя
        if (!empty($filters['employerName'])) {
            $query['bool']['must'][] = [
                'match' => [
                    'employer_name' => $filters['employerName'],
                ],
            ];
        }
    }

    private function addSalaryFromFilter(array &$query, array $filters): void
    {
        // ЗП от
        if (isset($filters['salaryFrom'])) {
            $query['bool']['filter'][] = [
                'range' => [
                    'salary_from' => [
                        'gte' => $filters['salaryFrom'],
                    ],
                ],
            ];
        }
    }

    private function addSalaryToFilter(array &$query, array $filters): void
    {
        // ЗП до
        if (isset($filters['salaryTo'])) {
            $query['bool']['filter'][] = [
                'range' => [
                    'salary_to' => [
                        'lte' => $filters['salaryTo'],
                    ],
                ],
            ];
        }
    }

    private function addPublishedAtFilter(array &$query, array $filters): void
    {
        // Опубликовано
        $range = $this->buildDateRange($filters['publishedAt'] ?? []);

        if ($range === null) {
            return;
        }

        $query['bool']['filter'][] = [
            'range' => [
                'published_at' => $range,
            ],
        ];
    }

    private function addCreatedAtFilter(array &$query, array $filters): void
    {
        // Создано
        $range = $this->buildDateRange($filters['createdAt'] ?? []);

        if ($range === null) {
            return;
        }

        $query['bool']['filter'][] = [
            'range' => [
                'created_at' => $range,
            ],
        ];
    }

    // Построение диапазона дат
    private function buildDateRange(array $range): ?array
    {
        if (empty($range)) {
            return null;
        }

        $result = [];
        if (!empty($range[0])) {
            $result['gte'] = Carbon::parse($range[0])->toISOString();
        }
        if (!empty($range[1])) {
            $result['lte'] = Carbon::parse($range[1])->toISOString();
        }

        return $result ?: null;
    }
}
