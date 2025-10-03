<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use App\Services\BaremetricsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ImportGHLUsersToBaremetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:import-to-baremetrics 
                           {--tags=* : Tags to filter GHL users (default: creetelo_mensual,creetelo_anual,créetelo_mensual,créetelo_anual)}
                           {--exclude-tags=* : Tags to exclude (default: unsubscribe)}
                           {--limit=100 : Maximum number of users to import}
                           {--batch-size=10 : Number of users to process in each batch}
                           {--dry-run : Show what would be imported without actually importing}
                           {--force : Force import even if not in sandbox mode}
                           {--plan-name= : Custom plan name (default: GHL Import Plan)}
                           {--plan-amount=2999 : Plan amount in cents (default: $29.99)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import GoHighLevel users to Baremetrics with automatic plan and subscription creation';

    protected $ghlService;
    protected $baremetricsService;
    protected $importedCount = 0;
    protected $errorCount = 0;
    protected $skippedCount = 0;

    public function __construct(GoHighLevelService $ghlService, BaremetricsService $baremetricsService)
    {
        parent::__construct();
        $this->ghlService = $ghlService;
        $this->baremetricsService = $baremetricsService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 IMPORTANDO USUARIOS DE GHL A BAREMETRICS');
        $this->info('==========================================');

        // Verificar modo sandbox
        if (!$this->verifySandboxMode()) {
            return 1;
        }

        // Obtener parámetros
        $tags = $this->option('tags') ?: ['creetelo_mensual', 'creetelo_anual', 'créetelo_mensual', 'créetelo_anual'];
        $excludeTags = $this->option('exclude-tags') ?: ['unsubscribe'];
        $limit = (int) $this->option('limit');
        $batchSize = (int) $this->option('batch-size');
        $dryRun = $this->option('dry-run');
        $planName = $this->option('plan-name') ?: 'GHL Import Plan';
        $planAmount = (int) $this->option('plan-amount');

        $this->info("📋 Configuración:");
        $this->line("   • Tags incluidos: " . implode(', ', $tags));
        $this->line("   • Tags excluidos: " . implode(', ', $excludeTags));
        $this->line("   • Límite: {$limit} usuarios");
        $this->line("   • Tamaño de lote: {$batchSize}");
        $this->line("   • Modo: " . ($dryRun ? 'DRY RUN' : 'IMPORTACIÓN REAL'));
        $this->line("   • Plan: {$planName} (\$" . ($planAmount/100) . ")");
        $this->newLine();

        // Obtener usuarios de GHL
        $this->info('🔍 Obteniendo usuarios de GHL...');
        $ghlUsers = $this->getGHLUsers($tags, $excludeTags, $limit);
        
        if (empty($ghlUsers)) {
            $this->error('❌ No se encontraron usuarios de GHL para importar');
            return 1;
        }

        $this->info("✅ Se encontraron " . count($ghlUsers) . " usuarios de GHL");
        $this->newLine();

        if ($dryRun) {
            $this->showDryRunPreview($ghlUsers, $planName, $planAmount);
            return 0;
        }

        // Crear planes por tag en Baremetrics
        $this->info('📋 Creando planes en Baremetrics...');
        $plans = $this->createBaremetricsPlansByTags($tags, $planAmount);
        
        if (empty($plans)) {
            $this->error('❌ Error al crear los planes en Baremetrics');
            return 1;
        }

        foreach ($plans as $tag => $plan) {
            $this->info("✅ Plan creado para {$tag}: {$plan['plan']['oid']}");
        }
        $this->newLine();

        // Procesar usuarios por lotes
        $this->info('📦 Procesando usuarios por lotes...');
        $batches = array_chunk($ghlUsers, $batchSize);
        $progressBar = $this->output->createProgressBar(count($ghlUsers));
        $progressBar->start();

        foreach ($batches as $batchIndex => $batch) {
            $this->processBatch($batch, $plans, $progressBar);
            
            // Pausa entre lotes para evitar rate limiting
            if ($batchIndex < count($batches) - 1) {
                sleep(2);
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        // Mostrar resumen
        $this->showImportSummary();

        return 0;
    }

    /**
     * Verificar que Baremetrics esté en modo sandbox
     */
    private function verifySandboxMode(): bool
    {
        $environment = config('services.baremetrics.environment', 'sandbox');
        
        if ($environment !== 'sandbox' && !$this->option('force')) {
            $this->error('❌ Baremetrics no está en modo sandbox');
            $this->line('   Para importar en producción, usa --force');
            $this->line("   Entorno actual: {$environment}");
            return false;
        }

        $this->info("✅ Modo sandbox confirmado: {$environment}");
        return true;
    }

    /**
     * Obtener usuarios de GHL filtrados por tags
     */
    private function getGHLUsers(array $tags, array $excludeTags, int $limit): array
    {
        $cacheKey = 'ghl_users_for_baremetrics_' . md5(implode(',', $tags) . implode(',', $excludeTags));
        
        return Cache::remember($cacheKey, now()->addHours(1), function () use ($tags, $excludeTags, $limit) {
            $allUsers = collect();
            $tagStatistics = [];

            foreach ($tags as $tag) {
                $this->line("   📄 Procesando tag: {$tag}");
                
                $contacts = [];
                $page = 1;
                $hasMore = true;
                $processedCount = 0;

                while ($hasMore && $page <= 100) { // Máximo 100 páginas por tag
                    $response = $this->ghlService->getContactsByTags([$tag], $page);
                    
                    if (!$response || empty($response['contacts'])) {
                        break;
                    }

                    $contacts = array_merge($contacts, $response['contacts']);
                    $processedCount += count($response['contacts']);

                    if (isset($response['meta']['pagination'])) {
                        $hasMore = $response['meta']['pagination']['has_more'] ?? false;
                    } else {
                        $hasMore = false;
                    }
                    $page++;
                    usleep(100000); // 0.1 second delay
                }

                foreach ($contacts as $user) {
                    $allUsers->put($user['id'], $user); // Deduplicate by ID
                }

                $tagStatistics[$tag] = count($contacts);
                $this->line("     • {$tag}: " . count($contacts) . " usuarios");
            }

            // Aplicar filtro de exclusión
            if (!empty($excludeTags)) {
                $allUsers = $allUsers->filter(function ($user) use ($excludeTags) {
                    $userTags = $user['tags'] ?? [];
                    return empty(array_intersect($excludeTags, $userTags));
                });
            }

            return $allUsers->take($limit)->values()->all();
        });
    }

    /**
     * Crear planes por tag en Baremetrics
     */
    private function createBaremetricsPlansByTags(array $tags, int $planAmount): array
    {
        $sourceId = $this->baremetricsService->getSourceId();
        if (!$sourceId) {
            $this->error('❌ No se pudo obtener el Source ID de Baremetrics');
            return [];
        }

        $plans = [];
        
        foreach ($tags as $tag) {
            // Determinar el intervalo basado en el tag
            $interval = $this->getIntervalFromTag($tag);
            $intervalCount = $this->getIntervalCountFromTag($tag);
            $planName = $this->getPlanNameFromTag($tag);
            
            $planData = [
                'name' => $planName,
                'interval' => $interval,
                'interval_count' => $intervalCount,
                'amount' => $planAmount,
                'currency' => 'USD',
                'trial_days' => 7,
                'notes' => "Plan creado automáticamente para importación de GHL - Tag: {$tag}"
            ];

            $plan = $this->baremetricsService->createPlan($planData, $sourceId);
            if ($plan) {
                $plans[$tag] = $plan;
            }
        }

        return $plans;
    }

    /**
     * Obtener intervalo basado en el tag
     */
    private function getIntervalFromTag(string $tag): string
    {
        if (strpos($tag, 'mensual') !== false) {
            return 'month';
        } elseif (strpos($tag, 'anual') !== false) {
            return 'year';
        } elseif (strpos($tag, 'semanal') !== false) {
            return 'week';
        } elseif (strpos($tag, 'diario') !== false) {
            return 'day';
        }
        
        return 'month'; // Default
    }

    /**
     * Obtener conteo de intervalo basado en el tag
     */
    private function getIntervalCountFromTag(string $tag): int
    {
        if (strpos($tag, 'mensual') !== false) {
            return 1;
        } elseif (strpos($tag, 'anual') !== false) {
            return 1;
        } elseif (strpos($tag, 'semanal') !== false) {
            return 1;
        } elseif (strpos($tag, 'diario') !== false) {
            return 1;
        }
        
        return 1; // Default
    }

    /**
     * Obtener nombre del plan basado en el tag
     */
    private function getPlanNameFromTag(string $tag): string
    {
        $tagMap = [
            'creetelo_mensual' => 'CréeTelo Mensual',
            'creetelo_anual' => 'CréeTelo Anual',
            'créetelo_mensual' => 'CréeTelo Mensual',
            'créetelo_anual' => 'CréeTelo Anual',
        ];

        return $tagMap[$tag] ?? ucfirst(str_replace('_', ' ', $tag));
    }

    /**
     * Procesar lote de usuarios
     */
    private function processBatch(array $batch, array $plans, $progressBar): void
    {
        foreach ($batch as $user) {
            try {
                $result = $this->importUserToBaremetrics($user, $plans);
                
                if ($result) {
                    $this->importedCount++;
                } else {
                    $this->errorCount++;
                }
                
            } catch (\Exception $e) {
                $this->errorCount++;
                Log::error('Error importing user to Baremetrics', [
                    'user_id' => $user['id'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
            
            $progressBar->advance();
        }
    }

    /**
     * Importar usuario individual a Baremetrics
     */
    private function importUserToBaremetrics(array $user, array $plans): bool
    {
        try {
            $sourceId = $this->baremetricsService->getSourceId();
            if (!$sourceId) {
                return false;
            }

            // Determinar el plan correcto basado en los tags del usuario
            $userTags = $user['tags'] ?? [];
            $planOid = $this->getPlanOidForUser($userTags, $plans);
            
            if (!$planOid) {
                Log::warning('No se encontró plan para usuario', [
                    'user_id' => $user['id'] ?? 'unknown',
                    'user_tags' => $userTags,
                    'available_plans' => array_keys($plans)
                ]);
                return false;
            }

            // Crear cliente
            $customerData = [
                'name' => $this->getUserName($user),
                'email' => $user['email'] ?? null,
                'company' => $this->getUserCompany($user),
                'notes' => $this->getUserNotes($user)
            ];

            $customer = $this->baremetricsService->createCustomer($customerData, $sourceId);
            if (!$customer || !isset($customer['customer']['oid'])) {
                return false;
            }

            // Obtener fecha de renovación real de GHL
            $renewalDate = $this->getUserRenewalDate($user);

            // Crear suscripción con fecha real
            $subscriptionData = [
                'customer_oid' => $customer['customer']['oid'],
                'plan_oid' => $planOid,
                'started_at' => $renewalDate,
                'status' => 'active',
                'notes' => 'Importado desde GoHighLevel con fecha real de renovación'
            ];

            $subscription = $this->baremetricsService->createSubscription($subscriptionData, $sourceId);
            return $subscription !== null;

        } catch (\Exception $e) {
            Log::error('Error importing user to Baremetrics', [
                'user' => $user,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtener nombre del usuario
     */
    private function getUserName(array $user): string
    {
        if (!empty($user['firstName']) && !empty($user['lastName'])) {
            return trim($user['firstName'] . ' ' . $user['lastName']);
        }
        
        if (!empty($user['name'])) {
            return $user['name'];
        }
        
        if (!empty($user['email'])) {
            return explode('@', $user['email'])[0];
        }
        
        return 'Usuario GHL ' . ($user['id'] ?? 'Desconocido');
    }

    /**
     * Obtener empresa del usuario
     */
    private function getUserCompany(array $user): ?string
    {
        // Buscar en campos personalizados
        if (isset($user['customFields']) && is_array($user['customFields'])) {
            foreach ($user['customFields'] as $field) {
                $fieldName = strtolower($field['name'] ?? '');
                if (in_array($fieldName, ['company', 'empresa', 'business', 'negocio'])) {
                    return $field['value'] ?? null;
                }
            }
        }
        
        return null;
    }

    /**
     * Obtener notas del usuario
     */
    private function getUserNotes(array $user): string
    {
        $notes = [];
        
        if (!empty($user['tags'])) {
            $notes[] = 'Tags GHL: ' . implode(', ', $user['tags']);
        }
        
        if (!empty($user['phone'])) {
            $notes[] = 'Teléfono: ' . $user['phone'];
        }
        
        if (!empty($user['source'])) {
            $notes[] = 'Fuente: ' . $user['source'];
        }
        
        return implode(' | ', $notes);
    }

    /**
     * Obtener OID del plan para el usuario basado en sus tags
     */
    private function getPlanOidForUser(array $userTags, array $plans): ?string
    {
        // Priorizar tags en orden específico
        $priorityTags = ['creetelo_anual', 'créetelo_anual', 'creetelo_mensual', 'créetelo_mensual'];
        
        foreach ($priorityTags as $tag) {
            if (in_array($tag, $userTags) && isset($plans[$tag])) {
                return $plans[$tag]['plan']['oid'];
            }
        }
        
        // Si no encuentra un tag prioritario, buscar cualquier tag disponible
        foreach ($userTags as $tag) {
            if (isset($plans[$tag])) {
                return $plans[$tag]['plan']['oid'];
            }
        }
        
        return null;
    }

    /**
     * Obtener fecha de renovación real del usuario de GHL
     */
    private function getUserRenewalDate(array $user): int
    {
        // Buscar fecha de renovación en campos personalizados
        if (isset($user['customFields']) && is_array($user['customFields'])) {
            foreach ($user['customFields'] as $field) {
                $fieldName = strtolower($field['name'] ?? '');
                $fieldValue = $field['value'] ?? '';
                
                // Buscar campos relacionados con fechas de renovación
                if (in_array($fieldName, ['renewal_date', 'fecha_renovacion', 'next_billing', 'proximo_pago', 'expiration_date', 'fecha_expiracion'])) {
                    if (is_numeric($fieldValue)) {
                        return (int)$fieldValue;
                    } elseif (is_string($fieldValue) && !empty($fieldValue)) {
                        $timestamp = strtotime($fieldValue);
                        if ($timestamp !== false) {
                            return $timestamp;
                        }
                    }
                }
            }
        }
        
        // Buscar en otros campos del usuario
        $dateFields = ['renewalDate', 'nextBillingDate', 'expirationDate', 'subscriptionEnd'];
        foreach ($dateFields as $field) {
            if (isset($user[$field]) && !empty($user[$field])) {
                if (is_numeric($user[$field])) {
                    return (int)$user[$field];
                } elseif (is_string($user[$field])) {
                    $timestamp = strtotime($user[$field]);
                    if ($timestamp !== false) {
                        return $timestamp;
                    }
                }
            }
        }
        
        // Si no se encuentra fecha específica, calcular basado en el tag
        $userTags = $user['tags'] ?? [];
        $baseDate = now()->timestamp;
        
        foreach ($userTags as $tag) {
            if (strpos($tag, 'anual') !== false) {
                // Si es anual, agregar 1 año
                return strtotime('+1 year', $baseDate);
            } elseif (strpos($tag, 'mensual') !== false) {
                // Si es mensual, agregar 1 mes
                return strtotime('+1 month', $baseDate);
            } elseif (strpos($tag, 'semanal') !== false) {
                // Si es semanal, agregar 1 semana
                return strtotime('+1 week', $baseDate);
            }
        }
        
        // Default: 1 mes desde ahora
        return strtotime('+1 month', $baseDate);
    }

    /**
     * Mostrar vista previa del dry run
     */
    private function showDryRunPreview(array $users, string $planName, int $planAmount): void
    {
        $this->info('🔍 VISTA PREVIA DE IMPORTACIÓN (DRY RUN)');
        $this->info('=====================================');
        
        $this->table(
            ['#', 'Nombre', 'Email', 'Empresa', 'Tags'],
            array_map(function ($user, $index) {
                return [
                    $index + 1,
                    $this->getUserName($user),
                    $user['email'] ?? 'N/A',
                    $this->getUserCompany($user) ?? 'N/A',
                    implode(', ', array_slice($user['tags'] ?? [], 0, 3))
                ];
            }, array_slice($users, 0, 10), array_keys(array_slice($users, 0, 10)))
        );
        
        if (count($users) > 10) {
            $this->line("... y " . (count($users) - 10) . " usuarios más");
        }
        
        $this->newLine();
        $this->info("📊 Resumen:");
        $this->line("   • Total usuarios: " . count($users));
        $this->line("   • Planes a crear: " . count($this->option('tags') ?: ['creetelo_mensual', 'creetelo_anual', 'créetelo_mensual', 'créetelo_anual']));
        $this->line("   • Monto por plan: \$" . ($planAmount/100));
        $this->line("   • Suscripciones a crear: " . count($users));
        $this->line("   • Fechas de renovación: Reales de GHL o calculadas por tag");
        $this->newLine();
        $this->warn('⚠️  Esta es solo una vista previa. Para importar realmente, ejecuta sin --dry-run');
    }

    /**
     * Mostrar resumen de importación
     */
    private function showImportSummary(): void
    {
        $this->info('📊 RESUMEN DE IMPORTACIÓN');
        $this->info('========================');
        $this->line("✅ Usuarios importados: {$this->importedCount}");
        $this->line("❌ Errores: {$this->errorCount}");
        $this->line("⏭️  Omitidos: {$this->skippedCount}");
        
        $total = $this->importedCount + $this->errorCount + $this->skippedCount;
        $successRate = $total > 0 ? round(($this->importedCount / $total) * 100, 2) : 0;
        
        $this->line("📈 Tasa de éxito: {$successRate}%");
        $this->newLine();
        
        if ($this->errorCount > 0) {
            $this->warn("⚠️  Se encontraron errores durante la importación. Revisa los logs para más detalles.");
        }
        
        if ($this->importedCount > 0) {
            $this->info("🎉 ¡Importación completada! Los usuarios están disponibles en Baremetrics (modo sandbox).");
        }
    }
}
