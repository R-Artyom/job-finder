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
    private Client $client;

    public function __construct()
    {
        $this->client = ElasticClient::make();
    }

    public function search(SearchDTO $dto)
    {
        // Булевый запрос
        $query['bool']['filter'] = [];

        // * "in" - фильтр по значению
        // Id работодателя
        if (!empty($dto->filters['employerId'])) {
            $query['bool']['filter'][] = [
                'terms' => [
                    'employer_id' => $dto->filters['employerId'],
                ],
            ];
        }
        // Id региона
        if (!empty($dto->filters['areaId'])) {
            $query['bool']['filter'][] = [
                'terms' => [
                    'area_id' => $dto->filters['areaId'],
                ],
            ];
        }
        // Валюта
        if (!empty($dto->filters['salaryCurrency'])) {
            $query['bool']['filter'][] = [
                'terms' => [
                    'salary_currency' => $dto->filters['salaryCurrency'],
                ],
            ];
        }
        // В архиве
        if (!empty($dto->filters['archived'])) {
            $query['bool']['filter'][] = [
                'terms' => [
                    'archived' => array_map('boolval', $dto->filters['archived']),
                ],
            ];
        }
        // Id страны
        if (!empty($dto->filters['countryId'])) {
            $query['bool']['filter'][] = [
                'terms' => [
                    'country_id' => $dto->filters['countryId'],
                ],
            ];
        }

        // * "like" - фильтр по шаблону
        // Название вакансии
        $query['bool']['must'] = [];
        if (!empty($dto->filters['name'])) {
            $query['bool']['must'][] = [
                'match' => [
                    'name' => $dto->filters['name'],
                ],
            ];
        }
        // Описание вакансии
        if (!empty($dto->filters['description'])) {
            $query['bool']['must'][] = [
                'match' => [
                    'description' => $dto->filters['description'],
                ],
            ];
        }
        // Название работодателя
        if (!empty($dto->filters['employerName'])) {
            $query['bool']['must'][] = [
                'match' => [
                    'employer_name' => $dto->filters['employerName'],
                ],
            ];
        }

        // * "from" - фильтр "От"
        // ЗП от
        if (isset($dto->filters['salaryFrom'])) {
            $query['bool']['filter'][] = [
                'range' => [
                    'salary_from' => [
                        'gte' => $dto->filters['salaryFrom'],
                    ],
                ],
            ];
        }

        // * "to" - фильтр "До"
        // ЗП до
        if (isset($dto->filters['salaryTo'])) {
            $query['bool']['filter'][] = [
                'range' => [
                    'salary_to' => [
                        'lte' => $dto->filters['salaryTo'],
                    ],
                ],
            ];
        }

        // * "date" - фильтр по дате
        // Опубликовано
        if (!empty($dto->filters['publishedAt'])) {
            if (!empty($dto->filters['publishedAt'][0]) && !empty($dto->filters['publishedAt'][1])) {
                $publishedAt = [
                    'gte' => Carbon::parse($dto->filters['publishedAt'][0])->toISOString(),
                    'lte' => Carbon::parse($dto->filters['publishedAt'][1])->toISOString(),
                ];
            } elseif (!empty($dto->filters['publishedAt'][0])) {
                $publishedAt = [
                    'gte' => Carbon::parse($dto->filters['publishedAt'][0])->toISOString(),
                ];
            } else {
                $publishedAt = [
                    'lte' => Carbon::parse($dto->filters['publishedAt'][1])->toISOString(),
                ];
            }
            $query['bool']['filter'][] = [
                'range' => [
                    'published_at' => $publishedAt,
                ],
            ];
        }

        // Создано
        if (!empty($dto->filters['createdAt'])) {
            if (!empty($dto->filters['createdAt'][0]) && !empty($dto->filters['createdAt'][1])) {
                $createdAt = [
                    'gte' => Carbon::parse($dto->filters['createdAt'][0])->toISOString(),
                    'lte' => Carbon::parse($dto->filters['createdAt'][1])->toISOString(),
                ];
            } elseif (!empty($dto->filters['createdAt'][0])) {
                $createdAt = [
                    'gte' => Carbon::parse($dto->filters['createdAt'][0])->toISOString(),
                ];
            } else {
                $createdAt = [
                    'lte' => Carbon::parse($dto->filters['createdAt'][1])->toISOString(),
                ];
            }
            $query['bool']['filter'][] = [
                'range' => [
                    'created_at' => $createdAt,
                ],
            ];
        }

        // 1. Формирование 'sort' для Elasticsearch
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

        // 2. Базовые параметры
        $params = [
            'index' => Index::VACANCIES,
            'body' => [
                'size' => $dto->limit,
                'query' => $query,
                'sort' => $sort,
            ]
        ];

        // 3. Cursor pagination (search_after)
        if ($dto->cursor) {
            $params['body']['search_after'] = $dto->cursor;
        }

        // 4. Запрос
        $response = $this->client->search($params)->asArray();

        // 5. Получить cursor следующей страницы
        $hits = $response['hits']['hits'];
        $nextCursor = null;
        if (!empty($hits)) {
            $lastHit = end($hits);
            $nextCursor = $lastHit['sort'] ?? null;
        }

//        dd(array_column($hits, '_source'));

        // 6. Получить модели вакансий по списку id
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

        // 7. Отсортировать вакансии, как в ElasticSearch
        $order = array_flip($ids);
        $vacanciesModels = $vacanciesModels
            ->sortBy(fn ($item) => $order[$item->id])
            ->values();

        return [
            'vacanciesModels' => $vacanciesModels,
            'next' => $nextCursor,
            'totalCount' => $response['hits']['total']['value'],
        ];
    }
}
