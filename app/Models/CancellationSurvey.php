<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CancellationSurvey extends Model
{
    protected $fillable = [
        'customer_id',
        'email',
        'reason',
        'comment',
        'additional_comments',
    ];
}
