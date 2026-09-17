<?php

namespace App\Providers;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Policies\RetailerPolicy;
use App\Policies\TransactionPolicy;
use App\Policies\WalletPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Transaction::class => TransactionPolicy::class,
        User::class => RetailerPolicy::class,
        Wallet::class => WalletPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
