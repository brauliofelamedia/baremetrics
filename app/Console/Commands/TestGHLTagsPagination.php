<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;

class TestGHLTagsPagination extends Command
{
    protected $signature = 'ghl:test-tags-pagination {--tag=} {--pages=5}';
    protected $description = 'Test GHL tags API pagination';

    protected $ghlService;

    public function __construct(GoHighLevelService $ghlService)
    {
        parent::__construct();
        $this->ghlService = $ghlService;
    }

    public function handle()
    {
        $tag = $this->option('tag');
        $maxPages = (int) $this->option('pages');

        if (!$tag) {
            $this->error('Debe especificar un tag con --tag=nombre_tag');
            return;
        }

        $this->info("🔍 Probando paginación para tag: {$tag}");
        $this->info("📄 Máximo de páginas a probar: {$maxPages}");
        $this->line('');

        $totalContacts = 0;
        $allContacts = [];

        for ($page = 1; $page <= $maxPages; $page++) {
            $this->line("📄 Página {$page}:");
            
            try {
                $response = $this->ghlService->getContactsByTags([$tag], $page);
                
                if (!$response) {
                    $this->warn("   ❌ No hay respuesta para la página {$page}");
                    break;
                }

                $contacts = $response['contacts'] ?? [];
                $meta = $response['meta'] ?? [];
                $pagination = $meta['pagination'] ?? [];

                $this->line("   • Contactos en esta página: " . count($contacts));
                $this->line("   • Total contactos encontrados: " . ($pagination['total'] ?? 'N/A'));
                $this->line("   • Página actual: " . ($pagination['page'] ?? 'N/A'));
                $this->line("   • Límite por página: " . ($pagination['limit'] ?? 'N/A'));
                $this->line("   • Hay más páginas: " . ($pagination['has_more'] ?? 'N/A' ? 'Sí' : 'No'));
                
                // Debug: mostrar estructura completa de la respuesta
                if ($page === 1) {
                    $this->line("   🔍 Debug - Estructura de respuesta:");
                    $this->line("   • Meta: " . json_encode($meta));
                    $this->line("   • Pagination: " . json_encode($pagination));
                }

                $totalContacts += count($contacts);
                
                // Agregar emails únicos
                foreach ($contacts as $contact) {
                    if (!empty($contact['email'])) {
                        $email = strtolower(trim($contact['email']));
                        if (!in_array($email, $allContacts)) {
                            $allContacts[] = $email;
                        }
                    }
                }

                $this->line("   • Emails únicos acumulados: " . count($allContacts));
                $this->line('');

                // Si no hay más páginas, salir
                if (!($pagination['has_more'] ?? false)) {
                    $this->info("✅ No hay más páginas disponibles");
                    break;
                }

                // Pausa pequeña entre requests
                usleep(200000);

            } catch (\Exception $e) {
                $this->error("   ❌ Error en página {$page}: " . $e->getMessage());
                break;
            }
        }

        $this->line('');
        $this->info("📊 RESUMEN:");
        $this->line("   • Total contactos procesados: {$totalContacts}");
        $this->line("   • Emails únicos encontrados: " . count($allContacts));
        $this->line("   • Páginas procesadas: {$page}");
    }
}
