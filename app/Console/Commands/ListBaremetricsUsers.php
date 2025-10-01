<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;
use Illuminate\Support\Facades\Log;

class ListBaremetricsUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:list-baremetrics-users 
                           {--limit=20 : Límite de usuarios a mostrar (default: 20)}
                           {--offset=0 : Índice de inicio (default: 0)}
                           {--search= : Buscar por email (opcional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lista usuarios de Baremetrics con sus índices para facilitar el reanudar procesamiento';

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
        $offset = (int) $this->option('offset');
        $search = $this->option('search');

        $this->info('👥 Listando usuarios de Baremetrics...');
        
        if ($search) {
            $this->info("🔍 Buscando usuarios que contengan: {$search}");
        }
        
        $this->info("📊 Mostrando {$limit} usuarios desde índice {$offset}");
        $this->newLine();

        try {
            // Obtener usuarios
            $allUsers = $this->getAllBaremetricsUsers();
            
            if (empty($allUsers)) {
                $this->error('❌ No se encontraron usuarios en Baremetrics');
                return 1;
            }

            $totalUsers = count($allUsers);
            $this->info("✅ Total de usuarios encontrados: {$totalUsers}");

            // Filtrar por búsqueda si se especifica
            if ($search) {
                $allUsers = array_filter($allUsers, function($user) use ($search) {
                    $email = $user['email'] ?? '';
                    return stripos($email, $search) !== false;
                });
                $allUsers = array_values($allUsers); // Reindexar
                $this->info("🔍 Usuarios que contienen '{$search}': " . count($allUsers));
            }

            // Aplicar offset y limit
            $users = array_slice($allUsers, $offset, $limit);

            if (empty($users)) {
                $this->warn("⚠️  No hay usuarios para mostrar desde el índice {$offset}");
                $this->info("💡 Los índices válidos van de 0 a " . (count($allUsers) - 1));
                return 0;
            }

            // Mostrar usuarios
            $this->displayUsers($users, $offset);

            // Mostrar información de navegación
            $this->newLine();
            $this->info('📋 INFORMACIÓN DE NAVEGACIÓN:');
            $this->line("• Total usuarios: {$totalUsers}");
            $this->line("• Mostrando: " . count($users) . " usuarios");
            $this->line("• Desde índice: {$offset}");
            $this->line("• Hasta índice: " . ($offset + count($users) - 1));
            
            if ($offset + count($users) < count($allUsers)) {
                $nextOffset = $offset + count($users);
                $this->line("• Siguiente página: --offset={$nextOffset}");
            }

            // Mostrar comandos de ejemplo
            $this->newLine();
            $this->info('💡 COMANDOS DE EJEMPLO:');
            $this->line("• Ver más usuarios: php artisan ghl:list-baremetrics-users --offset=" . ($offset + $limit));
            $this->line("• Reanudar desde índice {$offset}: php artisan ghl:resume-processing --from={$offset}");
            $this->line("• Buscar usuario: php artisan ghl:list-baremetrics-users --search=usuario@ejemplo.com");

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            Log::error('Error listando usuarios de Baremetrics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }

    /**
     * Obtener todos los usuarios de Baremetrics
     */
    private function getAllBaremetricsUsers()
    {
        $allUsers = [];
        
        // Obtener fuentes de Stripe
        $sources = $this->baremetricsService->getSources();
        
        if (!$sources) {
            throw new \Exception('No se pudieron obtener las fuentes de Baremetrics');
        }

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

        if (empty($sourceIds)) {
            throw new \Exception('No se encontraron fuentes de Stripe en Baremetrics');
        }

        // Obtener clientes de cada fuente
        foreach ($sourceIds as $sourceId) {
            $page = 1;
            $hasMore = true;
            
            while ($hasMore) {
                $response = $this->baremetricsService->getCustomers($sourceId, $page);
                
                if (!$response) {
                    break;
                }

                $customers = [];
                if (is_array($response) && isset($response['customers']) && is_array($response['customers'])) {
                    $customers = $response['customers'];
                } elseif (is_array($response)) {
                    $customers = $response;
                }

                if (isset($response['meta']['pagination'])) {
                    $pagination = $response['meta']['pagination'];
                    $hasMore = $pagination['has_more'] ?? false;
                } else {
                    $hasMore = false;
                }

                if (!empty($customers)) {
                    $allUsers = array_merge($allUsers, $customers);
                }

                $page++;
                usleep(100000); // Pequeña pausa entre requests
            }
        }

        return $allUsers;
    }

    /**
     * Mostrar usuarios en tabla
     */
    private function displayUsers($users, $offset)
    {
        $this->table(
            ['Índice', 'Email', 'OID', 'Nombre', 'Estado'],
            array_map(function($user, $index) use ($offset) {
                return [
                    $offset + $index,
                    $user['email'] ?? 'N/A',
                    $user['oid'] ?? 'N/A',
                    $user['name'] ?? 'N/A',
                    $user['status'] ?? 'N/A'
                ];
            }, $users, array_keys($users))
        );
    }
}
