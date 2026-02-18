<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Запуск парсинга регионов
        $schedule->command('parse-areas:run')->daily();

        // Запуск парсинга вакансии
        $schedule->command('parse-vacancy:run')
            // Каждые 10 минут (0,10,20,30,40,50)
            ->everyTenMinutes()
            // В любой момент времени работает только один экземпляр команды, lock снимется максимум через 10 минут (т.к lock может остаться, если команда упала с фатальной ошибкой)
            ->withoutOverlapping(10);

        // Запуск обновления логотипа работодателя
        $schedule->command('update-logo-path:run')
            // Каждую минуту, кроме тех, когда идёт парсинг вакансий
            ->everyMinute()
            ->when(fn () => now()->minute % 10 !== 0)
            // В любой момент времени работает только один экземпляр команды, lock снимется максимум через 10 минут (т.к lock может остаться, если команда упала с фатальной ошибкой)
            ->withoutOverlapping(10);
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
