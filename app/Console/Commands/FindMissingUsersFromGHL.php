<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;
use App\Services\GoHighLevelService;
use Illuminate\Support\Facades\Log;

class FindMissingUsersFromGHL extends Command
{
    protected $signature = 'baremetrics:find-missing-users 
                           {--limit=10 : Límite de usuarios a procesar}
                           {--tags= : Tags específicos de GHL a buscar (separados por coma)}
                           {--dry-run : Solo mostrar resultados sin hacer cambios}';
    
    protected $description = 'Encuentra usuarios de GHL que NO existen en ningún source de Baremetrics';

    public function handle()
    {
        $limit = (int) $this->option('limit');
        $tags = $this->option('tags');
        $isDryRun = $this->option('dry-run');
        
        $this->info("🔍 Buscando usuarios faltantes de GHL en Baremetrics...");
        $this->info("📊 Límite: {$limit} usuarios");
        
        if ($tags) {
            $this->info("🏷️ Tags específicos: {$tags}");
        }
        
        if ($isDryRun) {
            $this->warn("⚠️  MODO DRY-RUN: Solo análisis, sin cambios");
        }

        // Configurar para producción
        config(['services.baremetrics.environment' => 'production']);
        
        $baremetricsService = new BaremetricsService();
        $baremetricsService->reinitializeConfiguration();
        
        $ghlService = new GoHighLevelService();

        try {
            // 1. Obtener usuarios de GHL
            $this->info("📡 Obteniendo usuarios de GHL...");
            $ghlUsers = $this->getGHLUsers($ghlService, $limit, $tags);
            
            if (empty($ghlUsers)) {
                $this->error("❌ No se encontraron usuarios en GHL");
                return;
            }

            $this->info("📊 Encontrados " . count($ghlUsers) . " usuarios en GHL");

            // 2. Obtener todos los sources de Baremetrics
            $this->info("📋 Obteniendo sources de Baremetrics...");
            $sourcesResponse = $baremetricsService->getSources();
            
            if (!$sourcesResponse || !isset($sourcesResponse['sources'])) {
                $this->error("❌ No se pudieron obtener los sources");
                return;
            }

            $sources = $sourcesResponse['sources'];
            $this->info("📊 Encontrados " . count($sources) . " sources en Baremetrics");

            // 3. Analizar cada usuario de GHL
            $missingUsers = [];
            $existingUsers = [];
            $duplicateUsers = [];

            $progressBar = $this->output->createProgressBar(count($ghlUsers));
            $progressBar->start();

            foreach ($ghlUsers as $user) {
                $email = $user['email'];
                $foundInSources = [];
                
                // Buscar en cada source
                foreach ($sources as $source) {
                    $sourceId = $source['id'];
                    $customers = $baremetricsService->getCustomers($sourceId);
                    
                    if ($customers && isset($customers['customers'])) {
                        foreach ($customers['customers'] as $customer) {
                            if (strtolower($customer['email']) === strtolower($email)) {
                                $foundInSources[] = [
                                    'source_id' => $sourceId,
                                    'provider' => $source['provider'] ?? 'unknown',
                                    'customer_oid' => $customer['oid'],
                                    'name' => $customer['name']
                                ];
                                break;
                            }
                        }
                    }
                }

                // Clasificar usuario
                if (empty($foundInSources)) {
                    $missingUsers[] = $user;
                } elseif (count($foundInSources) === 1) {
                    $existingUsers[] = [
                        'ghl_user' => $user,
                        'baremetrics' => $foundInSources[0]
                    ];
                } else {
                    $duplicateUsers[] = [
                        'ghl_user' => $user,
                        'baremetrics' => $foundInSources
                    ];
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine();

            // 4. Mostrar resultados
            $this->info("📊 RESULTADOS DEL ANÁLISIS:");
            $this->info("   • Usuarios faltantes: " . count($missingUsers));
            $this->info("   • Usuarios existentes: " . count($existingUsers));
            $this->info("   • Usuarios duplicados: " . count($duplicateUsers));

            // 5. Mostrar usuarios faltantes
            if (!empty($missingUsers)) {
                $this->info("❌ USUARIOS FALTANTES EN BAREMETRICS:");
                foreach ($missingUsers as $user) {
                    $this->info("   • {$user['email']} - {$user['firstName']} {$user['lastName']}");
                    if (!$isDryRun) {
                        $this->info("     💡 Comando: php artisan baremetrics:complete-test-import {$user['email']}");
                    }
                }
            }

            // 6. Mostrar usuarios duplicados
            if (!empty($duplicateUsers)) {
                $this->warn("⚠️  USUARIOS DUPLICADOS:");
                foreach ($duplicateUsers as $duplicate) {
                    $user = $duplicate['ghl_user'];
                    $this->info("   • {$user['email']} - {$user['firstName']} {$user['lastName']}");
                    foreach ($duplicate['baremetrics'] as $bm) {
                        $this->info("     - {$bm['source_id']} ({$bm['provider']}) - {$bm['customer_oid']}");
                    }
                    if (!$isDryRun) {
                        $this->info("     💡 Comando: php artisan baremetrics:cleanup-duplicate-user {$user['email']}");
                    }
                }
            }

            // 7. Resumen final
            $this->newLine();
            $this->info("🎯 RESUMEN FINAL:");
            $this->info("   • Total usuarios analizados: " . count($ghlUsers));
            $this->info("   • Listos para importar: " . count($missingUsers));
            $this->info("   • Necesitan limpieza: " . count($duplicateUsers));
            $this->info("   • Ya están sincronizados: " . count($existingUsers));

        } catch (\Exception $e) {
            $this->error("❌ Error durante el análisis: " . $e->getMessage());
            Log::error('Error analizando usuarios faltantes', [
                'limit' => $limit,
                'tags' => $tags,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function getGHLUsers(GoHighLevelService $ghlService, int $limit, ?string $tags): array
    {
        // Por ahora, vamos a usar una lista de emails de prueba
        // En el futuro, esto debería obtener usuarios reales de GHL
        $testEmails = [
            'yuvianat.holisticcoach@gmail.com',
            'test1@example.com',
            'test2@example.com',
            'test3@example.com',
        ];

        $users = [];
        foreach (array_slice($testEmails, 0, $limit) as $email) {
            $response = $ghlService->getContacts($email);
            if (!empty($response['contacts'])) {
                $contact = $response['contacts'][0];
                
                // Filtrar por tags si se especifican
                if ($tags) {
                    $requiredTags = array_map('trim', explode(',', $tags));
                    $userTags = array_column($contact['tags'] ?? [], 'name');
                    
                    if (!array_intersect($requiredTags, $userTags)) {
                        continue; // Saltar usuario si no tiene los tags requeridos
                    }
                }
                
                $users[] = $contact;
            }
        }

        return $users;
    }
}
