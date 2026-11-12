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

    public function test_admin_can_bulk_update_sim_limit_for_selected_retailers_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $selectedRetailers = User::factory()->count(2)->create(['role' => 'retailer', 'is_active' => true]);
        $unselectedRetailer = User::factory()->create(['role' => 'retailer', 'is_active' => true]);

        $this->withToken($admin->createToken('admin-app')->plainTextToken)
            ->postJson('/api/admin/retailers/bulk-sim-limit', [
                'target' => 'selected',
                'retailer_ids' => $selectedRetailers->pluck('id')->all(),
                'sim_order_limit' => 35,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.target', 'selected')
            ->assertJsonPath('data.sim_order_limit', 35)
            ->assertJsonPath('data.updated_count', 2);

        foreach ($selectedRetailers as $retailer) {
            $this->assertDatabaseHas('users', [
                'id' => $retailer->id,
                'sim_order_limit' => 35,
            ]);
        }
        $this->assertDatabaseHas('users', [
            'id' => $unselectedRetailer->id,
            'sim_order_limit' => null,
        ]);
    }

    public function test_admin_can_bulk_update_sim_limit_for_all_retailers_without_updating_admins(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $retailers = User::factory()->count(2)->create(['role' => 'retailer', 'is_active' => true]);

        $this->withToken($admin->createToken('admin-app')->plainTextToken)
            ->postJson('/api/admin/retailers/bulk-sim-limit', [
                'target' => 'all',
                'retailer_ids' => [],
                'sim_order_limit' => 25,
            ])
            ->assertOk()
            ->assertJsonPath('data.target', 'all')
            ->assertJsonPath('data.updated_count', 2);

        foreach ($retailers as $retailer) {
            $this->assertDatabaseHas('users', [
                'id' => $retailer->id,
                'sim_order_limit' => 25,
            ]);
        }
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'sim_order_limit' => null,
        ]);
    }

    public function test_admin_bulk_sim_limit_rejects_non_retailer_ids(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $otherAdmin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->withToken($admin->createToken('admin-app')->plainTextToken)
            ->postJson('/api/admin/retailers/bulk-sim-limit', [
                'target' => 'selected',
                'retailer_ids' => [$otherAdmin->id],
                'sim_order_limit' => 25,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('retailer_ids.0');

        $this->assertDatabaseHas('users', [
            'id' => $otherAdmin->id,
            'sim_order_limit' => null,
        ]);
    }
}
