<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('sku_code')->nullable()->after('receipt_number');
            $table->decimal('send_value', 10, 2)->nullable()->after('sku_code');
            $table->decimal('receive_value', 10, 2)->nullable()->after('send_value');
            $table->string('send_currency', 3)->nullable()->after('receive_value');
            $table->string('receive_currency', 3)->nullable()->after('send_currency');
            $table->text('display_text')->nullable()->after('receive_currency');
            $table->text('receipt_text')->nullable()->after('display_text');
            $table->string('validity_period')->nullable()->after('receipt_text');
            $table->json('benefits')->nullable()->after('validity_period');
            $table->string('region_code')->nullable()->after('benefits');
            $table->string('provider_code')->nullable()->after('region_code');
            $table->boolean('free_range')->default(false)->after('provider_code');
            $table->decimal('receive_value_excluding_tax', 10, 2)->nullable()->after('free_range');
            $table->text('description_markdown')->nullable()->after('receive_value_excluding_tax');
            $table->text('readmore_markdown')->nullable()->after('description_markdown');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'sku_code', 'send_value', 'receive_value',
                'send_currency', 'receive_currency',
                'display_text', 'receipt_text', 'validity_period',
                'benefits', 'region_code', 'provider_code',
                'free_range', 'receive_value_excluding_tax',
                'description_markdown', 'readmore_markdown',
            ]);
        });
    }
};
