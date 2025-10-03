<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use Illuminate\Support\Facades\Log;

class GetGHLUsersFirstPage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:users-first-page {--limit=100 : Número de usuarios a obtener por página}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Obtiene la primera página completa de usuarios de GoHighLevel y muestra toda la información disponible';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Iniciando consulta de usuarios de GoHighLevel...');
        
        try {
            $ghlService = new GoHighLevelService();
            $limit = $this->option('limit');
            
            $this->info("📊 Obteniendo {$limit} usuarios de la primera página...");
            
            // Obtener la primera página de usuarios (máximo 20 por página según la API)
            $pageLimit = min($limit, 20);
            $response = $ghlService->getContacts('', 1, $pageLimit);
            
            if (!$response) {
                $this->error('❌ No se pudo obtener respuesta de GoHighLevel');
                return 1;
            }
            
            // Mostrar información de la respuesta
            $this->displayResponseInfo($response);
            
            // Mostrar usuarios detallados
            $this->displayUsers($response);
            
            $this->info('✅ Consulta completada exitosamente');
            return 0;
            
        } catch (\Exception $e) {
            $this->error('❌ Error al obtener usuarios de GoHighLevel: ' . $e->getMessage());
            Log::error('Error en GetGHLUsersFirstPage', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
    
    /**
     * Muestra información general de la respuesta
     */
    private function displayResponseInfo($response)
    {
        $this->newLine();
        $this->info('📋 INFORMACIÓN GENERAL DE LA RESPUESTA:');
        $this->line('=====================================');
        
        // Información de paginación
        if (isset($response['meta']['pagination'])) {
            $pagination = $response['meta']['pagination'];
            $this->line("📄 Página actual: " . ($pagination['page'] ?? 'N/A'));
            $this->line("📊 Total de páginas: " . ($pagination['totalPages'] ?? 'N/A'));
            $this->line("📈 Total de registros: " . ($pagination['total'] ?? 'N/A'));
            $this->line("🔢 Límite por página: " . ($pagination['pageLimit'] ?? 'N/A'));
            $this->line("➡️  Tiene más páginas: " . ($pagination['has_more'] ? 'Sí' : 'No'));
        }
        
        // Información de contactos
        $contacts = $response['contacts'] ?? [];
        $this->line("👥 Contactos en esta página: " . count($contacts));
        
        $this->newLine();
    }
    
    /**
     * Muestra todos los usuarios con su información completa
     */
    private function displayUsers($response)
    {
        $contacts = $response['contacts'] ?? [];
        
        if (empty($contacts)) {
            $this->warn('⚠️  No se encontraron contactos en la respuesta');
            return;
        }
        
        $this->info('👥 DETALLES DE TODOS LOS USUARIOS:');
        $this->line('==================================');
        
        foreach ($contacts as $index => $contact) {
            $this->displaySingleUser($contact, $index + 1);
            $this->newLine();
        }
    }
    
    /**
     * Muestra la información completa de un usuario individual
     */
    private function displaySingleUser($contact, $userNumber)
    {
        $this->line("🔸 USUARIO #{$userNumber}:");
        $this->line("   ID: " . ($contact['id'] ?? 'N/A'));
        $this->line("   Nombre: " . ($contact['name'] ?? 'N/A'));
        $this->line("   Email: " . ($contact['email'] ?? 'N/A'));
        $this->line("   Teléfono: " . ($contact['phone'] ?? 'N/A'));
        
        // Información de fechas
        if (isset($contact['dateAdded'])) {
            $this->line("   Fecha de registro: " . $contact['dateAdded']);
        }
        if (isset($contact['dateUpdated'])) {
            $this->line("   Última actualización: " . $contact['dateUpdated']);
        }
        
        // Información de ubicación
        if (isset($contact['locationId'])) {
            $this->line("   ID de ubicación: " . $contact['locationId']);
        }
        
        // Tags
        if (isset($contact['tags']) && is_array($contact['tags']) && !empty($contact['tags'])) {
            $this->line("   Tags: " . implode(', ', $contact['tags']));
        } else {
            $this->line("   Tags: Ninguno");
        }
        
        // Campos personalizados
        if (isset($contact['customFields']) && is_array($contact['customFields']) && !empty($contact['customFields'])) {
            $this->line("   Campos personalizados:");
            foreach ($contact['customFields'] as $field) {
                $fieldName = $field['name'] ?? 'Campo sin nombre';
                $fieldValue = $field['value'] ?? 'Sin valor';
                // Convertir arrays a string para evitar errores
                if (is_array($fieldValue)) {
                    $fieldValue = json_encode($fieldValue, JSON_UNESCAPED_UNICODE);
                }
                $this->line("     • {$fieldName}: {$fieldValue}");
            }
        } else {
            $this->line("   Campos personalizados: Ninguno");
        }
        
        // Información de suscripción si está disponible
        if (isset($contact['subscription'])) {
            $this->line("   Información de suscripción:");
            $subscription = $contact['subscription'];
            $this->line("     • Estado: " . ($subscription['status'] ?? 'N/A'));
            $this->line("     • ID: " . ($subscription['id'] ?? 'N/A'));
            if (isset($subscription['createdAt'])) {
                $this->line("     • Fecha de creación: " . $subscription['createdAt']);
            }
        }
        
        // Información de fuente
        if (isset($contact['source'])) {
            $this->line("   Fuente: " . $contact['source']);
        }
        
        // Información de estado
        if (isset($contact['status'])) {
            $this->line("   Estado: " . $contact['status']);
        }
        
        // Información de dirección
        if (isset($contact['address1'])) {
            $this->line("   Dirección: " . $contact['address1']);
        }
        if (isset($contact['city'])) {
            $this->line("   Ciudad: " . $contact['city']);
        }
        if (isset($contact['state'])) {
            $this->line("   Estado/Provincia: " . $contact['state']);
        }
        if (isset($contact['postalCode'])) {
            $this->line("   Código postal: " . $contact['postalCode']);
        }
        if (isset($contact['country'])) {
            $this->line("   País: " . $contact['country']);
        }
        
        // Información de empresa
        if (isset($contact['companyName'])) {
            $this->line("   Empresa: " . $contact['companyName']);
        }
        
        // Información de sitio web
        if (isset($contact['website'])) {
            $this->line("   Sitio web: " . $contact['website']);
        }
        
        // Información de notas
        if (isset($contact['notes'])) {
            $this->line("   Notas: " . $contact['notes']);
        }
        
        // Mostrar toda la información JSON completa para debugging
        $this->line("   📄 JSON completo:");
        $this->line("   " . json_encode($contact, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
