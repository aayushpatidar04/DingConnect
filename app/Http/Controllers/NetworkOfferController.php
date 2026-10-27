<?php

namespace App\Http\Controllers;

use App\Models\Operator;
use App\Models\OperatorPlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NetworkOfferController extends Controller
{
    public function index(Request $request): Response
    {
        $networks = Operator::query()
            ->where('is_active', true)
            ->whereHas('plans', fn (Builder $query) => $query->where('is_active', true))
            ->with([
                'country:id,name,iso_code',
                'plans' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('currency')
                    ->orderBy('price'),
            ])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Operator $operator) => [
                'id' => $operator->id,
                'name' => $operator->name,
                'logo_url' => $operator->logo_url,
                'country_name' => $operator->country?->name,
                'country_iso' => $operator->country?->iso_code,
                'plans' => $operator->plans->map(fn (OperatorPlan $plan) => [
                    'id' => $plan->id,
                    'price' => (float) $plan->price,
                    'currency' => $plan->currency,
                    'benefits' => $plan->benefits,
                ])->values(),
            ])
            ->values();

        return Inertia::render('NetworkOffers/Index', [
            'networks' => $networks,
            'isRetailer' => $request->routeIs('retailer.network-offers'),
        ]);
    }
}
