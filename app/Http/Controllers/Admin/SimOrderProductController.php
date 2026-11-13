<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SimOrderProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SimOrderProductController extends Controller
{
    public function store(Request $request): RedirectResponse
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

        SimOrderProduct::create([
            'provider_name' => trim($data['provider_name']),
            'category' => trim($data['category']),
            'price' => $data['price'],
        ]);

        return back()->with('success', 'New SIM product added successfully.');
    }

    public function update(Request $request): RedirectResponse
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

        return back()->with('success', 'SIM product prices updated successfully.');
    }

    public function destroy(SimOrderProduct $simOrderProduct): RedirectResponse
    {
        $simOrderProduct->delete();

        return back()->with('success', 'SIM product deleted successfully.');
    }
}
