<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SearchSpecificUserInBaremetrics extends Command
{
    protected $signature = 'baremetrics:search-user {email} {--environment=production}';
    protected $description = 'Search for a specific user in Baremetrics with detailed results';

    public function handle()
    {
        $email = $this->argument('email');
        $environment = $this->option('environment');
        
        $this->info("🔍 Buscando usuario específico en Baremetrics...");
        $this->line("📧 Email: {$email}");
        $this->line("🌐 Entorno: {$environment}");
        $this->line('');

        $config = config('services.baremetrics');
        
        if ($environment === 'sandbox') {
            $apiKey = $config['sandbox_key'];
            $baseUrl = $config['sandbox_url'];
        } else {
            $apiKey = $config['live_key'];
            $baseUrl = $config['production_url'];
        }

        try {
            // Método 1: Búsqueda directa por email
            $this->line("🔍 MÉTODO 1: Búsqueda directa por email");
            $this->line("=====================================");
            $this->searchByEmail($baseUrl, $apiKey, $email);
            
            $this->line('');
            
            // Método 2: Obtener todos los usuarios y buscar localmente
            $this->line("🔍 MÉTODO 2: Obtener usuarios y buscar localmente");
            $this->line("===============================================");
            $this->searchInAllUsers($baseUrl, $apiKey, $email);
            
            $this->line('');
            
            // Método 3: Probar diferentes parámetros de búsqueda
            $this->line("🔍 MÉTODO 3: Diferentes parámetros de búsqueda");
            $this->line("=============================================");
            $this->searchWithDifferentParams($baseUrl, $apiKey, $email);

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
        }
    }

    private function searchByEmail($baseUrl, $apiKey, $email)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->get($baseUrl . '/customers', [
                'email' => $email,
                'per_page' => 50
            ]);

            $this->line("   📡 Status: " . $response->status());
            
            if ($response->successful()) {
                $data = $response->json();
                $customers = $data['customers'] ?? [];
                
                $this->line("   📊 Total usuarios devueltos: " . count($customers));
                
                if (!empty($customers)) {
                    $found = false;
                    foreach ($customers as $customer) {
                        $customerEmail = strtolower(trim($customer['email'] ?? ''));
                        $searchEmail = strtolower(trim($email));
                        
                        $this->line("   📧 Comparando: '{$customerEmail}' vs '{$searchEmail}'");
                        
                        if ($customerEmail === $searchEmail) {
                            $this->line("   ✅ ¡ENCONTRADO! Usuario exacto:");
                            $this->line("      🆔 ID: " . ($customer['id'] ?? 'N/A'));
                            $this->line("      📧 Email: " . ($customer['email'] ?? 'N/A'));
                            $this->line("      👤 Nombre: " . ($customer['name'] ?? 'N/A'));
                            $this->line("      🏢 Company: " . ($customer['company'] ?? 'N/A'));
                            $found = true;
                        }
                    }
                    
                    if (!$found) {
                        $this->line("   ❌ Usuario NO encontrado en los resultados");
                        $this->line("   📋 Primeros 5 usuarios devueltos:");
                        $count = 0;
                        foreach ($customers as $customer) {
                            if ($count >= 5) break;
                            $this->line("      • " . ($customer['email'] ?? 'N/A'));
                            $count++;
                        }
                    }
                } else {
                    $this->line("   ❌ No se devolvieron usuarios");
                }
            } else {
                $this->line("   ❌ Error en la respuesta: " . $response->body());
            }
        } catch (\Exception $e) {
            $this->line("   ❌ Excepción: " . $e->getMessage());
        }
    }

    private function searchInAllUsers($baseUrl, $apiKey, $email)
    {
        try {
            $this->line("   📡 Obteniendo usuarios (página 1)...");
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->get($baseUrl . '/customers', [
                'per_page' => 100
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $customers = $data['customers'] ?? [];
                
                $this->line("   📊 Usuarios en página 1: " . count($customers));
                
                $found = false;
                foreach ($customers as $customer) {
                    $customerEmail = strtolower(trim($customer['email'] ?? ''));
                    $searchEmail = strtolower(trim($email));
                    
                    if ($customerEmail === $searchEmail) {
                        $this->line("   ✅ ¡ENCONTRADO en página 1!");
                        $this->line("      🆔 ID: " . ($customer['id'] ?? 'N/A'));
                        $this->line("      📧 Email: " . ($customer['email'] ?? 'N/A'));
                        $this->line("      👤 Nombre: " . ($customer['name'] ?? 'N/A'));
                        $found = true;
                        break;
                    }
                }
                
                if (!$found) {
                    $this->line("   ❌ No encontrado en página 1");
                    $this->line("   📋 Primeros 5 emails de la página:");
                    $count = 0;
                    foreach ($customers as $customer) {
                        if ($count >= 5) break;
                        $this->line("      • " . ($customer['email'] ?? 'N/A'));
                        $count++;
                    }
                }
            } else {
                $this->line("   ❌ Error obteniendo usuarios: " . $response->status());
            }
        } catch (\Exception $e) {
            $this->line("   ❌ Excepción: " . $e->getMessage());
        }
    }

    private function searchWithDifferentParams($baseUrl, $apiKey, $email)
    {
        $searchParams = [
            ['email' => $email],
            ['q' => $email],
            ['search' => $email],
            ['filter[email]' => $email],
        ];

        foreach ($searchParams as $index => $params) {
            $this->line("   🔍 Parámetros " . ($index + 1) . ": " . json_encode($params));
            
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])->get($baseUrl . '/customers', $params);

                $this->line("      📡 Status: " . $response->status());
                
                if ($response->successful()) {
                    $data = $response->json();
                    $customers = $data['customers'] ?? [];
                    $this->line("      📊 Usuarios devueltos: " . count($customers));
                    
                    if (!empty($customers)) {
                        $found = false;
                        foreach ($customers as $customer) {
                            $customerEmail = strtolower(trim($customer['email'] ?? ''));
                            $searchEmail = strtolower(trim($email));
                            
                            if ($customerEmail === $searchEmail) {
                                $this->line("      ✅ ¡ENCONTRADO con estos parámetros!");
                                $found = true;
                                break;
                            }
                        }
                        
                        if (!$found) {
                            $this->line("      ❌ No encontrado con estos parámetros");
                        }
                    }
                } else {
                    $this->line("      ❌ Error: " . $response->status());
                }
            } catch (\Exception $e) {
                $this->line("      ❌ Excepción: " . $e->getMessage());
            }
            
            $this->line('');
        }
    }
}
