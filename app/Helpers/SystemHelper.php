<?php

if (!function_exists('system_name')) {
    /**
     * Get the system name from configuration
     */
    function system_name()
    {
        try {
            // Try to get from cache first
            $cached = cache('system_configuration');
            if ($cached && $cached->system_name) {
                return $cached->system_name;
            }
            
            // Try to get from database
            if (app()->has(\App\Services\SystemService::class)) {
                $systemService = app(\App\Services\SystemService::class);
                return $systemService->getSystemName();
            }
            
            // Try direct database query as last resort
            if (class_exists(\App\Models\SystemConfiguration::class)) {
                $config = \App\Models\SystemConfiguration::getConfig();
                return $config->getSystemName();
            }
        } catch (\Exception $e) {
            // Silent fail and use fallback
        }
        
        // Fallback to config value
        return config('app.name', 'Baremetrics Dashboard');
    }
}

if (!function_exists('system_config')) {
    /**
     * Get the full system configuration
     */
    function system_config()
    {
        try {
            $systemService = app(\App\Services\SystemService::class);
            return $systemService->getConfiguration();
        } catch (\Exception $e) {
            // Return null if system service is not available
            return null;
        }
    }
}

if (!function_exists('system_name_safe')) {
    /**
     * Get the system name safely for use in config files
     * This doesn't rely on Laravel services and can be used during bootstrap
     */
    function system_name_safe()
    {
        // For config files, we'll use the environment variable first
        // and let the app handle the database lookup later
        return env('APP_NAME', 'Baremetrics Dashboard');
    }
}
