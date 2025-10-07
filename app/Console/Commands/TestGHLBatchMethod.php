<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use App\Services\BaremetricsService;

class TestGHLBatchMethod extends Command
{
    protected $signature = 'ghl:test-batch-method {--tags=} {--max=2000}';
    protected $description = 'Test the new batch method for getting GHL contacts by tags';

    protected $ghlService;

    public function __construct(GoHighLevelService $ghlService)
    {
        parent::__construct();
        $this->ghlService = $ghlService;
    }

    public function handle()
    {
        $tagsInput = $this->option('tags');
        $maxContacts = (int) $this->option('max');

        if (!$tagsInput) {
            $this->error('Debe especificar tags con --tags=tag1,tag2');
            return;
        }

        $tags = array_map('trim', explode(',', $tagsInput));
        $excludeTags = ['unsubscribe'];

        $this->info("🔍 Probando método batch para obtener contactos de GHL...");
        $this->info("📋 Tags incluidos: " . implode(', ', $tags));
        $this->info("📋 Tags excluidos: " . implode(', ', $excludeTags));
        $this->info("📋 Máximo contactos: {$maxContacts}");
        $this->line('');

        try {
            $this->line("🔍 Obteniendo usuarios de GHL usando método batch...");
            
            $response = $this->ghlService->getContactsByTagsBatch($tags, $maxContacts);
            
            if (!$response || empty($response['contacts'])) {
                $this->warn("⚠️ No se encontraron contactos");
                return;
            }

            $allContacts = $response['contacts'];
            $meta = $response['meta'] ?? [];
            
            $this->line("✅ Contactos encontrados: " . count($allContacts));
            $this->line("📊 Total procesados: " . ($meta['total_processed'] ?? 'N/A'));
            $this->line("📊 Páginas procesadas: " . ($meta['pages_processed'] ?? 'N/A'));
            $this->line('');

            // Filtrar usuarios válidos
            $validUsers = [];
            foreach ($allContacts as $contact) {
                if (!empty($contact['email']) && $this->isValidEmail($contact['email'])) {
                    $userTags = $contact['tags'] ?? [];
                    
                    // Verificar si tiene tags excluidos
                    $hasExcludedTags = !empty(array_intersect($excludeTags, $userTags));
                    
                    if (!$hasExcludedTags) {
                        $validUsers[] = [
                            'id' => $contact['id'],
                            'name' => $contact['name'] ?? 'Sin nombre',
                            'email' => strtolower(trim($contact['email'])),
                            'tags' => $userTags,
                            'phone' => $contact['phone'] ?? '',
                            'company' => $contact['companyName'] ?? ''
                        ];
                    }
                }
            }

            $this->line("✅ Usuarios válidos encontrados: " . count($validUsers));
            $this->line('');

            // Mostrar algunos ejemplos
            $this->line("📋 Primeros 10 usuarios encontrados:");
            $this->line("=====================================");
            
            $count = 0;
            foreach ($validUsers as $user) {
                if ($count >= 10) break;
                
                $tagsStr = implode(', ', array_slice($user['tags'], 0, 5));
                if (count($user['tags']) > 5) {
                    $tagsStr .= '...';
                }
                
                $this->line("   • {$user['email']} - {$user['name']} - Tags: {$tagsStr}");
                $count++;
            }

            if (count($validUsers) > 10) {
                $this->line("   ... y " . (count($validUsers) - 10) . " más");
            }

            $this->line('');
            $this->info("🎯 RESUMEN:");
            $this->line("   • Total contactos procesados: " . ($meta['total_processed'] ?? 'N/A'));
            $this->line("   • Contactos con tags coincidentes: " . count($allContacts));
            $this->line("   • Usuarios válidos (con email válido): " . count($validUsers));
            $this->line("   • Páginas procesadas: " . ($meta['pages_processed'] ?? 'N/A'));

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
        }
    }

    private function isValidEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
