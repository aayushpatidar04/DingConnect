<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('retailer_commissions', function (Blueprint $table) {
            $table->index(
                ['retailer_connection_id', 'user_id', 'commission_level_id', 'operator_id', 'status'],
                'retailer_commissions_sequence_lookup_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('retailer_commissions', function (Blueprint $table) {
            $table->dropIndex('retailer_commissions_sequence_lookup_index');
        });
    }
};
