<?php

namespace App\Listeners;

use App\Events\RechargeSuccess;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendRechargeSuccessNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(RechargeSuccess $event): void
    {
        $transaction = $event->transaction;

        Notification::create([
            'user_id' => $transaction->user_id,
            'type' => 'recharge_success',
            'title' => 'Recharge Successful',
            'message' => $event->broadcastWith()['message'],
            'data' => ['transaction_id' => $transaction->id],
        ]);
    }
}
