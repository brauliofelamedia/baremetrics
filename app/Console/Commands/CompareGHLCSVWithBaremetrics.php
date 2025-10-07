<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;
use Illuminate\Support\Facades\Storage;

class CompareGHLCSVWithBaremetrics extends Command
{
    protected $signature = 'ghl:compare-csv {--file=storage/csv/creetelo_ghl.csv} {--tags=} {--exclude-tags=unsubscribe} {--limit=0} {--format=table} {--save}';
    protected $description = 'Compare GHL users from CSV export with Baremetrics users';

    protected $baremetricsService;

    public function __construct(BaremetricsService $baremetricsService)
    {
        parent::__construct();
        $this->baremetricsService = $baremetricsService;
    }

    public function handle()
    {
        $csvFile = $this->option('file');
        $tagsInput = $this->option('tags');
        $excludeTagsInput = $this->option('exclude-tags');
        $limit = (int) $this->option('limit');
        $format = $this->option('format');
        $save = $this->option('save');

        // Parse tags (opcional, para casos donde necesitemos filtrar adicionalmente)
        $tags = [];
        if ($tagsInput) {
            $tags = array_map('trim', explode(',', $tagsInput));
        }

        $excludeTags = [];
        if ($excludeTagsInput) {
            $excludeTags = array_map('trim', explode(',', $excludeTagsInput));
        }

        $this->info("🔍 Comparando usuarios GHL (CSV) vs Baremetrics...");
        $this->line('');
        $this->info("📋 Configuración:");
        $this->line("   • Archivo CSV: {$csvFile}");
        $this->line("   • Tags incluidos: " . (empty($tags) ? 'CSV ya filtrado por GHL' : implode(', ', $tags)));
        $this->line("   • Tags excluidos: " . (empty($excludeTags) ? 'Ninguno' : implode(', ', $excludeTags)));
        $this->line("   • Límite: " . ($limit > 0 ? $limit : 'Sin límite'));
        $this->line("   • Formato: {$format}");
        $this->line("   • Guardar archivo: " . ($save ? 'Sí' : 'No'));
        $this->line('');

        // Verificar que el archivo existe
        if (!file_exists($csvFile)) {
            $this->error("❌ El archivo CSV no existe: {$csvFile}");
            $this->line('');
            $this->line("💡 Asegúrate de que el archivo esté en la ubicación correcta.");
            $this->line("   Puedes especificar una ruta diferente con: --file=ruta/al/archivo.csv");
            return;
        }

        try {
            // Leer usuarios del CSV
            $this->line("🔍 Leyendo usuarios del archivo CSV...");
            $ghlUsers = $this->readCSVUsers($csvFile, $tags, $excludeTags, $limit);
            
            if (empty($ghlUsers)) {
                $this->warn("⚠️ No se encontraron usuarios válidos en el CSV");
                return;
            }

            $this->line("✅ Encontrados " . count($ghlUsers) . " usuarios de GHL");
            $this->line('');

            // Obtener emails de Baremetrics
            $this->line("🔍 Obteniendo emails de Baremetrics...");
            $baremetricsEmails = $this->getBaremetricsEmails();
            $this->line("✅ Encontrados " . count($baremetricsEmails) . " emails en Baremetrics");
            $this->line('');

            // Comparar usuarios
            $this->line("🔄 Analizando usuarios...");
            $this->line('');

            $commonUsers = [];
            $missingUsers = [];

            foreach ($ghlUsers as $user) {
                $email = strtolower(trim($user['email']));
                
                if (in_array($email, $baremetricsEmails)) {
                    $commonUsers[] = $user;
                } else {
                    $missingUsers[] = $user;
                }
            }

            // Mostrar resultados
            $this->displayResults($ghlUsers, $baremetricsEmails, $commonUsers, $missingUsers, $format, $save);

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
        }
    }

