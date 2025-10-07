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
        // MySQL no permite modificar ENUM directamente, necesitamos usar ALTER TABLE
        DB::statement("ALTER TABLE missing_users MODIFY COLUMN import_status ENUM('pending', 'importing', 'imported', 'failed', 'found_in_other_source') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE missing_users MODIFY COLUMN import_status ENUM('pending', 'importing', 'imported', 'failed') DEFAULT 'pending'");
    }
};