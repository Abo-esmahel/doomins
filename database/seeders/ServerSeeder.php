<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
{
    DB::table('servers')->insert([
        [
            'name' => 'Cloud-One',
            'cpu_speed' => '3.5 GHz',
            'cpu_cores' => 4,
            'ram' => '8GB',
            'category' => 'Cloud',
            'storage_type' => 'SSD',
            'storage' => 100,
            'datacenter_location' => 'USA',
            'os' => 'Ubuntu',
            'price_monthly' => 20.00,
            'price_yearly' => 200.00,
            'status' => 'available',
            'created_at' => now(),
            'updated_at' => now(),
        ]
    ]);
}
}
