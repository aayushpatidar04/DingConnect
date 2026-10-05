<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ValuetopupService
{
    protected string $baseUrl;
    protected string $username;
    protected string $password;
    protected string $countryCode = 'GB';

    public function __construct()
    {
        $this->baseUrl = rtrim(config('valuetopup.base_url'), '/');
        $this->username = config('valuetopup.username');
        $this->password = config('valuetopup.password');
    }

    protected function client()
    {
        return Http::withBasicAuth(
            $this->username,
            $this->password
        )
            ->acceptJson()
            ->timeout(120);
    }

    protected function get(string $uri, array $params = [])
    {
        return $this->client()
            ->get($this->baseUrl . $uri, $params)
            ->json();
    }

    protected function post(string $uri, array $payload)
    {
        return $this->client()
            ->post($this->baseUrl . $uri, $payload)
            ->json();
    }

    /*
    |--------------------------------------------------------------------------
    | Account
    |--------------------------------------------------------------------------
    */

    public function balance()
    {
        return $this->get('/api/v2/account/balance');
    }

    /*
    |--------------------------------------------------------------------------
    | Catalog
    |--------------------------------------------------------------------------
    */

    public function operators(?int $operatorId = null)
    {
        $params = [
            'countryCode' => $this->countryCode,
        ];

        if ($operatorId) {
            $params['operatorId'] = $operatorId;
        }

        $response = $this->get('/api/v2/catalog/operators', $params);

        $allowedOperators = [
            'Vodafone',
            'O2',
            'Lebara',
            'giffgaff',
            'Lyca Mobile',
            '3',
            'EE',
            'Voxi',
            'Smarty',
        ];

        $response['payLoad'] = collect($response['payLoad'] ?? [])
            ->filter(function ($operator) use ($allowedOperators) {
                return in_array(
                    strtolower($operator['operatorName']),
                    array_map('strtolower', $allowedOperators)
                );
            })
            ->values()
            ->toArray();

        return $response;
    }

    public function products(int $operatorId)
    {
        return $this->get('/api/v2/catalog/getproducts', [
            'operatorId' => $operatorId,
            'countryCode' => $this->countryCode,
        ]);
    }

    public function skus(int $productId, ?int $skuId = null)
    {
        $params = [
            'productId' => $productId,
        ];

        if ($skuId) {
            $params['skuId'] = $skuId;
        }

        return $this->get('/api/v2/catalog/skus', $params);
    }

    public function giftCardSkus(array $params = [])
    {
        $params['countryCode'] = $this->countryCode;

        return $this->get(
            '/api/v2/catalog/skus/giftcards',
            $params
        );
    }

    public function exchangeRate(int $skuId)
    {
        return $this->get(
            "/api/v2/catalog/sku/exchangeRate/{$skuId}"
        );
    }

    public function bundles(int $productId, string $mobile)
    {
        return $this->get(
            "/api/v2/catalog/bundles/{$productId}/{$mobile}"
        );
    }

    public function errorCodes()
    {
        return $this->get('/api/v2/catalog/errors');
    }

    /*
    |--------------------------------------------------------------------------
    | Lookup
    |--------------------------------------------------------------------------
    */

    public function lookupMobile(string $mobile)
    {
        return $this->get(
            "/api/v2/lookup/mobile/{$mobile}"
        );
    }

    public function fetchBillAccount(
        int $skuId,
        string $accountNumber
    ) {
        return $this->get(
            '/api/v2/lookup/billpay/fetch-account-detail',
            [
                'skuId' => $skuId,
                'accountNumber' => $accountNumber,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Promotions
    |--------------------------------------------------------------------------
    */

    public function currentPromotions()
    {
        return $this->get('/api/v2/promotion/current');
    }

    public function upcomingPromotions()
    {
        return $this->get('/api/v2/promotion/upcoming');
    }

    /*
    |--------------------------------------------------------------------------
    | Transactions
    |--------------------------------------------------------------------------
    */

    public function estimateCost(
        int $skuId,
        float $amount,
        string $currency = 'GBP'
    ) {
        return $this->post(
            '/api/v2/transaction/estimatecost',
            [
                'skuId' => $skuId,
                'amount' => $amount,
                'transactionCurrencyCode' => $currency,
            ]
        );
    }

    public function topup(
        int $skuId,
        string $mobile,
        float $amount,
        string $correlationId,
        string $currency = 'GBP'
    ) {
        return $this->post(
            '/api/v2/transaction/topup',
            [
                'skuId' => $skuId,
                'mobile' => $mobile,
                'amount' => $amount,
                'correlationId' => $correlationId,
                'transactionCurrencyCode' => $currency,
            ]
        );
    }

    public function pin(
        int $skuId,
        string $recipient,
        string $correlationId
    ) {
        return $this->post(
            '/api/v2/transaction/pin',
            [
                'skuId' => $skuId,
                'recipient' => $recipient,
                'correlationId' => $correlationId,
            ]
        );
    }

    public function billPay(
        int $skuId,
        string $accountNumber,
        float $amount,
        string $correlationId,
        string $currency = 'GBP'
    ) {
        return $this->post(
            '/api/v2/transaction/billpay',
            [
                'skuId' => $skuId,
                'accountNumber' => $accountNumber,
                'amount' => $amount,
                'correlationId' => $correlationId,
                'transactionCurrencyCode' => $currency,
            ]
        );
    }

    public function giftCard(
        int $skuId,
        float $amount,
        string $recipient,
        string $correlationId,
        string $currency = 'GBP'
    ) {
        return $this->post(
            '/api/v2/transaction/giftcard/order',
            [
                'skuId' => $skuId,
                'amount' => $amount,
                'recipient' => $recipient,
                'correlationId' => $correlationId,
                'transactionCurrencyCode' => $currency,
            ]
        );
    }

    public function giftCardFetch(int $id)
    {
        return $this->get(
            "/api/v2/transaction/giftcard/fetch/{$id}"
        );
    }

    public function status(string $correlationId)
    {
        return $this->get(
            "/api/v2/transaction/status/{$correlationId}"
        );
    }

    /*
    |--------------------------------------------------------------------------
    | eSIM
    |--------------------------------------------------------------------------
    */

    public function esimOrder(
        int $skuId,
        string $correlationId
    ) {
        return $this->post(
            '/api/v2/esim/order',
            [
                'skuId' => $skuId,
                'correlationId' => $correlationId,
            ]
        );
    }

    public function esimStatus(string $iccid)
    {
        return $this->get(
            "/api/v2/esim/status/{$iccid}"
        );
    }

    // =========================================================================
    // DATA TRANSFORMERS
    // =========================================================================

    /**
     * Convert a Valuetopup SKU payload into the same normalized format
     * used by DingConnect's transformProduct(), so the frontend and
     * transaction flow do not need to change.
     *
     * @param  array  $sku  Raw SKU object from /catalog/skus or /catalog/getproducts
     * @return array
     */
    public function transformValuetopupProduct(array $sku): array
    {
        $min = $sku['min'] ?? [];
        $max = $sku['max'] ?? [];

        $sendValue = (float) ($max['cost'] ?? $min['cost'] ?? 0);
        $receiveValue = (float) ($max['faceValue'] ?? $min['faceValue'] ?? 0);
        $sendCurrency = $min['costCurrency'] ?? 'GBP';
        $receiveCurrency = $max['faceValueCurrency'] ?? $min['faceValueCurrency'] ?? 'GBP';

        $category = strtolower($sku['category'] ?? 'rtr');
        $isPin = $category === 'pin';
        $isBundle = $category === 'bundle';
        $isData = $category === 'data';
        $isTopup = $category === 'rtr' || $category === 'topup';

        // Build benefits array matching DingConnect's format so the shared
        // frontend categorizer (TopUp/Data/Bundle/PIN/LDI/Voucher/DTH)
        // classifies VT products correctly.
        $benefits = [];
        if ($isBundle) {
            $benefits = ['Mobile', 'Minutes', 'Data'];
        } elseif ($isPin) {
            $benefits = ['Mobile', 'Minutes', 'Data'];
        } elseif ($isData) {
            $benefits = ['Mobile', 'Data'];
        } else {
            $benefits = ['Mobile', 'Minutes'];
        }

        // Parse benefitType string if available (format: "Mobile+Minutes+Data")
        if (!empty($sku['benefitType']) && is_string($sku['benefitType'])) {
            $parsed = array_map('ucfirst', explode('+', $sku['benefitType']));
            if (!empty($parsed)) {
                $benefits = $parsed;
            }
        }

        return [
            'sku_code' => 'vt-' . ($sku['skuId'] ?? ''),
            'provider_code' => 'vt-' . ($sku['operatorId'] ?? ''),
            'region_code' => $sku['region'] ?? '',
            'display_text' => $sku['skuName'] ?? $sku['productName'] ?? '',
            'localization_key' => '',
            'send_value' => $sendValue,
            'receive_value' => $receiveValue,
            'send_currency' => $sendCurrency,
            'receive_currency' => $receiveCurrency,
            'receive_value_excluding_tax' => $receiveValue,
            'commission_rate' => 0,
            'commission_applied' => 0,
            'validity_period' => $sku['validity'] ?? '',
            'benefits' => $benefits,
            'payment_types' => [],
            'processing_mode' => 'Instant',
            'redemption_type' => $isPin ? 'ReadReceipt' : 'Immediate',
            'product_type' => $category,
            'requires_receipt' => $isPin,
            'min_send_value' => $sendValue,
            'max_send_value' => $sendValue,
            'is_denomination' => true,
            'description_markdown' => $sku['productDescription'] ?? '',
            'readmore_markdown' => $sku['additionalInformation'] ?? '',
            '_valuetopup' => [
                'skuId' => $sku['skuId'] ?? null,
                'productId' => $sku['productId'] ?? null,
                'operatorId' => $sku['operatorId'] ?? null,
                'productName' => $sku['productName'] ?? '',
                'category' => $category,
            ],
        ];
    }

    /**
     * Normalize a Valuetopup transaction response into a flat array
     * for saving to the transaction record.
     *
     * @param  array  $response
     * @return array
     */
    public function normalizeValuetopupResponse(array $response): array
    {
        $payload = $response['payLoad'] ?? [];

        $pinNumbers = [];
        if (!empty($payload['pins']) && is_array($payload['pins'])) {
            foreach ($payload['pins'] as $pin) {
                $pinNumbers[] = [
                    'pinNumber' => $pin['pinNumber'] ?? '',
                    'controlNumber' => $pin['controlNumber'] ?? '',
                    'deliveredAmount' => $pin['deliveredAmount'] ?? 0,
                    'deliveredCurrencyCode' => $pin['deliveredCurrencyCode'] ?? '',
                    'expirationDate' => $pin['expirationDate'] ?? null,
                ];
            }
        }

        $receiptText = null;
        if (!empty($pinNumbers)) {
            $lines = [];
            foreach ($pinNumbers as $pin) {
                $lines[] = "PIN: {$pin['pinNumber']}";
                if ($pin['controlNumber']) {
                    $lines[] = "Control: {$pin['controlNumber']}";
                }
            }
            $receiptText = implode("\n", $lines);
        }

        return [
            'valuetopup_transaction_id' => $payload['transactionId'] ?? null,
            'valuetopup_response' => $response,
            'receipt_text' => $receiptText,
            'send_value' => (float) ($payload['invoiceAmount'] ?? 0),
            'ding_transaction_id' => $payload['topupDetail']['operatorTransactionId'] ?? null,
        ];
    }

    public function isValuetopupSuccess(array $response): bool
    {
        return ($response['responseCode'] ?? '') === '000';
    }

    public function isValuetopupInProgress(array $response): bool
    {
        return ($response['responseCode'] ?? '') === '852';
    }
}