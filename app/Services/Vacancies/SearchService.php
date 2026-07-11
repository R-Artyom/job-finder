<?php

namespace App\Services\Vacancies;

use App\DTO\Vacancies\SearchDTO;
use App\Elastic\ElasticClient;
use App\Elastic\Index;
use App\Models\Vacancy;
use Carbon\Carbon;
use Elastic\Elasticsearch\Client;

class SearchService
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

    private Client $client;

    public function __construct()
    {
        $this->client = ElasticClient::make();
    }

    public function search(SearchDTO $dto): array
    {
        $query = $this->buildQuery($dto->filters);
        $sort = $this->buildSort($dto);

        $params = [
            'index' => Index::VACANCIES,
            'body' => [
                'query' => $query,
                'sort' => $sort,
                'aggs' => $this->buildAggregations($dto->filters),
                'size' => $dto->limit,
            ],
        ];

        // Cursor pagination (search_after)
        if ($dto->cursor) {
            $params['body']['search_after'] = $dto->cursor;
        }

        // Запрос
        $response = $this->client->search($params)->asArray();

        return $this->mapSearchResult($response);
    }

    private function buildQuery(array $filters): array
    {
        // Булевый запрос
        $query = [
            'bool' => [
                'filter' => [],
                'must' => [],
            ],
        ];

        // * "in" - фильтр по значению
        // Id работодателя
        if (!empty($filters['employerId'])) {
            $query['bool']['filter'][] = [
                'terms' => [
                    'employer_id' => $filters['employerId'],
                ],
            ];
        }
        // Id региона
        if (!empty($filters['areaId'])) {
            $query['bool']['filter'][] = [
                'terms' => [
                    'area_id' => $filters['areaId'],
                ],
            ];
        }
        // Валюта
        if (!empty($filters['salaryCurrency'])) {
            $query['bool']['filter'][] = [
                'terms' => [
                    'salary_currency' => $filters['salaryCurrency'],
                ],
            ];
        }
        // В архиве
        if (!empty($filters['archived'])) {
            $query['bool']['filter'][] = [
                'terms' => [
                    'archived' => array_map('boolval', $filters['archived']),
                ],
            ];
        }
        // Id страны
        if (!empty($filters['countryId'])) {
            $query['bool']['filter'][] = [
                'terms' => [
                    'country_id' => $filters['countryId'],
                ],
            ];
        }

        // * "like" - фильтр по шаблону
        // Название вакансии
        if (!empty($filters['name'])) {
            $query['bool']['must'][] = [
                'match' => [
                    'name' => $filters['name'],
                ],
            ];
        }
        // Описание вакансии
        if (!empty($filters['description'])) {
            $query['bool']['must'][] = [
                'match' => [
                    'description' => $filters['description'],
                ],
            ];
        }
        // Название работодателя
        if (!empty($filters['employerName'])) {
            $query['bool']['must'][] = [
                'match' => [
                    'employer_name' => $filters['employerName'],
                ],
            ];
        }

        // * "from" - фильтр "От"
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

        // * "to" - фильтр "До"
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

        // * "date" - фильтр по дате
        // Опубликовано
        if (!empty($filters['publishedAt'])) {
            if (!empty($filters['publishedAt'][0]) && !empty($filters['publishedAt'][1])) {
                $publishedAt = [
                    'gte' => Carbon::parse($filters['publishedAt'][0])->toISOString(),
                    'lte' => Carbon::parse($filters['publishedAt'][1])->toISOString(),
                ];
            } elseif (!empty($filters['publishedAt'][0])) {
                $publishedAt = [
                    'gte' => Carbon::parse($filters['publishedAt'][0])->toISOString(),
                ];
            } else {
                $publishedAt = [
                    'lte' => Carbon::parse($filters['publishedAt'][1])->toISOString(),
                ];
            }
            $query['bool']['filter'][] = [
                'range' => [
                    'published_at' => $publishedAt,
                ],
            ];
        }

        // Создано
        if (!empty($filters['createdAt'])) {
            if (!empty($filters['createdAt'][0]) && !empty($filters['createdAt'][1])) {
                $createdAt = [
                    'gte' => Carbon::parse($filters['createdAt'][0])->toISOString(),
                    'lte' => Carbon::parse($filters['createdAt'][1])->toISOString(),
                ];
            } elseif (!empty($filters['createdAt'][0])) {
                $createdAt = [
                    'gte' => Carbon::parse($filters['createdAt'][0])->toISOString(),
                ];
            } else {
                $createdAt = [
                    'lte' => Carbon::parse($filters['createdAt'][1])->toISOString(),
                ];
            }
            $query['bool']['filter'][] = [
                'range' => [
                    'created_at' => $createdAt,
                ],
            ];
        }

        return $query;
    }

    private function buildSort(SearchDTO $dto): array
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

    private function buildAggregations($filters): array
    {
        $aggregations = [];

        foreach (self::FACETS as $facetName => $config) {
            $facetFilters = $filters;
            unset($facetFilters[$facetName]);
            $aggregations[$facetName] = [
                'filter' => $this->buildQuery($facetFilters),
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

    public function mapSearchResult($response): array
    {
        // 1. Получить cursor следующей страницы
        $hits = $response['hits']['hits'];
        $nextCursor = null;
        if (!empty($hits)) {
            $lastHit = end($hits);
            $nextCursor = $lastHit['sort'] ?? null;
        }

//        dd(array_column($hits, '_source'));

        // 2. Получить модели вакансий по списку id
        $ids = collect($hits)
            ->pluck('_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $vacanciesModels = Vacancy::query()
            ->leftJoin('employers', 'employers.id', 'vacancies.employer_id')
            ->leftJoin('areas', 'areas.id', 'vacancies.area_id')
            ->select(
            // №
                'vacancies.id',
                // Название
                'vacancies.name',
                // Работодатель
                'vacancies.employer_id',
                // Регион
                'vacancies.area_id',
                // Описание
                'vacancies.description',
                // ЗП от
                'vacancies.salary_from',
                // ЗП до
                'vacancies.salary_to',
                // Валюта
                'vacancies.salary_currency',
                // В архиве
                'vacancies.archived',
                // Опубликовано
                'vacancies.published_at',
                // Название работодателя
                'employers.name as employerName',
                // Страна
                'areas.country_id as countryId',
                // Оригинальная дата создания
                'vacancies.created_at',
            )
            ->whereIn('vacancies.id', $ids)
            ->get();

        // 3. Отсортировать вакансии, как в ElasticSearch
        $order = array_flip($ids);
        $vacanciesModels = $vacanciesModels
            ->sortBy(fn ($item) => $order[$item->id])
            ->values();

        return [
            'vacanciesModels' => $vacanciesModels,
            'next' => $nextCursor,
            'totalCount' => $response['hits']['total']['value'],
            'facets' => $this->parseAggregations($response),
        ];
    }

    private function parseAggregations(array $response): array
    {
        $result = [];

        foreach ($response['aggregations'] as $name => $aggregation) {
            $result[$name] = collect($aggregation['values']['buckets'])
                ->pluck('key')
                ->all();
        }

        return $result;
    }
}
