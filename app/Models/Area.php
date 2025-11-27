<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    use HasFactory;
    // Явное название таблицы "Регионы"
    protected $table = 'areas';
    // Снять защиту массового заполнения модели
    protected $guarded = false;

    // Вакансии
    public function vacancies(): hasMany
    {
        // Связь региона с вакансиями - один ко многим
        return $this->hasMany(Vacancy::class, 'area_id', 'id');
    }
}
