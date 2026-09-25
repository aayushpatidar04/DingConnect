<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allowed_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // admin who added it
            $table->string('number')->unique(); // mobile number or serial number
            $table->string('type')->default('mobile'); // 'mobile' or 'serial'
            $table->string('operator_name')->nullable();
            $table->string('country')->nullable();
            $table->string('note')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'number', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allowed_numbers');
    }
};
