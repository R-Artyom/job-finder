<?php

use App\Http\Controllers\Vacancies\IndexController as VacanciesIndexController;
use App\Http\Controllers\Vacancies\IndexControllerElastic as VacanciesIndexControllerElastic;

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Вакансии
Route::get('/vacancies', [VacanciesIndexControllerElastic::class, '__invoke'])->name('vacancies.index');
