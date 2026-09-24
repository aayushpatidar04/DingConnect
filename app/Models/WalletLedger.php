<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletLedger extends Model
{
    protected $fillable = [
        'wallet_id',
        'transaction_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'ip_address',
        'user_agent',
        'description',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public $timestamps = false;

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
