<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemConfiguration extends Model
{
    use HasFactory;

    protected $table = 'system_configuration';

    protected $fillable = [
        'system_name',
        'system_logo',
        'system_favicon',
        'description',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the system configuration (singleton pattern)
     */
    public static function getConfig()
    {
        return self::firstOrCreate(['id' => 1], [
            'system_name' => 'Créetelo',
            'description' => 'Configuración general del sistema',
        ]);
    }

    /**
     * Get the system name
     */
    public function getSystemName()
    {
        return $this->system_name ?: config('app.name', 'Baremetrics Dashboard');
    }

    /**
     * Get the system logo URL
     */
    public function getLogoUrl()
    {
        return $this->system_logo ? asset('storage/' . $this->system_logo) : null;
    }

    /**
     * Get the system favicon URL
     */
    public function getFaviconUrl()
    {
        return $this->system_favicon ? asset('storage/' . $this->system_favicon) : null;
    }

    /**
     * Check if system has logo
     */
    public function hasLogo()
    {
        return !empty($this->system_logo);
    }

    /**
     * Check if system has favicon
     */
    public function hasFavicon()
    {
        return !empty($this->system_favicon);
    }
}
