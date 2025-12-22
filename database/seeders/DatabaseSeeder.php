<?php

namespace Database\Seeders;

use App\Models\Domain;
use App\Models\Server;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{

    public function run(): void
    {
         $server = Server::create([
        'name' => 'Basic VPS',
        'cpu_speed' => '2.4GHz',
        'cpu_cores' => 2,
        'ram' => '4GB',
        'category' => 'VPS',
        'storage' => 50,
        'storage_type' => 'SSD',
        'datacenter_location' => 'Dubai',
        'os' => 'Ubuntu 20.04',
        'status' => 'available'
    ]);

    $server->createProduct([
        'price_monthly' => 29.99,
        'price_yearly' => 299.99,
        'description' => 'سيرفر افتراضي أساسي'
    ]);

    $domain = Domain::create([
        'name' => 'example',
        'tld' => 'com',
        'available' => true
    ]);

    $domain->createProduct([
        'price_monthly' => 9.99,
        'price_yearly' => 99.99,
        'description' => 'نطاق .com مميز'
    ]);
    }
}
