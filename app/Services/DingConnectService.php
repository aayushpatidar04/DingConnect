<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class DingConnectService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $customerId;

    public function __construct()
    {
        $this->baseUrl      = rtrim(config('ding.api_url', 'https://api.dingconnect.com'), '/');
        $this->apiKey       = config('ding.api_key', '');
        $this->customerId   = config('ding.customer_id', '');
    }

    // =========================================================================
    // HTTP HELPERS
    // =========================================================================

    protected function get(string $endpoint, array $params = []): array
    {
        try {
            $url = $this->baseUrl . $endpoint;
            if (!empty($params)) {
                $url .= '?' . http_build_query($params);
            }

            Log::info('DingConnect GET', ['url' => $url]);

            $response = Http::timeout(30)
                ->withHeaders(['api_key' => $this->apiKey])
                ->get($url);

            Log::info('DingConnect GET response', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 500),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (($data['ResultCode'] ?? 0) == 1) {
                    return ['success' => true, 'data' => $data];
                }
                return ['success' => false, 'error' => $data['ErrorCodes'][0] ?? $data['Message'] ?? 'API error', 'raw' => $data];
            }

            return ['success' => false, 'error' => 'HTTP ' . $response->status(), 'body' => substr($response->body(), 0, 200)];

        } catch (Exception $e) {
            Log::error('DingConnect GET Error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function postForm(string $endpoint, array $formData = []): array
    {
        try {
            $url = $this->baseUrl . $endpoint;

            Log::info('DingConnect POST form', ['url' => $url, 'data' => $formData]);

            $response = Http::timeout(90)
                ->withHeaders(['api_key' => $this->apiKey])
                ->asForm()
                ->post($url, $formData);

            Log::info('DingConnect POST form response', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 500),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (($data['ResultCode'] ?? 0) == 1) {
                    return ['success' => true, 'data' => $data];
                }
                return ['success' => false, 'error' => $data['ErrorCodes'][0] ?? $data['Message'] ?? 'API error', 'raw' => $data];
            }

            return ['success' => false, 'error' => 'HTTP ' . $response->status(), 'body' => substr($response->body(), 0, 200)];

        } catch (Exception $e) {
            Log::error('DingConnect POST Error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function postJson(string $endpoint, array $jsonData = []): array
    {
        try {
            $url = $this->baseUrl . $endpoint;

            Log::info('DingConnect POST json', ['url' => $url, 'data' => $jsonData]);

            $response = Http::timeout(90)
                ->withHeaders(['api_key' => $this->apiKey])
                ->withHeader('Content-Type', 'application/json')
                ->post($url, $jsonData);

            Log::info('DingConnect POST json response', ['status' => $response->status(), 'body' => substr($response->body(), 0, 500)]);

            if ($response->successful()) {
                $data = $response->json();
                if (($data['ResultCode'] ?? 0) == 1) {
                    return ['success' => true, 'data' => $data];
                }
                return ['success' => false, 'error' => $data['ErrorCodes'][0] ?? $data['Message'] ?? 'API error', 'raw' => $data];
            }

            return ['success' => false, 'error' => 'HTTP ' . $response->status(), 'body' => substr($response->body(), 0, 200)];

        } catch (Exception $e) {
            Log::error('DingConnect POST JSON Error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // =========================================================================
    // PUBLIC API METHODS
    // =========================================================================

    public function getCountries(): array
    {
        return $this->get('/api/V1/GetCountries');
    }

    public function getProviders(?string $countryIso = null, ?string $providerCodes = null, ?string $accountNumber = null): array
    {
        $params = [];
        if ($countryIso)
            $params['countryIsos'] = $countryIso;
        if ($providerCodes)
            $params['providerCodes'] = $providerCodes;
        if ($accountNumber)
            $params['accountNumber'] = $accountNumber;
        return $this->get('/api/V1/GetProviders', $params);
    }

    /**
     * Get products filtered by country ISO and provider code.
     * Uses GET with query params: countryIsos, providerCodes
     * Optionally also passes accountNumber for phone-based filtering.
     */
    public function getProducts(string $countryIso, string $providerCode, ?string $accountNumber = null): array
    {
        $params = [
            'countryIsos'   => $countryIso,
            'providerCodes' => $providerCode,
        ];

        if ($accountNumber) {
            $params['accountNumber'] = $accountNumber;
        }

        return $this->get('/api/V1/GetProducts', $params);
    }

    public function getProviderStatus(?string $providerCodes = null): array
    {
        $params = [];
        if ($providerCodes)
            $params['providerCodes'] = $providerCodes;
        return $this->get('/api/V1/GetProviderStatus', $params);
    }

    public function getProductDescriptions(array $skuCodes, ?string $languageCode = 'en'): array
    {
        if (empty($skuCodes))
            return ['success' => true, 'data' => []];
        return $this->get('/api/V1/GetProductDescriptions', [
            'languageCodes' => $languageCode,
            'skuCodes' => implode(',', array_slice($skuCodes, 0, 50)),
        ]);
    }

    public function getBalance(): array
    {
        return $this->get('/api/V1/GetBalance');
    }

    public function getPromotions(?string $countryIsos = null, ?string $providerCodes = null): array
    {
        $params = [];
        if ($countryIsos)
            $params['countryIsos'] = $countryIsos;
        if ($providerCodes)
            $params['providerCodes'] = $providerCodes;
        return $this->get('/api/V1/GetPromotions', $params);
    }

    public function getAccountLookup(string $accountNumber): array
    {
        return $this->get('/api/V1/GetAccountLookup', [
            'accountNumber' => $accountNumber,
        ]);
    }

    public function estimatePrices(string $skuCode, float $sendValue, ?string $sendCurrencyIso = null, ?float $receiveValue = null): array
    {
        $data = [
            'SkuCode' => $skuCode,
            'SendValue' => $sendValue,
        ];
        if ($sendCurrencyIso)
            $data['SendCurrencyIso'] = $sendCurrencyIso;
        if ($receiveValue)
            $data['ReceiveValue'] = $receiveValue;
        return $this->postJson('/api/V1/EstimatePrices', $data);
    }

    /**
     * SendTransfer - Send a top-up / recharge
     * POST /api/V1/SendTransfer
     */
    public function sendTransfer(string $skuCode, float $sendValue, string $accountNumber, string $distributorRef, bool $validateOnly = false, ?string $sendCurrencyIso = null, ?array $settings = null, ?string $billRef = null): array
    {
        $formData = [
            'SkuCode'        => $skuCode,
            'SendValue'      => (float) $sendValue,
            'AccountNumber'  => $accountNumber,
            'DistributorRef' => $distributorRef,
            'ValidateOnly'   => $validateOnly ? 'true' : 'false',
        ];

        if ($sendCurrencyIso) {
            $formData['SendCurrencyIso'] = $sendCurrencyIso;
        }

        if ($settings && count($settings) > 0) {
            $formData['Settings'] = collect($settings)
                ->map(fn($v, $k) => ['Name' => $k, 'Value' => $v])
                ->values()
                ->all();
        }

        if ($billRef) {
            $formData['BillRef'] = $billRef;
        }

        return $this->postJson('/api/V1/SendTransfer', $formData);
    }

    /**
     * ListTransferRecords - Query transfer status
     * POST /api/V1/ListTransferRecords (JSON body)
     */
    public function listTransferRecords(?string $transferRef = null, ?string $distributorRef = null, ?string $accountNumber = null, int $take = 10, int $skip = 0): array
    {
        $payload = [
            'Take' => $take,
            'Skip' => $skip,
        ];
        if ($transferRef)
            $payload['TransferRef'] = $transferRef;
        if ($distributorRef)
            $payload['DistributorRef'] = $distributorRef;
        if ($accountNumber)
            $payload['AccountNumber'] = $accountNumber;

        return $this->postJson('/api/V1/ListTransferRecords', $payload);
    }

    /**
     * CancelTransfers - Cancel a transfer
     * POST /api/V1/CancelTransfers (JSON body)
     */
    public function cancelTransfers(string $transferRef, string $distributorRef): array
    {
        $payload = [
            'TransferId' => json_encode(['TransferRef' => $transferRef, 'DistributorRef' => $distributorRef]),
        ];
        return $this->postJson('/api/V1/CancelTransfers', $payload);
    }

    /**
     * Check transaction status (legacy - use listTransferRecords instead)
     * GET /api/V1/Transfer/{id}
     */
    public function checkStatus(string $dingTransactionId): array
    {
        return $this->get('/api/V1/Transfer/' . $dingTransactionId);
    }

    /**
     * Legacy sendTopUp - delegates to sendTransfer
     */
    public function sendTopUp(array $params): array
    {
        return $this->sendTransfer(
            skuCode: $params['countryIso'] . '-' . ($params['operatorCode'] ?? 'default'),
            sendValue: (float) $params['amount'],
            accountNumber: preg_replace('/[^0-9]/', '', $params['mobileNumber'] ?? ''),
            distributorRef: 'ORD-' . uniqid(),
            validateOnly: false,
        );
    }

    // =========================================================================
    // CALLBACK PROCESSING
    // =========================================================================

    public function processCallback(array $payload): Transaction
    {
        $dingTransactionId = $payload['TransferId']['TransferRef'] ?? null;

        if (!$dingTransactionId) {
            throw new \InvalidArgumentException('Missing TransferID in callback');
        }

        $transaction = Transaction::where('ding_transaction_id', $dingTransactionId)->first();

        if (!$transaction) {
            Log::warning("Ding callback for unknown transaction: {$dingTransactionId}");
            throw new \Exception("Transaction not found: {$dingTransactionId}");
        }

        $status = $payload['Status'] ?? 'Unknown';
        $statusMap = [
            'Successful' => 'success',
            'Success' => 'success',
            'Failed' => 'failed',
            'Failure' => 'failed',
            'Pending' => 'processing',
            'Cancelled' => 'cancelled',
        ];

        $newStatus = $statusMap[$status] ?? 'failed';

        $transaction->update([
            'status'               => $newStatus,
            'callback_received'    => true,
            'callback_received_at' => now(),
            'ding_response'        => $payload,
        ]);

        return $transaction->fresh();
    }
}
