<?php

namespace App\Listeners;

use App\Events\RechargeFailed;
use App\Services\OfferService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * A failed recharge does not use up the offer: the reserved use is released.
 */
class ReleaseOfferRedemption implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(RechargeFailed $event): void
    {
        app(OfferService::class)->release($event->transaction);
    }
}
