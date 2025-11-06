<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CancellationSurvey extends Model
{
    protected $fillable = [
        'customer_id',
        'stripe_customer_id',
        'email',
        'reason',
        'comment',
        'additional_comments',
    ];

    /**
     * Obtener el tracking asociado (método helper)
     */
    public function getTrackingAttribute()
    {
        if (!$this->email && !$this->customer_id) {
            return null;
        }
        
        $query = CancellationTracking::query();
        
        if ($this->email) {
            $query->where('email', $this->email);
        }
        
        if ($this->customer_id) {
            if ($this->email) {
                $query->orWhere('customer_id', $this->customer_id);
            } else {
                $query->where('customer_id', $this->customer_id);
            }
        }
        
        return $query->latest()->first();
    }
}
