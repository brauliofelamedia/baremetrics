<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use App\Services\BaremetricsService;
use App\Services\StripeService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestGHLAPI extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:test-api 
                           {email : Email del usuario a probar}
                           {--url= : URL base de la API (opcional)}
                           {--api-key= : API Key para autenticación (opcional)}
                           {--debug : Mostrar información de debugging}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prueba la API de GoHighLevel mejorada';

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
        $url = $this->option('url') ?? config('app.url');
        $apiKey = $this->option('api-key') ?? env('API_KEY');
        $debug = $this->option('debug');

        $this->info("🧪 Probando API de GoHighLevel mejorada para: {$email}");
        $this->newLine();

        // Probar directamente con los servicios
        $this->info('📋 Probando servicios directamente...');
        $this->testServicesDirectly($email, $debug);

        $this->newLine();

        // Probar la API HTTP
        $this->info('🌐 Probando API HTTP...');
        $this->testAPIHTTP($email, $url, $apiKey, $debug);

        return 0;
    }

    /**
     * Prueba los servicios directamente
     */
    private function testServicesDirectly($email, $debug = false)
    {
        try {
            // Buscar usuario en GoHighLevel
            $this->info('  🔍 Buscando usuario en GoHighLevel...');
            $ghl_customer = $this->ghlService->getContactsByExactEmail($email);
            
            if (empty($ghl_customer['contacts'])) {
                $this->info('  🔍 Intentando búsqueda con "contains"...');
                $ghl_customer = $this->ghlService->getContacts($email);
            }

            if (empty($ghl_customer['contacts'])) {
                $this->error('  ❌ Usuario no encontrado en GoHighLevel');
                return;
            }

            $contact = $ghl_customer['contacts'][0];
            $contactId = $contact['id'];
            $this->info("  ✅ Usuario encontrado (ID: {$contactId})");

            // Buscar en Stripe
            $this->info('  🔍 Buscando cliente en Stripe...');
            $stripeCustomer = $this->stripeService->searchCustomersByEmail($email);
            
            if (empty($stripeCustomer['data'])) {
                $this->error('  ❌ Cliente no encontrado en Stripe');
                return;
            }

            $stripe_id = $stripeCustomer['data'][0]['id'];
            $this->info("  ✅ Cliente encontrado en Stripe (ID: {$stripe_id})");

            // Obtener suscripción
            $this->info('  📋 Obteniendo suscripción más reciente...');
            $subscription = $this->ghlService->getSubscriptionStatusByContact($contactId);
            
            if ($subscription) {
                $this->info("  ✅ Suscripción obtenida (Estado: {$subscription['status']})");
                
                if ($debug) {
                    $this->line('  📊 Detalles de suscripción:');
                    $this->line('    • ID: ' . ($subscription['id'] ?? 'N/A'));
                    $this->line('    • Estado: ' . ($subscription['status'] ?? 'N/A'));
                    $this->line('    • Cupón: ' . ($subscription['couponCode'] ?? 'N/A'));
                    $this->line('    • Creada: ' . ($subscription['createdAt'] ?? 'N/A'));
                }
            } else {
                $this->warn('  ⚠️  No se encontró suscripción');
            }

            // Preparar datos
            $this->info('  🔧 Preparando datos para Baremetrics...');
            $customFields = collect($contact['customFields'] ?? []);
            
            $ghlData = [
                'relationship_status' => $customFields->firstWhere('id', '1fFJJsONHbRMQJCstvg1')['value'] ?? '-',
                'community_location' => $customFields->firstWhere('id', 'q3BHfdxzT2uKfNO3icXG')['value'] ?? '-',
                'country' => $contact['country'] ?? '-',
                'engagement_score' => $customFields->firstWhere('id', 'j175N7HO84AnJycpUb9D')['value'] ?? '-',
                'has_kids' => $customFields->firstWhere('id', 'xy0zfzMRFpOdXYJkHS2c')['value'] ?? '-',
                'state' => $contact['state'] ?? '-',
                'location' => $contact['city'] ?? '-',
                'zodiac_sign' => $customFields->firstWhere('id', 'JuiCbkHWsSc3iKfmOBpo')['value'] ?? '-',
                'subscriptions' => $subscription['status'] ?? 'none',
                'coupon_code' => $subscription['couponCode'] ?? null
            ];

            $this->info('  ✅ Datos preparados correctamente');

            if ($debug) {
                $this->line('  📊 Datos que se enviarían a Baremetrics:');
                foreach ($ghlData as $key => $value) {
                    if ($value !== '-' && $value !== null && $value !== '') {
                        $this->line("    • {$key}: {$value}");
                    }
                }
            }

        } catch (\Exception $e) {
            $this->error('  ❌ Error en prueba directa: ' . $e->getMessage());
            
            if ($debug) {
                $this->line('Stack trace:');
                $this->line($e->getTraceAsString());
            }
        }
    }

    /**
     * Prueba la API HTTP
     */
    private function testAPIHTTP($email, $url, $apiKey, $debug = false)
    {
        try {
            $apiUrl = rtrim($url, '/') . '/api/gohighlevel/contact/update';
            
            $this->info("  🌐 Enviando request a: {$apiUrl}");
            
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-API-Key' => $apiKey
            ])->post($apiUrl, [
                'email' => $email
            ]);

            $statusCode = $response->status();
            $responseData = $response->json();

            if ($statusCode === 200) {
                $this->info('  ✅ API respondió exitosamente');
                
                if (isset($responseData['success']) && $responseData['success']) {
                    $this->info('  ✅ Actualización exitosa');
                    
                    if (isset($responseData['data'])) {
                        $data = $responseData['data'];
                        $this->line("    • Email: {$data['email']}");
                        $this->line("    • Contact ID: {$data['contact_id']}");
                        $this->line("    • Stripe ID: {$data['stripe_id']}");
                        $this->line("    • Estado suscripción: {$data['subscription_status']}");
                        $this->line("    • Código cupón: " . ($data['coupon_code'] ?? 'N/A'));
                        
                        if (isset($data['updated_fields'])) {
                            $this->line("    • Campos actualizados: " . implode(', ', $data['updated_fields']));
                        }
                    }
                } else {
                    $this->error('  ❌ La API reportó error: ' . ($responseData['message'] ?? 'Error desconocido'));
                }
            } else {
                $this->error("  ❌ API respondió con código: {$statusCode}");
                
                if (isset($responseData['message'])) {
                    $this->error("    Mensaje: {$responseData['message']}");
                }
            }

            if ($debug) {
                $this->newLine();
                $this->info('🔍 RESPUESTA COMPLETA DE LA API:');
                $this->line(json_encode($responseData, JSON_PRETTY_PRINT));
            }

        } catch (\Exception $e) {
            $this->error('  ❌ Error en prueba HTTP: ' . $e->getMessage());
            
            if ($debug) {
                $this->line('Stack trace:');
                $this->line($e->getTraceAsString());
            }
        }
    }
}
