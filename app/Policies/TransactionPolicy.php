<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function view(User $user, Transaction $transaction): bool
    {
        return (int) $user->id === (int) $transaction->user_id;
    }

    public function refund(User $user, Transaction $transaction): bool
    {
        return (int) $user->id === (int) $transaction->user_id
            && $transaction->status === 'success';
    }

    public function receipt(User $user, Transaction $transaction): bool
    {
        return (int) $user->id === (int) $transaction->user_id
            && in_array($transaction->status, ['success', 'failed']);
    }
}
