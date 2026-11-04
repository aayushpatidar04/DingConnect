<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Poster extends Model
{
    public const TYPE_ADVERTISEMENT = 'advertisement';

    public const TYPE_FESTIVE_GREETING = 'festive_greeting';

    public const TYPE_ANNOUNCEMENT = 'announcement';

    protected $fillable = [
        'title', 'description', 'image_path', 'link_url', 'poster_type',
        'audience', 'starts_at', 'expires_at', 'is_active', 'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function levels(): BelongsToMany
    {
        return $this->belongsToMany(CommissionLevel::class, 'poster_levels');
    }

    public function retailers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'poster_retailers');
    }

    /** Active, started and not expired posters. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('expires_at', '>', now())
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            });
    }

    public function status(): string
    {
        if (! $this->is_active) {
            return 'paused';
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return 'expired';
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return 'scheduled';
        }

        return 'active';
    }
}
