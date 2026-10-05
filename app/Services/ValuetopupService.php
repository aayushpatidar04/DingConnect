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

        return $this->get('/api/v2/catalog/operators', $params);
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
}