<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestBaremetricsEnvironments extends Command
{
    protected $signature = 'baremetrics:test-environments {--email=isabelbtorres@gmail.com}';
    protected $description = 'Test Baremetrics API in both sandbox and production environments';

    public function handle()
    {
        $testEmail = $this->option('email');
        
        $this->info("🔍 Probando Baremetrics API en ambos entornos...");
        $this->line("📧 Email de prueba: {$testEmail}");
        $this->line('');

        // Probar Sandbox
        $this->line("🔍 PROBANDO SANDBOX:");
        $this->line("===================");
        $sandboxResult = $this->testEnvironment('sandbox', $testEmail);
        
        $this->line('');
        
        // Probar Producción
        $this->line("🔍 PROBANDO PRODUCCIÓN:");
        $this->line("=======================");
        $productionResult = $this->testEnvironment('production', $testEmail);
        
        $this->line('');
        
        // Resumen
        $this->line("📊 RESUMEN:");
        $this->line("===========");
        $this->line("Sandbox: " . ($sandboxResult ? "✅ Usuario encontrado" : "❌ Usuario NO encontrado"));
        $this->line("Producción: " . ($productionResult ? "✅ Usuario encontrado" : "❌ Usuario NO encontrado"));
        
        if ($sandboxResult && !$productionResult) {
            $this->line('');
            $this->warn("⚠️  El usuario existe en SANDBOX pero NO en PRODUCCIÓN");
            $this->warn("⚠️  Esto confirma que estábamos comparando contra el entorno incorrecto");
        } elseif (!$sandboxResult && $productionResult) {
            $this->line('');
            $this->warn("⚠️  El usuario existe en PRODUCCIÓN pero NO en SANDBOX");
            $this->warn("⚠️  Necesitamos cambiar la configuración a producción");
        } elseif (!$sandboxResult && !$productionResult) {
            $this->line('');
            $this->warn("⚠️  El usuario NO existe en ningún entorno");
            $this->warn("⚠️  Puede ser que el email no esté registrado o haya un problema con la búsqueda");
        } else {
            $this->line('');
            $this->info("✅ El usuario existe en ambos entornos");
        }
    }

    private function testEnvironment($environment, $email)
    {
        $config = config('services.baremetrics');
        
        if ($environment === 'sandbox') {
            $apiKey = $config['sandbox_key'];
            $baseUrl = $config['sandbox_url'];
        } else {
            $apiKey = $config['live_key'];
            $baseUrl = $config['production_url'];
        }

        $this->line("   🔑 API Key: " . substr($apiKey, 0, 10) . "...");
        $this->line("   🌐 URL: {$baseUrl}");
        
        try {
            // Probar conexión básica
            $this->line("   🔍 Probando conexión básica...");
            $accountResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->get($baseUrl . '/account');

            if ($accountResponse->successful()) {
                $accountData = $accountResponse->json();
                $this->line("   ✅ Conexión exitosa - Account: " . ($accountData['name'] ?? 'N/A'));
            } else {
                $this->line("   ❌ Error de conexión: " . $accountResponse->status());
                $this->line("   📄 Respuesta: " . $accountResponse->body());
                return false;
            }

            // Probar búsqueda de usuarios
            $this->line("   🔍 Buscando usuario por email...");
            $customersResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->get($baseUrl . '/customers', [
                'email' => $email,
                'per_page' => 10
            ]);

            if ($customersResponse->successful()) {
                $customersData = $customersResponse->json();
                $customers = $customersData['customers'] ?? [];
                
                if (!empty($customers)) {
                    $this->line("   ✅ Usuario encontrado!");
                    foreach ($customers as $customer) {
                        $this->line("      📧 Email: " . ($customer['email'] ?? 'N/A'));
                        $this->line("      👤 Nombre: " . ($customer['name'] ?? 'N/A'));
                        $this->line("      🆔 ID: " . ($customer['id'] ?? 'N/A'));
                    }
                    return true;
                } else {
                    $this->line("   ❌ Usuario NO encontrado");
                    return false;
                }
            } else {
                $this->line("   ❌ Error en búsqueda: " . $customersResponse->status());
                $this->line("   📄 Respuesta: " . $customersResponse->body());
                return false;
            }

        } catch (\Exception $e) {
            $this->line("   ❌ Excepción: " . $e->getMessage());
            return false;
        }
    }
}
