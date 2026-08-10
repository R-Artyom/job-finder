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
        $schedule->command('parse-vacancy:run')->everyMinute();

//        // * Динамический режим нагрузки
//        // Запуск основного парсинга вакансий
//        $schedule->command('parse-vacancy:run')
//            ->everyMinute()
//            ->when(function () {
//                $now = now()->timezone('Europe/Moscow');
//                $hour = $now->hour;
//                $minute = $now->minute;
//                $isNight = $hour >= 20 || $hour < 8;
//                return $isNight
//                    // Ночь - Каждую 4-ю минуту (0,4,8,12,16...)
//                    ? $minute % 4 === 0
//                    // День - Только по четным минутам
//                    : $minute % 2 === 0;
//            })
//            // В любой момент времени работает только один экземпляр команды, lock снимется максимум через 10 минут (т.к lock может остаться, если команда упала с фатальной ошибкой)
//            ->withoutOverlapping(10);
//
//        // Запуск повторного парсинга вакансий для считывания по ошибке пропущенных после первого прохода вакансий
//        $schedule->command('parse-vacancy:run secondVacancyId')
//            ->everyMinute()
//            ->when(function () {
//                $now = now()->timezone('Europe/Moscow');
//                $hour = $now->hour;
//                $minute = $now->minute;
//                $isNight = $hour >= 20 || $hour < 8;
//                return $isNight
//                    // Ночь - Каждую минуту, кроме тех, когда идёт парсинг вакансий
//                    ? $minute % 4 !== 0
//                    // День - Только по НЕчетным минутам
//                    : $minute % 2 === 1;
//            })
//            // В любой момент времени работает только один экземпляр команды, lock снимется максимум через 10 минут (т.к lock может остаться, если команда упала с фатальной ошибкой)
//            ->withoutOverlapping(10);
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
