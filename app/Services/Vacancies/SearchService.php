<?php

namespace App\Services\Vacancies;

use App\DTO\Vacancies\SearchDTO;
use App\Elastic\ElasticClient;
use App\Services\Vacancies\DTO\SearchResponse;
use App\Services\Vacancies\Mapper\SearchResponseParser;
use App\Services\Vacancies\Request\SearchRequestBuilder;
use Elastic\Elasticsearch\Client;

class SearchService
{
    private Client $client;

    public function __construct(
        private SearchRequestBuilder $requestBuilder,
        private SearchResponseParser $searchResponseParser,
    ) {
        $this->client = ElasticClient::make();
    }

    public function search(SearchDTO $dto): SearchResponse
    {
        $response = $this->client
            ->search($this->requestBuilder->build($dto))
            ->asArray();

        return $this->searchResponseParser->map($response);
    }

}
