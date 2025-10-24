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
        $driver = DB::getDriverName();
        
        if ($driver === 'pgsql') {
            // PostgreSQL: Drop old constraint and add new one with additional value
            DB::statement("
                ALTER TABLE missing_users 
                DROP CONSTRAINT IF EXISTS missing_users_import_status_check
            ");
            
            DB::statement("
                ALTER TABLE missing_users 
                ADD CONSTRAINT missing_users_import_status_check 
                CHECK (import_status::text = ANY (ARRAY['pending'::character varying, 'importing'::character varying, 'imported'::character varying, 'failed'::character varying, 'found_in_other_source'::character varying]::text[]))
            ");
        } else {
            // MySQL
            DB::statement("ALTER TABLE missing_users MODIFY COLUMN import_status ENUM('pending', 'importing', 'imported', 'failed', 'found_in_other_source') DEFAULT 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'pgsql') {
            // PostgreSQL: Restore original constraint
            DB::statement("
                ALTER TABLE missing_users 
                DROP CONSTRAINT IF EXISTS missing_users_import_status_check
            ");
            
            DB::statement("
                ALTER TABLE missing_users 
                ADD CONSTRAINT missing_users_import_status_check 
                CHECK (import_status::text = ANY (ARRAY['pending'::character varying, 'importing'::character varying, 'imported'::character varying, 'failed'::character varying]::text[]))
            ");
        } else {
            // MySQL
            DB::statement("ALTER TABLE missing_users MODIFY COLUMN import_status ENUM('pending', 'importing', 'imported', 'failed') DEFAULT 'pending'");
        }
    }
};