<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use Illuminate\Support\Facades\Log;

class TestGHLContactsSimple extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:test-contacts-simple 
                           {--limit=20 : Límite por página}
                           {--page=1 : Número de página}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Probar obtención simple de contactos en GoHighLevel';

    protected $ghlService;

    public function __construct(GoHighLevelService $ghlService)
    {
        parent::__construct();
        $this->ghlService = $ghlService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = (int) $this->option('limit');
        $page = (int) $this->option('page');

        $this->info('🔍 PRUEBA SIMPLE DE CONTACTOS EN GOHIGHLEVEL');
        $this->info('============================================');
        $this->newLine();

        try {
            $this->line("📊 Probando con límite: {$limit}, página: {$page}");
            $this->newLine();

            $startTime = microtime(true);
            $response = $this->ghlService->getContacts('', $page, $limit);
            $endTime = microtime(true);
            
            $duration = round(($endTime - $startTime) * 1000, 2);

            if (!$response) {
                $this->error("❌ No se obtuvo respuesta de la API");
                return 1;
            }

            $this->info("✅ Respuesta obtenida en {$duration}ms");
            $this->newLine();

            // Mostrar información básica
            if (isset($response['contacts'])) {
                $contacts = $response['contacts'];
                $this->line("📊 Contactos obtenidos: " . count($contacts));
                
                if (!empty($contacts)) {
                    $this->line("👤 Primer contacto:");
                    $firstContact = $contacts[0];
                    $this->line("  • ID: " . ($firstContact['id'] ?? 'N/A'));
                    $this->line("  • Email: " . ($firstContact['email'] ?? 'N/A'));
                    $this->line("  • Nombre: " . trim(($firstContact['firstName'] ?? '') . ' ' . ($firstContact['lastName'] ?? '')));
                }
            } else {
                $this->warn("⚠️  No se encontraron contactos en la respuesta");
            }

            // Mostrar información de paginación
            if (isset($response['meta']['pagination'])) {
                $pagination = $response['meta']['pagination'];
                $this->newLine();
                $this->line("📋 Información de paginación:");
                $this->line("  • has_more: " . ($pagination['has_more'] ?? 'N/A'));
                $this->line("  • total: " . ($pagination['total'] ?? 'N/A'));
                $this->line("  • page: " . ($pagination['page'] ?? 'N/A'));
                $this->line("  • limit: " . ($pagination['limit'] ?? 'N/A'));
            } else {
                $this->newLine();
                $this->line("📋 Información de paginación: No disponible");
            }

            // Mostrar respuesta completa para debugging
            $this->newLine();
            $this->line("🔍 Respuesta completa (primeros 1000 caracteres):");
            $this->line(substr(json_encode($response, JSON_PRETTY_PRINT), 0, 1000) . "...");

            $this->info('✅ Prueba completada exitosamente');
            
        } catch (\Exception $e) {
            $this->error("❌ Error durante la prueba: " . $e->getMessage());
            Log::error('Error en prueba simple de contactos GHL', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }
}
