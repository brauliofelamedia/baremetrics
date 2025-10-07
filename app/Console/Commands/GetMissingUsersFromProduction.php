<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class GetMissingUsersFromProduction extends Command
{
    protected $signature = 'ghl:get-missing-users {--file=public/storage/csv/creetelo_ghl.csv} {--limit=0} {--save}';
    protected $description = 'Get list of GHL users missing from Baremetrics PRODUCTION (read-only)';

    public function handle()
    {
        $csvFile = $this->option('file');
        $limit = (int) $this->option('limit');
        $save = $this->option('save');

        $this->info("🔍 Identificando usuarios GHL faltantes en Baremetrics PRODUCCIÓN...");
        $this->line('');
        $this->warn("⚠️  IMPORTANTE: Esta es una identificación SOLO LECTURA");
        $this->warn("⚠️  NO se realizarán importaciones ni cambios");
        $this->line('');

        // Verificar que el archivo existe
        if (!file_exists($csvFile)) {
            $this->error("❌ El archivo CSV no existe: {$csvFile}");
            return;
        }

        $this->info("📋 Configuración:");
        $this->line("   • Archivo CSV: {$csvFile}");
        $this->line("   • Límite: " . ($limit > 0 ? $limit : 'Sin límite'));
        $this->line("   • Entorno: PRODUCCIÓN (solo lectura)");
        $this->line("   • Guardar archivo: " . ($save ? 'Sí' : 'No'));
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
            $missingFromProduction = [];
            $checkedCount = 0;
            $totalUsers = count($ghlUsers);

            foreach ($ghlUsers as $user) {
                $checkedCount++;
                $email = $user['email'];
                
                $this->line("   📧 Verificando: {$email} ({$checkedCount}/{$totalUsers})");
                
                $existsInProduction = $this->checkUserInBaremetricsProduction($email);
                
                if ($existsInProduction) {
                    $foundInProduction[] = $user;
                    $this->line("      ✅ ENCONTRADO en producción");
                } else {
                    $missingFromProduction[] = $user;
                    $this->line("      ❌ NO encontrado - FALTANTE");
                }

                // Pausa pequeña para no sobrecargar la API
                usleep(200000); // 200ms
                
                // Mostrar progreso cada 50 usuarios
                if ($checkedCount % 50 === 0) {
                    $this->line("      📊 Progreso: {$checkedCount} verificados, " . count($foundInProduction) . " encontrados, " . count($missingFromProduction) . " faltantes");
                }
            }

            // Mostrar resultados
            $this->showResults($ghlUsers, $foundInProduction, $missingFromProduction);

            // Guardar archivos si se solicita
            if ($save && !empty($missingFromProduction)) {
                $this->saveMissingUsers($missingFromProduction);
            }

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
                case 'business name':
                    $map['business_name'] = $index;
                    break;
                case 'tags':
                    $map['tags'] = $index;
                    break;
                case 'created':
                    $map['created'] = $index;
                    break;
                case 'last activity':
                    $map['last_activity'] = $index;
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
            'company' => isset($columnMap['company_name']) ? trim($data[$columnMap['company_name']]) : 
                        (isset($columnMap['business_name']) ? trim($data[$columnMap['business_name']]) : ''),
            'tags' => isset($columnMap['tags']) ? trim($data[$columnMap['tags']]) : '',
            'created' => isset($columnMap['created']) ? trim($data[$columnMap['created']]) : '',
            'last_activity' => isset($columnMap['last_activity']) ? trim($data[$columnMap['last_activity']]) : '',
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
                $customers = $data['customers'] ?? [];
                
                // Verificar si el email exacto existe
                foreach ($customers as $customer) {
                    $customerEmail = strtolower(trim($customer['email'] ?? ''));
                    $searchEmail = strtolower(trim($email));
                    
                    if ($customerEmail === $searchEmail) {
                        return true;
                    }
                }
                
                return false;
            }

            return false;

        } catch (\Exception $e) {
            $this->warn("      ⚠️ Error verificando {$email}: " . $e->getMessage());
            return false;
        }
    }

    private function showResults($ghlUsers, $foundInProduction, $missingFromProduction)
    {
        $this->line('');
        $this->line("📊 RESULTADOS DE LA IDENTIFICACIÓN");
        $this->line("==================================");
        $this->line("👥 Total usuarios GHL verificados: " . count($ghlUsers));
        $this->line("✅ Usuarios encontrados en PRODUCCIÓN: " . count($foundInProduction));
        $this->line("❌ Usuarios FALTANTES en PRODUCCIÓN: " . count($missingFromProduction));
        $this->line('');
        
        $totalVerified = count($ghlUsers);
        if ($totalVerified > 0) {
            $foundPercentage = round((count($foundInProduction) / $totalVerified) * 100, 1);
            $missingPercentage = round((count($missingFromProduction) / $totalVerified) * 100, 1);
            
            $this->line("📈 PORCENTAJES:");
            $this->line("   • Ya sincronizados: {$foundPercentage}%");
            $this->line("   • Faltantes por importar: {$missingPercentage}%");
            $this->line('');
        }

        // Mostrar usuarios faltantes
        if (!empty($missingFromProduction)) {
            $this->line("❌ USUARIOS FALTANTES EN PRODUCCIÓN:");
            $this->line("====================================");
            $count = 0;
            foreach ($missingFromProduction as $user) {
                $count++;
                $this->line("   {$count}. {$user['email']} - {$user['name']}");
                if (!empty($user['company'])) {
                    $this->line("      🏢 Empresa: {$user['company']}");
                }
                if (!empty($user['tags'])) {
                    $this->line("      🏷️ Tags: {$user['tags']}");
                }
                if (!empty($user['phone'])) {
                    $this->line("      📞 Teléfono: {$user['phone']}");
                }
                $this->line('');
                
                // Mostrar solo los primeros 20 para no saturar la pantalla
                if ($count >= 20) {
                    $remaining = count($missingFromProduction) - 20;
                    if ($remaining > 0) {
                        $this->line("   ... y {$remaining} usuarios más");
                    }
                    break;
                }
            }
            $this->line('');
        }

        $this->line("🎯 CONCLUSIÓN:");
        if (count($missingFromProduction) > 0) {
            $this->line("❌ Se encontraron " . count($missingFromProduction) . " usuarios faltantes");
            $this->line("📥 Estos usuarios necesitan ser importados a Baremetrics");
            $this->line("📋 Lista lista para importación masiva");
        } else {
            $this->line("✅ Todos los usuarios ya están sincronizados");
            $this->line("🎉 No se requiere importación adicional");
        }
        $this->line('');
        
        $this->warn("⚠️  RECORDATORIO: Esta fue una identificación SOLO LECTURA");
        $this->warn("⚠️  NO se realizaron importaciones ni cambios");
    }

    private function saveMissingUsers($missingUsers)
    {
        $timestamp = date('Y-m-d_H-i-s');
        
        // Guardar JSON completo
        $jsonFile = "storage/csv/missing_users_production_{$timestamp}.json";
        $jsonData = [
            'timestamp' => $timestamp,
            'total_missing' => count($missingUsers),
            'environment' => 'production',
            'missing_users' => $missingUsers
        ];
        
        file_put_contents($jsonFile, json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        // Guardar CSV para importación
        $csvFile = "storage/csv/missing_users_production_{$timestamp}.csv";
        $csvHandle = fopen($csvFile, 'w');
        
        // Headers del CSV
        fputcsv($csvHandle, [
            'Email',
            'Name', 
            'Company',
            'Phone',
            'Tags',
            'Created',
            'Last Activity'
        ]);
        
        // Datos
        foreach ($missingUsers as $user) {
            fputcsv($csvHandle, [
                $user['email'],
                $user['name'],
                $user['company'],
                $user['phone'],
                $user['tags'],
                $user['created'],
                $user['last_activity']
            ]);
        }
        
        fclose($csvHandle);
        
        $this->line('');
        $this->line("💾 ARCHIVOS GUARDADOS:");
        $this->line("=====================");
        $this->line("📄 JSON completo: {$jsonFile}");
        $this->line("📄 CSV para importación: {$csvFile}");
        $this->line("📊 Total usuarios faltantes: " . count($missingUsers));
    }

    private function isValidEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
