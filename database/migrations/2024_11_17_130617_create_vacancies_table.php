<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vacancies', function (Blueprint $table) {
            // Атрибуты
            $table->unsignedBigInteger('id')->comment('Вакансия')->primary();
            $table->string('name', 150)->nullable()->comment('Название');
            $table->unsignedInteger('area_id')->nullable()->comment('Регион');
//            $table->string('alternate_url', 100)->nullable()->comment('Ссылка');
            $table->string('description', 100)->nullable()->comment('Описание');
            $table->unsignedBigInteger('employer_id')->nullable()->comment('Работодатель');
            $table->unsignedInteger('salary_from')->nullable()->comment('ЗП от');
            $table->unsignedInteger('salary_to')->nullable()->comment('ЗП до');
            $table->string('salary_currency', 20)->nullable()->comment('Валюта ЗП');
            $table->boolean('archived')->nullable()->comment('В архиве');
            $table->timestamp('published_at')->nullable()->comment('Опубликовано');
            $table->timestamp('created_at')->nullable()->comment('Создано');
            $table->timestamp('updated_at')->nullable()->comment('Обновлено');
            // Уникальные индексы
            $table->unique('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vacancies');
    }
};
