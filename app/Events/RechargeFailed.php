<?php

namespace App\Events;

use App\Models\Transaction;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RechargeFailed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Transaction $transaction;

    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('retailer.' . $this->transaction->user_id);
    }

    public function broadcastAs(): string
    {
        return 'RechargeFailed';
    }

    public function broadcastWith(): array
    {
        return [
            'transaction_id' => $this->transaction->id,
            'mobile_number' => $this->transaction->mobile_number,
            'amount' => $this->transaction->amount,
            'reason' => $this->transaction->failure_reason,
            'message' => "Recharge of £ {$this->transaction->amount} for {$this->transaction->mobile_number} failed. Amount refunded.",
        ];
    }
}
