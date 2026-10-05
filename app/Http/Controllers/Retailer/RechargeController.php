<?php

namespace App\Http\Controllers\Retailer;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessRechargeJob;
use App\Models\Country;
use App\Models\Operator;
use App\Models\Transaction;
use App\Models\AllowedNumber;
use App\Services\DingConnectService;
use App\Services\ValuetopupService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class RechargeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $walletService = app(WalletService::class);
        $wallet = $walletService->getWallet($user);
        $availableBalance = $walletService->getAvailableBalance($wallet);

        $countries = Country::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'iso_code', 'calling_code', 'flag_emoji']);

        return Inertia::render('Retailer/Recharge/New', compact('wallet', 'availableBalance', 'countries'));
    }

    // =========================================================================
    // API ENDPOINTS
    // =========================================================================

    /**
     * Get operators for a phone number using DingConnect GetProviders API.
     * Always hits the API, stores/updates results in DB.
     * GET /api/V1/GetProviders?countryIsos=<>&accountNumber=<>
     */
    public function getOperators(Request $request, DingConnectService $dingService): JsonResponse
    {
        $request->validate([
            'phone_number' => 'required|string|min:7|max:20',
            'country_iso' => 'nullable|string|size:2',
        ]);

        $accountNumber = preg_replace('/[^0-9]/', '', $request->phone_number);
        $countryIso = $request->country_iso ?? $this->detectCountryFromPhone($accountNumber);

        $result = $dingService->getProviders($countryIso, null, $accountNumber);

        if (!$result['success']) {
            return response()->json(['success' => false, 'error' => $result['error']], 400);
        }

        $country = Country::where('iso_code', $countryIso)->first();

        $providers = collect($result['data']['Items'] ?? $result['data']['Providers'] ?? [])->map(function ($item) use ($country) {
            $operator = Operator::updateOrCreate(
                [
                    'provider_code' => $item['ProviderCode'],
                ],
                [
                    'name' => $item['Name'] ?? '',
                    'slug' => Str::slug($item['Name'] ?? $item['ProviderCode']),
                    'country_id' => $country?->id,
                    'logo_url' => $item['LogoUrl'] ?? null,
                    'region_codes' => json_encode($item['RegionCodes'] ?? []),
                    'payment_types' => json_encode($item['PaymentTypes'] ?? []),
                    'validation_regex' => $item['ValidationRegex'] ?? null,
                    'is_premium' => $item['IsPremium'] ?? false,
                    'is_active' => true,
                ]
            );

            return [
                'id' => $operator->id,
                'provider_code' => $operator->provider_code,
                'name' => $operator->name,
                'logo_url' => $operator->logo_url,
                'is_premium' => $operator->is_premium,
                'region_codes' => $item['RegionCodes'] ?? [],
                'payment_types' => $item['PaymentTypes'] ?? [],
            ];
        })->filter(fn($p) => !empty($p['provider_code']))->values();

        return response()->json([
            'success' => true,
            'country_iso' => $countryIso,
            'providers' => $providers,
        ]);
    }

    /**
     * Simple provider list by country (no phone number required).
     * Always hits the DingConnect API first, then upserts into DB.
     * GET /retailer/recharge/providers?country_iso=GB
     */
    public function getProvidersSimple(Request $request, DingConnectService $dingService): JsonResponse
    {
        $request->validate([
            'country_iso' => 'required|string|size:2',
        ]);

        $countryIso = $request->query('country_iso');
        $result = $dingService->getProviders($countryIso);

        if (!$result['success'] || empty($result['data'])) {
            return response()->json(['success' => true, 'providers' => []]);
        }

        $country = Country::where('iso_code', $countryIso)->first();

        // Always hit API, then upsert into DB
        $providers = collect($result['data']['Items'] ?? $result['data'] ?? [])->map(function ($item) use ($country, $countryIso) {
            $operator = Operator::updateOrCreate(
                [
                    'provider_code' => $item['ProviderCode'],
                ],
                [
                    'name' => $item['Name'] ?? '',
                    'slug' => Str::slug($item['Name'] ?? $item['ProviderCode']),
                    'country_id' => $country?->id,
                    'logo_url' => $item['LogoUrl'] ?? null,
                    'region_codes' => json_encode($item['RegionCodes'] ?? []),
                    'payment_types' => json_encode($item['PaymentTypes'] ?? []),
                    'validation_regex' => $item['ValidationRegex'] ?? null,
                    'is_premium' => $item['IsPremium'] ?? false,
                    'is_active' => true,
                ]
            );

            return [
                'provider_code' => $operator->provider_code,
                'provider_id' => $operator->id,
                'name' => $operator->name,
                'logo_url' => $operator->logo_url,
                'country_iso' => $countryIso,
            ];
        })->filter(fn($p) => !empty($p['provider_code']))->values()->all();

        return response()->json(['success' => true, 'providers' => $providers]);
    }

    /**
     * Fetch Valuetopup operators for a country and return them in the same
     * shape as getProvidersSimple so the frontend can merge them transparently.
     * GET /recharge/valuetopup/operators?country_iso=GB
     */
    public function getValuetopupOperators(Request $request, ValuetopupService $vt): JsonResponse
    {
        $request->validate([
            'country_iso' => 'required|string|size:2',
        ]);

        $countryIso = $request->query('country_iso');
        $country = Country::where('iso_code', $countryIso)->first();

        $result = $vt->operators();

        if (!($result['responseCode'] ?? '') === '000' || empty($result['payLoad'])) {
            return response()->json(['success' => true, 'providers' => []]);
        }

        // Load existing DingConnect operators for this country to enable matching
        $existingOperators = Operator::where('country_id', $country?->id)
            ->whereNotNull('provider_code')
            ->where('provider_code', 'not like', 'vt-%')
            ->get()
            ->keyBy(fn($o) => mb_strtolower($o->name));

        // Reverse lookup: VT ID -> Ding operator name (for already-linked operators)
        $vtIdToDingName = $existingOperators
            ->filter(fn($o) => !empty($o->valuetopup_operator_id))
            ->mapWithKeys(fn($o) => [(string) $o->valuetopup_operator_id => $o->name])
            ->all();

        $syncedProviders = [];

        foreach ($result['payLoad'] as $item) {
            $vtName = $item['operatorName'];
            $vtId = (string) $item['operatorId'];

            // Check if already linked via valuetopup_operator_id
            if (isset($vtIdToDingName[$vtId])) {
                $operator = $existingOperators[mb_strtolower($vtIdToDingName[$vtId])] ?? null;
            } else {
                // Fuzzy name match against existing DingConnect operators
                $operator = $this->findMatchingDingOperator($vtName, $existingOperators);
            }

            if ($operator) {
                // Link existing DingConnect operator to this VT ID
                $operator->update([
                    'valuetopup_operator_id' => $item['operatorId'],
                    'logo_url' => $operator->logo_url ?: ($item['imageUrl'] ?? null),
                    'payment_types' => $operator->payment_types ?: json_encode(['valuetopup']),
                ]);
            } else {
                // No DingConnect match — create a new operator row
                $operator = Operator::create([
                    'name' => $vtName,
                    'slug' => Str::slug($vtName),
                    'provider_code' => 'vt-' . $item['operatorId'],
                    'valuetopup_operator_id' => $item['operatorId'],
                    'country_id' => $country?->id,
                    'logo_url' => $item['imageUrl'] ?? null,
                    'region_codes' => json_encode([]),
                    'payment_types' => json_encode(['valuetopup']),
                    'is_premium' => false,
                    'is_active' => true,
                ]);
            }

            $syncedProviders[] = [
                'provider_code'  => $operator->provider_code,
                'provider_id'    => $operator->id,
                'name'           => $operator->name,
                'logo_url'       => $operator->logo_url,
                'country_iso'    => $countryIso,
                'source'         => $operator->provider_code === 'vt-' . $item['operatorId'] ? 'valuetopup_only' : 'both',
                'valuetopup_id'  => $item['operatorId'],
            ];
        }

        return response()->json(['success' => true, 'providers' => $syncedProviders]);
    }

    /**
     * Try to find a DingConnect operator whose name contains or is contained
     * by the Valuetopup operator name.
     *
     * Example: "Vodafone" matches "Vodafone United Kingdom"
     *          "Lebara" matches "Lebara United Kingdom"
     */
    private function findMatchingDingOperator(string $vtName, $existingOperators): ?Operator
    {
        $vtLower = mb_strtolower($vtName);

        foreach ($existingOperators as $dingNameLower => $dingOperator) {
            if ($vtLower === $dingNameLower
                || str_contains($dingNameLower, $vtLower)
                || str_contains($vtLower, $dingNameLower)
            ) {
                return $dingOperator;
            }

            $dingFirstWord = explode(' ', $dingNameLower)[0];
            if ($vtLower === $dingFirstWord) {
                return $dingOperator;
            }
        }

        return null;
    }
    /**
     * Fetch Valuetopup products + SKUs for an operator and return them
     * in the same shape as getProducts so the frontend can merge them.
     * GET /recharge/valuetopup/products?country_iso=GB&operator_id=67
     */
    public function getValuetopupProducts(Request $request, ValuetopupService $vt): JsonResponse
    {
        $request->validate([
            'country_iso' => 'required|string|size:2',
            'operator_id' => 'required_without:valuetopup_id|integer',
            'valuetopup_id' => 'required_without:operator_id|integer',
        ]);

        $countryIso = $request->query('country_iso');

        // Resolve the VT operator ID either directly or via the operator DB id
        $valuetopupId = (int) $request->query('valuetopup_id');
        if (!$valuetopupId) {
            $operator = Operator::findOrFail((int) $request->query('operator_id'));
            $valuetopupId = (int) ($operator->valuetopup_operator_id ?? 0);
        }

        if (!$valuetopupId) {
            return response()->json(['success' => true, 'products' => []]);
        }

        $productsResult = $vt->products($valuetopupId);

        if (!($productsResult['responseCode'] ?? '') === '000' || empty($productsResult['payLoad'])) {
            return response()->json(['success' => true, 'products' => []]);
        }

        $products = [];
        foreach ($productsResult['payLoad'] as $product) {
            $productId = $product['productId'];

            $skusResult = $vt->skus($productId);
            if (!($skusResult['responseCode'] ?? '') !== '000' || empty($skusResult['payLoad'])) {
                continue;
            }

            foreach ($skusResult['payLoad'] as $sku) {
                $products[] = $vt->transformValuetopupProduct($sku);
            }
        }

        return response()->json(['success' => true, 'products' => $products]);
    }

    /**
     * Get products for selected operator.
     * Always fetches from API and merges product descriptions.
     * GET /api/V1/GetProducts?countryIsos=<>&providerCodes=<>&accountNumber=<>
     */

    /**
     * Estimate cost for a Valuetopup SKU.
     * POST /recharge/estimate-cost
     */
    public function estimateCost(Request $request, ValuetopupService $vt): JsonResponse
    {
        $request->validate([
            'sku_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $result = $vt->estimateCost(
            (int) $request->sku_id,
            (float) $request->amount,
            $request->currency ?? 'GBP'
        );

        if (!($result['responseCode'] ?? '') === '000' || empty($result['payLoad'])) {
            return response()->json(['success' => false, 'error' => $result['responseMessage'] ?? 'Could not estimate cost'], 400);
        }

        $payload = $result['payLoad'];

        return response()->json([
            'success' => true,
            'pricing' => [
                'send_value'      => (float) ($payload['invoiceAmount'] ?? 0),
                'send_currency'   => 'GBP',
                'receive_value'   => (float) ($payload['localCurrencyAmount'] ?? 0),
                'receive_currency' => $payload['destinationCurrency'] ?? 'GBP',
                'face_value'      => (float) ($payload['faceValue'] ?? 0),
                'face_value_currency' => $payload['faceValueCurrency'] ?? 'GBP',
                'sales_tax'       => (float) ($payload['salesTaxAmount'] ?? 0),
            ],
        ]);
    }

    public function getProducts(Request $request, DingConnectService $dingService): JsonResponse
    {
        $request->validate([
            'country_iso' => 'required|string|size:2',
            'provider_code' => 'required|string',
            'account_number' => 'nullable|string',
        ]);

        $result = $dingService->getProducts(
            $request->country_iso,
            $request->provider_code,
            $request->account_number
        );

        if (!$result['success']) {
            return response()->json(['success' => false, 'error' => $result['error']], 400);
        }

        $products = collect($result['data']['Items'] ?? [])->map(function ($item) {
            return $this->transformProduct($item);
        })->values();

        // Fetch product descriptions from DingConnect API for all returned SKUs
        $skuCodes = $products->pluck('sku_code')->filter()->unique()->values()->all();
        $descriptions = [];

        if (!empty($skuCodes)) {
            $descResult = $dingService->getProductDescriptions($skuCodes);
            if ($descResult['success'] && !empty($descResult['data'])) {
                foreach ($descResult['data'] as $descItem) {
                    $sku = $descItem['SkuCode'] ?? null;
                    if ($sku) {
                        $descriptions[$sku] = [
                            'description_markdown' => $descItem['DescriptionMarkdown'] ?? '',
                            'readmore_markdown'    => $descItem['ReadMoreMarkdown'] ?? '',
                        ];
                    }
                }
            }
        }

        // Merge descriptions into each product
        $products = $products->map(function ($product) use ($descriptions) {
            $sku = $product['sku_code'];
            if (isset($descriptions[$sku])) {
                $product['description_markdown'] = $descriptions[$sku]['description_markdown'];
                $product['readmore_markdown']    = $descriptions[$sku]['readmore_markdown'];
            }
            return $product;
        });

        return response()->json(['success' => true, 'products' => $products]);
    }

    /**
     * Get promotions
     * GET /api/V1/GetPromotions?countryIsos=<>&providerCodes=<>
     */
    public function getPromotions(Request $request, DingConnectService $dingService): JsonResponse
    {
        $request->validate([
            'country_isos' => 'nullable|string',
            'provider_codes' => 'nullable|string',
        ]);

        $result = $dingService->getPromotions($request->country_isos, $request->provider_codes);

        if (!$result['success']) {
            return response()->json(['success' => true, 'promotions' => []]);
        }

        $promotions = collect($result['data']['Promotions'] ?? $result['data']['Items'] ?? [])->map(function ($item) {
            return [
                'localization_key' => $item['LocalizationKey'] ?? '',
                'promotion_name' => $item['PromotionName'] ?? '',
                'display_text' => $item['DisplayText'] ?? '',
                'from_date' => $item['FromDate'] ?? '',
                'to_date' => $item['ToDate'] ?? '',
                'is_active' => true,
            ];
        })->values();

        return response()->json(['success' => true, 'promotions' => $promotions]);
    }

    /**
     * Estimate prices for free range flow (Flow B)
     */
    public function estimatePricing(Request $request, DingConnectService $dingService): JsonResponse
    {
        $request->validate([
            'sku_code' => 'required|string',
            'send_value' => 'required|numeric|min:1',
            'send_currency_iso' => 'nullable|string|size:3',
        ]);

        $result = $dingService->estimatePrices(
            $request->sku_code,
            (float) $request->send_value,
            $request->send_currency_iso
        );

        if (!$result['success']) {
            return response()->json(['success' => false, 'error' => $result['error']], 400);
        }

        $pricing = collect($result['data']['Estimates'] ?? [$result['data']])->map(function ($item) {
            return [
                'send_value' => (float) ($item['SendValue'] ?? 0),
                'send_currency' => $item['SendCurrencyIso'] ?? 'GBP',
                'receive_value' => (float) ($item['ReceiveValue'] ?? 0),
                'receive_currency' => $item['ReceiveCurrencyIso'] ?? 'GBP',
                'receive_value_excluding_tax' => (float) ($item['ReceiveValueExcludingTax'] ?? 0),
            ];
        })->first();

        return response()->json(['success' => true, 'pricing' => $pricing]);
    }

    /**
     * Get provider status for a selected provider.
     */
    public function getProviderStatus(Request $request, DingConnectService $dingService): JsonResponse
    {
        $request->validate([
            'provider_code' => 'required|string',
        ]);

        $result = $dingService->getProviderStatus($request->provider_code);

        if (!$result['success']) {
            return response()->json(['success' => false, 'error' => $result['error']], 400);
        }

        return response()->json(['success' => true, 'data' => $result['data']]);
    }

    /**
     * Validate number — mobile (with 44) or serial (anything else).
     * Phone: 12 digits starting with 44 → checks DB with/without 44, then AccountLookup.
     * Serial: not 12-digit 44 → exact DB match, no AccountLookup.
     */
    public function validateNumber(Request $request, DingConnectService $dingService)
    {
        $request->validate([
            'mobile_number' => ['required', 'string', 'min:3', 'max:30'],
            'provider_code' => ['required', 'string'],
        ]);

        $digits = preg_replace('/[^0-9]/', '', $request->mobile_number);

        $isPhone = strlen($digits) === 12 && str_starts_with($digits, '44');
        $bareNumber = substr($digits, 2);

        if ($isPhone) {
            // Match DB entry with 44 (449982414226) or without (9982414226)
            $allowed = AllowedNumber::where('active', true)
                ->where(function ($q) use ($digits, $bareNumber) {
                    $q->where('number', $digits)
                      ->orWhere('number', $bareNumber);
                })
                ->first();

            if (!$allowed) {
                return response()->json([
                    'success' => false,
                    'error' => 'This number is not registered for recharges on this portal.',
                ], 422);
            }

            // AccountLookup with full 44-prefixed number
            try {
                $result = $dingService->getAccountLookup($digits);
            } catch (\Throwable $e) {
                Log::error('AccountLookup failed for ' . $digits . ': ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'error' => 'Could not verify this number right now. Please try again.',
                ], 500);
            }

            $data = $result['data'] ?? $result;

            if (($data['ResultCode'] ?? 0) !== 1 || empty($data['Items'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'This number could not be verified. Please check it and try again.',
                ], 422);
            }

            $providers = collect($data['Items'])->pluck('ProviderCode');

            if (!$providers->contains($request->provider_code)) {
                return response()->json([
                    'success' => false,
                    'error' => 'This number does not belong to ' . $request->provider_code . '. Please check the number or change provider.',
                ], 422);
            }

            return response()->json(['success' => true, 'account_number' => $digits]);
        }

        // =========================================================
        // SERIAL NUMBER — exact match, no AccountLookup
        // =========================================================
        $allowed = AllowedNumber::where('active', true)
            ->where('number', $digits)
            ->first();

        if (!$allowed) {
            return response()->json([
                'success' => false,
                'error' => 'This number is not registered for recharges on this portal.',
            ], 422);
        }

        return response()->json(['success' => true, 'account_number' => $digits]);
    }

    /**
     * Initiate recharge (unified flow — Immediate + ReadReceipt)
     */
    public function initiate(Request $request, DingConnectService $dingService, ValuetopupService $vtService)
    {
        $isPin = $request->redemption_type === 'ReadReceipt';
        $gateway = $request->input('gateway', 'ding');

        $request->validate([
            'sku_code' => ['required', 'string'],
            'send_value' => ['required', 'numeric', 'min:1'],
            'send_currency' => ['nullable', 'string', 'size:3'],
            'receive_value' => ['nullable', 'numeric'],
            'receive_currency' => ['nullable', 'string', 'size:3'],
            'display_text' => ['nullable', 'string'],
            'validity_period' => ['nullable', 'string'],
            'description_markdown' => ['nullable', 'string'],
            'readmore_markdown' => ['nullable', 'string'],
            'benefits' => ['nullable', 'array'],
            'redemption_type' => ['required', 'string', 'in:Immediate,ReadReceipt,Manual'],
            'product_type' => ['nullable', 'string'],
            'region_code' => ['nullable', 'string'],
            'provider_code' => ['nullable', 'string'],
            'free_range' => ['nullable', 'boolean'],
            'receive_value_excluding_tax' => ['nullable', 'numeric'],
            'mobile_number' => $isPin ? ['nullable', 'string'] : ['required', 'string', 'min:7', 'max:20'],
            'country_id' => ['required', 'exists:countries,id'],
            'operator_id' => ['required', 'exists:operators,id'],
            'gateway' => ['nullable', 'string', 'in:ding,valuetopup'],
            'valuetopup_sku_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $walletService = app(WalletService::class);
        $wallet = $walletService->getWallet($user);

        $country = Country::findOrFail($request->country_id);
        $operator = $request->operator_id ? Operator::findOrFail($request->operator_id) : null;

        $retailerCharged = (float) $request->send_value;
        $availableBalance = $walletService->getAvailableBalance($wallet);

        if ($availableBalance < $retailerCharged) {
            return back()->with('error', 'Insufficient wallet balance. Please top up your wallet.');
        }

        $orderReference = 'ORD-' . strtoupper(Str::random(12));
        $receiptNumber = 'TXN-' . now()->format('Ymd') . '-' . str_pad(Transaction::whereDate('created_at', today())->count() + 1, 5, '0', STR_PAD_LEFT);

        // Determine account number for SendTransfer
        $accountNumber = $isPin ? '' : preg_replace('/[^0-9]/', '', $request->mobile_number);

        $settings = [];
        if ($request->region_code) {
            $settings['RegionCode'] = $request->region_code;
        }
        if ($request->provider_code && !$operator) {
            $settings['ProviderCode'] = $request->provider_code;
        }
        if ($request->redemption_type) {
            $settings['RedemptionMechanism'] = $request->redemption_type;
        }
        if ($gateway === 'valuetopup') {
            $settings['Gateway'] = 'valuetopup';
            if ($request->valuetopup_sku_id) {
                $settings['ValuetopupSkuId'] = (int) $request->valuetopup_sku_id;
            }
        }

        $transaction = DB::transaction(function () use ($user, $operator, $country, $request, $retailerCharged, $orderReference, $receiptNumber, $accountNumber, $settings, $isPin, $gateway) {
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'mobile_number' => $accountNumber ?: ($request->mobile_number ?? ''),
                'operator_id' => $operator?->id,
                'country_id' => $country->id,
                'amount' => $request->send_value,
                'currency' => $request->send_currency ?? 'GBP',
                'sku_code' => $request->sku_code,
                'send_value' => $request->send_value,
                'receive_value' => $request->receive_value ?? $request->send_value,
                'send_currency' => $request->send_currency ?? 'GBP',
                'receive_currency' => $request->receive_currency ?? 'GBP',
                'display_text' => $request->default_display_text ?? $request->display_text,
                'receipt_text' => null,
                'validity_period' => $request->validity_period,
                'benefits' => $request->benefits ?? [],
                'redemption_type' => $request->redemption_type ?? 'Immediate',
                'product_type' => $request->product_type ?? '',
                'region_code' => $request->region_code,
                'provider_code' => $request->provider_code,
                'free_range' => $request->boolean('free_range', false),
                'min_send_value' => null,
                'max_send_value' => null,
                'receive_value_excluding_tax' => $request->receive_value_excluding_tax,
                'description_markdown' => $request->description_markdown,
                'readmore_markdown' => $request->readmore_markdown,
                'ding_order_reference' => $orderReference,
                'receipt_number' => $receiptNumber,
                'gateway' => $gateway,
                'valuetopup_correlation_id' => $gateway === 'valuetopup' ? 'VT-' . strtoupper(Str::random(16)) : null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $walletService = app(WalletService::class);
            $wallet = $walletService->getWallet($user);
            $walletService->hold($user->id, $retailerCharged, $transaction->id, "Hold for recharge - {$accountNumber}");

            return $transaction;
        });

        ProcessRechargeJob::dispatch($transaction, $accountNumber, $settings);

        broadcast(new \App\Events\RechargeProcessing($transaction));

        return redirect()->route('retailer.transactions.show', $transaction->id)
            ->with('success', 'Recharge request submitted! Processing...');
    }

    /**
     * Transform a raw DingConnect product item into our format
     */
    private function transformProduct(array $item): array
    {
        $min = $item['Minimum'] ?? [];
        $max = $item['Maximum'] ?? [];

        $sendValue = (float) ($max['SendValue'] ?? $min['SendValue'] ?? 0);
        $receiveValue = (float) ($max['ReceiveValue'] ?? $min['ReceiveValue'] ?? 0);
        $sendCurrency = $max['SendCurrencyIso'] ?? $min['SendCurrencyIso'] ?? 'GBP';
        $receiveCurrency = $max['ReceiveCurrencyIso'] ?? $min['ReceiveCurrencyIso'] ?? 'GBP';

        $redemptionType = $item['RedemptionMechanism'] ?? 'Immediate';
        $productType = $item['ProductType'] ?? '';
        $benefits = $item['Benefits'] ?? [];

        return [
            'sku_code' => $item['SkuCode'],
            'provider_code' => $item['ProviderCode'] ?? '',
            'region_code' => $item['RegionCode'] ?? '',
            'display_text' => $item['DefaultDisplayText'] ?? '',
            'localization_key' => $item['LocalizationKey'] ?? '',
            'send_value' => $sendValue,
            'receive_value' => $receiveValue,
            'send_currency' => $sendCurrency,
            'receive_currency' => $receiveCurrency,
            'receive_value_excluding_tax' => (float) ($max['ReceiveValueExcludingTax'] ?? $max['ReceiveValue'] ?? 0),
            'commission_rate' => (float) ($item['CommissionRate'] ?? 0),
            'commission_applied' => (float) ($item['CommissionApplied'] ?? 0),
            'validity_period' => $item['ValidityPeriodIso'] ?? '',
            'benefits' => $benefits,
            'payment_types' => $item['PaymentTypes'] ?? [],
            'processing_mode' => $item['ProcessingMode'] ?? 'Instant',
            'redemption_type' => $redemptionType,
            'product_type' => $productType,
            'requires_receipt' => in_array($redemptionType, ['ReadReceipt', 'Manual']),
            'min_send_value' => (float) ($min['SendValue'] ?? $max['SendValue'] ?? 0),
            'max_send_value' => (float) ($max['SendValue'] ?? $min['SendValue'] ?? 0),
            'is_denomination' => $min['SendValue'] == $max['SendValue'],
            'description_markdown' => $item['DescriptionMarkdown'] ?? '',
            'readmore_markdown' => $item['ReadMoreMarkdown'] ?? '',
        ];
    }

    /**
     * Fetch product description from DingConnect (always from API).
     * GET /retailer/recharge/product-description?sku_code=xxx
     */
    public function productDescription(Request $request, DingConnectService $dingService): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'sku_code' => 'required|string',
        ]);

        $result = $dingService->getProductDescriptions([$request->sku_code]);

        if (!$result['success'] || empty($result['data'])) {
            return response()->json(['success' => false, 'description_markdown' => null, 'readmore_markdown' => null]);
        }

        $item = $result['data'][0] ?? [];
        $descriptionMarkdown = $item['DescriptionMarkdown'] ?? null;
        $readmoreMarkdown = $item['ReadMoreMarkdown'] ?? null;

        // Update any existing transaction with this SKU for caching
        Transaction::where('sku_code', $request->sku_code)->update([
            'description_markdown' => $descriptionMarkdown,
            'readmore_markdown'    => $readmoreMarkdown,
        ]);

        return response()->json([
            'success'              => true,
            'description_markdown' => $descriptionMarkdown,
            'readmore_markdown'    => $readmoreMarkdown,
            'source'               => 'api',
        ]);
    }

    /**
     * Detect country ISO from phone number
     */
    private function detectCountryFromPhone(string $phoneNumber): ?string
    {
        $countryCodes = [
            '44' => 'GB',
            '91' => 'IN',
            '1' => 'US',
            '971' => 'AE',
            '968' => 'OM',
            '974' => 'QA',
            '966' => 'SA',
            '965' => 'KW',
            '973' => 'BH',
        ];

        foreach ($countryCodes as $code => $iso) {
            if (str_starts_with($phoneNumber, $code)) {
                return $iso;
            }
        }

        return null;
    }
}
