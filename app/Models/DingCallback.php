<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DingCallback extends Model
{
    protected $fillable = ['transaction_id', 'ding_transaction_id', 'payload', 'status', 'error_message', 'received_at', 'processed_at'];

    protected $casts = [
        'payload' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public $timestamps = false;

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
