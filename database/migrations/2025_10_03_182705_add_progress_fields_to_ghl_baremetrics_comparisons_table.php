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
        Schema::table('ghl_baremetrics_comparisons', function (Blueprint $table) {
            $table->integer('total_rows_processed')->default(0)->comment('Total de filas procesadas del CSV');
            $table->integer('ghl_users_processed')->default(0)->comment('Usuarios GHL procesados');
            $table->integer('baremetrics_users_fetched')->default(0)->comment('Usuarios obtenidos de Baremetrics');
            $table->integer('comparisons_made')->default(0)->comment('Comparaciones realizadas');
            $table->integer('users_found_count')->default(0)->comment('Usuarios encontrados en ambos sistemas');
            $table->integer('users_missing_count')->default(0)->comment('Usuarios faltantes encontrados');
            $table->text('current_step')->nullable()->comment('Paso actual del procesamiento');
            $table->decimal('progress_percentage', 5, 2)->default(0)->comment('Porcentaje de progreso');
            $table->timestamp('last_progress_update')->nullable()->comment('Última actualización de progreso');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ghl_baremetrics_comparisons', function (Blueprint $table) {
            $table->dropColumn([
                'total_rows_processed',
                'ghl_users_processed', 
                'baremetrics_users_fetched',
                'comparisons_made',
                'users_found_count',
                'users_missing_count',
                'current_step',
                'progress_percentage',
                'last_progress_update'
            ]);
        });
    }
};