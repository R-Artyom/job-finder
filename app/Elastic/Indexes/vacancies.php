<?php

// Конфигурация индекса "Вакансии"
return [
    'settings' => [
        'number_of_shards' => 1,
        'number_of_replicas' => 0,
    ],

    'mappings' => [
        'properties' => [
            // № вакансии
            'id' => [
                'type' => 'long',
            ],

            // * "in" - фильтр по значению
            // Id работодателя
            'employer_id' => [
                'type' => 'long',
            ],
            // Id региона
            'area_id' => [
                'type' => 'integer',
            ],
            // Валюта
            'salary_currency' => [
                'type' => 'keyword',
            ],
            // В архиве
            'archived' => [
                'type' => 'boolean',
            ],
            // Id страны
            'country_id' => [
                'type' => 'integer',
            ],

            // * "like" - фильтр по шаблону
            // Название вакансии
            'name' => [
                'type' => 'text',
                'fields' => [
                    'keyword' => [
                        'type' => 'keyword',
                        'ignore_above' => 256,
                    ],
                ],
            ],
            // Описание вакансии
            'description' => [
                'type' => 'text',
            ],
            // Название работодателя
            'employer_name' => [
                'type' => 'text',
                'fields' => [
                    'keyword' => [
                        'type' => 'keyword',
                        'ignore_above' => 256,
                    ],
                ],
            ],

            // * "from" - фильтр "От"
            // ЗП от
            'salary_from' => [
                'type' => 'integer',
            ],
            // * "to" - фильтр "До"
            // ЗП до
            'salary_to' => [
                'type' => 'integer',
            ],

            // * "date" - фильтр по дате
            // Опубликовано
            'published_at' => [
                'type' => 'date',
            ],
            // Создано
            'created_at' => [
                'type' => 'date',
            ],
            // Обновлено
            'updated_at' => [
                'type' => 'date',
            ],
        ],
    ],
];
