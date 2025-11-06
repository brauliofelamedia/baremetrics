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
        Schema::create('cancellation_tracking', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('customer_id')->nullable()->index();
            $table->string('stripe_customer_id')->nullable()->index();
            $table->string('token')->nullable()->index();
            
            // Estados del proceso
            $table->boolean('email_requested')->default(false);
            $table->timestamp('email_requested_at')->nullable();
            
            $table->boolean('survey_viewed')->default(false);
            $table->timestamp('survey_viewed_at')->nullable();
            
            $table->boolean('survey_completed')->default(false);
            $table->timestamp('survey_completed_at')->nullable();
            
            $table->boolean('baremetrics_cancelled')->default(false);
            $table->timestamp('baremetrics_cancelled_at')->nullable();
            $table->text('baremetrics_cancellation_details')->nullable();
            
            $table->boolean('stripe_cancelled')->default(false);
            $table->timestamp('stripe_cancelled_at')->nullable();
            $table->text('stripe_cancellation_details')->nullable();
            
            $table->boolean('process_completed')->default(false);
            $table->timestamp('process_completed_at')->nullable();
            
            // Información adicional
            $table->string('current_step')->nullable(); // Para saber en qué paso se quedó
            $table->text('notes')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cancellation_tracking');
    }
};
