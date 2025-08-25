<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\SystemConfiguration;
use Illuminate\Support\Facades\Cache;

class SystemSettingsService
{
    /**
     * Get system setting value with caching (backward compatibility)
     */
    public static function get(string $key, $default = null)
    {
        $cacheKey = 'system_setting_' . $key;
        
        return Cache::remember($cacheKey, 3600, function () use ($key, $default) {
            return SystemSetting::getValue($key, $default);
        });
    }

    /**
     * Set system setting value and clear cache (backward compatibility)
     */
    public static function set(string $key, $value, string $type = 'text', string $description = null)
    {
        $result = SystemSetting::setValue($key, $value, $type, $description);
        
        // Clear cache for this setting
        Cache::forget('system_setting_' . $key);
        
        return $result;
    }

    /**
     * Get all settings with caching (backward compatibility)
     */
    public static function all()
    {
        return Cache::remember('all_system_settings', 3600, function () {
            return SystemSetting::getAllSettings();
        });
    }

    /**
     * Clear all settings cache
     */
    public static function clearCache()
    {
        $settings = SystemSetting::all();
        
        foreach ($settings as $setting) {
            Cache::forget('system_setting_' . $setting->key);
        }
        
        Cache::forget('all_system_settings');
        Cache::forget('system_configuration');
    }

    /**
     * Get system configuration (new unified system)
     */
    public static function getConfiguration()
    {
        return Cache::remember('system_configuration', 3600, function () {
            return SystemConfiguration::getInstance();
        });
    }

    /**
     * Get system name (unified)
     */
    public static function getSystemName()
    {
        $config = self::getConfiguration();
        return $config ? $config->system_name : self::get('system_name', 'Baremetrics Dashboard');
    }

    /**
     * Get system logo URL (unified)
     */
    public static function getSystemLogo()
    {
        $config = self::getConfiguration();
        if ($config && $config->hasLogo()) {
            return $config->system_logo_url;
        }
        
        // Fallback to old system
        $logo = self::get('system_logo');
        return $logo ? \Storage::url($logo) : null;
    }

    /**
     * Get system favicon URL (unified)
     */
    public static function getSystemFavicon()
    {
        $config = self::getConfiguration();
        if ($config && $config->hasFavicon()) {
            return $config->system_favicon_url;
        }
        
        // Fallback to old system
        $favicon = self::get('system_favicon');
        return $favicon ? \Storage::url($favicon) : null;
    }
}
