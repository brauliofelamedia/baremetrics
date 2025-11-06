<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CancellationTracking;
use App\Models\CancellationSurvey;
use App\Models\CancellationToken;
use Illuminate\Support\Str;
use Carbon\Carbon;

class GenerateTestCancellations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:generate-cancellations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera 3 cancelaciones de prueba con diferentes estados del proceso';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Generando cancelaciones de prueba...');
        $this->newLine();

        // Cancelación 1: Solo solicitó correo (email_requested)
        $this->info('📧 Creando cancelación 1: Solo Correo Solicitado');
        $tracking1 = CancellationTracking::create([
            'email' => 'test1.solo-correo@example.com',
            'customer_id' => 'cus_test_' . Str::random(10),
            'stripe_customer_id' => 'cus_stripe_' . Str::random(10),
            'token' => Str::random(64),
            'email_requested' => true,
            'email_requested_at' => Carbon::now()->subHours(2),
            'current_step' => 'email_requested',
        ]);

        $token1 = CancellationToken::create([
            'token' => $tracking1->token,
            'email' => $tracking1->email,
            'expires_at' => Carbon::now()->addMinutes(30),
            'is_used' => false,
        ]);

        $this->info("   ✅ Tracking ID: {$tracking1->id}");
        $this->info("   📧 Email: {$tracking1->email}");
        $this->info("   📊 Estado: Solo Correo Solicitado");
        $this->newLine();

        // Cancelación 2: Vio la encuesta pero no la completó (survey_viewed)
        $this->info('👁️  Creando cancelación 2: Vio Encuesta (No Completada)');
        $tracking2 = CancellationTracking::create([
            'email' => 'test2.vio-encuesta@example.com',
            'customer_id' => 'cus_test_' . Str::random(10),
            'stripe_customer_id' => 'cus_stripe_' . Str::random(10),
            'token' => Str::random(64),
            'email_requested' => true,
            'email_requested_at' => Carbon::now()->subDays(1),
            'survey_viewed' => true,
            'survey_viewed_at' => Carbon::now()->subHours(5),
            'current_step' => 'survey_viewed',
        ]);

        $token2 = CancellationToken::create([
            'token' => $tracking2->token,
            'email' => $tracking2->email,
            'expires_at' => Carbon::now()->addMinutes(30),
            'is_used' => true,
            'used_at' => $tracking2->survey_viewed_at,
        ]);

        $this->info("   ✅ Tracking ID: {$tracking2->id}");
        $this->info("   📧 Email: {$tracking2->email}");
        $this->info("   📊 Estado: Vio Encuesta");
        $this->newLine();

        // Cancelación 3: Proceso completo (process_completed)
        $this->info('✅ Creando cancelación 3: Proceso Completo');
        $tracking3 = CancellationTracking::create([
            'email' => 'test3.completo@example.com',
            'customer_id' => 'cus_test_' . Str::random(10),
            'stripe_customer_id' => 'cus_stripe_' . Str::random(10),
            'token' => Str::random(64),
            'email_requested' => true,
            'email_requested_at' => Carbon::now()->subDays(2),
            'survey_viewed' => true,
            'survey_viewed_at' => Carbon::now()->subDays(1)->addHours(2),
            'survey_completed' => true,
            'survey_completed_at' => Carbon::now()->subDays(1)->addHours(3),
            'baremetrics_cancelled' => true,
            'baremetrics_cancelled_at' => Carbon::now()->subDays(1)->addHours(4),
            'baremetrics_cancellation_details' => json_encode([
                'subscription_oid' => 'sub_test_' . Str::random(10),
                'source_id' => 'source_test_123',
                'subscription_id' => 'sub_stripe_' . Str::random(10)
            ]),
            'stripe_cancelled' => true,
            'stripe_cancelled_at' => Carbon::now()->subDays(1)->addHours(4),
            'stripe_cancellation_details' => json_encode([
                'subscription_id' => 'sub_stripe_' . Str::random(10),
                'details' => ['status' => 'canceled']
            ]),
            'process_completed' => true,
            'process_completed_at' => Carbon::now()->subDays(1)->addHours(4),
            'current_step' => 'completed',
        ]);

        $token3 = CancellationToken::create([
            'token' => $tracking3->token,
            'email' => $tracking3->email,
            'expires_at' => Carbon::now()->addMinutes(30),
            'is_used' => true,
            'used_at' => $tracking3->survey_viewed_at,
        ]);

        // Crear el survey para la cancelación 3
        $survey3 = CancellationSurvey::create([
            'customer_id' => $tracking3->customer_id,
            'stripe_customer_id' => $tracking3->stripe_customer_id,
            'email' => $tracking3->email,
            'reason' => 'No conecté con el estilo, enfoque o dinámica de la comunidad',
            'additional_comments' => 'Esta es una cancelación de prueba con proceso completo.',
        ]);

        $this->info("   ✅ Tracking ID: {$tracking3->id}");
        $this->info("   ✅ Survey ID: {$survey3->id}");
        $this->info("   📧 Email: {$tracking3->email}");
        $this->info("   📊 Estado: Proceso Completo");
        $this->newLine();

        // Resumen
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📊 RESUMEN DE CANCELACIONES DE PRUEBA');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();
        
        $this->table(
            ['#', 'Email', 'Estado', 'Tracking ID', 'Survey ID'],
            [
                [
                    '1',
                    'test1.solo-correo@example.com',
                    'Solo Correo Solicitado',
                    $tracking1->id,
                    '-'
                ],
                [
                    '2',
                    'test2.vio-encuesta@example.com',
                    'Vio Encuesta',
                    $tracking2->id,
                    '-'
                ],
                [
                    '3',
                    'test3.completo@example.com',
                    'Proceso Completo',
                    $tracking3->id,
                    $survey3->id
                ],
            ]
        );

        $this->newLine();
        $this->info('✅ ¡Cancelaciones de prueba creadas exitosamente!');
        $this->info('📋 Puedes verlas en: /admin/cancellation-surveys');
        $this->newLine();

        return 0;
    }
}
