<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class CancellationToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'email',
        'expires_at',
        'is_used',
        'used_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'is_used' => 'boolean'
    ];

    /**
     * Scope para tokens activos (no expirados y no usados)
     */
    public function scopeActive($query)
    {
        return $query->where('is_used', false)
                    ->where('expires_at', '>', now());
    }

    /**
     * Scope para tokens expirados
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Verificar si el token está expirado
     */
    public function isExpired()
    {
        return $this->expires_at <= now();
    }

    /**
     * Marcar token como usado
     */
    public function markAsUsed()
    {
        $this->update([
            'is_used' => true,
            'used_at' => now()
        ]);
    }

    /**
     * Obtener tiempo restante en minutos
     */
    public function getRemainingMinutesAttribute()
    {
        if ($this->isExpired()) {
            return 0;
        }
        
        return max(0, now()->diffInMinutes($this->expires_at));
    }

    /**
     * Limpiar tokens expirados (comando de limpieza)
     */
    public static function cleanupExpiredTokens()
    {
        return self::expired()->delete();
    }
}