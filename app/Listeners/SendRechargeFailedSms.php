<?php

namespace App\Listeners;

use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendRechargeFailedSms implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle($event): void
    {
        $transaction = $event->transaction;
        $smsService = app(SmsService::class);

        $smsService->sendRechargeFailed(
            $transaction->user->phone,
            $transaction->mobile_number,
            $transaction->amount
        );
    }
}
