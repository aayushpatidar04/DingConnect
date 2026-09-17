<?php

namespace App\Jobs;

use App\Services\DingConnectService;
use App\Services\WalletService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessDingWebhook implements ShouldQueue
{
    use Dispatchable, Queueable;

    public array $payload;
    public int $timeout = 30;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function handle(DingConnectService $ding): void
    {
        try {
            $transactionId = $this->payload['TransactionId'] ?? null;
            if (!$transactionId) {
                Log::warning('Ding webhook missing TransactionId');
                return;
            }

            $transaction = \DB::table('transactions')
                ->where('ding_transaction_id', $transactionId)
                ->first();

            if (!$transaction) {
                Log::warning('Ding webhook for unknown transaction', ['ding_transaction_id' => $transactionId]);
                return;
            }

            $statusMap = [
                'SUCCESSFUL' => 'success',
                'FAILED'     => 'failed',
                'PENDING'    => 'processing',
                'CANCELLED'  => 'cancelled',
            ];

            $newStatus     = $statusMap[strtoupper($this->payload['Status'] ?? '')] ?? 'processing';
            $failureReason = $this->payload['FailureReason'] ?? null;

            \DB::table('transactions')->where('id', $transaction->id)->update([
                'status'                => $newStatus,
                'failure_reason'        => $failureReason,
                'callback_received'     => true,
                'callback_received_at'  => now(),
                'ding_response'         => json_encode($this->payload),
                'updated_at'            => now(),
            ]);

            if ($newStatus === 'success' && $transaction->status !== 'success') {
                \DB::table('transaction_commissions')->insert([
                    'transaction_id'   => $transaction->id,
                    'user_id'          => $transaction->user_id,
                    'operator_id'      => $transaction->operator_id,
                    'amount'           => $transaction->amount,
                    'commission_rate'  => (float) config('platform.commission_default', 2.5),
                    'commission_amount' => ($transaction->amount * (float) config('platform.commission_default', 2.5)) / 100,
                    'created_at'       => now(),
                ]);
            }

        } catch (\Throwable $e) {
            Log::error('ProcessDingWebhook error', ['error' => $e->getMessage(), 'payload' => $this->payload]);
        }
    }
}
