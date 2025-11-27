<?php

namespace App\Console\Commands\Areas;

use App\Http\Controllers\Areas\RunParseController;
use Illuminate\Console\Command;

class RunParseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'parse-areas:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Запуск парсера дерева регионов';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Запуск парсера дерева регионов
        (new RunParseController)();
    }
}
