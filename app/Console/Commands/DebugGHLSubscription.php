<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;

class DebugGHLSubscription extends Command
{
    protected $signature = 'ghl:debug-subscription {email : Email del usuario}';
    protected $description = 'Debug para ver la estructura real de la suscripción de GHL';

    protected $ghlService;

    public function __construct(GoHighLevelService $ghlService)
    {
        parent::__construct();
        $this->ghlService = $ghlService;
    }

    public function handle()
    {
        $email = $this->argument('email');

        $this->info("🔍 DEBUGGING SUSCRIPCIÓN DE GHL");
        $this->info("=================================");
        $this->info("Email: {$email}");
        $this->newLine();

        try {
            // 1. Buscar usuario en GHL
            $this->info("🔍 Buscando usuario en GHL...");
            $ghlResponse = $this->ghlService->getContacts($email);
            
            if (empty($ghlResponse) || !isset($ghlResponse['contacts']) || empty($ghlResponse['contacts'])) {
                $this->error("❌ No se encontró el usuario en GHL");
                return 1;
            }

            $contact = $ghlResponse['contacts'][0];
            $contactId = $contact['id'];
            
            $this->info("✅ Usuario encontrado: {$contact['firstName']} {$contact['lastName']}");
            $this->info("🆔 Contact ID: {$contactId}");

            // 2. Obtener última suscripción
            $this->info("📋 Obteniendo última suscripción...");
            $latestSubscription = $this->ghlService->getMostRecentActiveSubscription($contactId);
            
            if (!$latestSubscription) {
                $this->warn("⚠️ No se encontró suscripción");
                return 0;
            }

            // 3. Mostrar estructura completa
            $this->info("📊 ESTRUCTURA COMPLETA DE LA SUSCRIPCIÓN:");
            $this->info("==========================================");
            $this->line(json_encode($latestSubscription, JSON_PRETTY_PRINT));

            // 4. Buscar campos de nombre específicos
            $this->newLine();
            $this->info("🔍 BUSCANDO CAMPOS DE NOMBRE:");
            $this->info("=============================");
            
            $nameFields = [
                'productName',
                'name', 
                'product_name',
                'plan_name',
                'lineItemDetail',
                'recurringProd',
                'product'
            ];

            foreach ($nameFields as $field) {
                if (isset($latestSubscription[$field])) {
                    $this->info("✅ Campo '{$field}': " . json_encode($latestSubscription[$field]));
                } else {
                    $this->warn("❌ Campo '{$field}': NO ENCONTRADO");
                }
            }

            // 5. Intentar extraer nombre
            $this->newLine();
            $this->info("🎯 INTENTANDO EXTRAER NOMBRE:");
            $this->info("============================");
            
            $extractedName = $this->extractSubscriptionName($latestSubscription);
            $this->info("📋 Nombre extraído: " . $extractedName);

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Extraer solo el nombre de la última suscripción
     */
    private function extractSubscriptionName(?array $latestSubscription): ?string
    {
        if (!$latestSubscription) {
            return null;
        }

        // Buscar el nombre en diferentes campos posibles
        $nameFields = [
            'productName',
            'name', 
            'product_name',
            'plan_name',
            'lineItemDetail.name',
            'recurringProd.name',
            'product.name'
        ];

        foreach ($nameFields as $field) {
            if (strpos($field, '.') !== false) {
                // Campo anidado como lineItemDetail.name
                $parts = explode('.', $field);
                $value = $latestSubscription;
                foreach ($parts as $part) {
                    if (isset($value[$part])) {
                        $value = $value[$part];
                    } else {
                        $value = null;
                        break;
                    }
                }
                if ($value && !empty($value)) {
                    return $value;
                }
            } else {
                // Campo directo
                if (isset($latestSubscription[$field]) && !empty($latestSubscription[$field])) {
                    return $latestSubscription[$field];
                }
            }
        }

        // Si no encontramos nombre específico, usar un guión
        return '-';
    }
}
