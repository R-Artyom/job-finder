<?php

namespace App\Console\Commands\Elastic;

use App\Models\Vacancy;
use App\Elastic\Services\VacancyIndexer;
use Illuminate\Console\Command;

class ReindexVacancies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:reindex-vacancies';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Индексация всех вакансий ElasticSearch';

    /**
     * Execute the console command.
     */
    public function handle(VacancyIndexer $indexer)
    {
        Vacancy::query()
            ->orderBy('id')
            ->chunkById(1000, function ($vacancies) use ($indexer) {
                foreach ($vacancies as $vacancy) {
                    $indexer->index($vacancy);
                }
                $this->info('Chunk indexed');
            });

        $this->info('Индексация всех вакансий завершена');
    }
}
