<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;

class ListGHLContacts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:list-contacts 
                           {--search= : Buscar contactos por término}
                           {--limit=10 : Límite de contactos a mostrar}
                           {--debug : Mostrar información de debugging}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lista contactos de GoHighLevel para debugging';

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
        $search = $this->option('search');
        $limit = (int) $this->option('limit');
        $debug = $this->option('debug');

        $this->info('📋 Listando contactos de GoHighLevel...');
        
        if ($search) {
            $this->info("🔍 Buscando contactos que contengan: {$search}");
        }

        try {
            // Obtener contactos
            $contacts = $this->ghlService->getContacts($search);
            
            if ($debug) {
                $this->info('📋 Respuesta completa de GoHighLevel:');
                $this->line(json_encode($contacts, JSON_PRETTY_PRINT));
            }

            if (empty($contacts['contacts'])) {
                $this->warn('⚠️  No se encontraron contactos');
                if ($search) {
                    $this->info("💡 Intenta buscar sin filtro para ver todos los contactos disponibles");
                }
                return 0;
            }

            $contactList = $contacts['contacts'];
            $totalFound = count($contactList);
            
            $this->info("✅ Se encontraron {$totalFound} contactos");
            
            // Aplicar límite
            if ($limit > 0 && $totalFound > $limit) {
                $contactList = array_slice($contactList, 0, $limit);
                $this->info("📊 Mostrando los primeros {$limit} contactos");
            }

            // Mostrar tabla de contactos
            $headers = ['Email', 'Nombre', 'Teléfono', 'País', 'Estado', 'ID'];
            $rows = [];

            foreach ($contactList as $contact) {
                $rows[] = [
                    $contact['email'] ?? 'N/A',
                    $contact['name'] ?? 'N/A',
                    $contact['phone'] ?? 'N/A',
                    $contact['country'] ?? 'N/A',
                    $contact['state'] ?? 'N/A',
                    $contact['id'] ?? 'N/A'
                ];
            }

            $this->table($headers, $rows);

            // Mostrar información adicional
            $this->newLine();
            $this->info('📊 Información adicional:');
            $this->line("• Total encontrados: {$totalFound}");
            $this->line("• Mostrados: " . count($contactList));
            
            if ($search) {
                $this->line("• Término de búsqueda: {$search}");
                $this->line("• Operador usado: 'contains'");
            }

            // Sugerencias
            $this->newLine();
            $this->info('💡 Sugerencias:');
            $this->line("• Para buscar un email específico, usa: --search=email@ejemplo.com");
            $this->line("• Para ver más contactos, usa: --limit=50");
            $this->line("• Para debugging completo, usa: --debug");

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            $this->warn('💡 Verifica la configuración de GoHighLevel ejecutando: php artisan ghl:check-config');
            return 1;
        }

        return 0;
    }
}
