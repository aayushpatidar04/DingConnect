<?php

namespace Tests\Feature\Api\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetailerControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_approve_a_retailer_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $token = $admin->createToken('admin-app')->plainTextToken;

        $created = $this->withToken($token)->postJson('/api/admin/retailers', [
            'name' => 'New Retailer',
            'email' => 'new-retailer@example.test',
            'phone' => '447700900222',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $created->assertCreated()
            ->assertJsonPath('data.role', 'retailer')
            ->assertJsonPath('data.kyc_status', 'pending');
        $this->assertNotNull($created->json('data.email_verified_at'));
        $retailerId = $created->json('data.id');
        $this->assertDatabaseHas('wallets', ['user_id' => $retailerId]);

        $approved = $this->withToken($token)->postJson("/api/admin/retailers/{$retailerId}/approve");

        $approved->assertOk()
            ->assertJsonPath('data.kyc_status', 'approved')
            ->assertJsonPath('data.is_active', true);
        $this->assertDatabaseHas('users', [
            'id' => $retailerId,
            'role' => 'retailer',
            'kyc_status' => 'approved',
        ]);
        $this->assertDatabaseHas('model_has_roles', ['model_id' => $retailerId]);
    }
}
