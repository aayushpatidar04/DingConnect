<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DingConnectService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $customerId;
    protected string $defaultAgent;

    public function __construct()
    {
        $this->baseUrl      = config('ding.api_url', 'https://api.dingconnect.com');
        $this->apiKey       = config('ding.api_key', '');
        $this->customerId   = config('ding.customer_id', '');
        $this->defaultAgent = config('ding.default_agent', '');
    }

    protected function httpClient()
    {
        $timeout = config('ding.timeout', 30);
        $retries = config('ding.retry_attempts', 2);
        $retryDelay = config('ding.retry_delay', 10);

        return Http::withHeaders($this->headers())
            ->timeout($timeout)
            ->retry($retries, $retryDelay);
    }

    // ========================================================================
    // GENERIC HELPERS
    // =========================================================================

    protected function headers(): array
    {
        return [
            'api_key' => $this->apiKey,
        ];
    }

    public function get(string $endpoint, array $params = []): array
    {
        try {
            $response = $this->httpClient()->get($this->baseUrl . $endpoint, $params);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            Log::error('DingConnect GET failed', ['endpoint' => $endpoint, 'response' => $response->body()]);
            return ['success' => false, 'error' => $response->json('Message', 'Request failed')];
        } catch (\Throwable $e) {
            Log::error('DingConnect GET error', ['endpoint' => $endpoint, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * POST with JSON body (for newer V1 endpoints like SendTransfer, EstimatePrices)
     */
    public function post(string $endpoint, array $payload): array
    {
        try {
            $response = $this->httpClient()
                ->withHeader('Content-Type', 'application/json')
                ->post($this->baseUrl . $endpoint, $payload);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            Log::error('DingConnect POST JSON failed', ['endpoint' => $endpoint, 'response' => $response->body()]);
            return ['success' => false, 'error' => $response->json('Message', 'Request failed')];
        } catch (\Throwable $e) {
            Log::error('DingConnect POST JSON error', ['endpoint' => $endpoint, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * POST with form-encoded body (for legacy v1.0 endpoints like SendTransaction, GetProducts with CountryCode)
     */
    public function postForm(string $endpoint, array $formData): array
    {
        try {
            $response = $this->httpClient()
                ->asForm()
                ->post($this->baseUrl . $endpoint, $formData);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            Log::error('DingConnect POST FORM failed', ['endpoint' => $endpoint, 'response' => $response->body()]);
            return ['success' => false, 'error' => $response->json('Message', 'Request failed')];
        } catch (\Throwable $e) {
            Log::error('DingConnect POST FORM error', ['endpoint' => $endpoint, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // =========================================================================
    // PROVIDER OPERATIONS
    // =========================================================================

    /**
     * Get providers (operators) for a phone number
     */
    public function getProviders(string $accountNumber, ?string $countryIso = null): array
    {
        $params = [
            'accountNumber' => $accountNumber,
        ];

        if ($countryIso) {
            $params['countryIso'] = $countryIso;
        }

        return $this->get('/api/V1/GetProviders', $params);
    }

    /**
     * Get regions for a specific provider
     */
    public function getRegions(string $providerCode): array
    {
        return $this->get('/api/V1/GetRegions', [
            'providerCode' => $providerCode,
        ]);
    }

    /**
     * Check if a provider is processing transfers (is live)
     */
    public function getProviderStatus(string $providerCode): array
    {
        return $this->get('/api/V1/GetProviderStatus', [
            'providerCode' => $providerCode,
        ]);
    }

    // =========================================================================
    // PRODUCT OPERATIONS
    // =========================================================================

    /**
     * Get all products (legacy v1.0 form-encoded endpoint)
     * @param string $countryIso Country ISO code (e.g. 'GB', 'IN'). Pass '0' to get all countries.
     */
    public function getProducts(string $countryIso = '0', bool $includeProducts = true, bool $includePaymentOptions = true): array
    {
        return $this->postForm('/api/v1.0/Products/GetProducts', [
            'CountryCode'            => $countryIso,
            'IncludeProducts'        => $includeProducts ? 'true' : 'false',
            'IncludePaymentOptions'  => $includePaymentOptions ? 'true' : 'false',
        ]);
    }

    /**
     * Get products filtered by account number, country, provider, and region.
     * Uses V1 JSON endpoint which is required for the new flow with categorization.
     */
    public function getProductsByAccountNumber(
        string $accountNumber,
        ?string $countryIso = null,
        ?string $providerCode = null,
        ?string $regionCode = null
    ): array {
        $payload = [
            'accountNumber' => $accountNumber,
        ];

        if ($countryIso) {
            $payload['countryIso'] = $countryIso;
        }
        if ($providerCode) {
            $payload['providerCode'] = $providerCode;
        }
        if ($regionCode) {
            $payload['regionCode'] = $regionCode;
        }

        return $this->post('/api/V1/GetProducts', $payload);
    }

    /**
     * Get promotions for specific countries/providers
     */
    public function getPromotions(?array $params = []): array
    {
        return $this->get('/api/V1/GetPromotions', $params);
    }

    // =========================================================================
    // PRICING
    // =========================================================================

    /**
     * Estimate prices for free-range flow (Flow B)
     */
    public function estimatePrices(string $skuCode, float $sendValue, ?string $sendCurrencyIso = 'GBP'): array
    {
        return $this->post('/api/V1/EstimatePrices', [
            'skuCode'          => $skuCode,
            'sendValue'        => (string) $sendValue,
            'sendCurrencyIso'  => $sendCurrencyIso,
        ]);
    }

    // =========================================================================
    // TRANSFER / RECHARGE
    // =========================================================================

    /**
     * Send a transfer (recharge) using the free-range SKU flow
     * This is the primary method used by ProcessRechargeJob
     */
    public function sendTransfer(
        string $skuCode,
        float $sendValue,
        string $accountNumber,
        string $distributorRef,
        bool $validateOnly = false,
        ?string $sendCurrencyIso = 'GBP',
        ?array $settings = [],
        ?string $senderNumber = null,
        ?string $description = null,
    ): array {
        $payload = [
            'skuCode'         => $skuCode,
            'sendValue'       => (string) $sendValue,
            'accountNumber'   => $accountNumber,
            'distributorRef'  => $distributorRef,
            'validateOnly'    => $validateOnly ? 'true' : 'false',
            'sendCurrencyIso' => $sendCurrencyIso,
            'settings'        => $settings,
        ];

        if ($senderNumber) {
            $payload['senderNumber'] = $senderNumber;
        }
        if ($description) {
            $payload['description'] = $description;
        }

        return $this->post('/api/V1/SendTransfer', $payload);
    }

    /**
     * Legacy: Send a top-up using ProductCode/CountryCode flow (form-encoded body)
     */
    public function sendTopUp(array $params): array
    {
        $payload = [
            'ProductCode'         => $params['operatorCode'],
            'CountryCode'         => $params['countryIso'],
            'RecipientNumber'     => $params['mobileNumber'],
            'SendValue'           => (string) (float) $params['amount'],
            'SenderNumber'        => $this->defaultAgent,
            'SendCurrency'        => 'GBP',
            'InvoiceReference'    => $params['invoiceReference'] ?? 'TXN-' . time() . '-' . random_int(1000, 9999),
            'RetailerCustomerRef' => $params['retailerRef'] ?? (string) now()->timestamp,
        ];

        return $this->postForm('/api/v1.0/Transactions/SendTransaction', $payload);
    }

    // =========================================================================
    // ACCOUNT
    // =========================================================================

    /**
     * Get DingConnect account details (balance, etc.)
     */
    public function getBalance(): array
    {
        return $this->get('/api/v1.0/Accounts/GetAccount');
    }

    // =========================================================================
    // WEBHOOK PROCESSING
    // =========================================================================

    /**
     * Process a webhook payload from DingConnect.
     */
    public function processWebhook(array $payload): void
    {
        $transactionId = $payload['TransactionId'] ?? null;
        if (!$transactionId) {
            return;
        }

        $transaction = Transaction::where('ding_transaction_id', $transactionId)->first();
        if (!$transaction) {
            Log::warning('Webhook for unknown transaction', ['transaction_id' => $transactionId]);
            return;
        }

        $previousStatus = $transaction->status;

        $statusMap = [
            'SUCCESSFUL'  => 'success',
            'FAILED'      => 'failed',
            'PENDING'     => 'processing',
            'CANCELLED'   => 'cancelled',
            'PROCESSING'  => 'processing',
        ];

        $newStatus = $statusMap[strtoupper($payload['Status'] ?? '')] ?? $transaction->status;
        $failureReason = $payload['FailureReason'] ?? null;

        $transaction->update([
            'status'               => $newStatus,
            'failure_reason'       => $failureReason,
            'callback_received'    => true,
            'callback_received_at' => now(),
            'ding_response'        => json_encode($payload),
        ]);

        // Record commission if transaction just became successful
        if ($newStatus === 'success' && $previousStatus !== 'success') {
            $this->recordCommission($transaction);
        }
    }

    /**
     * Process a callback response from DingConnect (same as webhook but for internal use)
     */
    public function processCallback(array $record): void
    {
        $status = strtoupper($record['ProcessingState'] ?? $record['Status'] ?? '');

        $statusMap = [
            'COMPLETED'   => 'success',
            'SUCCESSFUL'  => 'success',
            'FAILED'      => 'failed',
            'FAILURE'     => 'failed',
            'PENDING'     => 'processing',
            'CANCELLED'   => 'cancelled',
            'PROCESSING'  => 'processing',
        ];

        $newStatus = $statusMap[$status] ?? 'processing';

        if (!empty($record['TransferId']['TransferRef'])) {
            $transaction = Transaction::where('ding_transaction_id', $record['TransferId']['TransferRef'])->first();
            if ($transaction) {
                $previousStatus = $transaction->status;
                $transaction->update([
                    'status'            => $newStatus,
                    'ding_response'     => json_encode($record),
                    'failure_reason'    => $record['ErrorCodes'][0] ?? $record['Message'] ?? null,
                ]);

                if ($newStatus === 'success' && $previousStatus !== 'success') {
                    $this->recordCommission($transaction);
                }
            }
        }
    }

    protected function recordCommission(Transaction $transaction): void
    {
        // Try DB-backed setting first
        $commissionRate = 2.5;
        try {
            $setting = \App\Models\Setting::where('key', 'platform_commission_default')->first();
            if ($setting && $setting->value !== null) {
                $commissionRate = is_numeric($setting->value) ? (float) $setting->value : (float) (is_array($setting->value) ? ($setting->value['value'] ?? 2.5) : 2.5);
            }
        } catch (\Throwable $e) {
            // table may not exist yet
        }

        $commissionAmount = ($transaction->amount * $commissionRate) / 100;

        if ($commissionAmount > 0) {
            try {
                DB::table('transaction_commissions')->updateOrInsert(
                    ['transaction_id' => $transaction->id],
                    [
                        'user_id'           => $transaction->user_id,
                        'operator_id'       => $transaction->operator_id,
                        'amount'            => $transaction->amount,
                        'commission_rate'   => $commissionRate,
                        'commission_amount' => $commissionAmount,
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ]
                );
            } catch (\Throwable $e) {
                // table may not exist yet — skip silently
            }
        }
    }
}
