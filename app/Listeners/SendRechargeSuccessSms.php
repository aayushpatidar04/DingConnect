<?php

namespace App\Listeners;

use App\Events\RechargeSuccess;
use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendRechargeSuccessSms implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(RechargeSuccess $event): void
    {
        $transaction = $event->transaction;
        $smsService = app(SmsService::class);

        $smsService->sendRechargeSuccess(
            $transaction->user->phone,
            $transaction->mobile_number,
            (float) $transaction->amount,
            $transaction->operator->name
        );
    }
}
