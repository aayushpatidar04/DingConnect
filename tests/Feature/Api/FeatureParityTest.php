<?php

namespace Tests\Feature\Api;

use App\Models\CommissionLevel;
use App\Models\Country;
use App\Models\Offer;
use App\Models\Operator;
use App\Models\RetailerCommission;
use App\Models\RetailerConnection;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_analytics_returns_filtered_json(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $network = $this->createOperator();
        $qualifiedRetailer = User::factory()->create(['role' => 'retailer', 'is_active' => true]);

        for ($number = 1; $number <= 5; $number++) {
            RetailerConnection::create([
                'user_id' => $qualifiedRetailer->id,
                'operator_id' => $network->id,
                'account_key' => "analytics:{$number}",
                'topups_count' => 0,
            ]);
        }

        $response = $this->withToken($admin->createToken('test')->plainTextToken)
            ->getJson("/api/admin/analytics?network_id={$network->id}&connections=5");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.stats.retailers', 1)
            ->assertJsonPath('data.highestRetailers.0.id', $qualifiedRetailer->id);
    }

    public function test_retailer_receives_403_from_admin_analytics(): void
    {
        $retailer = User::factory()->create(['role' => 'retailer', 'is_active' => true]);

        $this->withToken($retailer->createToken('test')->plainTextToken)
            ->getJson('/api/admin/analytics')
            ->assertForbidden();
    }

    public function test_retailer_commissions_return_only_their_own_filtered_rows(): void
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
        $operator = $this->createOperator();
        $ownedTransaction = $this->createTransaction($owner, $operator);
        $otherTransaction = $this->createTransaction($otherRetailer, $operator);
        $ownedCommission = RetailerCommission::create([
            'transaction_id' => $ownedTransaction->id,
            'user_id' => $owner->id,
            'operator_id' => $operator->id,
            'type' => 'first',
            'status' => 'credited',
            'recharge_amount' => 10,
            'commission_amount' => 1,
        ]);
        RetailerCommission::create([
            'transaction_id' => $otherTransaction->id,
            'user_id' => $otherRetailer->id,
            'operator_id' => $operator->id,
            'type' => 'ongoing',
            'status' => 'credited',
            'recharge_amount' => 10,
            'commission_amount' => 2,
        ]);

        $retailerResponse = $this->withToken($owner->createToken('test')->plainTextToken)
            ->getJson('/api/retailer/commissions?type=first');

        $retailerResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.commissions.data.0.id', $ownedCommission->id)
            ->assertJsonCount(1, 'data.commissions.data');
    }

    public function test_admin_commission_report_applies_type_filter_in_json_response(): void
    {
        $owner = User::factory()->create(['role' => 'retailer']);
        $operator = $this->createOperator();
        $ownedTransaction = $this->createTransaction($owner, $operator);
        $otherTransaction = $this->createTransaction($owner, $operator);
        $ownedCommission = RetailerCommission::create([
            'transaction_id' => $ownedTransaction->id,
            'user_id' => $owner->id,
            'operator_id' => $operator->id,
            'type' => 'first',
            'status' => 'credited',
            'recharge_amount' => 10,
            'commission_amount' => 1,
        ]);
        RetailerCommission::create([
            'transaction_id' => $otherTransaction->id,
            'user_id' => $owner->id,
            'operator_id' => $operator->id,
            'type' => 'ongoing',
            'status' => 'credited',
            'recharge_amount' => 10,
            'commission_amount' => 2,
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $response = $this->withToken($admin->createToken('test')->plainTextToken)
            ->getJson('/api/admin/commissions?type=first');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.commissions.data.0.id', $ownedCommission->id)
            ->assertJsonCount(1, 'data.commissions.data');
    }

    public function test_retailer_dashboard_and_eligible_offers_include_only_usable_offers(): void
    {
        $retailer = User::factory()->create([
            'role' => 'retailer',
            'kyc_status' => 'approved',
            'is_active' => true,
        ]);
        $token = $retailer->createToken('test')->plainTextToken;
        $usableOffer = Offer::create([
            'title' => 'Available offer',
            'type' => 'fixed',
            'value' => 1,
            'min_amount' => 5,
            'expires_at' => now()->addDay(),
            'audience' => 'all',
            'is_active' => true,
        ]);
        Offer::create([
            'title' => 'Amount too low',
            'type' => 'fixed',
            'value' => 2,
            'min_amount' => 20,
            'expires_at' => now()->addDay(),
            'audience' => 'all',
            'is_active' => true,
        ]);

        $dashboard = $this->withToken($token)->getJson('/api/retailer/dashboard');
        $dashboard->assertOk()
            ->assertJsonPath('data.offers.0.id', $usableOffer->id);

        $eligible = $this->withToken($token)
            ->getJson('/api/retailer/offers/eligible?amount=10');
        $eligible->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('offers.0.id', $usableOffer->id)
            ->assertJsonCount(1, 'offers');
    }

    public function test_admin_can_create_commission_level_with_json_response(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $response = $this->withToken($admin->createToken('test')->plainTextToken)
            ->postJson('/api/admin/commission-levels', [
                'name' => 'API level',
                'min_connections' => 7,
                'sort_order' => 2,
                'is_active' => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'API level');
        $this->assertDatabaseHas('commission_levels', [
            'name' => 'API level',
            'min_connections' => 7,
        ]);
    }

    public function test_admin_can_create_commission_rate_with_json_response(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $level = CommissionLevel::create([
            'name' => 'Rate level',
            'min_connections' => 3,
        ]);

        $response = $this->withToken($admin->createToken('test')->plainTextToken)
            ->postJson('/api/admin/commission-rates', [
                'commission_level_id' => $level->id,
                'min_amount' => 5,
                'max_amount' => 20,
                'first_topup_amount' => 0.5,
                'first_bonus_amount' => 0,
                'steps' => [
                    ['from_sequence' => 2, 'to_sequence' => null, 'amount' => 0.25],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.commission_level_id', $level->id)
            ->assertJsonPath('data.steps.0.from_sequence', 2);
        $this->assertDatabaseHas('commission_rates', [
            'commission_level_id' => $level->id,
            'min_amount' => 5,
        ]);
    }

    public function test_admin_can_assign_a_retailer_level_through_json_api(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $retailer = User::factory()->create(['role' => 'retailer', 'is_active' => true]);
        $level = CommissionLevel::create([
            'name' => 'Assigned level',
            'min_connections' => 5,
        ]);

        $response = $this->withToken($admin->createToken('test')->plainTextToken)
            ->putJson("/api/admin/retailer-levels/{$retailer->id}", [
                'level_id' => $level->id,
                'actual_connections' => 6,
                'reason' => 'API assignment',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);
        $this->assertDatabaseHas('retailer_commission_profiles', [
            'user_id' => $retailer->id,
            'commission_level_id' => $level->id,
            'actual_connections' => 6,
        ]);
    }

    public function test_admin_can_create_an_offer_through_json_api(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $expiresAt = now()->addWeek()->toISOString();

        $response = $this->withToken($admin->createToken('test')->plainTextToken)
            ->postJson('/api/admin/offers', [
                'title' => 'API offer',
                'type' => 'percent',
                'value' => 5,
                'expires_at' => $expiresAt,
                'audience' => 'all',
                'is_active' => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'API offer');
        $this->assertDatabaseHas('offers', [
            'title' => 'API offer',
            'created_by' => $admin->id,
        ]);
    }

    private function createOperator(): Operator
    {
        $country = Country::create([
            'name' => 'United Kingdom',
            'iso_code' => 'GB',
            'iso_code_3' => 'GBR',
            'is_active' => true,
        ]);

        return Operator::create([
            'name' => 'Test Network',
            'slug' => 'test-network',
            'provider_code' => 'test-network',
            'country_id' => $country->id,
            'is_active' => true,
        ]);
    }

    private function createTransaction(User $retailer, Operator $operator): Transaction
    {
        return Transaction::create([
            'user_id' => $retailer->id,
            'mobile_number' => '447700900123',
            'operator_id' => $operator->id,
            'country_id' => $operator->country_id,
            'amount' => 10,
            'currency' => 'GBP',
            'status' => 'success',
        ]);
    }
}
