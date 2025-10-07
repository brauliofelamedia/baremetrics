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
        Schema::table('missing_users', function (Blueprint $table) {
            $table->text('import_notes')->nullable()->comment('Notas adicionales sobre el estado de importación');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('missing_users', function (Blueprint $table) {
            $table->dropColumn('import_notes');
        });
    }
};