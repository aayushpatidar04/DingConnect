<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        \App\Events\RechargeSuccess::class => [
            \App\Listeners\SendRechargeSuccessNotification::class,
            \App\Listeners\SendRechargeSuccessSms::class,
        ],
        \App\Events\RechargeFailed::class => [
            \App\Listeners\SendRechargeFailedNotification::class,
            \App\Listeners\SendRechargeFailedSms::class,
        ],
        \App\Events\WalletCredited::class => [
            \App\Listeners\SendWalletCreditNotification::class,
        ],
    ];

    public function boot(): void
    {
        //
    }
}
