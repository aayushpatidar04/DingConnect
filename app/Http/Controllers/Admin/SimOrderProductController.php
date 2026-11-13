<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SimOrderProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SimOrderProductController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'sim_products' => SimOrderProduct::orderBy('id')->get(['id', 'provider_name', 'category', 'price']),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'provider_name' => ['required', 'string', 'max:80'],
            'category' => [
                'required',
                'string',
                'max:20',
                Rule::unique('sim_order_products')->where('provider_name', $request->provider_name),
            ],
            'price' => ['required', 'numeric', 'min:0', 'max:100000', 'decimal:0,2'],
        ]);

        $product = SimOrderProduct::create([
            'provider_name' => trim($data['provider_name']),
            'category' => trim($data['category']),
            'price' => $data['price'],
        ]);

        if ($request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => 'New SIM product added successfully.',
                'data' => ['sim_product' => $product],
            ], 201);
        }

        return back()->with('success', 'New SIM product added successfully.');
    }

    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'products' => ['required', 'array', 'min:1'],
            'products.*.id' => ['required', 'integer', 'distinct', 'exists:sim_order_products,id'],
            'products.*.price' => ['required', 'numeric', 'min:0', 'max:100000', 'decimal:0,2'],
        ]);

        $products = SimOrderProduct::whereKey(collect($data['products'])->pluck('id'))->get()->keyBy('id');

        DB::transaction(function () use ($data, $products): void {
            foreach ($data['products'] as $productData) {
                $products->get($productData['id'])->update([
                    'price' => $productData['price'],
                ]);
            }
        });

        if ($request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => 'SIM product prices updated successfully.',
                'data' => [
                    'sim_products' => SimOrderProduct::whereKey(collect($data['products'])->pluck('id'))
                        ->orderBy('id')
                        ->get(['id', 'provider_name', 'category', 'price']),
                    'updated_count' => count($data['products']),
                ],
            ]);
        }

        return back()->with('success', 'SIM product prices updated successfully.');
    }

    public function destroy(Request $request, SimOrderProduct $simOrderProduct): JsonResponse|RedirectResponse
    {
        $productId = $simOrderProduct->id;
        $simOrderProduct->delete();

        if ($request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => 'SIM product deleted successfully.',
                'data' => ['sim_product_id' => $productId],
            ]);
        }

        return back()->with('success', 'SIM product deleted successfully.');
    }
}
