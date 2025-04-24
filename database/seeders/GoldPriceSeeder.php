<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\GoldPrice;
class GoldPriceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        GoldPrice::create([

            'price_irr' => 93442500, // Example price in IRR
            'timestamp' => now(), // Current timestamp
        ]);

        GoldPrice::create([
            'price_irr' => 81481520, // Example price in IRR per gram
            'timestamp' => now()->subDay(), // One day ago
        ]);

        GoldPrice::create([
            'price_irr' => 71410000,
            'timestamp' => now()->subDays(2),
        ]);
    }
}
