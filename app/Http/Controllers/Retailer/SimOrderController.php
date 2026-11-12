<?php

namespace App\Http\Controllers\Retailer;

use App\Http\Controllers\Controller;
use App\Models\SimOrder;
use App\Models\User;
use App\Notifications\SimOrderPlacedNotification;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SimOrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = $request->user()
            ->simOrders()
            ->filter($request->only(['status', 'from', 'to']))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (SimOrder $order) => [
                'id' => $order->id,
                'quantity' => $order->quantity,
                'status' => $order->status,
                'tracking_notes' => $order->tracking_notes,
                'dispatched_at' => $order->dispatched_at?->format('d M Y H:i'),
                'delivered_at' => $order->delivered_at?->format('d M Y H:i'),
                'created_at' => $order->created_at->format('d M Y H:i'),
            ]);

        $stats = [
            'total' => $request->user()->simOrders()->count(),
            'pending' => $request->user()->simOrders()->where('status', SimOrder::STATUS_PENDING)->count(),
            'dispatched' => $request->user()->simOrders()->where('status', SimOrder::STATUS_DISPATCHED)->count(),
            'delivered' => $request->user()->simOrders()->where('status', SimOrder::STATUS_DELIVERED)->count(),
        ];

        return Inertia::render('Retailer/SimOrders/Index', [
            'orders' => $orders,
            'stats' => $stats,
            'filters' => $request->only(['status', 'from', 'to']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        $order = $request->user()->simOrders()->create([
            'quantity' => $data['quantity'],
            'status' => SimOrder::STATUS_PENDING,
        ]);

        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new SimOrderPlacedNotification($order));
        }

        return back()->with('success', 'SIM card order placed successfully! You will be notified when it is dispatched.');
    }
}
