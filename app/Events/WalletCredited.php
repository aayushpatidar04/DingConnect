<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WalletCredited implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $amount;
    public $balance;
    public $message;

    public function __construct(User $user, float $amount, float $balance)
    {
        $this->user = $user;
        $this->amount = $amount;
        $this->balance = $balance;

        $this->message = "₹{$amount} credited successfully. Current balance: ₹{$balance}";
    }

    public function broadcastOn()
    {
        return new PrivateChannel('user.' . $this->user->id);
    }

    public function broadcastAs()
    {
        return 'WalletCredited';
    }

    public function broadcastWith()
    {
        return [
            'user_id' => $this->user->id,
            'amount' => $this->amount,
            'balance' => $this->balance,
            'message' => $this->message,
        ];
    }
}