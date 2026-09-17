<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Operator;
use App\Services\DingConnectService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Inertia\Inertia;

class OperatorController extends Controller
{
    public function index(Request $request)
    {
        $query = Operator::with('country')->orderBy('display_order')->orderBy('name');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('slug', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('country_id')) {
            $query->where('country_id', $request->country_id);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        $operators = $query->paginate(50)->withQueryString();
        $countries = Country::orderBy('name')->get();

        return Inertia::render('Admin/Operators/Index', [
            'operators' => $operators,
            'countries' => $countries,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ding_operator_id' => 'required|string|max:100',
            'country_id' => 'required|exists:countries,id',
            'logo_url' => 'nullable|url',
            'display_order' => 'integer|min:0',
        ]);

        $validated['slug'] = strtolower(Str::slug($validated['name']));
        $validated['is_active'] = $request->has('is_active');

        Operator::create($validated);

        return back()->with('success', 'Operator added successfully!');
    }

    public function update(Request $request, Operator $operator)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ding_operator_id' => 'required|string|max:100',
            'country_id' => 'required|exists:countries,id',
            'logo_url' => 'nullable|url',
            'display_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['slug'] = strtolower(Str::slug($validated['name']));

        $operator->update($validated);

        return back()->with('success', 'Operator updated successfully!');
    }

    public function destroy(Operator $operator)
    {
        $operator->delete();

        return back()->with('success', 'Operator deleted successfully!');
    }

    public function syncFromDing(DingConnectService $dingService)
    {
        $synced = 0;
        $failedCountries = [];

        $countryCodes = Country::whereIn('iso_code', ['GB', 'IN'])->where('is_active', true)->pluck('iso_code')->filter()->values();

        $allowedProviders = [
            'GB' => ['Vodafone United Kingdom', 'O2 United Kingdom', 'Lebara United Kingdom', 'giffgaff United Kingdom', 'Lyca Mobile United Kingdom', '3 United Kingdom'],
            'IN' => ['Airtel India', 'Vi India', 'Jio India', 'BSNL India', 'MTNL India'],
        ];

        foreach ($countryCodes as $isoCode) {
            $operatorResult = $dingService->getProviders($isoCode);

            if (!$operatorResult['success']) {
                $failedCountries[] = $isoCode;
                continue;
            }

            $localCountry = Country::where('iso_code', $isoCode)->first();
            if (!$localCountry) {
                continue;
            }

            $allowed = $allowedProviders[$isoCode] ?? [];
            $filtered = [];

            if (empty($allowed)) {
                $filtered = $operatorResult['data']['Items'];
            } else {
                $filtered = collect($operatorResult['data']['Items'])->filter(function ($op) use ($allowed) {
                    $name = strtolower($op['Name'] ?? '');
                    $code = strtolower($op['ProviderCode'] ?? '');
                    foreach ($allowed as $keyword) {
                        if ($name === strtolower($keyword)) {
                            return true;
                        }
                    }
                    return false;
                })->values()->all();
            }

            foreach ($filtered as $operatorData) {
                $providerCode = $operatorData['ProviderCode'] ?? null;

                if(!$providerCode) {
                    continue;
                }

                $regionCodes = isset($operatorData['RegionCodes']) ? implode(',', $operatorData['RegionCodes']) : null;
                $paymentTypes = isset($operatorData['PaymentTypes']) ? implode(',', $operatorData['PaymentTypes']) : null;

                Operator::updateOrCreate(
                    ['provider_code' => (string) $providerCode],
                    [
                        'name' => $operatorData['Name'] ?? 'Unknown',
                        'provider_code' => $providerCode,
                        'slug' => strtolower(Str::slug($operatorData['Name'] ?? 'unknown')),
                        'country_id' => $localCountry->id,
                        'logo_url' => $operatorData['LogoUrl'] ?? null,
                        'region_codes' => $regionCodes,
                        'payment_types' => $paymentTypes,
                        'validation_regex' => $operatorData['ValidationRegex'] ?? null,
                        'customer_care_number' => $operatorData['CustomerCareNumber'] ?? null,
                        'is_active' => true,
                    ]
                );
                $synced++;
            }
        }

        $msg = "Synced {$synced} operators from DingConnect";
        if (count($failedCountries) > 0) {
            $msg .= " (failed for: " . implode(', ', $failedCountries) . ")";
        }

        return back()->with('success', $msg);
    }
}
