<?php

namespace App\Jobs;

use App\Services\DingConnectService;
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

    public function handle(DingConnectService $dingService): void
    {
        if ($this->transaction->status !== 'pending') {
            return;
        }

        $this->transaction->update(['status' => 'processing']);

        broadcast(new \App\Events\RechargeProcessing($this->transaction));

        $skuCode = $this->transaction->sku_code;
        $sendValue = (float) $this->transaction->send_value;
        $accountNumber = $this->accountNumber ?: preg_replace('/[^0-9]/', '', $this->transaction->mobile_number);
        $distributorRef = 'TXN-' . $this->transaction->id;
        $settings = $this->settings ?? [];

        if (!$skuCode) {
            $this->markFailed('Missing SKU code for transaction');
            return;
        }

        Log::info('ProcessRechargeJob: Sending transfer', [
            'sku_code' => $skuCode,
            'send_value' => $sendValue,
            'account_number' => $accountNumber,
        ]);

        // Send to DingConnect using the correct SendTransfer API
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
            // DingConnect wraps the actual record inside TransferRecord key
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

            // Check if it was instant or batch
            if (in_array($processingState, ['Completed', 'Complete', 'Successful'])) {
                // Instant success — convert hold into permanent debit
                $this->transaction->update(['status' => 'success']);

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

                broadcast(new RechargeSuccess($this->transaction));
            } elseif (in_array($processingState, ['Failed', 'Failure'])) {
                // Instant failure — release the hold
                $this->markFailed($record['ErrorCodes'][0] ?? $record['Message'] ?? 'Transfer failed');
            } else {
                // Batch or still processing — status remains 'processing'
                $this->transaction->update(['status' => 'processing']);
            }
        } else {
            $this->markFailed($result['error']);
        }
    }

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
