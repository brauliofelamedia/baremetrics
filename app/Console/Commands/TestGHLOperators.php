<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use Illuminate\Support\Facades\Log;

class TestGHLOperators extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:test-operators 
                           {email : Email del usuario a buscar}
                           {--all : Probar todos los operadores disponibles}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prueba diferentes operadores de búsqueda en GoHighLevel';

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
        $email = $this->argument('email');
        $testAll = $this->option('all');

        $this->info("🧪 Probando operadores de búsqueda para: {$email}");
        $this->newLine();

        // Operadores válidos según el error 422
        $operators = [
            'eq' => 'Igual a (exacto)',
            'not_eq' => 'No igual a',
            'contains' => 'Contiene',
            'not_contains' => 'No contiene',
            'wildcard' => 'Comodín',
            'not_wildcard' => 'No comodín'
        ];

        if (!$testAll) {
            // Solo probar los más comunes
            $operators = array_slice($operators, 0, 3, true);
        }

        $results = [];

        foreach ($operators as $operator => $description) {
            $this->info("🔍 Probando operador: {$operator} ({$description})");
            
            try {
                $result = $this->testOperator($email, $operator);
                $results[$operator] = $result;
                
                if ($result['success']) {
                    $count = count($result['contacts']);
                    $this->info("  ✅ Éxito: {$count} contactos encontrados");
                    
                    if ($count > 0) {
                        $this->line("  📧 Primer contacto: " . ($result['contacts'][0]['email'] ?? 'N/A'));
                    }
                } else {
                    $this->error("  ❌ Error: " . $result['error']);
                }
                
            } catch (\Exception $e) {
                $this->error("  ❌ Excepción: " . $e->getMessage());
                $results[$operator] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
            
            $this->newLine();
        }

        // Mostrar resumen
        $this->displaySummary($results, $email);
    }

    /**
     * Prueba un operador específico
     */
    private function testOperator($email, $operator)
    {
        $body = [
            'pageLimit' => 20,
            'locationId' => config('services.gohighlevel.location'),
            'filters' => [
                [
                    'field' => 'email',
                    'operator' => $operator,
                    'value' => $email
                ]
            ]
        ];

        try {
            $token = $this->ghlService->ensureValidToken();
            
            $response = $this->ghlService->getClient()->request('POST', 'https://services.leadconnectorhq.com/contacts/search', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => "Bearer {$token}",
                    'Version' => '2021-07-28',
                ],
                'json' => $body,
            ]);

            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getBody()->getContents(), true);
                return [
                    'success' => true,
                    'contacts' => $data['contacts'] ?? [],
                    'total' => $data['meta']['total'] ?? 0
                ];
            } else {
                return [
                    'success' => false,
                    'error' => "HTTP {$response->getStatusCode()}: " . $response->getBody()->getContents()
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Muestra el resumen de resultados
     */
    private function displaySummary($results, $email)
    {
        $this->info('📊 RESUMEN DE RESULTADOS');
        $this->info('========================');
        
        $successful = array_filter($results, function($result) {
            return $result['success'];
        });

        if (empty($successful)) {
            $this->error('❌ Ningún operador funcionó correctamente');
            $this->warn('💡 Posibles causas:');
            $this->line('  • El email no existe en GoHighLevel');
            $this->line('  • Problemas de permisos en la API');
            $this->line('  • Token inválido o expirado');
            return;
        }

        $this->info('✅ Operadores que funcionaron:');
        
        foreach ($successful as $operator => $result) {
            $count = count($result['contacts']);
            $this->line("  • {$operator}: {$count} contactos");
        }

        // Recomendación
        $this->newLine();
        $this->info('💡 RECOMENDACIÓN:');
        
        if (isset($successful['eq'])) {
            $this->line('✅ Usa el operador "eq" para búsquedas exactas');
        } elseif (isset($successful['contains'])) {
            $this->line('✅ Usa el operador "contains" para búsquedas parciales');
        } else {
            $this->line('✅ Usa el primer operador que funcionó');
        }

        // Mostrar detalles del primer resultado exitoso
        $firstSuccess = reset($successful);
        if (!empty($firstSuccess['contacts'])) {
            $this->newLine();
            $this->info('📋 DETALLES DEL PRIMER CONTACTO ENCONTRADO:');
            $contact = $firstSuccess['contacts'][0];
            
            $this->table(
                ['Campo', 'Valor'],
                [
                    ['Email', $contact['email'] ?? 'N/A'],
                    ['Nombre', $contact['name'] ?? 'N/A'],
                    ['Teléfono', $contact['phone'] ?? 'N/A'],
                    ['País', $contact['country'] ?? 'N/A'],
                    ['ID', $contact['id'] ?? 'N/A'],
                ]
            );
        }
    }
}
