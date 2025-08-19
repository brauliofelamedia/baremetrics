<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\StripeService;

class GetStripeCustomers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stripe:customers 
                           {--limit=100 : Límite de customers a obtener}
                           {--all : Obtener todos los customers}
                           {--email= : Buscar customers por email}
                           {--export= : Exportar a archivo (csv|json)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Obtener customer IDs desde Stripe';

    protected $stripeService;

    public function __construct(StripeService $stripeService)
    {
        parent::__construct();
        $this->stripeService = $stripeService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Conectando con Stripe...');

        try {
            if ($this->option('email')) {
                $this->searchByEmail();
            } elseif ($this->option('all')) {
                $this->getAllCustomers();
            } else {
                $this->getCustomersWithLimit();
            }
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function searchByEmail()
    {
        $email = $this->option('email');
        $this->info("🔍 Buscando customers con email: {$email}");

        $result = $this->stripeService->searchCustomersByEmail($email);

        if (!$result['success']) {
            $this->error('❌ Error: ' . $result['error']);
            return;
        }

        $this->displayResults($result['data'], "Customers encontrados con email: {$email}");
    }

    private function getAllCustomers()
    {
        $this->info('📥 Obteniendo TODOS los customers de Stripe...');
        $this->warn('⚠️  Esto puede tomar tiempo si tienes muchos customers.');

        $result = $this->stripeService->getAllCustomerIds();

        if (!$result['success']) {
            $this->error('❌ Error: ' . $result['error']);
            return;
        }

        $this->displayResults($result['data'], 'Todos los customers');
    }

    private function getCustomersWithLimit()
    {
        $limit = $this->option('limit');
        $this->info("📥 Obteniendo {$limit} customers de Stripe...");

        $result = $this->stripeService->getCustomerIds($limit);

        if (!$result['success']) {
            $this->error('❌ Error: ' . $result['error']);
            return;
        }

        $this->displayResults($result['data'], "Primeros {$limit} customers");

        if ($result['has_more']) {
            $this->warn('⚠️  Hay más customers disponibles. Usa --all para obtener todos.');
        }
    }

    private function displayResults($customers, $title)
    {
        $this->info("✅ {$title}: " . count($customers));
        $this->newLine();

        if (empty($customers)) {
            $this->warn('No se encontraron customers.');
            return;
        }

        // Mostrar tabla
        $headers = ['ID', 'Email', 'Nombre', 'Fecha de Creación'];
        $rows = [];

        foreach ($customers as $customer) {
            $rows[] = [
                $customer['id'],
                $customer['email'] ?? 'N/A',
                $customer['name'] ?? 'N/A',
                date('Y-m-d H:i:s', $customer['created'])
            ];
        }

        $this->table($headers, $rows);

        // Exportar si se solicita
        if ($this->option('export')) {
            $this->exportData($customers, $this->option('export'));
        }
    }

    private function exportData($customers, $format)
    {
        $filename = 'stripe_customers_' . date('Y-m-d_H-i-s');
        
        switch (strtolower($format)) {
            case 'csv':
                $this->exportToCsv($customers, $filename . '.csv');
                break;
            case 'json':
                $this->exportToJson($customers, $filename . '.json');
                break;
            default:
                $this->error('❌ Formato de exportación no válido. Usa: csv o json');
                return;
        }
    }

    private function exportToCsv($customers, $filename)
    {
        $path = storage_path('app/' . $filename);
        $file = fopen($path, 'w');

        // Headers
        fputcsv($file, ['ID', 'Email', 'Nombre', 'Fecha de Creación']);

        // Data
        foreach ($customers as $customer) {
            fputcsv($file, [
                $customer['id'],
                $customer['email'] ?? '',
                $customer['name'] ?? '',
                date('Y-m-d H:i:s', $customer['created'])
            ]);
        }

        fclose($file);
        $this->info("📄 Datos exportados a: {$path}");
    }

    private function exportToJson($customers, $filename)
    {
        $path = storage_path('app/' . $filename);
        $data = json_encode($customers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($path, $data);
        $this->info("📄 Datos exportados a: {$path}");
    }
}
