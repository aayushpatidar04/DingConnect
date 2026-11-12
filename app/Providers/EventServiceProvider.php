<?php

namespace App\Providers;

use App\Events\RechargeFailed;
use App\Events\RechargeSuccess;
use App\Events\WalletCredited;
use App\Listeners\CreditOfferCashback;
use App\Listeners\CreditRechargeCommission;
use App\Listeners\ReleaseOfferRedemption;
use App\Listeners\SendRechargeFailedNotification;
use App\Listeners\SendRechargeFailedSms;
use App\Listeners\SendRechargeSuccessNotification;
use App\Listeners\SendRechargeSuccessSms;
use App\Listeners\SendWalletCreditNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        RechargeSuccess::class => [
            SendRechargeSuccessNotification::class,
            SendRechargeSuccessSms::class,
            CreditRechargeCommission::class,
            CreditOfferCashback::class,
        ],
        RechargeFailed::class => [
            SendRechargeFailedNotification::class,
            SendRechargeFailedSms::class,
            ReleaseOfferRedemption::class,
        ],
        WalletCredited::class => [
            SendWalletCreditNotification::class,
        ],
    ];

    public function boot(): void
    {
        //
    }
}
