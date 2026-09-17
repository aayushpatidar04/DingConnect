<?php

namespace App\Events;

use App\Models\Transaction;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransactionCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Transaction $transaction;
    public $user;

    public function __construct(Transaction $transaction, $user)
    {
        $this->transaction = $transaction;
        $this->user        = $user;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->user->id),
            new PrivateChannel('transactions'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'TransactionCreated';
    }

    public function broadcastWith(): array
    {
        return [
            'id'              => $this->transaction->id,
            'mobile_number'   => $this->transaction->mobile_number,
            'amount'          => $this->transaction->amount,
            'status'          => $this->transaction->status,
            'operator_name'   => $this->transaction->operator?->name ?? 'N/A',
            'country_name'    => $this->transaction->country?->name ?? 'N/A',
            'created_at'      => $this->transaction->created_at?->toDateTimeString(),
        ];
    }
}
