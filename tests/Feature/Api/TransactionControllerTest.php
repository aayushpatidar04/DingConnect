<?php

namespace Tests\Feature\Api;

use App\Models\Country;
use App\Models\Operator;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_retailer_cannot_view_another_retailers_transaction(): void
    {
        $owner = User::factory()->create([
            'role' => 'retailer',
            'kyc_status' => 'approved',
            'is_active' => true,
        ]);
        $otherRetailer = User::factory()->create([
            'role' => 'retailer',
            'kyc_status' => 'approved',
            'is_active' => true,
        ]);
        $country = Country::create(['name' => 'United Kingdom', 'iso_code' => 'GB']);
        $operator = Operator::create([
            'name' => 'Test Operator',
            'slug' => 'test-operator',
            'provider_code' => 'test-provider',
            'country_id' => $country->id,
        ]);
        $transaction = Transaction::create([
            'user_id' => $owner->id,
            'mobile_number' => '447700900123',
            'operator_id' => $operator->id,
            'country_id' => $country->id,
            'amount' => 10,
            'currency' => 'GBP',
            'status' => 'processing',
        ]);
        $token = $otherRetailer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/retailer/transactions/{$transaction->id}")
            ->assertNotFound();
    }
}
