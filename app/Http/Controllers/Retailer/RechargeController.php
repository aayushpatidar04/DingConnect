<?php

namespace App\Http\Controllers\Retailer;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessRechargeJob;
use App\Models\Country;
use App\Models\Operator;
use App\Models\Transaction;
use App\Models\AllowedNumber;
use App\Services\DingConnectService;
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
     * Get products for selected operator.
     * GET /api/V1/GetProducts?countryIsos=<>&providerCodes=<>&accountNumber=<>
     */
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

    public function validateNumber(Request $request, DingConnectService $dingService)
    {
        $request->validate([
            'mobile_number' => ['required', 'string', 'min:7', 'max:20'],
            'provider_code' => ['required', 'string'],
        ]);

        $accountNumber = preg_replace('/[^0-9]/', '', $request->mobile_number);

        // 1. Allow-list gate: number MUST exist in AllowedNumber
        if (!AllowedNumber::where('number', $accountNumber)->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'This number is not registered for recharges on this portal.',
            ], 422);
        }

        // 2. DingConnect AccountLookup — verify number ↔ provider
        try {
            $result = $dingService->getAccountLookup($accountNumber);
        } catch (\Throwable $e) {
            \Log::error('AccountLookup failed for ' . $accountNumber . ': ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Could not verify this number right now. Please try again.',
            ], 500);
        }

        $data = $result['data'] ?? $result; // handle wrapper variations

        if (($data['ResultCode'] ?? 0) !== 1 || empty($data['Items'])) {
            return response()->json([
                'success' => false,
                'error' => 'This number could not be verified. Please check it and try again.',
            ], 422);
        }

        // Items can contain MULTIPLE providers — selected one must be among them
        $providers = collect($data['Items'])->pluck('ProviderCode');

        if (!$providers->contains($request->provider_code)) {
            return response()->json([
                'success' => false,
                'error' => 'This number does not belong to ' . $request->provider_code . '. Please check the number or change provider.',
            ], 422);
        }

        return response()->json(['success' => true, 'account_number' => $accountNumber]);
    }

    /**
     * Initiate recharge (unified flow — Immediate + ReadReceipt)
     */
    public function initiate(Request $request, DingConnectService $dingService)
    {
        $isPin = $request->redemption_type === 'ReadReceipt';

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

        // Build settings array for SendTransfer
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

        $transaction = DB::transaction(function () use ($user, $operator, $country, $request, $retailerCharged, $orderReference, $receiptNumber, $accountNumber, $settings, $isPin) {
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
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $walletService = app(WalletService::class);
            $wallet = $walletService->getWallet($user);
            $walletService->hold($user->id, $retailerCharged, $transaction->id, "Hold for recharge - {$accountNumber}");

            return $transaction;
        });

        // Dispatch job to process with DingConnect
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

        // Always fetch from DingConnect
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
