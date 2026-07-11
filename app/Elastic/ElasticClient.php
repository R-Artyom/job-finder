<?php

namespace App\Elastic;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;

class ElasticClient
{
    public static function make(): Client
    {
        $builder = ClientBuilder::create()
            ->setHosts([
                config('services.elasticsearch.host'),
            ])
            ->setCABundle(
                config('services.elasticsearch.ca_bundle')
            );

        if (
            config('services.elasticsearch.username') &&
            config('services.elasticsearch.password')
        ) {
            $builder->setBasicAuthentication(
                config('services.elasticsearch.username'),
                config('services.elasticsearch.password')
            );
        }

        return $builder->build();
    }
}