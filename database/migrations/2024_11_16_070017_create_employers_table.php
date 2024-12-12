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
        Schema::create('employers', function (Blueprint $table) {
            // Атрибуты
            $table->unsignedBigInteger('id')->comment('Работодатель')->primary();
            $table->string('name', 100)->nullable()->comment('Название');
            $table->unsignedInteger('area_id')->nullable()->comment('Регион');
//            $table->string('alternate_url', 100)->nullable()->comment('Ссылка');
            $table->string('site_url', 100)->nullable()->comment('Сайт');
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
        Schema::dropIfExists('employers');
    }
};
