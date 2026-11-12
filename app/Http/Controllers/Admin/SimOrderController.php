<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SimOrder;
use App\Notifications\SimOrderStatusUpdatedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SimOrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = SimOrder::with('user:id,name,shop_name,email,phone')
            ->filter($request->only(['search', 'status', 'from', 'to']))
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
                'retailer' => $order->user ? [
                    'id' => $order->user->id,
                    'name' => $order->user->name,
                    'shop_name' => $order->user->shop_name,
                    'email' => $order->user->email,
                    'phone' => $order->user->phone,
                ] : null,
            ]);

        $stats = [
            'total' => SimOrder::count(),
            'pending' => SimOrder::where('status', SimOrder::STATUS_PENDING)->count(),
            'processing' => SimOrder::where('status', SimOrder::STATUS_PROCESSING)->count(),
            'dispatched' => SimOrder::where('status', SimOrder::STATUS_DISPATCHED)->count(),
            'delivered' => SimOrder::where('status', SimOrder::STATUS_DELIVERED)->count(),
            'this_month_quantity' => SimOrder::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('quantity'),
        ];

        $monthlyData = SimOrder::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('SUM(quantity) as total_quantity'),
            DB::raw('COUNT(*) as total_orders')
        )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'total_quantity' => (int) $row->total_quantity,
                'total_orders' => (int) $row->total_orders,
            ])
            ->toArray();

        return Inertia::render('Admin/SimOrders/Index', [
            'orders' => $orders,
            'stats' => $stats,
            'filters' => $request->only(['search', 'status', 'from', 'to']),
            'monthlyData' => $monthlyData,
        ]);
    }

    public function updateStatus(Request $request, SimOrder $simOrder)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(SimOrder::STATUSES)],
            'tracking_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $updates = ['status' => $data['status']];

        if (isset($data['tracking_notes'])) {
            $updates['tracking_notes'] = $data['tracking_notes'];
        }

        if ($data['status'] === SimOrder::STATUS_DISPATCHED && ! $simOrder->dispatched_at) {
            $updates['dispatched_at'] = now();
        }

        if ($data['status'] === SimOrder::STATUS_DELIVERED && ! $simOrder->delivered_at) {
            $updates['delivered_at'] = now();
        }

        $simOrder->update($updates);

        $simOrder->user->notify(new SimOrderStatusUpdatedNotification($simOrder));

        return back()->with('success', 'Order status updated to '.ucfirst($data['status']).'.');
    }
}
