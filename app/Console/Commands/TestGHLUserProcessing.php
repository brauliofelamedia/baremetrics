<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use App\Services\BaremetricsService;
use App\Services\StripeService;
use Illuminate\Support\Facades\Log;

class TestGHLUserProcessing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:test-processing 
                           {email : Email del usuario a probar}
                           {--dry-run : Ejecutar sin hacer cambios reales}
                           {--debug : Mostrar información de debugging}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prueba el procesamiento de un usuario específico de GoHighLevel';

    protected $ghlService;
    protected $baremetricsService;
    protected $stripeService;

    public function __construct(
        GoHighLevelService $ghlService,
        BaremetricsService $baremetricsService,
        StripeService $stripeService
    ) {
        parent::__construct();
        $this->ghlService = $ghlService;
        $this->baremetricsService = $baremetricsService;
        $this->stripeService = $stripeService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $isDryRun = $this->option('dry-run');

        $this->info("🧪 Probando procesamiento para: {$email}");
        
        if ($isDryRun) {
            $this->warn('⚠️  MODO DRY-RUN: No se realizarán cambios reales');
        }

        try {
            // Buscar usuario en Baremetrics
            $this->info('🔍 Buscando usuario en Baremetrics...');
            $baremetricsUser = $this->findUserInBaremetrics($email);
            
            if (!$baremetricsUser) {
                $this->error("❌ Usuario no encontrado en Baremetrics: {$email}");
                return 1;
            }

            $this->info("✅ Usuario encontrado en Baremetrics (ID: {$baremetricsUser['oid']})");

            // Buscar usuario en GoHighLevel
            $this->info('🔍 Buscando usuario en GoHighLevel...');
            
            // Primero intentar búsqueda exacta
            $this->info('  🔍 Intentando búsqueda exacta...');
            $ghlCustomer = $this->ghlService->getContactsByExactEmail($email);
            
            // Si no se encuentra con búsqueda exacta, intentar con contains
            if (empty($ghlCustomer['contacts'])) {
                $this->info('  🔍 Intentando búsqueda con "contains"...');
                $ghlCustomer = $this->ghlService->getContacts($email);
                
                // Debug: mostrar respuesta de la segunda búsqueda
                if ($this->option('debug')) {
                    $this->info('📋 Respuesta de GoHighLevel (búsqueda con contains):');
                    $this->line(json_encode($ghlCustomer, JSON_PRETTY_PRINT));
                }
            }
            
            // Debug: mostrar respuesta completa si se solicita
            if ($this->option('debug')) {
                $this->info('📋 Respuesta de GoHighLevel (búsqueda exacta):');
                $this->line(json_encode($ghlCustomer, JSON_PRETTY_PRINT));
            }
            
            if (empty($ghlCustomer['contacts'])) {
                $this->error("❌ Usuario no encontrado en GoHighLevel: {$email}");
                $this->warn("💡 Verifica que el email esté correcto y que el usuario exista en GoHighLevel");
                $this->warn("💡 También verifica que el token de GHL sea válido ejecutando: php artisan ghl:check-config");
                return 1;
            }

            $contact = $ghlCustomer['contacts'][0];
            $this->info("✅ Usuario encontrado en GoHighLevel (ID: {$contact['id']})");

            // Mostrar datos del usuario
            $this->displayUserData($contact, $baremetricsUser);

            // Procesar datos
            $this->info('🔄 Procesando datos...');
            $ghlData = $this->extractGHLData($contact);
            
            $this->displayGHLData($ghlData);

            if ($isDryRun) {
                $this->warn('🔍 DRY-RUN: Datos que se actualizarían:');
                $this->displayUpdatePreview($baremetricsUser['oid'], $ghlData);
            } else {
                // Actualizar en Baremetrics
                $this->info('📝 Actualizando en Baremetrics...');
                $result = $this->baremetricsService->updateCustomerAttributes($baremetricsUser['oid'], $ghlData);
                
                if ($result) {
                    $this->info('✅ Actualización exitosa en Baremetrics');
                } else {
                    $this->error('❌ Error al actualizar en Baremetrics');
                    return 1;
                }
            }

            $this->info('✅ Prueba completada exitosamente');

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            Log::error('Error en prueba de procesamiento GHL', [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }

    /**
     * Busca un usuario en Baremetrics por email
     */
    private function findUserInBaremetrics($email)
    {
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

        // Buscar en cada fuente
        foreach ($sourceIds as $sourceId) {
            $response = $this->baremetricsService->getCustomers($sourceId, $email, 0);
            
            if ($response && isset($response['customers']) && !empty($response['customers'])) {
                foreach ($response['customers'] as $customer) {
                    if (strtolower($customer['email'] ?? '') === strtolower($email)) {
                        return $customer;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Extrae datos de GoHighLevel del contacto
     */
    private function extractGHLData($contact)
    {
        $customFields = collect($contact['customFields'] ?? []);
        
        // Obtener datos de suscripción más reciente
        $subscription = $this->ghlService->getSubscriptionStatusByContact($contact['id']);
        $couponCode = $subscription['couponCode'] ?? null;
        $subscriptionStatus = $subscription['status'] ?? 'none';
        
        // Log para debugging
        if ($this->option('debug')) {
            $this->info('📋 Datos de suscripción obtenidos:');
            $this->line(json_encode([
                'subscription_id' => $subscription['id'] ?? 'N/A',
                'status' => $subscriptionStatus,
                'coupon_code' => $couponCode,
                'created_at' => $subscription['createdAt'] ?? 'N/A'
            ], JSON_PRETTY_PRINT));
        }

        return [
            'relationship_status' => $customFields->firstWhere('id', '1fFJJsONHbRMQJCstvg1')['value'] ?? '-',
            'community_location' => $customFields->firstWhere('id', 'q3BHfdxzT2uKfNO3icXG')['value'] ?? '-',
            'country' => $contact['country'] ?? '-',
            'engagement_score' => $customFields->firstWhere('id', 'j175N7HO84AnJycpUb9D')['value'] ?? '-',
            'has_kids' => $customFields->firstWhere('id', 'xy0zfzMRFpOdXYJkHS2c')['value'] ?? '-',
            'state' => $contact['state'] ?? '-',
            'location' => $contact['city'] ?? '-',
            'zodiac_sign' => $customFields->firstWhere('id', 'JuiCbkHWsSc3iKfmOBpo')['value'] ?? '-',
            'subscriptions' => $subscriptionStatus,
            'coupon_code' => $couponCode
        ];
    }

    /**
     * Muestra los datos del usuario
     */
    private function displayUserData($contact, $baremetricsUser)
    {
        $this->newLine();
        $this->info('📋 DATOS DEL USUARIO');
        $this->info('==================');
        
        $this->table(
            ['Campo', 'Valor'],
            [
                ['Email', $contact['email'] ?? 'N/A'],
                ['Nombre', $contact['name'] ?? 'N/A'],
                ['Teléfono', $contact['phone'] ?? 'N/A'],
                ['País', $contact['country'] ?? 'N/A'],
                ['Estado', $contact['state'] ?? 'N/A'],
                ['Ciudad', $contact['city'] ?? 'N/A'],
                ['ID GHL', $contact['id']],
                ['ID Baremetrics', $baremetricsUser['oid']],
            ]
        );
    }

    /**
     * Muestra los datos extraídos de GHL
     */
    private function displayGHLData($ghlData)
    {
        $this->newLine();
        $this->info('📊 DATOS EXTRAÍDOS DE GHL');
        $this->info('=========================');
        
        $rows = [];
        foreach ($ghlData as $key => $value) {
            $rows[] = [
                ucwords(str_replace('_', ' ', $key)),
                $value ?? 'N/A'
            ];
        }
        
        $this->table(['Campo', 'Valor'], $rows);
    }

    /**
     * Muestra una vista previa de la actualización
     */
    private function displayUpdatePreview($customerOid, $ghlData)
    {
        $this->newLine();
        $this->info('🔍 VISTA PREVIA DE ACTUALIZACIÓN');
        $this->info('================================');
        
        $this->line("Customer OID: {$customerOid}");
        $this->line("Campos a actualizar:");
        
        foreach ($ghlData as $key => $value) {
            if ($value && $value !== '-') {
                $this->line("  • {$key}: {$value}");
            }
        }
    }
}
