<?php

namespace App\Http\Controllers\Vacancies;

use App\Http\Controllers\Controller;
use App\Models\Vacancy;
use Carbon\Carbon;

class StoreController extends Controller
{
    public function __invoke(array $data)
    {
        $vacancy = new Vacancy;

        // Id вакансии
        if (!empty($data['id'])) {
            $vacancy->id = $data['id'];
        }
        // Название вакансии
        if (!empty($data['name'])) {
            $vacancy->name = $data['name'];
        }
        // Регион
        if (!empty($data['area']['id'])) {
            $vacancy->area_id = $data['area']['id'];
        }
        // Описание (100 симв)
        if (!empty($data['description'])) {
            $vacancy->description = $data['description'];
        }
        // Работодатель
        if (!empty($data['employer']['id'])) {
            $vacancy->employer_id = $data['employer']['id'];
        }
        // Зарплата От..До и Валюта
        if (!empty($data['salary']['from'])) {
            $vacancy->salary_from = $data['salary']['from'];
        }
        if (!empty($data['salary']['to'])) {
            $vacancy->salary_to = $data['salary']['to'];
        }
        if (!empty($data['salary']['currency'])) {
            $vacancy->salary_currency = $data['salary']['currency'];
        }
        // Признак "В архиве"
        if (!empty($data['archived'])) {
            $vacancy->archived = $data['archived'];
        }
        // Дата публикации
        if (!empty($data['published_at'])) {
            $vacancy->published_at = Carbon::parse($data['published_at'])->setTimezone('UTC')->format('Y-m-d H:i:s');
        }
        // Оригинальная дата создания вакансии
        if (!empty($data['created_at'])) {
            $vacancy->created_at = Carbon::parse($data['created_at'])->setTimezone('UTC')->format('Y-m-d H:i:s');
        }
        // Дата создания записи в БД
        if (!empty($data['updated_at'])) {
            $vacancy->updated_at = now()->setTimezone('UTC')->format('Y-m-d H:i:s');
        }

        // Создание отеля
        $vacancy->save();
    }
}
