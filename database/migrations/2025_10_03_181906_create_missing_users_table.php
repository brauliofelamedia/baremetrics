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
        Schema::create('missing_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comparison_id')->constrained('ghl_baremetrics_comparisons')->onDelete('cascade');
            $table->string('email')->comment('Email del usuario');
            $table->string('name')->nullable()->comment('Nombre del usuario');
            $table->string('phone')->nullable()->comment('Teléfono del usuario');
            $table->string('company')->nullable()->comment('Empresa del usuario');
            $table->text('tags')->nullable()->comment('Tags del usuario');
            $table->date('created_date')->nullable()->comment('Fecha de creación en GHL');
            $table->date('last_activity')->nullable()->comment('Última actividad en GHL');
            $table->enum('import_status', ['pending', 'importing', 'imported', 'failed'])->default('pending');
            $table->string('baremetrics_customer_id')->nullable()->comment('ID del cliente en Baremetrics después de importar');
            $table->text('import_error')->nullable()->comment('Error al importar');
            $table->timestamp('imported_at')->nullable()->comment('Fecha de importación');
            $table->timestamps();
            
            $table->index(['comparison_id', 'import_status']);
            $table->index('email');
            $table->index('imported_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('missing_users');
    }
};