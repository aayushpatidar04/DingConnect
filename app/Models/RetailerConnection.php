<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetailerConnection extends Model
{
    protected $fillable = [
        'user_id', 'account_key', 'allowed_number_id', 'account_number', 'operator_id',
        'first_transaction_id', 'topups_count', 'connected_at',
    ];

    protected $casts = [
        'topups_count' => 'integer',
        'connected_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }
}
