<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $singleTierProviders = ['Lebara', 'Lyca Mobile', 'Voxi', 'Smarty'];

        foreach ($singleTierProviders as $providerName) {
            DB::table('sim_order_products')->updateOrInsert(
                [
                    'provider_name' => $providerName,
                    'category' => 'Standard',
                ],
                [
                    'price' => 10.00,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('sim_order_products')->whereIn('provider_name', ['Lebara', 'Lyca Mobile', 'Voxi', 'Smarty'])->delete();
    }
};
