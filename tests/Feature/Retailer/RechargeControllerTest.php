<?php

namespace Tests\Feature\Retailer;

use App\Models\AllowedNumber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RechargeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_valuetopup_validation_returns_mobile_and_serial_numbers(): void
    {
        $retailer = User::factory()->create([
            'role' => 'retailer',
            'is_active' => true,
            'kyc_status' => 'approved',
        ]);
        AllowedNumber::query()->create([
            'user_id' => $retailer->id,
            'mobile' => '447700900123',
            'serial' => 'SIM-12345',
            'provider' => 'Vodafone',
            'country' => 'GB',
            'active' => true,
        ]);

        $response = $this->actingAs($retailer)->getJson(route(
            'retailer.recharge.validate-number',
            [
                'mobile_number' => '447700900123',
                'provider_code' => 'VF',
                'gateway' => 'valuetopup',
            ],
        ));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('mobile_number', '447700900123')
            ->assertJsonPath('serial_number', 'SIM-12345');
    }
}
