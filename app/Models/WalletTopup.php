<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTopup extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'currency',
        'fee_amount',
        'fee_percentage',
        'total_charged',
        'payment_method',
        'payment_gateway',
        'gateway_transaction_id',
        'gateway_order_id',
        'payment_response',
        'status',
        'receipt_number',
    ];

    protected $casts = [
        'payment_response' => 'array',
        'amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'fee_percentage' => 'decimal:4',
        'total_charged' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
