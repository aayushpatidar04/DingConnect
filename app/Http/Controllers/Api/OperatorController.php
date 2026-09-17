<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OperatorController extends Controller
{
    public function byCountry(Request $request, string $countryIso): JsonResponse
    {
        $country = DB::table('countries')->where('iso_code', $countryIso)->first();

        if (!$country) {
            return response()->json(['success' => false, 'message' => 'Country not found'], 404);
        }

        $operators = DB::table('operators')
            ->where('country_id', $country->id)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get([
                'id', 'name', 'slug', 'provider_code',
                'logo_url', 'region_codes', 'payment_types',
                'validation_regex', 'customer_care_number', 'is_premium'
            ]);

        return response()->json(['success' => true, 'data' => $operators]);
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $countryId = $request->get('country_id');

        $operators = DB::table('operators')
            ->when($countryId, fn($q) => $q->where('country_id', $countryId))
            ->when($query, fn($q) => $q->where('name', 'like', "%{$query}%"))
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get(['id', 'name', 'slug', 'provider_code', 'country_id', 'logo_url']);

        return response()->json(['success' => true, 'data' => $operators]);
    }
}
