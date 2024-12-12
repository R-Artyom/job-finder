<?php

namespace App\Console\Commands\Vacancies;

use App\Http\Controllers\Vacancies\RunParseController;
use Illuminate\Console\Command;

class RunParseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'parse-vacancy:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Запуск разбора вакансии';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Запуск разбора вакансии
        (new RunParseController)();
    }
}
