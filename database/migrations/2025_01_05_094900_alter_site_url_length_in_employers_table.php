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
        Schema::table('employers', function (Blueprint $table) {
            // Увеличение длины до 200 символов
            $table->string('site_url', 200)->nullable()->comment('Сайт')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employers', function (Blueprint $table) {
            // Возврат старого значения длины
            $table->string('site_url', 150)->nullable()->comment('Сайт')->change();
        });
    }
};
