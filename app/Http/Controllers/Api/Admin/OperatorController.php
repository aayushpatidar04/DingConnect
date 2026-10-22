<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Operator;
use App\Services\DingConnectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OperatorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $operators = Operator::with('country')
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%");
            }))
            ->when($filters['country_id'] ?? null, fn ($query, $countryId) => $query->where('country_id', $countryId))
            ->when(array_key_exists('is_active', $filters), fn ($query) => $query->where('is_active', $filters['is_active']))
            ->orderBy('display_order')
            ->orderBy('name')
            ->paginate(50);

        return response()->json(['success' => true, 'data' => ['operators' => $operators, 'countries' => Country::orderBy('name')->get()]]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatedData($request);
        $validated['slug'] = Str::slug($validated['name']);
        $operator = Operator::create($validated);

        return response()->json(['success' => true, 'data' => $operator->load('country')], 201);
    }

    public function show(Operator $operator): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $operator->load('country')]);
    }

    public function update(Request $request, Operator $operator): JsonResponse
    {
        $validated = $this->validatedData($request);
        $validated['slug'] = Str::slug($validated['name']);
        $operator->update($validated);

        return response()->json(['success' => true, 'data' => $operator->fresh()->load('country')]);
    }

    public function destroy(Operator $operator): JsonResponse
    {
        $operator->delete();

        return response()->json(['success' => true, 'message' => 'Operator deleted.']);
    }

    public function syncFromDing(DingConnectService $dingService): JsonResponse
    {
        $countries = Country::where('iso_code', 'GB')->where('is_active', true)->get();
        $synced = 0;
        $failedCountries = [];

        foreach ($countries as $country) {
            $result = $dingService->getProviders($country->iso_code);

            if (! $result['success']) {
                $failedCountries[] = $country->iso_code;

                continue;
            }

            foreach ($result['data']['Items'] ?? [] as $item) {
                if (empty($item['ProviderCode'])) {
                    continue;
                }

                Operator::updateOrCreate(
                    ['provider_code' => (string) $item['ProviderCode']],
                    [
                        'name' => $item['Name'] ?? 'Unknown',
                        'slug' => Str::slug($item['Name'] ?? 'unknown'),
                        'country_id' => $country->id,
                        'logo_url' => $item['LogoUrl'] ?? null,
                        'region_codes' => implode(',', $item['RegionCodes'] ?? []),
                        'payment_types' => implode(',', $item['PaymentTypes'] ?? []),
                        'validation_regex' => $item['ValidationRegex'] ?? null,
                        'customer_care_number' => $item['CustomerCareNumber'] ?? null,
                        'is_active' => true,
                    ],
                );
                $synced++;
            }
        }

        return response()->json([
            'success' => true,
            'data' => ['synced' => $synced, 'failed_countries' => $failedCountries],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'provider_code' => ['required', 'string', 'max:100'],
            'valuetopup_operator_id' => ['nullable', 'integer'],
            'country_id' => ['required', 'exists:countries,id'],
            'logo_url' => ['nullable', 'url', 'max:2048'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'is_premium' => ['sometimes', 'boolean'],
            'region_codes' => ['nullable', 'string', 'max:1000'],
            'payment_types' => ['nullable', 'string', 'max:1000'],
            'validation_regex' => ['nullable', 'string', 'max:1000'],
            'customer_care_number' => ['nullable', 'string', 'max:50'],
        ]);
    }
}
