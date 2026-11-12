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
        Schema::create('sim_provider_comparisons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('provider_name')->nullable();
            $table->integer('total_internal')->default(0);
            $table->integer('total_provider')->default(0);
            $table->integer('matched_count')->default(0);
            $table->integer('missing_count')->default(0);
            $table->integer('additional_count')->default(0);
            $table->json('report_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sim_provider_comparisons');
    }
};
