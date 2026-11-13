<?php

namespace Tests\Feature\Api;

use App\Models\Country;
use App\Models\Operator;
use App\Models\OperatorPlan;
use App\Models\Poster;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosterAndNetworkOfferApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_retailer_can_get_posters_and_network_offers(): void
    {
        $retailer = User::factory()->create([
            'role' => 'retailer',
            'kyc_status' => 'approved',
            'is_active' => true,
        ]);
        $token = $retailer->createToken('test')->plainTextToken;

        Poster::query()->create([
            'title' => 'Test Poster',
            'audience' => 'all',
            'poster_type' => 'advertisement',
            'image_path' => 'posters/test.jpg',
            'is_active' => true,
        ]);

        $country = Country::query()->create([
            'name' => 'United Kingdom',
            'iso_code' => 'GB',
            'iso_code_3' => 'GBR',
            'currency' => 'GBP',
            'is_active' => true,
        ]);

        $operator = Operator::query()->create([
            'name' => 'Vodafone',
            'slug' => 'vodafone',
            'provider_code' => 'VF',
            'country_id' => $country->id,
            'is_active' => true,
        ]);

        OperatorPlan::query()->create([
            'operator_id' => $operator->id,
            'price' => 10.00,
            'currency' => 'GBP',
            'benefits' => ['40 GB data'],
            'is_active' => true,
        ]);

        $this->withToken($token)
            ->getJson('/api/retailer/posters')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.posters.0.title', 'Test Poster');

        $this->withToken($token)
            ->getJson('/api/retailer/network-offers')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.networks.0.name', $operator->name);
    }

    public function test_admin_can_manage_posters_and_network_plans(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $token = $admin->createToken('test')->plainTextToken;

        $country = Country::query()->create([
            'name' => 'United Kingdom',
            'iso_code' => 'GB',
            'iso_code_3' => 'GBR',
            'currency' => 'GBP',
            'is_active' => true,
        ]);

        Operator::query()->create([
            'name' => 'Vodafone',
            'slug' => 'vodafone',
            'provider_code' => 'VF',
            'country_id' => $country->id,
            'is_active' => true,
        ]);

        $this->withToken($token)
            ->getJson('/api/admin/posters')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withToken($token)
            ->getJson('/api/admin/network-plans')
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