    private function readCSVUsers($csvFile, $tags, $excludeTags, $limit)
    {
        $users = [];
        $rowCount = 0;
        $validCount = 0;

        if (($handle = fopen($csvFile, "r")) !== FALSE) {
            // Leer la primera línea (headers)
            $headers = fgetcsv($handle, 1000, ",");
            
            if (!$headers) {
                throw new \Exception("No se pudieron leer los headers del CSV");
            }

            // Mapear índices de columnas
            $columnMap = $this->mapColumns($headers);
            
            $this->line("   📄 Headers encontrados: " . implode(', ', $headers));
            $this->line("   📄 Mapeo de columnas:");
            foreach ($columnMap as $key => $index) {
                $this->line("     • {$key}: columna " . ($index + 1));
            }
            $this->line('');

            // Leer datos
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE && ($limit === 0 || $validCount < $limit)) {
                $rowCount++;
                
                if (count($data) < count($headers)) {
                    continue; // Saltar filas incompletas
                }

                $user = $this->parseUserFromCSV($data, $columnMap);
                
                if (!$user || !$this->isValidEmail($user['email'])) {
                    continue;
                }

                // Solo aplicar filtros adicionales si se especificaron
                if (!empty($tags)) {
                    $userTags = $this->parseTags($user['tags']);
                    $hasMatchingTag = !empty(array_intersect($tags, $userTags));
                    
                    if (!$hasMatchingTag) {
                        continue;
                    }
                }

                // Excluir usuarios con tags excluidos si se especificaron
                if (!empty($excludeTags)) {
                    $userTags = $this->parseTags($user['tags']);
                    $hasExcludedTags = !empty(array_intersect($excludeTags, $userTags));
                    
                    if ($hasExcludedTags) {
                        continue;
                    }
                }

                $users[] = $user;
                $validCount++;

                // Mostrar progreso cada 100 usuarios
                if ($validCount % 100 === 0) {
                    $this->line("     • Progreso: {$validCount} usuarios válidos procesados");
                }
            }
            
            fclose($handle);
        }

        $this->line("   • Total filas procesadas: {$rowCount}");
        $this->line("   • Usuarios válidos encontrados: {$validCount}");
        $this->line('');

