<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DomainSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
  public function run(): void
{
    DB::table('domains')->insert([
        [
            'name' => 'google',
            'tld' => 'com',
            'price_monthly' => 12.00,
            'price_yearly' => 120.00,
            'available' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'name' => 'my-site',
            'tld' => 'net',
            'price_monthly' => 10.00,
            'price_yearly' => 95.00,
            'available' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]
    ]);
}
}
