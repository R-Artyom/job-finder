<?php

namespace App\Elastic\Services;

use App\Elastic\ElasticClient;
use App\Models\Vacancy;
use Carbon\Carbon;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Response\Elasticsearch;
use RuntimeException;

class VacancyIndexer
{
    // Клиент Elasticsearch
    private Client $client;

    public function __construct()
    {
        $this->client = ElasticClient::make();
    }

    /**
     * Индексация одной вакансии
     *
     * Используется при:
     * - создании вакансии;
     * - обновлении вакансии;
     * - синхронизации одной записи.
     */
    public function index(Vacancy $vacancy, string $index): void
    {
        $this->client->index([
            'index' => $index,
            'id'    => $vacancy->id,
            'body'  => $this->prepareDocument($vacancy),
        ]);
    }

    /**
     * Массовая индексация вакансий через Bulk API
     *
     * Один HTTP-запрос вместо тысяч отдельных, используется при полной переиндексации
     *
     * @param iterable $vacancies
     * @param string $index
     * @return void
     * @throws \Elastic\Elasticsearch\Exception\ClientResponseException
     * @throws \Elastic\Elasticsearch\Exception\ServerResponseException
     */
    public function bulkIndex(iterable $vacancies, string $index): void
    {
        $body = [];

        foreach ($vacancies as $vacancy) {
            // Описание операции
            $body[] = [
                'index' => [
                    '_index' => $index,
                    '_id'    => $vacancy->id,
                ],
            ];
            // Документ
            $body[] = $this->prepareDocument($vacancy);
        }

        // Нет документов
        if (empty($body)) {
            return;
        }

        // Выполнить массовую индексацию вакансий
        $response = $this->client->bulk([
            'body' => $body,
        ]);

        // Обработка ошибок, если есть
        if ($response['errors'] === true) {
            $this->handleBulkErrors($response);
        }
    }

    /**
     * Подготовка документа Elasticsearch
     *
     * Здесь находится вся логика преобразования модели Vacancy в документ Elasticsearch
     */
    private function prepareDocument(Vacancy $vacancy): array
    {
        return [
            // № вакансии
            'id' => $vacancy->id,

            // * "in" - фильтр по значению
            // Id работодателя
            'employer_id' => $vacancy->employer_id,
            // Id региона
            'area_id' => $vacancy->area_id,
            // Валюта
            'salary_currency' => $vacancy->salary_currency,
            // В архиве
            'archived' => (bool) $vacancy->archived,
            // Id страны
            'country_id' => $vacancy->countryId,

            // * "like" - фильтр по шаблону
            // Название вакансии
            'name' => $vacancy->name,
            // Описание вакансии
            'description' => $vacancy->description,
            // Название работодателя
            'employer_name' => $vacancy->employerName,

            // * "from" - фильтр "От"
            // ЗП от
            'salary_from' => $vacancy->salary_from,

            // * "to" - фильтр "До"
            // ЗП до
            'salary_to' => $vacancy->salary_to,

            // * "date" - фильтр по дате
            // Вызывать метод преобразования даты в формат ISO 8601 (напр. "2024-01-15T14:30:00+00:00")
            // Опубликовано
            'published_at' => $vacancy->published_at
                ? Carbon::parse($vacancy->published_at)->toISOString()
                : null,
            // Создано
            'created_at' => $vacancy->created_at
                ? Carbon::parse($vacancy->created_at)->toISOString()
                : null,
            // Обновлено
            'updated_at' => $vacancy->updated_at
                ? Carbon::parse($vacancy->updated_at)->toISOString()
                : null,
        ];
    }

    /**
     * Обработка ошибок Bulk API
     *
     * Elasticsearch может вернуть HTTP 200, но часть документов окажется не проиндексирована,
     * поэтому обязательна проверка поля "error"
     */
    private function handleBulkErrors(Elasticsearch $response): void
    {
        $messages = [];

        foreach ($response['items'] as $item) {
            if (!isset($item['index']['error'])) {
                continue;
            }
            $messages[] = 'Id ' . $item['index']['_id'] . ': ' . $item['index']['error']['reason'];
        }

        if (!empty($messages)) {
            throw new RuntimeException(
                "Ошибка Bulk API:\n" . implode("\n", $messages)
            );
        }
    }
}