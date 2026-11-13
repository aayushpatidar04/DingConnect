<?php

namespace Tests\Feature\Api;

use App\Models\SimOrderProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimOrderProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_create_update_and_delete_sim_products(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $token = $admin->createToken('test')->plainTextToken;
        $product = SimOrderProduct::where('provider_name', 'EE')
            ->where('category', '10GBP')
            ->firstOrFail();

        $this->withToken($token)
            ->getJson('/api/admin/sim-orders/products')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment([
                'id' => $product->id,
                'provider_name' => 'EE',
                'category' => '10GBP',
                'price' => '10.00',
            ]);

        $createResponse = $this->withToken($token)
            ->postJson('/api/admin/sim-orders/products', [
                'provider_name' => 'CustomNetwork',
                'category' => '50GBP',
                'price' => '45.00',
            ]);

        $createResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sim_product.provider_name', 'CustomNetwork')
            ->assertJsonPath('data.sim_product.category', '50GBP')
            ->assertJsonPath('data.sim_product.price', '45.00');

        $createdProductId = $createResponse->json('data.sim_product.id');
        $this->assertDatabaseHas('sim_order_products', [
            'id' => $createdProductId,
            'provider_name' => 'CustomNetwork',
            'category' => '50GBP',
            'price' => '45.00',
        ]);

        $this->withToken($token)
            ->putJson('/api/admin/sim-orders/product-prices', [
                'products' => [
                    ['id' => $createdProductId, 'price' => '47.50'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.updated_count', 1)
            ->assertJsonPath('data.sim_products.0.price', '47.50');

        $this->assertDatabaseHas('sim_order_products', [
            'id' => $createdProductId,
            'price' => '47.50',
        ]);

        $this->withToken($token)
            ->deleteJson("/api/admin/sim-orders/products/{$createdProductId}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sim_product_id', $createdProductId);

        $this->assertDatabaseMissing('sim_order_products', ['id' => $createdProductId]);
    }

    public function test_retailer_cannot_manage_sim_products(): void
    {
        $retailer = User::factory()->create([
            'role' => 'retailer',
            'kyc_status' => 'approved',
            'is_active' => true,
        ]);

        $this->withToken($retailer->createToken('test')->plainTextToken)
            ->getJson('/api/admin/sim-orders/products')
            ->assertForbidden();
    }

    public function test_admin_product_api_rejects_prices_with_more_than_two_decimal_places(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $product = SimOrderProduct::where('provider_name', 'EE')
            ->where('category', '10GBP')
            ->firstOrFail();

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->putJson('/api/admin/sim-orders/product-prices', [
                'products' => [
                    ['id' => $product->id, 'price' => '10.123'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('products.0.price');

        $this->assertDatabaseHas('sim_order_products', [
            'id' => $product->id,
            'price' => '10.00',
        ]);
    }
}
