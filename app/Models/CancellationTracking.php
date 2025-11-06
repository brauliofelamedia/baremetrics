<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class CancellationTracking extends Model
{
    protected $table = 'cancellation_tracking';
    
    protected $fillable = [
        'email',
        'customer_id',
        'stripe_customer_id',
        'token',
        'email_requested',
        'email_requested_at',
        'survey_viewed',
        'survey_viewed_at',
        'survey_completed',
        'survey_completed_at',
        'baremetrics_cancelled',
        'baremetrics_cancelled_at',
        'baremetrics_cancellation_details',
        'stripe_cancelled',
        'stripe_cancelled_at',
        'stripe_cancellation_details',
        'process_completed',
        'process_completed_at',
        'current_step',
        'notes',
    ];

    protected $casts = [
        'email_requested' => 'boolean',
        'email_requested_at' => 'datetime',
        'survey_viewed' => 'boolean',
        'survey_viewed_at' => 'datetime',
        'survey_completed' => 'boolean',
        'survey_completed_at' => 'datetime',
        'baremetrics_cancelled' => 'boolean',
        'baremetrics_cancelled_at' => 'datetime',
        'stripe_cancelled' => 'boolean',
        'stripe_cancelled_at' => 'datetime',
        'process_completed' => 'boolean',
        'process_completed_at' => 'datetime',
    ];

    /**
     * Obtener o crear un registro de seguimiento por email
     */
    public static function getOrCreateByEmail(string $email, ?string $token = null)
    {
        // Si hay token, buscar primero por token
        if ($token) {
            $tracking = self::where('email', $email)
                ->where('token', $token)
                ->latest()
                ->first();
            
            if ($tracking) {
                return $tracking;
            }
        }
        
        // Si no hay token o no se encontró con token, buscar el más reciente sin importar el token
        $tracking = self::where('email', $email)
            ->latest()
            ->first();

        if (!$tracking) {
            $tracking = self::create([
                'email' => $email,
                'token' => $token,
            ]);
        } elseif ($token && !$tracking->token) {
            // Si tenemos un token pero el registro no lo tiene, actualizarlo
            $tracking->update(['token' => $token]);
        }

        return $tracking;
    }

    /**
     * Marcar que se solicitó el correo de cancelación
     */
    public function markEmailRequested(?string $token = null)
    {
        $this->update([
            'email_requested' => true,
            'email_requested_at' => now(),
            'token' => $token ?? $this->token,
            'current_step' => 'email_requested',
        ]);
    }

    /**
     * Marcar que el usuario vio la encuesta
     */
    public function markSurveyViewed(?string $customerId = null)
    {
        $this->update([
            'survey_viewed' => true,
            'survey_viewed_at' => now(),
            'customer_id' => $customerId ?? $this->customer_id,
            'current_step' => 'survey_viewed',
        ]);
    }

    /**
     * Marcar que el usuario completó la encuesta
     */
    public function markSurveyCompleted(?string $customerId = null, ?string $stripeCustomerId = null)
    {
        $this->update([
            'survey_completed' => true,
            'survey_completed_at' => now(),
            'customer_id' => $customerId ?? $this->customer_id,
            'stripe_customer_id' => $stripeCustomerId ?? $this->stripe_customer_id,
            'current_step' => 'survey_completed',
        ]);
    }

    /**
     * Marcar que se canceló en Baremetrics
     */
    public function markBaremetricsCancelled(?string $details = null)
    {
        $this->update([
            'baremetrics_cancelled' => true,
            'baremetrics_cancelled_at' => now(),
            'baremetrics_cancellation_details' => $details,
            'current_step' => 'baremetrics_cancelled',
        ]);

        // Verificar si el proceso está completo
        $this->checkIfProcessCompleted();
    }

    /**
     * Marcar que se canceló en Stripe
     */
    public function markStripeCancelled(?string $details = null)
    {
        $this->update([
            'stripe_cancelled' => true,
            'stripe_cancelled_at' => now(),
            'stripe_cancellation_details' => $details,
            'current_step' => 'stripe_cancelled',
        ]);

        // Verificar si el proceso está completo
        $this->checkIfProcessCompleted();
    }

    /**
     * Verificar si el proceso de cancelación está completo
     */
    protected function checkIfProcessCompleted()
    {
        if ($this->survey_completed && 
            $this->baremetrics_cancelled && 
            $this->stripe_cancelled && 
            !$this->process_completed) {
            
            $this->update([
                'process_completed' => true,
                'process_completed_at' => now(),
                'current_step' => 'completed',
            ]);
        }
    }

    /**
     * Obtener el estado actual del proceso
     */
    public function getCurrentStatus(): string
    {
        if ($this->process_completed) {
            return 'completed';
        }

        if ($this->stripe_cancelled && $this->baremetrics_cancelled) {
            return 'cancelled_both';
        }

        if ($this->stripe_cancelled) {
            return 'stripe_cancelled';
        }

        if ($this->baremetrics_cancelled) {
            return 'baremetrics_cancelled';
        }

        if ($this->survey_completed) {
            return 'survey_completed';
        }

        if ($this->survey_viewed) {
            return 'survey_viewed';
        }

        if ($this->email_requested) {
            return 'email_requested';
        }

        return 'not_started';
    }
}
