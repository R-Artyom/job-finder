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
        Schema::table('areas', function (Blueprint $table) {
            $table->unsignedInteger('country_id')->nullable()->comment('Страна')->after('parent_id');
            // Простые индексы
            $table->index('country_id', 'areas_country_id_idx');
            // Ссылка на столбец id в таблице areas
            $table->foreign('country_id', 'areas_country_id_fk')
                ->references('id')
                ->on('areas')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            // Сначала удаление внешних ключей
            $table->dropForeign('areas_country_id_fk');
            // Затем индексов
            $table->dropIndex('areas_country_id_idx');
            // Затем атрибутов
            $table->dropColumn('country_id');
        });
    }
};
