<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = ['user_id', 'action', 'description', 'ip_address', 'user_agent', 'metadata'];

    protected $casts = [
        'metadata' => 'array',
    ];

    public $timestamps = false;

    protected $dates = ['created_at'];
}
