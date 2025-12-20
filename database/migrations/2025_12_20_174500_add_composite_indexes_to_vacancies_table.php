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
            // * Составные индексы
            $table->index(['published_at', 'id'], 'vacancies_published_at_id_idx');
            $table->index(['area_id', 'id'], 'vacancies_area_id_id_idx');
            $table->index(['employer_id', 'id'], 'vacancies_employer_id_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vacancies', function (Blueprint $table) {
            $table->dropIndex('vacancies_published_at_id_idx');
            $table->dropIndex('vacancies_area_id_id_idx');
            $table->dropIndex('vacancies_employer_id_id_idx');
        });
    }
};
