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
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('available_gold_balance',8,3)->default(0);
            $table->decimal('available_irr_balance', 15, 2)->default(0);
            $table->decimal('frozen_gold_balance',8,3)->default(0);
            $table->decimal('frozen_irr_balance', 15, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['available_gold_balance', 'available_irr_balance', 'frozen_gold_balance', 'frozen_irr_balance']);  // Remove the columns in case of rollback
        });
    }
};
