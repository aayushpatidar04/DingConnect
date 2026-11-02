<?php

namespace Tests\Feature\Admin;

use App\Models\CommissionLevel;
use App\Models\RetailerCommissionProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RetailerIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_retailer_index_includes_the_commission_level_name(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $retailer = User::factory()->create([
            'role' => 'retailer',
            'is_active' => true,
        ]);
        $level = CommissionLevel::create([
            'name' => 'Gold',
            'min_connections' => 10,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        RetailerCommissionProfile::create([
            'user_id' => $retailer->id,
            'commission_level_id' => $level->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.retailers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Retailers/Index')
                ->where('retailers.data.0.retailer_commission_profile.level.name', 'Gold')
            );
    }
}
