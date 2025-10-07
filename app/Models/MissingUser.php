<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissingUser extends Model
{
    use HasFactory;

    protected $table = 'missing_users';

    protected $fillable = [
        'comparison_id',
        'email',
        'name',
        'phone',
        'company',
        'tags',
        'created_date',
        'last_activity',
        'import_status',
        'baremetrics_customer_id',
        'import_error',
        'import_notes',
        'imported_at',
    ];

    protected $casts = [
        'created_date' => 'date',
        'last_activity' => 'date',
        'imported_at' => 'datetime',
    ];

    /**
     * Relación con el registro de comparación
     */
    public function comparison(): BelongsTo
    {
        return $this->belongsTo(ComparisonRecord::class, 'comparison_id');
    }

    /**
     * Scope para usuarios pendientes
     */
    public function scopePending($query)
    {
        return $query->where('import_status', 'pending');
    }

    /**
     * Scope para usuarios importados
     */
    public function scopeImported($query)
    {
        return $query->where('import_status', 'imported');
    }

    /**
     * Scope para usuarios con error
     */
    public function scopeFailed($query)
    {
        return $query->where('import_status', 'failed');
    }

    /**
     * Scope para usuarios en proceso de importación
     */
    public function scopeImporting($query)
    {
        return $query->where('import_status', 'importing');
    }

    /**
     * Marcar como importado
     */
    public function markAsImported($baremetricsCustomerId = null)
    {
        $this->update([
            'import_status' => 'imported',
            'baremetrics_customer_id' => $baremetricsCustomerId,
            'imported_at' => now(),
            'import_error' => null,
        ]);
    }

    /**
     * Marcar como fallido
     */
    public function markAsFailed($errorMessage)
    {
        $this->update([
            'import_status' => 'failed',
            'import_error' => $errorMessage,
            'imported_at' => null,
        ]);
    }

    /**
     * Marcar como en proceso de importación
     */
    public function markAsImporting()
    {
        $this->update([
            'import_status' => 'importing',
            'import_error' => null,
        ]);
    }

    /**
     * Obtener tags como array
     */
    public function getTagsArrayAttribute()
    {
        if (empty($this->tags)) {
            return [];
        }
        
        return array_map('trim', explode(',', $this->tags));
    }

    /**
     * Verificar si tiene tags específicos
     */
    public function hasTags(array $tags)
    {
        $userTags = $this->tags_array;
        return !empty(array_intersect($tags, $userTags));
    }
}