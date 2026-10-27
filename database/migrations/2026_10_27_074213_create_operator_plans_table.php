<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('GBP');
            $table->json('benefits');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['operator_id', 'currency', 'price']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_plans');
    }
};
