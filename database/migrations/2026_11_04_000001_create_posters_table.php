<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posters', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image_path');
            $table->string('link_url')->nullable(); // where clicking the poster redirects
            $table->string('poster_type')->default('advertisement'); // advertisement | festive_greeting | announcement
            $table->string('audience')->default('all'); // all | selected | level
            $table->foreignId('commission_level_id')->nullable()->constrained('commission_levels')->nullOnDelete();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'expires_at']);
            $table->index('audience');
            $table->index('commission_level_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posters');
    }
};
