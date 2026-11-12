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

    public function test_admin_can_update_sim_order_status_and_notify_retailer(): void
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
            'quantity' => 50,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->put("/admin/sim-orders/{$order->id}", [
            'status' => 'dispatched',
            'tracking_notes' => 'Royal Mail Tracking #RM123456789',
        ]);

        $response->assertRedirect();
        $order->refresh();
        $this->assertEquals('dispatched', $order->status);
        $this->assertEquals('Royal Mail Tracking #RM123456789', $order->tracking_notes);

        Notification::assertSentTo($retailer, SimOrderStatusUpdatedNotification::class);
    }
}
