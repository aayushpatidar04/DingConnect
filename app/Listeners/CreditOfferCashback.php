<?php

namespace App\Listeners;

use App\Events\RechargeSuccess;
use App\Services\OfferService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pays the cashback of the offer a retailer chose, once the recharge has succeeded.
 * Runs on the queue, separately from the recharge job.
 */
class CreditOfferCashback implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    public function handle(RechargeSuccess $event): void
    {
        app(OfferService::class)->settle($event->transaction);
    }

    public function failed(RechargeSuccess $event, Throwable $exception): void
    {
        Log::error('Offer cashback could not be processed', [
            'transaction_id' => $event->transaction->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
