<?php

namespace App\Http\Controllers\Vacancies;

use App\Http\Controllers\Controller;
use App\Models\Vacancy;

class ShowController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Vacancy $vacancy)
    {
        echo
        "
            <h2><a href='https://hh.ru/vacancy/$vacancy->id' target='_blank'>$vacancy->name</a></h2>
            <b>Зарплата ОТ:</b> " . ($vacancy->salary_from ?? '-') . "<br>
            <b>Зарплата ДО:</b> " . ($vacancy->salary_to ?? '-') . "<br>
            <b>Валюта:</b> " . ($vacancy->salary_currency ?? '-') . "<br>
            <b>Описание:</b> " . ($vacancy->description ?? '-') . "<br>
            <b>url:</b> <a href='https://api.hh.ru/vacancies/$vacancy->id' target='_blank'>https://api.hh.ru/vacancies/$vacancy->id</a><br>
            <br>
            <b><a href='https://hh.ru/employer/$vacancy->employer_id'>{$vacancy->employer->name}</a></b><br>
            <b>url:</b> <a href='https://api.hh.ru/employers/$vacancy->employer_id'>https://api.hh.ru/employers/$vacancy->employer_id</a><br>
            <br>
        ";
    }
}
