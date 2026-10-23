<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommissionLevel extends Model
{
    protected $fillable = ['name', 'min_connections', 'sort_order', 'is_active'];

    protected $casts = [
        'min_connections' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function rates(): HasMany
    {
        return $this->hasMany(CommissionRate::class);
    }

    public function profiles(): HasMany
    {
        return $this->hasMany(RetailerCommissionProfile::class);
    }
}
