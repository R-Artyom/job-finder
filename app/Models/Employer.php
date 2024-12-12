<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employer extends Model
{
    use HasFactory;
    // Явное название таблицы
    protected $table = 'employers';
    // Снять защиту массового заполнения модели
    protected $guarded = false;
    // Постоянная жадная загрузка
    protected $with = ['vacancies'];

    // Вакансии
    public function vacancies(): hasMany
    {
        // Связь работодателя с вакансиями - один ко многим
        return $this->hasMany(Vacancy::class, 'employer_id', 'id');
    }
}
