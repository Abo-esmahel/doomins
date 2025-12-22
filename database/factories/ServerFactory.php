<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Server>
 */
class ServerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
   public function definition(): array
{
    return [
        'name' => $this->faker->company . ' Server',
        'cpu_speed' => $this->faker->randomElement(['2.5 GHz', '3.8 GHz', '4.2 GHz']),
        'cpu_cores' => $this->faker->randomElement([2, 4, 8, 16, 32]),
        'ram' => $this->faker->randomElement(['8GB', '16GB', '32GB', '64GB']),
        'category' => $this->faker->randomElement(['VPS', 'Dedicated', 'Cloud']),
        'storage_type' => $this->faker->randomElement(['SSD', 'HDD', 'NVMe']),
        'storage' => $this->faker->numberBetween(100, 2000),
        'bandwidth' => $this->faker->randomElement(['1TB', '5TB', 'Unlimited']),
        'datacenter_location' => $this->faker->country,
        'os' => $this->faker->randomElement(['Ubuntu 22.04', 'CentOS 7', 'Windows Server 2022']),
        'price_monthly' => $this->faker->randomFloat(2, 10, 200),
        'price_yearly' => $this->faker->randomFloat(2, 100, 2000),
        'status' => $this->faker->randomElement(['available', 'sold_out', 'maintenance']),
    ];
}
}
