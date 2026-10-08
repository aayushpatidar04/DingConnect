<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('allowed_numbers'); // existing rows are discarded

        Schema::create('allowed_number_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // uploaded by
            $table->string('name')->unique();   // original file name, unique
            $table->string('path');             // path on the storage disk
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->timestamps();
        });

        Schema::create('allowed_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // added by
            $table->string('mobile', 50);
            $table->string('serial', 100);
            $table->string('provider', 100)->nullable();   // was operator_name
            $table->string('country', 10)->nullable();
            $table->string('note')->nullable();
            $table->string('file_name')->nullable()->index(); // null = manual entry
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['mobile', 'serial']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allowed_numbers');
        Schema::dropIfExists('allowed_number_files');
    }
};