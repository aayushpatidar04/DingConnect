<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posters', function (Blueprint $table) {
            $table->dropForeign(['commission_level_id']);
            $table->dropIndex(['commission_level_id']);
            $table->dropColumn('commission_level_id');
        });

        Schema::create('poster_levels', function (Blueprint $table) {
            $table->foreignId('poster_id')->constrained('posters')->cascadeOnDelete();
            $table->foreignId('commission_level_id')->constrained('commission_levels')->cascadeOnDelete();
            $table->primary(['poster_id', 'commission_level_id']);
        });

        Schema::create('poster_retailers', function (Blueprint $table) {
            $table->foreignId('poster_id')->constrained('posters')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['poster_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poster_retailers');
        Schema::dropIfExists('poster_levels');
        Schema::table('posters', function (Blueprint $table) {
            $table->foreignId('commission_level_id')->nullable()
                ->constrained('commission_levels')->nullOnDelete()
                ->after('audience');
        });
    }
};
