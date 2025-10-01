<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;

class ShowBaremetricsFields extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:show-baremetrics-fields';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Muestra qué custom fields se están actualizando en Baremetrics';

    protected $baremetricsService;

    public function __construct(BaremetricsService $baremetricsService)
    {
        parent::__construct();
        $this->baremetricsService = $baremetricsService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('📋 Custom Fields que se actualizan en Baremetrics');
        $this->newLine();

        // Mapeo de campos de GoHighLevel a Baremetrics
        $fieldMapping = [
            '727708655' => [
                'name' => 'Relationship Status',
                'ghl_field_id' => '1fFJJsONHbRMQJCstvg1',
                'description' => 'Estado de relación del usuario',
                'source' => 'GoHighLevel Custom Field'
            ],
            '727708792' => [
                'name' => 'Community Location',
                'ghl_field_id' => 'q3BHfdxzT2uKfNO3icXG',
                'description' => 'Ubicación de la comunidad',
                'source' => 'GoHighLevel Custom Field'
            ],
            '727706634' => [
                'name' => 'Country',
                'ghl_field_id' => 'country',
                'description' => 'País del usuario',
                'source' => 'GoHighLevel Contact Field'
            ],
            '727707546' => [
                'name' => 'Engagement Score',
                'ghl_field_id' => 'j175N7HO84AnJycpUb9D',
                'description' => 'Puntuación de engagement',
                'source' => 'GoHighLevel Custom Field'
            ],
            '727708656' => [
                'name' => 'Has Kids',
                'ghl_field_id' => 'xy0zfzMRFpOdXYJkHS2c',
                'description' => 'Si el usuario tiene hijos',
                'source' => 'GoHighLevel Custom Field'
            ],
            '727707002' => [
                'name' => 'State',
                'ghl_field_id' => 'state',
                'description' => 'Estado/Provincia del usuario',
                'source' => 'GoHighLevel Contact Field'
            ],
            '727709283' => [
                'name' => 'Location',
                'ghl_field_id' => 'city',
                'description' => 'Ciudad del usuario',
                'source' => 'GoHighLevel Contact Field'
            ],
            '727708657' => [
                'name' => 'Zodiac Sign',
                'ghl_field_id' => 'JuiCbkHWsSc3iKfmOBpo',
                'description' => 'Signo zodiacal del usuario',
                'source' => 'GoHighLevel Custom Field'
            ],
            '750414465' => [
                'name' => 'Subscriptions',
                'ghl_field_id' => 'subscription_status',
                'description' => 'Estado de suscripción más reciente',
                'source' => 'GoHighLevel Subscription Data'
            ],
            '750342442' => [
                'name' => 'Coupon Code',
                'ghl_field_id' => 'coupon_code',
                'description' => 'Código de cupón más reciente utilizado',
                'source' => 'GoHighLevel Subscription Data'
            ],
        ];

        $this->info('🗂️  MAPEO DE CAMPOS:');
        $this->info('===================');
        $this->newLine();

        foreach ($fieldMapping as $baremetricsFieldId => $fieldInfo) {
            $this->line("📌 <fg=cyan>Campo Baremetrics ID:</fg=cyan> {$baremetricsFieldId}");
            $this->line("   <fg=yellow>Nombre:</fg=yellow> {$fieldInfo['name']}");
            $this->line("   <fg=green>Campo GHL ID:</fg=green> {$fieldInfo['ghl_field_id']}");
            $this->line("   <fg=blue>Descripción:</fg=blue> {$fieldInfo['description']}");
            $this->line("   <fg=magenta>Fuente:</fg=magenta> {$fieldInfo['source']}");
            $this->newLine();
        }

        $this->info('📊 RESUMEN:');
        $this->info('===========');
        $this->line("• Total de campos: " . count($fieldMapping));
        $this->line("• Campos de Custom Fields GHL: 6");
        $this->line("• Campos de Contact Fields GHL: 3");
        $this->line("• Campos de Subscription Data GHL: 2");
        $this->newLine();

        $this->info('🔍 CAMPOS POR CATEGORÍA:');
        $this->info('========================');
        $this->newLine();

        // Agrupar por fuente
        $bySource = [];
        foreach ($fieldMapping as $fieldId => $fieldInfo) {
            $source = $fieldInfo['source'];
            if (!isset($bySource[$source])) {
                $bySource[$source] = [];
            }
            $bySource[$source][] = $fieldInfo['name'];
        }

        foreach ($bySource as $source => $fields) {
            $this->line("<fg=cyan>{$source}:</fg=cyan>");
            foreach ($fields as $fieldName) {
                $this->line("  • {$fieldName}");
            }
            $this->newLine();
        }

        $this->info('💡 INFORMACIÓN ADICIONAL:');
        $this->info('=========================');
        $this->line("• Los campos se actualizan solo si tienen valores válidos (no vacíos)");
        $this->line("• Los valores '-' se consideran vacíos y no se actualizan");
        $this->line("• Los campos de suscripción obtienen la información más reciente");
        $this->line("• Se pueden usar múltiples variaciones de nombres de campo");
        $this->newLine();

        $this->info('🛠️  COMANDOS RELACIONADOS:');
        $this->info('==========================');
        $this->line("• Ver campos disponibles en Baremetrics: php artisan ghl:diagnose-baremetrics");
        $this->line("• Probar actualización de un usuario: php artisan ghl:test-processing usuario@ejemplo.com --debug");
        $this->line("• Ver logs de actualización: tail -f storage/logs/laravel.log | grep 'Updating customer attributes'");

        return 0;
    }
}
