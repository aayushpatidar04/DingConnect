<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'shop_name',
        'address',
        'city',
        'county',
        'postcode',
        'vat_number',
        'company_reg_number',
        'utr_number',
        'kyc_id_proof_path',
        'kyc_address_proof_path',
        'kyc_status',
        'kyc_rejection_reason',
        'kyc_verified_at',
        'is_active',
        'ding_customer_id',
        'ding_secret',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'ding_secret',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'kyc_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'password' => 'hashed',
    ];

    // Relationships
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function walletTopups(): HasMany
    {
        return $this->hasMany(WalletTopup::class);
    }


    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, function ($q, $search) {
                // grouped, so it can't leak outside the other conditions
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('shop_name', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, function ($q, $status) {
                if ($status === 'active')
                    $q->where('is_active', true);
                if ($status === 'inactive')
                    $q->where('is_active', false);
            })
            ->when($filters['kyc_status'] ?? null, fn($q, $v) => $q->where('kyc_status', $v));
    }
}
