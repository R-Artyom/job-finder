<?php

use App\Http\Controllers\Vacancies\IndexController as VacanciesIndexController;

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
Route::get('/vacancies', [VacanciesIndexController::class, '__invoke'])->name('vacancies.index');
