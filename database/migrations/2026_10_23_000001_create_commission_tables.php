<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Levels: only the minimum is stored, the range is derived from the next level.
        Schema::create('commission_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('min_connections')->default(0)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Rate bands: any number per level + operator, each for its own amount range.
        Schema::create('commission_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_level_id')->constrained('commission_levels')->cascadeOnDelete();
            $table->foreignId('operator_id')->nullable()->constrained('operators')->cascadeOnDelete(); // null = all operators
            $table->decimal('min_amount', 10, 2)->default(0);
            $table->decimal('max_amount', 10, 2)->nullable(); // null = no upper limit
            $table->decimal('first_topup_amount', 10, 2)->default(0);
            $table->decimal('first_bonus_amount', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['commission_level_id', 'operator_id']);
        });

        // Ongoing bands for a rate: top-up number from..to (null = open ended) pays amount.
        Schema::create('commission_rate_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_rate_id')->constrained('commission_rates')->cascadeOnDelete();
            $table->unsignedInteger('from_sequence');
            $table->unsignedInteger('to_sequence')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('retailer_commission_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('commission_level_id')->nullable()->constrained('commission_levels')->nullOnDelete();
            $table->unsignedInteger('actual_connections')->default(0);
            $table->unsignedInteger('level_connections')->default(0);
            $table->boolean('auto_level_enabled')->default(false);
            $table->timestamp('level_assigned_at')->nullable();
            $table->timestamps();
        });

        Schema::create('retailer_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // owner
            $table->string('account_key', 150)->unique(); // s:<serial> or m:<mobile>
            $table->unsignedBigInteger('allowed_number_id')->nullable();
            $table->string('account_number', 100)->nullable();
            $table->foreignId('operator_id')->nullable()->constrained('operators')->nullOnDelete();
            $table->unsignedBigInteger('first_transaction_id')->nullable();
            $table->unsignedInteger('topups_count')->default(0);
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('retailer_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->unique()->constrained('transactions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('retailer_connection_id')->nullable()->constrained('retailer_connections')->nullOnDelete();
            $table->foreignId('commission_level_id')->nullable()->constrained('commission_levels')->nullOnDelete();
            $table->foreignId('commission_rate_id')->nullable()->constrained('commission_rates')->nullOnDelete();
            $table->foreignId('operator_id')->nullable()->constrained('operators')->nullOnDelete();
            $table->string('type', 20)->nullable(); // first | ongoing
            $table->unsignedInteger('topup_sequence')->nullable();
            $table->decimal('recharge_amount', 10, 2)->default(0);
            $table->decimal('topup_amount', 10, 2)->default(0);
            $table->decimal('bonus_amount', 10, 2)->default(0);
            $table->decimal('commission_amount', 10, 2)->default(0);
            $table->string('status', 20)->default('credited'); // credited | skipped
            $table->string('skip_reason', 100)->nullable();
            $table->timestamp('credited_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });

        Schema::create('retailer_level_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_level_id')->nullable()->constrained('commission_levels')->nullOnDelete();
            $table->foreignId('to_level_id')->nullable()->constrained('commission_levels')->nullOnDelete();
            $table->string('change_type', 30); // assigned | upgrade | downgrade | removed | auto_upgrade | settings
            $table->unsignedInteger('actual_connections')->default(0);
            $table->unsignedInteger('level_connections_before')->default(0);
            $table->unsignedInteger('level_connections_after')->default(0);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retailer_level_changes');
        Schema::dropIfExists('retailer_commissions');
        Schema::dropIfExists('retailer_connections');
        Schema::dropIfExists('retailer_commission_profiles');
        Schema::dropIfExists('commission_rate_steps');
        Schema::dropIfExists('commission_rates');
        Schema::dropIfExists('commission_levels');
    }
};
