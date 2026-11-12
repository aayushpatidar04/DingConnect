<?php

namespace App\Http\Controllers\Retailer;

use App\Http\Controllers\Controller;
use App\Models\SimOrder;
use App\Models\User;
use App\Notifications\SimOrderPlacedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SimOrderController extends Controller
{
    public function index(Request $request): JsonResponse|Response
    {
        $user = $request->user();
        $simOrderLimit = $user->sim_order_limit ?? 50;

        if ($request->is('api/*')) {
            $filters = $request->validate([
                'status' => ['nullable', 'in:'.implode(',', SimOrder::STATUSES)],
                'from' => ['nullable', 'date'],
                'to' => ['nullable', 'date', 'after_or_equal:from'],
            ]);

            $orders = $user->simOrders()
                ->filter($filters)
                ->orderByDesc('created_at')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (SimOrder $order): array => $this->apiOrderData($order));

            return response()->json([
                'success' => true,
                'data' => [
                    'orders' => $orders,
                    'stats' => $this->stats($user, $simOrderLimit),
                    'sim_order_limit' => $simOrderLimit,
                    'filters' => $filters,
                ],
            ]);
        }

        $orders = $user
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

        $stats = $this->stats($user, $simOrderLimit);

        return Inertia::render('Retailer/SimOrders/Index', [
            'orders' => $orders,
            'stats' => $stats,
            'filters' => $request->only(['status', 'from', 'to']),
            'simOrderLimit' => $simOrderLimit,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $maxLimit = $user->sim_order_limit ?? 50;

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', "max:{$maxLimit}"],
        ], [
            'quantity.max' => "You cannot order more than {$maxLimit} SIM cards in a single order based on your account limit.",
        ]);

        $order = $user->simOrders()->create([
            'quantity' => $data['quantity'],
            'status' => SimOrder::STATUS_PENDING,
        ]);

        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new SimOrderPlacedNotification($order));
        }

        if ($request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => 'SIM card order placed successfully! You will be notified when it is dispatched.',
                'data' => ['order' => $this->apiOrderData($order)],
            ], 201);
        }

        return back()->with('success', 'SIM card order placed successfully! You will be notified when it is dispatched.');
    }

    /**
     * @return array<string, int>
     */
    private function stats(User $user, int $simOrderLimit): array
    {
        return [
            'total' => $user->simOrders()->count(),
            'pending' => $user->simOrders()->where('status', SimOrder::STATUS_PENDING)->count(),
            'dispatched' => $user->simOrders()->where('status', SimOrder::STATUS_DISPATCHED)->count(),
            'delivered' => $user->simOrders()->where('status', SimOrder::STATUS_DELIVERED)->count(),
            'sim_order_limit' => $simOrderLimit,
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    private function apiOrderData(SimOrder $order): array
    {
        return [
            'id' => $order->id,
            'quantity' => $order->quantity,
            'status' => $order->status,
            'tracking_notes' => $order->tracking_notes,
            'dispatched_at' => $order->dispatched_at?->toIso8601String(),
            'delivered_at' => $order->delivered_at?->toIso8601String(),
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }
}
