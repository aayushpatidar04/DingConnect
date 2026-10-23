<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 20); // percent | fixed
            $table->decimal('value', 10, 2);
            $table->decimal('max_cashback', 10, 2)->nullable(); // cap for percent offers
            $table->decimal('min_amount', 10, 2)->default(0);
            $table->decimal('max_amount', 10, 2)->nullable(); // null = no upper limit
            $table->timestamp('starts_at')->nullable(); // null = already started
            $table->timestamp('expires_at');
            $table->unsignedInteger('per_retailer_limit')->nullable(); // null = unlimited
            $table->unsignedInteger('total_limit')->nullable(); // null = unlimited
            $table->string('audience', 20)->default('all'); // all | selected
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'expires_at']);
        });

        // No rows = the offer applies to every provider.
        Schema::create('offer_operator', function (Blueprint $table) {
            $table->foreignId('offer_id')->constrained('offers')->cascadeOnDelete();
            $table->foreignId('operator_id')->constrained('operators')->cascadeOnDelete();
            $table->primary(['offer_id', 'operator_id']);
        });

        // Used when audience = selected.
        Schema::create('offer_retailer', function (Blueprint $table) {
            $table->foreignId('offer_id')->constrained('offers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['offer_id', 'user_id']);
        });

        Schema::create('offer_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained('offers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('transaction_id')->unique()->constrained('transactions')->cascadeOnDelete();
            $table->string('title'); // snapshot of the offer title
            $table->string('status', 20)->default('pending'); // pending | credited | void
            $table->decimal('recharge_amount', 10, 2)->default(0);
            $table->decimal('benefit_amount', 10, 2)->default(0); // promised at checkout, paid on success
            $table->timestamp('credited_at')->nullable();
            $table->timestamps();

            $table->index(['offer_id', 'user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_redemptions');
        Schema::dropIfExists('offer_retailer');
        Schema::dropIfExists('offer_operator');
        Schema::dropIfExists('offers');
    }
};
