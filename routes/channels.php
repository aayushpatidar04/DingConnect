<?php

namespace App\Channels;

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('transactions', function ($user) {
    return in_array($user->role, ['admin', 'retailer']);
});

Broadcast::channel('retailers', function ($user) {
    return $user->role === 'admin';
});

Broadcast::channel('notifications.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
