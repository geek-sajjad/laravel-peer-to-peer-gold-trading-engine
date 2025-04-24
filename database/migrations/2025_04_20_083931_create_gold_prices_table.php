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

        Schema::create('gold_prices', function (Blueprint $table) {
            $table->id(); // auto increment primary key
            $table->decimal('price_irr', 15, 2); // Price of gold in IRR
            $table->timestamp('timestamp'); // When the price was recorded
            $table->timestamps(); // created_at and updated_at
        });


        Schema::table('gold_prices', function (Blueprint $table) {
            $table->index('timestamp');
            $table->index('price_irr');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gold_prices');
    }
};