        return $users;
    }

    private function mapColumns($headers)
    {
        $map = [];
        
        foreach ($headers as $index => $header) {
            $header = trim($header);
            
            switch (strtolower($header)) {
                case 'contact id':
                    $map['id'] = $index;
                    break;
                case 'first name':
                    $map['first_name'] = $index;
                    break;
                case 'last name':
                    $map['last_name'] = $index;
                    break;
                case 'business name':
                    $map['business_name'] = $index;
                    break;
                case 'company name':
                    $map['company_name'] = $index;
                    break;
                case 'phone':
                    $map['phone'] = $index;
                    break;
                case 'email':
                    $map['email'] = $index;
                    break;
                case 'created':
                    $map['created'] = $index;
                    break;
                case 'last activity':
                    $map['last_activity'] = $index;
                    break;
                case 'tags':
                    $map['tags'] = $index;
                    break;
                case 'additional emails':
                    $map['additional_emails'] = $index;
                    break;
                case 'additional phones':
                    $map['additional_phones'] = $index;
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
            'id' => isset($columnMap['id']) ? trim($data[$columnMap['id']]) : '',
            'name' => $name,
            'email' => isset($columnMap['email']) ? strtolower(trim($data[$columnMap['email']])) : '',
            'phone' => isset($columnMap['phone']) ? trim($data[$columnMap['phone']]) : '',
            'company' => isset($columnMap['company_name']) ? trim($data[$columnMap['company_name']]) : '',
            'business_name' => isset($columnMap['business_name']) ? trim($data[$columnMap['business_name']]) : '',
            'tags' => isset($columnMap['tags']) ? trim($data[$columnMap['tags']]) : '',
            'created' => isset($columnMap['created']) ? trim($data[$columnMap['created']]) : '',
            'last_activity' => isset($columnMap['last_activity']) ? trim($data[$columnMap['last_activity']]) : '',
            'additional_emails' => isset($columnMap['additional_emails']) ? trim($data[$columnMap['additional_emails']]) : '',
            'additional_phones' => isset($columnMap['additional_phones']) ? trim($data[$columnMap['additional_phones']]) : '',
        ];
    }

    private function parseTags($tagsString)
    {
        if (empty($tagsString)) {
            return [];
        }

        // Los tags pueden estar separados por comas, punto y coma, o pipes
        $tags = preg_split('/[,;|]+/', $tagsString);
        return array_map('trim', array_filter($tags));
    }

    private function getBaremetricsEmails()
    {
        $emails = [];
        
        try {
            $sources = $this->baremetricsService->getSources();
            
            if (empty($sources) || !isset($sources['sources'])) {
                $this->warn("   ⚠️ No se encontraron fuentes en Baremetrics");
                return $emails;
            }

            foreach ($sources['sources'] as $source) {
                $sourceId = $source['id'];
                $this->line("   📄 Procesando source: {$sourceId}");
                
                $customers = $this->baremetricsService->getCustomersAll($sourceId);
                
                if (!empty($customers)) {
                    foreach ($customers as $customer) {
                        if (!empty($customer['email'])) {
                            $emails[] = strtolower(trim($customer['email']));
                        }
                    }
                }
                
                $this->line("     • {$sourceId}: " . count($customers) . " customers");
            }
        } catch (\Exception $e) {
            $this->warn("   ⚠️ Error obteniendo emails de Baremetrics: " . $e->getMessage());
        }

        return array_unique($emails);
    }

    private function displayResults($ghlUsers, $baremetricsEmails, $commonUsers, $missingUsers, $format, $save)
    {
        $this->showCompleteSummary($ghlUsers, $baremetricsEmails, $commonUsers, $missingUsers);
        
        if ($format === 'table' && count($missingUsers) > 0) {
            $this->showMissingUsersTable($missingUsers);
        }

        if ($save) {
            $this->saveToFile($ghlUsers, $baremetricsEmails, $commonUsers, $missingUsers);
        }
    }

    private function showCompleteSummary($ghlUsers, $baremetricsEmails, $commonUsers, $missingUsers)
    {
        $this->line('');
        $this->line("📊 RESUMEN COMPLETO DE LA COMPARACIÓN");
        $this->line("=====================================");
        $this->line("👥 Total usuarios GHL (filtrados): " . count($ghlUsers));
        $this->line("👥 Total emails Baremetrics: " . count($baremetricsEmails));
        $this->line("✅ Usuarios en AMBOS sistemas: " . count($commonUsers));
        $this->line("❌ Usuarios GHL faltantes en Baremetrics: " . count($missingUsers));
        $this->line('');
        
        $this->line("📈 PORCENTAJES:");
        $totalGHL = count($ghlUsers);
        if ($totalGHL > 0) {
            $syncPercentage = round((count($commonUsers) / $totalGHL) * 100, 1);
            $missingPercentage = round((count($missingUsers) / $totalGHL) * 100, 1);
            $this->line("   • Sincronizados: {$syncPercentage}%");
            $this->line("   • Faltantes: {$missingPercentage}%");
        }
        $this->line('');
    }

    private function showMissingUsersTable($missingUsers)
    {
        if (empty($missingUsers)) {
            $this->line("✅ No hay usuarios faltantes");
            return;
        }

        $this->line("⚠️ USUARIOS DE GHL FALTANTES EN BAREMETRICS:");
        $this->line("=============================================");
        
        $count = 0;
        foreach ($missingUsers as $user) {
            if ($count >= 50) { // Limitar a 50 para no saturar la pantalla
                $this->line("   ... y " . (count($missingUsers) - 50) . " más");
                break;
            }
            
            $tagsStr = $this->parseTags($user['tags']);
            $tagsDisplay = implode(', ', array_slice($tagsStr, 0, 3));
            if (count($tagsStr) > 3) {
                $tagsDisplay .= '...';
            }
            
            $this->line("   • {$user['email']} - {$user['name']} - Tags: {$tagsDisplay}");
            $count++;
        }
        $this->line('');
    }

    private function saveToFile($ghlUsers, $baremetricsEmails, $commonUsers, $missingUsers)
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        
        // Guardar resumen
        $summaryData = [
            'timestamp' => now()->toISOString(),
            'summary' => [
                'total_ghl_users' => count($ghlUsers),
                'total_baremetrics_emails' => count($baremetricsEmails),
                'common_users' => count($commonUsers),
                'missing_users' => count($missingUsers),
                'sync_percentage' => count($ghlUsers) > 0 ? round((count($commonUsers) / count($ghlUsers)) * 100, 1) : 0,
                'missing_percentage' => count($ghlUsers) > 0 ? round((count($missingUsers) / count($ghlUsers)) * 100, 1) : 0,
            ],
            'common_users' => $commonUsers,
            'missing_users' => $missingUsers,
        ];

        $jsonFile = "storage/csv/ghl_baremetrics_comparison_{$timestamp}.json";
        file_put_contents($jsonFile, json_encode($summaryData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        // Guardar CSV con usuarios faltantes
        $csvFile = "storage/csv/ghl_missing_users_{$timestamp}.csv";
        $this->generateMissingUsersCSV($missingUsers, $csvFile);
        
        $this->line("💾 Archivos guardados:");
        $this->line("   • JSON completo: {$jsonFile}");
        $this->line("   • CSV usuarios faltantes: {$csvFile}");
        $this->line('');
    }

    private function generateMissingUsersCSV($missingUsers, $filename)
    {
        $file = fopen($filename, 'w');
        
        // Headers
        fputcsv($file, [
            'Email', 'Name', 'Phone', 'Company', 'Business Name', 
            'Tags', 'Created', 'Last Activity', 'Additional Emails', 'Additional Phones'
        ]);
        
        // Data
        foreach ($missingUsers as $user) {
            fputcsv($file, [
                $user['email'],
                $user['name'],
                $user['phone'],
                $user['company'],
                $user['business_name'],
                $user['tags'],
                $user['created'],
                $user['last_activity'],
                $user['additional_emails'],
                $user['additional_phones']
            ]);
        }
        
        fclose($file);
    }

    private function isValidEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
