<?php

namespace App\Http\Controllers\Employers;

use App\Http\Controllers\Controller;
use App\Models\Employer;

class ShowController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Employer $employer)
    {
        $table = '';
        foreach ($employer->vacancies as $vacancy) {
            $table .=
                "<tr>
                    <td><a href=\"" . route('vacancies.show', ['vacancy' => $vacancy]) . "\">$vacancy->id</a></td>
                    <td>$vacancy->name</td>
                    <td>$vacancy->area_id</td>
                    <td>$vacancy->description</td>
                    <td>$vacancy->salary_from</td>
                    <td>$vacancy->salary_to</td>
                    <td>$vacancy->salary_currency</td>
                    <td>$vacancy->archived</td>
                    <td>$vacancy->published_at</td>
                </tr>";
        }

        echo
            "<h2><a href='https://hh.ru/employer/$employer->id' target='_blank'>$employer->name</a></h2>
            <b>url:</b> <a href='https://api.hh.ru/employers/$employer->id' target='_blank'>https://api.hh.ru/employers/$employer->id</a><br>
            <br>
            <table>
                <thead>
                    <tr>
                        <th>id</th>
                        <th>Название</th>
                        <th>Регион</th>
                        <th>Описание</th>
                        <th>ЗП от</th>
                        <th>ЗП до</th>
                        <th>Валюта</th>
                        <th>В архиве</th>
                        <th>Опубликовано</th>
                    </tr>
                </thead>
                <tbody>"
                    . $table .
                "</tbody>
            </table>
        ";
    }
}
