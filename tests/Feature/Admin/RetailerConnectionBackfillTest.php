<?php

namespace Tests\Feature\Admin;

use App\Models\AllowedNumber;
use App\Models\Country;
use App\Models\Operator;
use App\Models\RetailerCommission;
use App\Models\RetailerConnection;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletTopup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetailerConnectionBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_defaults_to_a_dry_run(): void
    {
        [$retailer, $operator] = $this->createRetailerAndOperator();
        $this->allowNumber($retailer, '447700900111');
        $this->createTransaction($retailer, $operator, '447700900111');

        $this->artisan('retailer-connections:backfill')
            ->expectsOutputToContain('Unique SIM connections: 1')
            ->expectsOutputToContain('Dry run only')
            ->assertExitCode(0);

        $this->assertSame(0, RetailerConnection::query()->count());
    }

    public function test_apply_backfills_matched_transactions_idempotently_without_commission_payouts(): void
    {
        [$firstRetailer, $operator] = $this->createRetailerAndOperator();
        $secondRetailer = User::factory()->create(['role' => 'retailer']);
        $this->allowNumber($firstRetailer, '447700900111');

        $firstTransaction = $this->createTransaction($firstRetailer, $operator, '07700900111', '2025-01-01 10:00:00');
        $this->createTransaction($secondRetailer, $operator, '447700900111', '2025-01-02 10:00:00');
        $this->createTransaction($firstRetailer, $operator, '447700900111', '2025-01-03 10:00:00', 'failed');
        $this->createTransaction($firstRetailer, $operator, '447700900111', '2025-01-04 10:00:00', 'success', 'pin');
        $this->createTransaction($firstRetailer, $operator, '447700900222', '2025-01-05 10:00:00');
        $this->allowNumber($firstRetailer, '447700900333', false);
        $this->createTransaction($firstRetailer, $operator, '447700900333', '2025-01-06 10:00:00');

        $this->artisan('retailer-connections:backfill', ['--apply' => true])
            ->expectsOutputToContain('Matched successful transactions: 2')
            ->expectsOutputToContain('Unique SIM connections: 1')
            ->expectsOutputToContain('Unmatched successful transactions: 2')
            ->expectsOutputToContain('Created connections: 1')
            ->assertExitCode(0);

        $connection = RetailerConnection::query()->sole();
        $this->assertSame($firstRetailer->id, $connection->user_id);
        $this->assertSame($firstTransaction->id, $connection->first_transaction_id);
        $this->assertSame(2, $connection->topups_count);
        $this->assertSame('2025-01-01 10:00:00', $connection->connected_at->format('Y-m-d H:i:s'));
        $this->assertSame(0, RetailerCommission::query()->count());
        $this->assertSame(0, WalletTopup::query()->count());

        $this->artisan('retailer-connections:backfill', ['--apply' => true])
            ->expectsOutputToContain('Updated connections: 1')
            ->assertExitCode(0);

        $this->assertSame(1, RetailerConnection::query()->count());
        $this->assertSame(2, RetailerConnection::query()->sole()->topups_count);
    }

    private function createRetailerAndOperator(): array
    {
        $retailer = User::factory()->create(['role' => 'retailer']);
        $country = Country::query()->create([
            'name' => 'United Kingdom',
            'iso_code' => 'GB',
            'iso_code_3' => 'GBR',
            'calling_code' => '44',
            'currency' => 'GBP',
            'is_active' => true,
        ]);
        $operator = Operator::query()->create([
            'name' => 'Test Mobile',
            'slug' => 'test-mobile',
            'provider_code' => 'TM',
            'country_id' => $country->id,
            'is_active' => true,
        ]);

        return [$retailer, $operator];
    }

    private function allowNumber(User $retailer, string $mobile, bool $active = true): AllowedNumber
    {
        return AllowedNumber::query()->create([
            'user_id' => $retailer->id,
            'mobile' => $mobile,
            'serial' => '0',
            'active' => $active,
        ]);
    }

    private function createTransaction(
        User $retailer,
        Operator $operator,
        string $mobile,
        string $createdAt = '2025-01-01 10:00:00',
        string $status = 'success',
        string $productType = 'mobile_topup',
    ): Transaction {
        $transaction = Transaction::query()->create([
            'user_id' => $retailer->id,
            'mobile_number' => $mobile,
            'operator_id' => $operator->id,
            'country_id' => $operator->country_id,
            'amount' => 10,
            'currency' => 'GBP',
            'status' => $status,
            'product_type' => $productType,
        ]);

        $transaction->forceFill(['created_at' => $createdAt])->save();

        return $transaction;
    }
}
