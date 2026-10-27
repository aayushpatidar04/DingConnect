<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Operator;
use App\Models\OperatorPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NetworkPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_edit_a_network_plan(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $operator = $this->createOperator();

        $this->actingAs($admin)
            ->post(route('admin.network-plans.store'), [
                'operator_id' => $operator->id,
                'price' => '5.00',
                'currency' => 'GBP',
                'benefits' => ['40 GB data', 'Unlimited minutes and texts'],
                'is_active' => true,
            ])
            ->assertRedirect();

        $plan = OperatorPlan::query()->sole();
        $this->assertSame(['40 GB data', 'Unlimited minutes and texts'], $plan->benefits);
        $this->assertModelExists($plan);

        $this->actingAs($admin)
            ->put(route('admin.network-plans.update', $plan), [
                'operator_id' => $operator->id,
                'price' => '5.00',
                'currency' => 'GBP',
                'benefits' => ['50 GB data'],
                'is_active' => false,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $plan->refresh();
        $this->assertSame(['50 GB data'], $plan->benefits);
        $this->assertFalse($plan->is_active);
    }

    public function test_admin_cannot_create_a_second_plan_for_the_same_network_and_currency_price(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $operator = $this->createOperator();
        OperatorPlan::query()->create([
            'operator_id' => $operator->id,
            'price' => '5.00',
            'currency' => 'GBP',
            'benefits' => ['40 GB data'],
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.network-plans.store'), [
                'operator_id' => $operator->id,
                'price' => '5.00',
                'currency' => 'GBP',
                'benefits' => ['50 GB data'],
                'is_active' => true,
            ])
            ->assertSessionHasErrors('price');

        $this->assertSame(1, OperatorPlan::query()->count());
    }

    public function test_retailers_and_public_visitors_see_only_published_plans(): void
    {
        $operator = $this->createOperator();
        OperatorPlan::query()->create([
            'operator_id' => $operator->id,
            'price' => '5.00',
            'currency' => 'GBP',
            'benefits' => ['40 GB data', 'Unlimited minutes and texts'],
            'is_active' => true,
        ]);
        OperatorPlan::query()->create([
            'operator_id' => $operator->id,
            'price' => '10.00',
            'currency' => 'GBP',
            'benefits' => ['Hidden plan'],
            'is_active' => false,
        ]);

        $this->get(route('network-offers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('NetworkOffers/Index')
                ->where('isRetailer', false)
                ->where('networks.0.name', 'Vodafone')
                ->where('networks.0.plans', fn ($plans) => count($plans) === 1)
                ->where('networks.0.plans.0.currency', 'GBP')
            );

        $retailer = User::factory()->create([
            'role' => 'retailer',
            'is_active' => true,
            'kyc_status' => 'approved',
        ]);

        $this->actingAs($retailer)
            ->get(route('retailer.network-offers'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('NetworkOffers/Index')
                ->where('isRetailer', true)
                ->where('networks.0.plans.0.benefits.0', '40 GB data')
            );
    }

    public function test_retailer_gets_plan_benefits_by_network_currency_and_price(): void
    {
        $operator = $this->createOperator();
        OperatorPlan::query()->create([
            'operator_id' => $operator->id,
            'price' => '5.00',
            'currency' => 'GBP',
            'benefits' => ['40 GB data', 'Unlimited minutes and texts'],
            'is_active' => true,
        ]);
        $retailer = User::factory()->create([
            'role' => 'retailer',
            'is_active' => true,
            'kyc_status' => 'approved',
        ]);

        $this->actingAs($retailer)
            ->getJson(route('retailer.recharge.plan-benefits', [
                'operator_id' => $operator->id,
                'price' => '5',
                'currency' => 'GBP',
            ]))
            ->assertOk()
            ->assertJsonPath('plan.benefits', ['40 GB data', 'Unlimited minutes and texts']);
    }

    public function test_retailer_cannot_manage_network_plans(): void
    {
        $retailer = User::factory()->create([
            'role' => 'retailer',
            'is_active' => true,
        ]);

        $this->actingAs($retailer)
            ->get(route('admin.network-plans.index'))
            ->assertRedirect('/login');
    }

    private function createOperator(): Operator
    {
        $country = Country::query()->create([
            'name' => 'United Kingdom',
            'iso_code' => 'GB',
            'iso_code_3' => 'GBR',
            'currency' => 'GBP',
            'is_active' => true,
        ]);

        return Operator::query()->create([
            'name' => 'Vodafone',
            'slug' => 'vodafone',
            'provider_code' => 'VF',
            'country_id' => $country->id,
            'is_active' => true,
        ]);
    }
}
