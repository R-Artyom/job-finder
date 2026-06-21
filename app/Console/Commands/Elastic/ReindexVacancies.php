<?php

namespace App\Console\Commands\Elastic;

use App\Elastic\ElasticClient;
use App\Elastic\Index;
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
        $this->info('Старт индексации...');

        // Id последней вакансии из блока "1000"
        $lastId = 0;
        // Всего вакансий
        $count = 0;

        try {
            while (true) {
                $vacancies = Vacancy::query()
                    ->leftJoin('employers', 'employers.id', 'vacancies.employer_id')
                    ->leftJoin('areas', 'areas.id', 'vacancies.area_id')
                    ->select(
                        // № вакансии
                        'vacancies.id',

                        // * "in" - фильтр по значению
                        // Id работодателя
                        'vacancies.employer_id',
                        // Id региона
                        'vacancies.area_id',
                        // Валюта
                        'vacancies.salary_currency',
                        // В архиве
                        'vacancies.archived',
                        // Id страны
                        'areas.country_id as countryId',

                        // * "like" - фильтр по шаблону
                        // Название вакансии
                        'vacancies.name',
                        // Описание вакансии
                        'vacancies.description',
                        // Название работодателя
                        'employers.name as employerName',

                        // * "from" - фильтр "От"
                        // ЗП от
                        'vacancies.salary_from',

                        // * "to" - фильтр "До"
                        // ЗП до
                        'vacancies.salary_to',

                        // * "date" - фильтр по дате
                        // Опубликовано
                        'vacancies.published_at',
                        // Создано
                        'vacancies.created_at',
                        // Обновлено
                        'vacancies.updated_at',
                    )
                    ->where('vacancies.id', '>', $lastId)
                    ->orderBy('vacancies.id')
                    ->limit(1000)
                    ->get();

                // Вакансии закончились
                if ($vacancies->isEmpty()) {
                    break;
                }

                // Добавление в индекс Elasticsearch каждой вакансии из блока "1000"
                foreach ($vacancies as $vacancy) {
                    $indexer->index($vacancy, Index::VACANCIES_CURRENT);
                    $count++;
                }

                $lastId = $vacancies->last()->id;
                $this->info("Проиндексировано {$count} вакансий, последний id: $lastId");
            }

            $this->info("Индексация завершена! Всего: {$count} вакансий");

        } catch (\Exception $e) {
            $this->error('Ошибка: ' . $e->getMessage());
            $this->warn('Индекс' . Index::VACANCIES_CURRENT . 'удалён, создайте его заново командой php artisan elastic:switch-vacancies-alias ' . Index::VACANCIES_CURRENT . ' и запустите переиндексацию php artisan elastic:reindex-vacancies');

            $client = ElasticClient::make();

            // Если индекса нет, то ничего удалять не нужно
            if (!$client->indices()->exists(['index' => Index::VACANCIES_CURRENT])->asBool()) {
                return;
            }

            // Удаление индекса
            $client->indices()->delete(['index' => Index::VACANCIES_CURRENT]);

            return;
        }
    }
}
