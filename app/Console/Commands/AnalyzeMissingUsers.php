<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class AnalyzeMissingUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:analyze-missing-users 
                           {--file= : Archivo específico a analizar (opcional)}
                           {--latest : Analizar el archivo más reciente}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analiza el reporte de usuarios faltantes en Baremetrics';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('📊 Analizando reporte de usuarios faltantes en Baremetrics...');
        $this->newLine();

        try {
            $filePath = $this->getReportFile();
            
            if (!$filePath) {
                $this->error('❌ No se encontró ningún reporte de usuarios faltantes');
                $this->info('💡 Ejecuta primero: php artisan ghl:process-ghl-to-baremetrics');
                return 1;
            }

            $this->info("📄 Analizando archivo: " . basename($filePath));
            
            $report = json_decode(File::get($filePath), true);
            
            if (!$report) {
                $this->error('❌ Error leyendo el archivo de reporte');
                return 1;
            }

            $this->analyzeReport($report);

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Obtiene el archivo de reporte a analizar
     */
    private function getReportFile()
    {
        $specificFile = $this->option('file');
        $useLatest = $this->option('latest');

        if ($specificFile) {
            $filePath = storage_path('app/' . $specificFile);
            if (File::exists($filePath)) {
                return $filePath;
            } else {
                $this->error("❌ Archivo no encontrado: {$specificFile}");
                return null;
            }
        }

        if ($useLatest) {
            $files = File::glob(storage_path('app/ghl-missing-users-*.json'));
            if (!empty($files)) {
                // Ordenar por fecha de modificación (más reciente primero)
                usort($files, function($a, $b) {
                    return filemtime($b) - filemtime($a);
                });
                return $files[0];
            }
        }

        // Buscar todos los archivos disponibles
        $files = File::glob(storage_path('app/ghl-missing-users-*.json'));
        
        if (empty($files)) {
            return null;
        }

        if (count($files) === 1) {
            return $files[0];
        }

        // Mostrar lista de archivos disponibles
        $this->info('📋 Archivos de reporte disponibles:');
        foreach ($files as $index => $file) {
            $filename = basename($file);
            $modified = date('Y-m-d H:i:s', filemtime($file));
            $this->line("  " . ($index + 1) . ". {$filename} (modificado: {$modified})");
        }

        $this->newLine();
        $this->info('💡 Usa --latest para analizar el más reciente o --file=nombre-archivo.json para uno específico');
        
        return null;
    }

    /**
     * Analiza el reporte
     */
    private function analyzeReport($report)
    {
        $this->info('📊 INFORMACIÓN GENERAL:');
        $this->info('========================');
        $this->line("• Generado: " . ($report['generated_at'] ?? 'N/A'));
        $this->line("• Total usuarios faltantes: " . ($report['total_missing_users'] ?? 0));
        $this->line("• Descripción: " . ($report['description'] ?? 'N/A'));
        $this->newLine();

        if (empty($report['users'])) {
            $this->info('✅ No hay usuarios faltantes en Baremetrics');
            return;
        }

        $users = $report['users'];
        $totalUsers = count($users);

        // Estadísticas básicas
        $this->info('📈 ESTADÍSTICAS:');
        $this->info('================');
        
        $usersWithPhone = array_filter($users, function($user) {
            return !empty($user['phone']);
        });
        
        $usersWithName = array_filter($users, function($user) {
            return !empty($user['name']) && trim($user['name']) !== '';
        });

        // Estadísticas de membresía
        $usersWithMembership = array_filter($users, function($user) {
            return isset($user['membership']) && $user['membership']['has_membership'] === true;
        });

        // Estadísticas de suscripción
        $usersWithSubscription = array_filter($users, function($user) {
            return isset($user['subscription']) && $user['subscription']['has_subscription'] === true;
        });

        // Estadísticas de cupones
        $usersWithCoupon = array_filter($users, function($user) {
            return isset($user['subscription']) && 
                   $user['subscription']['has_subscription'] === true && 
                   !empty($user['subscription']['coupon_code']);
        });

        $this->line("• Total usuarios: {$totalUsers}");
        $this->line("• Con teléfono: " . count($usersWithPhone));
        $this->line("• Con nombre completo: " . count($usersWithName));
        $this->line("• Solo con email: " . ($totalUsers - count($usersWithName)));
        $this->line("• Con membresía: " . count($usersWithMembership));
        $this->line("• Con suscripción: " . count($usersWithSubscription));
        $this->line("• Con cupón utilizado: " . count($usersWithCoupon));
        $this->newLine();

        // Análisis de dominios de email
        $this->info('📧 ANÁLISIS DE DOMINIOS DE EMAIL:');
        $this->info('==================================');
        
        $domains = [];
        foreach ($users as $user) {
            if (!empty($user['email'])) {
                $domain = substr(strrchr($user['email'], "@"), 1);
                if (!isset($domains[$domain])) {
                    $domains[$domain] = 0;
                }
                $domains[$domain]++;
            }
        }

        // Ordenar dominios por frecuencia
        arsort($domains);
        
        $topDomains = array_slice($domains, 0, 10, true);
        foreach ($topDomains as $domain => $count) {
            $percentage = round(($count / $totalUsers) * 100, 1);
            $this->line("• {$domain}: {$count} usuarios ({$percentage}%)");
        }
        $this->newLine();

        // Análisis de membresías
        if (!empty($usersWithMembership)) {
            $this->info('🎫 ANÁLISIS DE MEMBRESÍAS:');
            $this->info('===========================');
            
            $membershipStatuses = [];
            foreach ($usersWithMembership as $user) {
                $status = $user['membership']['status'] ?? 'unknown';
                if (!isset($membershipStatuses[$status])) {
                    $membershipStatuses[$status] = 0;
                }
                $membershipStatuses[$status]++;
            }
            
            foreach ($membershipStatuses as $status => $count) {
                $percentage = round(($count / count($usersWithMembership)) * 100, 1);
                $this->line("• {$status}: {$count} usuarios ({$percentage}%)");
            }
            $this->newLine();
        }

        // Análisis de suscripciones
        if (!empty($usersWithSubscription)) {
            $this->info('💳 ANÁLISIS DE SUSCRIPCIONES:');
            $this->info('==============================');
            
            $subscriptionStatuses = [];
            foreach ($usersWithSubscription as $user) {
                $status = $user['subscription']['status'] ?? 'unknown';
                if (!isset($subscriptionStatuses[$status])) {
                    $subscriptionStatuses[$status] = 0;
                }
                $subscriptionStatuses[$status]++;
            }
            
            foreach ($subscriptionStatuses as $status => $count) {
                $percentage = round(($count / count($usersWithSubscription)) * 100, 1);
                $this->line("• {$status}: {$count} usuarios ({$percentage}%)");
            }
            $this->newLine();
        }

        // Análisis de cupones
        if (!empty($usersWithCoupon)) {
            $this->info('🎟️  ANÁLISIS DE CUPONES:');
            $this->info('=========================');
            
            $couponCodes = [];
            foreach ($usersWithCoupon as $user) {
                $coupon = $user['subscription']['coupon_code'] ?? 'unknown';
                if (!isset($couponCodes[$coupon])) {
                    $couponCodes[$coupon] = 0;
                }
                $couponCodes[$coupon]++;
            }
            
            // Ordenar por frecuencia
            arsort($couponCodes);
            
            $topCoupons = array_slice($couponCodes, 0, 10, true);
            foreach ($topCoupons as $coupon => $count) {
                $percentage = round(($count / count($usersWithCoupon)) * 100, 1);
                $this->line("• {$coupon}: {$count} usuarios ({$percentage}%)");
            }
            $this->newLine();
        }

        // Análisis por fechas de creación
        $this->info('📅 ANÁLISIS POR FECHAS DE CREACIÓN:');
        $this->info('====================================');
        
        $usersByMonth = [];
        foreach ($users as $user) {
            if (!empty($user['created_at'])) {
                $date = date('Y-m', strtotime($user['created_at']));
                if (!isset($usersByMonth[$date])) {
                    $usersByMonth[$date] = 0;
                }
                $usersByMonth[$date]++;
            }
        }

        if (!empty($usersByMonth)) {
            ksort($usersByMonth);
            foreach ($usersByMonth as $month => $count) {
                $this->line("• {$month}: {$count} usuarios");
            }
        } else {
            $this->line("• No hay información de fechas de creación");
        }
        $this->newLine();

        // Mostrar algunos ejemplos
        $this->info('👥 EJEMPLOS DE USUARIOS FALTANTES:');
        $this->info('===================================');
        
        $examples = array_slice($users, 0, 10);
        foreach ($examples as $index => $user) {
            $this->line(($index + 1) . ". Email: " . ($user['email'] ?? 'N/A'));
            $this->line("   Nombre: " . ($user['name'] ?? 'N/A'));
            $this->line("   Teléfono: " . ($user['phone'] ?? 'N/A'));
            $this->line("   GHL ID: " . ($user['ghl_id'] ?? 'N/A'));
            $this->line("   Creado: " . ($user['created_at'] ?? 'N/A'));
            
            // Información de membresía
            if (isset($user['membership']) && $user['membership']['has_membership']) {
                $this->line("   Membresía: " . ($user['membership']['status'] ?? 'N/A') . 
                          " (ID: " . ($user['membership']['membership_id'] ?? 'N/A') . ")");
            } else {
                $this->line("   Membresía: Sin membresía");
            }
            
            // Información de suscripción
            if (isset($user['subscription']) && $user['subscription']['has_subscription']) {
                $this->line("   Suscripción: " . ($user['subscription']['status'] ?? 'N/A'));
                if (!empty($user['subscription']['coupon_code'])) {
                    $this->line("   Cupón utilizado: " . $user['subscription']['coupon_code']);
                }
                if (!empty($user['subscription']['price'])) {
                    $this->line("   Precio: " . ($user['subscription']['price'] ?? 'N/A') . 
                              " " . ($user['subscription']['currency'] ?? ''));
                }
            } else {
                $this->line("   Suscripción: Sin suscripción");
            }
            
            $this->newLine();
        }

        if ($totalUsers > 10) {
            $this->line("... y " . ($totalUsers - 10) . " usuarios más");
            $this->newLine();
        }

        // Recomendaciones
        $this->info('💡 RECOMENDACIONES:');
        $this->info('====================');
        
        if ($totalUsers > 0) {
            $this->line("• Considerar crear estos usuarios en Baremetrics");
            $this->line("• Priorizar usuarios con información completa (nombre + teléfono)");
            
            if (count($usersWithPhone) > 0) {
                $this->line("• " . count($usersWithPhone) . " usuarios tienen teléfono - buena información de contacto");
            }
            
            if (!empty($topDomains)) {
                $topDomain = array_key_first($topDomains);
                $this->line("• Dominio más común: {$topDomain} - considerar estrategia específica");
            }
            
            $this->line("• Revisar usuarios creados recientemente para entender patrones");
        }

        $this->newLine();
        $this->info('🛠️  PRÓXIMOS PASOS:');
        $this->info('====================');
        $this->line("• Implementar creación automática de usuarios en Baremetrics");
        $this->line("• Configurar sincronización bidireccional");
        $this->line("• Establecer proceso de reconciliación periódica");
        $this->line("• Monitorear usuarios nuevos en GoHighLevel");
    }
}
