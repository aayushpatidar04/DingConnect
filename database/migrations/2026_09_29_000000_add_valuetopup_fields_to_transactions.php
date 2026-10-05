<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('gateway')->default('ding')->after('redemption_type');
            $table->unsignedBigInteger('valuetopup_transaction_id')->nullable()->after('gateway');
            $table->string('valuetopup_correlation_id')->nullable()->after('valuetopup_transaction_id');
            $table->json('valuetopup_response')->nullable()->after('valuetopup_correlation_id');
            $table->index(['gateway', 'valuetopup_correlation_id']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'gateway',
                'valuetopup_transaction_id',
                'valuetopup_correlation_id',
                'valuetopup_response',
            ]);
        });
    }
};
