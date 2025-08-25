<?php

namespace App\Services;

use App\Models\SystemConfiguration;
use Illuminate\Support\Facades\Cache;

class SystemService
{
    /**
     * Get system configuration with caching
     */
    public function getConfiguration()
    {
        return Cache::remember('system_configuration', 3600, function () {
            return SystemConfiguration::getConfig();
        });
    }

    /**
     * Update system configuration and clear cache
     */
    public function updateConfiguration(array $data)
    {
        $config = SystemConfiguration::getConfig();
        $config->update($data);
        
        $this->clearConfigCache();
        
        return $config;
    }

    /**
     * Clear system configuration cache
     */
    public function clearConfigCache()
    {
        Cache::forget('system_configuration');
    }

    /**
     * Get system name for views
     */
    public function getSystemName()
    {
        return $this->getConfiguration()->getSystemName();
    }

    /**
     * Get system logo URL
     */
    public function getSystemLogo()
    {
        return $this->getConfiguration()->getLogoUrl();
    }

    /**
     * Get system favicon URL
     */
    public function getSystemFavicon()
    {
        return $this->getConfiguration()->getFaviconUrl();
    }

    /**
     * Get system statistics
     */
    public function getSystemStats()
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => app()->environment(),
            'debug_mode' => config('app.debug'),
            'cache_driver' => config('cache.default'),
            'session_driver' => config('session.driver'),
            'database_driver' => config('database.default'),
            'memory_usage' => $this->getMemoryUsage(),
            'storage_usage' => $this->getStorageUsage(),
        ];
    }

    /**
     * Get current memory usage
     */
    private function getMemoryUsage()
    {
        return round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB';
    }

    /**
     * Get storage usage
     */
    private function getStorageUsage()
    {
        $storagePath = storage_path();
        $size = 0;
        
        if (is_dir($storagePath)) {
            try {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($storagePath)
                );
                
                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        $size += $file->getSize();
                    }
                }
            } catch (\Exception $e) {
                // Si hay error al calcular, retornar N/A
                return 'N/A';
            }
        }
        
        return round($size / 1024 / 1024, 2) . ' MB';
    }

    /**
     * Check if system is healthy
     */
    public function getSystemHealth()
    {
        $health = [
            'database' => $this->checkDatabase(),
            'storage' => $this->checkStorage(),
            'cache' => $this->checkCache(),
            'overall' => true
        ];

        $health['overall'] = $health['database'] && $health['storage'] && $health['cache'];

        return $health;
    }

    /**
     * Check database connection
     */
    private function checkDatabase()
    {
        try {
            \DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check storage writability
     */
    private function checkStorage()
    {
        return is_writable(storage_path());
    }

    /**
     * Check cache functionality
     */
    private function checkCache()
    {
        try {
            Cache::put('system_health_check', true, 1);
            $result = Cache::get('system_health_check');
            Cache::forget('system_health_check');
            return $result === true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Clear all system caches
     */
    public function clearAllCaches()
    {
        try {
            \Artisan::call('cache:clear');
            \Artisan::call('config:clear');
            \Artisan::call('route:clear');
            \Artisan::call('view:clear');
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get system logs
     */
    public function getSystemLogs($lines = 100)
    {
        $logPath = storage_path('logs/laravel.log');
        
        if (!file_exists($logPath)) {
            return [];
        }

        try {
            $logs = [];
            $file = new \SplFileObject($logPath);
            $file->seek(PHP_INT_MAX);
            $totalLines = $file->key();
            
            $startLine = max(0, $totalLines - $lines);
            $file->seek($startLine);
            
            while (!$file->eof()) {
                $line = trim($file->current());
                if (!empty($line)) {
                    $logs[] = $line;
                }
                $file->next();
            }
            
            return array_reverse($logs);
        } catch (\Exception $e) {
            return ['Error al leer los logs: ' . $e->getMessage()];
        }
    }
}
