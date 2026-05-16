<?php

namespace App\Services\Vacancies;

use App\Elastic\ElasticClient;
use App\Models\Vacancy;
use Elastic\Elasticsearch\Client;

class SearchService
{
    private Client $client;

    public function __construct()
    {
        $this->client = ElasticClient::make();
    }

    public function search(array $filters = [])
    {
        $response = $this->client->search([
            'index' => 'vacancies_v1',
            'body' => [
                'size' => 20,
                'query' => [
                    'match_all' => (object)[]
                ]
            ]
        ]);

        // Результаты поиска
        $hits = $response->asArray()['hits']['hits'];

        // Только список id вакансий
        $ids = collect($hits)
            ->pluck('_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return Vacancy::whereIn('id', $ids)->get();
    }
}