<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\DingConnectService;
use App\Services\ValuetopupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_retailer_dashboard_requires_a_sanctum_token(): void
    {
        $this->getJson('/api/retailer/dashboard')->assertUnauthorized();
    }

    public function test_retailer_dashboard_returns_stats_for_an_approved_retailer(): void
    {
        $user = User::factory()->create([
            'role' => 'retailer',
            'kyc_status' => 'approved',
            'is_active' => true,
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/retailer/dashboard');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.stats.wallet_balance', 0)
            ->assertJsonPath('data.stats.total_transactions', 0);
    }

    public function test_pending_kyc_retailer_receives_a_json_forbidden_response(): void
    {
        $user = User::factory()->create([
            'role' => 'retailer',
            'kyc_status' => 'pending',
            'is_active' => true,
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/retailer/dashboard');

        $response->assertForbidden()
            ->assertJsonPath('message', 'Your KYC is still under review. You can log in once it is approved.');
    }

    public function test_retailer_cannot_access_the_admin_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'retailer', 'is_active' => true]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/admin/dashboard')->assertForbidden();
    }

    public function test_admin_dashboard_returns_admin_metrics_as_json(): void
    {
        $this->mock(DingConnectService::class)
            ->shouldReceive('getBalance')
            ->once()
            ->andReturn(['data' => ['ResultCode' => 1, 'Balance' => 12.5, 'CurrencyIso' => 'GBP']]);
        $this->mock(ValuetopupService::class)
            ->shouldReceive('balance')
            ->once()
            ->andReturn(['responseCode' => '000', 'payLoad' => ['balance' => 23.5]]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/admin/dashboard');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.stats.total_retailers', 0)
            ->assertJsonPath('data.stats.ding_balance.balance', '12.50')
            ->assertJsonPath('data.stats.prepay_nation_balance.balance', '23.50');
    }
}
