<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetailerCommissionProfile extends Model
{
    protected $fillable = [
        'user_id', 'commission_level_id', 'actual_connections', 'level_connections',
        'auto_level_enabled', 'level_assigned_at',
    ];

    protected $casts = [
        'actual_connections' => 'integer',
        'level_connections' => 'integer',
        'auto_level_enabled' => 'boolean',
        'level_assigned_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(CommissionLevel::class, 'commission_level_id');
    }
}
