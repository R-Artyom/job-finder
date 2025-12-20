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
            // * Простые одиночные индексы
            $table->index('area_id', 'vacancies_area_id_idx');
            $table->index('salary_from', 'vacancies_salary_from_idx');
            $table->index('salary_to', 'vacancies_salary_to_idx');
            $table->index('salary_currency', 'vacancies_salary_currency_idx');
            $table->index('archived', 'vacancies_archived_idx');
            $table->index('published_at', 'vacancies_published_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vacancies', function (Blueprint $table) {
            $table->dropIndex('vacancies_area_id_idx');
            $table->dropIndex('vacancies_salary_from_idx');
            $table->dropIndex('vacancies_salary_to_idx');
            $table->dropIndex('vacancies_salary_currency_idx');
            $table->dropIndex('vacancies_archived_idx');
            $table->dropIndex('vacancies_published_at_idx');
        });
    }
};
