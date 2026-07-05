<?php

// Конфигурация индекса "Вакансии"
return [
    'settings' => [
        'number_of_shards' => 1,
        'number_of_replicas' => 0,
        'analysis' => [
            'analyzer' => [
                // Анализатор для индексации
                'autocomplete_analyzer' => [
                    'tokenizer' => 'standard',
                    'filter' => [
                        'lowercase',
                        'russian_stop_filter', // Удаление стоп-слова
                        'russian_stemmer_filter', // Стемминг
                        'autocomplete_filter' // N-граммы
                    ]
                ],
                // Анализатор для поиска (без n-грамм!)
                'autocomplete_search_analyzer' => [
                    'tokenizer' => 'standard',
                    'filter' => [
                        'lowercase',
                        'russian_stop_filter',
                        'russian_stemmer_filter'
                        // Нет autocomplete_filter, чтобы поиск был быстрее
                    ]
                ],
                // Стандартный анализатор
                'russian_analyzer' => [
                    'tokenizer' => 'standard',
                    'filter' => [
                        'lowercase',
                        'russian_stop_filter',
                        'russian_stemmer_filter'
                    ]
                ],
            ],
            'filter' => [
                'autocomplete_filter' => [
                    'type' => 'edge_ngram',
                    'min_gram' => 2,
                    'max_gram' => 20
                ],
                'russian_stop_filter' => [
                    'type' => 'stop',
                    'stopwords' => '_russian_'
                ],
                'russian_stemmer_filter' => [
                    'type' => 'stemmer',
                    'language' => 'russian'
                ]
            ]
        ]
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
                'analyzer' => 'autocomplete_analyzer', // Для индексации
                'search_analyzer' => 'autocomplete_search_analyzer', // Для поиска
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
                'analyzer' => 'russian_analyzer',
            ],
            // Название работодателя
            'employer_name' => [
                'type' => 'text',
                'analyzer' => 'russian_analyzer',
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
