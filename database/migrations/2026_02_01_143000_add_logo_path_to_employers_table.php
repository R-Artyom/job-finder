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
            // Логотипом работодателя будем считать объект "240", при этом относительный путь (logo_path) бывает 2х видов:
            // 1) "logo_urls": {
            //     "90": "https://img.hhcdn.ru/employer-logo-round/308266.png",
            //     "240": "https://img.hhcdn.ru/employer-logo-round/381123.png",
            //     "original": "https://img.hhcdn.ru/employer-logo-original-round/289493.png"
            // },
            // 2) "logo_urls": {
            //     "90": "https://img.hhcdn.ru/employer-logo/7218499.png",
            //     "240": "https://img.hhcdn.ru/employer-logo/7218500.png",
            //     "original": "https://img.hhcdn.ru/employer-logo-original/1399682.png"
            // }
            // В данном случае logo_path это "/employer-logo-round/381123" и "/employer-logo/7218500.png"
            $table->string('logo_path', 255)->nullable()->comment('Относительный путь логотипа "240"')->after('site_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employers', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });
    }
};
