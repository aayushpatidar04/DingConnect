<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SimOrderProduct extends Model
{
    protected $fillable = [
        'provider_name',
        'category',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function orderItems(): HasMany
    {
        return $this->hasMany(SimOrderItem::class);
    }
}
