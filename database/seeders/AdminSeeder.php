<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'retailer']);

        // Create default admin
        $admin = User::create([
            'name' => 'System Admin',
            'email' => 'admin@mknetwork.com',
            'phone' => '9999999999',
            'password' => Hash::make('Admin@123'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $admin->assignRole('admin');

        // Create default settings
        $defaultSettings = [
            ['platform_name', 'MK Network - TopUp Platform', 'string', 'general', 'Platform display name'],
            ['platform_currency', 'GBP', 'string', 'general', 'Default currency'],
            ['platform_min_recharge', '10', 'number', 'recharge', 'Minimum recharge amount'],
            ['platform_max_recharge', '10000', 'number', 'recharge', 'Maximum recharge amount'],
            ['ding_sandbox_mode', 'true', 'boolean', 'dingconnect', 'Use DingConnect sandbox'],
            ['sms_enabled', 'false', 'boolean', 'sms', 'Enable SMS notifications'],
        ];

        foreach ($defaultSettings as [$key, $value, $type, $group, $description]) {
            Setting::create(compact('key', 'value', 'type', 'group', 'description'));
        }
    }
}
