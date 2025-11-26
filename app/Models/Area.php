<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    use HasFactory;
    // Явное название таблицы "Регионы"
    protected $table = 'areas';
    // Снять защиту массового заполнения модели
    protected $guarded = false;
}
