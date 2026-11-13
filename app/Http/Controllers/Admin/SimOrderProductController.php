<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SimOrderProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SimOrderProductController extends Controller
{
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
}
