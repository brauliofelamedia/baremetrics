<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'description'
    ];

    /**
     * Get a setting value by key
     */
    public static function getValue(string $key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Set a setting value
     */
    public static function setValue(string $key, $value, string $type = 'text', string $description = null): bool
    {
        $setting = self::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'type' => $type,
                'description' => $description
            ]
        );

        return $setting !== null;
    }

    /**
     * Get all settings as a key-value array
     */
    public static function getAllSettings(): array
    {
        return self::all()->pluck('value', 'key')->toArray();
    }

    /**
     * Clear all settings cache
     */
    public static function clearAllCache(): void
    {
        $settings = self::all();
        foreach ($settings as $setting) {
            Cache::forget('system_setting_' . $setting->key);
        }
        Cache::forget('all_system_settings');
    }
}
