<?php

namespace App\Listeners;

use App\Events\RechargeFailed;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendRechargeFailedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(RechargeFailed $event): void
    {
        $transaction = $event->transaction;

        Notification::create([
            'user_id' => $transaction->user_id,
            'type' => 'recharge_failed',
            'title' => 'Recharge Failed',
            'message' => $event->broadcastWith()['message'],
            'data' => ['transaction_id' => $transaction->id],
        ]);
    }
}
