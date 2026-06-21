<?php

namespace App\Elastic;

class Index
{
    // * Индекс "Вакансии"
    public const VACANCIES_CURRENT = 'vacancies_v3'; // Текущая версия индекса. Используется только для создания индекса и реиндексации, далее по команде "elastic:switch-vacancies-alias {index}" индекс необходимо присвоить псевдониму
    public const VACANCIES = 'vacancies'; // Псевдоним индекса (alias). Только он используется в коде проекта
}
