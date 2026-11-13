<?php

namespace App\Http\Controllers\Retailer;

use App\Http\Controllers\Controller;
use App\Models\Operator;
use App\Models\SimOrder;
use App\Models\SimOrderProduct;
use App\Models\User;
use App\Notifications\SimOrderPlacedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SimOrderController extends Controller
{
    public function index(Request $request): JsonResponse|Response
    {
        $user = $request->user();
        $simOrderLimit = $user->sim_order_limit ?? 500;

        $operators = Operator::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $simCategories = ['5GBP', '10GBP', '15GBP', 'Higher'];

        if ($request->is('api/*')) {
            $filters = $request->validate([
                'status' => ['nullable', 'in:'.implode(',', SimOrder::STATUSES)],
                'from' => ['nullable', 'date'],
                'to' => ['nullable', 'date', 'after_or_equal:from'],
            ]);

            $orders = $user->simOrders()
                ->with(['items.operator:id,name', 'items.product'])
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
                    'operators' => $operators,
                    'sim_categories' => $simCategories,
                    'sim_order_limit' => $simOrderLimit,
                    'filters' => $filters,
                ],
            ]);
        }

        $orders = $user
            ->simOrders()
            ->with(['items.operator:id,name', 'items.product'])
            ->filter($request->only(['status', 'from', 'to']))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (SimOrder $order) => [
                'id' => $order->id,
                'quantity' => $order->quantity,
                'status' => $order->status,
                'tracking_notes' => $order->tracking_notes,
                'items' => $order->items->map(fn ($item) => [
                    'provider_name' => $item->product?->provider_name ?? $item->operator?->name,
                    'category' => $item->category,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                ]),
                'dispatched_at' => $order->dispatched_at?->format('d M Y H:i'),
                'delivered_at' => $order->delivered_at?->format('d M Y H:i'),
                'created_at' => $order->created_at->format('d M Y H:i'),
            ]);

        $stats = $this->stats($user, $simOrderLimit);

        return Inertia::render('Retailer/SimOrders/Index', [
            'orders' => $orders,
            'stats' => $stats,
            'operators' => $operators,
            'simCategories' => $simCategories,
            'simProducts' => SimOrderProduct::orderBy('id')->get(['id', 'provider_name', 'category', 'price']),
            'filters' => $request->only(['status', 'from', 'to']),
            'simOrderLimit' => $simOrderLimit,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        if (! $request->is('api/*')) {
            return $this->storePortalOrder($request);
        }

        $user = $request->user();
        $maxLimit = $user->sim_order_limit ?? 500;

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.operator_id' => ['required', 'exists:operators,id'],
            'items.*.category' => ['required', 'string', 'max:50'],
            'items.*.quantity' => ['required', 'integer', 'min:0'],
        ]);

        $totalQuantity = collect($data['items'])->sum('quantity');

        if ($totalQuantity <= 0) {
            throw ValidationException::withMessages([
                'items' => 'Please enter a quantity of at least 1 SIM card.',
            ]);
        }

        if ($totalQuantity > $maxLimit) {
            throw ValidationException::withMessages([
                'items' => "Total SIM order quantity ({$totalQuantity}) exceeds your account limit of {$maxLimit}.",
            ]);
        }

        $order = DB::transaction(function () use ($user, $totalQuantity, $data) {
            $order = $user->simOrders()->create([
                'quantity' => $totalQuantity,
                'status' => SimOrder::STATUS_PENDING,
            ]);

            foreach ($data['items'] as $item) {
                $qty = (int) ($item['quantity'] ?? 0);
                if ($qty > 0) {
                    $order->items()->create([
                        'operator_id' => $item['operator_id'],
                        'category' => $item['category'],
                        'quantity' => $qty,
                    ]);
                }
            }

            return $order->load(['items.operator', 'items.product']);
        });

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
     * @return array<string, int|string|null|array>
     */
    private function apiOrderData(SimOrder $order): array
    {
        return [
            'id' => $order->id,
            'quantity' => $order->quantity,
            'status' => $order->status,
            'tracking_notes' => $order->tracking_notes,
            'items' => $order->items->map(fn ($item) => [
                'operator_name' => $item->operator?->name ?? $item->product?->provider_name,
                'category' => $item->category,
                'quantity' => $item->quantity,
            ]),
            'dispatched_at' => $order->dispatched_at?->toIso8601String(),
            'delivered_at' => $order->delivered_at?->toIso8601String(),
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }

    private function storePortalOrder(Request $request): RedirectResponse
    {
        $user = $request->user();
        $maxLimit = $user->sim_order_limit ?? 500;

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.sim_order_product_id' => ['required', 'integer', 'distinct', 'exists:sim_order_products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:0'],
        ]);

        $totalQuantity = collect($data['items'])->sum('quantity');

        if ($totalQuantity <= 0) {
            throw ValidationException::withMessages([
                'items' => 'Please enter a quantity of at least 1 SIM card.',
            ]);
        }

        if ($totalQuantity > $maxLimit) {
            throw ValidationException::withMessages([
                'items' => "Total SIM order quantity ({$totalQuantity}) exceeds your account limit of {$maxLimit}.",
            ]);
        }

        $order = DB::transaction(function () use ($user, $totalQuantity, $data): SimOrder {
            $products = SimOrderProduct::whereKey(collect($data['items'])->pluck('sim_order_product_id'))
                ->get()
                ->keyBy('id');

            $order = $user->simOrders()->create([
                'quantity' => $totalQuantity,
                'status' => SimOrder::STATUS_PENDING,
            ]);

            foreach ($data['items'] as $item) {
                $quantity = (int) $item['quantity'];
                if ($quantity === 0) {
                    continue;
                }

                $product = $products->get((int) $item['sim_order_product_id']);

                if (! $product) {
                    throw ValidationException::withMessages([
                        'items' => 'One or more selected SIM products are no longer available.',
                    ]);
                }

                $order->items()->create([
                    'sim_order_product_id' => $product->id,
                    'category' => $product->category,
                    'unit_price' => $product->price,
                    'quantity' => $quantity,
                ]);
            }

            return $order->load(['items.operator', 'items.product']);
        });

        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new SimOrderPlacedNotification($order));
        }

        return back()->with('success', 'SIM card order placed successfully! You will be notified when it is dispatched.');
    }
}
