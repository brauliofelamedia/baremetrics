<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use Illuminate\Support\Facades\Log;

class GetTotalGHLContacts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:get-total-contacts 
                           {--limit= : Límite máximo de contactos a procesar}
                           {--show-sample : Mostrar muestra de contactos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Obtener el total real de contactos en GoHighLevel sin filtros';

    protected $ghlService;

    public function __construct(GoHighLevelService $ghlService)
    {
        parent::__construct();
        $this->ghlService = $ghlService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = $this->option('limit');
        $showSample = $this->option('show-sample');

        $this->info('🔍 OBTENIENDO TOTAL DE CONTACTOS EN GOHIGHLEVEL');
        $this->info('================================================');
        $this->newLine();

        try {
            $startTime = microtime(true);
            $totalContacts = 0;
            $page = 1;
            $hasMore = true;
            $contactsWithEmail = 0;
            $contactsWithTags = 0;
            $sampleContacts = [];

            $this->info('📊 Iniciando conteo de contactos...');
            $this->newLine();

            // Usar pageLimit máximo permitido por la API
            $pageLimit = 500; // Máximo permitido por la API de GoHighLevel

            while ($hasMore) {
                $this->line("📄 Procesando página {$page}...");
                
                $response = $this->ghlService->getContacts('', $page, $pageLimit);
                
                if (!$response || empty($response['contacts'])) {
                    $this->warn("⚠️  No se obtuvieron contactos en la página {$page}");
                    break;
                }

                $contacts = $response['contacts'];
                $pageTotal = count($contacts);
                $totalContacts += $pageTotal;

                // Estadísticas
                foreach ($contacts as $contact) {
                    if (!empty($contact['email'])) {
                        $contactsWithEmail++;
                    }
                    
                    if (!empty($contact['tags'])) {
                        $contactsWithTags++;
                    }
                    
                    // Guardar muestra si se solicita
                    if ($showSample && count($sampleContacts) < 10) {
                        $sampleContacts[] = [
                            'id' => $contact['id'] ?? 'N/A',
                            'email' => $contact['email'] ?? 'N/A',
                            'name' => trim(($contact['firstName'] ?? '') . ' ' . ($contact['lastName'] ?? '')),
                            'status' => $contact['status'] ?? 'N/A',
                            'tags' => $contact['tags'] ?? [],
                            'created_at' => $contact['dateAdded'] ?? 'N/A'
                        ];
                    }
                }

                $this->line("  ✅ {$pageTotal} contactos obtenidos (Total: {$totalContacts})");

                // Verificar paginación
                if (isset($response['meta']['pagination'])) {
                    $pagination = $response['meta']['pagination'];
                    $hasMore = $pagination['has_more'] ?? false;
                } else {
                    // Si no hay información de paginación, continuar si obtuvimos contactos
                    $hasMore = $pageTotal > 0;
                }

                $page++;

                // Verificar límite si se especifica
                if ($limit && $totalContacts >= $limit) {
                    $this->warn("⚠️  Límite alcanzado: {$limit} contactos");
                    break;
                }

                // Pausa pequeña para no sobrecargar la API
                usleep(100000); // 0.1 segundos
            }

            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);

            $this->newLine();
            $this->info('📊 RESULTADOS FINALES:');
            $this->info('====================');
            $this->line("🎯 Total de contactos: {$totalContacts}");
            $this->line("📧 Contactos con email: {$contactsWithEmail}");
            $this->line("🏷️  Contactos con tags: {$contactsWithTags}");
            $this->line("⏱️  Tiempo total: {$duration} segundos");
            $this->line("📄 Páginas procesadas: " . ($page - 1));
            
            if ($totalContacts > 0) {
                $speed = round($totalContacts / $duration, 2);
                $this->line("⚡ Velocidad: {$speed} contactos/segundo");
                
                $emailPercentage = round(($contactsWithEmail / $totalContacts) * 100, 2);
                $tagsPercentage = round(($contactsWithTags / $totalContacts) * 100, 2);
                $this->line("📊 % con email: {$emailPercentage}%");
                $this->line("📊 % con tags: {$tagsPercentage}%");
            }

            // Mostrar muestra si se solicita
            if ($showSample && !empty($sampleContacts)) {
                $this->newLine();
                $this->info('📋 MUESTRA DE CONTACTOS:');
                $this->info('========================');
                
                foreach ($sampleContacts as $index => $contact) {
                    $this->line(($index + 1) . ". ID: {$contact['id']}");
                    $this->line("   Email: {$contact['email']}");
                    $this->line("   Nombre: {$contact['name']}");
                    $this->line("   Estado: {$contact['status']}");
                    $this->line("   Tags: " . (empty($contact['tags']) ? 'Ninguno' : implode(', ', $contact['tags'])));
                    $this->line("   Creado: {$contact['created_at']}");
                    $this->newLine();
                }
            }

            $this->info('✅ Conteo completado exitosamente');
            
        } catch (\Exception $e) {
            $this->error("❌ Error durante el conteo: " . $e->getMessage());
            Log::error('Error obteniendo total de contactos GHL', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }
}
