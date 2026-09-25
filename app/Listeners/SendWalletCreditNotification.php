<?php

namespace App\Listeners;

use App\Events\WalletCredited;
use App\Notifications\WalletCreditNotification;

class SendWalletCreditNotification
{
    public function handle(WalletCredited $event): void
    {
        // $event->user->notify(
        //     new WalletCreditNotification(
        //         $event->amount,
        //         $event->balance
        //     )
        // );
    }
}