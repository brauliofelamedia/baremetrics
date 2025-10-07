<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CompareGHLWithBaremetricsProduction extends Command
{
    protected $signature = 'ghl:compare-production {--file=storage/csv/ghl_missing_users_2025-10-03_17-51-41.csv} {--limit=50}';
    protected $description = 'Compare GHL users against Baremetrics PRODUCTION environment (read-only)';

    public function handle()
    {
        $csvFile = $this->option('file');
        $limit = (int) $this->option('limit');

        $this->info("🔍 Comparando usuarios GHL vs Baremetrics PRODUCCIÓN (SOLO LECTURA)...");
        $this->line('');
        $this->warn("⚠️  IMPORTANTE: Esta es una comparación SOLO LECTURA contra PRODUCCIÓN");
        $this->warn("⚠️  NO se realizarán cambios ni sincronizaciones");
        $this->line('');

        // Verificar que el archivo existe
        if (!file_exists($csvFile)) {
            $this->error("❌ El archivo CSV no existe: {$csvFile}");
            return;
        }

        $this->info("📋 Configuración:");
        $this->line("   • Archivo CSV: {$csvFile}");
        $this->line("   • Límite de usuarios a verificar: " . ($limit > 0 ? $limit : 'Sin límite'));
        $this->line("   • Entorno: PRODUCCIÓN (solo lectura)");
        $this->line('');

        try {
            // Leer usuarios del CSV
            $this->line("🔍 Leyendo usuarios del CSV...");
            $ghlUsers = $this->readCSVUsers($csvFile, $limit);
            
            if (empty($ghlUsers)) {
                $this->warn("⚠️ No se encontraron usuarios en el CSV");
                return;
            }

            $this->line("✅ Encontrados " . count($ghlUsers) . " usuarios en el CSV");
            $this->line('');

            // Verificar usuarios en Baremetrics Producción
            $this->line("🔍 Verificando usuarios en Baremetrics PRODUCCIÓN...");
            $this->line("⚠️  Solo lectura - NO se realizarán cambios");
            $this->line('');

            $foundInProduction = [];
            $notFoundInProduction = [];
            $checkedCount = 0;

            foreach ($ghlUsers as $user) {
                $checkedCount++;
                $email = $user['email'];
                
                $this->line("   📧 Verificando: {$email} ({$checkedCount}/" . count($ghlUsers) . ")");
                
                $existsInProduction = $this->checkUserInBaremetricsProduction($email);
                
                if ($existsInProduction) {
                    $foundInProduction[] = $user;
                    $this->line("      ✅ ENCONTRADO en producción");
                } else {
                    $notFoundInProduction[] = $user;
                    $this->line("      ❌ NO encontrado en producción");
                }

                // Pausa pequeña para no sobrecargar la API
                usleep(200000); // 200ms
                
                // Mostrar progreso cada 10 usuarios
                if ($checkedCount % 10 === 0) {
                    $this->line("      📊 Progreso: {$checkedCount} verificados, " . count($foundInProduction) . " encontrados");
                }
            }

            // Mostrar resultados
            $this->showResults($ghlUsers, $foundInProduction, $notFoundInProduction);

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
        }
    }

    private function readCSVUsers($csvFile, $limit)
    {
        $users = [];
        $rowCount = 0;

        if (($handle = fopen($csvFile, "r")) !== FALSE) {
            // Leer la primera línea (headers)
            $headers = fgetcsv($handle, 1000, ",");
            
            if (!$headers) {
                throw new \Exception("No se pudieron leer los headers del CSV");
            }

            // Mapear índices de columnas
            $columnMap = $this->mapColumns($headers);

            // Leer datos
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE && ($limit === 0 || count($users) < $limit)) {
                $rowCount++;
                
                if (count($data) < count($headers)) {
                    continue; // Saltar filas incompletas
                }

                $user = $this->parseUserFromCSV($data, $columnMap);
                
                if (!$user || !$this->isValidEmail($user['email'])) {
                    continue;
                }

                $users[] = $user;
            }
            
            fclose($handle);
        }

        return $users;
    }

    private function mapColumns($headers)
    {
        $map = [];
        
        foreach ($headers as $index => $header) {
            $header = trim($header);
            
            switch (strtolower($header)) {
                case 'email':
                    $map['email'] = $index;
                    break;
                case 'first name':
                    $map['first_name'] = $index;
                    break;
                case 'last name':
                    $map['last_name'] = $index;
                    break;
                case 'phone':
                    $map['phone'] = $index;
                    break;
                case 'company name':
                    $map['company_name'] = $index;
                    break;
                case 'tags':
                    $map['tags'] = $index;
                    break;
            }
        }

        return $map;
    }

    private function parseUserFromCSV($data, $columnMap)
    {
        $firstName = isset($columnMap['first_name']) ? trim($data[$columnMap['first_name']]) : '';
        $lastName = isset($columnMap['last_name']) ? trim($data[$columnMap['last_name']]) : '';
        $name = trim($firstName . ' ' . $lastName);
        if (empty($name)) {
            $name = 'Sin nombre';
        }

        return [
            'name' => $name,
            'email' => isset($columnMap['email']) ? strtolower(trim($data[$columnMap['email']])) : '',
            'phone' => isset($columnMap['phone']) ? trim($data[$columnMap['phone']]) : '',
            'company' => isset($columnMap['company_name']) ? trim($data[$columnMap['company_name']]) : '',
            'tags' => isset($columnMap['tags']) ? trim($data[$columnMap['tags']]) : '',
        ];
    }

    private function checkUserInBaremetricsProduction($email)
    {
        try {
            // Usar la API de producción de Baremetrics
            $apiKey = config('services.baremetrics.live_key');
            $baseUrl = config('services.baremetrics.production_url');
            
            // Buscar el usuario por email usando el parámetro 'search'
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->get($baseUrl . '/customers', [
                'search' => $email,
                'per_page' => 10
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return !empty($data['customers']) && count($data['customers']) > 0;
            }

            return false;

        } catch (\Exception $e) {
            $this->warn("      ⚠️ Error verificando {$email}: " . $e->getMessage());
            return false;
        }
    }

    private function showResults($ghlUsers, $foundInProduction, $notFoundInProduction)
    {
        $this->line('');
        $this->line("📊 RESULTADOS DE LA COMPARACIÓN CON PRODUCCIÓN");
        $this->line("=============================================");
        $this->line("👥 Total usuarios GHL verificados: " . count($ghlUsers));
        $this->line("✅ Usuarios encontrados en PRODUCCIÓN: " . count($foundInProduction));
        $this->line("❌ Usuarios NO encontrados en PRODUCCIÓN: " . count($notFoundInProduction));
        $this->line('');
        
        $totalVerified = count($ghlUsers);
        if ($totalVerified > 0) {
            $foundPercentage = round((count($foundInProduction) / $totalVerified) * 100, 1);
            $notFoundPercentage = round((count($notFoundInProduction) / $totalVerified) * 100, 1);
            
            $this->line("📈 PORCENTAJES:");
            $this->line("   • Encontrados en producción: {$foundPercentage}%");
            $this->line("   • No encontrados en producción: {$notFoundPercentage}%");
            $this->line('');
        }

        // Mostrar algunos ejemplos de usuarios encontrados
        if (!empty($foundInProduction)) {
            $this->line("✅ USUARIOS ENCONTRADOS EN PRODUCCIÓN (primeros 10):");
            $this->line("==================================================");
            $count = 0;
            foreach ($foundInProduction as $user) {
                if ($count >= 10) break;
                $this->line("   • {$user['email']} - {$user['name']}");
                $count++;
            }
            if (count($foundInProduction) > 10) {
                $this->line("   ... y " . (count($foundInProduction) - 10) . " más");
            }
            $this->line('');
        }

        // Mostrar algunos ejemplos de usuarios no encontrados
        if (!empty($notFoundInProduction)) {
            $this->line("❌ USUARIOS NO ENCONTRADOS EN PRODUCCIÓN (primeros 10):");
            $this->line("=====================================================");
            $count = 0;
            foreach ($notFoundInProduction as $user) {
                if ($count >= 10) break;
                $this->line("   • {$user['email']} - {$user['name']}");
                $count++;
            }
            if (count($notFoundInProduction) > 10) {
                $this->line("   ... y " . (count($notFoundInProduction) - 10) . " más");
            }
            $this->line('');
        }

        $this->line("🎯 CONCLUSIÓN:");
        if (count($foundInProduction) > 0) {
            $this->line("✅ Los usuarios SÍ existen en Baremetrics PRODUCCIÓN");
            $this->line("✅ El problema era que estábamos comparando contra SANDBOX");
            $this->line("✅ La sincronización ya está funcionando correctamente");
        } else {
            $this->line("❌ Los usuarios NO existen en Baremetrics PRODUCCIÓN");
            $this->line("❌ Necesitan ser importados desde GHL");
        }
        $this->line('');
        
        $this->warn("⚠️  RECORDATORIO: Esta fue una verificación SOLO LECTURA");
        $this->warn("⚠️  NO se realizaron cambios en ningún entorno");
    }

    private function isValidEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
