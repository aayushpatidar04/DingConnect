<?php

namespace Tests\Feature;

use App\Models\SimOrder;
use App\Models\SimOrderProduct;
use App\Models\User;
use App\Notifications\SimOrderPlacedNotification;
use App\Notifications\SimOrderStatusUpdatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SimOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_retailer_can_place_a_sim_order_and_admin_is_notified(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $product = SimOrderProduct::where('provider_name', 'EE')
            ->where('category', '10GBP')
            ->firstOrFail();

        $retailer = User::factory()->create([
            'role' => 'retailer',
            'kyc_status' => 'approved',
            'is_active' => true,
            'sim_order_limit' => 200,
        ]);

        $response = $this->actingAs($retailer)->post('/retailer/sim-orders', [
            'items' => [
                ['sim_order_product_id' => $product->id, 'quantity' => 100],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('sim_orders', [
            'user_id' => $retailer->id,
            'quantity' => 100,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('sim_order_items', [
            'sim_order_product_id' => $product->id,
            'category' => '10GBP',
            'quantity' => 100,
            'unit_price' => '10.00',
        ]);

        Notification::assertSentTo($admin, SimOrderPlacedNotification::class);
    }

    public function test_retailer_cannot_exceed_sim_order_limit(): void
    {
        $product = SimOrderProduct::where('provider_name', 'EE')
            ->where('category', '10GBP')
            ->firstOrFail();

        $retailer = User::factory()->create([
            'role' => 'retailer',
            'kyc_status' => 'approved',
            'is_active' => true,
            'sim_order_limit' => 50,
        ]);

        $response = $this->actingAs($retailer)->post('/retailer/sim-orders', [
            'items' => [
                ['sim_order_product_id' => $product->id, 'quantity' => 100],
            ],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertDatabaseCount('sim_orders', 0);
    }

    public function test_admin_can_reject_sim_order_due_to_shortage(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $retailer = User::factory()->create([
            'role' => 'retailer',
            'kyc_status' => 'approved',
            'is_active' => true,
        ]);

        $order = SimOrder::create([
            'user_id' => $retailer->id,
            'quantity' => 100,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->put("/admin/sim-orders/{$order->id}", [
            'status' => 'rejected',
            'tracking_notes' => 'Rejected due to temporary SIM card shortage.',
        ]);

        $response->assertRedirect();
        $order->refresh();
        $this->assertEquals('rejected', $order->status);
        $this->assertEquals('Rejected due to temporary SIM card shortage.', $order->tracking_notes);

        Notification::assertSentTo($retailer, SimOrderStatusUpdatedNotification::class);
    }

    public function test_admin_can_update_retailer_sim_order_limit(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $retailer = User::factory()->create([
            'role' => 'retailer',
            'sim_order_limit' => 50,
        ]);

        $response = $this->actingAs($admin)->put("/admin/sim-orders/retailer-limit/{$retailer->id}", [
            'sim_order_limit' => 300,
        ]);

        $response->assertRedirect();
        $retailer->refresh();
        $this->assertEquals(300, $retailer->sim_order_limit);
    }

    public function test_admin_can_update_sim_product_prices_seen_by_retailers(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $retailer = User::factory()->create([
            'role' => 'retailer',
            'kyc_status' => 'approved',
            'is_active' => true,
        ]);
        $product = SimOrderProduct::where('provider_name', 'Vodafone')
            ->where('category', '10GBP')
            ->firstOrFail();
        $productIndex = SimOrderProduct::orderBy('id')->pluck('id')->search($product->id);

        $response = $this->actingAs($admin)->put('/admin/sim-orders/product-prices', [
            'products' => [
                ['id' => $product->id, 'price' => 12],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('sim_order_products', [
            'id' => $product->id,
            'price' => '12.00',
        ]);

        $this->actingAs($retailer)
            ->get('/retailer/sim-orders')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Retailer/SimOrders/Index')
                ->where("simProducts.{$productIndex}.price", '12.00')
            );

        $this->actingAs($retailer)->post('/retailer/sim-orders', [
            'items' => [
                ['sim_order_product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('sim_order_items', [
            'sim_order_product_id' => $product->id,
            'unit_price' => '12.00',
            'quantity' => 1,
        ]);
    }

    public function test_admin_can_add_and_delete_sim_products(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $response = $this->actingAs($admin)->post('/admin/sim-orders/products', [
            'provider_name' => 'CustomNetwork',
            'category' => '50GBP',
            'price' => 45.00,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('sim_order_products', [
            'provider_name' => 'CustomNetwork',
            'category' => '50GBP',
            'price' => '45.00',
        ]);

        $product = SimOrderProduct::where('provider_name', 'CustomNetwork')->firstOrFail();

        $this->actingAs($admin)->delete("/admin/sim-orders/products/{$product->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('sim_order_products', [
            'id' => $product->id,
        ]);
    }
}
