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
            $table->json('found_in_other_sources_data')->nullable()->comment('Datos de usuarios encontrados en otros sources');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ghl_baremetrics_comparisons', function (Blueprint $table) {
            $table->dropColumn('found_in_other_sources_data');
        });
    }
};