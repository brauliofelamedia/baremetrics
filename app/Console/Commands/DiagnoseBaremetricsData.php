<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;
use Illuminate\Support\Facades\Log;

class DiagnoseBaremetricsData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:diagnose-baremetrics 
                           {--limit=10 : Límite de usuarios a mostrar (default: 10)}
                           {--check-oid : Verificar usuarios sin OID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnostica la estructura de datos de Baremetrics';

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
        $limit = (int) $this->option('limit');
        $checkOid = $this->option('check-oid');

        $this->info('🔍 Diagnosticando estructura de datos de Baremetrics...');
        $this->newLine();

        try {
            // Obtener fuentes
            $this->info('📋 Obteniendo fuentes...');
            $sources = $this->baremetricsService->getSources();
            
            if (!$sources) {
                $this->error('❌ No se pudieron obtener las fuentes');
                return 1;
            }

            $this->info('✅ Fuentes obtenidas exitosamente');
            $this->displaySources($sources);

            // Obtener usuarios de muestra
            $this->newLine();
            $this->info('👥 Obteniendo usuarios de muestra...');
            
            $allUsers = $this->getSampleUsers($sources, $limit);
            
            if (empty($allUsers)) {
                $this->error('❌ No se encontraron usuarios');
                return 1;
            }

            $this->info("✅ Se obtuvieron " . count($allUsers) . " usuarios de muestra");
            
            // Analizar estructura
            $this->newLine();
            $this->info('📊 Analizando estructura de datos...');
            $this->analyzeUserStructure($allUsers, $checkOid);

        } catch (\Exception $e) {
            $this->error('❌ Error durante diagnóstico: ' . $e->getMessage());
            Log::error('Error en diagnóstico de Baremetrics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }

    /**
     * Muestra información de las fuentes
     */
    private function displaySources($sources)
    {
        $sourcesNew = [];
        if (is_array($sources) && isset($sources['sources']) && is_array($sources['sources'])) {
            $sourcesNew = $sources['sources'];
        } elseif (is_array($sources)) {
            $sourcesNew = $sources;
        }

        $this->table(['ID', 'Nombre', 'Proveedor', 'Estado'], array_map(function($source) {
            return [
                $source['id'] ?? 'N/A',
                $source['name'] ?? 'N/A',
                $source['provider'] ?? 'N/A',
                $source['status'] ?? 'N/A'
            ];
        }, $sourcesNew));

        // Filtrar fuentes de Stripe
        $stripeSources = array_filter($sourcesNew, function ($source) {
            return isset($source['provider']) && $source['provider'] === 'stripe';
        });

        $this->info("📊 Fuentes de Stripe encontradas: " . count($stripeSources));
    }

    /**
     * Obtiene usuarios de muestra
     */
    private function getSampleUsers($sources, $limit)
    {
        $allUsers = [];
        
        // Normalizar respuesta de fuentes
        $sourcesNew = [];
        if (is_array($sources) && isset($sources['sources']) && is_array($sources['sources'])) {
            $sourcesNew = $sources['sources'];
        } elseif (is_array($sources)) {
            $sourcesNew = $sources;
        }

        // Filtrar solo fuentes de Stripe
        $stripeSources = array_values(array_filter($sourcesNew, function ($source) {
            return isset($source['provider']) && $source['provider'] === 'stripe';
        }));

        $sourceIds = array_values(array_filter(array_column($stripeSources, 'id'), function ($id) {
            return !empty($id);
        }));

        // Obtener clientes de cada fuente (limitado)
        foreach ($sourceIds as $sourceId) {
            $response = $this->baremetricsService->getCustomers($sourceId, 1);
            
            if (!$response) {
                continue;
            }

            $customers = [];
            if (is_array($response) && isset($response['customers']) && is_array($response['customers'])) {
                $customers = $response['customers'];
            } elseif (is_array($response)) {
                $customers = $response;
            }

            if (!empty($customers)) {
                $allUsers = array_merge($allUsers, array_slice($customers, 0, $limit));
            }
        }

        return array_slice($allUsers, 0, $limit);
    }

    /**
     * Analiza la estructura de los usuarios
     */
    private function analyzeUserStructure($users, $checkOid)
    {
        $this->info("📋 Analizando " . count($users) . " usuarios...");
        
        $hasOid = 0;
        $hasEmail = 0;
        $hasBoth = 0;
        $missingOid = [];
        $missingEmail = [];

        foreach ($users as $index => $user) {
            $hasOidField = isset($user['oid']) && !empty($user['oid']);
            $hasEmailField = isset($user['email']) && !empty($user['email']);
            
            if ($hasOidField) $hasOid++;
            if ($hasEmailField) $hasEmail++;
            if ($hasOidField && $hasEmailField) $hasBoth++;
            
            if (!$hasOidField) {
                $missingOid[] = $index;
            }
            if (!$hasEmailField) {
                $missingEmail[] = $index;
            }
        }

        $this->newLine();
        $this->info('📊 ESTADÍSTICAS DE ESTRUCTURA:');
        $this->line("• Usuarios con OID: {$hasOid}/" . count($users));
        $this->line("• Usuarios con Email: {$hasEmail}/" . count($users));
        $this->line("• Usuarios con ambos: {$hasBoth}/" . count($users));
        $this->line("• Usuarios sin OID: " . count($missingOid));
        $this->line("• Usuarios sin Email: " . count($missingEmail));

        // Mostrar usuarios problemáticos
        if ($checkOid && !empty($missingOid)) {
            $this->newLine();
            $this->warn('⚠️  USUARIOS SIN OID:');
            foreach (array_slice($missingOid, 0, 5) as $index) {
                $this->line("• Usuario {$index}: " . json_encode($users[$index]));
            }
            if (count($missingOid) > 5) {
                $this->line("• ... y " . (count($missingOid) - 5) . " más");
            }
        }

        if (!empty($missingEmail)) {
            $this->newLine();
            $this->warn('⚠️  USUARIOS SIN EMAIL:');
            foreach (array_slice($missingEmail, 0, 5) as $index) {
                $this->line("• Usuario {$index}: " . json_encode($users[$index]));
            }
            if (count($missingEmail) > 5) {
                $this->line("• ... y " . (count($missingEmail) - 5) . " más");
            }
        }

        // Mostrar estructura de ejemplo
        $this->newLine();
        $this->info('📋 ESTRUCTURA DE USUARIO DE EJEMPLO:');
        if (!empty($users)) {
            $exampleUser = $users[0];
            $this->line(json_encode($exampleUser, JSON_PRETTY_PRINT));
        }

        // Recomendaciones
        $this->newLine();
        $this->info('💡 RECOMENDACIONES:');
        
        if ($hasBoth === count($users)) {
            $this->line('✅ Todos los usuarios tienen OID y email - estructura correcta');
        } else {
            if (count($missingOid) > 0) {
                $this->line('❌ Hay usuarios sin OID - esto causará errores en el procesamiento');
                $this->line('   Solución: Verificar configuración de Baremetrics o filtrar usuarios inválidos');
            }
            if (count($missingEmail) > 0) {
                $this->line('❌ Hay usuarios sin email - estos no se pueden procesar');
                $this->line('   Solución: Filtrar usuarios sin email antes del procesamiento');
            }
        }
    }
}
