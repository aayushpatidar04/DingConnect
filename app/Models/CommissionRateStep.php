<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionRateStep extends Model
{
    protected $fillable = ['commission_rate_id', 'from_sequence', 'to_sequence', 'amount'];

    protected $casts = [
        'from_sequence' => 'integer',
        'to_sequence' => 'integer',
        'amount' => 'decimal:2',
    ];

    public function rate(): BelongsTo
    {
        return $this->belongsTo(CommissionRate::class, 'commission_rate_id');
    }
}
