<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SimProviderComparison extends Model
{
    protected $fillable = [
        'admin_id',
        'operator_id',
        'reconciliation_month',
        'file_name',
        'provider_name',
        'total_internal',
        'total_provider',
        'matched_count',
        'missing_count',
        'additional_count',
        'report_data',
    ];

    protected $casts = [
        'total_internal' => 'integer',
        'total_provider' => 'integer',
        'matched_count' => 'integer',
        'missing_count' => 'integer',
        'additional_count' => 'integer',
        'report_data' => 'array',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }
}
