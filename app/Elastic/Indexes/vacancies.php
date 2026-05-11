<?php

return [
    'settings' => [
        'number_of_shards' => 1,
        'number_of_replicas' => 0,
    ],

    'mappings' => [
        'properties' => [

            'id' => [
                'type' => 'long',
            ],

            'name' => [
                'type' => 'text',
            ],

            'description' => [
                'type' => 'text',
            ],

            'area_id' => [
                'type' => 'integer',
            ],

            'employer_id' => [
                'type' => 'integer',
            ],

            'salary_from' => [
                'type' => 'integer',
            ],

            'salary_to' => [
                'type' => 'integer',
            ],

            'archived' => [
                'type' => 'boolean',
            ],

            'published_at' => [
                'type' => 'date',
            ],
        ],
    ],
];
