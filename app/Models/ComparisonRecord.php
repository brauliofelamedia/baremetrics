<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComparisonRecord extends Model
{
    use HasFactory;

    protected $table = 'ghl_baremetrics_comparisons';

    protected $fillable = [
        'name',
        'csv_file_path',
        'csv_file_name',
        'total_ghl_users',
        'total_baremetrics_users',
        'users_found_in_baremetrics',
        'users_missing_from_baremetrics',
        'sync_percentage',
        'comparison_data',
        'missing_users_data',
        'found_in_other_sources_data',
        'status',
        'error_message',
        'processed_at',
        'total_rows_processed',
        'ghl_users_processed',
        'baremetrics_users_fetched',
        'comparisons_made',
        'users_found_count',
        'users_missing_count',
        'current_step',
        'progress_percentage',
        'last_progress_update',
    ];

    protected $casts = [
        'comparison_data' => 'array',
        'missing_users_data' => 'array',
        'found_in_other_sources_data' => 'array',
        'processed_at' => 'datetime',
        'last_progress_update' => 'datetime',
        'sync_percentage' => 'decimal:2',
        'progress_percentage' => 'decimal:2',
    ];

    /**
     * Relación con usuarios faltantes
     */
    public function missingUsers(): HasMany
    {
        return $this->hasMany(MissingUser::class, 'comparison_id');
    }

    /**
     * Obtener usuarios faltantes pendientes de importar
     */
    public function pendingMissingUsers(): HasMany
    {
        return $this->hasMany(MissingUser::class, 'comparison_id')
                    ->where('import_status', 'pending');
    }

    /**
     * Obtener usuarios faltantes importados
     */
    public function importedMissingUsers(): HasMany
    {
        return $this->hasMany(MissingUser::class, 'comparison_id')
                    ->where('import_status', 'imported');
    }

    /**
     * Obtener usuarios faltantes con error
     */
    public function failedMissingUsers(): HasMany
    {
        return $this->hasMany(MissingUser::class, 'comparison_id')
                    ->where('import_status', 'failed');
    }

    /**
     * Scope para comparaciones completadas
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope para comparaciones pendientes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope para comparaciones fallidas
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Obtener estadísticas de importación
     */
    public function getImportStatsAttribute()
    {
        $total = $this->missingUsers()->count();
        $imported = $this->missingUsers()->where('import_status', 'imported')->count();
        $failed = $this->missingUsers()->where('import_status', 'failed')->count();
        $pending = $this->missingUsers()->where('import_status', 'pending')->count();

        return [
            'total' => $total,
            'imported' => $imported,
            'failed' => $failed,
            'pending' => $pending,
            'import_percentage' => $total > 0 ? round(($imported / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Marcar como completada
     */
    public function markAsCompleted()
    {
        $this->update([
            'status' => 'completed',
            'processed_at' => now(),
        ]);
    }

    /**
     * Marcar como fallida
     */
    public function markAsFailed($errorMessage = null)
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'processed_at' => now(),
        ]);
    }

    /**
     * Actualizar progreso
     */
    public function updateProgress($step, $percentage, $data = [])
    {
        $updateData = [
            'current_step' => $step,
            'progress_percentage' => $percentage,
            'last_progress_update' => now(),
        ];

        // Agregar datos específicos si se proporcionan
        foreach ($data as $key => $value) {
            if (in_array($key, $this->fillable)) {
                $updateData[$key] = $value;
            }
        }

        $this->update($updateData);
    }

    /**
     * Obtener información de progreso
     */
    public function getProgressInfo()
    {
        return [
            'status' => $this->status,
            'current_step' => $this->current_step,
            'progress_percentage' => $this->progress_percentage,
            'total_rows_processed' => $this->total_rows_processed,
            'ghl_users_processed' => $this->ghl_users_processed,
            'baremetrics_users_fetched' => $this->baremetrics_users_fetched,
            'comparisons_made' => $this->comparisons_made,
            'users_found_count' => $this->users_found_count,
            'users_missing_count' => $this->users_missing_count,
            'last_progress_update' => $this->last_progress_update,
            'is_completed' => $this->status === 'completed',
            'is_failed' => $this->status === 'failed',
        ];
    }
}