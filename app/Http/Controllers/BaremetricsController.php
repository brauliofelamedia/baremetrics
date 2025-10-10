<?php

namespace App\Http\Controllers;

use App\Services\BaremetricsService;
use App\Services\GoHighLevelService;
use Illuminate\Http\Request;
use App\Models\MissingUser;
use Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class BaremetricsController extends Controller
{
    private BaremetricsService $baremetricsService;
    private GoHighLevelService $ghlService;

    public function __construct(BaremetricsService $baremetricsService,GoHighLevelService $goHighLevelService)
    {
        $this->baremetricsService = $baremetricsService;
        $this->ghlService = $goHighLevelService;
    }

    /**
     * Get account information from Baremetrics
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAccount()
    {
        $account = $this->baremetricsService->getAccount();

        if ($account) {
            return response()->json([
                'success' => true,
                'data' => $account
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No se pudo obtener la información de la cuenta'
        ], 500);
    }

    /**
     * Get sources from Baremetrics
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSources()
    {
        $sources = $this->baremetricsService->getSources();

        if ($sources) {
            return response()->json([
                'success' => true,
                'data' => $sources
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No se pudo obtener las fuentes de datos'
        ], 500);
    }

    /**
     * Get users from Baremetrics
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUsers()
    {
        $users = $this->baremetricsService->getUsers();

        if ($users) {
            return response()->json([
                'success' => true,
                'data' => $users
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No se pudo obtener la información de usuarios'
        ], 500);
    }

    public function createCustomer()
    {
        $unique = str_replace('.', '', uniqid('', true));
        $customer = [
            'email' => 'braulio@felamedia.com',
            'name' => 'Braulio Miramontes',
            'oid' => $unique,
        ];

        $this->baremetricsService->createCustomer($customer, 'd9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8');
    }
    
    /**
     * Check if a user email exists in any Baremetrics source
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkEmailExists(Request $request): JsonResponse
    {
        $email = $request->input('email');
        
        if (!$email) {
            return response()->json([
                'success' => false,
                'message' => 'El correo electrónico es requerido',
                'exists' => false
            ], 400);
        }
        
        $exists = $this->checkIfEmailExistsInBaremetrics($email);
        
        return response()->json([
            'success' => true,
            'exists' => $exists,
            'message' => $exists ? 'El correo electrónico existe en Baremetrics' : 'El correo electrónico no existe en Baremetrics'
        ]);
    }
    
    /**
     * Check if an email exists in any Baremetrics source (internal method)
     *
     * @param string $email
     * @return bool
     */
    private function checkIfEmailExistsInBaremetrics(string $email): bool
    {
        $customers = $this->baremetricsService->getCustomersByEmail($email);
        return !empty($customers);
    }

    /**
     * Get plans from Baremetrics for a specific source
     *
     * @param \Illuminate\Http\Request $request
     * @param string $sourceId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPlans(Request $request, string $sourceId)
    {
        $page = $request->query('page');
        $perPage = $request->query('per_page');

        $plans = $this->baremetricsService->getPlans($sourceId, $page, $perPage);

        if ($plans) {
            return response()->json([
                'success' => true,
                'data' => $plans
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No se pudo obtener los planes para la fuente especificada'
        ], 500);
    }

    /**
     * Get customers from Baremetrics for a specific source
     *
     * @param \Illuminate\Http\Request $request
     * @param string $sourceId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCustomers(Request $request, string $sourceId)
    {
        $page = $request->query('page');
        $perPage = $request->query('per_page');

        $customers = $this->baremetricsService->getCustomers($sourceId, $page, $perPage);

        if ($customers) {
            return response()->json([
                'success' => true,
                'data' => $customers
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No se pudo obtener los clientes para la fuente especificada'
        ], 500);
    }

    /**
     * Get subscriptions from Baremetrics for a specific source
     *
     * @param \Illuminate\Http\Request $request
     * @param string $sourceId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSubscriptions(Request $request, string $sourceId)
    {
        $page = $request->query('page');
        $perPage = $request->query('per_page');

        $subscriptions = $this->baremetricsService->getSubscriptions($sourceId, $page, $perPage);

        if ($subscriptions) {
            return response()->json([
                'success' => true,
                'data' => $subscriptions
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No se pudo obtener las suscripciones para la fuente especificada'
        ], 500);
    }

    public function updateCustomerFieldsFromGHL()
    {
        // Dispatch the job to perform the update in background (or sync depending on queue driver)
        $cacheKey = 'baremetrics:update_fields:progress';
        Cache::put($cacheKey, [
            'status' => 'queued',
            'updated' => 0,
            'total' => 0,
            'current_email' => null,
            'started_at' => now()->toDateTimeString(),
        ], 60 * 60);

        try {
            \App\Jobs\UpdateBaremetricsFromGHLJob::dispatch();
            return response()->json(['success' => true, 'message' => 'Proceso encolado']);
        } catch (\Exception $e) {
            Log::error('Error dispatching UpdateBaremetricsFromGHLJob: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'No se pudo iniciar el proceso'], 500);
        }
    }

    public function updateAttributes($customerId,$ghlData)
    {
        try {
            $result = $this->baremetricsService->updateCustomerAttributes($customerId, $ghlData);

        } catch (\Exception $e) {
            Log::error("Error al actualizar campos GHL: " . $e->getMessage());
        }
    }

    /**
     * Return view that allows admin to start the update and see progress
     */
    public function showUpdateFields()
    {
        return view('baremetrics.update_fields');
    }

    /**
     * Return current progress as JSON
     */
    public function getUpdateStatus(): JsonResponse
    {
        $cacheKey = 'baremetrics:update_fields:progress';
        $progress = Cache::get($cacheKey, [
            'status' => 'idle',
            'updated' => 0,
            'total' => 0,
            'current_email' => null,
        ]);

        return response()->json(['success' => true, 'data' => $progress]);
    }

    /**
     * Get API configuration and environment information
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getConfig()
    {
        try {
            $config = [
                'environment' => $this->baremetricsService->getEnvironment(),
                'base_url' => $this->baremetricsService->getBaseUrl(),
                'is_sandbox' => $this->baremetricsService->isSandbox(),
                'is_production' => $this->baremetricsService->isProduction(),
                // Note: No incluimos la API key por seguridad
            ];

            return response()->json([
                'success' => true,
                'data' => $config
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo obtener la configuración'
            ], 500);
        }
    }

    /**
     * Show Baremetrics dashboard
     *
     * @return \Illuminate\View\View
     */
    public function dashboard()
    {
        $account = $this->baremetricsService->getAccount();
        return view('baremetrics.dashboard', compact('account'));
    }

    /**
     * Create plans in sandbox
     *
     * @return \Illuminate\Http\JsonResponse
     */
    private function getSource()
    {
        // Se obtiene el sourceId
            $sources = $this->baremetricsService->getSources();
            
            if (!$sources || empty($sources['sources'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron fuentes en Baremetrics'
                ], 500);
            }
            
            $sourceId = $sources['sources'][0]['id'];
            return $sourceId;
    }

    public function createPlanSandbox()
    {
        try {
            $sourceId = $this->getSource();

            // Obtenemos los planes existentes
            $existingPlans = $this->baremetricsService->getPlans($sourceId);
            $existingOids = [];
            if (isset($existingPlans['plans']) && is_array($existingPlans['plans'])) {
                foreach ($existingPlans['plans'] as $plan) {
                    if (isset($plan['oid'])) {
                        $existingOids[] = $plan['oid'];
                    }
                }
            }

            // Definimos los planes a crear (estructura corregida)
            $plansCreate = [
                [
                    'name' => 'Créetelo mensual',
                    'interval' => 'month',
                    'interval_count' => 1,
                    'amount' => 3900,
                    'currency' => 'USD',
                    'oid' => 'creetelo_mensual',
                ],
                [
                    'name' => 'Créetelo anual',
                    'interval' => 'year',
                    'interval_count' => 1,
                    'amount' => 39000,
                    'currency' => 'USD',
                    'oid' => 'creetelo_anual',
                ],
            ];

            $createdPlans = [];
            $errors = [];

            // Creamos solo los planes que no existen
            foreach ($plansCreate as $plan) {
                if (!in_array($plan['oid'], $existingOids)) {
                    $result = $this->baremetricsService->createPlan($plan, $sourceId);
                    
                    if ($result) {
                        $createdPlans[] = $plan['name'];
                        Log::info("Plan creado exitosamente: " . $plan['name']);
                    } else {
                        $errors[] = $plan['name'];
                        Log::error("Error creando plan: " . $plan['name']);
                    }
                    
                    sleep(1); // Evitar rate limiting
                } else {
                    Log::info("Plan ya existe, saltando: " . $plan['name']);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Proceso de creación de sandbox completado',
                'created_plans' => $createdPlans,
                'errors' => $errors,
                'existing_plans_count' => count($existingOids)
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error en createCompleteSandbox: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor: ' . $e->getMessage()
            ], 500);
        }
    }

    public function createCustomerSandbox()
    {
        try {
            $sourceId = $this->getSource();

            $customer = MissingUser::where('import_status', 'failed')->inRandomOrder()->first();
            
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró el usuario en MissingUser'
                ], 404);
            }

            Log::info('Procesando cliente sandbox', ['email' => $customer->email]);

            // Obtener información de GHL del usuario
            $ghlCustomer = $this->ghlService->getContactsByExactEmail($customer->email);
            
            if (empty($ghlCustomer['contacts'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró el contacto en GHL'
                ], 404);
            }
            
            $customer_ghl = $ghlCustomer['contacts'][0];

            // Obtener suscripción de GHL - llamar al método interno que retorna datos
            $subscriptionGHLResponse = $this->getGHLSubscriptionData($customer->email);
            
            if (!$subscriptionGHLResponse['success'] || !$subscriptionGHLResponse['data']['subscription']) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró suscripción activa en GHL para este usuario'
                ], 404);
            }
            
            $subscriptionGHL = $subscriptionGHLResponse['data'];

            // Verificar si el cliente ya existe en Baremetrics
            if ($this->checkIfEmailExistsInBaremetrics($customer->email)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El cliente ya existe en Baremetrics'
                ], 409);
            }

            // Crear cliente
            $unique = str_replace('.', '', uniqid('', true));
            $oid = 'ghl_' . $unique;
            $customerCreate = [
                'email' => $customer->email,
                'name' => $customer->name,
                'oid' => $oid,
            ];

            Log::info('Creando cliente en Baremetrics', ['oid' => $oid, 'email' => $customer->email]);
            
            $customerData = $this->baremetricsService->createCustomer($customerCreate, $sourceId);

            if (!$customerData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error creando el cliente de prueba'
                ], 500);
            }

            Log::info('Cliente creado exitosamente', ['customer_id' => $customerData['id'] ?? 'N/A']);

            // Obtener el plan tag de los tags del cliente
            $planTag = $this->findPlanTag($customer->tags);
            
            if (!$planTag) {
                Log::warning('No se encontró plan tag para el cliente', ['tags' => $customer->tags]);
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo determinar el plan del cliente. Tags: ' . $customer->tags,
                    'customer' => $customerData
                ], 400);
            }

            Log::info('Plan identificado', ['plan_oid' => $planTag]);

            // Crear suscripción - usar la fecha correcta de GHL
            $subscriptionOid = 'ghl_sub_' . str_replace('.', '', uniqid('', true));
            
            // Obtener la fecha de inicio de la suscripción desde GHL
            $startedAt = now()->timestamp; // Valor por defecto
            
            if (isset($subscriptionGHL['subscription']['createdAt'])) {
                $startedAt = Carbon::parse($subscriptionGHL['subscription']['createdAt'])->timestamp;
                Log::info('Usando fecha de GHL para started_at', [
                    'createdAt_original' => $subscriptionGHL['subscription']['createdAt'],
                    'timestamp' => $startedAt,
                    'fecha_legible' => Carbon::parse($subscriptionGHL['subscription']['createdAt'])->toDateString()
                ]);
            } else {
                Log::warning('No se encontró createdAt en suscripción GHL, usando fecha actual', [
                    'subscription_data' => $subscriptionGHL['subscription']
                ]);
            }
            
            $subscriptionData = [
                'oid' => $subscriptionOid,
                'customer_oid' => $oid,
                'plan_oid' => $planTag,
                'status' => $subscriptionGHL['subscription']['status'] ?? 'active',
                'started_at' => $startedAt,
            ];

            Log::info('Creando suscripción en Baremetrics', $subscriptionData);

            $subscription = $this->baremetricsService->createSubscription($subscriptionData, $sourceId);

            if (!$subscription) {
                Log::error('Error creando suscripción');
                return response()->json([
                    'success' => false,
                    'message' => 'Cliente creado pero falló la creación de la suscripción',
                    'customer' => $customerData
                ], 500);
            }

            Log::info('Suscripción creada exitosamente', ['subscription_id' => $subscription['id'] ?? 'N/A']);

            return response()->json([
                'success' => true,
                'message' => 'Cliente y suscripción creados exitosamente',
                'customer' => $customerData,
                'subscription' => $subscription
            ]);

        } catch (\Exception $e) {
            Log::error('Error en createCustomerSandbox: ' . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor: ' . $e->getMessage(),
                'line' => $e->getLine(),
                'file' => basename($e->getFile())
            ], 500);
        }
    }

    private function findPlanTag($tagsString)
    {
        $tags = explode(',', $tagsString);
        $normalizedTags = array_map(function($tag) {
            return strtolower(trim($tag));
        }, $tags);

        $planTags = [
            'creetelo_mensual',
            'créetelo_mensual',
            'creetelo_anual',
            'créetelo_anual'
        ];

        foreach ($planTags as $planTag) {
            $normalizedPlanTag = strtolower($planTag);
            if (in_array($normalizedPlanTag, $normalizedTags)) {
            // Remover acentos
            $planTagSinAcentos = str_replace(
                ['é'],
                ['e'],
                $normalizedPlanTag
            );
            return $planTagSinAcentos;
            }
        }

        return null;
    }

    /**
     * Obtener datos de suscripción de GHL (método interno)
     *
     * @param string $email
     * @return array
     */
    private function getGHLSubscriptionData(string $email): array
    {
        try {
            $ghlCustomer = $this->ghlService->getContactsByExactEmail($email);
            
            if (empty($ghlCustomer['contacts'])) {
                $ghlCustomer = $this->ghlService->getContacts($email);
            }

            if (empty($ghlCustomer['contacts'])) {
                return [
                    'success' => false,
                    'message' => 'Usuario no encontrado en GHL',
                    'data' => null
                ];
            }

            $contact = $ghlCustomer['contacts'][0];
            $contactId = $contact['id'];

            $subscription = $this->ghlService->getSubscriptionStatusByContact($contactId);

            if (!$subscription) {
                return [
                    'success' => true,
                    'message' => 'Usuario sin suscripción',
                    'data' => [
                        'contact' => $contact,
                        'subscription' => null
                    ]
                ];
            }

            return [
                'success' => true,
                'message' => 'Suscripción encontrada',
                'data' => [
                    'contact' => $contact,
                    'subscription' => $subscription
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Error obteniendo datos de suscripción GHL', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Error obteniendo datos: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Obtener la suscripción de un usuario en GoHighLevel
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    private function getUserSubscriptionGHL($email)
    {
        try {
            Log::info('Obteniendo suscripción de usuario en GHL', [
                'email' => $email,
            ]);

            // Buscar usuario en GoHighLevel por email
            $ghlCustomer = $this->ghlService->getContactsByExactEmail($email);
            
            // Si no se encuentra con búsqueda exacta, intentar con búsqueda amplia
            if (empty($ghlCustomer['contacts'])) {
                $ghlCustomer = $this->ghlService->getContacts($email);
            }

            if (empty($ghlCustomer['contacts'])) {
                Log::warning('Usuario no encontrado en GoHighLevel', ['email' => $email]);
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró el usuario en GoHighLevel con el correo proporcionado',
                    'email' => $email
                ], 404);
            }

            $contact = $ghlCustomer['contacts'][0];
            $contactId = $contact['id'];

            Log::info('Usuario encontrado en GHL', [
                'email' => $email,
                'contact_id' => $contactId,
                'name' => $contact['name'] ?? 'Sin nombre'
            ]);

            // Obtener información de suscripción más reciente
            $subscription = $this->ghlService->getSubscriptionStatusByContact($contactId);

            if (!$subscription) {
                Log::info('No se encontró suscripción para el usuario', [
                    'email' => $email,
                    'contact_id' => $contactId
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Usuario encontrado pero sin suscripción activa',
                    'data' => [
                        'contact' => [
                            'id' => $contactId,
                            'name' => $contact['name'] ?? null,
                            'email' => $contact['email'] ?? $email,
                            'tags' => $contact['tags'] ?? []
                        ],
                        'subscription' => null
                    ]
                ]);
            }

            // Preparar datos de respuesta
            $responseData = [
                'contact' => [
                    'id' => $contactId,
                    'name' => $contact['name'] ?? null,
                    'email' => $contact['email'] ?? $email,
                    'tags' => $contact['tags'] ?? []
                ],
                'subscription' => [
                    'id' => $subscription['id'] ?? null,
                    'status' => $subscription['status'] ?? 'unknown',
                    'coupon_code' => $subscription['couponCode'] ?? null,
                    'created_at' => $subscription['createdAt'] ?? null,
                    'updated_at' => $subscription['updatedAt'] ?? null,
                    'amount' => $subscription['amount'] ?? null,
                    'currency' => $subscription['currency'] ?? null,
                    'plan_name' => $subscription['planName'] ?? null
                ]
            ];

            Log::info('Suscripción obtenida exitosamente', [
                'email' => $email,
                'subscription_id' => $subscription['id'] ?? 'N/A',
                'status' => $subscription['status'] ?? 'unknown'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Suscripción obtenida exitosamente',
                'data' => $responseData
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Error de validación al obtener suscripción GHL', [
                'errors' => $e->errors()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error al obtener suscripción de usuario en GHL', [
                'email' => $request->input('email'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar la página para gestionar usuarios fallidos
     * 
     * @return \Illuminate\View\View
     */
    public function showFailedUsers(Request $request)
    {
        // Obtener el status del query parameter, por defecto 'failed'
        $status = $request->query('status', 'failed');
        
        // Validar que el status sea uno de los permitidos
        $allowedStatuses = ['pending', 'importing', 'imported', 'failed', 'found_in_other_source'];
        if (!in_array($status, $allowedStatuses)) {
            $status = 'failed';
        }
        
        $failedUsers = MissingUser::where('import_status', $status)
            ->orderBy('created_at', 'desc')
            ->paginate(50);
        
        // Obtener contadores para cada status
        $statusCounts = [
            'pending' => MissingUser::where('import_status', 'pending')->count(),
            'importing' => MissingUser::where('import_status', 'importing')->count(),
            'imported' => MissingUser::where('import_status', 'imported')->count(),
            'failed' => MissingUser::where('import_status', 'failed')->count(),
            'found_in_other_source' => MissingUser::where('import_status', 'found_in_other_source')->count(),
        ];

        return view('admin.baremetrics.failed-users', compact('failedUsers', 'status', 'statusCounts'));
    }

    /**
     * Eliminar usuarios y suscripciones de Baremetrics para usuarios con status específico
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteFailedUsers(Request $request)
    {
        try {
            // Obtener el status a eliminar desde el request
            $statusToDelete = $request->input('status', 'failed');
            
            // Validar que el status sea uno de los permitidos
            $allowedStatuses = ['pending', 'importing', 'imported', 'failed', 'found_in_other_source'];
            if (!in_array($statusToDelete, $allowedStatuses)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Status no válido. Los status permitidos son: ' . implode(', ', $allowedStatuses)
                ], 400);
            }
            
            Log::info('=== INICIANDO ELIMINACIÓN DE USUARIOS ===', [
                'status_to_delete' => $statusToDelete,
                'user_ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // Configurar Baremetrics para producción
            config(['services.baremetrics.environment' => 'production']);
            $this->baremetricsService->reinitializeConfiguration();

            // Obtener el source ID correcto para GHL
            $sourceId = $this->baremetricsService->getGHLSourceId();
            
            if (!$sourceId) {
                Log::error('No se pudo obtener source ID de GHL');
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo obtener el source ID de GHL de Baremetrics.'
                ], 500);
            }

            Log::info('Configuración de Baremetrics', [
                'environment' => config('services.baremetrics.environment'),
                'source_id' => $sourceId,
                'base_url' => config('services.baremetrics.production_url')
            ]);

            // Obtener usuarios con el status especificado
            $failedUsers = MissingUser::where('import_status', $statusToDelete)->get();

            Log::info('Usuarios encontrados', [
                'status' => $statusToDelete,
                'total_count' => $failedUsers->count(),
                'user_emails' => $failedUsers->pluck('email')->toArray(),
                'customer_ids' => $failedUsers->pluck('baremetrics_customer_id')->toArray()
            ]);

            if ($failedUsers->isEmpty()) {
                Log::warning('No hay usuarios para eliminar');
                return response()->json([
                    'success' => false,
                    'message' => "No hay usuarios con status \"{$statusToDelete}\" para eliminar."
                ], 404);
            }

            $deletedCount = 0;
            $failedCount = 0;
            $errors = [];
            $processedUsers = [];

            Log::info('Iniciando procesamiento de usuarios fallidos', [
                'total_users' => $failedUsers->count()
            ]);

            foreach ($failedUsers as $index => $user) {
                $userIndex = $index + 1;
                Log::info("Procesando usuario #{$userIndex}/{$failedUsers->count()}", [
                    'user_email' => $user->email,
                    'customer_id' => $user->baremetrics_customer_id,
                    'user_id' => $user->id
                ]);

                try {
                    $customerId = $user->baremetrics_customer_id;
                    
                    // Si el usuario no tiene customer_id, solo cambiar estado a pending
                    if (!$customerId) {
                        Log::info("Usuario {$user->email} no tiene customer_id, solo cambiando estado a pending");
                        
                        $updateResult = $user->update([
                            'import_status' => 'pending',
                            'baremetrics_customer_id' => null,
                            'imported_at' => null,
                            'import_error' => null
                        ]);
                        
                        Log::info("Estado actualizado para usuario sin customer_id", [
                            'user_email' => $user->email,
                            'update_success' => $updateResult
                        ]);
                        
                        $deletedCount++;
                        $processedUsers[] = [
                            'email' => $user->email,
                            'customer_id' => null,
                            'subscriptions_deleted' => 0,
                            'status' => 'no_customer_id_cleaned'
                        ];
                        
                        Log::info("Pausa de 200ms antes del siguiente usuario");
                        usleep(200000); // 200ms
                        continue;
                    }
                    
                    // 1. Obtener suscripciones del usuario
                    Log::info("Obteniendo suscripciones para usuario {$user->email}", [
                        'customer_id' => $customerId
                    ]);
                    
                    $subscriptions = $this->baremetricsService->getSubscriptions($sourceId);
                    $userSubscriptions = [];
                    
                    if ($subscriptions && isset($subscriptions['subscriptions'])) {
                        Log::info("Suscripciones obtenidas de Baremetrics", [
                            'total_subscriptions' => count($subscriptions['subscriptions'])
                        ]);
                        
                        foreach ($subscriptions['subscriptions'] as $subscription) {
                            $subscriptionCustomerOid = $subscription['customer_oid'] ?? 
                                                     $subscription['customer']['oid'] ?? 
                                                     $subscription['customerOid'] ?? 
                                                     null;
                            
                            if ($subscriptionCustomerOid === $customerId) {
                                $userSubscriptions[] = $subscription;
                            }
                        }
                        
                        Log::info("Suscripciones encontradas para usuario {$user->email}", [
                            'subscriptions_count' => count($userSubscriptions),
                            'subscription_ids' => array_column($userSubscriptions, 'oid')
                        ]);
                    } else {
                        Log::warning("No se pudieron obtener suscripciones de Baremetrics", [
                            'response' => $subscriptions
                        ]);
                    }

                    // 2. Eliminar suscripciones
                    Log::info("Eliminando suscripciones para usuario {$user->email}", [
                        'subscriptions_to_delete' => count($userSubscriptions)
                    ]);
                    
                    foreach ($userSubscriptions as $subscription) {
                        $subscriptionOid = $subscription['oid'];
                        Log::info("Eliminando suscripción {$subscriptionOid}");
                        
                        $deleteResult = $this->baremetricsService->deleteSubscription(
                            $sourceId, 
                            $subscriptionOid
                        );
                        
                        if ($deleteResult) {
                            Log::info("Suscripción {$subscriptionOid} eliminada exitosamente");
                        } else {
                            Log::warning('Error eliminando suscripción', [
                                'subscription_id' => $subscriptionOid,
                                'customer_id' => $customerId,
                                'user_email' => $user->email
                            ]);
                        }
                    }

                    // 3. Eliminar customer
                    Log::info("Eliminando customer {$customerId} para usuario {$user->email}");
                    
                    $deleteCustomerResult = $this->baremetricsService->deleteCustomer(
                        $sourceId, 
                        $customerId
                    );

                    Log::info("Resultado de eliminación de customer", [
                        'customer_id' => $customerId,
                        'success' => $deleteCustomerResult,
                        'user_email' => $user->email
                    ]);

                    // Verificar si el customer ya no existe en Baremetrics (404)
                    $customerNotFound = false;
                    if (!$deleteCustomerResult) {
                        // Asumimos que si falla la eliminación, es porque no existe
                        $customerNotFound = true;
                        Log::info("Customer no encontrado en Baremetrics, marcando como eliminado", [
                            'customer_id' => $customerId,
                            'user_email' => $user->email
                        ]);
                    }

                    if ($deleteCustomerResult || $customerNotFound) {
                        // 4. Cambiar estado a pending y limpiar datos de Baremetrics
                        Log::info("Actualizando estado del usuario {$user->email} a pending");
                        
                        $updateResult = $user->update([
                            'import_status' => 'pending',
                            'baremetrics_customer_id' => null,
                            'imported_at' => null,
                            'import_error' => null
                        ]);
                        
                        Log::info("Resultado de actualización de usuario", [
                            'user_email' => $user->email,
                            'update_success' => $updateResult
                        ]);
                        
                        $deletedCount++;
                        $processedUsers[] = [
                            'email' => $user->email,
                            'customer_id' => $customerId,
                            'subscriptions_deleted' => count($userSubscriptions),
                            'status' => $customerNotFound ? 'not_found_but_cleaned' : 'success'
                        ];
                        
                        Log::info('Usuario eliminado exitosamente', [
                            'user_email' => $user->email,
                            'customer_id' => $customerId,
                            'subscriptions_deleted' => count($userSubscriptions)
                        ]);
                    } else {
                        $failedCount++;
                        $errorMsg = "Error eliminando usuario {$user->email} (ID: {$customerId})";
                        $errors[] = $errorMsg;
                        
                        $processedUsers[] = [
                            'email' => $user->email,
                            'customer_id' => $customerId,
                            'status' => 'failed',
                            'error' => $errorMsg
                        ];
                        
                        Log::error('Error eliminando usuario de Baremetrics', [
                            'user_email' => $user->email,
                            'customer_id' => $customerId
                        ]);
                    }

                    // Pausa pequeña entre eliminaciones para evitar sobrecargar la API
                    Log::info("Pausa de 200ms antes del siguiente usuario");
                    usleep(200000); // 200ms

                } catch (\Exception $e) {
                    $failedCount++;
                    $errorMsg = "Error procesando usuario {$user->email}: {$e->getMessage()}";
                    $errors[] = $errorMsg;
                    
                    $processedUsers[] = [
                        'email' => $user->email,
                        'customer_id' => $user->baremetrics_customer_id,
                        'status' => 'error',
                        'error' => $e->getMessage()
                    ];
                    
                    Log::error('Excepción procesando usuario', [
                        'user_email' => $user->email,
                        'customer_id' => $user->baremetrics_customer_id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            Log::info('=== PROCESO DE ELIMINACIÓN COMPLETADO ===', [
                'total_procesados' => $failedUsers->count(),
                'exitosos' => $deletedCount,
                'fallidos' => $failedCount
            ]);

            $message = "Proceso completado. {$deletedCount} usuarios eliminados/limpiados";
            if ($failedCount > 0) {
                $message .= ", {$failedCount} fallidos.";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'total_processed' => $failedUsers->count(),
                    'deleted_count' => $deletedCount,
                    'failed_count' => $failedCount,
                    'errors' => $errors,
                    'processed_users' => $processedUsers
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error en deleteFailedUsers: ' . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor: ' . $e->getMessage(),
                'line' => $e->getLine(),
                'file' => basename($e->getFile())
            ], 500);
        }
    }

    /**
     * Mostrar vista para eliminar usuarios por plan
     */
    public function showDeleteUsersByPlan()
    {
        return view('baremetrics.delete-by-plan');
    }

    /**
     * Eliminar usuarios de Baremetrics por plan específico
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteUsersByPlan(Request $request)
    {
        try {
            // Validar que se reciba el plan
            $request->validate([
                'plan_name' => 'required|string'
            ]);

            $planName = $request->input('plan_name');
            $sourceId = config('services.baremetrics.production_source_id', 'd9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8');

            // Configurar entorno en producción
            config(['services.baremetrics.environment' => 'production']);
            $this->baremetricsService->reinitializeConfiguration();

            Log::info('=== INICIANDO ELIMINACIÓN DE USUARIOS POR PLAN ===', [
                'plan_name' => $planName,
                'environment' => config('services.baremetrics.environment'),
                'source_id' => $sourceId,
                'base_url' => config('services.baremetrics.production_url')
            ]);

            // 1. Obtener todas las suscripciones
            $subscriptions = $this->baremetricsService->getSubscriptions($sourceId);
            
            if (!$subscriptions || !isset($subscriptions['subscriptions'])) {
                Log::warning('No se pudieron obtener suscripciones de Baremetrics');
                return response()->json([
                    'success' => false,
                    'message' => "No se pudieron obtener las suscripciones de Baremetrics."
                ], 500);
            }

            // 2. Filtrar suscripciones por plan
            $planSubscriptions = [];
            $customerIds = [];
            
            foreach ($subscriptions['subscriptions'] as $subscription) {
                $subscriptionPlan = $subscription['plan']['name'] ?? 
                                   $subscription['plan_name'] ?? 
                                   null;
                
                if ($subscriptionPlan === $planName) {
                    $planSubscriptions[] = $subscription;
                    $customerId = $subscription['customer_oid'] ?? 
                                 $subscription['customer']['oid'] ?? 
                                 $subscription['customerOid'] ?? 
                                 null;
                    
                    if ($customerId && !in_array($customerId, $customerIds)) {
                        $customerIds[] = $customerId;
                    }
                }
            }

            Log::info('Suscripciones y usuarios filtrados por plan', [
                'plan_name' => $planName,
                'total_subscriptions' => count($planSubscriptions),
                'unique_customers' => count($customerIds),
                'customer_ids' => $customerIds
            ]);

            if (empty($planSubscriptions)) {
                return response()->json([
                    'success' => false,
                    'message' => "No se encontraron suscripciones para el plan '{$planName}'."
                ], 404);
            }

            // 3. Procesar cada cliente
            $deletedCount = 0;
            $failedCount = 0;
            $errors = [];
            $processedUsers = [];

            Log::info('Iniciando procesamiento de usuarios del plan', [
                'total_customers' => count($customerIds),
                'plan_name' => $planName
            ]);

            foreach ($customerIds as $index => $customerId) {
                $userIndex = $index + 1;
                
                try {
                    Log::info("Procesando usuario #{$userIndex}/{" . count($customerIds) . "}", [
                        'customer_id' => $customerId
                    ]);

                    // Obtener información del cliente
                    $customers = $this->baremetricsService->getCustomers($sourceId);
                    $customerInfo = null;
                    
                    if ($customers && isset($customers['customers'])) {
                        foreach ($customers['customers'] as $customer) {
                            if ($customer['oid'] === $customerId) {
                                $customerInfo = $customer;
                                break;
                            }
                        }
                    }

                    $customerEmail = $customerInfo['email'] ?? 'N/A';
                    $customerName = $customerInfo['name'] ?? 'N/A';

                    Log::info("Información del cliente", [
                        'customer_id' => $customerId,
                        'email' => $customerEmail,
                        'name' => $customerName
                    ]);

                    // 1. Obtener y eliminar todas las suscripciones del usuario (no solo del plan)
                    $userSubscriptions = [];
                    foreach ($subscriptions['subscriptions'] as $subscription) {
                        $subscriptionCustomerOid = $subscription['customer_oid'] ?? 
                                                 $subscription['customer']['oid'] ?? 
                                                 $subscription['customerOid'] ?? 
                                                 null;
                        
                        if ($subscriptionCustomerOid === $customerId) {
                            $userSubscriptions[] = $subscription;
                        }
                    }

                    Log::info("Suscripciones encontradas para el usuario", [
                        'customer_id' => $customerId,
                        'email' => $customerEmail,
                        'subscriptions_count' => count($userSubscriptions),
                        'subscription_ids' => array_column($userSubscriptions, 'oid')
                    ]);

                    // 2. Eliminar cada suscripción
                    $deletedSubscriptions = 0;
                    foreach ($userSubscriptions as $subscription) {
                        $subscriptionOid = $subscription['oid'];
                        Log::info("Eliminando suscripción {$subscriptionOid} del cliente {$customerEmail}");
                        
                        $deleteResult = $this->baremetricsService->deleteSubscription(
                            $sourceId, 
                            $subscriptionOid
                        );
                        
                        if ($deleteResult) {
                            $deletedSubscriptions++;
                            Log::info("Suscripción {$subscriptionOid} eliminada exitosamente");
                        } else {
                            Log::warning('Error eliminando suscripción', [
                                'subscription_id' => $subscriptionOid,
                                'customer_id' => $customerId,
                                'email' => $customerEmail
                            ]);
                        }
                    }

                    // 3. Eliminar customer
                    Log::info("Eliminando customer {$customerId} ({$customerEmail})");
                    
                    $deleteCustomerResult = $this->baremetricsService->deleteCustomer(
                        $sourceId, 
                        $customerId
                    );

                    Log::info("Resultado de eliminación de customer", [
                        'customer_id' => $customerId,
                        'email' => $customerEmail,
                        'success' => $deleteCustomerResult
                    ]);

                    if ($deleteCustomerResult) {
                        $deletedCount++;
                        $processedUsers[] = [
                            'customer_id' => $customerId,
                            'email' => $customerEmail,
                            'name' => $customerName,
                            'subscriptions_deleted' => $deletedSubscriptions,
                            'status' => 'success'
                        ];
                        
                        Log::info('Usuario eliminado exitosamente', [
                            'customer_id' => $customerId,
                            'email' => $customerEmail,
                            'subscriptions_deleted' => $deletedSubscriptions
                        ]);
                    } else {
                        $failedCount++;
                        $errorMsg = "Error eliminando usuario {$customerEmail} (ID: {$customerId})";
                        $errors[] = $errorMsg;
                        
                        $processedUsers[] = [
                            'customer_id' => $customerId,
                            'email' => $customerEmail,
                            'name' => $customerName,
                            'status' => 'failed',
                            'error' => $errorMsg
                        ];
                        
                        Log::error('Error eliminando usuario de Baremetrics', [
                            'customer_id' => $customerId,
                            'email' => $customerEmail
                        ]);
                    }

                    // Pausa pequeña entre eliminaciones
                    Log::info("Pausa de 300ms antes del siguiente usuario");
                    usleep(300000); // 300ms

                } catch (\Exception $e) {
                    $failedCount++;
                    $errorMsg = "Error procesando usuario {$customerId}: {$e->getMessage()}";
                    $errors[] = $errorMsg;
                    
                    $processedUsers[] = [
                        'customer_id' => $customerId,
                        'email' => 'N/A',
                        'status' => 'error',
                        'error' => $e->getMessage()
                    ];
                    
                    Log::error('Excepción procesando usuario', [
                        'customer_id' => $customerId,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            Log::info('=== PROCESO DE ELIMINACIÓN POR PLAN COMPLETADO ===', [
                'plan_name' => $planName,
                'total_procesados' => count($customerIds),
                'exitosos' => $deletedCount,
                'fallidos' => $failedCount
            ]);

            $message = "Proceso completado para el plan '{$planName}'. {$deletedCount} usuarios eliminados";
            if ($failedCount > 0) {
                $message .= ", {$failedCount} fallidos.";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'plan_name' => $planName,
                    'total_processed' => count($customerIds),
                    'deleted_count' => $deletedCount,
                    'failed_count' => $failedCount,
                    'errors' => $errors,
                    'processed_users' => $processedUsers
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error en deleteUsersByPlan: ' . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor: ' . $e->getMessage(),
                'line' => $e->getLine(),
                'file' => basename($e->getFile())
            ], 500);
        }
    }
}
