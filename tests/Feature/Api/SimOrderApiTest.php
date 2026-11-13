<?php

namespace Tests\Feature\Api;

use App\Models\SimOrder;
use App\Models\SimOrderProduct;
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
        $product = $this->createProduct();
        $order->items()->create([
            'sim_order_product_id' => $product->id,
            'category' => $product->category,
            'unit_price' => $product->price,
            'quantity' => 30,
        ]);
        SimOrder::factory()->for($otherRetailer)->pending()->create(['quantity' => 20]);

        $response = $this->withToken($retailer->createToken('test')->plainTextToken)
            ->getJson('/api/retailer/sim-orders');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sim_order_limit', 80)
            ->assertJsonPath('data.stats.total', 1)
            ->assertJsonPath('data.orders.data.0.id', $order->id)
            ->assertJsonPath('data.orders.data.0.items.0.provider_name', 'Example SIM Provider')
            ->assertJsonPath('data.orders.data.0.items.0.unit_price', '12.50')
            ->assertJsonMissingPath('data.orders.data.1')
            ->assertJsonFragment([
                'id' => $product->id,
                'provider_name' => 'Example SIM Provider',
                'category' => '10GBP',
                'price' => '12.50',
            ]);
    }

    public function test_retailer_can_place_sim_order_within_limit_and_admin_is_notified(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $product = $this->createProduct();
        $retailer = User::factory()->create([
            'role' => 'retailer',
            'kyc_status' => 'approved',
            'is_active' => true,
            'sim_order_limit' => 80,
        ]);

        $response = $this->withToken($retailer->createToken('test')->plainTextToken)
            ->postJson('/api/retailer/sim-orders', [
                'items' => [
                    ['sim_order_product_id' => $product->id, 'quantity' => 25],
                    ['sim_order_product_id' => $this->createProduct('Second SIM Provider', 'Standard', 8)->id, 'quantity' => 35],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order.quantity', 60)
            ->assertJsonPath('data.order.items.0.sim_order_product_id', $product->id)
            ->assertJsonPath('data.order.items.0.provider_name', 'Example SIM Provider')
            ->assertJsonPath('data.order.items.0.unit_price', '12.50')
            ->assertJsonPath('data.order.status', SimOrder::STATUS_PENDING);
        $this->assertDatabaseHas('sim_orders', [
            'user_id' => $retailer->id,
            'quantity' => 60,
            'status' => SimOrder::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('sim_order_items', [
            'sim_order_product_id' => $product->id,
            'quantity' => 25,
            'unit_price' => '12.50',
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
        $product = $this->createProduct();

        $this->withToken($retailer->createToken('test')->plainTextToken)
            ->postJson('/api/retailer/sim-orders', [
                'items' => [
                    ['sim_order_product_id' => $product->id, 'quantity' => 51],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');
        $this->assertDatabaseCount('sim_orders', 0);
    }

    public function test_retailer_without_custom_limit_uses_default_limit_of_500(): void
    {
        $retailer = User::factory()->create([
            'role' => 'retailer',
            'kyc_status' => 'approved',
            'is_active' => true,
            'sim_order_limit' => null,
        ]);
        $product = $this->createProduct();

        $this->withToken($retailer->createToken('test')->plainTextToken)
            ->getJson('/api/retailer/sim-orders')
            ->assertOk()
            ->assertJsonPath('data.sim_order_limit', 500);

        $this->withToken($retailer->createToken('test')->plainTextToken)
            ->postJson('/api/retailer/sim-orders', [
                'items' => [
                    ['sim_order_product_id' => $product->id, 'quantity' => 500],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.order.quantity', 500);

        $this->assertDatabaseHas('sim_orders', [
            'user_id' => $retailer->id,
            'quantity' => 500,
            'status' => SimOrder::STATUS_PENDING,
        ]);
    }

    public function test_retailer_cannot_order_a_product_that_does_not_exist(): void
    {
        $retailer = User::factory()->create([
            'role' => 'retailer',
            'kyc_status' => 'approved',
            'is_active' => true,
        ]);

        $this->withToken($retailer->createToken('test')->plainTextToken)
            ->postJson('/api/retailer/sim-orders', [
                'items' => [
                    ['sim_order_product_id' => 999999, 'quantity' => 1],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.sim_order_product_id');

        $this->assertDatabaseCount('sim_orders', 0);
        $this->assertDatabaseCount('sim_order_items', 0);
    }

    public function test_admin_lists_sim_orders_and_updates_status(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $retailer = User::factory()->create(['role' => 'retailer', 'is_active' => true]);
        $order = SimOrder::factory()->for($retailer)->pending()->create(['quantity' => 30]);
        $product = $this->createProduct();
        $order->items()->create([
            'sim_order_product_id' => $product->id,
            'category' => $product->category,
            'unit_price' => $product->price,
            'quantity' => 30,
        ]);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/sim-orders?status=pending')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.orders.data.0.id', $order->id)
            ->assertJsonPath('data.orders.data.0.retailer.sim_order_limit', 500)
            ->assertJsonPath('data.orders.data.0.items.0.provider_name', 'Example SIM Provider')
            ->assertJsonFragment([
                'id' => $product->id,
                'provider_name' => 'Example SIM Provider',
                'category' => '10GBP',
                'price' => '12.50',
            ])
            ->assertJsonPath('data.stats.pending', 1);

        $this->withToken($token)
            ->putJson("/api/admin/sim-orders/{$order->id}", [
                'status' => SimOrder::STATUS_DISPATCHED,
                'tracking_notes' => 'Dispatched by courier.',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order.status', SimOrder::STATUS_DISPATCHED)
            ->assertJsonPath('data.order.tracking_notes', 'Dispatched by courier.')
            ->assertJsonPath('data.order.items.0.unit_price', '12.50');

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

    private function createProduct(
        string $providerName = 'Example SIM Provider',
        string $category = '10GBP',
        float $price = 12.5,
    ): SimOrderProduct {
        return SimOrderProduct::create([
            'provider_name' => $providerName,
            'category' => $category,
            'price' => $price,
        ]);
    }
}
