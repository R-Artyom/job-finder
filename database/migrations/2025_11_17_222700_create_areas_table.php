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
        Schema::create('areas', function (Blueprint $table) {
            // Коммент к таблице
            $table->comment('Дерево регионов');
            // Атрибуты
            $table->unsignedInteger('id')->comment('Регион')->primary();
            $table->unsignedInteger('parent_id')->nullable()->comment('Родитель');
            $table->string('name', 100)->nullable()->comment('Название');
            $table->string('utc_offset', 6)->nullable()->comment('Таймзона');
            $table->timestamp('created_at')->nullable()->comment('Создано');
            $table->timestamp('updated_at')->nullable()->comment('Обновлено');
            // Простые индексы
            $table->index('parent_id');
            // Уникальные индексы
            $table->unique('id');
            // Ссылка на столбец id в таблице areas
            $table->foreign('parent_id')
                ->references('id')->on('areas')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('areas');
    }
};
