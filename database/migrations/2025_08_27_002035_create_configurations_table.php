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
        Schema::create('configurations', function (Blueprint $table) {
            $table->id();
            // GoHighLevel integration fields (nullable)
            $table->string('ghl_client_id')->nullable();
            $table->string('ghl_client_secret')->nullable();
            $table->string('ghl_code')->nullable();
            $table->text('ghl_token')->nullable();
            $table->string('ghl_location')->nullable();
            $table->string('ghl_company')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configurations');
    }
};
