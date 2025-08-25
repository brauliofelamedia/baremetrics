<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('system_configuration', function (Blueprint $table) {
            $table->id();
            $table->string('system_name')->default('Baremetrics Dashboard');
            $table->string('system_logo')->nullable();
            $table->string('system_favicon')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Insert default configuration record
        DB::table('system_configuration')->insert([
            'id' => 1,
            'system_name' => 'Créetelo',
            'system_logo' => null,
            'system_favicon' => null,
            'description' => 'Configuración general del sistema',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_configuration');
    }
};
