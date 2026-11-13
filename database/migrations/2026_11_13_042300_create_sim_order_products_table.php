<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sim_order_products', function (Blueprint $table) {
            $table->id();
            $table->string('provider_name', 80);
            $table->string('category', 20);
            $table->decimal('price', 10, 2);
            $table->timestamps();

            $table->unique(['provider_name', 'category']);
        });

        $products = [
            'EE' => ['10GBP' => 10, '15GBP' => 15, '20GBP' => 20, '30GBP' => 30, '35GBP' => 35],
            'Vodafone' => ['10GBP' => 10, '15GBP' => 15, '20GBP' => 20, '30GBP' => 30, '40GBP' => 40],
            'O2' => ['10GBP' => 10, '15GBP' => 15, '20GBP' => 20, '30GBP' => 30],
            'Three' => ['10GBP' => 10, '15GBP' => 15, '20GBP' => 20, '35GBP' => 35],
            'Giffgaff' => ['10GBP' => 10, '15GBP' => 15, '20GBP' => 20, '25GBP' => 25],
        ];

        foreach ($products as $providerName => $categories) {
            foreach ($categories as $category => $price) {
                DB::table('sim_order_products')->insert([
                    'provider_name' => $providerName,
                    'category' => $category,
                    'price' => $price,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('sim_order_items', function (Blueprint $table) {
            $table->foreignId('sim_order_product_id')
                ->nullable()
                ->after('operator_id')
                ->constrained('sim_order_products')
                ->nullOnDelete();
            $table->decimal('unit_price', 10, 2)->nullable()->after('quantity');
            $table->foreignId('operator_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('sim_order_items')->whereNotNull('sim_order_product_id')->exists()) {
            throw new RuntimeException('SIM product orders exist, so the product pricing migration cannot be rolled back safely.');
        }

        Schema::table('sim_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sim_order_product_id');
            $table->dropColumn('unit_price');
            $table->foreignId('operator_id')->nullable(false)->change();
        });

        Schema::dropIfExists('sim_order_products');
    }
};
