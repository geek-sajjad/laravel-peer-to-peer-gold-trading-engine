<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;


class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user =User::create([
            'name' => 'sajad',
            'email' => 'sajad@gmail.com',
            'password' => Hash::make('password'),
            'available_gold_balance' => 3.3792,
            'available_irr_balance' => 1000000000
        ]);

    }
}
