<?php

namespace Tests\Feature;

use App\Models\SimOrder;
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

        $admin = User::factory()->create(['role' => 'admin']);
        $retailer = User::factory()->create([
            'role' => 'retailer',
            'kyc_status' => 'approved',
            'is_active' => true,
            'sim_order_limit' => 200,
        ]);

        $response = $this->actingAs($retailer)->post('/retailer/sim-orders', [
            'quantity' => 100,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('sim_orders', [
            'user_id' => $retailer->id,
            'quantity' => 100,
            'status' => 'pending',
        ]);

        Notification::assertSentTo($admin, SimOrderPlacedNotification::class);
    }

    public function test_retailer_cannot_exceed_sim_order_limit(): void
    {
        $retailer = User::factory()->create([
            'role' => 'retailer',
            'kyc_status' => 'approved',
            'is_active' => true,
            'sim_order_limit' => 50,
        ]);

        $response = $this->actingAs($retailer)->post('/retailer/sim-orders', [
            'quantity' => 100,
        ]);

        $response->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('sim_orders', 0);
    }

    public function test_admin_can_reject_sim_order_due_to_shortage(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);
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
        $admin = User::factory()->create(['role' => 'admin']);
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
}
