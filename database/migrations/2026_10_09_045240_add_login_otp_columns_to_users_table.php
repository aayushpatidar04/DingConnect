<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('login_otp_hash')->nullable();
            $table->timestamp('login_otp_expires_at')->nullable();
            $table->unsignedTinyInteger('login_otp_attempts')->default(0);
            $table->timestamp('login_otp_last_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'login_otp_hash',
                'login_otp_expires_at',
                'login_otp_attempts',
                'login_otp_last_sent_at',
            ]);
        });
    }
};