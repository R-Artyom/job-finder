<?php

namespace App\Console\Commands\Employers;

use App\Http\Controllers\Employers\RunUpdateLogoPathController;
use Illuminate\Console\Command;

class RunParseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update-logo-path:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Запуск обновления логотипа работодателя';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Запуск обновления лого работодателя
        (new RunUpdateLogoPathController)();
    }
}
