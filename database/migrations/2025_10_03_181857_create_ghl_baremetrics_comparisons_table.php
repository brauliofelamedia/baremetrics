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
        Schema::create('ghl_baremetrics_comparisons', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Nombre descriptivo de la comparación');
            $table->string('csv_file_path')->comment('Ruta del archivo CSV subido');
            $table->string('csv_file_name')->comment('Nombre original del archivo CSV');
            $table->integer('total_ghl_users')->default(0)->comment('Total usuarios en CSV GHL');
            $table->integer('total_baremetrics_users')->default(0)->comment('Total usuarios en Baremetrics');
            $table->integer('users_found_in_baremetrics')->default(0)->comment('Usuarios encontrados en ambos');
            $table->integer('users_missing_from_baremetrics')->default(0)->comment('Usuarios faltantes en Baremetrics');
            $table->decimal('sync_percentage', 5, 2)->default(0)->comment('Porcentaje de sincronización');
            $table->json('comparison_data')->nullable()->comment('Datos detallados de la comparación');
            $table->json('missing_users_data')->nullable()->comment('Datos de usuarios faltantes');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable()->comment('Mensaje de error si falla');
            $table->timestamp('processed_at')->nullable()->comment('Fecha de procesamiento');
            $table->timestamps();
            
            $table->index(['status', 'created_at']);
            $table->index('processed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ghl_baremetrics_comparisons');
    }
};