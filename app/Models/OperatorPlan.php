<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperatorPlan extends Model
{
    protected $fillable = [
        'operator_id',
        'price',
        'currency',
        'benefits',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'benefits' => 'array',
        'is_active' => 'boolean',
    ];

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }
}
