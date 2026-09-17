<?php

namespace App\Listeners;

use App\Events\RechargeSuccess;
use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

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
