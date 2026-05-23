<?php

namespace App\Console\Commands\Elastic;

use App\Elastic\ElasticClient;
use App\Elastic\Index;
use Illuminate\Console\Command;

class CreateVacanciesIndex extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:create-vacancies-index';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Создание индекса вакансий ElasticSearch';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $indexName = Index::VACANCIES_CURRENT;

        $client = ElasticClient::make();

        $config = require app_path('Elastic/Indexes/vacancies.php');

        $client->indices()->create([
            'index' => $indexName,
            'body' => $config,
        ]);

        $this->info("Индекс вакансий {$indexName} создан");
    }
}
