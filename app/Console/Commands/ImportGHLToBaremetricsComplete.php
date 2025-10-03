<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use App\Services\BaremetricsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ImportGHLToBaremetricsComplete extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:import-complete-to-baremetrics 
                           {--tags=* : Tags to filter GHL users (default: creetelo_mensual,creetelo_anual,créetelo_mensual,créetelo_anual)}
                           {--exclude-tags=* : Tags to exclude (default: unsubscribe)}
                           {--limit=100 : Maximum number of users to import}
                           {--batch-size=5 : Number of users to process in each batch}
                           {--dry-run : Show what would be imported without actually importing}
                           {--force : Force import even if not in sandbox mode}
                           {--skip-existing : Skip users that already exist in Baremetrics}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Complete import of GoHighLevel users to Baremetrics with real plans, subscriptions and renewal dates';

    protected $ghlService;
    protected $baremetricsService;
    protected $importedCount = 0;
    protected $errorCount = 0;
    protected $skippedCount = 0;
    protected $createdPlans = [];
    protected $createdCustomers = [];

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
        $this->info('🚀 IMPORTACIÓN COMPLETA GHL → BAREMETRICS');
        $this->info('=========================================');

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

        $this->info("📋 Configuración:");
        $this->line("   • Tags incluidos: " . implode(', ', $tags));
        $this->line("   • Tags excluidos: " . implode(', ', $excludeTags));
        $this->line("   • Límite: {$limit} usuarios");
        $this->line("   • Tamaño de lote: {$batchSize}");
        $this->line("   • Modo: " . ($dryRun ? 'DRY RUN' : 'IMPORTACIÓN REAL'));
        $this->line("   • Omitir existentes: " . ($this->option('skip-existing') ? 'Sí' : 'No'));
        $this->newLine();

        // Obtener usuarios de GHL
        $this->info('🔍 Obteniendo usuarios de GHL...');
        $ghlUsers = $this->getGHLUsersWithSubscriptions($tags, $excludeTags, $limit);
        
        if (empty($ghlUsers)) {
            $this->error('❌ No se encontraron usuarios de GHL para importar');
            return 1;
        }

        $this->info("✅ Se encontraron " . count($ghlUsers) . " usuarios de GHL");
        $this->newLine();

        if ($dryRun) {
            $this->showDryRunPreview($ghlUsers);
            return 0;
        }

        // Procesar usuarios por lotes
        $this->info('📦 Procesando usuarios por lotes...');
        $batches = array_chunk($ghlUsers, $batchSize);
        $progressBar = $this->output->createProgressBar(count($ghlUsers));
        $progressBar->start();

        foreach ($batches as $batchIndex => $batch) {
            $this->processBatch($batch, $progressBar);
            
            // Pausa entre lotes para evitar rate limiting
            if ($batchIndex < count($batches) - 1) {
                sleep(3);
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
     * Obtener usuarios de GHL con sus suscripciones reales
     */
    private function getGHLUsersWithSubscriptions(array $tags, array $excludeTags, int $limit): array
    {
        $cacheKey = 'ghl_users_complete_' . md5(implode(',', $tags) . implode(',', $excludeTags));
        
        return Cache::remember($cacheKey, now()->addHours(1), function () use ($tags, $excludeTags, $limit) {
            $allUsers = collect();
            $tagStatistics = [];

            foreach ($tags as $tag) {
                $this->line("   📄 Procesando tag: {$tag}");
                
                $contacts = [];
                $page = 1;
                $hasMore = true;
                $processedCount = 0;

                while ($hasMore && $page <= 100) {
                    $response = $this->ghlService->getContactsByTags([$tag], $page);
                    
                    if (!$response || empty($response['contacts'])) {
                        break;
                    }

                    // Enriquecer cada contacto con sus suscripciones
                    foreach ($response['contacts'] as $contact) {
                        $contact['subscriptions'] = $this->getUserSubscriptions($contact['id']);
                        $contacts[] = $contact;
                    }

                    $processedCount += count($response['contacts']);

                    if (isset($response['meta']['pagination'])) {
                        $hasMore = $response['meta']['pagination']['has_more'] ?? false;
                    } else {
                        $hasMore = false;
                    }
                    $page++;
                    usleep(200000); // 0.2 second delay
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
     * Obtener suscripciones reales de un usuario de GHL
     */
    private function getUserSubscriptions(string $contactId): array
    {
        try {
            // Intentar obtener suscripciones desde GHL
            $response = $this->ghlService->getContacts($contactId);
            
            if ($response && isset($response['subscriptions'])) {
                return $response['subscriptions'];
            }

            // Si no hay suscripciones directas, buscar en campos personalizados
            if (isset($response['customFields'])) {
                $subscriptions = [];
                foreach ($response['customFields'] as $field) {
                    if (strpos(strtolower($field['name']), 'subscription') !== false) {
                        $subscriptions[] = [
                            'field_name' => $field['name'],
                            'field_value' => $field['value'],
                            'source' => 'custom_field'
                        ];
                    }
                }
                return $subscriptions;
            }

            return [];
        } catch (\Exception $e) {
            Log::warning('Error obteniendo suscripciones de usuario GHL', [
                'contact_id' => $contactId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Procesar lote de usuarios
     */
    private function processBatch(array $batch, $progressBar): void
    {
        foreach ($batch as $user) {
            try {
                $result = $this->importUserComplete($user);
                
                if ($result === 'imported') {
                    $this->importedCount++;
                } elseif ($result === 'skipped') {
                    $this->skippedCount++;
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
     * Importar usuario completo con sus suscripciones reales
     */
    private function importUserComplete(array $user): string
    {
        try {
            $sourceId = $this->baremetricsService->getSourceId();
            if (!$sourceId) {
                return 'error';
            }

            // Verificar si el usuario ya existe
            if ($this->option('skip-existing') && $this->userExistsInBaremetrics($user['email'])) {
                return 'skipped';
            }

            // Crear cliente
            $customer = $this->createCustomer($user, $sourceId);
            if (!$customer) {
                return 'error';
            }

            // Procesar suscripciones reales
            $subscriptions = $user['subscriptions'] ?? [];
            $importedSubscriptions = 0;

            foreach ($subscriptions as $subscription) {
                $plan = $this->createOrGetPlan($subscription, $sourceId);
                if ($plan) {
                    $subscriptionResult = $this->createSubscription($customer, $plan, $subscription, $sourceId);
                    if ($subscriptionResult) {
                        $importedSubscriptions++;
                    }
                }
            }

            // Si no hay suscripciones reales, crear una basada en tags
            if ($importedSubscriptions === 0) {
                $plan = $this->createPlanFromTags($user['tags'] ?? [], $sourceId);
                if ($plan) {
                    $subscriptionResult = $this->createSubscriptionFromTags($customer, $plan, $user, $sourceId);
                    if ($subscriptionResult) {
                        $importedSubscriptions++;
                    }
                }
            }

            return $importedSubscriptions > 0 ? 'imported' : 'error';

        } catch (\Exception $e) {
            Log::error('Error importing complete user to Baremetrics', [
                'user' => $user,
                'error' => $e->getMessage()
            ]);
            return 'error';
        }
    }

    /**
     * Verificar si el usuario ya existe en Baremetrics
     */
    private function userExistsInBaremetrics(string $email): bool
    {
        // Por ahora, asumimos que no existe
        // En el futuro se puede implementar búsqueda por email
        return false;
    }

    /**
     * Crear cliente en Baremetrics
     */
    private function createCustomer(array $user, string $sourceId): ?array
    {
        $customerData = [
            'name' => $this->getUserName($user),
            'email' => $user['email'] ?? null,
            'company' => $this->getUserCompany($user),
            'notes' => $this->getUserNotes($user)
        ];

        $customer = $this->baremetricsService->createCustomer($customerData, $sourceId);
        if ($customer && isset($customer['customer']['oid'])) {
            $this->createdCustomers[$user['email']] = $customer['customer']['oid'];
        }

        return $customer;
    }

    /**
     * Crear o obtener plan basado en suscripción real de GHL
     */
    private function createOrGetPlan(array $subscription, string $sourceId): ?array
    {
        // Extraer información del plan de la suscripción
        $planName = $this->extractPlanName($subscription);
        $planAmount = $this->extractPlanAmount($subscription);
        $planInterval = $this->extractPlanInterval($subscription);

        if (!$planName || !$planAmount || !$planInterval) {
            return null;
        }

        // Usar una clave única basada solo en el plan, normalizando el nombre
        $normalizedName = str_replace(['CréeTelo', 'CréeTelo'], 'Creetelo', $planName);
        $planKey = $normalizedName . '_' . $planAmount . '_' . $planInterval;
        
        // Verificar si el plan ya existe
        if (isset($this->createdPlans[$planKey])) {
            $this->line("   🔄 Reutilizando plan existente: {$planName}");
            return $this->createdPlans[$planKey];
        }

        // Crear nuevo plan
        $planData = [
            'name' => $planName,
            'interval' => $planInterval,
            'interval_count' => 1,
            'amount' => $planAmount,
            'currency' => 'USD',
            'trial_days' => 0,
            'notes' => "Plan importado desde GHL - {$planName}"
        ];

        $this->line("   📋 Creando nuevo plan desde suscripción: {$planName} ($" . ($planAmount/100) . "/{$planInterval})");
        $plan = $this->baremetricsService->createPlan($planData, $sourceId);
        if ($plan) {
            $this->createdPlans[$planKey] = $plan;
        }

        return $plan;
    }

    /**
     * Crear plan basado en tags de GHL
     */
    private function createPlanFromTags(array $tags, string $sourceId): ?array
    {
        // Determinar el plan basado en los tags
        $planInfo = $this->getPlanInfoFromTags($tags);
        
        if (!$planInfo) {
            return null;
        }

        // Usar una clave única basada solo en el plan, normalizando el nombre
        $normalizedName = str_replace(['CréeTelo', 'CréeTelo'], 'Creetelo', $planInfo['name']);
        $planKey = $normalizedName . '_' . $planInfo['amount'] . '_' . $planInfo['interval'];
        
        // Verificar si el plan ya existe
        if (isset($this->createdPlans[$planKey])) {
            $this->line("   🔄 Reutilizando plan existente: {$planInfo['name']}");
            return $this->createdPlans[$planKey];
        }

        // Crear nuevo plan
        $planData = [
            'name' => $planInfo['name'],
            'interval' => $planInfo['interval'],
            'interval_count' => 1,
            'amount' => $planInfo['amount'],
            'currency' => 'USD',
            'trial_days' => 0,
            'notes' => "Plan creado desde tags GHL - " . implode(', ', $tags)
        ];

        $this->line("   📋 Creando nuevo plan: {$planInfo['name']} ($" . ($planInfo['amount']/100) . "/{$planInfo['interval']})");
        $plan = $this->baremetricsService->createPlan($planData, $sourceId);
        if ($plan) {
            $this->createdPlans[$planKey] = $plan;
        }

        return $plan;
    }

    /**
     * Crear suscripción basada en suscripción real de GHL
     */
    private function createSubscription(array $customer, array $plan, array $subscription, string $sourceId): ?array
    {
        $renewalDate = $this->extractRenewalDate($subscription);
        
        $subscriptionData = [
            'customer_oid' => $customer['customer']['oid'],
            'plan_oid' => $plan['plan']['oid'],
            'started_at' => $renewalDate,
            'status' => 'active',
            'notes' => 'Importado desde suscripción real de GHL'
        ];

        return $this->baremetricsService->createSubscription($subscriptionData, $sourceId);
    }

    /**
     * Crear suscripción basada en tags de GHL
     */
    private function createSubscriptionFromTags(array $customer, array $plan, array $user, string $sourceId): ?array
    {
        $renewalDate = $this->getUserRenewalDate($user);
        
        $subscriptionData = [
            'customer_oid' => $customer['customer']['oid'],
            'plan_oid' => $plan['plan']['oid'],
            'started_at' => $renewalDate,
            'status' => 'active',
            'notes' => 'Importado desde tags de GHL'
        ];

        return $this->baremetricsService->createSubscription($subscriptionData, $sourceId);
    }

    /**
     * Extraer nombre del plan de la suscripción
     */
    private function extractPlanName(array $subscription): ?string
    {
        // Buscar en diferentes campos
        $fields = ['name', 'product_name', 'plan_name', 'field_name'];
        
        foreach ($fields as $field) {
            if (isset($subscription[$field]) && !empty($subscription[$field])) {
                return $subscription[$field];
            }
        }

        // Si viene de campo personalizado
        if (isset($subscription['field_name'])) {
            return $subscription['field_name'];
        }

        return null;
    }

    /**
     * Extraer monto del plan de la suscripción
     */
    private function extractPlanAmount(array $subscription): ?int
    {
        // Buscar en diferentes campos
        $fields = ['amount', 'price', 'cost', 'field_value'];
        
        foreach ($fields as $field) {
            if (isset($subscription[$field]) && is_numeric($subscription[$field])) {
                return (int) $subscription[$field];
            }
        }

        // Default basado en el nombre del plan
        $planName = $this->extractPlanName($subscription);
        if ($planName) {
            if (strpos(strtolower($planName), 'anual') !== false) {
                return 29700; // $297 anual
            } elseif (strpos(strtolower($planName), 'mensual') !== false) {
                return 3900; // $39 mensual
            }
        }

        return 2999; // Default $29.99
    }

    /**
     * Extraer intervalo del plan de la suscripción
     */
    private function extractPlanInterval(array $subscription): ?string
    {
        $planName = $this->extractPlanName($subscription);
        
        if ($planName) {
            if (strpos(strtolower($planName), 'anual') !== false) {
                return 'year';
            } elseif (strpos(strtolower($planName), 'mensual') !== false) {
                return 'month';
            } elseif (strpos(strtolower($planName), 'semanal') !== false) {
                return 'week';
            } elseif (strpos(strtolower($planName), 'diario') !== false) {
                return 'day';
            }
        }

        return 'month'; // Default
    }

    /**
     * Extraer fecha de renovación de la suscripción
     */
    private function extractRenewalDate(array $subscription): int
    {
        // Buscar en diferentes campos
        $fields = ['renewal_date', 'next_billing', 'end_date', 'expiration_date', 'field_value'];
        
        foreach ($fields as $field) {
            if (isset($subscription[$field]) && !empty($subscription[$field])) {
                if (is_numeric($subscription[$field])) {
                    $timestamp = (int) $subscription[$field];
                    // Si la fecha es futura, usar fecha actual para que la suscripción esté activa
                    return $timestamp > now()->timestamp ? now()->timestamp : $timestamp;
                } elseif (is_string($subscription[$field])) {
                    $timestamp = strtotime($subscription[$field]);
                    if ($timestamp !== false) {
                        // Si la fecha es futura, usar fecha actual para que la suscripción esté activa
                        return $timestamp > now()->timestamp ? now()->timestamp : $timestamp;
                    }
                }
            }
        }

        // Para suscripciones activas, usar fecha actual
        return now()->timestamp;
    }

    /**
     * Obtener información del plan basada en tags
     */
    private function getPlanInfoFromTags(array $tags): ?array
    {
        foreach ($tags as $tag) {
            if (strpos($tag, 'creetelo_anual') !== false || strpos($tag, 'créetelo_anual') !== false) {
                return [
                    'name' => 'CréeTelo Anual',
                    'amount' => 29700, // $297
                    'interval' => 'year'
                ];
            } elseif (strpos($tag, 'creetelo_mensual') !== false || strpos($tag, 'créetelo_mensual') !== false) {
                return [
                    'name' => 'CréeTelo Mensual',
                    'amount' => 3900, // $39
                    'interval' => 'month'
                ];
            }
        }

        return null;
    }

    /**
     * Obtener fecha de renovación del usuario
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
                        $timestamp = (int)$fieldValue;
                        // Si la fecha es futura, usar fecha actual para que la suscripción esté activa
                        return $timestamp > now()->timestamp ? now()->timestamp : $timestamp;
                    } elseif (is_string($fieldValue) && !empty($fieldValue)) {
                        $timestamp = strtotime($fieldValue);
                        if ($timestamp !== false) {
                            // Si la fecha es futura, usar fecha actual para que la suscripción esté activa
                            return $timestamp > now()->timestamp ? now()->timestamp : $timestamp;
                        }
                    }
                }
            }
        }
        
        // Para suscripciones activas, usar fecha actual para que aparezcan como activas
        // Las fechas futuras hacen que los clientes aparezcan como "inactive"
        return now()->timestamp;
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

        if (!empty($user['subscriptions'])) {
            $notes[] = 'Suscripciones: ' . count($user['subscriptions']);
        }
        
        return implode(' | ', $notes);
    }

    /**
     * Mostrar vista previa del dry run
     */
    private function showDryRunPreview(array $users): void
    {
        $this->info('🔍 VISTA PREVIA DE IMPORTACIÓN COMPLETA (DRY RUN)');
        $this->info('================================================');
        
        $this->table(
            ['#', 'Nombre', 'Email', 'Tags', 'Suscripciones'],
            array_map(function ($user, $index) {
                $subscriptions = $user['subscriptions'] ?? [];
                return [
                    $index + 1,
                    $this->getUserName($user),
                    $user['email'] ?? 'N/A',
                    implode(', ', array_slice($user['tags'] ?? [], 0, 2)),
                    count($subscriptions)
                ];
            }, array_slice($users, 0, 10), array_keys(array_slice($users, 0, 10)))
        );
        
        if (count($users) > 10) {
            $this->line("... y " . (count($users) - 10) . " usuarios más");
        }
        
        $this->newLine();
        $this->info("📊 Resumen:");
        $this->line("   • Total usuarios: " . count($users));
        $this->line("   • Planes a crear: Dinámicos basados en suscripciones reales");
        $this->line("   • Fechas de renovación: Reales de GHL o calculadas por tag");
        $this->line("   • Suscripciones: Reales de GHL o basadas en tags");
        $this->newLine();
        $this->warn('⚠️  Esta es solo una vista previa. Para importar realmente, ejecuta sin --dry-run');
    }

    /**
     * Mostrar resumen de importación
     */
    private function showImportSummary(): void
    {
        $this->info('📊 RESUMEN DE IMPORTACIÓN COMPLETA');
        $this->info('==================================');
        $this->line("✅ Usuarios importados: {$this->importedCount}");
        $this->line("❌ Errores: {$this->errorCount}");
        $this->line("⏭️  Omitidos: {$this->skippedCount}");
        $this->line("📋 Planes creados: " . count($this->createdPlans));
        $this->line("👤 Clientes creados: " . count($this->createdCustomers));
        
        $total = $this->importedCount + $this->errorCount + $this->skippedCount;
        $successRate = $total > 0 ? round(($this->importedCount / $total) * 100, 2) : 0;
        
        $this->line("📈 Tasa de éxito: {$successRate}%");
        $this->newLine();
        
        if (!empty($this->createdPlans)) {
            $this->info("📋 Planes creados:");
            foreach ($this->createdPlans as $planKey => $plan) {
                $this->line("   • {$plan['plan']['name']} ({$plan['plan']['oid']})");
            }
            $this->newLine();
        }
        
        if ($this->errorCount > 0) {
            $this->warn("⚠️  Se encontraron errores durante la importación. Revisa los logs para más detalles.");
        }
        
        if ($this->importedCount > 0) {
            $this->info("🎉 ¡Importación completa exitosa! Los usuarios están disponibles en Baremetrics (modo sandbox).");
        }
    }
}

