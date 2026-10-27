<?php

namespace App\Console\Commands;

use App\Models\RetailerConnection;
use App\Models\Transaction;
use App\Services\CommissionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('retailer-connections:backfill {--apply : Write the connection records (default is a dry run)}')]
#[Description('Backfill retailer connection records from successful transactions')]
class BackfillRetailerConnections extends Command
{
    public function handle(CommissionService $commissions): int
    {
        $connections = [];
        $matchedTransactions = 0;
        $unmatchedTransactions = 0;
        $unmatchedSampleIds = [];

        Transaction::query()
            ->select([
                'id',
                'user_id',
                'mobile_number',
                'serial_number',
                'operator_id',
                'created_at',
                'status',
                'product_type',
                'redemption_type',
            ])
            ->where('status', 'success')
            ->whereHas('user', fn ($query) => $query->where('role', 'retailer'))
            ->where('product_type', '<>', 'pin')
            ->where(function ($query): void {
                $query->whereNull('redemption_type')
                    ->orWhere('redemption_type', '<>', 'ReadReceipt');
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->cursor()
            ->each(function (Transaction $transaction) use ($commissions, &$connections, &$matchedTransactions, &$unmatchedTransactions, &$unmatchedSampleIds): void {
                $allowedNumber = $commissions->allowedNumberForConnection($transaction);

                if (! $allowedNumber) {
                    $unmatchedTransactions++;
                    if (count($unmatchedSampleIds) < 20) {
                        $unmatchedSampleIds[] = $transaction->id;
                    }

                    return;
                }

                $matchedTransactions++;
                $key = $commissions->connectionAccountKey($allowedNumber);

                if (! isset($connections[$key])) {
                    $connections[$key] = [
                        'user_id' => $transaction->user_id,
                        'allowed_number_id' => $allowedNumber->id,
                        'account_number' => mb_substr((string) $transaction->mobile_number, 0, 100),
                        'operator_id' => $transaction->operator_id,
                        'first_transaction_id' => $transaction->id,
                        'topups_count' => 0,
                        'connected_at' => $transaction->created_at,
                    ];
                }

                $connections[$key]['topups_count']++;
            });

        $this->line("Matched successful transactions: {$matchedTransactions}");
        $this->line('Unique SIM connections: '.count($connections));
        $this->line("Unmatched successful transactions: {$unmatchedTransactions}");
        if ($unmatchedSampleIds) {
            $this->line('Sample unmatched transaction IDs (up to 20): '.implode(', ', $unmatchedSampleIds));
        }

        if (! $this->option('apply')) {
            $this->warn('Dry run only. No connection records were written. Re-run with --apply to persist these results.');

            return self::SUCCESS;
        }

        $created = 0;
        $updated = 0;

        foreach (array_chunk($connections, 250, true) as $batch) {
            DB::transaction(function () use ($batch, &$created, &$updated): void {
                foreach ($batch as $accountKey => $attributes) {
                    $connection = RetailerConnection::query()->firstOrNew(['account_key' => $accountKey]);
                    $connection->fill($attributes);
                    $connection->save();

                    if ($connection->wasRecentlyCreated) {
                        $created++;
                    } else {
                        $updated++;
                    }
                }
            });
        }

        $this->info("Created connections: {$created}");
        $this->info("Updated connections: {$updated}");

        return self::SUCCESS;
    }
}
