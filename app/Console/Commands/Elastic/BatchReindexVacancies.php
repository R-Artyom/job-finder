<?php

namespace App\Console\Commands\Elastic;

use App\Elastic\ElasticClient;
use App\Elastic\Index;
use App\Elastic\Services\VacancyIndexer;
use App\Models\Vacancy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class BatchReindexVacancies extends Command
{
    // Команда
    protected $signature = 'elastic:batch-reindex-vacancies {--resume : Продолжить с последнего сохранённого id}';

    // Описание команды
    protected $description = 'Массовая индексация вакансий в Elasticsearch через Bulk API';

    // Размер одного пакета
    private int $bulkSize = 1000;

    // Последний успешно обработанный id
    private int $lastId = 0;

    // Количество проиндексированных вакансий
    private int $count = 0;

    // Флаг остановки по "Ctrl+C"
    private bool $shouldStop = false;

    // Выполнение команды
    public function handle(VacancyIndexer $indexer): int
    {
        // Продолжение индексации
        if ($this->option('resume')) {
            $this->lastId = Cache::get('elastic_last_id', 0);
            $this->count = Cache::get('elastic_count', 0);
            $this->info("Продолжаем с id {$this->lastId}. " . "Уже обработано {$this->count} вакансий.");
        }

        // Обработка "Ctrl+C"
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, function () {
                $this->shouldStop = true;
                $this->warn("\nПолучен Ctrl+C. " . "Ожидаем завершения текущего Bulk...");
            });

            pcntl_signal(SIGTERM, function () {
                $this->shouldStop = true;
                $this->warn("\nПолучен Ctrl+C. " . "Ожидаем завершения текущего Bulk...");
            });
        }

        $start = microtime(true);
        $this->info('Старт индексации...');

        try {
            while (!$this->shouldStop) {
                // Получение следующего пакета вакансий
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
                    ->where('vacancies.id', '>', $this->lastId)
                    ->orderBy('vacancies.id')
                    ->limit($this->bulkSize)
                    ->get();

                // Всё проиндексировано
                if ($vacancies->isEmpty()) {
                    break;
                }

                // Индексация пакета
                $indexer->bulkIndex($vacancies,Index::VACANCIES_CURRENT);

                // Обновление прогресса
                $this->lastId = $vacancies->last()->id;
                $this->count += $vacancies->count();

                // Сохранение прогресса после каждого успешно завершённого bulk
                Cache::forever('elastic_last_id', $this->lastId);
                Cache::forever('elastic_count', $this->count);
                $this->info("Проиндексировано {$this->count}, " . "последний id {$this->lastId}");

                // Проверка "Ctrl+C"
                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }
            }

            // Остановка пользователем
            if ($this->shouldStop) {
                $this->warn("Индексация остановлена пользователем.");
                $this->warn("Продолжить можно командой:");
                $this->line("php artisan elastic:batch-reindex-vacancies --resume");
                return self::SUCCESS;
            }

            // Полностью завершено, удалить из кэша
            Cache::forget('elastic_last_id');
            Cache::forget('elastic_count');

            $minutes = round((microtime(true) - $start) / 60, 2);
            $this->info("Готово. Проиндексировано {$this->count} вакансий " . "за {$minutes} минут.");

            return self::SUCCESS;
        }

        catch (\Throwable $e) {
            $this->error($e->getMessage());
            // Удаление повреждённого индекса
            $client = ElasticClient::make();
            if ($client->indices()->exists(['index' => Index::VACANCIES_CURRENT])->asBool()) {
                $client->indices()->delete(['index' => Index::VACANCIES_CURRENT]);
            }

            $this->warn('Последний успешно обработанный id: ' . $this->lastId);
            $this->warn('Для продолжения выполните:');
            $this->line('php artisan elastic:batch-reindex-vacancies --resume');

            return self::FAILURE;
        }
    }
}