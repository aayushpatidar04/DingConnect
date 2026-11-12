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
        Schema::table('sim_provider_comparisons', function (Blueprint $table) {
            $table->foreignId('operator_id')->nullable()->constrained('operators')->nullOnDelete();
            $table->string('reconciliation_month', 7)->nullable(); // e.g. '2026-11'
        });
    }

    public function down(): void
    {
        Schema::table('sim_provider_comparisons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('operator_id');
            $table->dropColumn('reconciliation_month');
        });
    }
};
