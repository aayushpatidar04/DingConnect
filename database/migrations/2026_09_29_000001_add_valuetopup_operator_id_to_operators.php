<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operators', function (Blueprint $table) {
            $table->unsignedInteger('valuetopup_operator_id')->nullable()->after('provider_code');
            $table->unique('valuetopup_operator_id');
            $table->index('valuetopup_operator_id');
        });
    }

    public function down(): void
    {
        Schema::table('operators', function (Blueprint $table) {
            $table->dropUnique('operators_valuetopup_operator_id_unique');
            $table->dropIndex('operators_valuetopup_operator_id_index');
            $table->dropColumn('valuetopup_operator_id');
        });
    }
};
