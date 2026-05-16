<?php

namespace App\Services\Vacancies;

use App\Elastic\ElasticClient;
use Elastic\Elasticsearch\Client;

class SearchService
{
    private Client $client;

    public function __construct()
    {
        $this->client = ElasticClient::make();
    }

    public function search(array $filters = []): array
    {
        $params = [
            'index' => 'vacancies_v1',
            'body' => [
                'size' => 20,
                'query' => [
                    'match_all' => (object)[]
                ]
            ]
        ];

        return $this->client
            ->search($params)
            ->asArray();
    }
}
