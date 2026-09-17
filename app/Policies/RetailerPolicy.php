<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Wallet;

class RetailerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function view(User $user, User $retailer): bool
    {
        return $user->role === 'admin'
            || (int) $user->id === (int) $retailer->id;
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, User $retailer): bool
    {
        return $user->role === 'admin'
            || (int) $user->id === (int) $retailer->id;
    }

    public function delete(User $user, User $retailer): bool
    {
        return $user->role === 'admin';
    }

    public function creditWallet(User $user, User $retailer): bool
    {
        return $user->role === 'admin';
    }

    public function processKyc(User $user, User $retailer): bool
    {
        return $user->role === 'admin';
    }
}
