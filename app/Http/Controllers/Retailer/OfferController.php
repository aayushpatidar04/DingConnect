<?php

namespace App\Http\Controllers\Retailer;

use App\Http\Controllers\Controller;
use App\Services\OfferService;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    /**
     * Offers the retailer can use on a recharge of this amount for this provider.
     */
    public function eligible(Request $request, OfferService $offers)
    {
        $data = $request->validate([
            'operator_id' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $user = $request->user();
        $amount = (float) $data['amount'];

        return response()->json([
            'offers' => $offers->eligibleFor($user, $data['operator_id'] ?? null, $amount)
                ->map(fn ($offer) => $offers->present($offer, $user, $amount))
                ->values(),
        ]);
    }
}
