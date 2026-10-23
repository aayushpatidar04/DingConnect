<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetailerLevelChange extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'from_level_id', 'to_level_id', 'change_type', 'actual_connections',
        'level_connections_before', 'level_connections_after', 'changed_by', 'reason', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function fromLevel(): BelongsTo
    {
        return $this->belongsTo(CommissionLevel::class, 'from_level_id');
    }

    public function toLevel(): BelongsTo
    {
        return $this->belongsTo(CommissionLevel::class, 'to_level_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
