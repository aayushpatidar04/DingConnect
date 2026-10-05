<?php

namespace App\Jobs;

use App\Services\DingConnectService;
use App\Services\ValuetopupService;
use App\Models\Transaction;
use App\Events\RechargeSuccess;
use App\Events\RechargeFailed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ProcessRechargeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 90;
    public int $tries = 3;

    public function __construct(
        public Transaction $transaction,
        public ?string $accountNumber = null,
        public ?array $settings = null,
    ) {
    }

    public function handle(DingConnectService $dingService, ValuetopupService $vtService): void
    {
        if ($this->transaction->status !== 'pending') {
            return;
        }

        $this->transaction->update(['status' => 'processing']);
        broadcast(new \App\Events\RechargeProcessing($this->transaction));

        $gateway = $this->transaction->gateway ?? 'ding';

        if ($gateway === 'valuetopup') {
            $this->processValuetopup($vtService);
        } else {
            $this->processDing($dingService);
        }
    }

    // =========================================================================
    // DINGCONNECT PATH
    // =========================================================================

    protected function processDing(DingConnectService $dingService): void
    {
        $skuCode = $this->transaction->sku_code;
        $sendValue = (float) $this->transaction->send_value;
        $accountNumber = $this->accountNumber ?: preg_replace('/[^0-9]/', '', $this->transaction->mobile_number);
        $distributorRef = 'TXN-' . $this->transaction->id;
        $settings = $this->settings ?? [];

        if (!$skuCode) {
            $this->markFailed('Missing SKU code for transaction');
            return;
        }

        Log::info('ProcessRechargeJob: Sending transfer via DingConnect', [
            'sku_code' => $skuCode,
            'send_value' => $sendValue,
            'account_number' => $accountNumber,
        ]);

        $result = $dingService->sendTransfer(
            skuCode: $skuCode,
            sendValue: $sendValue,
            accountNumber: $accountNumber,
            distributorRef: $distributorRef,
            validateOnly: false,
            sendCurrencyIso: $this->transaction->send_currency,
            settings: $settings,
        );

        if ($result['success']) {
            $record = $result['data']['TransferRecord'] ?? $result['data'];
            $transferId = $record['TransferId'] ?? [];
            $transferRef = $transferId['TransferRef'] ?? null;
            $distributorRef = $transferId['DistributorRef'] ?? $record['DistributorRef'] ?? null;
            $processingState = $record['ProcessingState'] ?? '';
            $receiptText = $record['ReceiptText'] ?? null;

            $this->transaction->update([
                'ding_transaction_id' => $transferRef,
                'ding_order_reference' => $distributorRef,
                'ding_response' => $record,
                'receipt_text' => $receiptText,
            ]);

            if (in_array($processingState, ['Completed', 'Complete', 'Successful'])) {
                $this->markDingSuccess();
            } elseif (in_array($processingState, ['Failed', 'Failure'])) {
                $this->markFailed($record['ErrorCodes'][0] ?? $record['Message'] ?? 'Transfer failed');
            } else {
                $this->transaction->update(['status' => 'processing']);
            }
        } else {
            $this->markFailed($result['error']);
        }
    }

    protected function markDingSuccess(): void
    {
        $this->transaction->update(['status' => 'success']);

        if (empty($this->transaction->description_markdown) || empty($this->transaction->readmore_markdown)) {
            try {
                $dingService = app(\App\Services\DingConnectService::class);
                $descResult = $dingService->getProductDescriptions([$this->transaction->sku_code]);
                $item = $descResult['data']['Items'][0] ?? null;
                if ($item) {
                    $this->transaction->update([
                        'description_markdown' => $item['DescriptionMarkdown'] ?? $this->transaction->description_markdown,
                        'readmore_markdown'    => $item['ReadMoreMarkdown'] ?? $this->transaction->readmore_markdown,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Product description backfill failed for transaction ' . $this->transaction->id . ': ' . $e->getMessage());
            }
        }

        $walletService = app(\App\Services\WalletService::class);
        $walletService->releaseHold(
            $this->transaction->user_id,
            (float) $this->transaction->send_value,
            $this->transaction->id,
            'Recharge successful — converting hold to debit'
        );
        $walletService->debit(
            $this->transaction->user_id,
            (float) $this->transaction->send_value,
            $this->transaction->id,
            "Mobile recharge to {$this->transaction->mobile_number}"
        );

        broadcast(new RechargeSuccess($this->transaction->fresh()));
    }

    // =========================================================================
    // VALUETOPUP PATH
    // =========================================================================

    protected function processValuetopup(ValuetopupService $vt): void
    {
        $skuId = $this->settings['ValuetopupSkuId'] ?? null;
        $correlationId = $this->transaction->valuetopup_correlation_id;

        if (!$skuId || !$correlationId) {
            $this->markFailed('Missing Valuetopup SKU ID or correlation ID');
            return;
        }

        $category = $this->transaction->product_type ?? '';
        $isPin = $category === 'pin';
        $sendValue = (float) $this->transaction->send_value;
        $currency = $this->transaction->send_currency ?? 'GBP';

        Log::info('ProcessRechargeJob: Sending transfer via Valuetopup', [
            'sku_id' => $skuId,
            'correlation_id' => $correlationId,
            'send_value' => $sendValue,
            'category' => $category,
        ]);

        // Step 1: Execute the transaction
        if ($isPin) {
            $recipient = $this->transaction->mobile_number ?: 'customer';
            $result = $vt->pin($skuId, $recipient, $correlationId);
        } else {
            $mobile = preg_replace('/[^0-9]/', '', $this->transaction->mobile_number);
            $result = $vt->topup($skuId, $mobile, $sendValue, $correlationId, $currency);
        }

        // Check for in-progress (code 852) — poll status
        if ($vt->isValuetopupInProgress($result)) {
            $this->transaction->update([
                'status' => 'processing',
                'valuetopup_response' => $result,
            ]);
            $this->pollValuetopupStatus($vt, $correlationId);
            return;
        }

        // Final result
        $this->finalizeValuetopupTransaction($vt, $result);
    }

    protected function pollValuetopupStatus(ValuetopupService $vt, string $correlationId): void
    {
        // Poll up to 6 times with 20-second intervals (2 min total, matching their timeout)
        $maxAttempts = 6;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            sleep(20);

            $result = $vt->status($correlationId);

            if (!$vt->isValuetopupInProgress($result)) {
                // Got a definitive result
                $this->finalizeValuetopupTransaction($vt, $result);
                return;
            }

            Log::info("Valuetopup status poll attempt {$attempt} still in progress", [
                'transaction_id' => $this->transaction->id,
                'correlation_id' => $correlationId,
            ]);
        }

        // Still in progress after all polls — mark as processing, let cron handle it
        $this->transaction->update([
            'status' => 'processing',
            'failure_reason' => 'Valuetopup transaction is still processing. Please check back later.',
        ]);
    }

    protected function finalizeValuetopupTransaction(ValuetopupService $vt, array $result): void
    {
        $normalized = $vt->normalizeValuetopupResponse($result);

        if ($vt->isValuetopupSuccess($result)) {
            $this->transaction->update(array_merge($normalized, ['status' => 'success']));

            $walletService = app(\App\Services\WalletService::class);
            $walletService->releaseHold(
                $this->transaction->user_id,
                (float) $this->transaction->send_value,
                $this->transaction->id,
                'Recharge successful — converting hold to debit'
            );
            $walletService->debit(
                $this->transaction->user_id,
                (float) $this->transaction->send_value,
                $this->transaction->id,
                "Mobile recharge to {$this->transaction->mobile_number}"
            );

            broadcast(new RechargeSuccess($this->transaction->fresh()));
        } else {
            $errorMessage = $result['responseMessage'] ?? 'Valuetopup transaction failed';
            $this->markFailed($errorMessage);

            // Still save the response data
            $this->transaction->update($normalized);
        }
    }

    // =========================================================================
    // SHARED
    // =========================================================================

    protected function markFailed(string $reason): void
    {
        $this->transaction->update([
            'status' => 'failed',
            'failure_reason' => $reason,
        ]);

        $walletService = app(\App\Services\WalletService::class);
        $walletService->releaseHold(
            $this->transaction->user_id,
            (float) $this->transaction->send_value,
            $this->transaction->id,
            "Recharge failed: {$reason}"
        );

        broadcast(new RechargeFailed($this->transaction));
    }
}

