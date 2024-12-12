<?php

use App\Http\Controllers\Employers\IndexController;
use App\Http\Controllers\Employers\ShowController;
use App\Http\Controllers\Vacancies\IndexController as VacanciesIndexController;
use App\Http\Controllers\Vacancies\RunParseController as VacanciesRunParseController;
use App\Http\Controllers\Vacancies\ShowController as VacanciesShowController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Работодатели
Route::get('/employers', [IndexController::class, '__invoke'])->name('employers.index');

// Вакансии
Route::get('/vacancies', [VacanciesIndexController::class, '__invoke'])->name('vacancies.index');
Route::get('/vacancies/run', [VacanciesRunParseController::class, '__invoke'])->name('vacancies.run');

// Главная
Route::get('/', function () {
    return view('welcome');
});
