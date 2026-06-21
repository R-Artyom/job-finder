<?php

namespace App\Console\Commands\Elastic;

use App\Elastic\ElasticClient;
use App\Elastic\Index;
use Illuminate\Console\Command;

class SwitchVacanciesAlias extends Command
{
    protected $signature = 'elastic:switch-vacancies-alias {index?}';
    protected $description = 'Переключение псевдонима индекса вакансий на новый индекс';

    public function handle()
    {
        $client = ElasticClient::make();

        // Псевдоним индекса вакансий
        $alias = Index::VACANCIES;

        // Индекс вакансий, с которым будет ассоциироваться псевдоним (напр. vacancies_v2)
        $newIndex = $this->argument('index');
        // Если название индекса в команде не указано
        if (!$newIndex) {
            // То взять текущий индекс
            $newIndex = Index::VACANCIES_CURRENT;
        }

        // Действия
        $actions = [];

        // Удалить alias со старых индексов
        try {
            $current = $client->indices()->getAlias([
                'name' => $alias,
                'ignore_unavailable' => true,
            ])->asArray();
            $oldIndexes = array_keys($current);
            foreach ($oldIndexes as $oldIndex) {
                $actions[] = [
                    'remove' => [
                        'index' => $oldIndex,
                        'alias' => $alias,
                    ],
                ];
            }
        } catch (\Exception $e) {
            // alias ещё не существует
        }

        // Ассоциировать псевдоним с новым индексом
        $actions[] = [
            'add' => [
                'index' => $newIndex,
                'alias' => $alias,
            ],
        ];
        $client->indices()->updateAliases([
            'body' => [
                'actions' => $actions,
            ],
        ]);

        $this->info("Псевдоним \"{$alias}\" переключен на \"{$newIndex}\"");
    }
}
