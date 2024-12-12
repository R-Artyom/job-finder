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
        Schema::table('vacancies', function (Blueprint $table) {
            // * Простые индексы
            $table->index('employer_id', 'vacancy_employer_idx');

            // * Внешние ключи:
            // Ссылка на столбец id в таблице employers
            $table->foreign('employer_id', 'vacancy_employer_fk')
                ->references('id')->on('employers')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vacancies', function (Blueprint $table) {
            // Сначала удаление внешних ключей
            $table->dropForeign('vacancy_employer_fk');
            // Затем индексов
            $table->dropIndex('vacancy_employer_idx');
        });
    }
};
