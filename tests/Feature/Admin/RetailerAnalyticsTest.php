<?php

namespace Tests\Feature\Admin;

use App\Models\Country;
use App\Models\Operator;
use App\Models\RetailerConnection;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RetailerAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_is_redirected_from_retailer_analytics(): void
    {
        $this->get(route('admin.analytics.index'))
            ->assertRedirect(route('login'));
    }

    public function test_retailer_is_redirected_from_admin_analytics(): void
    {
        $retailer = User::factory()->create(['role' => 'retailer']);

        $this->actingAs($retailer)
            ->get(route('admin.analytics.index'))
            ->assertRedirect(route('login'));
    }

    public function test_network_and_each_connection_threshold_filter_both_reports(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $vodafone = $this->createOperator('Vodafone United Kingdom', 'vodafone');
        $ee = $this->createOperator('EE United Kingdom', 'ee');

        $fiveConnections = User::factory()->create(['shop_name' => 'Five Vodafone']);
        $nineConnections = User::factory()->create(['shop_name' => 'Nine EE']);
        $tenConnections = User::factory()->create(['shop_name' => 'Ten Vodafone']);
        $nineteenConnections = User::factory()->create(['shop_name' => 'Nineteen EE']);
        $twentyConnections = User::factory()->create(['shop_name' => 'Twenty Vodafone']);
        $mixedNetworks = User::factory()->create(['shop_name' => 'Mixed networks']);

        $this->addConnections($fiveConnections, $vodafone, 5, 'five');
        $this->addConnections($nineConnections, $ee, 9, 'nine');
        $this->addConnections($tenConnections, $vodafone, 10, 'ten');
        $this->addConnections($nineteenConnections, $ee, 19, 'nineteen');
        $this->addConnections($twentyConnections, $vodafone, 20, 'twenty');
        $this->addConnections($mixedNetworks, $vodafone, 4, 'mixed-vodafone');
        $this->addConnections($mixedNetworks, $ee, 16, 'mixed-ee');

        foreach ([
            5 => [[20, 10, 5], [5, 10, 20], 35],
            10 => [[20, 10], [10, 20], 30],
            20 => [[20], [20], 20],
        ] as $minimum => [$highest, $lowest, $totalConnections]) {
            $this->actingAs($admin)
                ->get(route('admin.analytics.index', [
                    'network_id' => $vodafone->id,
                    'connections' => $minimum,
                ]))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Admin/RetailerAnalytics/Index')
                    ->where('stats.total_connections', $totalConnections)
                    ->where('highestRetailers', fn (Collection $rows): bool => $rows->pluck('connections')->all() === $highest)
                    ->where('lowestRetailers', fn (Collection $rows): bool => $rows->pluck('connections')->all() === $lowest)
                );
        }
    }

    public function test_retailer_filters_and_activity_dates_update_the_analytics(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $network = $this->createOperator('Vodafone United Kingdom', 'vodafone');
        $retailer = User::factory()->create([
            'shop_name' => 'London Retailer',
            'city' => 'London',
            'is_active' => true,
            'kyc_status' => 'approved',
        ]);
        $this->addConnections($retailer, $network, 5, 'london');

        $otherRetailer = User::factory()->create([
            'shop_name' => 'Leeds Retailer',
            'city' => 'Leeds',
            'is_active' => false,
            'kyc_status' => 'rejected',
        ]);
        $this->addConnections($otherRetailer, $network, 5, 'leeds');

        $inPeriod = $this->createTransaction($retailer, $network, 25);
        $inPeriod->forceFill(['created_at' => '2026-03-15 12:00:00'])->save();
        $outsidePeriod = $this->createTransaction($retailer, $network, 99);
        $outsidePeriod->forceFill(['created_at' => '2026-04-15 12:00:00'])->save();
        $retailer->forceFill(['created_at' => '2026-03-10 12:00:00'])->save();
        $otherRetailer->forceFill(['created_at' => '2026-04-10 12:00:00'])->save();

        $this->actingAs($admin)
            ->get(route('admin.analytics.index', [
                'network_id' => $network->id,
                'connections' => 5,
                'status' => 'active',
                'kyc_status' => 'approved',
                'city' => 'London',
                'registered_from' => '2026-03-01',
                'registered_to' => '2026-03-31',
                'activity_from' => '2026-03-01',
                'activity_to' => '2026-03-31',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/RetailerAnalytics/Index')
                ->where('stats.retailers', 1)
                ->where('stats.total_transactions', 1)
                ->where('stats.successful_volume', 25)
                ->where('highestRetailers.0.id', $retailer->id)
                ->where('highestRetailers.0.transactions', 1)
                ->where('highestRetailers.0.successful_volume', 25)
            );
    }

    public function test_invalid_connection_threshold_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.analytics.index', ['connections' => 15]))
            ->assertSessionHasErrors('connections');
    }

    private function createOperator(string $name, string $slug): Operator
    {
        $country = Country::query()->firstOrCreate(
            ['iso_code' => 'GB'],
            ['name' => 'United Kingdom', 'iso_code_3' => 'GBR'],
        );

        return Operator::query()->create([
            'name' => $name,
            'slug' => $slug,
            'provider_code' => $slug,
            'country_id' => $country->id,
            'is_active' => true,
        ]);
    }

    private function addConnections(User $retailer, Operator $operator, int $count, string $key): void
    {
        for ($index = 1; $index <= $count; $index++) {
            RetailerConnection::query()->create([
                'user_id' => $retailer->id,
                'operator_id' => $operator->id,
                'account_key' => "{$key}:{$index}",
                'topups_count' => 0,
            ]);
        }
    }

    private function createTransaction(User $retailer, Operator $operator, int $amount): Transaction
    {
        return Transaction::query()->create([
            'user_id' => $retailer->id,
            'mobile_number' => '447700900123',
            'operator_id' => $operator->id,
            'country_id' => $operator->country_id,
            'amount' => $amount,
            'status' => 'success',
        ]);
    }
}
