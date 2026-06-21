<?php

namespace App\Console\Commands\Elastic;

use App\Elastic\ElasticClient;
use Illuminate\Console\Command;

class DeleteVacanciesIndex extends Command
{
    protected $signature = 'elastic:delete-vacancies-index {index : Название индекса для удаления}';
    protected $description = 'Удаление индекса вакансий';

    public function handle()
    {
        $client = ElasticClient::make();

        // Индекс вакансий, который нужно удалить
        $deleteIndex = $this->argument('index');

        // Проверка существования индекса
        if (!$client->indices()->exists(['index' => $deleteIndex])->asBool()) {
            $this->info("Индекс \"{$deleteIndex}\" не существует");
            return;
        }

        // Проверка на наличие псевдонимов
        try {
            $aliases = $client->indices()->getAlias(['index' => $deleteIndex])->asArray();
            if (!empty($aliases[$deleteIndex]['aliases'])) {
                $aliasNames = array_keys($aliases[$deleteIndex]['aliases']);
                $this->warn("Индекс \"{$deleteIndex}\" имеет следующие алиасы: " . implode(', ', $aliasNames));

                if (!$this->confirm('Всё равно удалить индекс вместе с алиасами?')) {
                    $this->info('Удаление отменено');
                    return;
                }
            }
        } catch (\Exception $e) {
            // Псевдонимов нет, продолжаем
        }

        // Подтверждение удаления
        if (!$this->confirm("Вы уверены, что хотите удалить индекс \"{$deleteIndex}\"?")) {
            $this->info('Удаление отменено');
            return;
        }

        // Удаление индекса
        $client->indices()->delete(['index' => $deleteIndex]);

        $this->info("Индекс \"{$deleteIndex}\" успешно удалён");
    }
}
