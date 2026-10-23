<?php

namespace App\Listeners;

use App\Events\RechargeSuccess;
use App\Services\CommissionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pays retailer cashback after a successful recharge. Runs on the queue, separately from the
 * recharge job, so a problem here can never affect the recharge itself.
 */
class CreditRechargeCommission implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    public function handle(RechargeSuccess $event): void
    {
        app(CommissionService::class)->processSuccessfulRecharge($event->transaction);
    }

    public function failed(RechargeSuccess $event, Throwable $exception): void
    {
        Log::error('Commission could not be processed', [
            'transaction_id' => $event->transaction->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
