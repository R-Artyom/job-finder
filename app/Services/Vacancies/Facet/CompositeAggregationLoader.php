<?php

namespace App\Services\Vacancies\Facet;

use App\Elastic\ElasticClient;
use App\Elastic\Index;
use Elastic\Elasticsearch\Client;

class CompositeAggregationLoader
{
    private Client $client;

    public function __construct()
    {
        $this->client = ElasticClient::make();
    }

    public function load(string $field, array $query): array
    {
        $values = [];
        $after = null;

        do {
            $body = [
                'size' => 0,
                'query' => $query,
                'aggs' => [
                    'values' => [
                        'composite' => [
                            'size' => 1000,
                            'sources' => [
                                [
                                    'value' => [
                                        'terms' => [
                                            'field' => $field,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            if ($after) {
                $body['aggs']['values']['composite']['after'] = $after;
            }

            $response = $this->client
                ->search([
                    'index' => Index::VACANCIES,
                    'body' => $body,
                ])
                ->asArray();

            foreach ($response['aggregations']['values']['buckets'] as $bucket) {
                $value = $bucket['key']['value'];

                // Для булевых делаем 1 или 0
                if (is_bool($value)) {
                    $value = (int) $value;
                }

                $values[] = $value;
            }

            $after = $response['aggregations']['values']['after_key'] ?? null;

        } while ($after);

        return $values;
    }
}