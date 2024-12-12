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
        Schema::create('counters', function (Blueprint $table) {
            // Атрибуты
            $table->id();
            $table->string('name', 100)->comment('Название');
            $table->unsignedBigInteger('value')->default(1)->comment('Значение');
            $table->unsignedBigInteger('limit')->default(500)->comment('Максимальное значение счетчика');
            $table->string('status', 100)->default('run')->comment('Статус');
            $table->timestamp('created_at')->nullable()->comment('Создано');
            $table->timestamp('updated_at')->nullable()->comment('Обновлено');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('counters');
    }
};
