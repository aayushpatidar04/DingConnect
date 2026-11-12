<?php

namespace Tests\Feature\Api;

use App\Models\SimOrder;
use App\Models\User;
use App\Notifications\SimOrderPlacedNotification;
use App\Notifications\SimOrderStatusUpdatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SimOrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_retailer_lists_only_their_sim_orders_and_order_limit(): void
    {
        $retailer = User::factory()->create([
            'role' => 'retailer',
            'kyc_status' => 'approved',
            'is_active' => true,
            'sim_order_limit' => 80,
        ]);
        $otherRetailer = User::factory()->create([
            'role' => 'retailer',
            'kyc_status' => 'approved',
            'is_active' => true,
        ]);
        $order = SimOrder::factory()->for($retailer)->pending()->create(['quantity' => 30]);
        SimOrder::factory()->for($otherRetailer)->pending()->create(['quantity' => 20]);

        $response = $this->withToken($retailer->createToken('test')->plainTextToken)
            ->getJson('/api/retailer/sim-orders');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sim_order_limit', 80)
            ->assertJsonPath('data.stats.total', 1)
            ->assertJsonPath('data.orders.data.0.id', $order->id)
            ->assertJsonMissingPath('data.orders.data.1');
    }

    public function test_retailer_can_place_sim_order_within_limit_and_admin_is_notified(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $retailer = User::factory()->create([
            'role' => 'retailer',
            'kyc_status' => 'approved',
            'is_active' => true,
            'sim_order_limit' => 80,
        ]);

        $response = $this->withToken($retailer->createToken('test')->plainTextToken)
            ->postJson('/api/retailer/sim-orders', ['quantity' => 60]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order.quantity', 60)
            ->assertJsonPath('data.order.status', SimOrder::STATUS_PENDING);
        $this->assertDatabaseHas('sim_orders', [
            'user_id' => $retailer->id,
            'quantity' => 60,
            'status' => SimOrder::STATUS_PENDING,
        ]);
        Notification::assertSentTo($admin, SimOrderPlacedNotification::class);
    }

    public function test_retailer_receives_422_when_order_exceeds_configured_limit(): void
    {
        $retailer = User::factory()->create([
            'role' => 'retailer',
            'kyc_status' => 'approved',
            'is_active' => true,
            'sim_order_limit' => 50,
        ]);

        $this->withToken($retailer->createToken('test')->plainTextToken)
            ->postJson('/api/retailer/sim-orders', ['quantity' => 51])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');
        $this->assertDatabaseCount('sim_orders', 0);
    }

    public function test_retailer_without_custom_limit_uses_default_limit_of_50(): void
    {
        $retailer = User::factory()->create([
            'role' => 'retailer',
            'kyc_status' => 'approved',
            'is_active' => true,
            'sim_order_limit' => null,
        ]);

        $this->withToken($retailer->createToken('test')->plainTextToken)
            ->postJson('/api/retailer/sim-orders', ['quantity' => 51])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');
        $this->assertDatabaseCount('sim_orders', 0);
    }

    public function test_admin_lists_sim_orders_and_updates_status(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $retailer = User::factory()->create(['role' => 'retailer', 'is_active' => true]);
        $order = SimOrder::factory()->for($retailer)->pending()->create(['quantity' => 30]);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/sim-orders?status=pending')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.orders.data.0.id', $order->id)
            ->assertJsonPath('data.orders.data.0.retailer.sim_order_limit', 50)
            ->assertJsonPath('data.stats.pending', 1);

        $this->withToken($token)
            ->putJson("/api/admin/sim-orders/{$order->id}", [
                'status' => SimOrder::STATUS_DISPATCHED,
                'tracking_notes' => 'Dispatched by courier.',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order.status', SimOrder::STATUS_DISPATCHED)
            ->assertJsonPath('data.order.tracking_notes', 'Dispatched by courier.');

        $this->assertDatabaseHas('sim_orders', [
            'id' => $order->id,
            'status' => SimOrder::STATUS_DISPATCHED,
            'tracking_notes' => 'Dispatched by courier.',
        ]);
        $this->assertNotNull($order->fresh()->dispatched_at);
        Notification::assertSentTo($retailer, SimOrderStatusUpdatedNotification::class);
    }

    public function test_admin_can_update_retailer_sim_order_limit_over_api(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $retailer = User::factory()->create(['role' => 'retailer', 'is_active' => true]);

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->putJson("/api/admin/sim-orders/retailers/{$retailer->id}/limit", [
                'sim_order_limit' => 300,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.retailer_id', $retailer->id)
            ->assertJsonPath('data.sim_order_limit', 300);

        $this->assertDatabaseHas('users', [
            'id' => $retailer->id,
            'sim_order_limit' => 300,
        ]);
    }

    public function test_retailer_receives_403_from_admin_sim_order_routes(): void
    {
        $retailer = User::factory()->create([
            'role' => 'retailer',
            'kyc_status' => 'approved',
            'is_active' => true,
        ]);

        $this->withToken($retailer->createToken('test')->plainTextToken)
            ->getJson('/api/admin/sim-orders')
            ->assertForbidden();
    }

    public function test_admin_cannot_set_sim_order_limit_on_another_admin_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $otherAdmin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->putJson("/api/admin/sim-orders/retailers/{$otherAdmin->id}/limit", [
                'sim_order_limit' => 300,
            ])
            ->assertNotFound();
    }
}
