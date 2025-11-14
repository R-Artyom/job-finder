<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vacancy extends Model
{
    use HasFactory;
    // Явное название таблицы
    protected $table = 'vacancies';
    // Снять защиту массового заполнения модели
    protected $guarded = false;

    // Мутатор для обрезки description
    public function setDescriptionAttribute($value)
    {
        $this->attributes['description'] = mb_substr($value, 0, 100);
    }

    // Работодатель
    public function employer(): BelongsTo
    {
        // Связь вакансий с работодателем - принадлежит одному (обратная связь "Один ко многим")
        return $this->belongsTo(Employer::class, 'employer_id', 'id');
    }
}
