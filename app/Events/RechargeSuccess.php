<?php

namespace App\Events;

use App\Models\Transaction;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RechargeSuccess implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Transaction $transaction;
    public ?string $receiptNumber;

    public function __construct(Transaction $transaction, ?string $receiptNumber = null)
    {
        $this->transaction = $transaction;
        $this->receiptNumber = $receiptNumber ?? $transaction->receipt_number;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('retailer.' . $this->transaction->user_id);
    }

    public function broadcastAs(): string
    {
        return 'RechargeSuccess';
    }

    public function broadcastWith(): array
    {
        return [
            'transaction_id' => $this->transaction->id,
            'receipt_number' => $this->receiptNumber,
            'mobile_number' => $this->transaction->mobile_number,
            'amount' => $this->transaction->amount,
            'status' => 'success',
            'message' => "Recharge of £ {$this->transaction->amount} for {$this->transaction->mobile_number} was successful!",
        ];
    }
}
