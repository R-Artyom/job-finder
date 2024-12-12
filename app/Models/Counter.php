<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Counter extends Model
{
    use HasFactory;
    // Явное название таблицы
    protected $table = 'counters';
    // Снять защиту массового заполнения модели
    protected $guarded = false;
}
