<?php

namespace Tests\Feature\Admin;

use App\Models\Country;
use App\Models\DingCallback;
use App\Models\Operator;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TransactionShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_transaction_details_and_callbacks(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'role' => 'admin',
            'is_active' => true,
        ]);
        $retailer = User::factory()->create([
            'name' => 'Retailer User',
            'role' => 'retailer',
            'is_active' => true,
        ]);
        $country = Country::create([
            'name' => 'United Kingdom',
            'iso_code' => 'GB',
        ]);
        $operator = Operator::create([
            'name' => 'Test Operator',
            'slug' => 'test-operator',
            'provider_code' => 'TEST',
            'country_id' => $country->id,
        ]);
        $transaction = Transaction::create([
            'user_id' => $retailer->id,
            'mobile_number' => '447700900123',
            'operator_id' => $operator->id,
            'country_id' => $country->id,
            'amount' => 10,
            'currency' => 'GBP',
            'status' => 'success',
            'receipt_number' => 'TEST-RECEIPT-001',
            'ding_transaction_id' => 'PROVIDER-001',
        ]);
        $transaction->forceFill(['admin_id' => $admin->id])->save();
        DingCallback::create([
            'transaction_id' => $transaction->id,
            'ding_transaction_id' => 'PROVIDER-001',
            'payload' => ['status' => 'success'],
            'status' => 'processed',
            'received_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.transactions.show', $transaction))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Transactions/Show')
                ->where('transaction.id', $transaction->id)
                ->where('transaction.user.name', 'Retailer User')
                ->where('transaction.admin.name', 'Admin User')
                ->where('transaction.operator.name', 'Test Operator')
                ->where('transaction.callbacks.0.status', 'processed')
            );
    }

    public function test_transaction_details_require_admin_access(): void
    {
        $retailer = User::factory()->create([
            'role' => 'retailer',
            'is_active' => true,
        ]);
        $country = Country::create([
            'name' => 'United Kingdom',
            'iso_code' => 'GB',
        ]);
        $operator = Operator::create([
            'name' => 'Test Operator',
            'slug' => 'test-operator',
            'provider_code' => 'TEST',
            'country_id' => $country->id,
        ]);
        $transaction = Transaction::create([
            'user_id' => $retailer->id,
            'mobile_number' => '447700900123',
            'operator_id' => $operator->id,
            'country_id' => $country->id,
            'amount' => 10,
            'currency' => 'GBP',
            'status' => 'success',
        ]);

        $this->actingAs($retailer)
            ->get(route('admin.transactions.show', $transaction))
            ->assertRedirect(route('login'));
    }
}
