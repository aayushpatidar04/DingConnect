<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SimOrder;
use App\Models\User;
use App\Notifications\SimOrderStatusUpdatedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SimOrderController extends Controller
{
    public function index(Request $request): JsonResponse|Response
    {
        $filters = $request->is('api/*')
            ? $request->validate([
                'search' => ['nullable', 'string', 'max:100'],
                'status' => ['nullable', 'in:'.implode(',', SimOrder::STATUSES)],
                'from' => ['nullable', 'date'],
                'to' => ['nullable', 'date', 'after_or_equal:from'],
            ])
            : $request->only(['search', 'status', 'from', 'to']);

        $orders = SimOrder::with('user:id,name,shop_name,email,phone,sim_order_limit')
            ->filter($filters)
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (SimOrder $order) => $request->is('api/*')
                ? $this->apiOrderData($order)
                : [
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
                        'sim_order_limit' => $order->user->sim_order_limit ?? 20,
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

        if ($request->is('api/*')) {
            return response()->json([
                'success' => true,
                'data' => [
                    'orders' => $orders,
                    'stats' => $stats,
                    'monthly_data' => $monthlyData,
                    'filters' => $filters,
                ],
            ]);
        }

        return Inertia::render('Admin/SimOrders/Index', [
            'orders' => $orders,
            'stats' => $stats,
            'filters' => $filters,
            'monthlyData' => $monthlyData,
        ]);
    }

    public function updateStatus(Request $request, SimOrder $simOrder): JsonResponse|RedirectResponse
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

        if ($request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => 'Order status updated to '.ucfirst($data['status']).'.',
                'data' => ['order' => $this->apiOrderData($simOrder->load('user:id,name,shop_name,email,phone,sim_order_limit'))],
            ]);
        }

        return back()->with('success', 'Order status updated to '.ucfirst($data['status']).'.');
    }

    public function updateRetailerLimit(Request $request, User $retailer): JsonResponse|RedirectResponse
    {
        abort_unless($retailer->role === 'retailer', 404);

        $data = $request->validate([
            'sim_order_limit' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);

        $retailer->update([
            'sim_order_limit' => $data['sim_order_limit'],
        ]);

        if ($request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => "SIM order limit for {$retailer->shop_name} updated to {$data['sim_order_limit']}.",
                'data' => [
                    'retailer_id' => $retailer->id,
                    'sim_order_limit' => $retailer->sim_order_limit,
                ],
            ]);
        }

        return back()->with('success', "SIM order limit for {$retailer->shop_name} updated to {$data['sim_order_limit']}.");
    }

    /**
     * @return array<string, int|string|null|array<string, int|string|null>>
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
            'retailer' => $order->user ? [
                'id' => $order->user->id,
                'name' => $order->user->name,
                'shop_name' => $order->user->shop_name,
                'email' => $order->user->email,
                'phone' => $order->user->phone,
                'sim_order_limit' => $order->user->sim_order_limit ?? 20,
            ] : null,
        ];
    }
}
