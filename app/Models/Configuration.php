<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Configuration extends Model
{
    protected $fillable = [
        'ghl_client_id',
        'ghl_client_secret',
        'ghl_code',
        'ghl_token',
        'ghl_location',
        'ghl_company'
    ];
}
