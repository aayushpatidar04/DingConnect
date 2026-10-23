<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('retailer_commission_profiles', function (Blueprint $table) {
            // How many commission-paying recharges the retailer has had on their current level.
            // The next paying recharge is position (count + 1): 1 = first top-up, 2+ = ongoing.
            $table->unsignedInteger('commission_topups_count')->default(0)->after('level_connections');
        });
    }

    public function down(): void
    {
        Schema::table('retailer_commission_profiles', function (Blueprint $table) {
            $table->dropColumn('commission_topups_count');
        });
    }
};
